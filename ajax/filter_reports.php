<?php
// ajax/filter_reports.php - COMPLETE VERSION WITH VERIFICATION/UPVOTE INTEGRATION
// Supports: Filtering, Pagination, Sorting, Verification data, AJAX upvote

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
requireLogin();

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$risk = isset($_GET['risk']) ? $_GET['risk'] : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_range = isset($_GET['date_range']) ? (int)$_GET['date_range'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) && $_GET['sort'] === 'oldest' ? 'ASC' : 'DESC';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_conditions = ["r.user_id = :user_id"];
$params = [':user_id' => $user_id];

if ($status != '') {
    $where_conditions[] = "r.status = :status";
    $params[':status'] = $status;
}
if ($risk != '') {
    $where_conditions[] = "r.risk_level = :risk";
    $params[':risk'] = $risk;
}
if ($category > 0) {
    $where_conditions[] = "r.category_id = :category";
    $params[':category'] = $category;
}
if ($date_range > 0) {
    $where_conditions[] = "r.created_at >= DATE_SUB(NOW(), INTERVAL :date_range DAY)";
    $params[':date_range'] = $date_range;
}
if ($search != '') {
    $where_conditions[] = "(r.title LIKE :search OR r.description LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = implode(" AND ", $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM reports r WHERE $where_clause";
$count_stmt = $db->prepare($count_sql);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_reports = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = max(1, ceil($total_reports / $limit));

if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

// Get reports with verification data
$sql = "SELECT r.*, c.name as category_name, b.name as barangay_name,
               r.verification_count,
               (SELECT COUNT(*) FROM report_verifications WHERE report_id = r.id AND user_id = :user_id_verify) as is_verified_by_user
        FROM reports r
        JOIN categories c ON r.category_id = c.id
        JOIN barangays b ON r.barangay_id = b.id
        WHERE $where_clause
        ORDER BY r.created_at $sort
        LIMIT $limit OFFSET $offset";

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':user_id_verify', $user_id);
$stmt->execute();
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get risk summary for filtered results
$risk_summary = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
$risk_sql = "SELECT risk_level, COUNT(*) as cnt FROM reports r WHERE $where_clause GROUP BY risk_level";
$risk_stmt = $db->prepare($risk_sql);
foreach ($params as $key => $value) {
    $risk_stmt->bindValue($key, $value);
}
$risk_stmt->execute();
while ($row = $risk_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($risk_summary[$row['risk_level']])) {
        $risk_summary[$row['risk_level']] = $row['cnt'];
    }
}

// Generate HTML for reports
ob_start();
if (count($reports) > 0):
    foreach ($reports as $row):
        $risk_level = isset($row['risk_level']) ? $row['risk_level'] : 'low';
        $risk_labels = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];
        $risk_icons = ['low' => 'fa-seedling', 'medium' => 'fa-exclamation-triangle', 'high' => 'fa-fire', 'critical' => 'fa-skull-crossbones'];
        $risk_colors = ['low' => 'risk-low', 'medium' => 'risk-medium', 'high' => 'risk-high', 'critical' => 'risk-critical'];
        $risk_icon = $risk_icons[$risk_level] ?? 'fa-seedling';
        $risk_label = $risk_labels[$risk_level] ?? 'Low';
        $risk_class = $risk_colors[$risk_level] ?? 'risk-low';
        
        $status_label = ucfirst(str_replace('_', ' ', $row['status']));
        $status_class = 'status-' . $row['status'];
        $status_icon = '';
        if ($row['status'] == 'pending') $status_icon = 'fa-clock';
        elseif ($row['status'] == 'under_review') $status_icon = 'fa-search';
        elseif ($row['status'] == 'in_progress') $status_icon = 'fa-spinner fa-pulse';
        elseif ($row['status'] == 'escalated_pending') $status_icon = 'fa-hourglass-half';
        elseif ($row['status'] == 'escalated') $status_icon = 'fa-shield-alt';
        elseif ($row['status'] == 'resolved') $status_icon = 'fa-check-circle';
        elseif ($row['status'] == 'rejected') $status_icon = 'fa-times-circle';
        elseif ($row['status'] == 'cancelled') $status_icon = 'fa-ban';
        else $status_icon = 'fa-clock';
        ?>
