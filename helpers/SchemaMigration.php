<?php
/** Database-owned migration marker: works when config/ is read-only. */
class SchemaMigration {
    const SETTING_KEY = '_app_schema_version';

    private static function current(PDO $db, string $version): bool {
        try {
            $stmt = $db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
            $stmt->execute([self::SETTING_KEY]);
            return $stmt->fetchColumn() === $version;
        } catch (PDOException $e) {
            // A new install may not have the settings table yet. Other errors
            // (permissions, lost connection) must not trigger schema changes.
            if (($e->errorInfo[1] ?? null) == 1146) return false;
            throw $e;
        }
    }

    public static function run(PDO $db, string $version, callable $migrate): void {
        if (self::current($db, $version)) return;
        $name = 'sierra-schema-' . substr(hash('sha256', (string)$db->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
        $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
        $lock->execute([$name]);
        if ((int)$lock->fetchColumn() !== 1) {
            throw new PDOException('Database update is in progress. Please retry shortly.');
        }
        try {
            // Another request may have finished while this request waited.
            if (self::current($db, $version)) return;
            if ($migrate() !== true) throw new PDOException('Database update did not complete. Check the server error log.');
            $stmt = $db->prepare('INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            $stmt->execute([self::SETTING_KEY, $version]);
        } finally {
            $release = $db->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$name]);
        }
    }
}
