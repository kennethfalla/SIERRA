<?php
// views/profile.php - REDESIGNED WITH HEADER LIKE MY REPORTS
// Two-card layout, responsive margin, Back to Dashboard link, and logout button

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $db->prepare("
    SELECT u.*, b.name as barangay_name
    FROM users u
    LEFT JOIN barangays b ON u.barangay_id = b.id
    WHERE u.id = :user_id
");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: " . BASE_URL . "index.php?page=dashboard");
    exit();
}

// Get barangays for dropdown
$barangays = $db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $purok_street = trim($_POST['purok_street']);
    $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;

    $errors = [];
    if (strlen($first_name) < 2) $errors[] = "First name must be at least 2 characters.";
    if (strlen($last_name) < 2) $errors[] = "Last name must be at least 2 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
    if (!preg_match('/^09[0-9]{9}$/', $contact_number)) $errors[] = "Invalid mobile number.";

    $check = $db->prepare("SELECT id FROM users WHERE (email = :email OR contact_number = :contact) AND id != :user_id");
    $check->execute([':email' => $email, ':contact' => $contact_number, ':user_id' => $user_id]);
    if ($check->rowCount() > 0) {
        $errors[] = "Email or contact number already in use.";
    }

    // Handle cropped image
    $profile_picture = $user['profile_picture'];
    if (!empty($_POST['cropped_image'])) {
        $cropped_data = $_POST['cropped_image'];
        $parts = explode(',', $cropped_data);
        if (count($parts) === 2) {
            $base64 = $parts[1];
            $image_data = base64_decode($base64);
            if ($image_data !== false) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/profile/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if ($profile_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $profile_picture)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $profile_picture);
                }
                
                $ext = 'png';
                $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                $target = $upload_dir . $new_filename;
                if (file_put_contents($target, $image_data)) {
                    $profile_picture = 'uploads/profile/' . $new_filename;
                    $_SESSION['profile_picture'] = $profile_picture;
                } else {
                    $errors[] = "Failed to save image.";
                }
            }
        }
    }

    if (empty($errors)) {
        $update = $db->prepare("
            UPDATE users 
            SET first_name = :first_name,
                last_name = :last_name,
                email = :email,
                contact_number = :contact_number,
                purok_street = :purok_street,
                barangay_id = :barangay_id,
                profile_picture = :profile_picture
            WHERE id = :user_id
        ");
        $update->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':contact_number' => $contact_number,
            ':purok_street' => $purok_street,
            ':barangay_id' => $barangay_id,
            ':profile_picture' => $profile_picture,
            ':user_id' => $user_id
        ]);
        $_SESSION['success'] = "Profile updated!";
        
        $user = $db->prepare("SELECT u.*, b.name as barangay_name FROM users u LEFT JOIN barangays b ON u.barangay_id = b.id WHERE u.id = :user_id");
        $user->execute([':user_id' => $user_id]);
        $user = $user->fetch(PDO::FETCH_ASSOC);
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        
        if ($profile_picture) {
            $_SESSION['profile_picture'] = $profile_picture;
        }
    } else {
        $_SESSION['errors'] = $errors;
    }
    header("Location: " . BASE_URL . "index.php?page=profile");
    exit();
}

$profile_pic_url = !empty($user['profile_picture']) ? BASE_URL . $user['profile_picture'] : '';

$name_parts = explode(' ', $user['first_name'] . ' ' . $user['last_name']);
$initials = '';
if (count($name_parts) >= 2) {
    $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[1], 0, 1));
} else {
    $initials = strtoupper(substr($user['first_name'], 0, 2));
}

