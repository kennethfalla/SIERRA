<?php
// includes/functions.php - COMPLETE HELPERS
// Note: InputSanitizer functions have been moved to helpers/SecurityHelper.php
// Use InputSanitizer::sanitizeString(), InputSanitizer::generateCsrfToken(),
// and InputSanitizer::validateCsrfToken() from helpers/SecurityHelper.php instead.

// ============================================
// BADGE FUNCTIONS
// ============================================

/**
 * Get HTML for status badge
 */
function getStatusBadge($status) {
    $labels = [
        'pending' => ['label' => 'Pending', 'color' => 'yellow', 'icon' => 'fa-clock'],
        'under_review' => ['label' => 'Under Review', 'color' => 'blue', 'icon' => 'fa-search'],  // NEW
        'verified' => ['label' => 'Verified', 'color' => 'blue', 'icon' => 'fa-check-circle'],
        'in_progress' => ['label' => 'In Progress', 'color' => 'emerald', 'icon' => 'fa-spinner fa-pulse'],
        'escalated_pending' => ['label' => 'Escalated Pending', 'color' => 'orange', 'icon' => 'fa-hourglass-half'],
        'escalated' => ['label' => 'Escalated to MENRO', 'color' => 'purple', 'icon' => 'fa-shield-alt'],
        'resolved' => ['label' => 'Resolved', 'color' => 'green', 'icon' => 'fa-check-circle'],
        'rejected' => ['label' => 'Rejected', 'color' => 'red', 'icon' => 'fa-times-circle'],
        'closed' => ['label' => 'Closed', 'color' => 'gray', 'icon' => 'fa-archive']
    ];
    $info = $labels[$status] ?? ['label' => ucfirst($status), 'color' => 'gray', 'icon' => 'fa-circle'];
    return '<span class="status-badge status-' . $status . '"><i class="fas ' . $info['icon'] . ' text-xs"></i> ' . $info['label'] . '</span>';
}

/**
 * Get HTML for risk badge
 */
function getRiskBadge($risk) {
    $labels = [
        'low' => ['label' => 'Low', 'color' => 'green', 'icon' => 'fa-seedling'],
        'medium' => ['label' => 'Medium', 'color' => 'yellow', 'icon' => 'fa-exclamation-triangle'],
        'high' => ['label' => 'High', 'color' => 'red', 'icon' => 'fa-fire'],
        'critical' => ['label' => 'Critical', 'color' => 'purple', 'icon' => 'fa-skull-crossbones']
    ];
    $info = $labels[$risk] ?? ['label' => ucfirst($risk), 'color' => 'gray', 'icon' => 'fa-circle'];
    return '<span class="risk-badge risk-' . $risk . '"><i class="fas ' . $info['icon'] . ' text-xs"></i> ' . $info['label'] . '</span>';
}

/**
 * Get HTML for severity badge
 */
function getSeverityBadge($classification, $score) {
    if (empty($classification)) return '';
    $colors = [
        'Isolated Incident' => 'Green',
        'Emerging Pattern' => 'Yellow',
        'Moderate Recurrence' => 'Orange',
        'Critical Chronic Hotspot' => 'Red',
        'Under Review' => 'Gray'
    ];
    $color = $colors[$classification] ?? 'Gray';
    return '<span class="severity-badge severity-' . $color . '"><i class="fas fa-chart-line"></i> ' . $classification . ' <span class="text-[9px] font-mono opacity-75">(' . $score . ')</span></span>';
}

// ============================================
// SEVERITY / RISK BAND HELPERS (single source of truth)
// ============================================
// These mirror the exact band math used in models/Report.php
// (getDecisionFromScore) so that every view — dashboards, drill-downs,
// charts, report cards — labels a severity score with the SAME risk level.

/**
 * Compute the severity-score band boundaries from the admin-configured
 * Critical Threshold. Returns start values for each band.
 * @return array{yellow:int, orange:int, critical:int}
 */
function getSeverityBands() {
    $criticalThreshold = (int)SettingsHelper::get('critical_threshold_score', 15);
    $criticalThreshold = max(4, $criticalThreshold);
    $bandWidth = max(1, (int)floor(($criticalThreshold - 1) / 3));
    return [
        'yellow'   => $bandWidth + 1,
        'orange'   => $bandWidth * 2 + 1,
        'critical' => $criticalThreshold,
    ];
}

/**
 * Map a severity score to a risk level string.
 * @param int|float $score
 * @return string 'low'|'medium'|'high'|'critical'
 */
function getRiskLevelFromScore($score) {
    $score = (int)$score;
    $bands = getSeverityBands();
    if ($score < $bands['yellow'])   return 'low';
    if ($score < $bands['orange'])   return 'medium';
    if ($score < $bands['critical']) return 'high';
    return 'critical';
}

/**
 * Hex color for a risk level (used by map markers / cluster styling).
 * @param string $level low|medium|high|critical
 */
function getRiskColor($level) {
    $colors = [
        'low'      => '#10B981',
        'medium'   => '#F59E0B',
        'high'     => '#F97316',
        'critical' => '#EF4444',
    ];
    return $colors[$level] ?? '#10B981';
}

/**
 * Human-readable risk level label.
 * @param string $level
 */
function getRiskLevelLabel($level) {
    $labels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
    return $labels[$level] ?? 'Low';
}

/**
 * Get HTML for impact badge
 */
function getImpactBadge($impact_modifier) {
    if ($impact_modifier == 4) {
        return '<span class="impact-badge impact-severe"><i class="fas fa-fire"></i> Severe (+4)</span>';
    } elseif ($impact_modifier == 2) {
        return '<span class="impact-badge impact-moderate"><i class="fas fa-exclamation-triangle"></i> Moderate (+2)</span>';
    } else {
        return '<span class="impact-badge impact-localized"><i class="fas fa-circle"></i> Localized (+0)</span>';
    }
}

