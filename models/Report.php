<?php
// models/Report.php
require_once __DIR__ . '/../helpers/SettingsHelper.php';

class Report {
    private $conn;
    private $table = "reports";

    // ============================================
    // STATUS CONSTANTS
    // ============================================
    const STATUS_PENDING          = 'pending';
    const STATUS_UNDER_REVIEW     = 'under_review';
    const STATUS_VERIFIED         = 'verified';
    const STATUS_IN_PROGRESS      = 'in_progress';
    const STATUS_ESCALATED_PENDING = 'escalated_pending';
    const STATUS_ESCALATED        = 'escalated';
    const STATUS_RESOLVED         = 'resolved';
    const STATUS_REJECTED         = 'rejected';
    const STATUS_CANCELLED        = 'cancelled';

    // List of active statuses (not cancelled, not resolved/rejected) for density calculation
    const ACTIVE_STATUSES = [
        'pending',
        'under_review',
        'verified',
        'in_progress',
        'escalated_pending',
        'escalated'
    ];

    public function __construct($db) {
        $this->conn = $db;
    }

    // ============================================
    // CREATE
    // ============================================
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                SET user_id=:user_id, category_id=:category_id, barangay_id=:barangay_id,
                    title=:title, description=:description, latitude=:latitude, 
                    longitude=:longitude, location_address=:location_address,
                    risk_level=:risk_level, impact_modifier=:impact_modifier,
                    street_name=:street_name, barangay_name=:barangay_name, 
                    municipality_name=:municipality_name, province_name=:province_name,
                    country_name=:country_name";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":user_id", $data['user_id']);
        $stmt->bindParam(":category_id", $data['category_id']);
        $stmt->bindParam(":barangay_id", $data['barangay_id']);
        $stmt->bindParam(":title", $data['title']);
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":latitude", $data['latitude']);
        $stmt->bindParam(":longitude", $data['longitude']);
        $stmt->bindParam(":location_address", $data['location_address']);
        $stmt->bindParam(":risk_level", $data['risk_level']);
        $stmt->bindParam(":impact_modifier", $data['impact_modifier']);
        $stmt->bindParam(":street_name", $data['street_name']);
        $stmt->bindParam(":barangay_name", $data['barangay_name']);
        $stmt->bindParam(":municipality_name", $data['municipality_name']);
        $stmt->bindParam(":province_name", $data['province_name']);
        $stmt->bindParam(":country_name", $data['country_name']);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        $error = $stmt->errorInfo();
        error_log("Report creation failed: " . print_r($error, true));
        return false;
    }
        
    public function addImage($report_id, $image_path, $is_primary = false) {
        $query = "INSERT INTO report_images (report_id, image_path, is_primary) VALUES (:report_id, :image_path, :is_primary)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->bindParam(":image_path", $image_path);
        $stmt->bindParam(":is_primary", $is_primary);
        return $stmt->execute();
    }

    // ============================================
    // READ (with default exclusion of cancelled)
    // ============================================
    
    public function getReportsByUser($user_id) {
        $query = "SELECT r.*, c.name as category_name, c.icon_class, b.name as barangay_name,
                         (SELECT COUNT(*) FROM report_images WHERE report_id = r.id) as image_count
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN barangays b ON r.barangay_id = b.id
                  WHERE r.user_id = :user_id
                  AND r.status != :cancelled
                  ORDER BY r.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindValue(":cancelled", self::STATUS_CANCELLED);
        $stmt->execute();
        return $stmt;
    }

    public function getAllReports($barangay_id = null, $status = null, $limit = null, $offset = null) {
        $query = "SELECT r.*, c.name as category_name, c.icon_class, b.name as barangay_name,
                         u.id as user_id, u.first_name, u.last_name, u.email, u.contact_number,
                         CONCAT(u.first_name, ' ', u.last_name) as full_name,
                         (SELECT COUNT(*) FROM report_images WHERE report_id = r.id) as image_count
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN barangays b ON r.barangay_id = b.id
                  JOIN users u ON r.user_id = u.id
                  WHERE 1=1";
        
        $params = [];
        
        // Default: exclude cancelled unless explicitly requested
        if ($status !== self::STATUS_CANCELLED) {
            $query .= " AND r.status != :cancelled";
            $params[':cancelled'] = self::STATUS_CANCELLED;
        }
        
        if($barangay_id) {
            $query .= " AND r.barangay_id = :barangay_id";
            $params[':barangay_id'] = $barangay_id;
        }
        if($status) {
            $query .= " AND r.status = :status";
            $params[':status'] = $status;
        }
        
        $query .= " ORDER BY r.created_at DESC";
        
        if($limit) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int)$limit;
        }
        if($offset) {
            $query .= " OFFSET :offset";
            $params[':offset'] = (int)$offset;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            if($key == ':limit' || $key == ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        return $stmt;
    }

    public function getReportById($id) {
        $query = "SELECT r.*, c.name as category_name, c.icon_class, b.name as barangay_name,
                         u.id as user_id, u.first_name, u.last_name, u.email, u.contact_number,
                         CONCAT(u.first_name, ' ', u.last_name) as user_name
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN barangays b ON r.barangay_id = b.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getReportWithDetails($id) {
        $query = "SELECT r.*, c.name as category_name, c.icon_class, c.description as category_description,
                         b.name as barangay_name, b.zone as barangay_zone,
                         u.id as user_id, u.first_name, u.last_name, u.email, u.contact_number,
                         CONCAT(u.first_name, ' ', u.last_name) as user_name,
                         (SELECT COUNT(*) FROM report_images WHERE report_id = r.id) as image_count,
                         (SELECT image_path FROM report_images WHERE report_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN barangays b ON r.barangay_id = b.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReportsByStatus($status, $barangay_id = null, $user_id = null) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE status = :status";
        $params = [':status' => $status];
        
        if($barangay_id) {
            $query .= " AND barangay_id = :barangay_id";
            $params[':barangay_id'] = $barangay_id;
        }
        if($user_id) {
            $query .= " AND user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    public function getTotalCount($barangay_id = null, $user_id = null, $include_cancelled = false) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table . " WHERE 1=1";
        $params = [];
        
        if(!$include_cancelled) {
            $query .= " AND status != :cancelled";
            $params[':cancelled'] = self::STATUS_CANCELLED;
        }
        if($barangay_id) {
            $query .= " AND barangay_id = :barangay_id";
            $params[':barangay_id'] = $barangay_id;
        }
        if($user_id) {
            $query .= " AND user_id = :user_id";
            $params[':user_id'] = $user_id;
        }
        
        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
    
    public function getImagesByReport($report_id) {
        $query = "SELECT * FROM report_images WHERE report_id = :report_id ORDER BY is_primary DESC, uploaded_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getPrimaryImage($report_id) {
        $query = "SELECT image_path FROM report_images WHERE report_id = :report_id AND is_primary = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['image_path'] : null;
    }
    
    public function getReportsByBarangay($barangay_id, $limit = null) {
        $query = "SELECT r.*, c.name as category_name, c.icon_class,
                         u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) as full_name,
                         (SELECT COUNT(*) FROM report_images WHERE report_id = r.id) as image_count
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.barangay_id = :barangay_id
                  AND r.status != :cancelled
                  ORDER BY r.created_at DESC";
        
        if($limit) {
            $query .= " LIMIT :limit";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":barangay_id", $barangay_id);
        $stmt->bindValue(":cancelled", self::STATUS_CANCELLED);
        if($limit) {
            $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt;
    }
    
    public function getRecentReports($limit = 10) {
        $query = "SELECT r.*, c.name as category_name, b.name as barangay_name,
                         u.first_name, u.last_name, CONCAT(u.first_name, ' ', u.last_name) as full_name,
                         (SELECT COUNT(*) FROM report_images WHERE report_id = r.id) as image_count
                  FROM " . $this->table . " r
                  JOIN categories c ON r.category_id = c.id
                  JOIN barangays b ON r.barangay_id = b.id
                  JOIN users u ON r.user_id = u.id
                  WHERE r.status != :cancelled
                  ORDER BY r.created_at DESC
                  LIMIT :limit";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":cancelled", self::STATUS_CANCELLED);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }
    
    public function getDashboardStats($barangay_id = null, $include_cancelled = false) {
        $query = "SELECT 
                    COUNT(*) as total_reports,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
                    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(CASE WHEN risk_level = 'low' THEN 1 ELSE 0 END) as low_risk,
                    SUM(CASE WHEN risk_level = 'medium' THEN 1 ELSE 0 END) as medium_risk,
                    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk,
                    SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_risk
                  FROM " . $this->table . " r
                  WHERE 1=1";
        if($barangay_id) {
            $query .= " AND barangay_id = :barangay_id";
        }
        if(!$include_cancelled) {
            $query .= " AND status != :cancelled";
        }
        
        $stmt = $this->conn->prepare($query);
        if($barangay_id) {
            $stmt->bindParam(":barangay_id", $barangay_id);
        }
        if(!$include_cancelled) {
            $stmt->bindValue(":cancelled", self::STATUS_CANCELLED);
        }
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // ============================================
    // NOTES & EVIDENCE
    // ============================================
    
    public function addNote($report_id, $user_id, $note) {
        $query = "INSERT INTO report_notes (report_id, user_id, note) VALUES (:report_id, :user_id, :note)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":note", $note);
        return $stmt->execute();
    }
    
    public function getNotes($report_id) {
        $query = "SELECT n.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.role
                  FROM report_notes n
                  JOIN users u ON n.user_id = u.id
                  WHERE n.report_id = :report_id
                  ORDER BY n.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addResolutionEvidence($report_id, $image_path, $uploaded_by, $caption = null) {
        $query = "INSERT INTO resolution_evidence (report_id, image_path, uploaded_by, caption) VALUES (:report_id, :image_path, :uploaded_by, :caption)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->bindParam(":image_path", $image_path);
        $stmt->bindParam(":uploaded_by", $uploaded_by);
        $stmt->bindParam(":caption", $caption);
        return $stmt->execute();
    }
    
    public function getResolutionEvidence($report_id) {
        $query = "SELECT e.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                  FROM resolution_evidence e
                  JOIN users u ON e.uploaded_by = u.id
                  WHERE e.report_id = :report_id
                  ORDER BY e.uploaded_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function confirmResolution($report_id, $user_id) {
        $query = "UPDATE " . $this->table . " 
                  SET resolution_confirmed = 1, resolution_confirmed_at = NOW() 
                  WHERE id = :report_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->bindParam(":user_id", $user_id);
        return $stmt->execute();
    }
    
    public function escalateToMENRO($report_id, $reason, $escalated_by) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_UNDER_REVIEW) {
            return false;
        }

        $insert = $this->conn->prepare(
            "INSERT INTO escalations (report_id, escalated_by, escalation_reason, escalated_at, status) 
             VALUES (:report_id, :escalated_by, :reason, NOW(), 'pending')"
        );
        $insert->bindParam(":report_id", $report_id);
        $insert->bindParam(":escalated_by", $escalated_by);
        $insert->bindParam(":reason", $reason);
        if (!$insert->execute()) {
            return false;
        }

        $query = "UPDATE " . $this->table . " 
                  SET escalated = 1, escalation_reason = :reason, escalated_at = NOW(), 
                      escalated_by = :escalated_by, status = :escalated_pending
                  WHERE id = :report_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":report_id", $report_id);
        $stmt->bindParam(":reason", $reason);
        $stmt->bindParam(":escalated_by", $escalated_by);
        $stmt->bindValue(":escalated_pending", self::STATUS_ESCALATED_PENDING);
        return $stmt->execute();
    }

    public function approveEscalation($report_id, $approved_by) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_ESCALATED_PENDING) {
            return false;
        }

        $update = $this->conn->prepare(
            "UPDATE " . $this->table . " 
             SET status = :escalated, menro_accepted = 1, escalated_to_menro = 1 
             WHERE id = :id"
        );
        $update->bindValue(":escalated", self::STATUS_ESCALATED);
        $update->bindParam(":id", $report_id);
        if (!$update->execute()) {
            return false;
        }

        $escUpdate = $this->conn->prepare(
            "UPDATE escalations SET status = 'approved', approved_by = :approved_by, approved_at = NOW() 
             WHERE report_id = :report_id AND status = 'pending'"
        );
        $escUpdate->bindParam(":approved_by", $approved_by);
        $escUpdate->bindParam(":report_id", $report_id);
        return $escUpdate->execute();
    }

    public function rejectEscalation($report_id, $reason, $rejected_by) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_ESCALATED_PENDING) {
            return false;
        }

        $update = $this->conn->prepare(
            "UPDATE " . $this->table . " 
             SET status = :in_progress, menro_accepted = 0 
             WHERE id = :id"
        );
        $update->bindValue(":in_progress", self::STATUS_IN_PROGRESS);
        $update->bindParam(":id", $report_id);
        if (!$update->execute()) {
            return false;
        }

        $escUpdate = $this->conn->prepare(
            "UPDATE escalations SET status = 'rejected', rejected_by = :rejected_by, rejected_at = NOW(), rejection_reason = :reason 
             WHERE report_id = :report_id AND status = 'pending'"
        );
        $escUpdate->bindParam(":rejected_by", $rejected_by);
        $escUpdate->bindParam(":reason", $reason);
        $escUpdate->bindParam(":report_id", $report_id);
        return $escUpdate->execute();
    }

    // ============================================
    // UPDATE STATUS
    // ============================================
    public function updateStatus($report_id, $new_status, $extra_data = null) {
        $query = "UPDATE " . $this->table . " SET status = :status WHERE id = :id";
        $params = [':status' => $new_status, ':id' => $report_id];
        
        if($new_status == self::STATUS_RESOLVED) {
            $query .= ", resolved_at = NOW()";
        }
        if($new_status == self::STATUS_REJECTED && isset($extra_data['rejection_reason'])) {
            $query .= ", rejection_reason = :rejection_reason, rejected_at = NOW()";
            $params[':rejection_reason'] = $extra_data['rejection_reason'];
            if (isset($extra_data['rejected_by'])) {
                $query .= ", rejected_by = :rejected_by";
                $params[':rejected_by'] = $extra_data['rejected_by'];
            }
        }
        if(isset($extra_data['verified_by'])) {
            $query .= ", verified_by = :verified_by, verified_at = NOW()";
            $params[':verified_by'] = $extra_data['verified_by'];
        }

        $stmt = $this->conn->prepare($query);
        foreach($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        return $stmt->execute();
    }

    // ============================================
    // BARANGAY VIEWS REPORT (Submitted -> Under Review)
    // ============================================
    public function markUnderReview($report_id) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_PENDING) {
            return false;
        }

        $query = "UPDATE " . $this->table . " 
                  SET status = :status, viewed_at = NOW() 
                  WHERE id = :id AND status = :pending";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":status", self::STATUS_UNDER_REVIEW);
        $stmt->bindValue(":id", $report_id);
        $stmt->bindValue(":pending", self::STATUS_PENDING);
        return $stmt->execute();
    }

    // ============================================
    // BARANGAY VERIFIES REPORT (Under Review -> In Progress)
    // ============================================
    public function verifyReport($report_id, $verified_by) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_UNDER_REVIEW) {
            return false;
        }

        return $this->updateStatus($report_id, self::STATUS_IN_PROGRESS, [
            'verified_by' => $verified_by
        ]);
    }

    // ============================================
    // BARANGAY / MENRO RESOLVES REPORT (-> Resolved)
    // ============================================
    public function resolveReport($report_id) {
        $report = $this->getReportById($report_id);
        if (!$report || in_array($report['status'], [self::STATUS_RESOLVED, self::STATUS_REJECTED, self::STATUS_CANCELLED])) {
            return false;
        }
        return $this->updateStatus($report_id, self::STATUS_RESOLVED);
    }

    // ============================================
    // REJECT REPORT (Barangay rejects an invalid/duplicate report)
    // ============================================
    public function rejectReport($report_id, $reason, $rejected_by) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['status'] != self::STATUS_UNDER_REVIEW) {
            return false;
        }

        $update = $this->updateStatus($report_id, self::STATUS_REJECTED, [
            'rejection_reason' => $reason,
            'rejected_by' => $rejected_by
        ]);
        if ($update) {
            if ($report['latitude'] && $report['longitude']) {
                $this->recalcReportsNearLocation($report['latitude'], $report['longitude'], $report_id);
            }
            return true;
        }
        return false;
    }

    // ============================================
    // CANCEL REPORT (Resident cancels their own report)
    // ============================================
    public function cancelReport($report_id, $user_id, $remarks = null) {
        $report = $this->getReportById($report_id);
        if (!$report || $report['user_id'] != $user_id || $report['status'] != self::STATUS_PENDING) {
            return false;
        }
        
        $query = "UPDATE " . $this->table . " 
                  SET status = :status, cancelled_at = NOW()";
        $params = [':status' => self::STATUS_CANCELLED, ':id' => $report_id];
        if ($remarks !== null && $remarks !== '') {
            $query .= ", cancellation_remarks = :remarks";
            $params[':remarks'] = $remarks;
        }
        $query .= " WHERE id = :id AND user_id = :user_id AND status = :pending";
        $params[':user_id'] = $user_id;
        $params[':pending'] = self::STATUS_PENDING;

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $update = $stmt->execute();

        if ($update && $stmt->rowCount() > 0) {
            if ($report['latitude'] && $report['longitude']) {
                $this->recalcReportsNearLocation($report['latitude'], $report['longitude'], $report_id);
            }
            return true;
        }
        return false;
    }

    // ============================================
    // ALGORITHM METHODS
    // ============================================

    /**
     * Count active, unresolved reports within radius (meters) of given coordinates
     * Excludes 'cancelled', 'resolved', 'rejected' statuses
     * INCLUDES 'under_review' as it is active
     */
    public function countActiveReportsWithinRadius($lat, $lng, $radiusMeters = 50, $excludeId = null) {
        $earthRadius = 6371;
        $radiusKm = $radiusMeters / 1000;
        
        $sql = "
            SELECT COUNT(*) as count
            FROM reports
            WHERE 
                (6371 * ACOS(
                    COS(RADIANS(:lat)) * COS(RADIANS(latitude)) * 
                    COS(RADIANS(longitude) - RADIANS(:lng)) + 
                    SIN(RADIANS(:lat)) * SIN(RADIANS(latitude))
                )) <= :radius_km
                AND status NOT IN (:cancelled, :resolved, :rejected)
        ";
        
        $params = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':radius_km' => $radiusKm,
            ':cancelled' => self::STATUS_CANCELLED,
            ':resolved' => self::STATUS_RESOLVED,
            ':rejected' => self::STATUS_REJECTED
        ];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Count how many unique users have verified/upvoted this report
     */
    public function countVerifications($report_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM report_verifications WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['count'];
    }

    /**
     * Has this user already verified/upvoted this report?
     */
    public function hasUserVerified($report_id, $user_id) {
        $stmt = $this->conn->prepare("SELECT id FROM report_verifications WHERE report_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$report_id, $user_id]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Record a verification/upvote ("Yes, this is it" / "Support this report").
     * Guards against: missing report, self-upvoting your own report, duplicate
     * votes from the same user, and voting on inactive reports (cancelled/
     * resolved/rejected). Returns an array: ['success' => bool, 'message' => string].
     */
    public function addVerification($report_id, $user_id) {
        $report = $this->getReportById($report_id);
        if (!$report) {
            return ['success' => false, 'message' => 'Report not found.'];
        }
        if ((int)$report['user_id'] === (int)$user_id) {
            return ['success' => false, 'message' => 'You cannot support your own report.'];
        }
        if (in_array($report['status'], [self::STATUS_CANCELLED, self::STATUS_RESOLVED, self::STATUS_REJECTED], true)) {
            return ['success' => false, 'message' => 'This report is no longer active and cannot be supported.'];
        }
        if ($this->hasUserVerified($report_id, $user_id)) {
            return ['success' => false, 'message' => 'You have already supported this report.'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO report_verifications (report_id, user_id, created_at)
            VALUES (:report_id, :user_id, NOW())
        ");
        $inserted = $stmt->execute([
            ':report_id' => $report_id,
            ':user_id' => $user_id
        ]);

        if (!$inserted) {
            return ['success' => false, 'message' => 'Failed to record your support. Please try again.'];
        }

        // Recalculate this report's severity score now that the density/verification count changed
        $this->calculateAndUpdateSeverity($report_id);

        return ['success' => true, 'message' => 'Thank you for verifying this report!'];
    }

    /**
     * Get active reports (not cancelled/resolved/rejected) within radius (meters)
     * Used for duplicate detection on the submission page.
     * If $categoryId is provided (> 0), results are limited to that category,
     * since a "did you mean...?" prompt should only surface likely duplicates
     * of the same kind of issue (e.g. flooding near flooding, not near a streetlight report).
     * 
     * Excludes:
     * - Reports owned by $excludeUserId
     * - Reports already supported/verified by $excludeUserId
     */
    public function getActiveReportsNearLocation($lat, $lng, $radiusMeters = 50, $categoryId = 0, $excludeUserId = null) {
        $radiusKm = $radiusMeters / 1000;
        $sql = "
            SELECT r.id, r.title, r.description, r.status, r.created_at, r.category_id,
                   c.name as category_name,
                   CONCAT(u.first_name, ' ', u.last_name) as reporter_name,
                   (6371 * ACOS(
                       COS(RADIANS(:lat)) * COS(RADIANS(r.latitude)) *
                       COS(RADIANS(r.longitude) - RADIANS(:lng)) +
                       SIN(RADIANS(:lat)) * SIN(RADIANS(r.latitude))
                   )) as distance_km
            FROM reports r
            JOIN categories c ON r.category_id = c.id
            JOIN users u ON r.user_id = u.id
            WHERE r.status NOT IN (:cancelled, :resolved, :rejected)
        ";
        if ($categoryId > 0) {
            $sql .= " AND r.category_id = :category_id";
        }
        if ($excludeUserId) {
            $sql .= " AND r.user_id != :exclude_user_id";
            // Also exclude reports that this user has already supported
            $sql .= " AND NOT EXISTS (
                SELECT 1 FROM report_verifications rv 
                WHERE rv.report_id = r.id AND rv.user_id = :exclude_user_id
            )";
        }
        $sql .= "
            HAVING distance_km <= :radius_km
            ORDER BY distance_km ASC
            LIMIT 5
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':lat', $lat);
        $stmt->bindValue(':lng', $lng);
        $stmt->bindValue(':radius_km', $radiusKm);
        $stmt->bindValue(':cancelled', self::STATUS_CANCELLED);
        $stmt->bindValue(':resolved', self::STATUS_RESOLVED);
        $stmt->bindValue(':rejected', self::STATUS_REJECTED);
        if ($categoryId > 0) {
            $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        }
        if ($excludeUserId) {
            $stmt->bindValue(':exclude_user_id', $excludeUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate severity score with full formula:
     * Base Weight (1-10) + Impact Modifier (0-4) + Spatial Density (0-6) + Verification Bonus (0-5) = Score (1-20)
     * Plus Emergency Override Rule
     * AND auto-update risk_level
     */
    public function calculateAndUpdateSeverity($reportId) {
        $report = $this->getReportById($reportId);
        if (!$report) return false;
        
        // 1. Get Base Weight from category
        $catStmt = $this->conn->prepare("SELECT base_weight FROM categories WHERE id = ?");
        $catStmt->execute([$report['category_id']]);
        $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
        $baseWeight = $cat ? (int)$cat['base_weight'] : 1;
        
        // 2. Impact Modifier: the report stores a raw impact TIER (0/2/4 = Minor/Moderate/Severe).
        // Look up the admin-configured POINT value for that tier. Falls back to the tier id
        // itself (0/2/4) if not explicitly configured, matching the factory defaults.
        $impactTier = isset($report['impact_modifier']) ? (int)$report['impact_modifier'] : 0;
        $impactPoints = (int)SettingsHelper::get('impact_modifier_' . $impactTier, $impactTier);
        
        // 3. Count active reports within the admin-configured clustering radius
        // (excluding this one, and excluding cancelled/resolved/rejected)
        $radius = (int)SettingsHelper::get('clustering_radius_meters', 50);
        $densityCount = $this->countActiveReportsWithinRadius(
            $report['latitude'], 
            $report['longitude'], 
            $radius, 
            $reportId
        );
        
        // 4. Determine Density Points using admin-configured brackets
        // (0 nearby / 1-2 nearby / 3-5 nearby / 6+ nearby, matching the settings UI labels)
        if ($densityCount == 0) {
            $densityPoints = (int)SettingsHelper::get('density_points_0', 0);
        } elseif ($densityCount >= 1 && $densityCount <= 2) {
            $densityPoints = (int)SettingsHelper::get('density_points_2', 2);
        } elseif ($densityCount >= 3 && $densityCount <= 5) {
            $densityPoints = (int)SettingsHelper::get('density_points_4', 4);
        } else {
            $densityPoints = (int)SettingsHelper::get('density_points_6', 6);
        }
        
        // 4.5 Get verification count and compute bonus
        $verificationCount = $this->countVerifications($reportId);
        $pointsPerUpvote = (int)SettingsHelper::get('verification_points_per_upvote', 1);
        $maxBonus = (int)SettingsHelper::get('verification_max_points', 5);
        $verificationBonus = min($verificationCount * $pointsPerUpvote, $maxBonus);
        
        // 5. Calculate Final Score (add verification bonus)
        $finalScore = $baseWeight + $impactPoints + $densityPoints + $verificationBonus;
        
        // 6. Decision Matrix - bands scale off the admin-configured Critical Threshold
        $criticalThreshold = (int)SettingsHelper::get('critical_threshold_score', 15);
        $decision = $this->getDecisionFromScore($finalScore, $criticalThreshold);
        
        // 7. EMERGENCY OVERRIDE RULE: a Severe-impact report (tier 4) is never allowed to
        // score below the "Immediate Intervention" (Orange) band, regardless of the math above.
        if ($impactTier == 4 && $decision['pin'] === 'Green') {
            $finalScore = max($finalScore, $this->getOrangeBandStart($criticalThreshold));
            $decision = $this->getDecisionFromScore($finalScore, $criticalThreshold);
        } elseif ($impactTier == 4 && $decision['pin'] === 'Yellow') {
            $finalScore = $this->getOrangeBandStart($criticalThreshold);
            $decision = $this->getDecisionFromScore($finalScore, $criticalThreshold);
        }
        
        // Map pin to risk_level
        $risk_level_map = [
            'Green'  => 'low',
            'Yellow' => 'medium',
            'Orange' => 'high',
            'Red'    => 'critical'
        ];
        $risk_level = $risk_level_map[$decision['pin']] ?? 'low';
        
        // 8. Update report (including verification_count)
        $update = $this->conn->prepare("
            UPDATE reports SET
                severity_score = :score,
                spatial_density_count = :density_count,
                spatial_density_factor = :density_factor,
                verification_count = :verification_count,
                decision_pin = :pin,
                decision_classification = :classification,
                decision_support = :support,
                risk_level = :risk_level
            WHERE id = :id
        ");
        
        return $update->execute([
            ':score' => $finalScore,
            ':density_count' => $densityCount,
            ':density_factor' => $densityPoints,
            ':verification_count' => $verificationCount,
            ':pin' => $decision['pin'],
            ':classification' => $decision['classification'],
            ':support' => $decision['support'],
            ':risk_level' => $risk_level,
            ':id' => $reportId
        ]);
    }

    /**
     * Decision Matrix - three bands (Green/Yellow/Orange) scaled proportionally
     * below the admin-configured Critical Threshold, which marks the start of Red.
     * With the default threshold (15) this gives: Green 1-4, Yellow 5-8, Orange 9-14, Red 15+.
     */
    private function getDecisionFromScore($score, $criticalThreshold = 15) {
        $criticalThreshold = max(4, $criticalThreshold); // need room for at least 3 bands below it
        $bandWidth = max(1, (int)floor(($criticalThreshold - 1) / 3));
        $yellowStart = $bandWidth + 1;
        $orangeStart = $bandWidth * 2 + 1;

        if ($score < $yellowStart) {
            return [
                'pin' => 'Green',
                'classification' => 'Routine Monitoring',
                'support' => 'Routine Monitoring: No immediate dispatch needed. Handle during standard Barangay clearing operations.'
            ];
        } elseif ($score < $orangeStart) {
            return [
                'pin' => 'Yellow',
                'classification' => 'Action Required',
                'support' => 'Action Required: Barangay must verify the report and schedule a localized intervention within 48 to 72 hours.'
            ];
        } elseif ($score < $criticalThreshold) {
            return [
                'pin' => 'Orange',
                'classification' => 'Immediate Intervention',
                'support' => 'Immediate Intervention: Escalate to MENRO. Dispatch hazard clearing team to prevent secondary damage or flooding.'
            ];
        } else {
            return [
                'pin' => 'Red',
                'classification' => 'Emergency Response',
                'support' => 'Emergency Response: Immediate multi-agency coordination required (MENRO/MDRRMO) for urgent mitigation.'
            ];
        }
    }

    /**
     * Start-of-Orange-band score for a given critical threshold. Used by the
     * Emergency Override Rule to guarantee Severe-impact reports never sit below
     * "Immediate Intervention", matching the same proportional bands above.
     */
    private function getOrangeBandStart($criticalThreshold = 15) {
        $criticalThreshold = max(4, $criticalThreshold);
        $bandWidth = max(1, (int)floor(($criticalThreshold - 1) / 3));
        return $bandWidth * 2 + 1;
    }

    /**
     * Recalculate severity for all active reports within radius of given coordinates
     */
    public function recalcReportsNearLocation($lat, $lng, $excludeId = null) {
        $sql = "
            SELECT id FROM reports
            WHERE 
                (6371 * ACOS(
                    COS(RADIANS(:lat)) * COS(RADIANS(latitude)) * 
                    COS(RADIANS(longitude) - RADIANS(:lng)) + 
                    SIN(RADIANS(:lat)) * SIN(RADIANS(latitude))
                )) <= 0.05
                AND status NOT IN (:cancelled, :resolved, :rejected)
        ";
        $params = [
            ':lat' => $lat,
            ':lng' => $lng,
            ':cancelled' => self::STATUS_CANCELLED,
            ':resolved' => self::STATUS_RESOLVED,
            ':rejected' => self::STATUS_REJECTED
        ];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($ids as $id) {
            $this->calculateAndUpdateSeverity($id);
        }
    }
    
    /**
     * Reclassify impact modifier (for barangay officials)
     */
    public function reclassifyImpact($reportId, $newImpact, $userId) {
        $validImpacts = [0, 2, 4];
        if (!in_array($newImpact, $validImpacts)) {
            return false;
        }
        
        $update = $this->conn->prepare("
            UPDATE reports SET 
                impact_modifier = :impact,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $result = $update->execute([
            ':impact' => $newImpact,
            ':id' => $reportId
        ]);
        
        if ($result) {
            // Recalculate severity after reclassification
            $this->calculateAndUpdateSeverity($reportId);
            
            // Log the reclassification
            $log = $this->conn->prepare("
                INSERT INTO activity_logs (user_id, action, description, ip_address, created_at)
                VALUES (?, 'Reclassify Impact', ?, ?, NOW())
            ");
            $description = "Reclassified report #$reportId impact modifier to $newImpact";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $log->execute([$userId, $description, $ip]);
            
            return true;
        }
        return false;
    }
}
?>