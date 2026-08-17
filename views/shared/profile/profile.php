<?php
// views/profile.php - MAIN PROFILE PAGE with sections loaded dynamically

require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/config/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/helpers/SettingsHelper.php';
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

// ============================================================
// Helper functions
// ============================================================
function saveProfileCrop($user_id, $current_picture, $cropped_data) {
    $parts = explode(',', $cropped_data);
    if (count($parts) !== 2) return [false, 'Invalid image data.'];
    $image_data = base64_decode($parts[1]);
    if ($image_data === false) return [false, 'Invalid image data.'];

    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/profile/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if ($current_picture && file_exists($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $current_picture)) {
        @unlink($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $current_picture);
    }

    $new_filename = 'profile_' . $user_id . '_' . time() . '.png';
    if (file_put_contents($upload_dir . $new_filename, $image_data)) {
        return [true, 'uploads/profile/' . $new_filename];
    }
    return [false, 'Failed to save image.'];
}

// ============================================================
// POST: Update personal information
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    
    $purok_street = trim($_POST['purok_street'] ?? '');
    $barangay_id = !empty($_POST['barangay_id']) ? (int)$_POST['barangay_id'] : null;
    $non_resident_address = trim($_POST['non_resident_address'] ?? '');

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

    $profile_picture = $user['profile_picture'];
    if (!empty($_POST['cropped_image'])) {
        [$ok, $result] = saveProfileCrop($user_id, $profile_picture, $_POST['cropped_image']);
        if ($ok) {
            $profile_picture = $result;
            $_SESSION['profile_picture'] = $profile_picture;
        } else {
            $errors[] = $result;
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
                non_resident_address = :non_resident_address,
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
            ':non_resident_address' => $non_resident_address,
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
    header("Location: " . BASE_URL . "index.php?page=profile&section=personal-information");
    exit();
}

// ============================================================
// POST: Update profile photo only
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_photo'])) {
    if (!empty($_POST['cropped_image'])) {
        [$ok, $result] = saveProfileCrop($user_id, $user['profile_picture'], $_POST['cropped_image']);
        if ($ok) {
            $db->prepare("UPDATE users SET profile_picture = :pp WHERE id = :id")
               ->execute([':pp' => $result, ':id' => $user_id]);
            $_SESSION['profile_picture'] = $result;
            $_SESSION['success'] = "Profile photo updated!";
        } else {
            $_SESSION['errors'] = [$result];
        }
    } else {
        $_SESSION['errors'] = ['No image data received.'];
    }
    header("Location: " . BASE_URL . "index.php?page=profile");
    exit();
}

// ============================================================
// POST: Change password
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $csrf_ok = isset($_POST['csrf_token']) && InputSanitizer::validateCsrfToken($_POST['csrf_token']);
    if (!$csrf_ok) {
        $_SESSION['password_errors'] = ['Invalid security token. Please refresh the page and try again.'];
    } else {
        $current  = (string)($_POST['current_password'] ?? '');
        $new      = (string)($_POST['new_password'] ?? '');
        $confirm  = (string)($_POST['confirm_password'] ?? '');

        $errors = [];
        $userModel = new User($db);
        if (!$userModel->verifyPassword($user_id, $current)) {
            $errors[] = "Your current password is incorrect.";
        }
        $pwErrors = InputSanitizer::validatePassword($new);
        if ($pwErrors) {
            $errors = array_merge($errors, $pwErrors);
        }
        if ($new !== $confirm) {
            $errors[] = "New password and confirmation do not match.";
        }
        if ($new !== '' && $new === $current) {
            $errors[] = "New password must be different from your current password.";
        }

        if (empty($errors)) {
            $userModel->updatePassword($user_id, $new);
            $activity = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, status, created_at) VALUES (?, 'Change Password', ?, ?, ?, 'SUCCESS', NOW())");
            $activity->execute([
                $user_id,
                'User changed their own password from the profile page.',
                $_SERVER['REMOTE_ADDR'] ?? '',
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
            ]);
            $_SESSION['password_success'] = "Your password has been updated successfully.";
        } else {
            $_SESSION['password_errors'] = $errors;
        }
    }
    header("Location: " . BASE_URL . "index.php?page=profile&section=change-password");
    exit();
}

