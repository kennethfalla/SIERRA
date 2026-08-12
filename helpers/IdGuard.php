<?php
// helpers/IdGuard.php
// Opaque, signed tokens that replace raw numeric IDs in user-visible URLs.
// Tokens are HMAC-SHA256 signed and time-limited, so raw report IDs are never
// exposed in the address bar / browser tab, and stale or forged tokens expire.

class IdGuard {

    /**
     * Secret used to sign tokens. Prefer an env variable (set ID_SECRET in
     * Render/Aiven). Falls back to a project-derived constant so the feature
     * works with zero configuration.
     *
     * @return string
     */
    private static function secret() {
        $env = getenv('ID_SECRET');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        return 'sierra-idguard-' . md5(__FILE__);
    }

    /** Token validity window (seconds). */
    const TTL = 2592000; // 30 days

    /**
     * Encode a numeric ID into an opaque URL-safe token.
     *
     * @param int $id
     * @return string
     */
    public static function enc($id) {
        $id = (int)$id;
        if ($id <= 0) {
            return '';
        }
        $payload = $id . '|' . time();
        $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $payload, self::secret());
        return $b64 . '.' . $sig;
    }

    /**
     * Decode a token back to its numeric ID.
     * Returns 0 when the token is missing, malformed, forged, or expired.
     * Plain numeric strings are still accepted for backward compatibility
     * (Session-aware access-control checks still guard those requests).
     *
     * @param string $token
     * @return int
     */
    public static function dec($token) {
        if (!is_string($token) || $token === '') {
            return 0;
        }

        // Backward compatible: a bare integer is treated as the ID itself.
        if (preg_match('/^\d+$/', $token)) {
            return (int)$token;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return 0;
        }

        $payload = base64_decode(strtr($parts[0], '-_', '+/'), true);
        if ($payload === false) {
            return 0;
        }

        $expected = hash_hmac('sha256', $payload, self::secret());
        if (!hash_equals($expected, $parts[1])) {
            return 0;
        }

        $sep = strrpos($payload, '|');
        if ($sep === false) {
            return 0;
        }

        $id = (int)substr($payload, 0, $sep);
        $issuedAt = (int)substr($payload, $sep + 1);

        if ($id <= 0) {
            return 0;
        }
        if ($issuedAt <= 0 || $issuedAt < time() - self::TTL) {
            return 0;
        }

        return $id;
    }

    /**
     * Decode a request value (from $_GET/$_POST) into an ID without warnings.
     *
     * @param mixed $value
     * @return int
     */
    public static function req($value) {
        return self::dec(isset($value) ? (string)$value : '');
    }
}