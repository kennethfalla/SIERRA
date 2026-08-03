<?php
// helpers/SecurityHelper.php - COMPLETE SECURITY SYSTEM
// Features: Rate Limiting, Input Sanitization, XSS Prevention, CSRF Protection

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Rate Limiting Class
 * Tracks failed login attempts and manages lockouts
 */
class RateLimiter {
    private $db;
    private $maxAttempts = 5;        // Maximum failed attempts before lockout
    private $lockoutTime = 300;      // Lockout duration in seconds (5 minutes)
    private $windowTime = 300;       // Time window for counting attempts (5 minutes)
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Check if user has exceeded rate limit for failed logins
     * @param string $identifier Unique identifier (IP + email/mobile hash)
     * @throws Exception If rate limited
     * @return bool True if allowed to attempt login
     */
    public function checkRateLimit($identifier) {
        // Clean expired entries first
        $this->cleanExpiredEntries();
        
        // Check if currently locked out
        if ($this->isLockedOut($identifier)) {
            $remainingMinutes = $this->getLockoutTimeRemaining($identifier);
            throw new Exception("Too many failed attempts. Please try again after {$remainingMinutes} minutes.");
        }
        
        // Count failed attempts in the time window
        $failedAttempts = $this->getFailedAttempts($identifier);
        
        // If max attempts reached, lock the account
        if ($failedAttempts >= $this->maxAttempts) {
            $this->lockAccount($identifier);
            $minutes = ceil($this->lockoutTime / 60);
            throw new Exception("Account locked. Please try again after {$minutes} minutes.");
        }
        
        return true;
    }
    
    /**
     * Record a failed login attempt
     * @param string $identifier Unique identifier
     */
    public function recordFailedAttempt($identifier) {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (identifier, action, attempted_at) 
             VALUES (?, 'login', NOW())"
        );
        $stmt->execute([$identifier]);
    }
    
    /**
     * Reset all failed attempts and remove lockout on successful login
     * @param string $identifier Unique identifier
     */
    public function resetOnSuccess($identifier) {
        // Delete all failed attempts for this identifier
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE identifier = ?");
        $stmt->execute([$identifier]);
        
        // Remove any active lockout
        $stmt = $this->db->prepare("DELETE FROM rate_lockouts WHERE identifier = ?");
        $stmt->execute([$identifier]);
    }
    
    /**
     * Check if identifier is currently locked out
     */
    private function isLockedOut($identifier) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rate_lockouts 
             WHERE identifier = ? AND locked_until > NOW()"
        );
        $stmt->execute([$identifier]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get remaining lockout time in minutes
     */
    private function getLockoutTimeRemaining($identifier) {
        $stmt = $this->db->prepare(
            "SELECT TIMESTAMPDIFF(MINUTE, NOW(), locked_until) 
             FROM rate_lockouts 
             WHERE identifier = ? AND locked_until > NOW()
             ORDER BY locked_until DESC LIMIT 1"
        );
        $stmt->execute([$identifier]);
        return max(1, intval($stmt->fetchColumn()));
    }
    
    /**
     * Get remaining lockout time in seconds (for frontend countdown)
     */
    public function getLockoutSecondsRemaining($identifier) {
        $stmt = $this->db->prepare(
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), locked_until) 
             FROM rate_lockouts 
             WHERE identifier = ? AND locked_until > NOW()
             ORDER BY locked_until DESC LIMIT 1"
        );
        $stmt->execute([$identifier]);
        return max(0, intval($stmt->fetchColumn()));
    }
    
    /**
     * Count failed attempts in the time window
     */
    private function getFailedAttempts($identifier) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rate_limits 
             WHERE identifier = ? 
             AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $this->windowTime]);
        return intval($stmt->fetchColumn());
    }
    
    /**
     * Lock the account for the specified duration
     */
    private function lockAccount($identifier) {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_lockouts (identifier, action, locked_until) 
             VALUES (?, 'login', DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE locked_until = DATE_ADD(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $this->lockoutTime, $this->lockoutTime]);
    }
    
    /**
     * Clean expired entries from both tables
     */
    private function cleanExpiredEntries() {
        $this->db->exec(
            "DELETE FROM rate_limits 
             WHERE attempted_at < DATE_SUB(NOW(), INTERVAL {$this->windowTime} SECOND)"
        );
        $this->db->exec("DELETE FROM rate_lockouts WHERE locked_until < NOW()");
    }
    
    /**
     * Get current failed attempt count
     */
    public function getAttemptCount($identifier) {
        return $this->getFailedAttempts($identifier);
    }
    
    /**
     * Get remaining attempts before lockout
     */
    public function getRemainingAttempts($identifier) {
        $current = $this->getFailedAttempts($identifier);
        return max(0, $this->maxAttempts - $current);
    }
}

/**
 * Input Sanitization Class
 * Comprehensive input sanitization and XSS prevention
 */
