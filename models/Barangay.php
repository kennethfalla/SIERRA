<?php
// models/Barangay.php - COMPLETE VERSION
class Barangay {
    private $conn;
    private $table = "barangays";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReportStats($barangay_id = null) {
        $query = "SELECT b.id, b.name, COUNT(r.id) as report_count,
                         SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                         SUM(CASE WHEN r.status = 'verified' THEN 1 ELSE 0 END) as verified_count,
                         SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
                  FROM " . $this->table . " b
                  LEFT JOIN reports r ON b.id = r.barangay_id";
        if($barangay_id) {
            $query .= " WHERE b.id = :barangay_id";
        }
        $query .= " GROUP BY b.id ORDER BY report_count DESC";
        
        $stmt = $this->conn->prepare($query);
        if($barangay_id) {
            $stmt->bindParam(":barangay_id", $barangay_id);
        }
        $stmt->execute();
        return $stmt;
    }
}
?>