<?php
// Local design preview only. Sample data; no database access.
function t($text) { return $text; }
$role = ($_GET['role'] ?? '') === 'barangay' ? 'barangay' : 'admin';
$_SESSION = ['user_role'=>$role === 'admin' ? 'admin' : 'barangay_official', 'user_name'=>'Maria Santos'];
$menu_unread=2; $unread_count=2; $current_date='September 29, 2026'; $system_name='SIERRA';
$source=file_get_contents(dirname(__DIR__).'/views/'.$role.'/dashboard.php');
preg_match_all('/<style>(.*?)<\/style>/s', $source, $styles);
$mapStart=strpos($source, '<div id="map-container"');
$mapEnd=strpos($source, '<div class="grid grid-cols-1', $mapStart);
$mapMarkup=preg_replace('/<\?php echo t\(\x27([^\x27]*)\x27\); \?>/', '$1', substr($source,$mapStart,$mapEnd-$mapStart));
$mapMarkup=preg_replace('/<\?php.*?\?>/s', '', $mapMarkup);
$labels=$role==='admin' ? ['Active Hotspots','Avg Municipal Risk','Critical Escalations','Resolved Hotspots'] : ['Pending Acknowledgment','Critical Local Hotspots','Average Resolution Speed','Resolution Rate'];
$values=$role==='admin' ? ['12','8.2','3','18'] : ['12','3','2.4 days','78%'];
?>
<!doctype html><html lang="en"><head><base href="../"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SIERRA mobile dashboard preview</title>
<link rel="stylesheet" href="assets/css/tailwind.css"><link rel="stylesheet" href="assets/vendor/manrope/manrope.css">
<link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css"><link rel="stylesheet" href="assets/css/export-print.css">
<?php foreach($styles[1] as $style) echo '<style>'.$style.'</style>'; ?>
<link rel="stylesheet" href="assets/css/dashboard.css"><link rel="stylesheet" href="assets/css/dashboard-hero.css">
<style>#map{background:repeating-linear-gradient(30deg,transparent 0 48px,#ffffff99 49px 54px,transparent 55px 100px),repeating-linear-gradient(115deg,#e4efdf 0 62px,#c6dcca 63px 67px,#edf2e7 68px 110px);display:flex;align-items:center;justify-content:center} .preview-map-label{padding:10px;background:white;border-radius:10px;font-size:12px;color:#52685e}.preview-note{display:flex;flex-wrap:wrap;gap:10px;font-size:11px;color:#52685e;margin:0 0 14px}.preview-note a{color:#08745b;text-decoration:underline}.preview-bars{display:flex;align-items:flex-end;gap:14px;height:150px;margin-top:20px}.preview-bars span{flex:1;background:#10a37f;border-radius:6px 6px 0 0}</style>
</head><body class="dashboard-page">
<button id="showSidebarBtn" style="position:fixed;z-index:51;background:white;border:1px solid #dce8e1;border-radius:12px" aria-label="Menu preview" onclick="alert('Layout preview only. The menu works in the deployed dashboard.')"><i class="fas fa-bars"></i></button>
<?php include dirname(__DIR__).'/views/shared/dashboard_mobile_header.php'; ?>
<main id="main-content"><div class="main-container">
<?php include dirname(__DIR__).'/views/shared/dashboard_hero.php'; ?>
<div class="preview-note"><span>Layout preview · sample data</span><a href="preview/mobile-dashboard.php?role=admin">Admin</a><a href="preview/mobile-dashboard.php?role=barangay">Barangay</a></div>
<div class="analytics-kpi-grid">
<?php foreach($labels as $i=>$label): ?><div class="analytics-kpi-box bg-white flex items-start justify-between"><div><p class="uppercase"><?php echo $label; ?></p><p class="text-2xl font-extrabold text-emerald-600"><?php echo $values[$i]; ?></p><p class="mt-1">Sample dashboard metric</p></div></div><?php endforeach; ?></div>
<div class="dashboard-toolbar-row"><div class="dashboard-toolbar-filters">
<?php $ft=['search_id'=>'previewSearch','search_placeholder'=>'Search reports...', 'inline_selects'=>[['id'=>'previewStatus','value'=>'all','options'=>['all'=>'All Statuses','pending'=>'Pending','resolved'=>'Resolved']]],'filter_by'=>['active'=>false,'count'=>0],'callback'=>'previewFilter']; include dirname(__DIR__).'/views/shared/report_filter_toolbar.php'; ?>
</div><div class="export-dropdown"><button class="btn-export-trigger radius-12" onclick="document.getElementById('previewExport').classList.toggle('open')"><i class="fas fa-file-export"></i>Export<i class="fas fa-chevron-down"></i></button><div class="export-dropdown-menu" id="previewExport"><div class="export-dropdown-header">Export is available in the full dashboard.</div></div></div></div>
<?php echo $mapMarkup; ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6"><div class="chart-card"><div class="chart-title">Weekly report trends</div><div class="preview-bars"><span style="height:45%"></span><span style="height:75%"></span><span style="height:55%"></span><span style="height:90%"></span><span style="height:60%"></span><span style="height:80%"></span><span style="height:35%"></span></div><p class="text-xs text-gray-500 mt-3">Sample chart · Monday to Sunday</p></div><div class="chart-card"><div class="chart-title">Resolution progress</div><p class="text-4xl font-bold text-emerald-600 mt-4">78%</p><p class="text-sm text-gray-500 mt-3">Illustrative data for the layout preview.</p></div></div>
</div></main>
<script>function previewFilter(){} function toggleNotifications(){alert('Notification preview: 2 unread updates.');} function menroToggleNotifs(){toggleNotifications();} function closeDrillPanel(){} function toggleMapFullscreen(){document.getElementById('map-container').classList.toggle('map-fullscreen');} document.querySelector('#map').insertAdjacentHTML('beforeend','<span class="preview-map-label">Map area · layout preview</span>'); document.querySelectorAll('.map-toggle button').forEach(b=>b.addEventListener('click',()=>{b.parentNode.querySelectorAll('button').forEach(x=>x.classList.remove('active'));b.classList.add('active');}));</script>
</body></html>