$user_type = $user['user_type'] ?? null;
$role_display = ['admin' => 'Admin', 'menro_staff' => 'MENRO Staff', 'barangay_personnel' => 'Barangay Official'][$user_type] ?? 'Citizen';
$role_badge_color = in_array($user_type, ['admin', 'menro_staff']) ? 'bg-purple-100 text-purple-700' :
                    ($user_type === 'barangay_personnel' ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700');
$join_date = date('F Y', strtotime($user['created_at']));
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 'true';
$barangay_name = $user['barangay_name'] ?? '';
$full_name = $user['first_name'] . ' ' . $user['last_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>My Profile - EnviroTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { 
            background: #F5FBF6;
            overflow-x: hidden;
        }
        
        /* ===== RESPONSIVE SIDEBAR FIX (matches my_reports.php) ===== */
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
        
        /* ===== CONTAINER ===== */
        .main-container {
            padding: 1rem;
            max-width: 1000px;
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
        
        /* ===== HEADER (matches my_reports.php) ===== */
        .page-header {
            margin-bottom: 1.25rem;
        }
        @media (min-width: 640px) {
            .page-header {
                margin-bottom: 1.5rem;
            }
        }
        .page-title {
            font-size: 1.5rem;
        }
        @media (min-width: 640px) {
            .page-title {
                font-size: 1.875rem;
            }
        }
        
        /* ===== BUTTONS ===== */
        .btn-primary {
            background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        @media (min-width: 640px) {
            .btn-primary {
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
            }
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
        }
        
        .btn-secondary {
            background: white;
            border: 1px solid #e2e8f0;
            color: #4b5563;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        @media (min-width: 640px) {
            .btn-secondary {
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
            }
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        
        .btn-danger {
            background: #EF4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }
        @media (min-width: 640px) {
            .btn-danger {
                padding: 0.625rem 1.25rem;
                font-size: 0.875rem;
            }
        }
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        /* ===== CARDS ===== */
        .profile-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .profile-card:hover {
            border-color: #10A37F;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.05);
        }
        
        /* ===== AVATAR ===== */
        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10A37F, #0D8568);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            object-fit: cover;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.2);
        }
        @media (min-width: 640px) {
            .avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar .initials {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
        }
        
        /* ===== AVATAR UPLOAD OVERLAY ===== */
        .avatar-upload-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(6px);
            color: white;
            text-align: center;
            padding: 6px 0;
            font-size: 0.6rem;
            cursor: pointer;
            font-weight: 500;
            letter-spacing: 0.3px;
            display: none;
            align-items: center;
            justify-content: center;
            gap: 4px;
            border-radius: 0 0 50% 50% / 0 0 8px 8px;
        }
        .avatar-upload-overlay i {
            font-size: 0.7rem;
        }
        .avatar-upload-overlay.show {
            display: flex !important;
        }
        
        /* ===== METRICS ===== */
        .metric-item {
            display: flex;
            flex-direction: column;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .metric-item:last-child {
            border-bottom: none;
        }
        .metric-label {
            font-size: 0.65rem;
            font-weight: 600;
            color: #8aa38a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .metric-value {
            font-size: 0.9rem;
            font-weight: 500;
            color: #1a2e1a;
            margin-top: 0.1rem;
        }
        @media (min-width: 640px) {
            .metric-label {
                font-size: 0.7rem;
            }
            .metric-value {
                font-size: 1rem;
            }
        }
        
        /* ===== FORM ===== */
        .form-input {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1.5px solid #e5ece8;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: white;
            color: #1a2e1a;
            min-height: 44px;
        }
        .form-input:focus {
            border-color: #10A37F;
            outline: none;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        }
        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #4d6b4a;
            margin-bottom: 0.25rem;
        }
        @media (min-width: 640px) {
            .form-label {
                font-size: 0.875rem;
            }
        }
        
        /* ===== CROP MODAL ===== */
        .crop-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(8px);
            z-index: 100000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .crop-modal.active { display: flex; }
        .crop-modal-content {
            background: white;
            border-radius: 1rem;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .crop-modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid #eef3f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }
        .crop-modal-header h3 { 
            font-weight: 700; 
            color: #1a2e1a;
            font-size: 1rem;
        }
        .crop-modal-header h3 i { color: #10A37F; margin-right: 0.5rem; }
        .crop-modal-body { 
            padding: 16px; 
            overflow: hidden; 
            flex: 1;
            min-height: 200px;
        }
        .crop-modal-body img { max-width: 100%; display: block; }
        .crop-modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #eef3f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        .crop-modal-footer button {
            min-height: 44px;
        }
        @media (max-width: 480px) {
            .crop-modal-footer {
                flex-direction: column;
            }
            .crop-modal-footer button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* ===== RESPONSIVE FINE-TUNING ===== */
        @media (max-width: 480px) {
            .avatar {
                width: 64px;
                height: 64px;
                font-size: 1.5rem;
            }
            .metric-value {
                font-size: 0.8rem;
            }
            .btn-primary, .btn-secondary, .btn-danger {
                font-size: 0.75rem;
                padding: 0.4rem 0.75rem;
            }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<!-- ===== CONTAINER STRUCTURE (matches my_reports.php) ===== -->
<div class="lg:ml-72 min-h-screen">
    <div class="main-container max-w-7xl mx-auto">
        
        <!-- Back to Dashboard -->
        <div class="mb-4">
            <a href="<?php echo BASE_URL; ?>index.php?page=dashboard" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#10A37F] transition text-sm font-medium">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- ===== HEADER (matches my_reports.php) ===== -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-circle text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">My Profile</span>
            </div>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="page-title font-bold text-gray-800">My Profile</h1>
                    <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">View and manage your personal account information</p>
                </div>
                <button id="editToggleBtn" class="btn-primary inline-flex items-center gap-1.5 md:gap-2 w-full sm:w-auto justify-center">
                    <i class="fas fa-pen text-xs md:text-sm"></i> Edit Profile
                </button>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['errors']) && is_array($_SESSION['errors'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach($_SESSION['errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- TOP CARD: Profile Header -->
        <!-- ============================================================ -->
        <div class="profile-card mb-6">
            <div class="p-5 md:p-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                    <!-- Avatar -->
                    <div class="avatar" id="avatarContainer">
                        <?php if ($profile_pic_url): ?>
                            <img src="<?php echo $profile_pic_url; ?>" alt="Profile" id="avatarImg">
                        <?php else: ?>
                            <span class="initials" id="avatarInitials"><?php echo $initials; ?></span>
                        <?php endif; ?>
                        <div class="avatar-upload-overlay" id="avatarUploadOverlay">
                            <i class="fas fa-camera"></i> Change Photo
                        </div>
                        <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                    </div>
                    
                    <!-- User Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h1 class="text-2xl md:text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($full_name); ?></h1>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $role_badge_color; ?>">
                                        <i class="fas <?php echo in_array($user_type, ['admin', 'menro_staff'], true) ? 'fa-building' : ($user_type === 'barangay_personnel' ? 'fa-landmark' : 'fa-user'); ?> mr-1"></i>
                                        <?php echo $role_display; ?>
                                    </span>
                                    <?php if($barangay_name): ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                                        <i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($barangay_name); ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        <i class="far fa-calendar-alt mr-1"></i>Joined <?php echo $join_date; ?>
                                    </span>
                                </div>
                            </div>
                            <!-- The Edit Profile button is now in the header, but we keep a fallback here just in case -->
                        </div>
                    </div>
                </div>
                
                <!-- Metrics Row -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-5 pt-4 border-t border-gray-100">
                    <div class="metric-item">
                        <span class="metric-label">Status</span>
                        <span class="metric-value"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label">Type</span>
                        <span class="metric-value"><?php echo $user['is_resident'] ? 'Resident' : 'Non-Resident'; ?></span>
                    </div>
                    <div class="metric-item">
                        <span class="metric-label">Role</span>
                        <span class="metric-value"><?php echo $role_display; ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- BOTTOM CARD: Personal Information (View / Edit) -->
        <!-- ============================================================ -->
        <div class="profile-card">
            <div class="p-5 md:p-6">
                <!-- View Mode -->
                <div id="viewSection">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fas fa-id-card text-[#10A37F]"></i>
                        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Personal Information</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="metric-item">
                            <span class="metric-label">First Name</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['first_name']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Last Name</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['last_name']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Email</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Mobile Number</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['contact_number']); ?></span>
                        </div>
                        <?php if($user['is_resident']): ?>
                        <div class="metric-item">
                            <span class="metric-label">Purok/Street</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['purok_street'] ?? '—'); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Barangay</span>
                            <span class="metric-value"><?php echo htmlspecialchars($barangay_name ?: '—'); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="metric-item">
                            <span class="metric-label">Province</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['province'] ?? '—'); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Municipality</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['municipality'] ?? '—'); ?></span>
                        </div>
                        <div class="metric-item">
                            <span class="metric-label">Address</span>
                            <span class="metric-value"><?php echo htmlspecialchars($user['non_resident_address'] ?? '—'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Bottom Bar: Logout -->
                    <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end">
                        <button onclick="window.openLogoutModal()" class="btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <div id="editSection" style="display: none;">
                    <div class="flex items-center gap-2 mb-4">
                        <i class="fas fa-pen text-[#10A37F]"></i>
                        <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Edit Profile</h3>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="cropped_image" id="croppedImage" value="">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="form-input" required>
                            </div>
                            <div>
                                <label class="form-label">Mobile Number *</label>
                                <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" class="form-input" pattern="09[0-9]{9}" required>
                            </div>
                            <?php if($user['is_resident']): ?>
                            <div>
                                <label class="form-label">Purok/Street</label>
                                <input type="text" name="purok_street" value="<?php echo htmlspecialchars($user['purok_street'] ?? ''); ?>" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">Barangay</label>
                                <select name="barangay_id" class="form-input">
                                    <option value="">Select Barangay</option>
                                    <?php foreach($barangays as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" <?php echo ($user['barangay_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" id="cancelEditBtn" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Crop Modal -->
<div id="cropModal" class="crop-modal">
    <div class="crop-modal-content">
        <div class="crop-modal-header">
            <h3><i class="fas fa-crop-alt"></i> Crop Photo</h3>
            <button onclick="closeCropModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div class="crop-modal-body">
            <img id="cropImage" src="" alt="Crop">
        </div>
        <div class="crop-modal-footer">
            <button onclick="closeCropModal()" class="btn-secondary">Cancel</button>
            <button id="cropConfirmBtn" class="btn-primary">Crop &amp; Upload</button>
        </div>
    </div>
</div>

<script>
(function() {
    const editBtn = document.getElementById('editToggleBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const viewSection = document.getElementById('viewSection');
    const editSection = document.getElementById('editSection');
    const avatarUploadOverlay = document.getElementById('avatarUploadOverlay');
    const avatarFileInput = document.getElementById('avatarFileInput');
    const avatarContainer = document.getElementById('avatarContainer');
    const avatarImg = document.getElementById('avatarImg');
    const avatarInitials = document.getElementById('avatarInitials');
    const form = document.getElementById('profileForm');
    const cropModal = document.getElementById('cropModal');
    const cropImage = document.getElementById('cropImage');
    const cropConfirmBtn = document.getElementById('cropConfirmBtn');
    const croppedImageInput = document.getElementById('croppedImage');
    
    let cropper = null;
    let editMode = false;
    
    function toggleEdit(show) {
        editMode = show;
        if (editMode) {
            viewSection.style.display = 'none';
            editSection.style.display = 'block';
            editBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
            editBtn.className = 'btn-secondary';
            avatarUploadOverlay.classList.add('show');
        } else {
            viewSection.style.display = 'block';
            editSection.style.display = 'none';
            editBtn.innerHTML = '<i class="fas fa-pen"></i> Edit Profile';
            editBtn.className = 'btn-primary';
            avatarUploadOverlay.classList.remove('show');
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('edit');
                window.history.replaceState({}, document.title, url.toString());
            }
        }
    }
    
    editBtn.addEventListener('click', function() {
        toggleEdit(!editMode);
    });
    
    cancelBtn.addEventListener('click', function() {
        toggleEdit(false);
    });
    
    avatarUploadOverlay.addEventListener('click', function() {
        avatarFileInput.click();
    });
    
    avatarFileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                cropImage.src = ev.target.result;
                openCropModal();
            };
            reader.readAsDataURL(this.files[0]);
            this.value = '';
        }
    });
    
    function openCropModal() {
        cropModal.classList.add('active');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        cropImage.onload = function() {
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 0.8,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
            });
        };
        if (cropImage.complete) {
            cropImage.onload();
        }
    }
    
    function closeCropModal() {
        cropModal.classList.remove('active');
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }
    
    cropConfirmBtn.addEventListener('click', function() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({
            width: 300,
            height: 300,
            imageSmoothingQuality: 'high',
        });
        const dataUrl = canvas.toDataURL('image/png');
        croppedImageInput.value = dataUrl;
        
        // Update avatar preview
        if (avatarImg) {
            avatarImg.src = dataUrl;
            avatarImg.style.display = 'block';
            if (avatarInitials) avatarInitials.style.display = 'none';
        } else {
            const img = document.createElement('img');
            img.id = 'avatarImg';
            img.src = dataUrl;
            img.alt = 'Profile';
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';
            const span = avatarContainer.querySelector('.initials');
            if (span) span.style.display = 'none';
            avatarContainer.appendChild(img);
        }
        
        // Update sidebar avatar
        const sidebarImg = document.querySelector('#sidebar .w-10.h-10 img');
        const sidebarSpan = document.querySelector('#sidebar .w-10.h-10 span');
        if (sidebarImg) {
            sidebarImg.src = dataUrl;
            sidebarImg.style.display = 'block';
            if (sidebarSpan) sidebarSpan.style.display = 'none';
        } else {
            const sidebarContainer = document.querySelector('#sidebar .w-10.h-10');
            if (sidebarContainer) {
                const img = document.createElement('img');
                img.src = dataUrl;
                img.alt = 'Profile';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                const span = sidebarContainer.querySelector('span');
                if (span) span.style.display = 'none';
                sidebarContainer.appendChild(img);
            }
        }
        
        closeCropModal();
        // Submit form automatically after crop
        form.submit();
    });
    
    cropModal.addEventListener('click', function(e) {
        if (e.target === this) closeCropModal();
    });
    
    // If edit mode is activated via URL param
    <?php if($edit_mode): ?>
    toggleEdit(true);
    <?php endif; ?>
})();
</script>

</body>
</html>