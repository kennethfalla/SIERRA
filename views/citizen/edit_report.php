<?php
// views/citizen/edit_report.php - WITH IMPACT MODIFIER SELECTION
// Compatible with updated ReportController.

require_once dirname(dirname(__DIR__)) . '/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SecurityHelper.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$reportModel = new Report($db);

$report_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($report_id == 0) {
    $_SESSION['error'] = "Invalid report ID.";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// Get report data - MUST match user_id and status 'pending'
$query = "SELECT r.*, c.name as category_name, c.icon_class, b.name as barangay_name,
                 CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM reports r
          JOIN categories c ON r.category_id = c.id
          JOIN barangays b ON r.barangay_id = b.id
          JOIN users u ON r.user_id = u.id
          WHERE r.id = :id AND r.user_id = :user_id AND r.status = 'pending'";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $report_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    $_SESSION['error'] = "Report not found or cannot be edited (only pending reports can be edited).";
    header("Location: " . BASE_URL . "index.php?page=my-reports");
    exit();
}

// Get images for this report
$img_query = "SELECT * FROM report_images WHERE report_id = :report_id ORDER BY is_primary DESC, uploaded_at ASC";
$img_stmt = $db->prepare($img_query);
$img_stmt->bindParam(':report_id', $report_id);
$img_stmt->execute();
$images = $img_stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();

// Current impact modifier
$current_impact = isset($report['impact_modifier']) ? (int)$report['impact_modifier'] : 0;

// Handle edit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token. Please refresh and try again.";
        header("Location: " . BASE_URL . "index.php?page=edit-report&id=" . $report_id);
        exit();
    }

    // Validate impact_modifier
    $impact_modifier = isset($_POST['impact_modifier']) ? (int)$_POST['impact_modifier'] : 0;
    if (!in_array($impact_modifier, [0, 2, 4], true)) {
        $impact_modifier = 0;
    }

    $update_query = "UPDATE reports SET 
                     title = :title,
                     description = :description,
                     category_id = :category_id,
                     risk_level = :risk_level,
                     impact_modifier = :impact_modifier,
                     updated_at = NOW()
                     WHERE id = :id AND user_id = :user_id AND status = 'pending'";
    
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':title', $_POST['title']);
    $stmt->bindParam(':description', $_POST['description']);
    $stmt->bindParam(':category_id', $_POST['category_id']);
    $stmt->bindParam(':risk_level', $_POST['risk_level']); // will be recalculated anyway
    $stmt->bindParam(':impact_modifier', $impact_modifier);
    $stmt->bindParam(':id', $report_id);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        $upload_dir = BASE_PATH . 'uploads/reports/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        // Handle new image uploads
        if (isset($_FILES['new_images']) && isset($_FILES['new_images']['name'])) {
            for ($i = 0; $i < count($_FILES['new_images']['name']); $i++) {
                if ($_FILES['new_images']['error'][$i] === 0 && !empty($_FILES['new_images']['name'][$i])) {
                    $file_ext = strtolower(pathinfo($_FILES['new_images']['name'][$i], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if (in_array($file_ext, $allowed)) {
                        $new_filename = uniqid() . '_' . time() . '_' . $i . '.' . $file_ext;
                        if (move_uploaded_file($_FILES['new_images']['tmp_name'][$i], $upload_dir . $new_filename)) {
                            $image_path = 'uploads/reports/' . $new_filename;
                            $ins_query = "INSERT INTO report_images (report_id, image_path, is_primary) VALUES (:rid, :path, 0)";
                            $ins_stmt = $db->prepare($ins_query);
                            $ins_stmt->bindParam(':rid', $report_id);
                            $ins_stmt->bindParam(':path', $image_path);
                            $ins_stmt->execute();
                        }
                    }
                }
            }
        }
        
        // Handle image deletion
        if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
            $delete_ids = explode(',', $_POST['delete_images']);
            foreach ($delete_ids as $image_id) {
                if (is_numeric($image_id)) {
                    $get_img = $db->prepare("SELECT image_path FROM report_images WHERE id = :id AND report_id = :rid");
                    $get_img->bindParam(':id', $image_id);
                    $get_img->bindParam(':rid', $report_id);
                    $get_img->execute();
                    $img_data = $get_img->fetch(PDO::FETCH_ASSOC);
                    if ($img_data) {
                        $file_path = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $img_data['image_path'];
                        if (file_exists($file_path)) unlink($file_path);
                        $del = $db->prepare("DELETE FROM report_images WHERE id = :id AND report_id = :rid");
                        $del->bindParam(':id', $image_id);
                        $del->bindParam(':rid', $report_id);
                        $del->execute();
                    }
                }
            }
        }
        
        // Recalc severity (category or impact may have changed)
        $reportModel->calculateAndUpdateSeverity($report_id);
        
        // Recalc nearby reports
        if ($report['latitude'] && $report['longitude']) {
            $reportModel->recalcReportsNearLocation($report['latitude'], $report['longitude'], $report_id);
        }
        
        $_SESSION['success'] = "Report updated successfully!";
        header("Location: " . BASE_URL . "index.php?page=track-status&id=" . $report_id);
        exit();
    } else {
        $_SESSION['error'] = "Failed to update report.";
    }
}

