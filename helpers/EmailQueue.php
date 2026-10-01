<?php
/** Optional report-email outbox. Authentication email always bypasses it. */
class EmailQueue {
    const MAX_ATTEMPTS = 5;
    const LEASE_SECONDS = 120; // Longer than the provider's 30 second timeout.
    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    // Explicit CLI installation only; no schema work on each enqueue/poll.
    public function install(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS email_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            recipient VARCHAR(254) NOT NULL,
            recipient_name VARCHAR(255) NOT NULL,
            subject VARCHAR(500) NOT NULL,
            html_body MEDIUMTEXT NOT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            available_at BIGINT NOT NULL,
            lease_until BIGINT DEFAULT NULL,
            lease_token VARCHAR(64) DEFAULT NULL,
            last_error VARCHAR(255) DEFAULT NULL,
            created_at BIGINT NOT NULL,
            finished_at BIGINT DEFAULT NULL,
            KEY idx_email_ready (status, available_at),
            KEY idx_email_lease (status, lease_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function enqueue(string $email, string $name, string $subject, string $html): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
        $stmt = $this->db->prepare('INSERT INTO email_queue (recipient, recipient_name, subject, html_body, available_at, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        return $stmt->execute([$email, $name, $subject, $html, time(), time()]);
    }

    private function claim(): ?array {
        $now = time();
        // Recover interrupted senders. Exhausted messages remain inspectable.
        $stmt = $this->db->prepare("UPDATE email_queue SET status = CASE WHEN attempts >= ? THEN 'failed' ELSE 'pending' END, lease_token = NULL, lease_until = NULL, last_error = 'Sender lease expired' WHERE status = 'sending' AND lease_until <= ?");
        $stmt->execute([self::MAX_ATTEMPTS, $now]);
        for ($race = 0; $race < 5; $race++) {
            $stmt = $this->db->prepare("SELECT id FROM email_queue WHERE status = 'pending' AND available_at <= ? ORDER BY available_at, id LIMIT 1");
            $stmt->execute([$now]);
            $id = $stmt->fetchColumn();
            if ($id === false) return null;
            $token = bin2hex(random_bytes(24));
            // Compare-and-set ensures competing workers cannot claim one row.
            $stmt = $this->db->prepare("UPDATE email_queue SET status = 'sending', attempts = attempts + 1, lease_token = ?, lease_until = ? WHERE id = ? AND status = 'pending' AND available_at <= ?");
            $stmt->execute([$token, $now + self::LEASE_SECONDS, $id, $now]);
            if ($stmt->rowCount() !== 1) continue;
            $stmt = $this->db->prepare('SELECT * FROM email_queue WHERE id = ? AND lease_token = ?');
            $stmt->execute([$id, $token]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return null;
    }

    /** Injectable sender keeps tests offline. No transaction spans a network call. */
    public function process(callable $sender, int $limit = 10, int $seconds = 50): array {
        $result = ['sent' => 0, 'failed' => 0];
        $deadline = microtime(true) + max(1, $seconds);
        for ($i = 0; $i < max(1, min(100, $limit)) && microtime(true) < $deadline; $i++) {
            $mail = $this->claim();
            if (!$mail) break;
            try {
                $sent = $sender($mail['recipient'], $mail['recipient_name'], $mail['subject'], $mail['html_body']) === true;
            } catch (Throwable $e) {
                // Do not put recipient details or provider credentials in errors.
                $sent = false;
            }
            if ($sent) {
                $stmt = $this->db->prepare("UPDATE email_queue SET status = 'sent', finished_at = ?, recipient = '', recipient_name = '', subject = '', html_body = '', lease_token = NULL, lease_until = NULL, last_error = NULL WHERE id = ? AND status = 'sending' AND lease_token = ?");
                $stmt->execute([time(), $mail['id'], $mail['lease_token']]);
                $result['sent']++;
            } else {
                $status = (int)$mail['attempts'] >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $delay = min(3600, 60 * (2 ** ((int)$mail['attempts'] - 1)));
                $stmt = $this->db->prepare("UPDATE email_queue SET status = ?, available_at = ?, lease_token = NULL, lease_until = NULL, last_error = 'Provider did not confirm acceptance; review gateway logs' WHERE id = ? AND status = 'sending' AND lease_token = ?");
                $stmt->execute([$status, time() + $delay, $mail['id'], $mail['lease_token']]);
                $result['failed']++;
            }
        }
        // Completed rows carry no message content and expire after seven days.
        $stmt = $this->db->prepare("DELETE FROM email_queue WHERE status = 'sent' AND finished_at < ?");
        $stmt->execute([time() - 7 * 86400]);
        return $result;
    }

    public function status(): array {
        return $this->db->query('SELECT status, COUNT(*) AS total, MIN(created_at) AS oldest_created_at FROM email_queue GROUP BY status')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function retryFailed(): int {
        $stmt = $this->db->prepare("UPDATE email_queue SET status = 'pending', attempts = 0, available_at = ?, lease_token = NULL, lease_until = NULL, last_error = NULL WHERE status = 'failed'");
        $stmt->execute([time()]);
        return $stmt->rowCount();
    }
}
