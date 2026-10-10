<?php
/** In-app follow-ups. Delivery records survive notification deletion. */
class ReportReminder {
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }

    private function recipient(int $userId): array {
        $stmt = $this->db->prepare("SELECT id, user_type, barangay_id FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return in_array($user['user_type'] ?? '', ['admin', 'menro_staff', 'barangay_personnel'], true) ? $user : [];
    }

    private function clock(): DateTimeImmutable {
        return new DateTimeImmutable($this->db->query('SELECT CURRENT_TIMESTAMP')->fetchColumn());
    }

    private function scope(array $user, array $settings, DateTimeImmutable $now): array {
        // Escalation age starts at escalation, never at the original submission.
        $started = "CASE WHEN r.status = 'pending' THEN r.created_at ELSE COALESCE(
            NULLIF(r.escalated_at, '0000-00-00 00:00:00'),
            (SELECT MAX(e.escalated_at) FROM escalations e WHERE e.report_id = r.id)) END";
        $stage = "CASE WHEN r.status = 'pending' THEN 'pending' ELSE 'escalated' END";
        $where = "COALESCE(r.is_archived, 0) = 0 AND r.status IN ('pending', 'escalated_pending', 'escalated')
            AND ($started) <= ?";
        $params = [$now->modify('-' . $settings['days'] . ' days')->format('Y-m-d H:i:s')];
        if ($user['user_type'] === 'barangay_personnel') {
            $where .= ' AND r.barangay_id = ?';
            $params[] = (int)($user['barangay_id'] ?? 0);
        } else {
            $where .= " AND r.status IN ('escalated_pending', 'escalated')";
        }
        return [$where, $params, $started, $stage];
    }

    /** At most one scan/minute per recipient; at most 50 new reminders per scan. */
    public function processForUser(int $userId, bool $force = false): int {
        $settings = SettingsHelper::getReportReminderSettings();
        if (!$settings['enabled']) return 0;
        $user = $this->recipient($userId);
        if (!$user) return 0;
        $now = $this->clock();
        $time = $now->format('Y-m-d H:i:s');
        $signature = $settings['days'] . ':' . $user['user_type'] . ':' . (int)($user['barangay_id'] ?? 0);
        $ignore = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->db->prepare("$ignore INTO report_reminder_checks (user_id, checked_at, settings_key) VALUES (?, '1970-01-01 00:00:00', '')")->execute([$userId]);
        $claim = $this->db->prepare('UPDATE report_reminder_checks SET checked_at = ?, settings_key = ? WHERE user_id = ?'
            . ($force ? '' : ' AND (checked_at <= ? OR settings_key <> ?)'));
        $claim->execute($force ? [$time, $signature, $userId] : [$time, $signature, $userId, $now->modify('-60 seconds')->format('Y-m-d H:i:s'), $signature]);
        if (!$claim->rowCount() && !$force) return 0;

        try {
            [$where, $params, $started, $stage] = $this->scope($user, $settings, $now);
            $params[] = $userId;
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("SELECT r.id, r.title, r.status, $started AS started_at, $stage AS stage
                FROM reports r WHERE $where AND NOT EXISTS (
                    SELECT 1 FROM report_reminder_deliveries d WHERE d.report_id = r.id
                    AND d.stage = ($stage) AND d.started_at = ($started) AND d.user_id = ?)
                ORDER BY started_at, r.id LIMIT 50");
            $stmt->execute($params);
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $notification = new Notification($this->db);
            $delivery = $this->db->prepare("$ignore INTO report_reminder_deliveries
                (report_id, user_id, stage, started_at, delivered_at) VALUES (?, ?, ?, ?, ?)");
            $sent = 0;
            foreach ($reports as $report) {
                $delivery->execute([(int)$report['id'], $userId, $report['stage'], $report['started_at'], $time]);
                if (!$delivery->rowCount()) continue;
                $days = (int)floor(($now->getTimestamp() - (new DateTimeImmutable($report['started_at']))->getTimestamp()) / 86400);
                $pending = $report['stage'] === 'pending';
                $title = $pending ? 'Pending report needs attention' : 'Escalated report needs follow-up';
                $message = 'Report #' . $report['id'] . ' — ' . $report['title'] . ' has been '
                    . ($pending ? 'pending' : 'escalated') . " for $days day(s). "
                    . ($pending ? 'Please review this report.' : 'Please follow up with MENRO.');
                // A relative URL works in both browser requests and the CLI worker.
                $link = 'index.php?page=manage-report&id=' . rawurlencode(IdGuard::enc((int)$report['id']));
                if (!$notification->create($userId, $title, $message, 'report', 'fa-clock', '#0d8568', $link, $report['id'])) {
                    throw new RuntimeException('Could not save report reminder.');
                }
                $sent++;
            }
            $this->db->commit();
            return $sent;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->db->prepare('DELETE FROM report_reminder_checks WHERE user_id = ?')->execute([$userId]);
            throw $error;
        }
    }

    /** Current overdue queue, independent of read/deleted notification state. */
    public function summaryForUser(int $userId): array {
        $settings = SettingsHelper::getReportReminderSettings();
        $summary = ['enabled' => $settings['enabled'], 'days' => $settings['days'], 'pending' => 0, 'escalated' => 0, 'total' => 0, 'reports' => []];
        $user = $this->recipient($userId);
        if (!$settings['enabled'] || !$user) return $summary;
        $now = $this->clock();
        [$where, $params, $started, $stage] = $this->scope($user, $settings, $now);
        $stmt = $this->db->prepare("SELECT $stage AS stage, COUNT(*) AS total FROM reports r WHERE $where GROUP BY $stage");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $summary[$row['stage']] = (int)$row['total'];
        $summary['total'] = $summary['pending'] + $summary['escalated'];
        $stmt = $this->db->prepare("SELECT r.id, r.title, r.status, b.name AS barangay_name, $started AS started_at
            FROM reports r LEFT JOIN barangays b ON b.id = r.barangay_id WHERE $where ORDER BY started_at, r.id LIMIT 5");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $report) {
            $report['age_days'] = (int)floor(($now->getTimestamp() - (new DateTimeImmutable($report['started_at']))->getTimestamp()) / 86400);
            $report['url'] = 'index.php?page=manage-report&id=' . rawurlencode(IdGuard::enc((int)$report['id']));
            unset($report['id']);
            $summary['reports'][] = $report;
        }
        return $summary;
    }

    /** Best-effort web hook: reminder failure must not take down the app. */
    public static function tick(PDO $db, int $userId): void {
        try { (new self($db))->processForUser($userId); }
        catch (Throwable $error) { error_log('[Report reminders] ' . $error->getMessage()); }
    }
}
