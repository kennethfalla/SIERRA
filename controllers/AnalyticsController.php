<?php
// controllers/AnalyticsController.php
require_once dirname(__DIR__) . '/config/config.php';

class AnalyticsController {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function getCategoryStats() {
        $query = "SELECT c.name, COUNT(r.id) as count 
                  FROM categories c 
                  LEFT JOIN reports r ON c.id = r.category_id 
                  GROUP BY c.id";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getStatusStats() {
        $query = "SELECT status, COUNT(*) as count 
                  FROM reports 
                  GROUP BY status";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getMonthlyTrends($months = 6) {
        $query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                  FROM reports 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL :months MONTH)
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(":months", $months, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getBarangayStats() {
        $query = "SELECT b.name, COUNT(r.id) as count 
                  FROM barangays b 
                  LEFT JOIN reports r ON b.id = r.barangay_id 
                  GROUP BY b.id";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getHotspots() {
        $query = "SELECT b.name, COUNT(r.id) as report_count, c.name as top_category
                  FROM barangays b
                  JOIN reports r ON b.id = r.barangay_id
                  JOIN categories c ON r.category_id = c.id
                  GROUP BY b.id
                  HAVING report_count > 0
                  ORDER BY report_count DESC
                  LIMIT 5";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getKeyMetrics() {
        $metrics = [];
        
        $query = "SELECT COUNT(*) as total FROM reports";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $metrics['total_reports'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $query = "SELECT COUNT(*) as total FROM reports WHERE status='pending'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $metrics['pending_reports'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $query = "SELECT COUNT(*) as total FROM reports WHERE status='resolved'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $metrics['resolved_reports'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $query = "SELECT AVG(TIMESTAMPDIFF(DAY, created_at, resolved_at)) as avg_days 
                  FROM reports WHERE status='resolved'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $metrics['avg_resolution_days'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_days'] ?: 0);
        
        $query = "SELECT COUNT(*) as total FROM users";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $metrics['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return $metrics;
    }
    
    public function getDecisionInsights() {
        $insights = [];
        $hotspots = $this->getHotspots();
        
        foreach($hotspots as $hotspot) {
            $insights[] = [
                'type' => 'warning',
                'message' => "High activity in {$hotspot['name']}: {$hotspot['report_count']} reports - Mostly {$hotspot['top_category']}"
            ];
        }
        
        $metrics = $this->getKeyMetrics();
        if($metrics['pending_reports'] > 10) {
            $insights[] = [
                'type' => 'urgent',
                'message' => "High number of pending reports ({$metrics['pending_reports']}). Immediate verification needed."
            ];
        }
        
        $insights[] = [
            'type' => 'recommendation',
            'message' => "Based on trends, increase monitoring in identified hotspots and allocate more resources to waste management."
        ];
        
        return $insights;
    }
}

// API endpoint for AJAX requests
if(isset($_GET['action'])) {
    requireRole('admin');
    $database = new Database();
    $db = $database->getConnection();
    $analytics = new AnalyticsController($db);
    
    header('Content-Type: application/json');
    
    switch($_GET['action']) {
        case 'category_stats':
            echo json_encode($analytics->getCategoryStats());
            break;
        case 'status_stats':
            echo json_encode($analytics->getStatusStats());
            break;
        case 'monthly_trends':
            echo json_encode($analytics->getMonthlyTrends());
            break;
        case 'barangay_stats':
            echo json_encode($analytics->getBarangayStats());
            break;
        case 'hotspots':
            echo json_encode($analytics->getHotspots());
            break;
        case 'metrics':
            echo json_encode($analytics->getKeyMetrics());
            break;
        case 'insights':
            echo json_encode($analytics->getDecisionInsights());
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit();
}
?>