class InputSanitizer {
    
    // ========================================
    // BASIC CLEANING METHODS
    // ========================================
    
    /**
     * Basic string cleaning (legacy support)
     * @param mixed $data String or array to clean
     * @return mixed Cleaned data
     */
    public static function clean($data) {
        if (is_array($data)) {
            return array_map([self::class, 'clean'], $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    /**
     * Sanitize plain text string - Remove ALL HTML/PHP tags
     * Use for: names, addresses, titles, simple text fields
     * 
     * @param string $string Input to sanitize
     * @param int $maxLength Maximum allowed length (0 = no limit)
     * @return string Sanitized string
     */
    public static function sanitizeString($string, $maxLength = 255) {
        // Handle null/empty
        if ($string === null || $string === '') {
            return '';
        }
        
        $string = trim($string);
        
        // Remove NULL bytes (used in poison null byte attacks)
        $string = str_replace(chr(0), '', $string);
        
        // Strip ALL HTML and PHP tags completely
        $string = strip_tags($string);
        
        // Decode HTML entities to prevent double encoding
        $string = html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove any remaining HTML entities
        $string = preg_replace('/&[#a-zA-Z0-9]+;/', '', $string);
        
        // Remove invisible/control characters (except space, newline, tab)
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $string);
        
        // Remove Unicode zero-width characters (used in homograph attacks)
        $string = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $string);
        
        // Normalize multiple spaces to single space
        $string = preg_replace('/\s+/', ' ', $string);
        
        // Limit length if specified
        if ($maxLength > 0 && mb_strlen($string, 'UTF-8') > $maxLength) {
            $string = mb_substr($string, 0, $maxLength, 'UTF-8');
        }
        
        return trim($string);
    }
    
    /**
     * Sanitize rich text - Allow only safe HTML formatting tags
     * Use for: descriptions, notes, comments that need basic formatting
     * 
     * @param string $text Input text
     * @param int $maxLength Maximum allowed length
     * @return string Sanitized text with only safe HTML
     */
    public static function sanitizeRichText($text, $maxLength = 5000) {
        if ($text === null || $text === '') {
            return '';
        }
        
        $text = trim($text);
        
        // Remove NULL bytes
        $text = str_replace(chr(0), '', $text);
        
        // List of allowed HTML tags (whitelist approach)
        $allowed_tags = '<p><br><b><i><u><em><strong><ul><ol><li><h3><h4><h5><h6>';
        
        // Strip all tags EXCEPT the allowed ones
        $text = strip_tags($text, $allowed_tags);
        
        // Remove ALL attributes from allowed tags (prevent onclick, onload, style, etc.)
        $text = preg_replace('/<([a-z][a-z0-9]*)\s[^>]*?(\/?)>/i', '<$1$2>', $text);
        
        // Remove javascript: protocol in any remaining attributes
        $text = preg_replace('/javascript\s*:/i', '', $text);
        
        // Remove event handlers that might remain
        $text = preg_replace('/on\w+\s*=\s*"[^"]*"/i', '', $text);
        $text = preg_replace("/on\w+\s*=\s*'[^']*'/i", '', $text);
        $text = preg_replace('/on\w+\s*=\s*\w+/i', '', $text);
        
        // Remove data: URLs that could contain scripts
        $text = preg_replace('/data\s*:/i', '', $text);
        
        // Remove invisible/control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        // Remove Unicode zero-width characters
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Limit length
        if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        
        return trim($text);
    }
    
    // ========================================
    // OUTPUT ESCAPING
    // ========================================
    
    /**
     * Escape string for safe HTML output
     * Use when DISPLAYING any data that came from users/database
     * 
     * @param mixed $data String or array to escape
     * @return mixed Escaped data
     */
    public static function escapeForHtml($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escapeForHtml'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Escape for JavaScript inline
     */
    public static function escapeForJs($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * Escape for URL parameter
     */
    public static function escapeForUrl($data) {
        return urlencode($data);
    }
    
    // ========================================
    // SPECIFIC FIELD SANITIZERS
    // ========================================
    
    /**
     * Sanitize email address
     * @param string $email
     * @return string|false Sanitized email or false if invalid
     */
    public static function sanitizeEmail($email) {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
    
    /**
     * Sanitize Philippine mobile number
     * @param string $phone
     * @return string|false Sanitized 11-digit number or false
     */
    public static function sanitizePhone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle 10-digit numbers (add leading 0)
        if (strlen($phone) === 10 && strpos($phone, '9') === 0) {
            $phone = '0' . $phone;
        }
        
        // Validate: 11 digits starting with 09
        return (strlen($phone) === 11 && preg_match('/^09/', $phone)) ? $phone : false;
    }
    
    /**
     * Sanitize person's name
     * Allows letters, spaces, hyphens, apostrophes, periods
     * Supports Filipino characters (Ñ, ñ)
     * 
     * @param string $name
     * @return string Sanitized name
     */
    public static function sanitizeName($name) {
        $name = trim($name);
        
        // Remove all characters except allowed ones
        $name = preg_replace('/[^a-zA-Z\s\-\.\'Ññ]/u', '', $name);
        
        // Normalize spaces
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Title case
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        
        return $name;
    }
    
    /**
     * Sanitize alphanumeric string (letters, numbers, spaces, hyphens only)
     * @param string $string
     * @return string
     */
    public static function sanitizeAlphanumeric($string) {
        return preg_replace('/[^a-zA-Z0-9\s\-]/', '', trim($string));
    }
    
    /**
     * Sanitize integer value
     * @param mixed $value
     * @param int $default Default value if invalid
     * @return int
     */
    public static function sanitizeInt($value, $default = 0) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return ($value !== false) ? $value : $default;
    }
    
    /**
     * Sanitize float value
     * @param mixed $value
     * @param float $default Default value if invalid
     * @return float
     */
    public static function sanitizeFloat($value, $default = 0.0) {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        return ($value !== false) ? $value : $default;
    }
    
    /**
     * Sanitize URL
     * @param string $url
     * @return string|false Sanitized URL or false if invalid
     */
    public static function sanitizeUrl($url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        // Only allow http and https protocols
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
    }
    
    // ========================================
    // VALIDATION METHODS
    // ========================================
    
    /**
     * Validate password strength
     * Requirements: 8+ chars, uppercase, lowercase, number, special char, no spaces
     * 
     * @param string $password
     * @return array Array of error messages (empty if valid)
     */
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least 1 uppercase letter (A-Z)";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least 1 lowercase letter (a-z)";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least 1 number (0-9)";
        }
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $errors[] = "Password must contain at least 1 special character (!@#$%^&*...)";
        }
        if (preg_match('/\s/', $password)) {
            $errors[] = "Password cannot contain spaces";
        }
        
