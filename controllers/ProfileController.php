<?php
// controllers/ProfileController.php - MVC controller for the Profile section.
// Handles:
//   POST  action=profile_ajax  -> JSON endpoints (phone/email OTP, password change)
//   POST  update_profile / update_photo / change_password / save_pdf_export
//   GET   -> prepares data and renders views/shared/profile/profile.php

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/helpers/SettingsHelper.php';
require_once dirname(__DIR__) . '/models/ProfileModel.php';

class ProfileController {
    private $db;
    private $profileModel;

    public function __construct($db, $profileModel) {
        $this->db = $db;
        $this->profileModel = $profileModel;
    }

    // ============================================================
    // MAIN ENTRY POINT
    // ============================================================
    public function run() {
        requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (($_GET['action'] ?? '') === 'profile_ajax') {
                $this->handleAjax();
            } else {
                $this->handlePost();
            }
            exit();
        }

        $this->render();
        exit();
    }

    // ============================================================
    // AJAX ENDPOINTS (returns JSON)
    // ============================================================
    private function handleAjax() {
        header('Content-Type: application/json');

        if (!isset($_POST['csrf_token']) || !InputSanitizer::validateCsrfToken($_POST['csrf_token'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
            exit();
        }

        $user_id = $_SESSION['user_id'];
        $action = $_POST['action'] ?? '';
        $m = $this->profileModel;

        switch ($action) {
            case 'send_phone_otp':
                [$ok, $message] = $m->sendPhoneOtp($user_id, $_POST['new_phone'] ?? '');
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            case 'verify_phone_otp':
                [$ok, $message] = $m->verifyPhoneOtp($user_id, $_POST['otp'] ?? '');
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            case 'send_email_confirm':
                [$ok, $message] = $m->sendEmailConfirmation($user_id, $_POST['new_email'] ?? '');
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            case 'verify_email_confirm':
                [$ok, $message] = $m->verifyEmailConfirmation($user_id, $_POST['token'] ?? '');
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            case 'send_password_otp':
                [$ok, $message] = $m->sendPasswordChangeOtp(
                    $user_id,
                    $_POST['current_password'] ?? '',
                    $_POST['new_password'] ?? '',
                    $_POST['confirm_password'] ?? ''
                );
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            case 'verify_password_otp':
                [$ok, $message] = $m->verifyPasswordChangeOtp($user_id, $_POST['otp'] ?? '');
                echo json_encode(['success' => $ok, 'message' => $message]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        }
        exit();
    }

    // ============================================================
    // FORM POST HANDLERS (flash + redirect)
    // ============================================================
    private function handlePost() {
        $user_id = $_SESSION['user_id'];
        $m = $this->profileModel;

        $csrf_ok = isset($_POST['csrf_token']) && InputSanitizer::validateCsrfToken($_POST['csrf_token']);

        if (isset($_POST['update_profile'])) {
            if (!$csrf_ok) {
                $_SESSION['errors'] = ['Invalid security token. Please refresh the page and try again.'];
            } else {
                $result = $m->updateProfile($user_id, $_POST);
                if (!empty($result['errors'])) {
                    $_SESSION['errors'] = $result['errors'];
                } else {
                    $_SESSION['success'] = 'Profile updated!';
                }
            }
            header("Location: " . BASE_URL . "index.php?page=profile&section=personal-information");
            exit();
        }

        if (isset($_POST['update_photo'])) {
            if (!$csrf_ok) {
                $_SESSION['errors'] = ['Invalid security token. Please refresh the page and try again.'];
            } else {
                $result = $m->updateProfilePhoto($user_id, $_POST);
                if (!empty($result['errors'])) {
                    $_SESSION['errors'] = $result['errors'];
                } else {
                    $_SESSION['success'] = $result['message'];
                }
            }
            header("Location: " . BASE_URL . "index.php?page=profile");
            exit();
        }

        if (isset($_POST['change_password'])) {
            if (!$csrf_ok) {
                $_SESSION['password_errors'] = ['Invalid security token. Please refresh the page and try again.'];
            } else {
                $result = $m->changePassword(
                    $user_id,
                    (string)($_POST['current_password'] ?? ''),
                    (string)($_POST['new_password'] ?? ''),
                    (string)($_POST['confirm_password'] ?? '')
                );
                if (!empty($result['errors'])) {
                    $_SESSION['password_errors'] = $result['errors'];
                } else {
                    $_SESSION['password_success'] = $result['message'];
                }
            }
            header("Location: " . BASE_URL . "index.php?page=profile&section=change-password");
            exit();
        }

        if (isset($_POST['save_pdf_export'])) {
            if (!$csrf_ok) {
                $_SESSION['errors'] = ['Invalid security token. Please refresh and try again.'];
            } else {
                $result = $m->savePdfExportSettings($user_id, $_SESSION['user_role'] ?? '', $_POST, $_FILES);
                if (!empty($result['errors'])) {
                    $_SESSION['errors'] = $result['errors'];
                } else {
                    $_SESSION['success'] = $result['message'];
                }
            }
            header("Location: " . BASE_URL . "index.php?page=profile&section=pdf-export-settings");
            exit();
        }

        // Unknown POST - fall back to rendering the page.
        $this->render();
    }

    // ============================================================
    // VIEW RENDER
    // ============================================================
    private function render() {
        $user_id = $_SESSION['user_id'];
        $db = $this->db;
        $user = $this->profileModel->getProfile($user_id);

        if (!$user) {
            $_SESSION['error'] = "User not found.";
            header("Location: " . BASE_URL . "index.php?page=dashboard");
            exit();
        }

        $barangays = $this->profileModel->getBarangays();

        require __DIR__ . '/../views/shared/profile/profile.php';
    }
}

// ============================================================
// BOOTSTRAP
// ============================================================
$database = new Database();
$db = $database->getConnection();
$profileModel = new ProfileModel($db);
$controller = new ProfileController($db, $profileModel);
$controller->run();