$categories = $db->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Impact options (same as submit report)
$impact_options = [
    0 => ['label' => 'Localized / Minor', 'desc' => 'Contained in one small area, no immediate danger.', 'icon' => 'fa-circle', 'color' => 'emerald'],
    2 => ['label' => 'Moderate', 'desc' => 'Affecting sidewalks or causing strong, widespread odor.', 'icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
    4 => ['label' => 'Severe', 'desc' => 'Blocking roads, entering homes, active safety hazard.', 'icon' => 'fa-fire', 'color' => 'red']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Edit Report - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Manrope', sans-serif; }
        
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            transition: all 0.2s ease;
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(16,163,127,0.3); }
        .btn-primary:active { transform: translateY(0); }
        
        .impact-option {
            cursor: pointer;
        }
        .impact-option .card {
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            border-radius: 0.9rem;
            padding: 0.8rem 0.75rem;
            text-align: center;
            height: 100%;
            background: white;
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .impact-option .card:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .impact-option.selected .card {
            border-color: #10A37F;
            background: #f0fdf4;
            box-shadow: 0 3px 8px rgba(16,163,127,0.1);
        }
        .impact-option.selected-localized .card {
            border-color: #10A37F;
            background: #f0fdf4;
        }
        .impact-option.selected-moderate .card {
            border-color: #f59e0b;
            background: #fffbeb;
        }
        .impact-option.selected-severe .card {
            border-color: #ef4444;
            background: #fef2f2;
        }
        .impact-option .icon { font-size: 1.25rem; margin-bottom: 0.35rem; }
        .impact-option .title { font-weight: 700; font-size: 0.9rem; color: #1f2937; line-height: 1.2; }
        .impact-option .desc { font-size: 0.72rem; color: #6b7280; margin-top: 0.2rem; line-height: 1.35; }
        .impact-option .badge-severe { font-size: 0.65rem; display: block; margin-top: 0.25rem; color: #ef4444; font-weight: 600; }
        
        .image-item {
            position: relative;
            transition: all 0.3s ease;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .image-item.marked-for-delete {
            opacity: 0.5;
            filter: grayscale(0.5);
        }
        .image-item.marked-for-delete::after {
            content: '🗑️ Marked for deletion';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.7rem;
            background: rgba(0,0,0,0.7);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .delete-img-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: rgba(239,68,68,0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
            font-size: 12px;
        }
        .delete-img-btn:hover { background: #dc2626; transform: scale(1.1); }
        
        .toast-message {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1f2937;
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 12px;
            z-index: 1000;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            font-size: 0.875rem;
        }
        .toast-message.show { display: flex; animation: slideIn 0.3s ease; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .toast-undo {
            background: #10A37F;
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.75rem;
            transition: all 0.2s;
        }
        .toast-undo:hover { background: #0D8568; }
        
        .upload-area {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .upload-area:hover { border-color: #10A37F; background: #F0FDF4; }
        
        .photo-preview {
            position: relative;
            transition: all 0.2s ease;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .photo-preview:hover .remove-photo { opacity: 1; }
        .remove-photo {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 28px;
            height: 28px;
            background: #EF4444;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s ease;
            font-size: 12px;
        }
        .remove-photo:hover { transform: scale(1.1); background: #DC2626; }
        
        .form-input:focus {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16,163,127,0.1);
            outline: none;
        }
        
        @media (max-width: 768px) {
            .ml-72 { margin-left: 0; }
            .impact-option .card { padding: 0.6rem 0.4rem; min-height: 90px; }
            .impact-option .title { font-size: 0.8rem; }
            .impact-option .desc { font-size: 0.65rem; }
        }
    </style>
</head>
<body class="bg-[#F5FBF6]">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<div class="ml-72 min-h-screen">
    <div class="p-4 md:p-8 max-w-6xl mx-auto">
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Header with Breadcrumb -->
        <div class="mb-6 md:mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $report_id; ?>" 
                       class="text-gray-400 hover:text-gray-600 transition">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-edit text-amber-600 text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-bold text-gray-800">Edit Report</h1>
                        <p class="text-gray-500 text-xs md:text-sm">Update your environmental report details</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs font-semibold">
                        <i class="fas fa-clock mr-1"></i> Pending
                    </span>
                    <span class="text-xs text-gray-400">
                        <i class="fas fa-calendar-alt mr-1"></i>
                        Created: <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Main Form - Left Column (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Report Info Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 md:px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-info-circle text-[#10A37F] text-sm"></i>
                        <span class="text-sm font-medium text-gray-700">Report Information</span>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="editForm" class="p-4 md:p-5">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="delete_images" id="delete_images" value="">
                        <input type="hidden" name="risk_level" value="<?php echo $report['risk_level']; ?>">
                        
                        <div class="space-y-4 md:space-y-5">
                            <!-- Title -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Report Title *</label>
                                <input type="text" name="title" required value="<?php echo htmlspecialchars($report['title']); ?>" 
                                       class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] focus:ring-1 focus:ring-[#10A37F] outline-none transition text-sm">
                            </div>
                            
                            <!-- Category & Impact Modifier Row -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                                    <select name="category_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] outline-none bg-white text-sm">
                                        <?php foreach($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $report['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Impact Modifier (replaces risk level selector) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Impact Level *</label>
                                    <div class="grid grid-cols-3 gap-2" id="impactContainer">
                                        <?php foreach($impact_options as $value => $opt): 
                                            $selected = ($current_impact == $value) ? 'selected' : '';
                                            $selectedClass = '';
                                            if ($selected) {
                                                if ($value == 0) $selectedClass = 'selected-localized';
                                                elseif ($value == 2) $selectedClass = 'selected-moderate';
                                                elseif ($value == 4) $selectedClass = 'selected-severe';
                                            }
                                        ?>
                                        <div class="impact-option <?php echo $selected . ' ' . $selectedClass; ?>" data-value="<?php echo $value; ?>" onclick="selectImpact(<?php echo $value; ?>)">
                                            <div class="card">
                                                <div class="icon"><i class="fas <?php echo $opt['icon']; ?> text-<?php echo $opt['color']; ?>-500"></i></div>
                                                <div class="title"><?php echo $opt['label']; ?></div>
                                                <div class="desc"><?php echo $opt['desc']; ?></div>
                                                <?php if($value == 4): ?>
                                                <span class="badge-severe">Auto-escalates</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="impact_modifier" id="impact_modifier" value="<?php echo $current_impact; ?>">
                                    <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                        <i class="fas fa-info-circle text-emerald-500"></i>
                                        This helps prioritize urgent reports. <strong class="text-red-500">Severe</strong> issues automatically trigger a High Priority alert to MENRO.
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                                <textarea name="description" rows="5" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-[#10A37F] focus:ring-1 focus:ring-[#10A37F] outline-none transition text-sm"><?php echo htmlspecialchars($report['description']); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex justify-end gap-3 pt-4 md:pt-5 mt-4 md:mt-5 border-t border-gray-100">
                            <a href="<?php echo BASE_URL; ?>index.php?page=track-status&id=<?php echo $report_id; ?>" 
                               class="px-4 md:px-5 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition font-medium text-sm">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 md:px-5 py-2 btn-primary text-white rounded-xl font-medium text-sm">
                                <i class="fas fa-save mr-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Images Card (unchanged) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 md:px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-image text-[#10A37F] text-sm"></i>
                        <span class="text-sm font-medium text-gray-700">Manage Images</span>
                    </div>
                    
                    <div class="p-4 md:p-5">
                        <?php if(!empty($images)): ?>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Images</label>
                            <p class="text-xs text-gray-400 mb-3">Click the ✕ button to delete an image. Click again to undo.</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3" id="imagesContainer">
                                <?php foreach($images as $img): ?>
                                <div class="image-item relative" data-image-id="<?php echo $img['id']; ?>" id="img_<?php echo $img['id']; ?>">
                                    <img src="<?php echo BASE_URL . $img['image_path']; ?>" class="w-full h-24 md:h-28 object-cover rounded-lg border" onerror="this.src='https://placehold.co/400x300/e2e8f0/94a3b8?text=No+Image'">
                                    <div class="delete-img-btn" onclick="toggleImageDeletion(<?php echo $img['id']; ?>, this)">
                                        <i class="fas fa-times text-xs"></i>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="mb-6 text-center py-4 bg-gray-50 rounded-lg">
                            <i class="fas fa-image text-3xl text-gray-300 mb-2 block"></i>
                            <p class="text-sm text-gray-400">No images yet</p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Add New Images</label>
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 upload-area text-center" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt text-2xl md:text-3xl text-gray-400 mb-2 block"></i>
                                <p class="text-sm text-gray-500">Click or drag & drop to upload</p>
                                <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WebP (Max 5MB each)</p>
                                <input type="file" name="new_images[]" id="newImages" multiple accept="image/*" style="display: none;">
                            </div>
                            <div id="newImagePreviews" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar - Right Column (1/3 width) - (unchanged) -->
            <div class="space-y-6">
                <!-- Status Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 md:px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-chart-line text-[#10A37F] text-sm"></i>
                        <span class="text-sm font-medium text-gray-700">Report Status</span>
                    </div>
                    <div class="p-4 md:p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm text-gray-500">Current Status</span>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-700">
                                <i class="fas fa-clock mr-1"></i> Pending
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-yellow-500 h-2 rounded-full" style="width: 25%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-3">Your report is waiting for verification from barangay officials.</p>
                        <p class="text-xs text-gray-400 mt-1">You can only edit pending reports.</p>
                    </div>
                </div>
                
                <!-- Impact Info Card (new) -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-4 md:px-5 py-3 bg-gray-50 border-b border-gray-100 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-[#10A37F] text-sm"></i>
                        <span class="text-sm font-medium text-gray-700">Impact Level Info</span>
                    </div>
                    <div class="p-4 md:p-5">
                        <div class="space-y-3">
                            <?php foreach($impact_options as $value => $opt): ?>
                            <div class="flex items-center gap-3 p-2 rounded-lg <?php echo $current_impact == $value ? 'bg-gray-50' : ''; ?>">
                                <div class="w-8 h-8 rounded-full bg-<?php echo $opt['color']; ?>-50 flex items-center justify-center">
                                    <i class="fas <?php echo $opt['icon']; ?> text-<?php echo $opt['color']; ?>-500 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-800"><?php echo $opt['label']; ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $opt['desc']; ?></p>
                                </div>
                                <?php if($current_impact == $value): ?>
                                <i class="fas fa-check-circle text-emerald-500 text-sm"></i>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-xs text-gray-400 flex items-center gap-1">
                            <i class="fas fa-info-circle text-emerald-500"></i>
                            <span>Severe issues automatically trigger a High Priority alert to MENRO.</span>
                        </div>
                    </div>
                </div>
                
                <!-- Editing Guidelines Card -->
                <div class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl border border-emerald-100 p-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-emerald-500 mt-0.5"></i>
                        <div class="text-sm text-emerald-800">
                            <p class="font-medium">Editing Guidelines:</p>
                            <ul class="text-xs mt-2 space-y-1 text-emerald-700">
                                <li>• Only pending reports can be edited</li>
                                <li>• Deleted images cannot be recovered</li>
                                <li>• New images will be added to your report</li>
                                <li>• Changes will be reviewed by barangay</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Report ID Card -->
                <div class="bg-gray-50 rounded-2xl border border-gray-200 p-4 text-center">
                    <p class="text-xs text-gray-400">Report ID</p>
                    <p class="text-lg md:text-xl font-mono font-bold text-gray-700">#<?php echo str_pad($report_id, 6, '0', STR_PAD_LEFT); ?></p>
                    <p class="text-xs text-gray-400 mt-2">Created: <?php echo date('M d, Y', strtotime($report['created_at'])); ?></p>
                    <p class="text-xs text-gray-400">Last updated: <?php echo date('M d, Y', strtotime($report['updated_at'])); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Message -->
<div id="toastMessage" class="toast-message">
    <span id="toastText">Image marked for deletion</span>
    <button id="undoDeleteBtn" class="toast-undo">Undo</button>
</div>

<script>
// ============================================
// IMPACT MODIFIER SELECTION
// ============================================
function selectImpact(value) {
    document.querySelectorAll('.impact-option').forEach(el => {
        el.classList.remove('selected', 'selected-localized', 'selected-moderate', 'selected-severe');
    });
    
    const selected = document.querySelector(`.impact-option[data-value="${value}"]`);
    if (selected) {
        selected.classList.add('selected');
        if (value === 0) selected.classList.add('selected-localized');
        else if (value === 2) selected.classList.add('selected-moderate');
        else if (value === 4) selected.classList.add('selected-severe');
    }
    
    document.getElementById('impact_modifier').value = value;
}

// ============================================
// IMAGE DELETION & UPLOAD (unchanged)
// ============================================
let selectedRisk = '<?php echo $report['risk_level']; ?>';
let pendingDeletions = new Map();
let toastTimeout = null;
let newImageFiles = [];

function toggleImageDeletion(imageId, buttonElement) {
    const imageContainer = document.getElementById(`img_${imageId}`);
    if (pendingDeletions.has(imageId)) {
        pendingDeletions.delete(imageId);
        buttonElement.style.backgroundColor = 'rgba(239,68,68,0.9)';
        buttonElement.innerHTML = '<i class="fas fa-times text-xs"></i>';
        imageContainer.classList.remove('marked-for-delete');
        const toast = document.getElementById('toastMessage');
        toast.classList.remove('show');
        if (toastTimeout) clearTimeout(toastTimeout);
    } else {
        pendingDeletions.set(imageId, imageContainer);
        buttonElement.style.backgroundColor = '#dc2626';
        buttonElement.innerHTML = '<i class="fas fa-undo-alt text-xs"></i>';
        imageContainer.classList.add('marked-for-delete');
        showUndoToast(imageId);
    }
    updateDeleteImagesInput();
}

function showUndoToast(imageId) {
    const toast = document.getElementById('toastMessage');
    const undoBtn = document.getElementById('undoDeleteBtn');
    const newUndoBtn = undoBtn.cloneNode(true);
    undoBtn.parentNode.replaceChild(newUndoBtn, undoBtn);
    newUndoBtn.addEventListener('click', function() {
        const imgContainer = document.getElementById(`img_${imageId}`);
        const btn = imgContainer?.querySelector('.delete-img-btn');
        if (btn) toggleImageDeletion(imageId, btn);
        toast.classList.remove('show');
        if (toastTimeout) clearTimeout(toastTimeout);
    });
    toast.classList.add('show');
    if (toastTimeout) clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => toast.classList.remove('show'), 5000);
}

function updateDeleteImagesInput() {
    document.getElementById('delete_images').value = Array.from(pendingDeletions.keys()).join(',');
}

const uploadArea = document.getElementById('uploadArea');
const newImagesInput = document.getElementById('newImages');
const newImagePreviewsContainer = document.getElementById('newImagePreviews');

uploadArea.addEventListener('click', () => newImagesInput.click());
newImagesInput.addEventListener('change', (e) => handleNewImages(Array.from(e.target.files)));
uploadArea.addEventListener('dragover', (e) => { 
    e.preventDefault(); 
    uploadArea.classList.add('border-[#10A37F]', 'bg-emerald-50'); 
});
uploadArea.addEventListener('dragleave', (e) => { 
    uploadArea.classList.remove('border-[#10A37F]', 'bg-emerald-50'); 
});
uploadArea.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadArea.classList.remove('border-[#10A37F]', 'bg-emerald-50');
    handleNewImages(Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/')));
});

function handleNewImages(files) {
    for (let file of files) {
        if (file.type.startsWith('image/')) {
            newImageFiles.push(file);
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'photo-preview relative';
                previewDiv.innerHTML = `
                    <img src="${e.target.result}" class="w-full h-24 md:h-28 object-cover rounded-lg border border-gray-200">
                    <div class="remove-photo" onclick="removeNewImage(this, '${file.name.replace(/'/g, "\\'")}')">
                        <i class="fas fa-times text-sm"></i>
                    </div>
                `;
                newImagePreviewsContainer.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
    }
    updateNewImagesInput();
}

function removeNewImage(element, fileName) {
    const index = newImageFiles.findIndex(f => f.name === fileName);
    if (index !== -1) newImageFiles.splice(index, 1);
    element.closest('.photo-preview').remove();
    updateNewImagesInput();
}

function updateNewImagesInput() {
    const dataTransfer = new DataTransfer();
    newImageFiles.forEach(file => dataTransfer.items.add(file));
    newImagesInput.files = dataTransfer.files;
}

document.getElementById('editForm').addEventListener('submit', function(e) {
    if (pendingDeletions.size > 0) {
        return confirm(`You have marked ${pendingDeletions.size} image(s) for deletion. This action cannot be undone. Continue?`);
    }
    return true;
});
</script>
</body>
</html>