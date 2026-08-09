<?php
// views/citizen/dashboard.php - ENHANCED MOBILE DESIGN
// FIXED: Announcement title is now displayed prominently, with rich HTML content below.
// FIXED: Recent reports show correctly on mobile

require_once dirname(dirname(__DIR__)) . '/config/config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$report = new Report($db);

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

$reports_stmt = $report->getReportsByUser($user_id);
$reports = $reports_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_reports = $report->getTotalCount(null, $user_id);
$pending_count = $report->getReportsByStatus('pending', null, $user_id);

$resolved_count = $report->getReportsByStatus('resolved', null, $user_id);
$closed_count = $report->getReportsByStatus('closed', null, $user_id);
$total_resolved_count = $resolved_count + $closed_count;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM reports WHERE user_id = :user_id AND status IN ('escalated_pending', 'escalated')");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$escalated_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

date_default_timezone_set('Asia/Manila');
$current_hour = date('H');
$current_time = date('g:i A');
$current_date = date('F j, Y');

if ($current_hour < 12) {
    $greeting = "Good Morning";
    $greeting_icon = "fa-sun";
    $greeting_color = "text-yellow-200";
} elseif ($current_hour < 18) {
    $greeting = "Good Afternoon";
    $greeting_icon = "fa-cloud-sun";
    $greeting_color = "text-orange-200";
} else {
    $greeting = "Good Evening";
    $greeting_icon = "fa-moon";
    $greeting_color = "text-indigo-200";
}

// ========== NOTIFICATIONS ==========
$notifications = array();

$notifications[] = array(
    'id' => 'welcome',
    'type' => 'welcome',
    'title' => 'Welcome to EnviroTrack!',
    'message' => "Welcome back, $user_name! Together, we're making San Isidro cleaner and greener.",
    'time' => date('Y-m-d H:i:s'),
    'icon' => 'fa-leaf',
    'color' => '#10A37F',
    'link' => '',
    'read' => false
);

$stmt = $db->prepare("SELECT id, title, status, updated_at FROM reports WHERE user_id = :user_id AND status != 'pending' ORDER BY updated_at DESC LIMIT 10");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$report_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($report_updates as $update) {
    $shortTitle = strlen($update['title']) > 40 ? substr($update['title'], 0, 40) . '...' : $update['title'];
    
    $status_map = [
        'verified' => ['title' => 'Report Verified', 'icon' => 'fa-check-circle', 'color' => '#3B82F6', 'msg' => 'has been verified by barangay officials.'],
        'in_progress' => ['title' => 'Action Being Taken', 'icon' => 'fa-spinner', 'color' => '#F59E0B', 'msg' => 'your barangay is now working on'],
        'escalated_pending' => ['title' => 'Escalation Pending', 'icon' => 'fa-hourglass-half', 'color' => '#F97316', 'msg' => 'has been escalated to MENRO and is awaiting approval.'],
        'escalated' => ['title' => 'Escalated to MENRO', 'icon' => 'fa-shield-alt', 'color' => '#EF4444', 'msg' => 'has been accepted by MENRO and is under their supervision.'],
        'resolved' => ['title' => 'Issue Resolved!', 'icon' => 'fa-check-double', 'color' => '#10A37F', 'msg' => 'has been resolved. Great news!'],
        'closed' => ['title' => 'Report Closed', 'icon' => 'fa-archive', 'color' => '#6B7280', 'msg' => 'has been closed. Thank you for your cooperation!'],
        'rejected' => ['title' => 'Report Rejected', 'icon' => 'fa-times-circle', 'color' => '#EF4444', 'msg' => 'was rejected by barangay officials.']
    ];
    
    if (isset($status_map[$update['status']])) {
        $info = $status_map[$update['status']];
        $notifications[] = array(
            'id' => 'report_' . $update['status'] . '_' . $update['id'],
            'type' => 'report',
            'title' => $info['title'],
            'message' => 'Your report "' . htmlspecialchars($shortTitle) . '" ' . $info['msg'],
            'time' => $update['updated_at'],
            'icon' => $info['icon'],
            'color' => $info['color'],
            'link' => BASE_URL . "index.php?page=track-status&id=" . $update['id'],
            'read' => false
        );
    }
}

if ($barangay_id) {
    $stmt = $db->prepare("SELECT * FROM announcements WHERE is_archived = 0 AND (broadcast_type = 'global_public' OR (broadcast_type = 'localized_public' AND barangay_id = :barangay_id)) ORDER BY created_at DESC LIMIT 3");
    $stmt->bindParam(':barangay_id', $barangay_id);
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($announcements as $ann) {
        $notifications[] = array(
            'id' => 'announce_' . $ann['id'],
            'type' => 'announcement',
            'title' => 'Announcement',
            'message' => $ann['caption'] ?? $ann['title'],
            'time' => $ann['created_at'],
            'icon' => 'fa-bullhorn',
            'color' => '#8B5CF6',
            'link' => BASE_URL . "index.php?page=announcements",
            'read' => false
        );
    }
}

usort($notifications, function($a, $b) {
    return strtotime($b['time']) - strtotime($a['time']);
});

$notifications = array_slice($notifications, 0, 10);
$unread_count = count($notifications);