        return $errors;
    }
    
    /**
     * Validate and normalize login input (email or mobile)
     * @param string $input
     * @return array ['valid' => bool, 'type' => 'email'|'mobile', 'cleaned' => string]
     */
    public static function validateLoginInput($input) {
        $input = trim($input);
        
        // Check if input is purely numeric (mobile number)
        if (preg_match('/^[0-9]+$/', $input)) {
            $cleaned = preg_replace('/[^0-9]/', '', $input);
            
            // Handle 10-digit mobile numbers (09XXXXXXXXX format)
            if (strlen($cleaned) === 10 && strpos($cleaned, '9') === 0) {
                $cleaned = '0' . $cleaned;
            }
            
            if (strlen($cleaned) === 11 && preg_match('/^09/', $cleaned)) {
                return ['valid' => true, 'type' => 'mobile', 'cleaned' => $cleaned];
            }
            return ['valid' => false, 'type' => 'mobile', 'cleaned' => $input];
        }
        
        // Check if input is an email
        $cleaned = filter_var($input, FILTER_SANITIZE_EMAIL);
        if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => true, 'type' => 'email', 'cleaned' => $cleaned];
        }
        
        return ['valid' => false, 'type' => 'email', 'cleaned' => $input];
    }
    
    /**
     * Validate file upload
     * @param array $file $_FILES array element
     * @param array $allowedExtensions ['jpg', 'png', etc.]
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateFileUpload($file, $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Server temporary folder missing',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorMsg = $errors[$file['error']] ?? 'Unknown upload error';
            return ['valid' => false, 'error' => $errorMsg];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File exceeds maximum size of ' . ($maxSize / 1048576) . 'MB'];
        }
        
        // Check file extension
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExtensions)) {
            return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)];
        }
        
        // Verify MIME type (don't trust extension alone)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp']
        ];
        
        $validMime = false;
        foreach ($allowedMimes as $mime => $exts) {
            if ($mimeType === $mime && in_array($fileExt, $exts)) {
                $validMime = true;
                break;
            }
        }
        
        if (!$validMime) {
            return ['valid' => false, 'error' => 'File content does not match expected type'];
        }
        
        return ['valid' => true, 'error' => '', 'mime' => $mimeType, 'ext' => $fileExt];
    }
    
    // ========================================
    // CSRF PROTECTION
    // ========================================
    
    /**
     * Generate CSRF token and store in session
     * @return string CSRF token
     */
    public static function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token against session
     * Uses timing-safe comparison to prevent timing attacks
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public static function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Regenerate CSRF token (call after login, privilege changes)
     * @return string New CSRF token
     */
    public static function regenerateCsrfToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Get CSRF hidden input field HTML
     * @return string HTML input field
     */
    public static function csrfField() {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
    
    /**
     * Generate a secure random token
     * @param int $length Token length in bytes
     * @return string Hex-encoded token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
}
?>