// ============================================
// DATE & TIME FUNCTIONS
// ============================================

/**
 * Format date for display
 */
function formatDate($date) {
    return date('F j, Y, g:i a', strtotime($date));
}

/**
 * Format date for display (short)
 */
function formatDateShort($date) {
    return date('M d, Y', strtotime($date));
}

/**
 * Get time ago string
 */
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) return "Just Now";
    else if ($minutes <= 60) return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    else if ($hours <= 24) return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    else if ($days <= 7) return ($days == 1) ? "yesterday" : "$days days ago";
    else if ($weeks <= 4.3) return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    else if ($months <= 12) return ($months == 1) ? "1 month ago" : "$months months ago";
    else return ($years == 1) ? "1 year ago" : "$years years ago";
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format phone number for display
 */
function formatPhoneNumber($number) {
    if (empty($number)) return '—';
    
    // Clean the number
    $cleaned = preg_replace('/[^0-9]/', '', $number);
    
    // Format: 0912 345 6789 or +63 912 345 6789
    if (strlen($cleaned) == 11 && substr($cleaned, 0, 2) == '09') {
        return substr($cleaned, 0, 4) . ' ' . substr($cleaned, 4, 3) . ' ' . substr($cleaned, 7, 4);
    } elseif (strlen($cleaned) == 12 && substr($cleaned, 0, 3) == '639') {
        return '+63 ' . substr($cleaned, 3, 3) . ' ' . substr($cleaned, 6, 3) . ' ' . substr($cleaned, 9, 4);
    } elseif (strlen($cleaned) == 10) {
        return substr($cleaned, 0, 3) . ' ' . substr($cleaned, 3, 3) . ' ' . substr($cleaned, 6, 4);
    }
    
    return htmlspecialchars($number);
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 100, $ellipsis = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $ellipsis;
}

/**
 * Escape HTML for safe output
 */
function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ============================================
// URL HELPER FUNCTIONS
// ============================================

/**
 * Get current URL
 */
function currentUrl() {
    return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
}

/**
 * Build query string from array
 */
function buildQueryString($params) {
    $parts = [];
    foreach ($params as $key => $value) {
        if ($value !== '' && $value !== null) {
            $parts[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    return implode('&', $parts);
}

// ============================================
// ROLE HELPER FUNCTIONS
// ============================================

/**
 * Derive the legacy app-level role label from the user_type column.
 * users.user_type is now the single source of truth (NULL = citizen).
 *   admin -> admin
 *   menro_staff -> admin
 *   barangay_personnel -> barangay_official
 */
function roleFromUserType($user_type) {
    $map = [
        'admin' => 'admin',
        'menro_staff' => 'admin',
        'barangay_personnel' => 'barangay_official',
    ];
    if (!empty($user_type) && isset($map[$user_type])) {
        return $map[$user_type];
    }
    return 'citizen';
}

/**
 * Map a legacy role label to the user_type column value.
 * Returns null for citizens (user_type stays NULL/empty).
 */
function userTypeFromRole($role) {
    $map = [
        'citizen' => null,
        'barangay_official' => 'barangay_personnel',
        'admin' => 'admin',
    ];
    return $map[$role] ?? (empty($role) ? null : $role);
}

/**
 * Get role display name
 */
function getRoleDisplayName($role, $user_type = null) {
    $types = [
        'admin' => 'Admin',
        'menro_staff' => 'MENRO Staff',
        'barangay_personnel' => 'Barangay Official'
    ];
    if (!empty($user_type)) {
        return $types[$user_type] ?? 'Citizen';
    }
    $roles = [
        'citizen' => 'Citizen',
        'barangay_official' => 'Barangay Official',
        'admin' => 'Admin'
    ];
    return $roles[$role] ?? ucfirst($role);
}

/**
 * Get role badge color
 */
function getRoleBadgeColor($role, $user_type = null) {
    if (!empty($user_type)) {
        $colors = [
            'admin' => 'bg-purple-100 text-purple-700',
            'menro_staff' => 'bg-purple-100 text-purple-700',
            'barangay_personnel' => 'bg-emerald-100 text-emerald-700'
        ];
        return $colors[$user_type] ?? 'bg-blue-100 text-blue-700';
    }
    $colors = [
        'citizen' => 'bg-blue-100 text-blue-700',
        'barangay_official' => 'bg-emerald-100 text-emerald-700',
        'admin' => 'bg-purple-100 text-purple-700'
    ];
    return $colors[$role] ?? 'bg-gray-100 text-gray-700';
}

/**
 * Get role icon
 */
function getRoleIcon($role) {
    $icons = [
        'citizen' => 'fa-user',
        'barangay_official' => 'fa-map-marker-alt',
        'admin' => 'fa-building'
    ];
    return $icons[$role] ?? 'fa-user';
}

// ============================================
// FILE UPLOAD HELPERS
// ============================================

/**
 * Check if file is an image
 */
function isImageFile($file) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    return in_array($ext, $allowed) && $file['size'] <= 5242880;
}

/**
 * Generate safe filename
 */
function generateSafeFilename($original_name) {
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    return uniqid() . '_' . time() . '.' . $ext;
}

// ============================================
// ARRAY HELPERS
// ============================================

/**
 * Safe array access with default
 */
function array_get($array, $key, $default = null) {
    return isset($array[$key]) ? $array[$key] : $default;
}

/**
 * Check if any filters are applied
 */
function hasActiveFilters($filters) {
    foreach ($filters as $filter) {
        if (!empty($filter)) return true;
    }
    return false;
}
?>