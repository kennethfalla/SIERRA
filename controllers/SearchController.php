<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . 'helpers/PermissionHelper.php';
require_once BASE_PATH . 'models/Search.php';

if (!isLoggedIn()) {
    if (($_GET['format'] ?? '') === 'json') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error'=>'Please sign in again.']);
    } else {
        header('Location: ' . BASE_URL . 'index.php?page=login');
    }
    exit;
}

$search_user = [
    'id'=>(int)$_SESSION['user_id'], 'role'=>$_SESSION['user_role'] ?? '',
    'user_type'=>$_SESSION['user_type'] ?? '',
    'barangay_id'=>$_SESSION['barangay_id'] ?? null,
    'is_resident'=>$_SESSION['is_resident'] ?? (empty($_SESSION['barangay_id']) ? 0 : 1),
];
$search_query = SearchQuery::clean($_GET['q'] ?? $_GET['search'] ?? '');
$search_type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'all';
$search_json = ($_GET['format'] ?? '') === 'json';
$search_scope = is_string($_GET['scope'] ?? null) ? $_GET['scope'] : '';
$search_scope_tab = is_string($_GET['scope_tab'] ?? null) ? $_GET['scope_tab'] : '';
$search_scope_label = Search::PAGE_SCOPES[$search_scope][1] ?? '';
$search_context_params = $search_scope !== '' ? ['scope'=>$search_scope,'scope_tab'=>$search_scope_tab] : [];
$database = new Database();
$db = $database->getConnection();
$search_model = new Search($db, $search_user, [
    'reports'=>PermissionHelper::userHasAnyPermission(['can_view_reports','can_manage_reports']),
    'users'=>PermissionHelper::userHasAnyPermission(['can_manage_users','can_manage_staff']),
    'reporters'=>PermissionHelper::userHasPermission('can_view_reports'),
    'audit'=>PermissionHelper::userHasPermission('can_manage_system'),
], ['page'=>$search_scope, 'tab'=>$search_scope_tab]);
$search_types = $search_model->allowedTypes();
if ($search_scope !== '') $search_type = $search_types[0] ?? 'none';
if ($search_type !== 'all' && !in_array($search_type,$search_types,true)) $search_type = 'all';
header('Cache-Control: private, no-store');
$search_error = '';

// Suggestions should not hold the user's session lock while the DB is searching.
if ($search_json && session_status() === PHP_SESSION_ACTIVE) session_write_close();
try {
    $search_data = $search_model->find($search_query,$search_type,max(1,(int)($_GET['page_num'] ?? 1)),$search_json);
    foreach ($search_data['results'] as &$search_result) {
        if ($search_result['type'] === 'reports') {
            $route = $search_user['role'] === 'citizen' ? 'track-status' : 'manage-report';
            $search_result['url'] = BASE_URL . 'index.php?page='.$route.'&id='.rawurlencode(IdGuard::enc((int)$search_result['id']));
        } elseif ($search_result['type'] === 'announcements') {
            $search_result['url'] = BASE_URL . 'index.php?page=announcements&focus='.(int)$search_result['id'].'#announcement-'.(int)$search_result['id'];
        } elseif ($search_result['type'] === 'notifications') {
            $search_result['url'] = BASE_URL . 'index.php?page=notifications#notification-'.(int)$search_result['id'];
        } elseif ($search_result['type'] === 'users') {
            $tab = empty($search_result['status']) ? 'citizens' : ($search_result['status'] === 'barangay_personnel' ? 'barangay' : 'menro');
            $search_result['url'] = BASE_URL.'index.php?page=manage-users&subtab='.$tab.'&search='.rawurlencode($search_result['title']);
        } elseif ($search_result['type'] === 'reporters') {
            $search_result['url'] = BASE_URL.'index.php?page=reporters-directory&tab='.$search_result['status'].'&search='.rawurlencode($search_result['title']);
        } else {
            $search_result['url'] = BASE_URL.'index.php?page=audit-logs&focus='.(int)$search_result['id'];
        }
    }
    unset($search_result);
} catch (PDOException $e) {
    error_log('[Search] Query failed: '.$e->getMessage());
    $search_data = ['results'=>[], 'total'=>0, 'page'=>1, 'pages'=>0];
    $search_error = 'Search is temporarily unavailable. Please try again.';
    http_response_code(503);
}
if ($search_json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($search_error ? ['error'=>$search_error] : $search_data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
require BASE_PATH . 'views/shared/search.php';
