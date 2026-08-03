<?php
// controllers/BarangayController.php
require_once dirname(__DIR__) . '/config/config.php';

class BarangayController {
    private $db;
    private $report;
    private $activityLog;
    
    public function __construct($db) {
        $this->db = $db;
        $this->report = new Report($db);
        $this->activityLog = new ActivityLog($db);
    }
    
    public function getDashboardStats($barangay_id) {
        // Delegates to Report::getDashboardStats() to avoid duplicate logic.
        return $this->report->getDashboardStats($barangay_id);
    }
    
    public function getBarangayReports($barangay_id) {
        $query = "SELECT r.*, c.name as category_name,
                         CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM reports r
                  JOIN categories c ON r.category_id = c.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.barangay_id = :barangay_id
                  ORDER BY r.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":barangay_id", $barangay_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateReportStatus($report_id, $status, $barangay_id, $verified_by) {
        $query = "UPDATE reports SET status = :status";
        if($status == 'verified') {
            $query .= ", verified_by = :verified_by, verified_at = NOW()";
        }
        if($status == 'resolved') {
            $query .= ", resolved_at = NOW()";
        }
        $query .= " WHERE id = :id AND barangay_id = :barangay_id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $report_id);
        $stmt->bindParam(":barangay_id", $barangay_id);
        if($status == 'verified') {
            $stmt->bindParam(":verified_by", $verified_by);
        }
        
        if($stmt->execute()) {
            $this->activityLog->log($verified_by, 'Update Status', "Updated report #$report_id to $status");
            return true;
        }
        return false;
    }
    
    public function createAnnouncement($title, $content, $barangay_id, $created_by) {
        $query = "INSERT INTO announcements (title, content, barangay_id, created_by) 
                  VALUES (:title, :content, :barangay_id, :created_by)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":barangay_id", $barangay_id);
        $stmt->bindParam(":created_by", $created_by);
        
        if($stmt->execute()) {
            $this->activityLog->log($created_by, 'Create Announcement', "Created announcement: $title");
            return true;
        }
        return false;
    }
    
    public function getAnnouncements($barangay_id) {
        $query = "SELECT * FROM announcements 
                  WHERE barangay_id = :barangay_id OR barangay_id IS NULL 
                  ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":barangay_id", $barangay_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle API requests
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole('barangay_official');
    $database = new Database();
    $db = $database->getConnection();
    $controller = new BarangayController($db);
    
    $action = $_POST['action'] ?? '';
    $response = ['success' => false, 'message' => ''];
    
    switch($action) {
        case 'update_status':
            $success = $controller->updateReportStatus(
                $_POST['report_id'],
                $_POST['status'],
                $_SESSION['barangay_id'],
                $_SESSION['user_id']
            );
            $response['success'] = $success;
            $response['message'] = $success ? 'Status updated' : 'Update failed';
            break;
            
        case 'create_announcement':
            $success = $controller->createAnnouncement(
                $_POST['title'],
                $_POST['content'],
                $_SESSION['barangay_id'],
                $_SESSION['user_id']
            );
            $response['success'] = $success;
            $response['message'] = $success ? 'Announcement created' : 'Creation failed';
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>