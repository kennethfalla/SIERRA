<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function checkReview($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function t($value) { return $value; }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn() => '2026-10-09 08:30:00');
$db->exec('CREATE TABLE reports (id INTEGER PRIMARY KEY, status TEXT, viewed_at TEXT)');
$db->exec('CREATE TABLE activity_logs (action TEXT, description TEXT, status TEXT, created_at TEXT)');
$db->exec('CREATE TABLE system_settings (setting_key TEXT, setting_value TEXT)');
$db->exec("INSERT INTO system_settings VALUES ('system_name', 'Fixture'), ('permissions_v9_migrated', '1')");
class Database { public function getConnection() { return $GLOBALS['db']; } }
require_once __DIR__ . '/../models/Report.php';
$model = new class($db) extends Report {
    private $fixture;
    public function __construct($db) { parent::__construct($db); $this->fixture = $db; }
    public function getReportById($id) {
        $stmt = $this->fixture->prepare('SELECT * FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
};
$db->exec("INSERT INTO reports VALUES (1, 'pending', NULL)");
checkReview($model->markUnderReview(1), 'Opening a pending report starts review');
$row = $model->getReportById(1);
checkReview($row['status'] === 'under_review' && $row['viewed_at'] === '2026-10-09 08:30:00', 'Review start must be saved');
checkReview(!$model->markUnderReview(1), 'Opening the report again must not restart review');
checkReview($model->getUnderReviewDate($row) === $row['viewed_at'], 'Stored review date takes precedence');
$log = $db->prepare('INSERT INTO activity_logs VALUES (?, ?, ?, ?)');
$log->execute(['Status Change', 'Auto‑flipped report #10 to Under Review (barangay viewed the report)', 'SUCCESS', '2026-10-01 08:00:00']);
$log->execute(['Update Status', 'Updated report #1 status to under_review', 'FAILED', '2026-10-02 08:00:00']);
$log->execute(['Status Change', 'Auto‑flipped report #1 to Under Review (barangay viewed the report)', 'SUCCESS', '2026-10-03 08:00:00']);
$log->execute(['Update Status', 'Updated report #1 status to under_review', 'SUCCESS', '2026-10-04 08:00:00']);
$legacy = ['id'=>1, 'status'=>'in_progress', 'created_at'=>'2026-10-01 07:00:00'];
checkReview($model->getUnderReviewDate($legacy) === '2026-10-03 08:00:00', 'Legacy date must use the first successful review for this exact report');
checkReview($model->getUnderReviewDate(['id'=>2, 'status'=>'in_progress']) === null, 'Missing history must not invent a date');
$legacy['viewed_at'] = $model->getUnderReviewDate($legacy);
foreach (['manage', 'citizen'] as $context) {
    $report = $legacy;
    $reportProgressContext = $context;
    $reportProgressMode = 'history';
    ob_start(); include __DIR__ . '/../views/shared/report_progress.php'; $html = ob_get_clean();
    $doc = new DOMDocument(); @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    $reviewTime = $xpath->query('//li[.//h4[text()="Under Review"]]/time');
    checkReview($reviewTime->length === 1 && str_contains($reviewTime->item(0)->getAttribute('datetime'), '2026-10-03T08:00:00'), 'Both timelines must show the real review date');
}
echo "Review timestamp persistence, repeat viewing, legacy history and both timelines passed.\n";
