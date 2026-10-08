<?php
// Offline tests: all provider requests go to a local fixture, never real recipients.
if (PHP_SAPI === 'cli-server') {
    if (str_contains($_SERVER['REQUEST_URI'], '/slow')) sleep(12);
    header('Content-Type: application/json');
    echo json_encode(['status' => 200, 'message_id' => 'local-fixture']);
    return;
}

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

class FixtureResult {
    private $rows;
    public function __construct($settings) {
        $this->rows = [];
        foreach ($settings as $key => $value) $this->rows[] = ['setting_key' => $key, 'setting_value' => $value];
    }
    public function fetch($mode = null) { return array_shift($this->rows) ?: false; }
}
class FixtureStatement {
    private $db, $sql, $affected = 0;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute($params = []) {
        if ($this->db->failWrites) throw new PDOException('Synthetic storage failure');
        if (str_starts_with($this->sql, 'SELECT id FROM users')) return true;
        if (str_starts_with($this->sql, 'INSERT')) {
            $id = ++$this->db->serial;
            $this->db->codes[$id] = ['code' => $params[0], 'expires' => strtotime($params[1]), 'type' => $params[2], 'used' => 0];
            $this->affected = 1;
        } else {
            check(str_contains($this->sql, 'id ='), 'OTP updates must target a single code');
            $id = $params[':id'] ?? $params[0];
            $row = $this->db->codes[$id] ?? null;
            $valid = $row && !$row['used'];
            if (isset($params[':code'])) $valid = $valid && $row['code'] === $params[':code'] && $row['expires'] > time();
            $this->affected = $valid ? 1 : 0;
            if ($valid) $this->db->codes[$id]['used'] = 1;
        }
        return true;
    }
    public function rowCount() { return $this->affected; }
}
class FixtureDatabase {
    public $settings = [], $codes = [], $serial = 0, $failWrites = false;
    public function query($sql) {
        check($sql === 'SELECT setting_key, setting_value FROM system_settings', 'No schema inspection during OTP delivery');
        return new FixtureResult($this->settings);
    }
    public function prepare($sql) { return new FixtureStatement($this, $sql); }
    public function lastInsertId() { return (string)$this->serial; }
}
class Database {
    public static $fixture;
    public function getConnection() { return self::$fixture; }
}

