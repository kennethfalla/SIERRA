<?php
// controllers/SettingsController.php - COMPLETE SETTINGS CONTROLLER
// Features: General Settings, Security, Features, Tags, Algorithm, 
// Notifications (iProg SMS), Map, Archiving, Barangays (Full CRUD), Permissions

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SecurityHelper.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/helpers/PermissionHelper.php';

// ============================================
// ENSURE USER IS LOGGED IN AND IS ADMIN
// ("System Management" permission; super-admin bypasses)
// ============================================
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin' || !PermissionHelper::userHasPermission('can_manage_system')) {
    http_response_code(403);
    die("Access Denied");
}

$user_id = $_SESSION['user_id'];
$database = new Database();
$db = $database->getConnection();

// ============================================
// SETTINGS CONTROLLER CLASS
// ============================================
class SettingsController {
    private $db;
    private $user_id;
    private $activityLog;

    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->activityLog = new ActivityLog($db);
    }

    /**
     * Main entry point for POST requests
     */
    public function update($tab) {
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid security token. Please try again.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=" . $tab);
            exit();
        }

        // Route to the appropriate handler based on tab
        $method = 'update' . ucfirst($tab);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            $_SESSION['error'] = "Invalid settings tab.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
            exit();
        }
    }

    // ================================================================
    // 1. GENERAL SETTINGS (System Name, Email, Hotline, Logo)
    // ================================================================
    private function updateGeneral() {
        $system_name = InputSanitizer::sanitizeString($_POST['system_name'] ?? 'Sierra');
        $contact_email = InputSanitizer::sanitizeEmail($_POST['contact_email'] ?? '');
        $emergency_hotline = InputSanitizer::sanitizeString($_POST['emergency_hotline'] ?? '');

        if (!empty($contact_email) && !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Please enter a valid email address.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
            exit();
        }

        SettingsHelper::set('system_name', $system_name);
        SettingsHelper::set('contact_email', $contact_email);
        SettingsHelper::set('emergency_hotline', $emergency_hotline);

        // Handle logo upload
        if (isset($_FILES['lgu_logo']) && $_FILES['lgu_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['lgu_logo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed) && $file['size'] <= 5242880) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/settings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $old_logo = SettingsHelper::get('lgu_logo');
                if ($old_logo && file_exists($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $old_logo)) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/' . $old_logo);
                }
                $new_filename = 'logo_' . time() . '.' . $ext;
                $target_path = $upload_dir . $new_filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    SettingsHelper::set('lgu_logo', 'uploads/settings/' . $new_filename);
                } else {
                    $_SESSION['error'] = "Logo upload failed.";
                    header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
                    exit();
                }
            } else {
                $_SESSION['error'] = "Invalid logo file. Allowed: JPG, PNG, GIF, WebP (max 5MB).";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
                exit();
            }
        }

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated general settings (system name, contact email, hotline" . (isset($_FILES['lgu_logo']) && $_FILES['lgu_logo']['error'] === UPLOAD_ERR_OK ? ", logo" : "") . ")", null, 'Settings');
        $_SESSION['success'] = "General settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
        exit();
    }

    // ================================================================
    // 1b. LANDING PAGE SETTINGS (Public homepage content)
    // ================================================================
    private function updateLanding() {
        // Sub-action handling for the hero media gallery (upload / select / delete)
        $sub_action = $_POST['sub_action'] ?? '';
        if ($sub_action === 'hero_gallery_upload') {
            $this->heroGalleryUpload();
            return;
        }
        if ($sub_action === 'hero_gallery_select') {
            $this->heroGallerySelect();
            return;
        }
        if ($sub_action === 'hero_gallery_delete') {
            $this->heroGalleryDelete();
            return;
        }
        if ($sub_action === 'about_gallery_upload') {
            $this->aboutGalleryUpload();
            return;
        }
        if ($sub_action === 'about_gallery_select') {
            $this->aboutGallerySelect();
            return;
        }
        if ($sub_action === 'about_gallery_delete') {
            $this->aboutGalleryDelete();
            return;
        }
        if ($sub_action === 'about_slot_upload') {
            $this->aboutSlotUpload();
            return;
        }
        if ($sub_action === 'about_slot_clear') {
            $this->aboutSlotClear();
            return;
        }

        // Single-line / short fields (strip tags, collapse whitespace)
        $single = [
            'lp_hero_badge' => 200,
            'lp_hero_headline_1' => 200,
            'lp_how_kicker' => 100,
            'lp_how_heading' => 200,
            'lp_how_step1_title' => 150,
            'lp_how_step2_title' => 150,
            'lp_how_step3_title' => 150,
            'lp_map_kicker' => 100,
            'lp_map_heading' => 200,
            'lp_stats_kicker' => 100,
            'lp_stats_heading' => 200,
            'lp_stat_barangays_label' => 100,
            'lp_stat_barangays_sub' => 200,
            'lp_stat_population_label' => 100,
            'lp_stat_population_sub' => 200,
            'lp_stat_households_label' => 100,
            'lp_stat_households_sub' => 200,
            'lp_stat_reports_label' => 100,
            'lp_stat_reports_sub' => 200,
            'lp_about_kicker' => 100,
            'lp_about_heading' => 300,
            'lp_vision_title' => 100,
            'lp_vision_tagline' => 150,
            'lp_vision_label' => 100,
            'lp_about_title' => 100,
            'lp_about_tagline' => 150,
            'lp_core_protection_title' => 100,
            'lp_core_protection_desc' => 200,
            'lp_core_service_title' => 100,
            'lp_core_service_desc' => 200,
            'lp_core_sustainability_title' => 100,
            'lp_core_sustainability_desc' => 200,
            'lp_core_partnership_title' => 100,
            'lp_core_partnership_desc' => 200,
            'lp_footer_about' => 500,
            'lp_footer_address' => 200,
        ];
        foreach ($single as $key => $maxLen) {
            SettingsHelper::set($key, InputSanitizer::sanitizeString($_POST[$key] ?? '', $maxLen));
        }

        // Multi-line / paragraph fields (strip tags but KEEP line breaks)
        $multi = [
            'lp_hero_headline_2' => 500,
            'lp_hero_subtitle_guest' => 2000,
            'lp_hero_subtitle_staff' => 2000,
            'lp_hero_subtitle_user' => 2000,
            'lp_how_intro' => 2000,
            'lp_how_step1_desc' => 1000,
            'lp_how_step2_desc' => 1000,
            'lp_how_step3_desc' => 1000,
            'lp_map_intro' => 2000,
            'lp_stats_intro' => 2000,
            'lp_about_subtitle' => 2000,
            'lp_mission_body' => 5000,
            'lp_vision_body' => 5000,
            'lp_about_body' => 5000,
        ];
        foreach ($multi as $key => $maxLen) {
            SettingsHelper::set($key, $this->cleanLandingText($_POST[$key] ?? '', $maxLen));
        }

        // Numeric stat fields
        SettingsHelper::set('lp_stat_barangays', InputSanitizer::sanitizeInt($_POST['lp_stat_barangays'] ?? 0, 0));
        SettingsHelper::set('lp_stat_population', InputSanitizer::sanitizeInt($_POST['lp_stat_population'] ?? 0, 0));
        SettingsHelper::set('lp_stat_households', InputSanitizer::sanitizeInt($_POST['lp_stat_households'] ?? 0, 0));

        // Hero background media (image | video | none)
        $hero_bg_type = $_POST['lp_hero_bg_type'] ?? 'image';
        $hero_bg_type = in_array($hero_bg_type, ['image', 'video', 'none'], true) ? $hero_bg_type : 'image';
        SettingsHelper::set('lp_hero_bg_type', $hero_bg_type);
        SettingsHelper::set('lp_hero_bg_image', $this->cleanMediaUrl($_POST['lp_hero_bg_image'] ?? ''));
        SettingsHelper::set('lp_hero_bg_video', $this->cleanMediaUrl($_POST['lp_hero_bg_video'] ?? ''));

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated landing page content", null, 'Settings');
        $_SESSION['success'] = "Landing page saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Sanitize multi-line landing text: strip all HTML/PHP tags and unsafe
     * input but preserve line breaks so the page can render them via nl2br().
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function cleanLandingText($text, $maxLength = 5000) {
        if ($text === null || $text === '') {
            return '';
        }

        $text = (string)$text;
        $text = str_replace(chr(0), '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/&[#a-zA-Z0-9]+;/', '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);

        // Normalize line endings then collapse 3+ blank lines to 2
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);

        if ($maxLength > 0 && mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = mb_substr($text, 0, $maxLength, 'UTF-8');
        }
        return trim($text);
    }

    /**
     * Sanitize a media URL/path for the hero background. Allows http(s)
     * external URLs and safe relative upload paths under uploads/.
     * @param string $value
     * @return string
     */
    private function cleanMediaUrl($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        // External URL: http/https only
        if (preg_match('#^https?://#i', $value)) {
            $filtered = filter_var($value, FILTER_SANITIZE_URL);
            return filter_var($filtered, FILTER_VALIDATE_URL) ? $filtered : '';
        }
        // Local upload: safe relative path under uploads/
        if (preg_match('#^uploads/[a-zA-Z0-9_\-/]+\.(jpg|jpeg|png|gif|webp|mp4|webm|mov|ogg|m4v)$#i', $value)) {
            return $value;
        }
        return '';
    }

    /**
     * Absolute path of the hero media gallery directory (autocreated).
     * @return string
     */
    private function heroGalleryDir() {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/settings/hero/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Allowed gallery extensions => whether it is a video.
     * @return array<string,bool>
     */
    private function heroGalleryExts() {
        return [
            'jpg' => false, 'jpeg' => false, 'png' => false, 'gif' => false, 'webp' => false,
            'mp4' => true, 'webm' => true, 'mov' => true, 'm4v' => true, 'ogg' => true,
        ];
    }

    /**
     * Handle uploading a new image/video into the hero media gallery.
     */
    private function heroGalleryUpload() {
        $dir = $this->heroGalleryDir();
        $exts = $this->heroGalleryExts();

        if (!isset($_FILES['hero_media']) || $_FILES['hero_media']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "No file selected for upload.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }
        if ($_FILES['hero_media']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Upload failed (error code " . (int)$_FILES['hero_media']['error'] . ").";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $file = $_FILES['hero_media'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($exts[$ext])) {
            $_SESSION['error'] = "Invalid media type. Allowed images: JPG, PNG, GIF, WebP. Allowed videos: MP4, WEBM, MOV, M4V, OGG.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $is_video = $exts[$ext];
        $max_size = $is_video ? 52428800 : 5242880; // 50MB video, 5MB image
        if ($file['size'] > $max_size) {
            $_SESSION['error'] = ($is_video ? "Video" : "Image") . " is too large. Max " . ($max_size / 1048576) . "MB.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $basename = 'hero_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . $basename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            // Auto-activate the freshly uploaded media as the hero background so
            // the public landing page updates immediately after the upload.
            $rel = 'uploads/settings/hero/' . $basename;
            SettingsHelper::set('lp_hero_bg_type', $is_video ? 'video' : 'image');
            if ($is_video) {
                SettingsHelper::set('lp_hero_bg_video', $rel);
            } else {
                SettingsHelper::set('lp_hero_bg_image', $rel);
            }
            SettingsHelper::clearCache();
            $this->activityLog->log($this->user_id, 'Update System Settings', "Added hero background media and set it active: " . $basename, null, 'Settings');
            $_SESSION['success'] = ($is_video ? "Video" : "Image") . " uploaded and set as the hero background.";
        } else {
            $_SESSION['error'] = "Could not move the uploaded file. Check folder permissions.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Handle selecting a gallery file as the hero background (image or video).
     */
    private function heroGallerySelect() {
        $dir = $this->heroGalleryDir();
        $file = basename((string)($_POST['hero_file'] ?? ''));
        $type = ($_POST['hero_type'] ?? 'image') === 'video' ? 'video' : 'image';
        $exts = $this->heroGalleryExts();

        if ($file === '' || !isset($exts[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
            $_SESSION['error'] = "Invalid gallery file.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $abs = $dir . $file;
        if (!is_file($abs)) {
            $_SESSION['error'] = "That file is no longer in the gallery.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $rel = 'uploads/settings/hero/' . $file;
        SettingsHelper::set('lp_hero_bg_type', $type);
        if ($type === 'video') {
            SettingsHelper::set('lp_hero_bg_video', $rel);
        } else {
            SettingsHelper::set('lp_hero_bg_image', $rel);
        }
        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Set hero background to " . $rel . " (" . $type . ")", null, 'Settings');
        $_SESSION['success'] = ($type === 'video' ? "Video" : "Image") . " background activated from the gallery.";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Handle deleting a gallery file. Clears the background setting if the
     * deleted file was the currently active image or video.
     */
    private function heroGalleryDelete() {
        $dir = $this->heroGalleryDir();
        $file = basename((string)($_POST['hero_file'] ?? ''));
        $exts = $this->heroGalleryExts();

        if ($file === '' || !isset($exts[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
            $_SESSION['error'] = "Invalid gallery file.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $abs = $dir . $file;
        if (is_file($abs) && @unlink($abs)) {
            $rel = 'uploads/settings/hero/' . $file;
            if (SettingsHelper::get('lp_hero_bg_image') === $rel) {
                SettingsHelper::set('lp_hero_bg_image', '');
            }
            if (SettingsHelper::get('lp_hero_bg_video') === $rel) {
                SettingsHelper::set('lp_hero_bg_video', '');
            }
            SettingsHelper::clearCache();
            $this->activityLog->log($this->user_id, 'Update System Settings', "Removed hero background media: " . $file, null, 'Settings');
            $_SESSION['success'] = "Media removed from the gallery.";
        } else {
            $_SESSION['error'] = "Could not delete that file.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    // ================================================================
    // ABOUT GALLERY (Mission & Vision photos)
    // ================================================================

    /**
     * Absolute path of the about image gallery directory (autocreated).
     * @return string
     */
    private function aboutGalleryDir() {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/environmental-reporting-app/uploads/settings/about/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Allowed image extensions for the about gallery (images only).
     * @return array<string,true>
     */
    private function aboutGalleryExts() {
        return ['jpg' => true, 'jpeg' => true, 'png' => true, 'gif' => true, 'webp' => true];
    }

    /**
     * Maps a gallery "target" to the system_settings key it controls.
     * @return array<string,string>
     */
    private function aboutGallerySlots() {
        return [
            'mission_main'  => 'lp_mission_image_main',
            'mission_inset' => 'lp_mission_image_inset',
            'vision_main'   => 'lp_vision_image',
        ];
    }

    /**
     * Handle uploading a new image into the about gallery.
     */
    private function aboutGalleryUpload() {
        $dir = $this->aboutGalleryDir();
        $exts = $this->aboutGalleryExts();

        if (!isset($_FILES['about_media']) || $_FILES['about_media']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "No file selected for upload.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }
        if ($_FILES['about_media']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Upload failed (error code " . (int)$_FILES['about_media']['error'] . ").";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $file = $_FILES['about_media'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($exts[$ext])) {
            $_SESSION['error'] = "Invalid image type. Allowed: JPG, PNG, GIF, WebP.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        if ($file['size'] > 5242880) { // 5MB
            $_SESSION['error'] = "Image is too large. Max 5MB.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $basename = 'about_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . $basename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            SettingsHelper::clearCache();
            $this->activityLog->log($this->user_id, 'Update System Settings', "Added about/mission-vision photo: " . $basename, null, 'Settings');
            $_SESSION['success'] = "Photo uploaded. Use the buttons on it to assign it to Mission or Vision.";
        } else {
            $_SESSION['error'] = "Could not move the uploaded file. Check folder permissions.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Assign a gallery image to the Mission main, Mission inset, or Vision slot.
     */
    private function aboutGallerySelect() {
        $dir = $this->aboutGalleryDir();
        $file = basename((string)($_POST['about_file'] ?? ''));
        $target = (string)($_POST['about_target'] ?? '');
        $slots = $this->aboutGallerySlots();
        $exts = $this->aboutGalleryExts();

        if ($file === '' || $target === '' || !isset($slots[$target]) || !isset($exts[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
            $_SESSION['error'] = "Invalid gallery selection.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $abs = $dir . $file;
        if (!is_file($abs)) {
            $_SESSION['error'] = "That photo is no longer in the gallery.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $rel = 'uploads/settings/about/' . $file;
        $label = [
            'mission_main'  => 'Mission',
            'mission_inset' => 'Mission Inset',
            'vision_main'   => 'Vision',
        ][$target];
        SettingsHelper::set($slots[$target], $rel);
        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Set $label photo to " . $rel, null, 'Settings');
        $_SESSION['success'] = "$label photo set successfully.";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Handle deleting an about gallery image. Clears any slot pointing to it.
     */
    private function aboutGalleryDelete() {
        $dir = $this->aboutGalleryDir();
        $file = basename((string)($_POST['about_file'] ?? ''));
        $slots = $this->aboutGallerySlots();
        $exts = $this->aboutGalleryExts();

        if ($file === '' || !isset($exts[strtolower(pathinfo($file, PATHINFO_EXTENSION))])) {
            $_SESSION['error'] = "Invalid gallery file.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $abs = $dir . $file;
        if (is_file($abs) && @unlink($abs)) {
            $rel = 'uploads/settings/about/' . $file;
            foreach ($slots as $slot => $key) {
                if (SettingsHelper::get($key) === $rel) {
                    SettingsHelper::set($key, '');
                }
            }
            SettingsHelper::clearCache();
            $this->activityLog->log($this->user_id, 'Update System Settings', "Removed about/mission-vision photo: " . $file, null, 'Settings');
            $_SESSION['success'] = "Photo removed from the gallery.";
        } else {
            $_SESSION['error'] = "Could not delete that file.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Upload an image directly into a designated slot (mission_main,
     * mission_inset, or vision_main) and assign it right away — no need to
     * pick from a gallery afterwards.
     */
    private function aboutSlotUpload() {
        $dir = $this->aboutGalleryDir();
        $exts = $this->aboutGalleryExts();
        $slots = $this->aboutGallerySlots();
        $slot = (string)($_POST['about_slot'] ?? '');

        if ($slot === '' || !isset($slots[$slot])) {
            $_SESSION['error'] = "Invalid image slot.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }
        if (!isset($_FILES['about_media']) || $_FILES['about_media']['error'] === UPLOAD_ERR_NO_FILE) {
            $_SESSION['error'] = "No file selected for upload.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }
        if ($_FILES['about_media']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = "Upload failed (error code " . (int)$_FILES['about_media']['error'] . ").";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $file = $_FILES['about_media'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($exts[$ext])) {
            $_SESSION['error'] = "Invalid image type. Allowed: JPG, PNG, GIF, WebP.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }
        if ($file['size'] > 5242880) { // 5MB
            $_SESSION['error'] = "Image is too large. Max 5MB.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        $basename = 'about_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . $basename;
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $rel = 'uploads/settings/about/' . $basename;
            SettingsHelper::set($slots[$slot], $rel);
            SettingsHelper::clearCache();
            $label = ['mission_main' => 'Mission', 'mission_inset' => 'Mission Inset', 'vision_main' => 'Vision'][$slot];
            $this->activityLog->log($this->user_id, 'Update System Settings', "Uploaded and assigned $label image: " . $basename, null, 'Settings');
            $_SESSION['success'] = "$label image uploaded and set.";
        } else {
            $_SESSION['error'] = "Could not move the uploaded file. Check folder permissions.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    /**
     * Clear the image assigned to a designated slot (keeps the file on disk,
     * just unlinks it from the landing page so the default graphic shows).
     */
    private function aboutSlotClear() {
        $slots = $this->aboutGallerySlots();
        $slot = (string)($_POST['about_slot'] ?? '');

        if ($slot === '' || !isset($slots[$slot])) {
            $_SESSION['error'] = "Invalid image slot.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
            exit();
        }

        SettingsHelper::set($slots[$slot], '');
        SettingsHelper::clearCache();
        $label = ['mission_main' => 'Mission', 'mission_inset' => 'Mission Inset', 'vision_main' => 'Vision'][$slot];
        $this->activityLog->log($this->user_id, 'Update System Settings', "Removed $label image", null, 'Settings');
        $_SESSION['success'] = "$label image removed.";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=landing");
        exit();
    }

    // ================================================================
    // 2. SECURITY SETTINGS (Password Policies, Lockout)
    // ================================================================
    private function updateSecurity() {
        $min_length = (int)($_POST['password_min_length'] ?? 8);
        $max_attempts = (int)($_POST['max_login_attempts'] ?? 5);
        $lockout_duration = (int)($_POST['lockout_duration_minutes'] ?? 30);

        // Clamp values
        $min_length = max(6, min(20, $min_length));
        $max_attempts = max(3, min(10, $max_attempts));
        $lockout_duration = max(5, min(1440, $lockout_duration));

        SettingsHelper::set('password_min_length', $min_length);
        SettingsHelper::set('max_login_attempts', $max_attempts);
        SettingsHelper::set('lockout_duration_minutes', $lockout_duration);

        // Password requirements (checkboxes)
        $requirements = ['require_upper', 'require_lower', 'require_number', 'require_special'];
        foreach ($requirements as $req) {
            $value = isset($_POST['password_' . $req]) ? 1 : 0;
            SettingsHelper::set('password_' . $req, $value);
        }

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated security settings (password policy and login lockout)", null, 'Settings');
        $_SESSION['success'] = "Security settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=security");
        exit();
    }

    // ================================================================
    // 3. FEATURE TOGGLES (MASTER KILL SWITCHES)
    // ================================================================
    private function updateFeatures() {
        // The form submits a list of toggle keys via feature_keys[]; each key's
        // "on" state is derived from whether the checkbox was present. This way
        // unchecking a toggle saves 0, while keys not shown on the form are
        // never modified.
        $keys = isset($_POST['feature_keys']) && is_array($_POST['feature_keys'])
            ? array_map('strval', $_POST['feature_keys'])
            : [];

        // Whitelist against known toggles (defense in depth)
        $allowed = [
            'maintenance_mode',
            'enable_public_registration',
            'show_heatmap',
            'allow_citizen_cancellations',
            'allow_edit_pending_reports',
            'enable_escalation',
            'enable_notifications',
            'enable_report_submission',
            'enable_report_support',
            'enable_announcements',
        ];

        if (empty($keys)) {
            $_SESSION['error'] = "No feature toggles were submitted. Nothing was changed.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=features");
            exit();
        }

        foreach ($keys as $feature) {
            if (!in_array($feature, $allowed, true)) {
                continue;
            }
            $value = isset($_POST[$feature]) ? 1 : 0;
            SettingsHelper::set($feature, $value);
        }
        SettingsHelper::clearCache();

        $maintenance = SettingsHelper::get('maintenance_mode') == 1;
        $this->activityLog->log(
            $this->user_id,
            'Update System Settings',
            "Updated feature kill switches (maintenance_mode=" . ($maintenance ? 'ON' : 'OFF') . ", " . count($keys) . " toggle(s))",
            null,
            'Settings'
        );
        $_SESSION['success'] = "Kill switch settings saved successfully!" . ($maintenance ? " Maintenance mode is now ACTIVE." : "");
        header("Location: " . BASE_URL . "index.php?page=settings&tab=features");
        exit();
    }

    // ================================================================
    // 4. CUSTOM TAGS (Create, Edit, Delete)
    // ================================================================
    private function updateTags() {
        $sub_action = $_POST['sub_action'] ?? '';
        if ($sub_action === 'add') {
            $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
            $color = InputSanitizer::sanitizeString($_POST['color'] ?? '#6B7280');
            if (empty($name)) {
                $_SESSION['error'] = "Tag name cannot be empty.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=tags");
                exit();
            }
            $stmt = $this->db->prepare("INSERT INTO custom_tags (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            $this->activityLog->log($this->user_id, 'Create Tag', "Created tag: $name", null, 'Settings');
            $_SESSION['success'] = "Tag added successfully!";
        } elseif ($sub_action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $this->db->prepare("DELETE FROM custom_tags WHERE id = ?");
                $stmt->execute([$id]);
                $this->activityLog->log($this->user_id, 'Delete Tag', "Deleted tag #$id", null, 'Settings');
                $_SESSION['success'] = "Tag deleted successfully!";
            } else {
                $_SESSION['error'] = "Invalid tag ID.";
            }
        } elseif ($sub_action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = InputSanitizer::sanitizeString($_POST['name'] ?? '');
            $color = InputSanitizer::sanitizeString($_POST['color'] ?? '#6B7280');
            if ($id > 0 && !empty($name)) {
                $stmt = $this->db->prepare("UPDATE custom_tags SET name = ?, color = ? WHERE id = ?");
                $stmt->execute([$name, $color, $id]);
                $this->activityLog->log($this->user_id, 'Update Tag', "Updated tag #$id: $name", null, 'Settings');
                $_SESSION['success'] = "Tag updated successfully!";
            } else {
                $_SESSION['error'] = "Invalid tag data.";
            }
        } else {
            $_SESSION['error'] = "Invalid tag action.";
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=tags");
        exit();
    }

    // ================================================================
    // 5. SEVERITY ALGORITHM TUNER
    // ================================================================
    private function updateAlgorithm() {
        // Impact modifier points
        $impact_0 = (int)($_POST['impact_modifier_0'] ?? 0);
        $impact_2 = (int)($_POST['impact_modifier_2'] ?? 2);
        $impact_4 = (int)($_POST['impact_modifier_4'] ?? 4);
        SettingsHelper::set('impact_modifier_0', $impact_0);
        SettingsHelper::set('impact_modifier_2', $impact_2);
        SettingsHelper::set('impact_modifier_4', $impact_4);

        // Density points
        $density_0 = (int)($_POST['density_points_0'] ?? 0);
        $density_2 = (int)($_POST['density_points_2'] ?? 2);
        $density_4 = (int)($_POST['density_points_4'] ?? 4);
        $density_6 = (int)($_POST['density_points_6'] ?? 6);
        SettingsHelper::set('density_points_0', $density_0);
        SettingsHelper::set('density_points_2', $density_2);
        SettingsHelper::set('density_points_4', $density_4);
        SettingsHelper::set('density_points_6', $density_6);

        // Clustering radius (also used in map settings)
        $radius = (int)($_POST['clustering_radius_meters'] ?? 50);
        SettingsHelper::set('clustering_radius_meters', max(10, min(200, $radius)));

        // Critical threshold (score at/above which a report is flagged CRITICAL / Red)
        $critical_threshold = (int)($_POST['critical_threshold_score'] ?? 15);
        SettingsHelper::set('critical_threshold_score', max(1, min(100, $critical_threshold)));

        // Verification / upvote bonus
        $points_per_upvote = (int)($_POST['verification_points_per_upvote'] ?? 1);
        $max_verification_points = (int)($_POST['verification_max_points'] ?? 5);
        SettingsHelper::set('verification_points_per_upvote', max(0, min(10, $points_per_upvote)));
        SettingsHelper::set('verification_max_points', max(0, min(20, $max_verification_points)));

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated severity algorithm tuner", null, 'Settings');
        $_SESSION['success'] = "Algorithm settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=algorithm");
        exit();
    }

    // ================================================================
    // 5b. KPI & INSIGHTS CONFIGURATION (Insight Engine targets)
    // ================================================================
    private function updateKpi() {
        // Resolution Rate Target (%)
        $resolution_rate_target = (float)($_POST['kpi_resolution_rate_target'] ?? 60);
        SettingsHelper::set('kpi_resolution_rate_target', max(0, min(100, $resolution_rate_target)));

        // Municipal SLA - target max response time (hours)
        $sla_response_hours = (float)($_POST['kpi_sla_response_hours'] ?? 48);
        SettingsHelper::set('kpi_sla_response_hours', max(1, min(720, $sla_response_hours)));

        // Surge Alert Threshold - category spike warning (%)
        $surge_alert_threshold = (float)($_POST['kpi_surge_alert_threshold'] ?? 25);
        SettingsHelper::set('kpi_surge_alert_threshold', max(0, min(100, $surge_alert_threshold)));

        // Hotspot Definition Radius - repeat offender grouping (meters)
        $hotspot_radius_meters = (float)($_POST['kpi_hotspot_radius_meters'] ?? 10);
        SettingsHelper::set('kpi_hotspot_radius_meters', max(1, min(500, $hotspot_radius_meters)));

        // Critical Reports Alert Threshold - max acceptable critical share (%)
        $critical_reports_pct = (float)($_POST['kpi_critical_reports_pct'] ?? 30);
        SettingsHelper::set('kpi_critical_reports_pct', max(0, min(100, $critical_reports_pct)));

        // Demographic Engagement Threshold - min major-group share (%)
        $demographic_threshold = (float)($_POST['kpi_demographic_threshold'] ?? 10);
        SettingsHelper::set('kpi_demographic_threshold', max(1, min(100, $demographic_threshold)));

        // Repeat Offender Definition - min distinct reports + rolling window (days)
        $repeat_min_reports = (float)($_POST['kpi_repeat_min_reports'] ?? 3);
        SettingsHelper::set('kpi_repeat_min_reports', max(2, min(50, $repeat_min_reports)));
        $repeat_window_days = (float)($_POST['kpi_repeat_window_days'] ?? 30);
        SettingsHelper::set('kpi_repeat_window_days', max(7, min(365, $repeat_window_days)));

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated KPI & Insights configuration (resolution rate, SLA, surge alert, hotspot radius, critical share, demographic engagement, repeat offender definition)", null, 'Settings');
        $_SESSION['success'] = "KPI &amp; Insights settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=kpi");
        exit();
    }

    // ================================================================
    // 6. NOTIFICATION TEMPLATES + SMS GATEWAY SETTINGS (iProg Only)
    // ================================================================
    private function updateNotifications() {
        // ============================================
        // 6a. EMAIL/SMS TEMPLATES
        // ============================================
        $templates = [
            'template_submitted',
            'template_status_update',
            'template_resolved',
            'template_escalated',
            'template_staff_account_created'
        ];

        foreach ($templates as $key) {
            $value = $_POST[$key] ?? '';
            SettingsHelper::set($key, $value);
        }

        // ============================================
        // 6b. SMS GATEWAY SETTINGS (iProg Only)
        // ============================================
        $sms_settings = [
            'enable_sms_notifications' => isset($_POST['enable_sms_notifications']) ? 1 : 0,
            'sms_sender_name' => InputSanitizer::sanitizeString($_POST['sms_sender_name'] ?? 'SierraLGU', 11),
            'iprog_api_key' => trim($_POST['iprog_api_key'] ?? ''),
            'iprog_sender_id' => trim($_POST['iprog_sender_id'] ?? ''),
            'iprog_base_url' => trim($_POST['iprog_base_url'] ?? 'https://sms.iprogtech.com/api/v1/sms_messages'),
        ];

        foreach ($sms_settings as $key => $value) {
            SettingsHelper::set($key, $value);
        }

        SettingsHelper::clearCache();

        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated notification templates and SMS gateway settings", null, 'Settings');
        $_SESSION['success'] = "Notification templates and SMS settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=notifications");
        exit();
    }

    // ================================================================
    // 7. MAP SETTINGS (Clustering Radius)
    // ================================================================
    private function updateMap() {
        // Barangay boundary GeoJSON management uses its own sub-action form.
        if (isset($_POST['sub_action']) && $_POST['sub_action'] === 'save_barangay_boundaries') {
            $this->updateBarangayBoundaries();
        }

        // Clustering radius (10-200m)
        $radius = (int)($_POST['clustering_radius_meters'] ?? 50);
        SettingsHelper::set('clustering_radius_meters', max(10, min(200, $radius)));

        // Default map center (San Isidro) - validate as real coordinates,
        // falling back to the current saved value if the input is malformed.
        $current = SettingsHelper::getMapSettings();

        $lat = filter_var($_POST['map_default_lat'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lat < -90 || $lat > 90) {
            $lat = $current['default_lat'];
        }
        SettingsHelper::set('map_default_lat', $lat);

        $lng = filter_var($_POST['map_default_lng'] ?? '', FILTER_VALIDATE_FLOAT);
        if ($lng === false || $lng < -180 || $lng > 180) {
            $lng = $current['default_lng'];
        }
        SettingsHelper::set('map_default_lng', $lng);

        // Default zoom level (1-19)
        $zoom = (int)($_POST['map_default_zoom'] ?? 14);
        SettingsHelper::set('map_default_zoom', max(1, min(19, $zoom)));

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated map settings (clustering radius, default center, zoom)", null, 'Settings');
        $_SESSION['success'] = "Map settings saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=map");
        exit();
    }

    // ================================================================
    // 7b. BARANGAY BOUNDARY GeoJSON MANAGEMENT
    // ================================================================
    private function updateBarangayBoundaries() {
        $barangay_dir = BASE_PATH . 'geojson/barangay/';
        if (!is_dir($barangay_dir)) {
            mkdir($barangay_dir, 0755, true);
        }

        $barangay_ids = $_POST['barangay_id'] ?? [];
        if (!is_array($barangay_ids) || count($barangay_ids) === 0) {
            $_SESSION['error'] = "No barangay boundaries were selected.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=map");
            exit();
        }

        $errors = [];
        $saved = [];

        // Index existing boundary files by declared barangay name so overwrites
        // target the correct file (e.g. "Santo Cristo" -> sto-cristo.geojson).
        $boundary_files_by_name = [];
        foreach (glob($barangay_dir . '*.geojson') as $candidate) {
            $base = basename($candidate);
            if ($base === 'san-isidro.barangay.geojson') continue;
            $decoded = json_decode(file_get_contents($candidate), true);
            if (!is_array($decoded)) continue;
            foreach (($decoded['features'] ?? []) as $feat) {
                $declared = $feat['properties']['barangay_name'] ?? $feat['properties']['name'] ?? '';
                if ($declared !== '') {
                    $key = strtolower(preg_replace('/[^a-z0-9]+/', '', $declared));
                    $boundary_files_by_name[$key] = $candidate;
                }
            }
        }

        foreach ($barangay_ids as $index => $barangay_id) {
            $barangay_id = (int)$barangay_id;
            if ($barangay_id <= 0) continue;

            $stmt = $this->db->prepare("SELECT name FROM barangays WHERE id = ?");
            $stmt->execute([$barangay_id]);
            $barangay = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$barangay) continue;
            $barangay_name = $barangay['name'];

            // Use the matching existing file when present, otherwise the slug.
            $name_key = strtolower(preg_replace('/[^a-z0-9]+/', '', trim($barangay_name)));
            $existing_path = $boundary_files_by_name[$name_key] ?? null;
            $slug = strtolower(trim($barangay_name));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
            $slug = trim($slug, '-');
            $file_name = $existing_path ? basename($existing_path) : $slug . '.geojson';

            $raw_content = null;

            // 1) Uploaded GeoJSON file takes precedence.
            if (isset($_FILES['barangay_geojson_file']['name'][$index]) && $_FILES['barangay_geojson_file']['error'][$index] === UPLOAD_ERR_OK && !empty($_FILES['barangay_geojson_file']['name'][$index])) {
                $raw_content = file_get_contents($_FILES['barangay_geojson_file']['tmp_name'][$index]);
            }
            // 2) Otherwise use the inline textarea content if it changed.
            elseif (isset($_POST['barangay_geojson'][$index]) && trim($_POST['barangay_geojson'][$index]) !== '') {
                $raw_content = $_POST['barangay_geojson'][$index];
            }

            if ($raw_content === null) {
                continue; // untouched row
            }

            // Validate it is a real GeoJSON FeatureCollection with polygon geometry.
            $decoded = json_decode($raw_content, true);
            $valid = false;
            if (is_array($decoded) && ($decoded['type'] ?? '') === 'FeatureCollection' && isset($decoded['features']) && is_array($decoded['features'])) {
                foreach ($decoded['features'] as $feature) {
                    $geom_type = $feature['geometry']['type'] ?? '';
                    if ($geom_type === 'Polygon' || $geom_type === 'MultiPolygon') {
                        $valid = true;
                        break;
                    }
                }
            }

            if (!$valid) {
                $errors[] = "Invalid GeoJSON for $barangay_name. Must be a FeatureCollection containing Polygon or MultiPolygon geometry.";
                continue;
            }

            // Normalize the barangay name inside properties so app detection still works.
            foreach ($decoded['features'] as &$feature) {
                if (!isset($feature['properties']) || !is_array($feature['properties'])) {
                    $feature['properties'] = [];
                }
                $feature['properties']['name'] = $barangay_name;
                $feature['properties']['barangay_name'] = $barangay_name;
            }
            unset($feature);

            $json_out = json_encode($decoded);
            if ($json_out === false) {
                $errors[] = "Could not encode GeoJSON for $barangay_name.";
                continue;
            }

            if (file_put_contents($barangay_dir . $file_name, $json_out) !== false) {
                $saved[] = $barangay_name;
            } else {
                $errors[] = "Could not write boundary file for $barangay_name.";
            }
        }

        if (count($saved) > 0) {
            $this->activityLog->log($this->user_id, 'Update System Settings', "Updated barangay boundary GeoJSON: " . implode(', ', $saved), null, 'Settings');
            $_SESSION['success'] = "Barangay boundaries saved successfully: " . implode(', ', $saved) . ".";
        } elseif (count($errors) > 0) {
            $_SESSION['error'] = "Barangay boundaries were not updated.";
        } else {
            $_SESSION['success'] = "No changes were made to barangay boundaries.";
        }
        if (count($errors) > 0) {
            $message = "Failed: " . implode(' ', array_slice($errors, 0, 3));
            if (isset($_SESSION['success'])) {
                $_SESSION['success'] .= ' ' . $message;
            } else {
                $_SESSION['error'] = $message;
            }
        }
        header("Location: " . BASE_URL . "index.php?page=settings&tab=map");
        exit();
    }

    // ================================================================
    // 8. AUTO-ARCHIVING RULES
    // ================================================================
    private function updateArchiving() {
        $sub_action = $_POST['sub_action'] ?? 'save_rules';

        if ($sub_action === 'run_archive') {
            $this->runManualArchive();
            return;
        }

        if ($sub_action === 'restore') {
            $this->restoreArchivedItem();
            return;
        }

        if ($sub_action === 'export') {
            $this->exportArchiveBackup();
            return;
        }

        // ---- Default: save retention rules ----
        $resolved_days = (int)($_POST['archive_after_days'] ?? 30);
        $rejected_days = (int)($_POST['archive_rejected_days'] ?? 60);
        SettingsHelper::set('archive_after_days', max(0, min(365, $resolved_days)));
        SettingsHelper::set('archive_rejected_days', max(0, min(365, $rejected_days)));

        // Toggle: when enabled, generate a cryptographically random cron
        // secret on first activation so only holders of the URL can trigger
        // the archive job over HTTP. CLI invocation never needs the key.
        $enabled = !empty($_POST['archive_cron_enabled']);
        $secret  = SettingsHelper::get('archive_cron_secret', '');
        if ($enabled && $secret === '') {
            $secret = bin2hex(random_bytes(32));
            SettingsHelper::set('archive_cron_secret', $secret);
        }
        SettingsHelper::set('archive_cron_enabled', $enabled ? 1 : 0);

        SettingsHelper::clearCache();
        $this->activityLog->log($this->user_id, 'Update System Settings', "Updated archiving rules (resolved: $resolved_days days, rejected/spam: $rejected_days days, cron " . ($enabled ? 'enabled' : 'disabled') . ")", null, 'Settings');
        $_SESSION['success'] = "Archiving rules saved successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
        exit();
    }

    /**
     * Run the archiving job immediately (manual trigger). Mirrors the
     * cron/archive_reports.php logic so results are identical whether it is
     * fired by the scheduler or by the "Run Manual Archive Now" button.
     */
    private function runManualArchive() {
        $parts = [];

        $resolvedDays = max(0, (int)SettingsHelper::get('archive_after_days', 30));
        $rejectedDays = max(0, (int)SettingsHelper::get('archive_rejected_days', 60));

        try {
            // 1. Resolved reports (soft archive)
            if ($resolvedDays > 0) {
                $resolved = $this->db->prepare("
                    UPDATE reports
                    SET is_archived = 1, archived_at = NOW(), archived_reason = 'resolved'
                    WHERE status = 'resolved' AND is_archived = 0
                      AND COALESCE(resolved_at, updated_at, created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
                ");
                $resolved->execute([':days' => $resolvedDays]);
                $parts[] = ((int)$resolved->rowCount()) . " resolved report(s)";
            } else {
                $parts[] = "0 resolved report(s)";
            }

            // 2. Rejected / spam (soft archive, then hard purge)
            if ($rejectedDays > 0) {
                $rejected = $this->db->prepare("
                    UPDATE reports
                    SET is_archived = 1, archived_at = NOW(), archived_reason = 'rejected'
                    WHERE status = 'rejected' AND is_archived = 0
                      AND COALESCE(rejected_at, updated_at, created_at) < DATE_SUB(NOW(), INTERVAL :days DAY)
                ");
                $rejected->execute([':days' => $rejectedDays]);
                $rejectedCount = (int)$rejected->rowCount();

                $stale = $this->db->prepare("
                    SELECT id, latitude, longitude FROM reports
                    WHERE status = 'rejected' AND is_archived = 1
                      AND archived_at IS NOT NULL
                      AND archived_at < DATE_SUB(NOW(), INTERVAL :days DAY)
                ");
                $stale->execute([':days' => $rejectedDays]);
                $purgeCount = 0;
                foreach ($stale->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $rid = (int)$row['id'];
                    foreach (['report_images', 'resolution_evidence', 'report_notes', 'escalations', 'notifications', 'report_verifications'] as $child) {
                        $this->db->prepare("DELETE FROM `$child` WHERE report_id = ?")->execute([$rid]);
                    }
                    $this->db->prepare("DELETE FROM reports WHERE id = ?")->execute([$rid]);
                    $purgeCount++;
                    if (function_exists('recalcNearbyReports') && !empty($row['latitude']) && !empty($row['longitude'])) {
                        recalcNearbyReports($this->db, (float)$row['latitude'], (float)$row['longitude']);
                    }
                }
                $parts[] = "$rejectedCount rejected report(s) archived, $purgeCount permanently purged";
            } else {
                $parts[] = "0 rejected report(s)";
            }

            // 3. Expired announcements (soft archive)
            $announcements = $this->db->prepare("
                UPDATE announcements
                SET is_archived = 1, archived_at = NOW()
                WHERE is_archived = 0 AND expires_at IS NOT NULL AND expires_at < NOW()
            ");
            $announcements->execute();
            $parts[] = ((int)$announcements->rowCount()) . " expired announcement(s)";

            $summary = implode(', ', $parts);
            $this->activityLog->log($this->user_id, 'Run Manual Archive', "Manual archive run: $summary", null, 'Settings');
            $_SESSION['success'] = "Manual archive run completed: $summary.";
        } catch (Throwable $e) {
            error_log("[Archive Manual] Failed: " . $e->getMessage());
            $_SESSION['error'] = "Manual archive run failed. Check the server log.";
        }

        header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
        exit();
    }

    /**
     * Restore an archived report or announcement back to the active system.
     */
    private function restoreArchivedItem() {
        $type = $_POST['archive_type'] ?? 'report';
        $id = (int)($_POST['archive_id'] ?? 0);

        if ($id <= 0) {
            $_SESSION['error'] = "Invalid archive item ID.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
            exit();
        }

        if ($type === 'announcement') {
            $stmt = $this->db->prepare("UPDATE announcements SET is_archived = 0, archived_at = NULL WHERE id = ? AND is_archived = 1");
            $label = "announcement #$id";
        } else {
            $stmt = $this->db->prepare("UPDATE reports SET is_archived = 0, archived_at = NULL, archived_reason = NULL WHERE id = ? AND is_archived = 1");
            $label = "report #$id";
        }
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            $this->activityLog->log($this->user_id, 'Restore Archived Item', "Restored $label to the active system", null, 'Settings');
            $_SESSION['success'] = ucfirst($type) . " #$id restored to the active system.";
        } else {
            $_SESSION['error'] = "No archived $type found with ID #$id.";
        }

        header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
        exit();
    }

    /**
     * Stream a backup of all archived data as CSV (default) or SQL.
     */
    private function exportArchiveBackup() {
        $format = $_POST['export_format'] ?? 'csv';
        $format = ($format === 'sql') ? 'sql' : 'csv';
        $stamp = date('Y-m-d_His');

        try {
            if ($format === 'csv') {
                $this->streamArchiveCsv($stamp);
            } else {
                $this->streamArchiveSql($stamp);
            }
        } catch (Throwable $e) {
            error_log("[Archive Export] Failed: " . $e->getMessage());
            $_SESSION['error'] = "Archive export failed. Check the server log.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=archiving");
            exit();
        }
    }

    private function streamArchiveCsv($stamp) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="archive_backup_' . $stamp . '.csv"');
        $out = fopen('php://output', 'w');

        // Reports
        fputcsv($out, ['archive_id', 'source_type', 'original_id', 'title', 'category', 'barangay', 'status', 'date_resolved_or_rejected', 'archived_at']);
        $reports = $this->db->query("
            SELECT r.id AS archive_id, 'report' AS source_type, r.id AS original_id, r.title, c.name AS category,
                   b.name AS barangay, r.status,
                   COALESCE(r.resolved_at, r.rejected_at) AS closed_at, r.archived_at
            FROM reports r
            LEFT JOIN categories c ON c.id = r.category_id
            LEFT JOIN barangays b ON b.id = r.barangay_id
            WHERE r.is_archived = 1
            ORDER BY r.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($reports as $row) {
            fputcsv($out, [
                $row['archive_id'], $row['source_type'], $row['original_id'], $row['title'],
                $row['category'], $row['barangay'], $row['status'], $row['closed_at'], $row['archived_at']
            ]);
        }

        // Announcements
        $announcements = $this->db->query("
            SELECT a.id AS archive_id, 'announcement' AS source_type, a.id AS original_id, a.title, a.category,
                   b.name AS barangay, 'expired' AS status, a.expires_at AS closed_at, a.archived_at
            FROM announcements a
            LEFT JOIN barangays b ON b.id = a.barangay_id
            WHERE a.is_archived = 1
            ORDER BY a.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($announcements as $row) {
            fputcsv($out, [
                $row['archive_id'], $row['source_type'], $row['original_id'], $row['title'],
                $row['category'], $row['barangay'], $row['status'], $row['closed_at'], $row['archived_at']
            ]);
        }

        fclose($out);
        exit();
    }

    private function streamArchiveSql($stamp) {
        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="archive_backup_' . $stamp . '.sql"');
        $lines = [];
        $lines[] = "-- Sierra LGU Environmental Reporting - Archive Backup";
        $lines[] = "-- Generated: " . date('Y-m-d H:i:s');
        $lines[] = "SET NAMES utf8mb4;";
        $lines[] = "";

        $reports = $this->db->query("
            SELECT r.*, c.name AS category_name FROM reports r
            LEFT JOIN categories c ON c.id = r.category_id
            WHERE r.is_archived = 1
        ")->fetchAll(PDO::FETCH_ASSOC);
        if (count($reports) > 0) {
            $lines[] = "-- Archived reports (" . count($reports) . ")";
            foreach ($reports as $r) {
                $cols = [];
                $vals = [];
                foreach ($r as $k => $v) {
                    if ($k === 'category_name') continue;
                    $cols[] = "`" . str_replace('`', '', $k) . "`";
                    $vals[] = ($v === null) ? 'NULL' : $this->db->quote((string)$v);
                }
                $lines[] = "INSERT INTO `reports` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");";
            }
            $lines[] = "";
        }

        $announcements = $this->db->query("SELECT * FROM announcements WHERE is_archived = 1")->fetchAll(PDO::FETCH_ASSOC);
        if (count($announcements) > 0) {
            $lines[] = "-- Archived announcements (" . count($announcements) . ")";
            foreach ($announcements as $a) {
                $cols = [];
                $vals = [];
                foreach ($a as $k => $v) {
                    $cols[] = "`" . str_replace('`', '', $k) . "`";
                    $vals[] = ($v === null) ? 'NULL' : $this->db->quote((string)$v);
                }
                $lines[] = "INSERT INTO `announcements` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");";
            }
            $lines[] = "";
        }

        echo implode("\n", $lines);
        exit();
    }

    // ================================================================
    // 9. BARANGAY MANAGER - FULL CRUD
    // ================================================================
    private function updateBarangays() {
        $sub_action = $_POST['sub_action'] ?? '';
        
        if ($sub_action === 'add') {
            // ============================================
            // ADD BARANGAY
            // ============================================
            $name = InputSanitizer::sanitizeString($_POST['barangay_name'] ?? '');
            $captain_name = InputSanitizer::sanitizeName($_POST['captain_name'] ?? '');
            $captain_contact = InputSanitizer::sanitizeString($_POST['captain_contact'] ?? '');
            
            if (empty($name)) {
                $_SESSION['error'] = "Barangay name cannot be empty.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            // Check if barangay already exists
            $check = $this->db->prepare("SELECT id FROM barangays WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) {
                $_SESSION['error'] = "A barangay with this name already exists.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            $stmt = $this->db->prepare("INSERT INTO barangays (name, captain_name, captain_contact) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $captain_name, $captain_contact])) {
                $this->activityLog->log($this->user_id, 'Create Barangay', "Created barangay: $name", null, 'Settings');
                $_SESSION['success'] = "Barangay added successfully!";
            } else {
                $_SESSION['error'] = "Failed to add barangay.";
            }
            
        } elseif ($sub_action === 'edit') {
            // ============================================
            // EDIT BARANGAY
            // ============================================
            $id = (int)($_POST['barangay_id'] ?? 0);
            $name = InputSanitizer::sanitizeString($_POST['barangay_name'] ?? '');
            $captain_name = InputSanitizer::sanitizeName($_POST['captain_name'] ?? '');
            $captain_contact = InputSanitizer::sanitizeString($_POST['captain_contact'] ?? '');
            
            if ($id <= 0) {
                $_SESSION['error'] = "Invalid barangay ID.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            if (empty($name)) {
                $_SESSION['error'] = "Barangay name cannot be empty.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            // Check if name conflicts with another barangay
            $check = $this->db->prepare("SELECT id FROM barangays WHERE name = ? AND id != ?");
            $check->execute([$name, $id]);
            if ($check->rowCount() > 0) {
                $_SESSION['error'] = "Another barangay with this name already exists.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            $stmt = $this->db->prepare("UPDATE barangays SET name = ?, captain_name = ?, captain_contact = ? WHERE id = ?");
            if ($stmt->execute([$name, $captain_name, $captain_contact, $id])) {
                $this->activityLog->log($this->user_id, 'Update Barangay', "Updated barangay: $name (ID: $id)", null, 'Settings');
                $_SESSION['success'] = "Barangay updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update barangay.";
            }
            
        } elseif ($sub_action === 'delete') {
            // ============================================
            // DELETE BARANGAY - With Foreign Key Checks
            // ============================================
            $id = (int)($_POST['barangay_id'] ?? 0);
            
            if ($id <= 0) {
                $_SESSION['error'] = "Invalid barangay ID.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            // Check if barangay has associated users
            $check = $this->db->prepare("SELECT COUNT(*) FROM users WHERE barangay_id = ?");
            $check->execute([$id]);
            $userCount = (int)$check->fetchColumn();
            
            // Check if barangay has associated reports
            $check = $this->db->prepare("SELECT COUNT(*) FROM reports WHERE barangay_id = ?");
            $check->execute([$id]);
            $reportCount = (int)$check->fetchColumn();
            
            // Check if barangay has associated officials (guarded: the
            // barangay_officials table may not exist in older schemas — in
            // this app officials are users with role 'barangay_official',
            // which the users check above already covers).
            $officialCount = 0;
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'barangay_officials'");
            if ($tableCheck->rowCount() > 0) {
                $check = $this->db->prepare("SELECT COUNT(*) FROM barangay_officials WHERE barangay_id = ?");
                $check->execute([$id]);
                $officialCount = (int)$check->fetchColumn();
            }
            
            // Check if barangay has associated announcements
            $check = $this->db->prepare("SELECT COUNT(*) FROM announcements WHERE barangay_id = ?");
            $check->execute([$id]);
            $announcementCount = (int)$check->fetchColumn();
            
            if ($userCount > 0 || $reportCount > 0 || $officialCount > 0 || $announcementCount > 0) {
                $errors = [];
                if ($userCount > 0) $errors[] = "{$userCount} user(s)";
                if ($reportCount > 0) $errors[] = "{$reportCount} report(s)";
                if ($officialCount > 0) $errors[] = "{$officialCount} official(s)";
                if ($announcementCount > 0) $errors[] = "{$announcementCount} announcement(s)";
                
                $_SESSION['error'] = "Cannot delete this barangay. It has " . implode(", ", $errors) . " associated with it.";
                header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
                exit();
            }
            
            // Get barangay name for logging
            $stmt = $this->db->prepare("SELECT name FROM barangays WHERE id = ?");
            $stmt->execute([$id]);
            $name = $stmt->fetchColumn();
            
            $stmt = $this->db->prepare("DELETE FROM barangays WHERE id = ?");
            if ($stmt->execute([$id])) {
                // Log the activity
                $this->activityLog->log($this->user_id, 'Delete Barangay', "Deleted barangay: {$name} (ID: {$id})", null, 'Settings');
                $_SESSION['success'] = "Barangay deleted successfully!";
            } else {
                $_SESSION['error'] = "Failed to delete barangay.";
            }
            
        } else {
            $_SESSION['error'] = "Invalid action.";
        }
        
        header("Location: " . BASE_URL . "index.php?page=settings&tab=barangays");
        exit();
    }

    // ================================================================
    // 10. PERMISSIONS (RBAC) — dynamic roles, each with its own
    //     permission set stored in roles / role_permissions.
    //     sub_action distinguishes the Create Role modal, Edit Role
    //     modal, Delete Role button, and the per-role toggle grid from
    //     each other (they all post to the same 'permissions' tab).
    // ================================================================
    private function updatePermissions() {
        $subAction = $_POST['sub_action'] ?? 'save_toggles';

        switch ($subAction) {
            case 'create_role':
                $this->createRole();
                return;
            case 'update_role':
                $this->updateRoleDetails();
                return;
            case 'delete_role':
                $this->deleteRoleAction();
                return;
            default:
                $this->savePermissionToggles();
                return;
        }
    }

    /**
     * Create Role modal: Role Title, Description, and the 6 permission
     * checkboxes.
     */
    private function createRole() {
        $title = InputSanitizer::sanitizeString($_POST['title'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $permissions = $this->sanitizePermissionSelections($_POST['permissions'] ?? []);

        if (empty($title)) {
            $_SESSION['error'] = "Role title is required.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
            exit();
        }

        $roleId = SettingsHelper::createRole($title, $description, $permissions, $this->user_id);

        if ($roleId) {
            $this->activityLog->log($this->user_id, 'Create Role', "Created role: {$title}", null, 'Permissions');
            $_SESSION['success'] = "Role \"{$title}\" created successfully! It's now available in the Role dropdown.";
        } else {
            $_SESSION['error'] = "Failed to create role. A role with that title may already exist.";
        }

        header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
        exit();
    }

    /**
     * Edit Role modal: same fields as create, plus role_id.
     */
    private function updateRoleDetails() {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $title = InputSanitizer::sanitizeString($_POST['title'] ?? '');
        $description = InputSanitizer::sanitizeString($_POST['description'] ?? '');
        $permissions = $this->sanitizePermissionSelections($_POST['permissions'] ?? []);

        if ($roleId <= 0 || empty($title)) {
            $_SESSION['error'] = "Invalid role data.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
            exit();
        }

        if (SettingsHelper::updateRole($roleId, $title, $description, $permissions)) {
            $this->activityLog->log($this->user_id, 'Update Role', "Updated role #{$roleId}: {$title}", null, 'Permissions');
            $_SESSION['success'] = "Role updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update role.";
        }

        header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
        exit();
    }

    /**
     * Delete Role button. Built-in roles and roles still assigned to a
     * user are protected (see SettingsHelper::deleteRole()).
     */
    private function deleteRoleAction() {
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($roleId <= 0) {
            $_SESSION['error'] = "Invalid role.";
            header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
            exit();
        }

        $result = SettingsHelper::deleteRole($roleId);
        if ($result === true) {
            $this->activityLog->log($this->user_id, 'Delete Role', "Deleted role #{$roleId}", null, 'Permissions');
            $_SESSION['success'] = "Role deleted successfully!";
        } else {
            $_SESSION['error'] = $result; // human-readable reason from SettingsHelper::deleteRole()
        }

        header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
        exit();
    }

    /**
     * The original per-role permission-grid save (toggles for every
     * existing role at once, as rendered by permissions.php's main form).
     */
    private function savePermissionToggles() {
        $submitted = $_POST['permissions'] ?? [];

        $allowedRoleIds = array_keys(SettingsHelper::getManageableRoles());
        $allowedPermissionKeys = array_keys(SettingsHelper::getPermissionKeys());

        foreach ($allowedRoleIds as $roleId) {
            $role = SettingsHelper::getRoleById($roleId);
            if (!$role) {
                continue;
            }
            $permissions = [];
            foreach ($allowedPermissionKeys as $key) {
                $permissions[$key] = isset($submitted[$roleId][$key]);
            }
            // Manage Reports implies View Reports (enforced in the helper too).
            if (!empty($permissions['can_manage_reports'])) {
                $permissions['can_view_reports'] = true;
            }
            SettingsHelper::updateRole($roleId, $role['title'], $role['description'] ?? '', $permissions);
        }

        $this->activityLog->log($this->user_id, 'Update Permissions', "Updated permissions for " . count($allowedRoleIds) . " role(s)", null, 'Permissions');
        $_SESSION['success'] = "Permissions updated successfully!";
        header("Location: " . BASE_URL . "index.php?page=settings&tab=permissions");
        exit();
    }

    /**
     * Whitelist a submitted permissions[] array against the known keys.
     * @return array<string,bool>
     */
    private function sanitizePermissionSelections($submitted) {
        $allowedKeys = array_keys(SettingsHelper::getPermissionKeys());
        $result = [];
        foreach ($allowedKeys as $key) {
            $result[$key] = isset($submitted[$key]);
        }
        // Manage Reports implies View Reports.
        if (!empty($result['can_manage_reports'])) {
            $result['can_view_reports'] = true;
        }
        return $result;
    }

    // ================================================================
    // 11. SEND TEST SMS (AJAX endpoint - iProg Only)
    // ================================================================
    public function sendTestSMS() {
        // Ensure JSON response
        header('Content-Type: application/json; charset=utf-8');
        
        // CSRF validation
        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit();
        }

        $phone = InputSanitizer::sanitizePhone($_POST['phone'] ?? '');
        $message = InputSanitizer::sanitizeString($_POST['message'] ?? '');

        if (!$phone) {
            echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
            exit();
        }

        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
            exit();
        }

        // If the admin has entered an API key in the form, save it and test with it.
        $gatewayOverride = null;
        if (isset($_POST['iprog_api_key'])) {
            $apiKey = trim($_POST['iprog_api_key']);

            if ($apiKey === '') {
                echo json_encode(['success' => false, 'message' => 'Please enter an iProg API Token before testing.']);
                exit();
            }

            $senderId = trim($_POST['iprog_sender_id'] ?? '');
            $baseUrl = trim($_POST['iprog_base_url'] ?? '') ?: 'https://sms.iprogtech.com/api/v1/sms_messages';
            $senderName = InputSanitizer::sanitizeString($_POST['sms_sender_name'] ?? 'SierraLGU', 11);

            // Persist immediately
            SettingsHelper::set('iprog_api_key', $apiKey);
            SettingsHelper::set('iprog_sender_id', $senderId);
            SettingsHelper::set('iprog_base_url', $baseUrl);
            SettingsHelper::set('sms_sender_name', $senderName);
            SettingsHelper::clearCache();

            $gatewayOverride = [
                'gateway' => 'iprog',
                'api_key' => $apiKey,
                'sender_id' => $senderId,
                'base_url' => $baseUrl,
            ];
        }

        // Use SettingsHelper to send SMS
        $result = SettingsHelper::sendSms($phone, $message, $gatewayOverride);

        $savedNote = $gatewayOverride ? ' Your API key was saved.' : '';

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Test SMS sent successfully!' . $savedNote]);
        } else {
            $gateway = $gatewayOverride ?? SettingsHelper::getActiveSmsGateway();
            if (!$gateway) {
                $status = SettingsHelper::getGatewayStatus();
                $sms_enabled = (int)SettingsHelper::get('enable_sms_notifications');
                echo json_encode([
                    'success' => false,
                    'message' => 'No gateway configured. Check your gateway configuration.' . $savedNote,
                    'diagnostic' => [
                        'sms_enabled' => $sms_enabled,
                        'gateways' => $status
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Your API key was saved, but the test SMS failed. Double-check the API token, sender ID, and endpoint URL are correct for your iProg account.']);
            }
        }
        exit();
    }
    
    // ================================================================
    // 12. GET BARANGAYS (AJAX endpoint)
    // ================================================================
    public function getBarangays() {
        header('Content-Type: application/json; charset=utf-8');
        
        $stmt = $this->db->query("SELECT id, name, captain_name, captain_contact FROM barangays ORDER BY name ASC");
        $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $barangays]);
        exit();
    }
}

// ============================================================
// HANDLE THE REQUEST
// ============================================================

// Check if this is a test SMS AJAX request
if (isset($_POST['action']) && $_POST['action'] === 'send_test_sms') {
    $controller = new SettingsController($db, $user_id);
    $controller->sendTestSMS();
    exit();
}

// Get barangays via AJAX
if (isset($_POST['action']) && $_POST['action'] === 'get_barangays') {
    $controller = new SettingsController($db, $user_id);
    $controller->getBarangays();
    exit();
}

// Persist a single setting via AJAX (used by toggle controls)
if (isset($_POST['action']) && $_POST['action'] === 'save_setting') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit();
    }

    $key = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['key'] ?? ''));
    $value = (isset($_POST['value']) && $_POST['value'] == '1') ? 1 : 0;

    if (empty($key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid key']);
        exit();
    }

    SettingsHelper::set($key, $value);
    SettingsHelper::clearCache();
    echo json_encode(['success' => true, 'message' => 'Saved']);
    exit();
}

// Validate iProg credentials (AJAX)
if (isset($_POST['action']) && $_POST['action'] === 'validate_iprog') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit();
    }

    $apiKey = trim($_POST['iprog_api_key'] ?? '');

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'message' => 'API key is required']);
        exit();
    }

    $testUrl = 'https://sms.iprogtech.com/api/v1/account/sms_credits?' . http_build_query(['api_token' => $apiKey]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $json = json_decode($response, true);

    if ($response !== false && $http_code === 200 && $json && ($json['status'] ?? '') === 'success') {
        $balance = $json['data']['load_balance'] ?? 'unknown';
        echo json_encode(['success' => true, 'message' => 'API key is valid. SMS credit balance: ' . $balance]);
    } else {
        $body = $response !== false ? substr($response, 0, 1000) : '';
        echo json_encode(['success' => false, 'message' => 'Request failed (HTTP ' . $http_code . ')', 'response' => $body]);
    }
    exit();
}

// Get the active tab from URL
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';

// Handle the settings update
$controller = new SettingsController($db, $user_id);
$controller->update($tab);

// If we get here, something went wrong
$_SESSION['error'] = "Invalid request.";
header("Location: " . BASE_URL . "index.php?page=settings&tab=general");
exit();
?>