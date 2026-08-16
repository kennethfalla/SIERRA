<?php
// ajax/get_user_profile.php - Fetch user details for profile modal
require_once dirname(__DIR__) . '/config/config.php';
requireRole('admin');

header('Content-Type: application/json');

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$query = "SELECT u.id, u.email, u.first_name, u.last_name, u.user_type, u.barangay_id, 
                 u.contact_number, u.is_active, u.created_at, u.job_title,
                 u.is_resident, u.non_resident_address,
                 b.name as barangay_name 
          FROM users u 
          LEFT JOIN barangays b ON u.barangay_id = b.id 
          WHERE u.id = :id";
$stmt = $db->prepare($query);
$stmt->execute([':id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit();
}

$user['full_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
// Format contact number if needed
if (!empty($user['contact_number'])) {
    $clean = preg_replace('/[^0-9]/', '', $user['contact_number']);
    if (strlen($clean) === 11) {
        $user['contact_number'] = substr($clean, 0, 4) . ' ' . substr($clean, 4, 3) . ' ' . substr($clean, 7, 4);
    } elseif (strlen($clean) === 10) {
        $user['contact_number'] = substr($clean, 0, 3) . ' ' . substr($clean, 3, 3) . ' ' . substr($clean, 6, 4);
    }
}

echo json_encode($user);