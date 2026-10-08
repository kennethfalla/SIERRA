<?php
namespace RuntimeFixture;
use PDOException;
use RuntimeException;
use ReflectionProperty;

// Offline HTTP tests: no app config, live database, or real gateway is loaded.
function curl_setopt($handle, $option, $value) { $handle->options[$option] = $value; return true; }
function curl_exec($handle) {
    $handle->calls++;
    $handle->released = session_status() !== PHP_SESSION_ACTIVE;
    // Simulate another request writing the session during delivery.
    session_start(); $_SESSION['concurrent'] = 'retained'; session_write_close();
    return 'accepted';
}
if (PHP_SAPI !== 'cli') {
    if (getenv('SIERRA_RUNTIME_FIXTURE') !== '1') { http_response_code(404); exit; }
    ini_set('display_errors', '0');
    ini_set('session.save_path', getenv('SIERRA_RUNTIME_SESSION_PATH'));
    require dirname(__DIR__) . '/helpers/RequestRuntime.php';
    \RequestRuntime::boot();
    $case = $_GET['case'] ?? '';
    header('Content-Type: application/json');
    if ($case === 'database') throw new PDOException('private-password-must-not-leak');
    if ($case === 'exception') throw new RuntimeException('private-password-must-not-leak');
    if ($case === 'fatal') trigger_error('private-password-must-not-leak', E_USER_ERROR);
    if ($case === 'html') { header('Content-Type: text/html'); throw new PDOException('private-password-must-not-leak'); }
    $source = file_get_contents(dirname(__DIR__) . '/helpers/SettingsHelper.php');
    $start = strpos($source, 'class SettingsHelper');
    $end = strpos($source, '// INITIALIZE DEFAULTS ON FIRST RUN');
    eval('namespace RuntimeFixture; use \\PDO; use \\Throwable; use \\Exception; ' . substr($source, $start, $end - $start));
    session_start(); $_SESSION['code_id'] = 123;
    if ($case === 'closed') session_write_close();
    $handle = (object)['options' => [], 'calls' => 0, 'released' => false];
    if ($case === 'budget') (new ReflectionProperty(SettingsHelper::class, 'gatewayDeadline'))->setValue(null, microtime(true) - 1);
    if ($case === 'remaining') $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 22;
    $result = SettingsHelper::executeGatewayRequest($handle, 30);
    echo json_encode([
        'result' => $result, 'calls' => $handle->calls, 'released' => $handle->released,
        'timeout' => $handle->options[CURLOPT_TIMEOUT] ?? null,
        'active' => session_status() === PHP_SESSION_ACTIVE,
        'code' => $_SESSION['code_id'] ?? null, 'concurrent' => $_SESSION['concurrent'] ?? null,
    ]);
    exit;
}

function check($value, $message) { if (!$value) throw new RuntimeException($message); echo "PASS $message\n"; }
$auth = file_get_contents(dirname(__DIR__) . '/controllers/AuthController.php');
$domainStart = strpos($auth, 'function isEmailDomainValid(');
$domainEnd = strpos($auth, '// ============================================', $domainStart);
eval('namespace RuntimeFixture; ' . substr($auth, $domainStart, $domainEnd - $domainStart));
check(isEmailDomainValid('resident@example.test') && !isEmailDomainValid('resident@bad..test')
    && !isEmailDomainValid('resident@-bad.test') && !isEmailDomainValid('resident@localhost'), 'email domain syntax is checked without DNS waits');
$dir = sys_get_temp_dir() . '/sierra-runtime-' . bin2hex(random_bytes(5));
mkdir($dir);
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false); fclose($socket);
$environment = array_merge(getenv(), ['SIERRA_RUNTIME_FIXTURE' => '1', 'SIERRA_RUNTIME_SESSION_PATH' => $dir]);
$process = proc_open([PHP_BINARY, '-d', 'error_log=' . $dir . '/error.log', '-S', $address, __FILE__],
    [0 => ['pipe', 'r'], 1 => ['file', $dir . '/server.log', 'a'], 2 => ['file', $dir . '/server.log', 'a']], $pipes, null, $environment);
try {
    if (!is_resource($process)) throw new RuntimeException('Fixture server failed to start');
    fclose($pipes[0]);
    for ($i = 0; $i < 40; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); break; } usleep(100000);
    }
    $request = static function ($case) use ($address) {
        $ch = \curl_init('http://' . $address . '/?case=' . $case);
        \curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 5]);
        $raw = \curl_exec($ch); $status = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = \curl_getinfo($ch, CURLINFO_HEADER_SIZE); \curl_close($ch);
        if ($raw === false) throw new RuntimeException('Fixture HTTP request failed');
        return [$status, substr($raw, 0, $headerSize), substr($raw, $headerSize)];
    };
    foreach (['database' => 503, 'exception' => 500, 'fatal' => 500] as $case => $expected) {
        [$status, $headers, $body] = $request($case);
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        check($status === $expected && $data['success'] === false && preg_match('/^[a-f0-9]{16}$/', $data['request_id']), "$case returns a safe response with a request reference (HTTP $status, " . json_encode($data) . ')');
        check(stripos($headers, 'X-Request-ID:') !== false && ($status !== 503 || stripos($headers, 'Retry-After:') !== false), "$case includes recovery headers");
    }
    [$status, $headers, $body] = $request('html');
    check($status === 503 && str_contains($body, 'Reference:') && !str_contains($body, 'private-password'), 'HTML failure is readable and hides private details');
    foreach (['active', 'closed', 'budget', 'remaining'] as $case) {
        [$status, $headers, $body] = $request($case);
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($case === 'budget') check($data['calls'] === 0 && $data['result'] === false, 'exhausted delivery budget makes no provider call');
        else {
            check($status === 200 && $data['released'] && $data['code'] === 123, "$case delivery preserves the OTP and releases the session lock");
            check($data['active'] === ($case !== 'closed'), "$case restores only an originally active session");
            if ($case === 'active') check($data['concurrent'] === 'retained', 'session reload retains concurrent updates');
            check($data['timeout'] <= ($case === 'remaining' ? 2 : 10), "$case obeys the interactive timeout");
        }
    }
    $log = file_get_contents($dir . '/error.log');
    // PHP itself can log the synthetic fatal's message; application entries must not.
    $applicationLog = implode("\n", array_filter(explode("\n", $log), static function ($line) { return str_contains($line, '[Request]'); }));
    check(str_contains($applicationLog, 'PDOException') && !str_contains($applicationLog, 'private-password'), 'request diagnostics contain route/timing without private error messages');
    echo "All runtime checks passed; no real providers or accounts used.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    foreach (glob($dir . '/*') as $file) unlink($file);
    rmdir($dir);
}