// ========== LATEST ANNOUNCEMENT ==========
$announcement_sql = "
    SELECT a.*, 
           CONCAT(u.first_name, ' ', u.last_name) as author_name,
           b.name as barangay_name,
           COALESCE(a.is_public, 1) as is_public,
           a.created_by_role,
           (SELECT COUNT(*) FROM announcement_images WHERE announcement_id = a.id) as image_count
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    LEFT JOIN barangays b ON a.barangay_id = b.id
    WHERE a.is_active = 1 AND a.is_archived = 0 
    AND (
        a.broadcast_type = 'global_public' 
        OR (a.broadcast_type = 'localized_public' AND a.barangay_id = :barangay_id)
    )
    ORDER BY a.created_at DESC
    LIMIT 1
";
$ann_stmt = $db->prepare($announcement_sql);
$ann_stmt->bindParam(':barangay_id', $barangay_id);
$ann_stmt->execute();
$latest_announcement = $ann_stmt->fetch(PDO::FETCH_ASSOC);

$display_reports = array_slice($reports, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Citizen Dashboard - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        body { 
            background: #F5FBF6;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .ml-72 {
                margin-left: 0 !important;
                width: 100%;
                padding: 0;
            }
            .sidebar-mobile {
                position: fixed;
                left: -280px;
                transition: left 0.3s ease;
                z-index: 1000;
            }
            .sidebar-mobile.open {
                left: 0;
            }
        }
        
        .main-container {
            padding: 1rem;
            max-width: 1280px;
            margin: 0 auto;
        }
        @media (min-width: 640px) {
            .main-container {
                padding: 1.5rem;
            }
        }
        @media (min-width: 768px) {
            .main-container {
                padding: 2rem;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            padding: 1.25rem 1rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @media (min-width: 640px) {
            .stat-card {
                padding: 1.5rem;
            }
        }
        .stat-card:hover {
            transform: translateY(-3px);
            border-color: #10A37F;
            box-shadow: 0 12px 24px -8px rgba(16, 163, 127, 0.12);
        }
        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1a2e1a;
            letter-spacing: -0.02em;
        }
        @media (min-width: 640px) {
            .stat-card .stat-value {
                font-size: 2rem;
            }
        }
        .stat-card .stat-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #8aa38a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 0.15rem;
        }
        @media (min-width: 640px) {
            .stat-card .stat-label {
                font-size: 0.75rem;
            }
        }
        .stat-card .stat-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        @media (min-width: 640px) {
            .stat-card .stat-icon {
                width: 3rem;
                height: 3rem;
            }
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
            white-space: nowrap;
            letter-spacing: 0.01em;
            line-height: 1.4;
        }
        @media (min-width: 480px) {
            .status-badge {
                padding: 4px 12px;
                font-size: 0.65rem;
                gap: 5px;
            }
        }
        @media (min-width: 640px) {
            .status-badge {
                padding: 4px 14px;
                font-size: 0.7rem;
                gap: 6px;
            }
        }
        .status-badge i {
            font-size: 0.5rem;
        }
        @media (min-width: 640px) {
            .status-badge i {
                font-size: 0.6rem;
            }
        }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-verified { background: #DBEAFE; color: #1E40AF; }
        .status-in_progress { background: #FCE7F3; color: #9D174D; }
        .status-escalated_pending { background: #FDE68A; color: #92400E; border: 1px solid #F59E0B; }
        .status-escalated { background: #FED7AA; color: #9A3412; }
        .status-resolved { background: #D1FAE5; color: #065F46; }
        .status-closed { background: #F3F4F6; color: #4B5563; }
        .status-rejected { background: #FEE2E2; color: #991B1B; }
        
        .greeting-badge {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            border-radius: 1.25rem;
            padding: 1.5rem 1.25rem;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        @media (min-width: 640px) {
            .greeting-badge {
                padding: 2rem 2rem;
                border-radius: 1.5rem;
            }
        }
        .greeting-badge:hover {
            transform: translateY(-3px);
            box-shadow: 0 25px 40px -15px rgba(0,0,0,0.25);
        }
        .greeting-badge .greeting-name {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(120deg, #ffffff, #e0f2fe, #ffffff);
            background-size: 200% auto;
            background-clip: text;
            -webkit-background-clip: text;
            color: transparent;
            animation: textShine 4s linear infinite;
        }
        @media (min-width: 640px) {
            .greeting-badge .greeting-name {
                font-size: 1.75rem;
            }
        }
        @keyframes textShine {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }
        
        .time-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(8px);
            border-radius: 0.75rem;
            padding: 0.4rem 0.75rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        @media (min-width: 640px) {
            .time-card {
                padding: 0.5rem 1rem;
            }
        }
        .time-card:hover {
            transform: scale(1.05);
        }
        .time-card .time-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        @media (min-width: 640px) {
            .time-card .time-display {
                font-size: 1.5rem;
            }
        }
        .time-card .time-period {
            font-size: 0.6rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.7);
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        @media (min-width: 640px) {
            .time-card .time-period {
                font-size: 0.65rem;
            }
        }
        
        /* ===== ANNOUNCEMENT CARD (UPDATED) ===== */
        .announce-card {
            background: white;
            border: 1px solid rgba(16, 163, 127, 0.08);
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px 16px;
            transition: box-shadow 0.15s;
        }
        @media (min-width: 640px) {
            .announce-card {
                padding: 1.25rem 1.5rem;
                gap: 16px 24px;
            }
        }
        .announce-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .announce-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 2 1 200px;
        }
        @media (min-width: 640px) {
            .announce-left {
                gap: 14px;
                flex: 2 1 240px;
            }
        }
        .announce-icon {
            background: #10A37F10;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10A37F;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        @media (min-width: 640px) {
            .announce-icon {
                width: 48px;
                height: 48px;
                font-size: 1.5rem;
            }
        }
        .announce-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        .announce-label {
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #8aa38a;
        }
        @media (min-width: 640px) {
            .announce-label {
                font-size: 0.7rem;
            }
        }
        .announce-msg {
            font-weight: 500;
            color: #1F2937;
            font-size: 0.8rem;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            max-height: 2.8em;
        }
        .announce-msg p {
            margin: 0;
            display: inline;
        }
        .announce-msg strong, .announce-msg b { font-weight: 700; }
        .announce-msg em, .announce-msg i { font-style: italic; }
        .announce-msg ul, .announce-msg ol { margin: 0; padding-left: 1.2em; display: inline; }
        .announce-msg li { display: inline; }
        .announce-msg h1, .announce-msg h2, .announce-msg h3, .announce-msg h4, .announce-msg h5, .announce-msg h6 {
            font-size: inherit;
            font-weight: 700;
            display: inline;
            margin: 0;
        }
        @media (min-width: 640px) {
            .announce-msg {
                font-size: 0.95rem;
                white-space: normal;
            }
        }
        .announce-msg strong {
            color: #10A37F;
        }
        .announce-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }
        @media (min-width: 640px) {
            .announce-right {
                gap: 12px;
            }
        }
        .badge-delay {
            background: #FEF3C7;
            color: #92400E;
            padding: 2px 10px;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        @media (min-width: 640px) {
            .badge-delay {
                padding: 4px 14px;
                font-size: 0.7rem;
            }
        }
        .btn-announce {
            background: #10A37F;
            color: white;
            border: none;
            border-radius: 2rem;
            padding: 6px 14px;
            font-weight: 500;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }
        @media (min-width: 640px) {
            .btn-announce {
                padding: 8px 18px;
                font-size: 0.85rem;
            }
        }
        .btn-announce:hover { 
            background: #0D8568;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }

        /* ===== REPORT ISSUE CARD ===== */
        .report-issue-card {
            background: linear-gradient(145deg, #e8f5ee 0%, #d1e8df 100%);
            border: 2px solid #10A37F;
            border-radius: 1.25rem;
            padding: 1.5rem 1.25rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        @media (min-width: 640px) {
            .report-issue-card {
                padding: 2rem 2.25rem;
            }
        }
        .report-issue-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(16, 163, 127, 0.3);
            border-color: #0D8568;
        }
        .report-issue-card .issue-icon-large {
            background: #10A37F;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.25);
        }
        @media (min-width: 640px) {
            .report-issue-card .issue-icon-large {
                width: 64px;
                height: 64px;
                font-size: 1.8rem;
            }
        }
        .report-issue-card .issue-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1a3d2e;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        @media (min-width: 640px) {
            .report-issue-card .issue-title {
                font-size: 1.4rem;
            }
        }
        .report-issue-card .issue-title i {
            color: #10A37F;
            margin-right: 4px;
        }
        .report-issue-card .issue-description {
            color: #2d4a3e;
            font-size: 0.85rem;
            line-height: 1.6;
            margin-top: 8px;
            margin-bottom: 16px;
        }
        @media (min-width: 640px) {
            .report-issue-card .issue-description {
                font-size: 1rem;
                padding-left: 82px;
                margin-bottom: 20px;
            }
        }
        .report-issue-card .issue-action {
            display: flex;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .report-issue-card .issue-action {
                justify-content: flex-start;
                padding-left: 82px;
            }
        }
        .btn-report {
            background: #10A37F;
            color: white;
            border: none;
            border-radius: 1rem;
            padding: 12px 24px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
            width: 100%;
            justify-content: center;
        }
        @media (min-width: 640px) {
            .btn-report {
                padding: 14px 36px;
                font-size: 1rem;
                width: auto;
                justify-content: flex-start;
            }
        }
        .btn-report i { 
            font-size: 1rem;
            transition: transform 0.3s ease;
        }
        .btn-report:hover { 
            background: #0D8568;
            box-shadow: 0 8px 20px rgba(16, 163, 127, 0.4);
        }
        .btn-report:hover i {
            transform: translateX(4px);
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        @media (min-width: 768px) {
            .two-col {
                grid-template-columns: 1.6fr 1fr;
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        @media (min-width: 640px) {
            .stats-grid {
                gap: 1rem;
            }
        }

        .notification-bell { 
            cursor: pointer; 
            transition: all 0.2s; 
            position: relative; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            z-index: 100;
        }
        .notification-bell:hover { transform: scale(1.05); }
        
        .notification-badge {
            position: absolute; 
            top: -4px; 
            right: -4px; 
            background: #EF4444; 
            color: white;
            font-size: 9px; 
            font-weight: 700; 
            padding: 2px 5px; 
            border-radius: 20px;
            min-width: 16px; 
            text-align: center;
        }
        @media (min-width: 640px) {
            .notification-badge {
                font-size: 10px;
                padding: 2px 6px;
                min-width: 18px;
                top: -5px;
                right: -5px;
            }
        }
        
        .notification-dropdown {
            position: fixed !important;
            top: 80px;
            right: 16px;
            left: 16px;
            max-height: 480px;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 35px -10px rgba(0,0,0,0.25);
            z-index: 999999 !important;
            display: none;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #E5E7EB;
            transform-origin: top right;
            pointer-events: auto;
            width: auto;
        }
        @media (min-width: 640px) {
            .notification-dropdown {
                right: 32px;
                left: auto;
                width: 420px;
                max-height: 500px;
            }
        }
        .notification-dropdown.show { 
            display: flex; 
            animation: slideDown 0.2s ease; 
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .notification-header { 
            padding: 14px 18px; 
            border-bottom: 1px solid #F3F4F6; 
            background: #FAFAFA; 
            flex-shrink: 0; 
        }
        @media (min-width: 640px) {
            .notification-header {
                padding: 16px 20px;
            }
        }
        .notification-list { 
            overflow-y: auto; 
            max-height: 360px; 
            flex: 1; 
            -webkit-overflow-scrolling: touch; 
        }
        .notification-item { 
            display: flex; 
            gap: 12px; 
            padding: 12px 16px; 
            border-bottom: 1px solid #F3F4F6; 
            transition: background 0.2s; 
            cursor: pointer; 
        }
        @media (min-width: 640px) {
            .notification-item {
                gap: 14px;
                padding: 14px 20px;
            }
        }
        .notification-item:hover { 
            background: #F0FDF4; 
        }
        .notification-icon { 
            width: 36px; 
            height: 36px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0; 
        }
        @media (min-width: 640px) {
            .notification-icon {
                width: 44px;
                height: 44px;
                border-radius: 14px;
            }
        }
        .notification-content { 
            flex: 1; 
            min-width: 0; 
        }
        .notification-title { 
            font-weight: 600; 
            font-size: 0.8rem; 
            color: #1F2937; 
            margin-bottom: 2px; 
        }
        @media (min-width: 640px) {
            .notification-title {
                font-size: 0.9rem;
            }
        }
        .notification-message { 
            font-size: 0.7rem; 
            color: #6B7280; 
            line-height: 1.4; 
            margin-bottom: 4px; 
            word-wrap: break-word; 
        }
        @media (min-width: 640px) {
            .notification-message {
                font-size: 0.75rem;
            }
        }
        .notification-time { 
            font-size: 0.6rem; 
            color: #9CA3AF; 
            display: flex; 
            align-items: center; 
            gap: 4px; 
        }
        .notification-dot { 
            width: 6px; 
            height: 6px; 
            background: #10A37F; 
            border-radius: 50%; 
            flex-shrink: 0; 
            margin-top: 2px; 
        }
        .mark-all-read { 
            text-align: center; 
            padding: 10px 16px; 
            border-top: 1px solid #F3F4F6; 
            background: #FAFAFA; 
            font-size: 0.7rem; 
            font-weight: 500; 
            cursor: pointer; 
            transition: all 0.2s; 
            color: #10A37F; 
            flex-shrink: 0; 
        }
        @media (min-width: 640px) {
            .mark-all-read {
                padding: 12px 20px;
                font-size: 0.75rem;
            }
        }
        .mark-all-read:hover { 
            background: #F0FDF4; 
            color: #0D8568; 
        }
        
        .table-container {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        
        .table-container .table-header {
            padding: 0.75rem 1rem;
            background: #F5FBF6;
            border-bottom: 1px solid rgba(16, 163, 127, 0.08);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }
        @media (min-width: 640px) {
            .table-container .table-header {
                padding: 1rem 1.5rem;
            }
        }
        .table-container .table-header h3 {
            font-weight: 600;
            color: #1a2e1a;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-container .table-header h3 i {
            color: #10A37F;
        }
        .table-container .table-header .view-all {
            font-size: 0.75rem;
            color: #10A37F;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }
        .table-container .table-header .view-all:hover {
            color: #0D8568;
        }
        
        .desktop-table {
            display: none;
            width: 100%;
            border-collapse: collapse;
        }
        @media (min-width: 640px) {
            .desktop-table {
                display: table;
            }
        }
        
        .desktop-table thead th {
            padding: 0.6rem 0.75rem;
            text-align: left;
            font-size: 0.6rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #8aa38a;
            background: #f8fbf9;
            border-bottom: 1px solid #eef3f0;
        }
        @media (min-width: 640px) {
            .desktop-table thead th {
                padding: 0.75rem 1rem;
                font-size: 0.65rem;
            }
        }
        
        .desktop-table tbody tr {
            border-bottom: 1px solid #f0f4f2;
            transition: background 0.15s ease;
        }
        .desktop-table tbody tr:last-child {
            border-bottom: none;
        }
        .desktop-table tbody tr:hover {
            background: #f9fcfb;
        }
        
        .desktop-table tbody td {
            padding: 0.6rem 0.75rem;
            vertical-align: middle;
            font-size: 0.8rem;
            color: #1f2937;
        }
        @media (min-width: 640px) {
            .desktop-table tbody td {
                padding: 0.75rem 1rem;
                font-size: 0.875rem;
            }
        }
        
        .desktop-table .report-title {
            font-weight: 600;
            color: #1a2e1a;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        @media (min-width: 640px) {
            .desktop-table .report-title {
                max-width: 200px;
            }
        }
        .desktop-table .report-id {
            display: block;
            font-size: 0.55rem;
            color: #9ca3af;
            font-weight: 400;
            margin-top: 2px;
            font-family: monospace;
        }
        @media (min-width: 640px) {
            .desktop-table .report-id {
                font-size: 0.6rem;
            }
        }
        
        .desktop-table .category-cell {
            color: #4b5a4a;
            font-weight: 500;
        }
        .desktop-table .barangay-cell {
            color: #4b5a4a;
        }
        .desktop-table .date-cell {
            color: #8aa38a;
            font-size: 0.7rem;
            white-space: nowrap;
        }
        @media (min-width: 640px) {
            .desktop-table .date-cell {
                font-size: 0.8rem;
            }
        }
        .desktop-table .action-cell {
            text-align: right;
        }
        .desktop-table .action-cell a {
            color: #10A37F;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 0.75rem;
            transition: color 0.2s;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(16, 163, 127, 0.06);
        }
        .desktop-table .action-cell a:hover {
            color: #0D8568;
            background: rgba(16, 163, 127, 0.12);
        }
        .desktop-table .action-cell a i {
            font-size: 0.65rem;
        }
        
        .mobile-cards {
            display: block;
            padding: 0.5rem 0.75rem;
        }
        @media (min-width: 640px) {
            .mobile-cards {
                display: none;
            }
        }
        
        .report-card-item {
            background: white;
            border: 1px solid #f0f4f2;
            border-radius: 0.75rem;
            padding: 0.75rem 0.875rem;
            margin-bottom: 0.5rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .report-card-item:hover {
            border-color: #10A37F;
            box-shadow: 0 2px 8px rgba(16, 163, 127, 0.06);
        }
        .report-card-item:last-child {
            margin-bottom: 0;
        }
        
        .report-card-item .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .report-card-item .card-title {
            font-weight: 600;
            font-size: 0.8rem;
            color: #1a2e1a;
            line-height: 1.3;
            flex: 1;
        }
        .report-card-item .card-id {
            font-size: 0.55rem;
            color: #9ca3af;
            font-family: monospace;
            flex-shrink: 0;
            padding-top: 0.1rem;
        }
        
        .report-card-item .card-details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.3rem 0.6rem;
            margin-bottom: 0.4rem;
        }
        .report-card-item .card-detail {
            font-size: 0.65rem;
            color: #4b5a4a;
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }
        .report-card-item .card-detail i {
            color: #8aa38a;
            font-size: 0.55rem;
            width: 0.9rem;
            text-align: center;
        }
        .report-card-item .card-detail .label {
            color: #8aa38a;
            font-weight: 500;
        }
        
        .report-card-item .card-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 0.4rem;
            border-top: 1px solid #f0f4f2;
            gap: 0.5rem;
        }
        .report-card-item .card-status {
            flex-shrink: 0;
        }
        .report-card-item .card-action a {
            color: #10A37F;
            font-weight: 500;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
            background: rgba(16, 163, 127, 0.06);
            transition: background 0.2s;
        }
        .report-card-item .card-action a:hover {
            background: rgba(16, 163, 127, 0.12);
        }
        .report-card-item .card-action a i {
            font-size: 0.6rem;
        }
        
        .empty-reports {
            padding: 2.5rem 1rem;
            text-align: center;
            color: #9ca3af;
        }
        .empty-reports i {
            font-size: 2.5rem;
            color: #d1d5db;
            margin-bottom: 0.75rem;
            display: block;
        }
        .empty-reports p {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .empty-reports a {
            color: #10A37F;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .empty-reports a:hover {
            text-decoration: underline;
        }
        
        .community-footer {
            margin-top: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.75rem;
            color: #6B7280;
            border-top: 1px solid #E5E7EB;
            padding-top: 1rem;
        }
        @media (min-width: 640px) {
            .community-footer {
                margin-top: 1.75rem;
                gap: 16px;
                font-size: 0.85rem;
                padding-top: 1.25rem;
            }
        }
        .community-footer i { color: #10A37F; width: 18px; }
        @media (min-width: 640px) {
            .community-footer i { width: 20px; }
        }
        
        .ring-animation { 
            animation: ring 0.6s ease-in-out; 
        }
        @keyframes ring { 
            0%, 100% { transform: rotate(0); } 
            20%, 60% { transform: rotate(12deg); } 
            40%, 80% { transform: rotate(-8deg); } 
        }
        
        @media (max-width: 480px) {
            .stat-card .stat-value {
                font-size: 1.5rem;
            }
            .greeting-badge .greeting-name {
                font-size: 1.1rem;
            }
            .report-issue-card .issue-title {
                font-size: 0.95rem;
            }
            .report-issue-card .issue-description {
                font-size: 0.75rem;
            }
            .btn-report {
                font-size: 0.8rem;
                padding: 10px 16px;
            }
            .announce-msg {
                font-size: 0.7rem;
            }
            .time-card .time-display {
                font-size: 0.9rem;
            }
            .report-card-item .card-title {
                font-size: 0.75rem;
            }
            .report-card-item .card-detail {
                font-size: 0.6rem;
            }
            .mobile-cards {
                padding: 0.25rem 0.5rem;
            }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- ===== GREETING BADGE ===== -->
        <div class="greeting-badge mb-6">
            <div class="flex justify-between items-start flex-wrap gap-4">
                <div>
                    <div class="flex items-center space-x-2 mb-1">
                        <i class="fas <?php echo $greeting_icon; ?> <?php echo $greeting_color; ?> text-lg"></i>
                        <span class="text-sm font-medium text-white/80"><?php echo $greeting; ?></span>
                    </div>
                    <h1 class="greeting-name"><?php echo htmlspecialchars($user_name); ?></h1>
                    <p class="text-emerald-100/80 text-xs mt-0.5">It's <?php echo $current_time; ?> on <?php echo $current_date; ?></p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="notification-container">
                        <div class="notification-bell bg-white/20 rounded-xl w-10 h-10 flex items-center justify-center" onclick="toggleNotifications()">
                            <i class="fas fa-bell text-white text-lg"></i>
                            <?php if($unread_count > 0): ?>
                            <span class="notification-badge" id="notificationBadge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="time-card">
                        <div class="time-display" id="currentTime"><?php echo date('h:i'); ?></div>
                        <div class="time-period" id="currentPeriod"><?php echo date('A'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ===== NOTIFICATION DROPDOWN ===== -->
        <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
            <div class="notification-header">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Notifications</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Stay updated on your reports</p>
                    </div>
                    <span class="text-xs bg-emerald-50 text-emerald-600 px-2.5 py-1 rounded-full font-medium">
                        <?php echo count($notifications); ?>
                    </span>
                </div>
            </div>
            
            <div class="notification-list">
                <?php if(count($notifications) > 0): ?>
                    <?php foreach($notifications as $notif): ?>
                    <div class="notification-item" data-link="<?php echo isset($notif['link']) ? $notif['link'] : ''; ?>">
                        <div class="notification-icon" style="background: <?php echo $notif['color']; ?>20;">
                            <i class="fas <?php echo $notif['icon']; ?>" style="color: <?php echo $notif['color']; ?>; font-size: 1rem;"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                            <div class="notification-time">
                                <i class="far fa-clock"></i>
                                <?php
                                    $time_diff = time() - strtotime($notif['time']);
                                    if($time_diff < 60) echo "Just now";
                                    elseif($time_diff < 3600) echo floor($time_diff / 60) . " min ago";
                                    elseif($time_diff < 86400) echo floor($time_diff / 3600) . " hrs ago";
                                    else echo date('M d', strtotime($notif['time']));
                                ?>
                            </div>
                        </div>
                        <?php if(!$notif['read']): ?>
                        <div class="notification-dot"></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-notifications py-8 text-center">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-bell-slash text-xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-400 text-sm">No notifications yet</p>
                        <p class="text-xs text-gray-300 mt-1">We'll notify you when something arrives</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(count($notifications) > 0): ?>
            <div class="mark-all-read" onclick="markAllAsRead()">
                <i class="fas fa-check-double mr-2"></i>Mark all as read
            </div>
            <?php endif; ?>
        </div>
        
        <!-- ===== ANNOUNCEMENT CARD (TITLE + RICH CONTENT) ===== -->
        <div class="announce-card mb-4 md:mb-6">
            <div class="announce-left">
                <div class="announce-icon"><i class="fas fa-bullhorn"></i></div>
                <div class="announce-text">
                    <span class="announce-label">
                        <i class="far fa-calendar-alt mr-1"></i> 
                        <?php 
                        if ($latest_announcement) {
                            echo date('M d, Y', strtotime($latest_announcement['created_at']));
                        } else {
                            echo 'No announcements';
                        }
                        ?>
                    </span>
                    <div class="announce-msg">
                        <?php if ($latest_announcement): ?>
                            <strong><?php echo htmlspecialchars($latest_announcement['title']); ?></strong>
                            <?php 
                                // Get safe content with allowed tags
                                $allowed_tags = '<p><br><strong><em><u><i><b><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><a>';
                                $safe_content = strip_tags($latest_announcement['content'], $allowed_tags);
                                // Remove event handlers and javascript:
                                $safe_content = preg_replace('/\s*on\w+\s*=\s*"[^"]*"/i', '', $safe_content);
                                $safe_content = preg_replace('/\s*on\w+\s*=\s*\'[^\']*\'/i', '', $safe_content);
                                $safe_content = preg_replace('/javascript\s*:/i', '', $safe_content);
                                $safe_content = preg_replace('/\s*style\s*=\s*"[^"]*"/i', '', $safe_content);
                                if (!empty(trim($safe_content))) {
                                    echo ' — ' . $safe_content;
                                }
                            ?>
                        <?php else: ?>
                            No announcements available at the moment.
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="announce-right">
                <?php if ($latest_announcement): ?>
                <span class="badge-delay">
                    <?php 
                    $days = floor((time() - strtotime($latest_announcement['created_at'])) / 86400);
                    if ($days == 0) echo 'New';
                    elseif ($days == 1) echo '1 day ago';
                    else echo $days . ' days ago';
                    ?>
                </span>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>index.php?page=announcements" class="btn-announce">
                    Details <i class="fas fa-chevron-right text-xs"></i>
                </a>
            </div>
        </div>

        <!-- ===== TWO COLUMN: REPORT ISSUE + STATS ===== -->
        <div class="two-col">
            <!-- LEFT: Report Issue -->
            <div class="report-issue-card">
                <div class="flex items-start gap-3 md:gap-4">
                    <div class="issue-icon-large"><i class="fas fa-tree"></i></div>
                    <div>
                        <div class="issue-title"><i class="fas fa-leaf"></i> Have you spotted an ecological concern?</div>
                    </div>
                </div>
                <div class="issue-description">
                    Rapid reporting helps local authorities address illegal dumping, pollution, and wildlife concerns before they escalate.
                </div>
                <div class="issue-action">
                    <a href="<?php echo BASE_URL; ?>index.php?page=submit-report" class="btn-report">
                        <span>Report an Issue Now</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- RIGHT: Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value"><?php echo $total_reports; ?></div>
                            <div class="stat-label">Total Reports</div>
                        </div>
                        <div class="stat-icon bg-emerald-100">
                            <i class="fas fa-flag text-[#10A37F] text-base md:text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value text-green-600"><?php echo $total_resolved_count; ?></div>
                            <div class="stat-label">Resolved</div>
                            <?php if($closed_count > 0): ?>
                            <span class="text-[10px] text-gray-400">(<?php echo $closed_count; ?> closed)</span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-icon bg-green-50">
                            <i class="fas fa-check-circle text-[#10A37F] text-base md:text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="stat-value text-yellow-600"><?php echo $pending_count; ?></div>
                            <div class="stat-label">Pending Action</div>
                        </div>
                        <div class="stat-icon bg-yellow-50">
                            <i class="fas fa-hourglass-half text-yellow-500 text-base md:text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ===== ENHANCED RECENT REPORTS ===== -->
        <div class="table-container">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list-ul"></i> Recent Reports
                </h3>
                <a href="<?php echo BASE_URL; ?>index.php?page=my-reports" class="view-all">
                    View All <i class="fas fa-arrow-right text-xs"></i>
                </a>
            </div>
            
            <!-- Desktop Table -->
            <table class="desktop-table">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Category</th>
                        <th>Barangay</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($display_reports)): ?>
                        <?php foreach($display_reports as $row): ?>
                        <tr>
                            <td>
                                <div class="report-title"><?php echo htmlspecialchars($row['title']); ?></div>
                                <span class="report-id">#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            </td>
                            <td class="category-cell"><?php echo htmlspecialchars($row['category_name']); ?></td>
                            <td class="barangay-cell"><?php echo htmlspecialchars($row['barangay_name']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="fas <?php 
                                        echo $row['status'] == 'pending' ? 'fa-clock' : 
                                            ($row['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : 
                                            ($row['status'] == 'escalated_pending' ? 'fa-hourglass-half' :
                                            ($row['status'] == 'escalated' ? 'fa-shield-alt' :
                                            ($row['status'] == 'resolved' ? 'fa-check-circle' : 
                                            ($row['status'] == 'closed' ? 'fa-archive' :
                                            ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-check')))))); 
                                    ?>"></i>
                                    <?php echo str_replace('_', ' ', ucfirst($row['status'])); ?>
                                </span>
                            </td>
                            <td class="date-cell"><?php echo date('M d', strtotime($row['created_at'])); ?></td>
                            <td class="action-cell">
                                <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $row['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-reports">
                                    <i class="fas fa-inbox"></i>
                                    <p>No reports yet</p>
                                    <a href="<?php echo BASE_URL; ?>index.php?page=submit-report">Submit your first report →</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Mobile Cards -->
            <div class="mobile-cards">
                <?php if(!empty($display_reports)): ?>
                    <?php foreach($display_reports as $row): ?>
                    <div class="report-card-item">
                        <div class="card-top">
                            <span class="card-title"><?php echo htmlspecialchars($row['title']); ?></span>
                            <span class="card-id">#<?php echo str_pad($row['id'], 6, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="card-details">
                            <span class="card-detail">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($row['category_name']); ?>
                            </span>
                            <span class="card-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($row['barangay_name']); ?>
                            </span>
                            <span class="card-detail">
                                <i class="far fa-calendar-alt"></i>
                                <?php echo date('M d', strtotime($row['created_at'])); ?>
                            </span>
                        </div>
                        <div class="card-bottom">
                            <span class="card-status">
                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                    <i class="fas <?php 
                                        echo $row['status'] == 'pending' ? 'fa-clock' : 
                                            ($row['status'] == 'in_progress' ? 'fa-spinner fa-pulse' : 
                                            ($row['status'] == 'escalated_pending' ? 'fa-hourglass-half' :
                                            ($row['status'] == 'escalated' ? 'fa-shield-alt' :
                                            ($row['status'] == 'resolved' ? 'fa-check-circle' : 
                                            ($row['status'] == 'closed' ? 'fa-archive' :
                                            ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-check')))))); 
                                    ?>"></i>
                                    <?php echo str_replace('_', ' ', ucfirst($row['status'])); ?>
                                </span>
                            </span>
                            <span class="card-action">
                                <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $row['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-reports">
                        <i class="fas fa-inbox"></i>
                        <p>No reports yet</p>
                        <a href="<?php echo BASE_URL; ?>index.php?page=submit-report">Submit your first report →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ===== COMMUNITY FOOTER ===== -->
        <div class="community-footer">
            <div><i class="fas fa-trophy"></i> <strong>Community Status:</strong> Calaba is ranked #3</div>
            <div><i class="fas fa-recycle"></i> <strong>Impact:</strong> 1.2 tons waste cleared</div>
            <div><i class="fas fa-leaf"></i> <strong>Sustainability:</strong> 85%</div>
        </div>
        
    </div>
</div>

<script>
// ========== NOTIFICATION FUNCTIONS ==========

let isDropdownOpen = false;
let currentDropdownElement = null;

function toggleNotifications() {
    var dropdown = document.getElementById('notificationDropdown');
    if (!dropdown) return;
    
    if (isDropdownOpen) {
        dropdown.style.display = 'none';
        isDropdownOpen = false;
    } else {
        dropdown.style.display = 'flex';
        positionDropdown();
        isDropdownOpen = true;
        
        if (currentDropdownElement) {
            window.removeEventListener('scroll', positionDropdown);
            window.removeEventListener('resize', positionDropdown);
        }
        currentDropdownElement = dropdown;
        window.addEventListener('scroll', positionDropdown);
        window.addEventListener('resize', positionDropdown);
    }
}

function positionDropdown() {
    var dropdown = document.getElementById('notificationDropdown');
    var bell = document.querySelector('.notification-bell');
    
    if (!bell || !dropdown) return;
    if (dropdown.style.display !== 'flex') return;
    
    var rect = bell.getBoundingClientRect();
    var viewportWidth = window.innerWidth;
    var dropdownHeight = dropdown.offsetHeight;
    
    var top = rect.bottom + 8;
    var maxTop = window.innerHeight - dropdownHeight - 10;
    if (top > maxTop) {
        top = rect.top - dropdownHeight - 8;
        if (top < 10) top = 10;
    }
    if (top < 10) top = 10;
    
    if (viewportWidth <= 640) {
        dropdown.style.left = '16px';
        dropdown.style.right = '16px';
        dropdown.style.width = 'auto';
    } else {
        dropdown.style.left = 'auto';
        dropdown.style.right = Math.max(16, viewportWidth - rect.right) + 'px';
        dropdown.style.width = '420px';
    }
    
    dropdown.style.top = top + 'px';
}

function handleNotificationClick(link) {
    if (link && link !== '') {
        window.location.href = link;
    }
    closeDropdown();
}

function closeDropdown() {
    var dropdown = document.getElementById('notificationDropdown');
    if (dropdown) {
        dropdown.style.display = 'none';
        isDropdownOpen = false;
    }
    if (currentDropdownElement) {
        window.removeEventListener('scroll', positionDropdown);
        window.removeEventListener('resize', positionDropdown);
        currentDropdownElement = null;
    }
}

function markAllAsRead() {
    var unreadItems = document.querySelectorAll('.notification-item .notification-dot');
    unreadItems.forEach(function(dot) { dot.remove(); });
    
    var badge = document.getElementById('notificationBadge');
    if (badge) badge.style.display = 'none';
    
    var markAllBtn = document.querySelector('.mark-all-read');
    if (markAllBtn) {
        var originalText = markAllBtn.innerHTML;
        markAllBtn.innerHTML = '<i class="fas fa-check mr-2"></i>All marked as read';
        setTimeout(function() { 
            if (markAllBtn) markAllBtn.innerHTML = originalText; 
        }, 2000);
    }
    
    setTimeout(function() { closeDropdown(); }, 1500);
}

function attachNotificationClickHandlers() {
    var items = document.querySelectorAll('.notification-item');
    items.forEach(function(item) {
        var link = item.getAttribute('data-link');
        var newItem = item.cloneNode(true);
        item.parentNode.replaceChild(newItem, item);
        newItem.addEventListener('click', function(e) {
            e.stopPropagation();
            handleNotificationClick(link);
        });
    });
}

document.addEventListener('click', function(event) {
    var dropdown = document.getElementById('notificationDropdown');
    var bell = document.querySelector('.notification-bell');
    
    if (dropdown && bell && isDropdownOpen) {
        if (!dropdown.contains(event.target) && !bell.contains(event.target)) {
            closeDropdown();
        }
    }
});

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && isDropdownOpen) {
        closeDropdown();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    attachNotificationClickHandlers();
    
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                attachNotificationClickHandlers();
            }
        });
    });
    
    var notificationList = document.querySelector('.notification-list');
    if (notificationList) {
        observer.observe(notificationList, { childList: true, subtree: true });
    }
});

// Real-time clock
function updateClock() {
    var now = new Date();
    var hours = now.getHours();
    var minutes = now.getMinutes();
    var ampm = hours >= 12 ? 'PM' : 'AM';
    var displayHours = hours % 12 || 12;
    var timeString = displayHours.toString().padStart(2, '0') + ':' + minutes.toString().padStart(2, '0');
    
    var timeElement = document.getElementById('currentTime');
    var periodElement = document.getElementById('currentPeriod');
    if (timeElement) timeElement.textContent = timeString;
    if (periodElement) periodElement.textContent = ampm;
}

setInterval(updateClock, 1000);
updateClock();

// Ring animation
<?php if($unread_count > 0): ?>
var bell = document.querySelector('.notification-bell');
if (bell) {
    bell.classList.add('ring-animation');
    setTimeout(function() { bell.classList.remove('ring-animation'); }, 600);
}
<?php endif; ?>
</script>

</body>
</html>