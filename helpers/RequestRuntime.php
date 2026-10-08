<?php
/** Shared request diagnostics and recoverable error responses. */
final class RequestRuntime {
    private static $started;
    private static $id;
    private static $reserve;

    public static function boot(): void {
        if (PHP_SAPI === 'cli' || self::$id !== null) return;
        self::$started = microtime(true);
        self::$id = bin2hex(random_bytes(8));
        self::$reserve = str_repeat(' ', 65536);
        header('X-Request-ID: ' . self::$id);
        set_exception_handler(static function (Throwable $error): void {
            self::log('exception', get_class($error), $error->getFile(), $error->getLine());
            self::unavailable($error instanceof PDOException ? 503 : 500);
        });
        register_shutdown_function(static function (): void {
            self::$reserve = null;
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                $kind = stripos($error['message'], 'memory') !== false ? 'memory_limit'
                    : (stripos($error['message'], 'execution time') !== false ? 'execution_limit' : 'fatal');
                self::log($kind, '', $error['file'], $error['line']);
                self::unavailable(500);
            } elseif (microtime(true) - self::$started >= 5) {
                self::log('slow');
            }
        });
    }

    private static function log(string $kind, string $class = '', string $file = '', int $line = 0): void {
        // Log route/action and timing, never query strings, POST contents, OTPs,
        // account information or exception messages that can contain credentials.
        $safe = static function ($value): string {
            return is_string($value) ? substr(preg_replace('/[^a-zA-Z0-9_.\/-]/', '', $value), 0, 100) : '';
        };
        error_log('[Request] ' . json_encode([
            'id' => self::$id, 'kind' => $kind,
            'script' => $safe($_SERVER['SCRIPT_NAME'] ?? ''),
            'page' => $safe($_GET['page'] ?? ''),
            'action' => $safe($_POST['action'] ?? $_GET['action'] ?? ''),
            'seconds' => round(microtime(true) - self::$started, 3),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'class' => $class, 'file' => basename($file), 'line' => $line,
        ], JSON_UNESCAPED_SLASHES));
    }

    public static function unavailable(int $status = 503): void {
        $message = 'The system could not finish this request. Please try again shortly.';
        if (!headers_sent()) {
            http_response_code($status);
            header('Cache-Control: no-store');
            if ($status === 503) header('Retry-After: 30');
            $json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
                || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
            foreach (headers_list() as $header) {
                if (stripos($header, 'Content-Type: application/json') === 0) $json = true;
            }
            header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=utf-8');
            if ($json) {
                echo json_encode(['success' => false, 'error' => $message, 'message' => $message, 'request_id' => self::$id]);
                return;
            }
        }
        echo '<p role="alert">' . $message . '</p>';
        if (self::$id !== null) echo '<p>Reference: ' . self::$id . '</p>';
    }
}