// ============================================================
// Determine which section to display
// ============================================================
$section = $_GET['section'] ?? '';
$valid_sections = ['personal-information', 'change-password', 'about', 'terms', 'privacy', 'faqs', 'help'];
if ($section && !in_array($section, $valid_sections)) {
    $section = ''; // treat as no section
}

// ============================================================
// Display variables
// ============================================================
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
$barangay_name = $user['barangay_name'] ?? '';
$full_name = $user['first_name'] . ' ' . $user['last_name'];
$system_name = SettingsHelper::get('system_name', 'Sierra');

// Password policy
$p_min     = (int) SettingsHelper::get('password_min_length', 8);
$p_upper   = (int) SettingsHelper::get('password_require_upper', 1);
$p_lower   = (int) SettingsHelper::get('password_require_lower', 1);
$p_number  = (int) SettingsHelper::get('password_require_number', 1);
$p_special = (int) SettingsHelper::get('password_require_special', 1);

$csrf_token = InputSanitizer::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php if (class_exists('SettingsHelper') && SettingsHelper::getLogoUrl()): ?>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(SettingsHelper::getLogoUrl()); ?>">
    <?php endif; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo $section ? ucwords(str_replace('-', ' ', $section)) : 'My Profile'; ?> - <?php echo htmlspecialchars($system_name); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    <style>
        * { font-family: 'Manrope', sans-serif; }
        body { background: #F5FBF6; overflow-x: hidden; }
        
        @media (max-width: 768px) {
            .lg\:ml-72 { margin-left: 0 !important; width: 100%; padding: 0; }
            .sidebar-mobile { position: fixed; left: -280px; transition: left 0.3s ease; z-index: 1000; }
            .sidebar-mobile.open { left: 0; }
        }
        
        .main-container { padding: 0.75rem; max-width: 1100px; margin: 0 auto; }
        @media (min-width: 480px) { .main-container { padding: 1rem; } }
        @media (min-width: 640px) { .main-container { padding: 1.5rem; } }
        @media (min-width: 768px) { .main-container { padding: 2rem; } }
        
        .page-header { margin-bottom: 0.75rem; }
        @media (min-width: 640px) { .page-header { margin-bottom: 1.25rem; } }
        .page-title { font-size: 1.15rem; }
        @media (min-width: 480px) { .page-title { font-size: 1.3rem; } }
        @media (min-width: 640px) { .page-title { font-size: 1.875rem; } }
        
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
            .btn-primary { padding: 0.625rem 1.25rem; font-size: 0.875rem; }
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3); }
        
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
            .btn-secondary { padding: 0.625rem 1.25rem; font-size: 0.875rem; }
        }
        .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }
        
        .profile-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid rgba(16, 163, 127, 0.08);
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .profile-card:hover { border-color: #10A37F; box-shadow: 0 4px 12px rgba(16, 163, 127, 0.05); }
        
        .avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #10A37F, #0D8568);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
            object-fit: cover;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(16, 163, 127, 0.2);
            cursor: pointer;
        }
        @media (min-width: 480px) {
            .avatar { width: 64px; height: 64px; font-size: 1.4rem; }
        }
        @media (min-width: 640px) {
            .avatar { width: 72px; height: 72px; font-size: 1.6rem; }
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; }
        .avatar .initials { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
        .avatar-upload-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            backdrop-filter: blur(4px);
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            font-size: 0.55rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .avatar:hover .avatar-upload-overlay { opacity: 1; }
        
        .metric-item {
            display: flex;
            flex-direction: column;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        .metric-item:last-child { border-bottom: none; }
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
            .metric-label { font-size: 0.7rem; }
            .metric-value { font-size: 1rem; }
        }
        
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
        @media (min-width: 640px) { .form-label { font-size: 0.875rem; } }
        
        /* Profile menu (landing page) */
        .profile-menu-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid #eef3f0;
            padding: 0.9rem;
        }
        @media (min-width: 480px) { .profile-menu-card { padding: 1.1rem; } }
        @media (min-width: 640px) { .profile-menu-card { padding: 1.5rem; } }

        /* Profile summary (avatar + name) compact on mobile */
        .profile-summary {
            padding-bottom: 0.85rem;
            gap: 0.6rem;
        }
        @media (min-width: 480px) { .profile-summary { padding-bottom: 1rem; gap: 0.75rem; } }
        @media (min-width: 640px) { .profile-summary { padding-bottom: 1.25rem; gap: 1rem; } }

        .profile-summary h3 { font-size: 0.85rem; }
        @media (min-width: 480px) { .profile-summary h3 { font-size: 0.95rem; } }
        @media (min-width: 640px) { .profile-summary h3 { font-size: 1rem; } }

        .profile-menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 8px;
            border-radius: 0.6rem;
            color: #1f2937;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.15s;
            border-bottom: 1px solid #f3f6f4;
        }
        @media (min-width: 480px) {
            .profile-menu-item { gap: 12px; padding: 11px 10px; font-size: 0.85rem; }
        }
        @media (min-width: 640px) {
            .profile-menu-item { gap: 14px; padding: 13px 12px; font-size: 0.9rem; }
        }
        .profile-menu-item:last-child { border-bottom: none; }
        .profile-menu-item .menu-icon {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
            border-radius: 9px;
            background: #eef4f0;
            color: #10A37F;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: all 0.15s;
        }
        @media (min-width: 480px) {
            .profile-menu-item .menu-icon { width: 36px; height: 36px; font-size: 0.9rem; border-radius: 10px; }
        }
        @media (min-width: 640px) {
            .profile-menu-item .menu-icon { width: 40px; height: 40px; font-size: 1rem; border-radius: 12px; }
        }
        .profile-menu-item:hover {
            background: #fafcfb;
            color: #111827;
        }
        .profile-menu-item:hover .menu-icon {
            background: #10A37F;
            color: #fff;
            box-shadow: 0 4px 10px rgba(16, 163, 127, 0.3);
        }
        .profile-menu-item .menu-label { flex: 1; }
        .profile-menu-item .menu-chevron { color: #d1d5db; font-size: 0.65rem; }
        @media (min-width: 640px) { .profile-menu-item .menu-chevron { font-size: 0.75rem; } }
        .profile-menu-item.logout-item { color: #b91c1c; }
        .profile-menu-item.logout-item .menu-icon { background: #fdecec; color: #b91c1c; }
        .profile-menu-item.logout-item:hover .menu-icon { background: #EF4444; color: #fff; }
        .profile-menu-group-label {
            font-size: 0.6rem;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.4rem 0.4rem 0.2rem;
        }
        @media (min-width: 640px) {
            .profile-menu-group-label { font-size: 0.68rem; padding: 0.5rem 0.5rem 0.25rem; }
        }
        
        .boxed-field {
            border: 1.5px solid #e5ece8;
            border-radius: 0.9rem;
            padding: 0.5rem 0.9rem;
            background: #fff;
            transition: all 0.2s;
        }
        .boxed-field:focus-within {
            border-color: #10A37F;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
        }
        .boxed-field .boxed-label {
            display: block;
            font-size: 0.68rem;
            font-weight: 600;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .boxed-field input,
        .boxed-field select {
            width: 100%;
            border: none;
            outline: none;
            padding: 0;
            font-size: 0.92rem;
            font-weight: 600;
            color: #1a2e1a;
            background: transparent;
        }
        .boxed-field input:read-only { color: #9ca3af; font-weight: 500; }
        
        .legal-content h4 {
            font-weight: 700;
            color: #1f2937;
            margin: 0.9rem 0 0.3rem;
            font-size: 0.9rem;
        }
        .legal-content p, .legal-content li {
            font-size: 0.85rem;
            line-height: 1.6;
            color: #4b5563;
        }
        .legal-content ul { list-style: disc; padding-left: 1.25rem; }
        .legal-content li { margin-bottom: 0.15rem; }
        
        .faq-item {
            border: 1px solid #eef3f0;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }
        .faq-question {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.85rem 1rem;
            background: #fbfefc;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: #1a2e1a;
            cursor: pointer;
            border: none;
        }
        .faq-question i { color: #10A37F; transition: transform 0.2s; flex-shrink: 0; }
        .faq-item.open .faq-question i { transform: rotate(180deg); }
        .faq-answer {
            display: none;
            padding: 0 1rem 0.85rem;
            font-size: 0.85rem;
            line-height: 1.6;
            color: #4b5563;
        }
        .faq-item.open .faq-answer { display: block; }
        
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
        .crop-modal-header h3 { font-weight: 700; color: #1a2e1a; font-size: 1rem; }
        .crop-modal-header h3 i { color: #10A37F; margin-right: 0.5rem; }
        .crop-modal-body { padding: 16px; overflow: hidden; flex: 1; min-height: 200px; }
        .crop-modal-body img { max-width: 100%; display: block; }
        .crop-modal-footer {
            padding: 16px 20px;
            border-top: 1px solid #eef3f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            flex-shrink: 0;
        }
        .crop-modal-footer button { min-height: 44px; }
        @media (max-width: 480px) {
            .crop-modal-footer { flex-direction: column; }
            .crop-modal-footer button { width: 100%; justify-content: center; }
        }
        
        .pw-check {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            color: #9ca3af;
            margin-bottom: 4px;
            transition: color 0.15s;
        }
        .pw-check.met { color: #10A37F; }
        .pw-check.met i.fa-circle { display: none; }
        .pw-check.met i.fa-check-circle { display: inline-block; }
        .pw-check i.fa-check-circle { display: none; }
        
        @media (max-width: 480px) {
            .metric-value { font-size: 0.8rem; }
            .btn-primary, .btn-secondary { font-size: 0.75rem; padding: 0.4rem 0.75rem; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/views/layouts/sidebar.php'; ?>

<!-- ===== MAIN CONTAINER ===== -->
<div class="lg:ml-72 min-h-screen">
    <div class="main-container mx-auto">
        
        
        <!-- ===== HEADER ===== -->
        <div class="page-header">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-7 h-7 md:w-8 md:h-8 bg-[#10A37F]/10 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-circle text-[#10A37F] text-xs md:text-sm"></i>
                </div>
                <span class="text-[10px] md:text-xs uppercase tracking-wider text-[#10A37F] font-semibold">My Account</span>
            </div>
            <div>
                <h1 class="page-title font-bold text-gray-800">
                    <?php echo $section ? ucwords(str_replace('-', ' ', $section)) : 'My Profile'; ?>
                </h1>
                <p class="text-gray-500 text-xs md:text-sm mt-0.5 md:mt-1">
                    <?php echo $section ? 'Manage your account details' : 'Choose a section to manage your account'; ?>
                </p>
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
        
        <?php if(isset($_SESSION['password_success'])): ?>
            <div class="mb-4 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                <div class="flex items-center gap-2">
                    <i class="fas fa-check-circle text-green-500"></i>
                    <span><?php echo $_SESSION['password_success']; unset($_SESSION['password_success']); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['password_errors']) && is_array($_SESSION['password_errors'])): ?>
            <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 rounded-xl text-red-700 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach($_SESSION['password_errors'] as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php unset($_SESSION['password_errors']); ?>
        <?php endif; ?>
        
        <!-- ============================================================ -->
        <!-- CONTENT: Either menu (no section) or section content          -->
        <!-- ============================================================ -->
        <?php if (!$section): ?>
            <!-- ===== PROFILE MENU (LANDING) ===== -->
            <div class="profile-menu-card">
                <!-- Profile summary (avatar + name) -->
                <div class="profile-summary flex flex-col items-center text-center border-b border-gray-100">
                    <div class="avatar" id="avatarContainer" title="Change profile photo">
                        <?php if ($profile_pic_url): ?>
                            <img src="<?php echo $profile_pic_url; ?>" alt="Profile" id="avatarImg">
                        <?php else: ?>
                            <span class="initials" id="avatarInitials"><?php echo $initials; ?></span>
                        <?php endif; ?>
                        <div class="avatar-upload-overlay" id="avatarUploadOverlay">
                            <i class="fas fa-camera text-sm"></i>
                            Change Photo
                        </div>
                    </div>
                    <input type="file" id="avatarFileInput" accept="image/*" style="display: none;">
                    <div>
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($full_name); ?></h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium mt-1 <?php echo $role_badge_color; ?>">
                            <i class="fas <?php echo in_array($user_type, ['admin', 'menro_staff'], true) ? 'fa-building' : ($user_type === 'barangay_personnel' ? 'fa-landmark' : 'fa-user'); ?> mr-1"></i>
                            <?php echo $role_display; ?>
                        </span>
                    </div>
                </div>
                
                <!-- Menu items -->
                <nav class="mt-2" aria-label="Profile sections">
                    <!-- Account -->
                    <div class="profile-menu-group-label">Account</div>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=personal-information">
                        <span class="menu-icon"><i class="fas fa-id-card"></i></span>
                        <span class="menu-label">Personal Information</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=change-password">
                        <span class="menu-icon"><i class="fas fa-key"></i></span>
                        <span class="menu-label">Change Password</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    
                    <!-- Legal & About -->
                    <div class="profile-menu-group-label">Legal &amp; About</div>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=about">
                        <span class="menu-icon"><i class="fas fa-info-circle"></i></span>
                        <span class="menu-label">About <?php echo htmlspecialchars($system_name); ?></span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=terms">
                        <span class="menu-icon"><i class="fas fa-file-contract"></i></span>
                        <span class="menu-label">Terms of Service</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=privacy">
                        <span class="menu-icon"><i class="fas fa-user-shield"></i></span>
                        <span class="menu-label">Privacy Notice</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    
                    <!-- Support -->
                    <div class="profile-menu-group-label">Support</div>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=faqs">
                        <span class="menu-icon"><i class="fas fa-question-circle"></i></span>
                        <span class="menu-label">FAQs</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    <a class="profile-menu-item" href="<?php echo BASE_URL; ?>index.php?page=profile&section=help">
                        <span class="menu-icon"><i class="fas fa-headset"></i></span>
                        <span class="menu-label">Help and Support</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                    
                    <!-- Session -->
                    <div class="profile-menu-group-label" style="border-top:1px solid #f0f4f1; padding-top:0.75rem; margin-top:0.5rem;">Session</div>
                    <a class="profile-menu-item logout-item" href="javascript:void(0)" onclick="window.openLogoutModal()">
                        <span class="menu-icon"><i class="fas fa-sign-out-alt"></i></span>
                        <span class="menu-label">Logout</span>
                        <i class="fas fa-chevron-right menu-chevron"></i>
                    </a>
                </nav>
            </div>
        <?php else: ?>
            <!-- ===== SECTION CONTENT (full width) ===== -->
            <div class="profile-card">
                <div class="p-5 md:p-6">
                    <!-- Back to Profile link -->
                    <div class="mb-4">
                        <a href="<?php echo BASE_URL; ?>index.php?page=profile" class="inline-flex items-center gap-2 text-gray-500 hover:text-[#10A37F] transition text-sm font-medium">
                            <i class="fas fa-arrow-left"></i> Back to Profile
                        </a>
                    </div>
                    
                    <?php
                    // Load the appropriate section content
                    switch($section) {
                        case 'personal-information':
                            include __DIR__ . '/personal_info.php';
                            break;
                        case 'change-password':
                            include __DIR__ . '/change_password.php';
                            break;
                        case 'about':
                            include __DIR__ . '/about.php';
                            break;
                        case 'terms':
                            include __DIR__ . '/terms.php';
                            break;
                        case 'privacy':
                            include __DIR__ . '/privacy.php';
                            break;
                        case 'faqs':
                            include __DIR__ . '/faqs.php';
                            break;
                        case 'help':
                            include __DIR__ . '/help.php';
                            break;
                        default:
                            // fallback to personal info
                            include __DIR__ . '/personal_info.php';
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Hidden form for profile photo upload -->
<form method="POST" action="" enctype="multipart/form-data" id="photoForm">
    <input type="hidden" name="update_photo" value="1">
    <input type="hidden" name="cropped_image" id="photoCroppedImage" value="">
</form>

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
    'use strict';
    
    // ===== AVATAR / PROFILE PHOTO FLOW =====
    var avatarContainer = document.getElementById('avatarContainer');
    var avatarFileInput = document.getElementById('avatarFileInput');
    var photoForm = document.getElementById('photoForm');
    var photoCroppedImage = document.getElementById('photoCroppedImage');
    
    if (avatarContainer && avatarFileInput) {
        avatarContainer.addEventListener('click', function() { avatarFileInput.click(); });
        avatarFileInput.addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function(ev) {
                    cropImage.src = ev.target.result;
                    openCropModal();
                };
                reader.readAsDataURL(this.files[0]);
                this.value = '';
            }
        });
    }
    
    // ===== CROP MODAL =====
    var cropModal = document.getElementById('cropModal');
    var cropImage = document.getElementById('cropImage');
    var cropConfirmBtn = document.getElementById('cropConfirmBtn');
    var cropper = null;
    
    function openCropModal() {
        cropModal.classList.add('active');
        if (cropper) { cropper.destroy(); cropper = null; }
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
                cropBoxResizable: true
            });
        };
        if (cropImage.complete) cropImage.onload();
    }
    
    window.closeCropModal = function() {
        cropModal.classList.remove('active');
        if (cropper) { cropper.destroy(); cropper = null; }
    };
    
    cropConfirmBtn.addEventListener('click', function() {
        if (!cropper) return;
        var canvas = cropper.getCroppedCanvas({ width: 300, height: 300, imageSmoothingQuality: 'high' });
        var dataUrl = canvas.toDataURL('image/png');
        photoCroppedImage.value = dataUrl;
        
        // Preview in the nav avatar
        var img = document.getElementById('avatarImg');
        if (img) {
            img.src = dataUrl;
            img.style.display = 'block';
        } else {
            var init = document.getElementById('avatarInitials');
            if (init) init.style.display = 'none';
            var newImg = document.createElement('img');
            newImg.id = 'avatarImg';
            newImg.src = dataUrl;
            newImg.alt = 'Profile';
            avatarContainer.appendChild(newImg);
        }
        // Update sidebar avatar
        var sidebarImg = document.querySelector('#sidebar .w-10.h-10 img');
        var sidebarSpan = document.querySelector('#sidebar .w-10.h-10 span');
        if (sidebarImg) {
            sidebarImg.src = dataUrl;
            sidebarImg.style.display = 'block';
            if (sidebarSpan) sidebarSpan.style.display = 'none';
        }
        
        window.closeCropModal();
        photoForm.submit();
    });
    
    cropModal.addEventListener('click', function(e) {
        if (e.target === this) window.closeCropModal();
    });
    
    // ===== PASSWORD SECTION =====
    var minLen = <?php echo $p_min; ?>;
    var pwInput = document.getElementById('newPassword');
    var confirmInput = document.getElementById('confirmPassword');
    var pwChecksBox = document.getElementById('pwChecks');
    
    function updatePwChecks() {
        if (!pwInput || !confirmInput || !pwChecksBox) return;
        var v = pwInput.value;
        var rules = {
            min: v.length >= minLen,
            upper: /[A-Z]/.test(v),
            lower: /[a-z]/.test(v),
            number: /[0-9]/.test(v),
            special: /[-!@#$%^&*()_=+{};:,<.>]/.test(v),
            match: v !== '' && v === confirmInput.value
        };
        pwChecksBox.querySelectorAll('.pw-check').forEach(function(c) {
            var rule = c.getAttribute('data-rule');
            if (rules[rule]) c.classList.add('met'); else c.classList.remove('met');
        });
    }
    if (pwInput && confirmInput && pwChecksBox) {
        pwInput.addEventListener('input', updatePwChecks);
        confirmInput.addEventListener('input', updatePwChecks);
    }
    
    window.togglePwVisibility = function(id, btn) {
        var input = document.getElementById(id);
        if (!input) return;
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="fas fa-eye"></i>';
        }
    };
    
    // ===== FAQ ACCORDION =====
    document.querySelectorAll('.faq-question').forEach(function(q) {
        q.addEventListener('click', function() {
            var item = q.closest('.faq-item');
            var wasOpen = item.classList.contains('open');
            document.querySelectorAll('.faq-item.open').forEach(function(i) { i.classList.remove('open'); });
            if (!wasOpen) item.classList.add('open');
        });
    });
    
})();
</script>

</body>
</html>