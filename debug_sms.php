<?php
// debug_sms.php - Diagnostic script for SMS

require_once 'config/config.php';
require_once 'helpers/SettingsHelper.php';

// Clear cache to get fresh settings
SettingsHelper::clearCache();

echo "<h1>SMS Debug Information</h1>";

// 1. Check database settings
echo "<h2>1. Database Settings:</h2>";
$database = new Database();
$db = $database->getConnection();

$stmt = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%sms%' OR setting_key LIKE '%iprog%' ORDER BY setting_key");
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Key</th><th>Value</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $value = $row['setting_value'];
    if (strpos($row['setting_key'], 'api') !== false) {
        // Mask API keys for security
        $value = substr($value, 0, 10) . '...' . substr($value, -5);
    }
    echo "<tr><td>" . $row['setting_key'] . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
}
echo "</table>";

// 2. Check SettingsHelper
echo "<h2>2. SettingsHelper::getSmsSettings():</h2>";
echo "<pre>";
$settings = SettingsHelper::getSmsSettings();
// Mask API key
if (isset($settings['iprog_api_key']) && !empty($settings['iprog_api_key'])) {
    $settings['iprog_api_key'] = substr($settings['iprog_api_key'], 0, 10) . '...' . substr($settings['iprog_api_key'], -5);
}
print_r($settings);
echo "</pre>";

// 3. Check Active Gateway
echo "<h2>3. SettingsHelper::getActiveSmsGateway():</h2>";
echo "<pre>";
$gateway = SettingsHelper::getActiveSmsGateway();
if ($gateway) {
    $gateway['api_key'] = substr($gateway['api_key'], 0, 10) . '...' . substr($gateway['api_key'], -5);
    print_r($gateway);
} else {
    echo "No active gateway found!";
}
echo "</pre>";

// 4. Check isSmsEnabled
echo "<h2>4. SettingsHelper::isSmsEnabled():</h2>";
echo SettingsHelper::isSmsEnabled() ? '✅ TRUE' : '❌ FALSE';
echo "<br>";

// 5. Test sending SMS
echo "<h2>5. Test SMS:</h2>";
$test_phone = '09123456789';
$test_message = 'Test SMS from Sierra - Debug';

echo "Sending to: $test_phone<br>";
echo "Message: $test_message<br><br>";

$result = SettingsHelper::sendSms($test_phone, $test_message);

if ($result) {
    echo "✅ SMS sent successfully!";
} else {
    echo "❌ SMS failed.";
}

// 6. Check PHP cURL
echo "<h2>6. PHP cURL Status:</h2>";
if (function_exists('curl_version')) {
    $curl = curl_version();
    echo "✅ cURL is enabled.<br>";
    echo "Version: " . $curl['version'] . "<br>";
    echo "SSL Version: " . $curl['ssl_version'] . "<br>";
} else {
    echo "❌ cURL is NOT enabled!";
}

// 7. Test connection to iProg
echo "<h2>7. Connection Test to iProg:</h2>";
$test_url = 'https://www.iprogsms.com';
echo "Testing connection to: $test_url<br>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($http_code > 0) {
    echo "✅ Connection successful. HTTP Code: $http_code<br>";
} else {
    echo "❌ Connection failed.<br>";
    echo "Error: $error<br>";
}
?>