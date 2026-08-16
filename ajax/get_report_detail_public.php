<?php
/**
 * ajax/get_report_detail_public.php
 * Returns report details for the community map "View Details" modal.
 * - Reporter name / contact are NEVER exposed.
 * - Images (evidence photos) are returned as a plain array of paths.
 * - is_mine flag indicates whether the report belongs to the calling user.
 */

require_once dirname(__DIR__) . '/config/config.php';

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$report_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($report_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid report ID']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch report — no user name columns
    $stmt = $db->prepare("
        SELECT r.id, r.title, r.description, r.status, r.severity_score,
               r.latitude, r.longitude, r.created_at, r.updated_at,
               c.name  AS category_name,
               b.name  AS barangay_name,
               IF(r.user_id = :uid, 1, 0) AS is_mine
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN barangays  b ON r.barangay_id = b.id
        WHERE r.id = :rid
          AND r.is_archived = 0
          AND r.status NOT IN ('cancelled')
        LIMIT 1
    ");
    $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':rid', $report_id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        echo json_encode(['success' => false, 'error' => 'Report not found']);
        exit;
    }

    // Only return other people's reports if they are publicly visible (not resolved/closed)
    // OR if the report belongs to the requesting user.
    if (!$report['is_mine'] && in_array($report['status'], ['resolved', 'closed', 'rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Report not available']);
        exit;
    }

    // Fetch evidence images
    $img_stmt = $db->prepare("
        SELECT image_path
        FROM report_images
        WHERE report_id = :rid
        ORDER BY is_primary DESC, id ASC
        LIMIT 20
    ");
    $img_stmt->bindValue(':rid', $report_id, PDO::PARAM_INT);
    $img_stmt->execute();
    $images = $img_stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'report'  => $report,
        'images'  => $images,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
