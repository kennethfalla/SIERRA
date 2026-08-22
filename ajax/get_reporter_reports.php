<?php
// ajax/get_reporter_reports.php - Fetch a reporter's reports (barangay-scoped, read-only)
// Used by the Barangay Reporters Directory modal. Only returns reports
// submitted within the logged-in official's own barangay.
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/PermissionHelper.php';
requireRole('barangay_official');

header('Content-Type: application/json');

$barangay_id = $_SESSION['barangay_id'] ?? null;
$reporter_id = isset($_GET['reporter_id']) ? (int)$_GET['reporter_id'] : 0;

if (empty($barangay_id) || $reporter_id <= 0) {
    echo json_encode(['error' => 'Invalid request.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user = $db->prepare("SELECT id, first_name, last_name, email, contact_number, is_resident, barangay_id FROM users WHERE id = ?");
$user->execute([$reporter_id]);
$reporter = $user->fetch(PDO::FETCH_ASSOC);

if (!$reporter) {
    echo json_encode(['error' => 'Reporter not found.']);
    exit();
}

$stmt = $db->prepare("
    SELECT r.id, r.title, r.description, r.status, r.risk_level, r.severity_score,
           r.decision_classification, r.created_at, r.resolved_at,
           c.name AS category_name
    FROM reports r
    LEFT JOIN categories c ON c.id = r.category_id
    WHERE r.user_id = ? AND r.barangay_id = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$reporter_id, $barangay_id]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'reporter' => [
        'full_name'  => trim($reporter['first_name'] . ' ' . $reporter['last_name']),
        'email'      => $reporter['email'],
        'contact'    => $reporter['contact_number'],
        'is_resident'=> (int)$reporter['is_resident'],
    ],
    'count'   => count($reports),
    'reports' => $reports,
]);
