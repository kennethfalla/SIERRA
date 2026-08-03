<?php
// Test script to verify password reset functionality
// Run this to check if password update and force_password_reset flag work

require_once 'config/config.php';

echo "<h2>Password Reset Test</h2>";

// Test user ID (change this to your test barangay official user ID)
$test_user_id = 2; // Change this to the actual user ID you're testing with

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

echo "<h3>Step 1: Check current state</h3>";

// Get current user data
$stmt = $db->prepare("SELECT id, first_name, last_name, email, role, force_password_reset, 
                      LEFT(password_hash, 30) as password_preview 
                      FROM users WHERE id = ?");
$stmt->execute([$test_user_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current) {
    echo "<p style='color:red'>❌ User ID $test_user_id not found!</p>";
    exit;
}

echo "<pre>";
print_r($current);
echo "</pre>";

echo "<h3>Step 2: Set force_password_reset to 1</h3>";
$result = $user->setForcePasswordReset($test_user_id, 1);
echo $result ? "<p style='color:green'>✅ Set to 1 successfully</p>" : "<p style='color:red'>❌ Failed to set to 1</p>";

// Verify
$stmt->execute([$test_user_id]);
$check1 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "force_password_reset = " . $check1['force_password_reset'] . "<br>";

echo "<h3>Step 3: Update password</h3>";
$test_password = "TestPassword123!";
$result = $user->updatePassword($test_user_id, $test_password);
echo $result ? "<p style='color:green'>✅ Password updated successfully</p>" : "<p style='color:red'>❌ Failed to update password</p>";

// Verify password works
$stmt->execute([$test_user_id]);
$check2 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Password hash preview: " . substr($check2['password_preview'], 0, 30) . "...<br>";

// Test if password verifies
$verify_result = $user->verifyPassword($test_user_id, $test_password);
echo $verify_result ? "<p style='color:green'>✅ New password verifies correctly</p>" : "<p style='color:red'>❌ Password verification failed</p>";

echo "<h3>Step 4: Clear force_password_reset</h3>";
$result = $user->setForcePasswordReset($test_user_id, 0);
echo $result ? "<p style='color:green'>✅ Cleared to 0 successfully</p>" : "<p style='color:red'>❌ Failed to clear to 0</p>";

// Verify
$stmt->execute([$test_user_id]);
$check3 = $stmt->fetch(PDO::FETCH_ASSOC);
echo "force_password_reset = " . $check3['force_password_reset'] . "<br>";

echo "<h3>Final State:</h3>";
echo "<pre>";
print_r($check3);
echo "</pre>";

echo "<h3>Summary:</h3>";
if ($check3['force_password_reset'] == 0 && $verify_result) {
    echo "<h2 style='color:green'>✅ ALL TESTS PASSED!</h2>";
    echo "<p>Password reset functionality is working correctly.</p>";
    echo "<p><strong>Test password set:</strong> $test_password</p>";
    echo "<p>You can now try logging in with this password.</p>";
} else {
    echo "<h2 style='color:red'>❌ SOME TESTS FAILED</h2>";
    echo "<p>Check the error log for details.</p>";
}
?>
