<?php
// Test the real availability helper with synthetic query results and no live database.
$source = file_get_contents(__DIR__ . '/../helpers/SettingsHelper.php');
eval(substr($source, 5, strpos($source, '// INITIALIZE DEFAULTS ON FIRST RUN') - 5));
$property = new ReflectionProperty(SettingsHelper::class, 'settings');
class AvailabilityFixture {
    public $row = [], $calls = 0;
    function prepare($sql) { $this->calls++; return new AvailabilityStatement($this); }
}
class AvailabilityStatement {
    private $fixture;
    function __construct($fixture) { $this->fixture = $fixture; }
    function execute($params) { if ($params !== [17]) throw new RuntimeException('Limit query must be scoped to the current user'); }
    function fetch($mode) { return $this->fixture->row; }
}
$db = new AvailabilityFixture();
$settings = ['enable_report_limits' => '1', 'report_daily_limit' => '5', 'report_min_interval_minutes' => '10'];
function expect($condition, $message) { if (!$condition) throw new RuntimeException($message); }
foreach ([[null, 0], [59, 541], [599, 1], [600, 0], [601, 0]] as [$elapsed, $expected]) {
    $property->setValue(null, $settings);
    $db->row = ['today_count' => 1, 'elapsed_seconds' => $elapsed, 'midnight_seconds' => 200];
    $result = SettingsHelper::getReportSubmissionAvailability($db, 17);
    expect($result['retry_after'] === $expected, 'Interval countdown must use exact seconds');
    expect($result['allowed'] === ($expected === 0), 'Submission must unlock at the exact limit');
}
$db->row = ['today_count' => 5, 'elapsed_seconds' => 600, 'midnight_seconds' => 200];
expect(SettingsHelper::getReportSubmissionAvailability($db, 17)['retry_after'] === 200, 'Daily cap must wait until midnight');
$property->setValue(null, array_merge($settings, ['enable_report_limits' => '0']));
$calls = $db->calls;
expect(SettingsHelper::getReportSubmissionAvailability($db, 17)['allowed'], 'Disabled limits must allow submission');
expect($db->calls === $calls, 'Disabled limits must not query reports');
$property->setValue(null, array_merge($settings, ['enable_report_submission' => '0']));
expect(!SettingsHelper::getReportSubmissionAvailability($db, 17)['allowed'], 'Submission kill switch remains enforced');
echo "Report submission limit tests passed.\n";
