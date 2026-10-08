<?php
// Verify popup handoff preserves the first-login flag without real sessions.
define('BASE_PATH', dirname(__DIR__) . '/');
define('BASE_URL', 'https://example.test/');
function renderLoading($destination) {
    $_SESSION = ['initial_page_loading' => true];
    $_SERVER['HTTP_SEC_FETCH_DEST'] = $destination;
    ob_start();
    include BASE_PATH . 'views/shared/dashboard_loading.php';
    $html = ob_get_clean();
    if (!str_contains($html, 'data-initial-load="1"')) throw new RuntimeException('First-login loading must be available');
    return !empty($_SESSION['initial_page_loading']);
}
if (!renderLoading('iframe')) throw new RuntimeException('Popup must preserve the first-login flag');
if (renderLoading('document')) throw new RuntimeException('Full page must consume the first-login flag once');
echo "PASS first-login loading survives the popup and is consumed by the full page\n";