$db = Database::$fixture = new FixtureDatabase();
$db->settings = ['permissions_v9_migrated' => '1'];
require dirname(__DIR__) . '/helpers/SettingsHelper.php';
$source = file_get_contents(dirname(__DIR__) . '/controllers/AuthController.php');
if (($argv[1] ?? '') === '--request') {
    $fixture = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
    $db->settings = array_merge($db->settings, $fixture['settings']);
    SettingsHelper::clearCache();
    session_id('sierra-request-test-' . bin2hex(random_bytes(8)));
    require dirname(__DIR__) . '/helpers/SecurityHelper.php';
    $_SESSION['csrf_token'] = 'fixture-token';
    $_SESSION['registration_data'] = ['email' => 'resident@example.test', 'contact_number' => '09170000000', 'first_name' => 'Fixture'];
    $_POST = $fixture['post'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    define('BASE_URL', '/fixture/');
    $requestSessionFile = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . session_id();
    ob_start();
    register_shutdown_function(function () use ($db, $requestSessionFile) {
        $response = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (is_file($requestSessionFile)) unlink($requestSessionFile);
        echo json_encode(['response' => $response, 'codes' => array_values($db->codes)]);
    });
    // Real controller dispatch/validation with only DNS replaced by an offline fixture.
    eval('namespace RegistrationFixture; use \\SettingsHelper; use \\InputSanitizer; use \\PDO; use \\PDOException; use \\Throwable; '
        . 'function checkdnsrr($domain, $type) { return true; } '
        . substr($source, strpos($source, 'function isEmailDomainValid(')));
    exit;
}
$start = strpos($source, 'function storeRegistrationOtpCode(');
$end = strpos($source, '// NEW DEVICE LOGIN DETECTION', $start);
eval(substr($source, $start, $end - $start));
// Exercise the actual verification query without booting the production controller.
$verifyStart = strpos($source, '$stmt = $db->prepare(', strpos($source, '// Consume only this session'));
$verifyEnd = strpos($source, '        if ($stmt->rowCount()', $verifyStart);
$verifyQuery = substr($source, $verifyStart, $verifyEnd - $verifyStart);
$verify = function ($otp) use ($db, $verifyQuery) { eval($verifyQuery); return $stmt->rowCount() === 1; };

session_id('sierra-test-' . bin2hex(random_bytes(8)));
session_start();
$sessionFile = session_save_path() . DIRECTORY_SEPARATOR . 'sess_' . session_id();
$process = null;
$pipes = [];
try {
    $_SESSION = [];
    $a = storeRegistrationOtpCode($db, 'registration');
    $aSession = $_SESSION;
    $_SESSION = [];
    $b = storeRegistrationOtpCode($db, 'registration');
    $bId = $_SESSION['registration_otp_id'];
    $_SESSION = $aSession;
    $aNew = storeRegistrationOtpCode($db, 'registration_email');
    check(!$db->codes[$bId]['used'], 'Resending for A must leave B valid');
    check($db->codes[$aSession['registration_otp_id']]['used'], 'Previous code must be invalidated');
    $db->codes[$bId]['code'] = '000001';
    check(!$verify('000001'), 'Another session code must be rejected');
    check($verify($aNew), 'Latest code must verify');
    check(!$verify($aNew), 'A consumed code must not verify twice');
    storeRegistrationOtpCode($db, 'registration');
    $expiredId = $_SESSION['registration_otp_id'];
    $db->codes[$expiredId]['expires'] = time() - 1;
    check(!$verify($db->codes[$expiredId]['code']), 'Expired code must be rejected');

    // Reserve a local ephemeral port for a synthetic SMS/Mailgun provider.
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    check(is_resource($process), 'Fixture provider must start');
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(100000);
    }
    check($ready, 'Fixture provider must become ready');
    $db->settings = [
        'enable_sms_notifications' => '1', 'iprog_api_key' => 'fixture-only', 'iprog_base_url' => 'http://' . $address . '/sms',
        'enable_email_receipts' => '1', 'email_gateway' => 'mailgun', 'mailgun_api_key' => 'fixture-only',
        'mailgun_domain' => 'example.test', 'mailgun_sender_email' => 'no-reply@example.test', 'mailgun_base_url' => 'http://' . $address,
    ];
    SettingsHelper::clearCache();
    $validPost = [
        'action' => 'start_registration', 'csrf_token' => 'fixture-token',
        'first_name' => 'Fixture', 'last_name' => 'Resident', 'email' => 'resident@example.test',
        'contact_number' => '09170000000', 'password' => 'FixturePass1!', 'confirm_password' => 'FixturePass1!',
        'is_resident' => 'yes', 'barangay_id' => '1', 'purok_street' => 'Fixture Street',
    ];
    $request = function ($post, $settings = []) use ($db) {
        $fixture = json_encode(['post' => $post, 'settings' => array_merge($db->settings, $settings)]);
        $worker = proc_open([PHP_BINARY, __FILE__, '--request', $fixture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $workerPipes);
        check(is_resource($worker), 'Controller fixture must start');
        fclose($workerPipes[0]);
        $output = stream_get_contents($workerPipes[1]);
        $errors = stream_get_contents($workerPipes[2]);
        fclose($workerPipes[1]);
        fclose($workerPipes[2]);
        check(proc_close($worker) === 0, 'Controller fixture failed: ' . $errors);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    };
    $initial = $request($validPost);
    check($initial['response']['success'] ?? false, 'Continue must successfully send an OTP');
    check($initial['codes'][0]['type'] === 'registration_email', 'Continue must use email');
    $sms = $request(['action' => 'send_registration_otp', 'resend' => '1', 'csrf_token' => 'fixture-token']);
    check($sms['response']['success'] ?? false, 'Explicit SMS fallback must send an OTP');
    check($sms['codes'][0]['type'] === 'registration', 'Explicit fallback must use SMS');
    $missingPhone = $request(array_merge($validPost, ['contact_number' => '']));
    check(isset($missingPhone['response']['error']) && !$missingPhone['codes'], 'Mobile number remains required');
    $invalidCsrf = $request(array_merge($validPost, ['csrf_token' => 'invalid']));
    check(isset($invalidCsrf['response']['error']) && !$invalidCsrf['codes'], 'CSRF must remain enforced');
    $disabled = $request($validPost, ['enable_public_registration' => '0']);
    check(isset($disabled['response']['error']) && !$disabled['codes'], 'Registration kill switch must remain enforced');
    $noEmail = $request($validPost, ['enable_email_receipts' => '0']);
    check(($noEmail['response']['registration_ready'] ?? false) && isset($noEmail['response']['error']), 'Email failure must allow the verification screen and SMS fallback');
    $_SESSION = [];
    check(sendRegistrationOtpCode($db, '09170000000')['success'] ?? false, 'Successful SMS delivery');
    check(session_status() !== PHP_SESSION_ACTIVE, 'Session lock must be released before provider I/O');
    session_start();
    check(isset($_SESSION['registration_otp_id'], $_SESSION['registration_otp_requests']), 'OTP and rate limit must persist');
    check(sendRegistrationEmailOtp($db, 'resident@example.test', 'Fixture')['success'] ?? false, 'Successful email delivery');
    session_start();
    $_SESSION['registration_otp_requests'] = [time(), time(), time()];
    $count = $db->serial;
    check(sendRegistrationOtpCode($db, '09170000000')['limit_reached'] ?? false, 'Rate limit remains enforced');
    check($db->serial === $count, 'Rate-limited request must not create a code');
    $_SESSION['registration_otp_requests'] = [];
    $db->failWrites = true;
    check(isset(sendRegistrationOtpCode($db, '09170000000')['error']), 'Storage failure must return an error');
    $db->failWrites = false;

    $db->settings['iprog_base_url'] = 'http://' . $address . '/slow';
    SettingsHelper::clearCache(); // Simulate settings loaded by the next request.
    $started = microtime(true);
    check(isset(sendRegistrationOtpCode($db, '09170000000')['error']), 'Stalled SMS provider must return an error');
    $smsElapsed = microtime(true) - $started;
    check($smsElapsed >= 9 && $smsElapsed < 11.5, 'SMS timeout must be 10 seconds');
    // The single-threaded fixture finishes its stalled response after 12 seconds.
    usleep(2500000);
    session_start();
    $_SESSION['registration_otp_requests'] = [];
    $db->settings['mailgun_base_url'] = 'http://' . $address . '/slow';
    SettingsHelper::clearCache();
    $started = microtime(true);
    check(isset(sendRegistrationEmailOtp($db, 'resident@example.test')['error']), 'Stalled email provider must return an error');
    $emailElapsed = microtime(true) - $started;
    check($emailElapsed >= 9 && $emailElapsed < 11.5, 'Email timeout must be 10 seconds');
    echo sprintf("Registration OTP tests passed; stalled SMS %.1fs, email %.1fs.\n", $smsElapsed, $emailElapsed);
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (is_file($sessionFile)) unlink($sessionFile);
    if (is_resource($process)) { proc_terminate($process); foreach ($pipes as $pipe) fclose($pipe); proc_close($process); }
}
