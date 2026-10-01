<?php
// models/UserDevice.php - tracks recognized login devices for new-device detection.
// A device is auto-created on first login; a login is "new" only when neither the
// device signature (User-Agent hash) nor the IP address has been seen for the user.
class UserDevice {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        // Schema is prepared once per version by Database.
    }

    /**
     * Register a login for a user and report whether the device/IP is brand new.
     *
     * @param int    $user_id
     * @param string $userAgent
     * @param string $ip
     * @return array ['is_new' => bool, 'device_name' => string]
     */
    public function registerLogin($user_id, $userAgent, $ip) {
        $deviceKey  = self::deviceKey($userAgent, $ip);
        $deviceName = self::friendlyDeviceName($userAgent);
        $now        = date('Y-m-d H:i:s');
        $userAgent  = $userAgent !== null ? substr($userAgent, 0, 255) : null;

        // Recognized = this device signature OR this IP was already used by the user.
        $stmt = $this->conn->prepare(
            "SELECT id FROM user_devices
             WHERE user_id = ? AND (device_key = ? OR ip_address = ?)
             ORDER BY last_login_at DESC LIMIT 1"
        );
        $stmt->execute([(int)$user_id, $deviceKey, $ip]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $upd = $this->conn->prepare(
                "UPDATE user_devices SET last_login_at = ?, ip_address = ?, user_agent = ?, device_name = ? WHERE id = ?"
            );
            $upd->execute([$now, $ip, $userAgent, $deviceName, $existing['id']]);
            return ['is_new' => false, 'device_name' => $deviceName];
        }

        $ins = $this->conn->prepare(
            "INSERT INTO user_devices (user_id, device_key, device_name, user_agent, ip_address, first_seen_at, last_login_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([(int)$user_id, $deviceKey, $deviceName, $userAgent, $ip, $now, $now]);
        return ['is_new' => true, 'device_name' => $deviceName];
    }

    // Stable signature for a browser/device. Scripts (empty UA) fall back to IP.
    private static function deviceKey($userAgent, $ip) {
        $seed = !empty(trim((string)$userAgent)) ? trim((string)$userAgent) : 'cli:' . $ip;
        return hash('sha256', $seed);
    }

    // Parse a User-Agent into a friendly device label, e.g. "iPhone · Safari",
    // "Windows 11 · Chrome". Adapted from the Audit Logs view parser.
    private static function friendlyDeviceName($ua) {
        if (empty(trim((string)$ua))) return 'Unknown Device';
        $ua = ' ' . $ua . ' ';

        if (stripos($ua, 'iPhone') !== false) {
            $device = 'iPhone' . self::iosVersion($ua);
        } elseif (stripos($ua, 'iPad') !== false) {
            $device = 'iPad' . self::iosVersion($ua);
        } elseif (stripos($ua, 'Android') !== false) {
            $androidVer = '';
            if (preg_match('/Android\s([0-9.]+)/i', $ua, $m)) $androidVer = ' ' . $m[1];
            $device = (stripos($ua, 'Mobile') !== false ? 'Android Phone' : 'Android Tablet') . $androidVer;
        } elseif (stripos($ua, 'CrOS') !== false) {
            $device = 'Chromebook';
        } elseif (preg_match('/Windows NT 11/i', $ua)) {
            $device = 'Windows 11';
        } elseif (preg_match('/Windows NT 10\.0/i', $ua)) {
            $device = 'Windows 10/11';
        } elseif (preg_match('/Windows NT 6\.3/i', $ua)) {
            $device = 'Windows 8.1';
        } elseif (preg_match('/Windows NT 6\.1/i', $ua)) {
            $device = 'Windows 7';
        } elseif (stripos($ua, 'Windows') !== false) {
            $device = 'Windows PC';
        } elseif (stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false) {
            $device = 'Mac';
        } elseif (stripos($ua, 'Linux') !== false) {
            $device = 'Linux Device';
        } else {
            $device = 'Unknown Device';
        }

        if (stripos($ua, 'SamsungBrowser') !== false) {
            $browser = 'Samsung Internet';
        } elseif (stripos($ua, 'Edg/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (preg_match('/Version\/[\d.]+.*Safari/i', $ua)) {
            $browser = 'Safari';
        } elseif (stripos($ua, 'Trident/') !== false || stripos($ua, 'MSIE') !== false) {
            $browser = 'Internet Explorer';
        } else {
            $browser = null;
        }

        return $browser ? $device . ' · ' . $browser : $device;
    }

    private static function iosVersion($ua) {
        if (preg_match('/OS (\d+)[_\.](\d+)/', $ua, $m)) {
            return ' ' . $m[1] . '.' . $m[2];
        }
        return '';
    }
}