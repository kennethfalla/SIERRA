<?php
// models/ProfileModel.php - Business logic for the Profile section.
// Owns all profile-related data access: personal info updates, profile
// photo/avatar, phone & email verification (OTP), and password changes.

class ProfileModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // ============================================================
    // READ OPERATIONS
    // ============================================================

    public function getProfile($user_id) {
        $stmt = $this->db->prepare("
            SELECT u.*, b.name as barangay_name
            FROM users u
            LEFT JOIN barangays b ON u.barangay_id = b.id
            WHERE u.id = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getBarangays() {
        return $this->db->query("SELECT id, name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ============================================================
    // PHONE VERIFICATION (OTP)
    // ============================================================

    public function sendPhoneOtp($user_id, $new_phone) {
        $new_phone = InputSanitizer::sanitizePhone($new_phone);
        if (!$new_phone || !preg_match('/^09[0-9]{9}$/', $new_phone)) {
            return [false, 'Invalid mobile number.'];
        }

        $dup = $this->db->prepare("SELECT id FROM users WHERE contact_number = :phone AND id != :uid");
        $dup->execute([':phone' => $new_phone, ':uid' => $user_id]);
        if ($dup->rowCount() > 0) {
            return [false, 'This mobile number is already registered by another account.'];
        }

        $otp = sprintf("%06d", random_int(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $this->storeCode($user_id, $otp, 'profile_phone', $expires_at);

        $system_name = SettingsHelper::get('system_name', 'Sierra');
        $message = "Your $system_name profile verification OTP is: $otp. Expires in 10 minutes.";
        $sms_sent = SettingsHelper::sendSms($new_phone, $message);

        if ($sms_sent) {
            $_SESSION['profile_phone_otp_new'] = $new_phone;
            return [true, 'OTP sent to your new number.'];
        }
        return [false, 'Failed to send OTP. Please check your number and try again.'];
    }

    public function verifyPhoneOtp($user_id, $otp) {
        $otp = trim($otp);
        if (strlen($otp) !== 6) {
            return [false, 'Invalid OTP format.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM verification_codes
                                    WHERE user_id = :uid AND code = :code AND type = 'profile_phone'
                                    AND expires_at > NOW() AND used = 0");
        $stmt->execute([':uid' => $user_id, ':code' => $otp]);
        if ($stmt->rowCount() > 0) {
            $update = $this->db->prepare("UPDATE verification_codes SET used = 1 WHERE user_id = :uid AND code = :code AND type = 'profile_phone'");
            $update->execute([':uid' => $user_id, ':code' => $otp]);
            $_SESSION['profile_phone_verified'] = true;
            return [true, 'Phone number verified.'];
        }
        return [false, 'Invalid or expired OTP.'];
    }

    // ============================================================
    // EMAIL CONFIRMATION
    // ============================================================

    public function sendEmailConfirmation($user_id, $new_email) {
        $new_email = InputSanitizer::sanitizeEmail($new_email);
        if (!$new_email || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Invalid email address.'];
        }

        $dup = $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :uid");
        $dup->execute([':email' => $new_email, ':uid' => $user_id]);
        if ($dup->rowCount() > 0) {
            return [false, 'This email is already registered by another account.'];
        }

        $confirm_code = strtoupper(bin2hex(random_bytes(4)));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $this->storeCode($user_id, $confirm_code, 'profile_email', $expires_at);

        $user = $this->getProfile($user_id);
        $system_name = SettingsHelper::get('system_name', 'Sierra');
        $full_name = htmlspecialchars($user['first_name'] . ' ' . $user['last_name']);
        $subject = "Confirm Your Email Change - $system_name";

        $html = "
        <html><head><style>
            body { font-family: 'Manrope', Arial, sans-serif; color: #1a2e1a; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #10A37F; color: white; padding: 20px; text-align: center; border-radius: 12px 12px 0 0; }
            .content { background: #f9fbfa; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 12px 12px; }
            .code { font-size: 28px; font-weight: 800; letter-spacing: 6px; color: #10A37F; text-align: center; padding: 15px; background: #e6f7f0; border-radius: 8px; margin: 20px 0; }
            .footer { text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px; }
        </style></head><body>
            <div class='container'>
                <div class='header'>
                    <h2 style='margin:0;'>$system_name</h2>
                    <p style='margin:5px 0 0; opacity:0.9;'>Email Confirmation</p>
                </div>
                <div class='content'>
                    <h3 style='margin-top:0;'>Hello $full_name,</h3>
                    <p>You requested to change your email address to <strong>" . htmlspecialchars($new_email) . "</strong>.</p>
                    <p>Please use the confirmation code below to verify this change:</p>
                    <div class='code'>$confirm_code</div>
                    <p style='font-size:13px; color:#6b7280;'>This code expires in 30 minutes.</p>
                    <p style='font-size:13px; color:#6b7280;'>If you didn't request this change, please ignore this email and your current email will remain unchanged.</p>
                </div>
                <div class='footer'>&copy; " . date('Y') . " $system_name - LGU San Isidro</div>
            </div>
        </body></html>";

        $email_sent = SettingsHelper::sendEmail($new_email, $user['first_name'] . ' ' . $user['last_name'], $subject, $html);

        if ($email_sent) {
            $_SESSION['profile_email_otp_new'] = $new_email;
            return [true, 'Confirmation code sent to your new email.'];
        }
        return [false, 'Failed to send confirmation email. Please check your email address.'];
    }

    public function verifyEmailConfirmation($user_id, $token) {
        $token = trim($token);
        if (strlen($token) < 4) {
            return [false, 'Invalid confirmation code.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM verification_codes
                                    WHERE user_id = :uid AND code = :code AND type = 'profile_email'
                                    AND expires_at > NOW() AND used = 0");
        $stmt->execute([':uid' => $user_id, ':code' => $token]);
        if ($stmt->rowCount() > 0) {
            $update = $this->db->prepare("UPDATE verification_codes SET used = 1 WHERE user_id = :uid AND code = :code AND type = 'profile_email'");
            $update->execute([':uid' => $user_id, ':code' => $token]);
            $_SESSION['profile_email_verified'] = true;
            return [true, 'Email address verified.'];
        }
        return [false, 'Invalid or expired confirmation code.'];
    }

    // ============================================================
    // PASSWORD CHANGE (OTP verify)
    // ============================================================

    public function sendPasswordChangeOtp($user_id, $current_password, $new_password, $confirm_password) {
        $errors = [];
        $userModel = new User($this->db);
        if (!$userModel->verifyPassword($user_id, $current_password)) {
            $errors[] = "Your current password is incorrect.";
        }
        $pwErrors = InputSanitizer::validatePassword($new_password);
        if ($pwErrors) {
            $errors = array_merge($errors, $pwErrors);
        }
        if ($new_password !== $confirm_password) {
            $errors[] = "New password and confirmation do not match.";
        }
        if ($new_password !== '' && ($new_password === $current_password || $userModel->verifyPassword($user_id, $new_password))) {
            $errors[] = "New password must be different from your current password.";
        }

        if (!empty($errors)) {
            return [false, implode(' ', $errors)];
        }

        $_SESSION['pending_new_password'] = $new_password;

        $otp = sprintf("%06d", random_int(100000, 999999));
        $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $this->storeCode($user_id, $otp, 'password_change', $expires_at);

        $user = $this->getProfile($user_id);
        $contact_number = $user['contact_number'];
        $system_name = SettingsHelper::get('system_name', 'Sierra');
        $message = "Your $system_name password change OTP is: $otp. Expires in 10 minutes.";
        $sms_sent = SettingsHelper::sendSms($contact_number, $message);

        if ($sms_sent) {
            return [true, 'OTP sent to your registered mobile number.'];
        }
        return [false, 'Failed to send OTP. Please try again.'];
    }

    public function verifyPasswordChangeOtp($user_id, $otp) {
        $otp = trim($otp);
        if (strlen($otp) !== 6) {
            return [false, 'Invalid OTP format.'];
        }

        if (empty($_SESSION['pending_new_password'])) {
            return [false, 'Session expired. Please start the password change again.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM verification_codes
                                    WHERE user_id = :uid AND code = :code AND type = 'password_change'
                                    AND expires_at > NOW() AND used = 0");
        $stmt->execute([':uid' => $user_id, ':code' => $otp]);
        if ($stmt->rowCount() > 0) {
            $update = $this->db->prepare("UPDATE verification_codes SET used = 1 WHERE user_id = :uid AND code = :code AND type = 'password_change'");
            $update->execute([':uid' => $user_id, ':code' => $otp]);

            $new_password = $_SESSION['pending_new_password'];
            unset($_SESSION['pending_new_password']);

            $userModel = new User($this->db);
            $userModel->updatePassword($user_id, $new_password);

            $this->logActivity($user_id, 'Change Password', 'User changed their password from the profile page (verified via OTP).');

            return [true, 'Password changed successfully!'];
        }
        unset($_SESSION['pending_new_password']);
        return [false, 'Invalid or expired OTP.'];
    }

    // ============================================================
    // PERSONAL INFORMATION UPDATE
    // ============================================================

    public function updateProfile($user_id, $data) {
        $first_name = trim($data['first_name']);
        $last_name = trim($data['last_name']);
        $email = trim($data['email']);
        $contact_number = trim($data['contact_number']);

        $purok_street = trim($data['purok_street'] ?? '');
        $barangay_id = !empty($data['barangay_id']) ? (int)$data['barangay_id'] : null;
        $non_resident_address = trim($data['non_resident_address'] ?? '');

        $current = $this->getProfile($user_id);

        $errors = [];
        if (strlen($first_name) < 2) $errors[] = "First name must be at least 2 characters.";
        if (strlen($last_name) < 2) $errors[] = "Last name must be at least 2 characters.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email address.";
        if (!preg_match('/^09[0-9]{9}$/', $contact_number)) $errors[] = "Invalid mobile number.";

        $phone_changed = ($contact_number !== $current['contact_number']);
        if ($phone_changed) {
            if (!isset($_SESSION['profile_phone_verified']) || $_SESSION['profile_phone_verified'] !== true) {
                $errors[] = "Please verify your new phone number before saving.";
            }
            $check = $this->db->prepare("SELECT id FROM users WHERE contact_number = :contact AND id != :user_id");
            $check->execute([':contact' => $contact_number, ':user_id' => $user_id]);
            if ($check->rowCount() > 0) {
                $errors[] = "This mobile number is already registered by another account.";
            }
        }

        $email_changed = (strtolower($email) !== strtolower($current['email']));
        if ($email_changed) {
            if (!isset($_SESSION['profile_email_verified']) || $_SESSION['profile_email_verified'] !== true) {
                $errors[] = "Please confirm your new email address before saving.";
            }
            $check = $this->db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
            $check->execute([':email' => $email, ':user_id' => $user_id]);
            if ($check->rowCount() > 0) {
                $errors[] = "This email is already registered by another account.";
            }
        }

        $profile_picture = $current['profile_picture'];
        if (isset($data['remove_photo']) && $data['remove_photo'] === '1') {
            $profile_picture = null;
        } elseif (!empty($data['cropped_image'])) {
            [$ok, $result] = $this->saveProfileCrop($user_id, $profile_picture, $data['cropped_image']);
            if ($ok) {
                $profile_picture = $result;
                $_SESSION['profile_picture'] = $profile_picture;
            } else {
                $errors[] = $result;
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $update = $this->db->prepare("
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

        if ($phone_changed) {
            $this->logActivity($user_id, 'Update Phone', 'User updated their phone number from profile.');
            unset($_SESSION['profile_phone_verified'], $_SESSION['profile_phone_otp_new']);
        }
        if ($email_changed) {
            $this->logActivity($user_id, 'Update Email', 'User updated their email address from profile.');
            unset($_SESSION['profile_email_verified'], $_SESSION['profile_email_otp_new']);
        }

        $_SESSION['user_name'] = $first_name . ' ' . $last_name;

        if ($profile_picture) {
            $_SESSION['profile_picture'] = $profile_picture;
        } else {
            unset($_SESSION['profile_picture']);
        }

        return ['success' => true, 'message' => 'Profile updated!'];
    }

    // ============================================================
    // PROFILE PHOTO ONLY
    // ============================================================

    public function updateProfilePhoto($user_id, $data) {
        if (isset($data['remove_photo']) && $data['remove_photo'] === '1') {
            $this->db->prepare("UPDATE users SET profile_picture = NULL WHERE id = :id")
                ->execute([':id' => $user_id]);
            unset($_SESSION['profile_picture']);
            $this->logActivity($user_id, 'Update Profile Photo', 'User removed their profile photo.');
            return ['success' => true, 'message' => 'Profile photo removed.'];
        }

        if (!empty($data['cropped_image'])) {
            $current = $this->getProfile($user_id);
            [$ok, $result] = $this->saveProfileCrop($user_id, $current['profile_picture'], $data['cropped_image']);
            if ($ok) {
                $this->db->prepare("UPDATE users SET profile_picture = :pp WHERE id = :id")
                    ->execute([':pp' => $result, ':id' => $user_id]);
                $_SESSION['profile_picture'] = $result;
                $this->logActivity($user_id, 'Update Profile Photo', 'User updated their profile photo.');
                return ['success' => true, 'message' => 'Profile photo updated!'];
            }
            return ['errors' => [$result]];
        }

        return ['errors' => ['No image data received.']];
    }

    // ============================================================
    // CHANGE PASSWORD (direct, no OTP)
    // ============================================================

    public function changePassword($user_id, $current, $new, $confirm) {
        $errors = [];
        $userModel = new User($this->db);
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
        if ($new !== '' && ($new === $current || $userModel->verifyPassword($user_id, $new))) {
            $errors[] = "New password must be different from your current password.";
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        $userModel->updatePassword($user_id, $new);
        $this->logActivity($user_id, 'Change Password', 'User changed their own password from the profile page.');

        return ['success' => true, 'message' => 'Your password has been updated successfully.'];
    }

    // ============================================================
    // BARANGAY PDF EXPORT SETTINGS
    // ============================================================

    public function savePdfExportSettings($user_id, $role, $data, $file) {
        if ($role !== 'barangay_official') {
            return ['errors' => ['You are not permitted to change PDF export settings.']];
        }

        $approved_by = InputSanitizer::sanitizeString($data['brgy_pdf_approved_by_name'] ?? '');
        $approved_title = InputSanitizer::sanitizeString($data['brgy_pdf_approved_by_title'] ?? 'Punong Barangay');

        SettingsHelper::set('brgy_pdf_approved_by_name_' . $user_id, $approved_by);
        SettingsHelper::set('brgy_pdf_approved_by_title_' . $user_id, $approved_title);

        $errors = [];
        if (isset($file['brgy_pdf_logo']) && $file['brgy_pdf_logo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['brgy_pdf_logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed) && $file['brgy_pdf_logo']['size'] <= 5242880) {
                $upload_dir = BASE_PATH . 'uploads/settings/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $old = SettingsHelper::get('brgy_pdf_logo_' . $user_id, '');
                if ($old && file_exists(BASE_PATH . $old)) {
                    @unlink(BASE_PATH . $old);
                }
                $new_filename = 'brgy_pdf_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($file['brgy_pdf_logo']['tmp_name'], $upload_dir . $new_filename)) {
                    SettingsHelper::set('brgy_pdf_logo_' . $user_id, 'uploads/settings/' . $new_filename);
                } else {
                    $errors[] = 'Barangay logo upload failed.';
                }
            } else {
                $errors[] = 'Invalid logo file. Allowed: JPG, PNG, GIF, WebP (max 5MB).';
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        SettingsHelper::clearCache();
        return ['success' => true, 'message' => 'PDF export settings saved successfully!'];
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function saveProfileCrop($user_id, $current_picture, $cropped_data) {
        $parts = explode(',', $cropped_data);
        if (count($parts) !== 2) return [false, 'Invalid image data.'];
        $image_data = base64_decode($parts[1]);
        if ($image_data === false) return [false, 'Invalid image data.'];

        $upload_dir = PROFILE_UPLOAD_DIR;
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        if ($current_picture && file_exists(BASE_PATH . $current_picture)) {
            @unlink(BASE_PATH . $current_picture);
        }

        $new_filename = 'profile_' . $user_id . '_' . time() . '.png';
        if (file_put_contents($upload_dir . $new_filename, $image_data)) {
            return [true, 'uploads/profile/' . $new_filename];
        }
        return [false, 'Failed to save image.'];
    }

    private function storeCode($user_id, $code, $type, $expires_at) {
        try {
            $typeCol = $this->db->query("SHOW COLUMNS FROM verification_codes LIKE 'type'")->fetch(PDO::FETCH_ASSOC);
            if (!$typeCol) {
                $this->db->exec("ALTER TABLE verification_codes ADD COLUMN type VARCHAR(20) DEFAULT 'forgot'");
            } elseif (stripos($typeCol['Type'], 'enum') === 0) {
                $this->db->exec("ALTER TABLE verification_codes MODIFY COLUMN type VARCHAR(20) DEFAULT 'forgot'");
            }
            $stmt = $this->db->prepare("INSERT INTO verification_codes (user_id, code, expires_at, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $code, $expires_at, $type]);
        } catch (PDOException $e) {
            $this->db->exec("CREATE TABLE IF NOT EXISTS verification_codes (
                id INT(11) AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                type VARCHAR(20) DEFAULT 'forgot',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $this->db->prepare("INSERT INTO verification_codes (user_id, code, expires_at, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $code, $expires_at, $type]);
        }
    }

    private function logActivity($user_id, $action, $description, $status = 'SUCCESS') {
        $stmt = $this->db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $status
        ]);
    }
}