<div class="report-card-grid" data-report-id="<?php echo $row['id']; ?>" onclick="window.location.href='<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $row['id']; ?>'" style="cursor:pointer;">
    <div class="report-card-header rounded-t-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start gap-3 mb-3">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <div class="w-5 h-5 md:w-6 md:h-6 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-alt text-white/80 text-[10px] md:text-xs"></i>
                    </div>
                    <span class="header-label">Report Summary</span>
                </div>
                <h3 class="header-title"><?php echo htmlspecialchars($row['title']); ?></h3>
            </div>
            <div class="text-right">
                <div class="header-meta">#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></div>
                <div class="header-meta mt-2"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
            </div>
        </div>
        <div class="header-badges">
            <span class="status-badge header-badge <?php echo $status_class; ?>">
                <i class="fas <?php echo $status_icon; ?> text-[10px] sm:text-xs"></i>
                <?php echo $status_label; ?>
            </span>
            <?php if ($row['status'] != 'cancelled' && $row['status'] != 'rejected'): ?>
            <span class="risk-badge header-badge <?php echo $risk_class; ?>">
                <i class="fas <?php echo $risk_icon; ?> text-[10px] sm:text-xs"></i>
                <?php echo $risk_label; ?>
            </span>
            <?php endif; ?>
            <?php if(isset($row['decision_classification']) && $row['decision_classification'] && $row['status'] != 'cancelled' && $row['status'] != 'rejected'): ?>
            <span class="severity-badge header-badge severity-<?php echo strtolower($row['decision_pin'] ?? 'Green'); ?>">
                <i class="fas fa-chart-line text-[10px] sm:text-xs"></i>
                <?php echo $row['decision_classification']; ?>
                <span class="text-[8px] sm:text-[9px] font-mono opacity-75">(<?php echo $row['severity_score'] ?? 0; ?>)</span>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="p-4 sm:p-5">
        <p class="text-gray-500 mb-3 sm:mb-4 line-clamp-3"><?php echo htmlspecialchars(substr($row['description'], 0, 80)); ?><?php echo strlen($row['description']) > 80 ? '...' : ''; ?></p>
        
        <div class="flex flex-wrap gap-2 sm:gap-3 pt-2 sm:pt-3 border-t border-gray-100">
            <div class="meta-item">
                <div class="meta-icon"><i class="fas fa-tag text-gray-400 text-[10px] sm:text-xs"></i></div>
                <span><?php echo htmlspecialchars($row['category_name']); ?></span>
            </div>
            <div class="meta-item">
                <div class="meta-icon"><i class="fas fa-map-marker-alt text-gray-400 text-[10px] sm:text-xs"></i></div>
                <span><?php echo htmlspecialchars($row['barangay_name']); ?></span>
            </div>
        </div>
        
        <!-- ===== VERIFICATION SECTION ===== -->
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-2 border-t border-gray-100">
            <!-- Verification Count -->
            <span class="verification-count">
                <i class="fas fa-thumbs-up"></i>
                <span class="font-medium" id="verifyCount-<?php echo $row['id']; ?>"><?php echo (int)$row['verification_count']; ?></span>
                <span class="text-gray-400">verification<?php echo $row['verification_count'] != 1 ? 's' : ''; ?></span>
            </span>
            
            <!-- User verification status -->
            <?php if ($row['is_verified_by_user'] > 0): ?>
                <span class="verification-badge">
                    <i class="fas fa-check-circle"></i> You verified this
                </span>
            <?php else: ?>
                <!-- Verify button (only if report is active and not resolved/rejected/cancelled) -->
                <?php if (!in_array($row['status'], ['resolved', 'rejected', 'cancelled'])): ?>
                    <button class="verify-btn" onclick="event.stopPropagation(); verifyReport(<?php echo $row['id']; ?>, this)">
                        <i class="fas fa-thumbs-up"></i> Verify
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="flex flex-wrap justify-between items-center gap-3 pt-3 border-t border-gray-100 mt-3">
            <div class="text-xs text-gray-500">
                <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
            </div>
            <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $row['id']; ?>"
               class="inline-flex items-center gap-2 text-sm font-semibold text-[#10A37F] hover:text-[#0D8568] transition">
                <i class="fas fa-eye"></i> View Details
            </a>
        </div>
    </div>
</div>
<?php
    endforeach;
else:
?>
<div class="empty-state">
    <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3 sm:mb-4">
        <i class="fas fa-inbox text-xl sm:text-2xl text-gray-400"></i>
    </div>
    <h3 class="font-semibold text-gray-700 mb-1 sm:mb-2 text-base sm:text-lg">No reports found</h3>
    <p class="text-gray-400 text-xs sm:text-sm mb-3 sm:mb-4">Try adjusting your filters</p>
    <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-primary inline-flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm">
        <i class="fas fa-plus-circle"></i> New Report
    </a>
</div>
<?php
endif;
$reports_html = ob_get_clean();

// Generate pagination
ob_start();
if ($total_pages > 1):
?>
<div class="pagination">
    <?php if($page > 1): ?>
    <button onclick="goToPage(<?php echo $page-1; ?>)" class="page-btn"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></button>
    <?php else: ?>
    <span class="page-btn disabled"><i class="fas fa-chevron-left text-[10px] sm:text-xs"></i></span>
    <?php endif; ?>
    
    <?php for($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
    <button onclick="goToPage(<?php echo $i; ?>)" class="page-btn <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></button>
    <?php endfor; ?>
    
    <?php if($page < $total_pages): ?>
    <button onclick="goToPage(<?php echo $page+1; ?>)" class="page-btn"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></button>
    <?php else: ?>
    <span class="page-btn disabled"><i class="fas fa-chevron-right text-[10px] sm:text-xs"></i></span>
    <?php endif; ?>
</div>
<?php
endif;
$pagination_html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $reports_html,
    'pagination' => $pagination_html,
    'total_count' => $total_reports,
    'risk_summary' => $risk_summary
]);
exit();
?>