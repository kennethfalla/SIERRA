<?php
// models/Category.php - COMPLETE UPDATED VERSION
class Category {
    private $conn;
    private $table = "categories";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $query = "SELECT * FROM " . $this->table . " ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Get all categories with usage count (how many reports use each category)
     */
    public function getAllWithUsageCount() {
        $query = "SELECT c.*, COUNT(r.id) as usage_count 
                  FROM " . $this->table . " c
                  LEFT JOIN reports r ON c.id = r.category_id
                  GROUP BY c.id
                  ORDER BY c.name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getActive() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_active = 1 ORDER BY name";
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

    public function create($name, $description, $icon_class, $base_weight = 1) {
        $query = "INSERT INTO " . $this->table . " (name, description, icon_class, base_weight) VALUES (:name, :description, :icon_class, :base_weight)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":icon_class", $icon_class);
        $stmt->bindParam(":base_weight", $base_weight);
        return $stmt->execute();
    }

    public function update($id, $name, $description, $icon_class, $is_active, $base_weight = 1) {
        $query = "UPDATE " . $this->table . " SET name = :name, description = :description, icon_class = :icon_class, is_active = :is_active, base_weight = :base_weight WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":name", $name);
        $stmt->bindParam(":description", $description);
        $stmt->bindParam(":icon_class", $icon_class);
        $stmt->bindParam(":is_active", $is_active);
        $stmt->bindParam(":base_weight", $base_weight);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    /**
     * Check if category is used in any reports
     */
    public function isUsed($category_id) {
        $query = "SELECT COUNT(*) as count FROM reports WHERE category_id = :category_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":category_id", $category_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Toggle category status (activate/deactivate)
     */
    public function toggleStatus($id) {
        $query = "SELECT is_active FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_status = $current['is_active'] ? 0 : 1;
        
        $query = "UPDATE " . $this->table . " SET is_active = :is_active WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":is_active", $new_status);
        $stmt->bindParam(":id", $id);
        return $stmt->execute();
    }
    
    /**
     * Get categories by weight range
     */
    public function getByWeightRange($min_weight, $max_weight) {
        $query = "SELECT * FROM " . $this->table . " WHERE is_active = 1 AND base_weight BETWEEN :min_weight AND :max_weight ORDER BY base_weight DESC, name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":min_weight", $min_weight);
        $stmt->bindParam(":max_weight", $max_weight);
        $stmt->execute();
        return $stmt;
    }
    
    /**
     * Get high priority categories (weight 7-10)
     */
    public function getHighPriority() {
        return $this->getByWeightRange(7, 10);
    }
    
    /**
     * Get medium priority categories (weight 4-6)
     */
    public function getMediumPriority() {
        return $this->getByWeightRange(4, 6);
    }
    
    /**
     * Get low priority categories (weight 1-3)
     */
    public function getLowPriority() {
        return $this->getByWeightRange(1, 3);
    }
}
?>