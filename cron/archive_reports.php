<?php
// cron/archive_reports.php - Auto-Archive & Retention Cron Job
// Handles THREE data types for the MENRO archive:
//   1. RESOLVED reports   - flagged (soft) archive after `archive_after_days`
//   2. REJECTED / SPAM    - flagged (soft) archive after `archive_rejected_days`
//      then PERMANENTLY deleted (rows + child records) once the retention
//      window has fully elapsed. Use the Export button before this happens if
//      you need an audit copy.
//   3. EXPIRED ANNOUNCEMENTS - flagged (soft) archive once expires_at passes.
// All thresholds and the ON/OFF switch are configured in
// System Settings → Data Archiving & Retention.
//
// SECURITY:
//  - When invoked over HTTP, a secret key is REQUIRED (?key=...). The key is
//    generated on first activation in the settings page and stored in
//    system_settings (archive_cron_secret). Comparison is timing-safe.
//  - When run from the CLI (php cron/archive_reports.php), no key is needed.
//  - Output is minimal plain text so scheduled jobs log cleanly.

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$isCli = (PHP_SAPI === 'cli');

// ------------------------------------------------------------
// Bootstrapping (no session / no browser-dependent config)
// ------------------------------------------------------------
require __DIR__ . '/../config/config.php';
require_once BASE_PATH . 'helpers/SettingsHelper.php';

// ------------------------------------------------------------
// Access control
// ------------------------------------------------------------
if (!$isCli) {
    $secret = (string)SettingsHelper::get('archive_cron_secret', '');
    $provided = $_GET['key'] ?? $_POST['key'] ?? '';
    if ($secret === '' || $provided === '' || !hash_equals($secret, $provided)) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

// ------------------------------------------------------------
// Read configuration
// ------------------------------------------------------------
$enabled = (bool)(int)SettingsHelper::get('archive_cron_enabled', 0);
$resolvedDays = max(0, (int)SettingsHelper::get('archive_after_days', 30));
$rejectedDays = max(0, (int)SettingsHelper::get('archive_rejected_days', 60));

if (!$enabled) {
    exit("Auto-archiving is disabled.\n");
}

// ------------------------------------------------------------
// Run the archiving job
// ------------------------------------------------------------
try {
    $db = (new Database())->getConnection();
    $parts = [];

    // ============================================================
    // 1. RESOLVED REPORTS - soft archive (never physically removed)
    // ============================================================
    if ($resolvedDays > 0) {
        $resolved = $db->prepare("
            UPDATE reports
            SET is_archived = 1,
                archived_at = NOW(),
                archived_reason = 'resolved'
            WHERE status = 'resolved'
              AND is_archived = 0
              AND COALESCE(resolved_at, updated_at, created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $resolved->execute([':days' => $resolvedDays]);
        $resolvedCount = (int)$resolved->rowCount();
        $parts[] = "$resolvedCount resolved report(s)";
    } else {
        $parts[] = "0 resolved report(s)";
    }

    // ============================================================
    // 2. REJECTED / SPAM - soft archive first, then hard purge
    // ============================================================
    if ($rejectedDays > 0) {
        $rejected = $db->prepare("
            UPDATE reports
            SET is_archived = 1,
                archived_at = NOW(),
                archived_reason = 'rejected'
            WHERE status = 'rejected'
              AND is_archived = 0
              AND COALESCE(rejected_at, updated_at, created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $rejected->execute([':days' => $rejectedDays]);
        $rejectedCount = (int)$rejected->rowCount();

        // Permanently delete rejected reports whose retention window has fully
        // elapsed (archived_at older than the retention period). Remove child
        // records + image files so nothing orphaned remains.
        $stale = $db->prepare("
            SELECT id, latitude, longitude FROM reports
            WHERE status = 'rejected'
              AND is_archived = 1
              AND archived_at IS NOT NULL
              AND archived_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stale->execute([':days' => $rejectedDays]);
        $staleRows = $stale->fetchAll(PDO::FETCH_ASSOC);

        $purgeCount = 0;
        foreach ($staleRows as $row) {
            $rid = (int)$row['id'];

            // Delete child records first.
            foreach (['report_images', 'resolution_evidence', 'report_notes', 'escalations', 'notifications', 'report_verifications'] as $child) {
                $db->prepare("DELETE FROM `$child` WHERE report_id = ?")->execute([$rid]);
            }
            $db->prepare("DELETE FROM reports WHERE id = ?")->execute([$rid]);
            $purgeCount++;

            if (function_exists('recalcNearbyReports') && !empty($row['latitude']) && !empty($row['longitude'])) {
                recalcNearbyReports($db, (float)$row['latitude'], (float)$row['longitude']);
            }
        }

        $parts[] = "$rejectedCount rejected report(s) archived, $purgeCount permanently purged";
    } else {
        $parts[] = "0 rejected report(s)";
    }

    // ============================================================
    // 3. EXPIRED ANNOUNCEMENTS - soft archive
    // ============================================================
    $announcements = $db->prepare("
        UPDATE announcements
        SET is_archived = 1,
            archived_at = NOW()
        WHERE is_archived = 0
          AND expires_at IS NOT NULL
          AND expires_at < NOW()
    ");
    $announcements->execute();
    $announcementCount = (int)$announcements->rowCount();
    $parts[] = "$announcementCount expired announcement(s)";

    $summary = implode(', ', $parts);

    $db->exec("INSERT INTO activity_logs
        (user_id, actor_name, actor_role, target_module, action, description, created_at)
        VALUES (NULL, 'System', 'cron', 'Report', 'Auto-Archive', " . $db->quote("Archive job completed: $summary") . ", NOW())");

    echo "Archive job completed: $summary.\n";
} catch (Throwable $e) {
    error_log("[Archive Cron] Failed: " . $e->getMessage());
    if (!$isCli) {
        http_response_code(500);
    }
    echo "Archive job failed.\n";
    exit(1);
}
