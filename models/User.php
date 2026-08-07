<?php
// models/User.php - COMPLETE USER MODEL
// Includes: Registration, Login, Profile Management, Password Reset, Staff Account Creation

class User {
    private $conn;
    private $table = "users";

    // Public properties (used for registration and updates)
    public $id;
    public $first_name;
    public $last_name;
    public $email;
    public $contact_number;
    public $password;
    public $role;
    public $barangay_id;
    public $is_resident;
    public $province;
    public $municipality;
    public $non_resident_address;
    public $purok_street;
    public $is_verified;
    public $verification_code;
    public $verification_expires;
    public $is_active;
    public $job_title;
    public $profile_picture;
    public $force_password_reset;

    public function __construct($db) {
        $this->conn = $db;
    }

    // ============================================================
    // REGISTRATION & AUTHENTICATION
    // ============================================================

    /**
     * Register new user with all fields
     * @return bool True on success, false on failure
     */
    public function register() {
        $query = "INSERT INTO " . $this->table . " 
                  SET first_name = :first_name, 
                      last_name = :last_name,
                      email = :email,
                      contact_number = :contact_number, 
                      password_hash = :password, 
                      role = :role, 
                      barangay_id = :barangay_id,
                      is_resident = :is_resident,
                      province = :province,
                      municipality = :municipality,
                      non_resident_address = :non_resident_address,
                      purok_street = :purok_street,
                      is_verified = :is_verified,
                      verification_code = :verification_code,
                      verification_expires = :verification_expires";
        
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($this->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":barangay_id", $this->barangay_id);
        $stmt->bindParam(":is_resident", $this->is_resident);
        $stmt->bindParam(":province", $this->province);
        $stmt->bindParam(":municipality", $this->municipality);
        $stmt->bindParam(":non_resident_address", $this->non_resident_address);
        $stmt->bindParam(":purok_street", $this->purok_street);
        $stmt->bindParam(":is_verified", $this->is_verified);
        $stmt->bindParam(":verification_code", $this->verification_code);
        $stmt->bindParam(":verification_expires", $this->verification_expires);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Login user - accepts email OR contact_number
     * @param string $login Email or contact number
     * @param string $password Plain text password
     * @return array|false User data array or false on failure
     */
    public function login($login, $password) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE (email = :login OR contact_number = :login) AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":login", $login);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password_hash'])) {
                return [
                    'id' => $row['id'],
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'contact_number' => $row['contact_number'],
                    'role' => $row['role'],
                    'barangay_id' => $row['barangay_id'],
                    'is_resident' => $row['is_resident'],
                    'province' => $row['province'],
                    'municipality' => $row['municipality'],
                    'non_resident_address' => $row['non_resident_address'],
                    'purok_street' => $row['purok_street'],
                    'is_verified' => $row['is_verified'],
                    'is_active' => $row['is_active'],
                    'profile_picture' => $row['profile_picture'] ?? '',
                    'job_title' => $row['job_title'] ?? '',
                    'force_password_reset' => $row['force_password_reset'] ?? 0
                ];
            }
        }
        return false;
    }

    // ============================================================
    // PASSWORD RESET - 2-STEP LOGIN FOR STAFF ACCOUNTS
    // ============================================================

    /**
     * Check if user needs to reset password (first login for staff accounts)
     * @param int $user_id User ID
     * @return bool True if password reset is required
     */
    public function needsPasswordReset($user_id) {
        $query = "SELECT force_password_reset FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result && (int)$result['force_password_reset'] == 1);
    }

    /**
     * Set force password reset flag
     * @param int $user_id User ID
     * @param int $value 1 = force reset, 0 = clear
     * @return bool True on success
     */
    public function setForcePasswordReset($user_id, $value = 1) {
        $query = "UPDATE " . $this->table . " SET force_password_reset = :value WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":value", $value, PDO::PARAM_INT);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("User::setForcePasswordReset FAILED for user $user_id. Error: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("User::setForcePasswordReset SUCCESS for user $user_id, set to $value");
        }
        
        return $result;
    }

    /**
     * Check if user is a staff account (barangay_official or admin)
     * @param int $user_id User ID
     * @return bool True if user is staff
     */
    public function isStaffAccount($user_id) {
        $query = "SELECT role FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result && in_array($result['role'], ['barangay_official', 'admin']));
    }

    /**
     * Check if user is a barangay official
     * @param int $user_id User ID
     * @return bool True if user is a barangay official
     */
    public function isBarangayOfficial($user_id) {
        $query = "SELECT role FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result && $result['role'] === 'barangay_official');
    }

    /**
     * Create staff account (barangay_official or admin) with temporary password
     * Used by MENRO admins when creating new staff accounts
     * Automatically sets force_password_reset = 1
     * 
     * @param array $data User data array
     * @param string $temp_password Temporary password (will be hashed)
     * @return int|false New user ID or false on failure
     */
    public function createStaffAccount($data, $temp_password) {
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO " . $this->table . " (
            first_name, 
            last_name, 
            email, 
            contact_number, 
            password_hash, 
            role, 
            role_id,
            user_type,
            barangay_id, 
            is_resident, 
            is_verified, 
            is_active, 
            force_password_reset,
            job_title,
            created_at
        ) VALUES (
            :first_name, 
            :last_name, 
            :email, 
            :contact_number, 
            :password_hash, 
            :role, 
            :role_id,
            :user_type,
            :barangay_id, 
            1, 
            1, 
            1, 
            1,
            :job_title,
            NOW()
        )";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(":first_name", $data['first_name']);
        $stmt->bindValue(":last_name", $data['last_name']);
        $stmt->bindValue(":email", $data['email']);
        $stmt->bindValue(":contact_number", $data['contact_number']);
        $stmt->bindValue(":password_hash", $hashed_password);
        $stmt->bindValue(":role", $data['role']);
        $stmt->bindValue(":role_id", $data['role_id'] ?? null);
        $stmt->bindValue(":user_type", $data['user_type'] ?? null);
        $stmt->bindValue(":barangay_id", $data['barangay_id']);
        $stmt->bindValue(":job_title", $data['job_title'] ?? null);
        
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // ============================================================
    // PASSWORD MANAGEMENT
    // ============================================================

    /**
     * Update user password
     * @param int $user_id User ID
     * @param string $new_password Plain text new password
     * @return bool True on success
     */
    public function updatePassword($user_id, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        error_log("User::updatePassword called for user $user_id. Hash: " . substr($hashed_password, 0, 20) . "...");
        
        $query = "UPDATE " . $this->table . " SET password_hash = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("User::updatePassword FAILED for user $user_id. Error: " . print_r($stmt->errorInfo(), true));
        } else {
            error_log("User::updatePassword SUCCESS for user $user_id");
        }
        
        return $result;
    }

    /**
     * Verify user's current password
     * @param int $user_id User ID
     * @param string $password Plain text password to check
     * @return bool True if password matches
     */
    public function verifyPassword($user_id, $password) {
        $query = "SELECT password_hash FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result) {
            if (function_exists('password_verify')) {
                return password_verify($password, $result['password_hash']);
            }

            return hash_equals($result['password_hash'], crypt($password, $result['password_hash']));
        }
        return false;
    }

    // ============================================================
    // READ OPERATIONS
    // ============================================================

    /**
     * Get full name helper method
     * @return string Full name
     */
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get all users with barangay names
     * @return PDOStatement
     */
    public function getAllUsers() {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  ORDER BY u.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get user by ID
     * @param int $id User ID
     * @return array|false User data or false
     */
    public function getUserById($id) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by contact number (with active status)
     * @param string $contact_number
     * @return array|false User data or false
     */
    public function getUserByContactNumber($contact_number) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.contact_number = :contact_number AND u.is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":contact_number", $contact_number);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get user by email (with active status)
     * @param string $email
     * @return array|false User data or false
     */
    public function getUserByEmail($email) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.email = :email AND u.is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get users by role
     * @param string $role User role
     * @return PDOStatement
     */
    public function getUsersByRole($role) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.role = :role
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get users by barangay
     * @param int $barangay_id
     * @return PDOStatement
     */
    public function getUsersByBarangay($barangay_id) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.barangay_id = :barangay_id
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":barangay_id", $barangay_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get recent users (limit)
     * @param int $limit
     * @return PDOStatement
     */
    public function getRecentUsers($limit = 5) {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  ORDER BY u.created_at DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Search users by name, email, or contact number
     * @param string $keyword
     * @return PDOStatement
     */
    public function searchUsers($keyword) {
        $searchTerm = "%{$keyword}%";
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.first_name LIKE :search 
                     OR u.last_name LIKE :search 
                     OR u.email LIKE :search
                     OR u.contact_number LIKE :search
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":search", $searchTerm);
        $stmt->execute();
        return $stmt;
    }

    // ============================================================
    // UPDATE OPERATIONS
    // ============================================================

    /**
     * Update user role
     * @param int $user_id
     * @param string $role
     * @return bool
     */
    public function updateRole($user_id, $role) {
        $query = "UPDATE " . $this->table . " SET role = :role WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update user status (active/inactive)
     * @param int $user_id
     * @param int $is_active
     * @return bool
     */
    public function updateStatus($user_id, $is_active) {
        $query = "UPDATE " . $this->table . " SET is_active = :is_active WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":is_active", $is_active, PDO::PARAM_INT);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Verify user
     * @param int $user_id
     * @return bool
     */
    public function verifyUser($user_id) {
        $query = "UPDATE " . $this->table . " SET is_verified = 1, verification_code = NULL, verification_expires = NULL WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update user profile
     * @param int $user_id
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @param string|null $purok_street
     * @param int|null $barangay_id
     * @return bool
     */
    public function updateProfile($user_id, $first_name, $last_name, $email, $purok_street = null, $barangay_id = null) {
        $query = "UPDATE " . $this->table . " 
                  SET first_name = :first_name, 
                      last_name = :last_name, 
                      email = :email,
                      purok_street = :purok_street,
                      barangay_id = :barangay_id
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":first_name", $first_name);
        $stmt->bindParam(":last_name", $last_name);
        $stmt->bindParam(":email", $email);
        $stmt->bindParam(":purok_street", $purok_street);
        $stmt->bindParam(":barangay_id", $barangay_id);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }


    /**
     * Update contact number
     * @param int $user_id
     * @param string $contact_number
     * @return bool
     */
    public function updateContactNumber($user_id, $contact_number) {
        $query = "UPDATE " . $this->table . " SET contact_number = :contact_number WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":contact_number", $contact_number);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update profile picture
     * @param int $user_id
     * @param string $profile_picture Path to profile picture
     * @return bool
     */
    public function updateProfilePicture($user_id, $profile_picture) {
        $query = "UPDATE " . $this->table . " SET profile_picture = :profile_picture WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":profile_picture", $profile_picture);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Update job title
     * @param int $user_id
     * @param string $job_title
     * @return bool
     */
    public function updateJobTitle($user_id, $job_title) {
        $query = "UPDATE " . $this->table . " SET job_title = :job_title WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":job_title", $job_title);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // ============================================================
    // DELETE OPERATIONS
    // ============================================================

    /**
     * Delete user
     * @param int $user_id
     * @return bool
     */
    public function deleteUser($user_id) {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // ============================================================
    // DUPLICATE CHECKS
    // ============================================================

    /**
     * Check if contact number exists (including inactive accounts)
     * @return array|false User data or false
     */
    public function contactNumberExists() {
        $query = "SELECT id, is_active FROM " . $this->table . " WHERE contact_number = :contact_number";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":contact_number", $this->contact_number);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result) {
            return $result;
        }
        return false;
    }

    /**
     * Check if email exists (including inactive accounts)
     * @return array|false User data or false
     */
    public function emailExists() {
        if(empty($this->email)) return false;
        $query = "SELECT id, is_active FROM " . $this->table . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $this->email);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if($result) {
            return $result;
        }
        return false;
    }

    // ============================================================
    // STATISTICS & COUNTS
    // ============================================================

    /**
     * Get user count by role
     * @param string $role
     * @return int
     */
    public function getCountByRole($role) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE role = :role";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":role", $role);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get total count of active users
     * @return int
     */
    public function getTotalActiveUsers() {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " WHERE is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get citizen count by barangay
     * @param int $barangay_id
     * @return int
     */
    public function getCitizenCountByBarangay($barangay_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                  WHERE barangay_id = :barangay_id AND role = 'citizen'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":barangay_id", $barangay_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Get user statistics
     * @return array
     */
    public function getUserStatistics() {
        $query = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'citizen' THEN 1 ELSE 0 END) as total_citizens,
                    SUM(CASE WHEN role = 'barangay_official' THEN 1 ELSE 0 END) as total_officials,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                    SUM(CASE WHEN is_resident = 1 THEN 1 ELSE 0 END) as residents,
                    SUM(CASE WHEN is_resident = 0 THEN 1 ELSE 0 END) as non_residents
                  FROM " . $this->table;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get all citizens
     * @return PDOStatement
     */
    public function getAllCitizens() {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.role = 'citizen'
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Get all barangay officials
     * @return PDOStatement
     */
    public function getAllBarangayOfficials() {
        $query = "SELECT u.*, b.name as barangay_name,
                  CONCAT(u.first_name, ' ', u.last_name) as full_name
                  FROM " . $this->table . " u
                  LEFT JOIN barangays b ON u.barangay_id = b.id
                  WHERE u.role = 'barangay_official'
                  ORDER BY b.name, u.last_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // ============================================================
    // VERIFICATION CODES
    // ============================================================

    /**
     * Save verification code
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function saveVerificationCode($user_id, $code) {
        $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $query = "INSERT INTO verification_codes (user_id, code, expires_at) 
                  VALUES (:user_id, :code, :expires)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":code", $code);
        $stmt->bindParam(":expires", $expires);
        return $stmt->execute();
    }

    /**
     * Verify code
     * @param int $user_id
     * @param string $code
     * @return bool
     */
    public function verifyCode($user_id, $code) {
        $query = "SELECT id FROM verification_codes 
                  WHERE user_id = :user_id AND code = :code 
                  AND expires_at > NOW() AND used = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->bindParam(":code", $code);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            // Mark code as used
            $update = "UPDATE verification_codes SET used = 1 WHERE user_id = :user_id AND code = :code";
            $stmt2 = $this->conn->prepare($update);
            $stmt2->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt2->bindParam(":code", $code);
            $stmt2->execute();
            return true;
        }
        return false;
    }

    // ============================================================
    // ADDITIONAL HELPER METHODS
    // ============================================================

    /**
     * Check if user has active reports
     * @param int $user_id
     * @return bool
     */
    public function hasActiveReports($user_id) {
        $query = "SELECT COUNT(*) as count FROM reports 
                  WHERE user_id = :user_id AND status NOT IN ('resolved', 'rejected', 'cancelled')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'] > 0;
    }

    /**
     * Get user's full name from ID
     * @param int $user_id
     * @return string|null Full name or null if not found
     */
    public function getUserFullName($user_id) {
        $query = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['full_name'] : null;
    }

    /**
     * Get user's role from ID
     * @param int $user_id
     * @return string|null Role or null if not found
     */
    public function getUserRole($user_id) {
        $query = "SELECT role FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['role'] : null;
    }

    /**
     * Get user's barangay ID from ID
     * @param int $user_id
     * @return int|null Barangay ID or null if not found
     */
    public function getUserBarangayId($user_id) {
        $query = "SELECT barangay_id FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['barangay_id'] : null;
    }

    /**
     * Get user's email from ID
     * @param int $user_id
     * @return string|null Email or null if not found
     */
    public function getUserEmail($user_id) {
        $query = "SELECT email FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['email'] : null;
    }

    /**
     * Get user's contact number from ID
     * @param int $user_id
     * @return string|null Contact number or null if not found
     */
    public function getUserContactNumber($user_id) {
        $query = "SELECT contact_number FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['contact_number'] : null;
    }

    /**
     * Check if user is active
     * @param int $user_id
     * @return bool
     */
    public function isActive($user_id) {
        $query = "SELECT is_active FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && (int)$result['is_active'] == 1;
    }
}
?>