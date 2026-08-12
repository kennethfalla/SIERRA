<?php
// controllers/AnnouncementController.php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/helpers/PermissionHelper.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "index.php?page=announcements");
    exit();
}

// Check login
if (!isLoggedIn()) {
    $_SESSION['error'] = "Please login to continue.";
    header("Location: " . BASE_URL . "views/auth/login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$barangay_id = $_SESSION['barangay_id'] ?? null;

// CSRF validation
if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
    $_SESSION['error'] = "Invalid security token. Please refresh and try again.";
    header("Location: " . BASE_URL . "index.php?page=announcements");
    exit();
}

$action = $_POST['action'] ?? '';

// ============================================
// Helper functions for permissions
// ============================================
function canEditAnnouncement($announcement, $user_id, $user_role) {
    if ($user_role === 'admin') return true;
    if ($user_role === 'barangay_official' && $announcement['created_by'] == $user_id) return true;
    return false;
}

function canDeleteAnnouncement($announcement, $user_id, $user_role) {
    return canEditAnnouncement($announcement, $user_id, $user_role);
}

// ============================================
// CREATE
// ============================================
if ($action === 'create') {
    // Only admin or barangay official can create
    if (!in_array($user_role, ['admin', 'barangay_official'])) {
        $_SESSION['error'] = "You don't have permission to create announcements.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    // KILL SWITCH: announcements disabled
    if (SettingsHelper::get('enable_announcements', '1') != '1') {
        $_SESSION['error'] = "Creating announcements is currently disabled by the system administrator.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    $title = InputSanitizer::sanitizeString($_POST['title'] ?? '');
    $category = InputSanitizer::sanitizeString($_POST['category'] ?? 'General');
    $content = InputSanitizer::sanitizeRichText($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    // ============================================
    // Broadcast targeting
    //   global_public     -> pushed to the global feed (every resident + every barangay admin)
    //   localized_public  -> residents + admin of a specific barangay only
    //   internal_global   -> every barangay admin only (hidden from the public)
    //   internal_direct   -> one specific barangay admin only (hidden from all others)
    // ============================================
    $broadcast_target = $_POST['broadcast_target'] ?? 'localized_public';
    // The compose form sends the primary value 'internal' plus a secondary
    // 'admin_level' (internal_global | internal_direct). Resolve it here.
    if ($broadcast_target === 'internal') {
        $broadcast_target = ($_POST['admin_level'] ?? 'internal_global') === 'internal_direct' ? 'internal_direct' : 'internal_global';
    }
    $is_public = 0;
    $target_barangay_id = null;
    $target_admin_id = null;
    $can_manage_system = PermissionHelper::userHasPermission('can_manage_system');

    switch ($broadcast_target) {
        case 'global_public':
            // Municipality-wide public post requires System Management permission
            // (super-admin bypasses; barangay-scoped posts are unaffected).
            if (!$can_manage_system) {
                $_SESSION['error'] = "You are not permitted to post global public announcements.";
                header("Location: " . BASE_URL . "index.php?page=announcements");
                exit();
            }
            $is_public = 1;
            break;

        case 'localized_public':
            if ($user_role === 'admin') {
                $target_barangay_id = (int)($_POST['barangay_id'] ?? 0);
                if ($target_barangay_id <= 0) {
                    $_SESSION['error'] = "Please select a barangay for a localized public announcement.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
            } else {
                // Barangay official always targets their own barangay.
                $target_barangay_id = $barangay_id;
            }
            break;

        case 'internal_global':
            // No public visibility, no specific target: every barangay admin.
            break;

        case 'internal_direct':
            if ($user_role === 'admin') {
                $target_admin_id = (int)($_POST['target_admin_id'] ?? 0);
                if ($target_admin_id <= 0) {
                    $_SESSION['error'] = "Please select a specific barangay admin for a direct internal announcement.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                // Resolve the targeted admin's barangay for reference/display.
                $stmt_admin = $db->prepare("SELECT id, barangay_id FROM users WHERE id = ? AND user_type = 'barangay_personnel'");
                $stmt_admin->execute([$target_admin_id]);
                $target_admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
                if (!$target_admin) {
                    $_SESSION['error'] = "The selected barangay admin is no longer valid.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                $target_barangay_id = $target_admin['barangay_id'] ? (int)$target_admin['barangay_id'] : null;
            } else {
                // Barangay official can only direct an internal announcement to their own office.
                $target_admin_id = $user_id;
                $target_barangay_id = $barangay_id;
            }
            break;

        default:
            $_SESSION['error'] = "Invalid broadcast target.";
            header("Location: " . BASE_URL . "index.php?page=announcements");
            exit();
    }

    $created_by_role = ($user_role === 'admin') ? 'menro' : 'barangay';

    try {
        $stmt = $db->prepare("INSERT INTO announcements (title, category, content, barangay_id, created_by, created_by_role, is_public, broadcast_type, target_admin_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$title, $category, $content, $target_barangay_id, $user_id, $created_by_role, $is_public, $broadcast_target, $target_admin_id]);
        $announcement_id = $db->lastInsertId();

        // Handle image uploads
        if (isset($_FILES['images']) && is_array($_FILES['images']['name']) && $_FILES['images']['name'][0] !== '') {
            $upload_dir = BASE_PATH . 'uploads/announcements/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount && $i < 10; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $filename = uniqid() . '.' . $ext;
                        $target_path = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_path)) {
                            $stmt_img = $db->prepare("INSERT INTO announcement_images (announcement_id, image_path) VALUES (?, ?)");
                            $stmt_img->execute([$announcement_id, 'uploads/announcements/' . $filename]);
                        }
                    }
                }
            }
        }

        $_SESSION['success'] = "Announcement posted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to create announcement: " . $e->getMessage();
    }

    header("Location: " . BASE_URL . "index.php?page=announcements");
    exit();
}

// ============================================
// EDIT
// ============================================
if ($action === 'edit') {
    $announcement_id = (int)($_POST['announcement_id'] ?? 0);
    if ($announcement_id <= 0) {
        $_SESSION['error'] = "Invalid announcement ID.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    // Fetch announcement to check permissions
    $stmt = $db->prepare("SELECT created_by, barangay_id FROM announcements WHERE id = ?");
    $stmt->execute([$announcement_id]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ann) {
        $_SESSION['error'] = "Announcement not found.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }
    if (!canEditAnnouncement($ann, $user_id, $user_role)) {
        $_SESSION['error'] = "You don't have permission to edit this announcement.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    $title = InputSanitizer::sanitizeString($_POST['title'] ?? '');
    $category = InputSanitizer::sanitizeString($_POST['category'] ?? 'General');
    $content = InputSanitizer::sanitizeRichText($_POST['content'] ?? '');
    if (empty($title) || empty($content)) {
        $_SESSION['error'] = "Title and content are required.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    // Admin may re-target an announcement on edit; barangay officials keep their
    // (own-barangay) targeting untouched.
    $broadcast_sets = [];
    $broadcast_values = [];
    if ($user_role === 'admin' && isset($_POST['broadcast_target'])) {
        $broadcast_target = $_POST['broadcast_target'];
        if ($broadcast_target === 'internal') {
            $broadcast_target = ($_POST['admin_level'] ?? 'internal_global') === 'internal_direct' ? 'internal_direct' : 'internal_global';
        }
        $is_public = 0;
        $target_barangay_id = null;
        $target_admin_id = null;
        switch ($broadcast_target) {
            case 'global_public':
                if (!PermissionHelper::userHasPermission('can_manage_system')) {
                    $_SESSION['error'] = "You are not permitted to set a global public broadcast target.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                $is_public = 1;
                break;
            case 'localized_public':
                $target_barangay_id = (int)($_POST['barangay_id'] ?? 0);
                if ($target_barangay_id <= 0) {
                    $_SESSION['error'] = "Please select a barangay for a localized public announcement.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                break;
            case 'internal_global':
                break;
            case 'internal_direct':
                $target_admin_id = (int)($_POST['target_admin_id'] ?? 0);
                if ($target_admin_id <= 0) {
                    $_SESSION['error'] = "Please select a specific barangay admin for a direct internal announcement.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                $stmt_admin = $db->prepare("SELECT id, barangay_id FROM users WHERE id = ? AND user_type = 'barangay_personnel'");
                $stmt_admin->execute([$target_admin_id]);
                $target_admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
                if (!$target_admin) {
                    $_SESSION['error'] = "The selected barangay admin is no longer valid.";
                    header("Location: " . BASE_URL . "index.php?page=announcements");
                    exit();
                }
                $target_barangay_id = $target_admin['barangay_id'] ? (int)$target_admin['barangay_id'] : null;
                break;
            default:
                $_SESSION['error'] = "Invalid broadcast target.";
                header("Location: " . BASE_URL . "index.php?page=announcements");
                exit();
        }
        $broadcast_sets[] = "broadcast_type = ?";
        $broadcast_values[] = $broadcast_target;
        $broadcast_sets[] = "is_public = ?";
        $broadcast_values[] = $is_public;
        $broadcast_sets[] = "barangay_id = ?";
        $broadcast_values[] = $target_barangay_id;
        $broadcast_sets[] = "target_admin_id = ?";
        $broadcast_values[] = $target_admin_id;
    }

    try {
        // Update announcement
        $update_sets = "title = ?, category = ?, content = ?";
        $update_params = [$title, $category, $content];
        if ($broadcast_sets) {
            $update_sets .= ", " . implode(", ", $broadcast_sets);
            $update_params = array_merge($update_params, $broadcast_values);
        }
        $update_params[] = $announcement_id;
        $stmt = $db->prepare("UPDATE announcements SET $update_sets WHERE id = ?");
        $stmt->execute($update_params);

        // Handle new image uploads
        if (isset($_FILES['images']) && is_array($_FILES['images']['name']) && $_FILES['images']['name'][0] !== '') {
            $upload_dir = BASE_PATH . 'uploads/announcements/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $fileCount = count($_FILES['images']['name']);
            for ($i = 0; $i < $fileCount && $i < 10; $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $filename = uniqid() . '.' . $ext;
                        $target_path = $upload_dir . $filename;
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $target_path)) {
                            $stmt_img = $db->prepare("INSERT INTO announcement_images (announcement_id, image_path) VALUES (?, ?)");
                            $stmt_img->execute([$announcement_id, 'uploads/announcements/' . $filename]);
                        }
                    }
                }
            }
        }

        // Handle image deletions
        if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
            $delete_ids = explode(',', $_POST['delete_images']);
            foreach ($delete_ids as $img_id) {
                if (empty($img_id)) continue;
                $stmt = $db->prepare("SELECT image_path FROM announcement_images WHERE id = ? AND announcement_id = ?");
                $stmt->execute([$img_id, $announcement_id]);
                $img = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($img) {
                    $file_path = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $img['image_path'];
                    if (file_exists($file_path)) unlink($file_path);
                    $stmt = $db->prepare("DELETE FROM announcement_images WHERE id = ?");
                    $stmt->execute([$img_id]);
                }
            }
        }

        $_SESSION['success'] = "Announcement updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to update announcement: " . $e->getMessage();
    }

    header("Location: " . BASE_URL . "index.php?page=announcements");
    exit();
}

// ============================================
// DELETE
// ============================================
if ($action === 'delete') {
    $announcement_id = (int)($_POST['announcement_id'] ?? 0);
    if ($announcement_id <= 0) {
        $_SESSION['error'] = "Invalid announcement ID.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    $stmt = $db->prepare("SELECT created_by, barangay_id FROM announcements WHERE id = ?");
    $stmt->execute([$announcement_id]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ann) {
        $_SESSION['error'] = "Announcement not found.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }
    if (!canDeleteAnnouncement($ann, $user_id, $user_role)) {
        $_SESSION['error'] = "You don't have permission to delete this announcement.";
        header("Location: " . BASE_URL . "index.php?page=announcements");
        exit();
    }

    try {
        // Delete images
        $stmt = $db->prepare("SELECT image_path FROM announcement_images WHERE announcement_id = ?");
        $stmt->execute([$announcement_id]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($images as $image) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $image['image_path'];
            if (file_exists($file_path)) unlink($file_path);
        }
        $stmt = $db->prepare("DELETE FROM announcement_images WHERE announcement_id = ?");
        $stmt->execute([$announcement_id]);

        // Delete announcement
        $stmt = $db->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->execute([$announcement_id]);

        $_SESSION['success'] = "Announcement deleted successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Failed to delete announcement: " . $e->getMessage();
    }

    header("Location: " . BASE_URL . "index.php?page=announcements");
    exit();
}

// If no valid action, redirect
$_SESSION['error'] = "Invalid action.";
header("Location: " . BASE_URL . "index.php?page=announcements");
exit();
?>