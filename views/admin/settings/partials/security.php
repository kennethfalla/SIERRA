<?php
// views/admin/settings/partials/security.php
// Security Settings: Password policy & login lockout
// This file is included by views/admin/settings/index.php

$csrf_token = InputSanitizer::generateCsrfToken();

// Load current settings (with defaults)
$min_length      = (int) SettingsHelper::get('password_min_length', 8);
$require_upper   = (int) SettingsHelper::get('password_require_upper', 1);
$require_lower   = (int) SettingsHelper::get('password_require_lower', 1);
$require_number  = (int) SettingsHelper::get('password_require_number', 1);
$require_special = (int) SettingsHelper::get('password_require_special', 1);
$max_attempts    = (int) SettingsHelper::get('max_login_attempts', 5);
$lockout_minutes = (int) SettingsHelper::get('lockout_duration_minutes', 30);
$session_timeout = (float) SettingsHelper::get('session_timeout_hours', 2);
$otp_max         = (int) SettingsHelper::get('otp_max_requests', 3);
$otp_window_min  = (int) round((int) SettingsHelper::get('otp_request_window_seconds', 600) / 60);
$otp_cooldown    = (int) SettingsHelper::get('otp_cooldown_seconds', 60);
?>
<form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=security" id="securityForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <!-- ============================================ -->
    <!-- PASSWORD REQUIREMENTS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-key text-[#10A37F]"></i> Password Requirements
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Minimum Length -->
            <div class="form-group">
                <label class="form-label" for="password_min_length">Minimum Password Length</label>
                <input type="number" name="password_min_length" id="password_min_length"
                       value="<?php echo $min_length; ?>" min="6" max="20" class="form-input">
                <p class="help-text">Between 6 and 20 characters. Default: 8</p>
            </div>

            <!-- Checkboxes -->
            <div class="flex flex-col gap-2">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="password_require_upper" value="1"
                           <?php echo $require_upper ? 'checked' : ''; ?> class="rounded border-gray-300 text-[#10A37F]">
                    <span class="text-sm text-gray-700">Require uppercase letter (A–Z)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="password_require_lower" value="1"
                           <?php echo $require_lower ? 'checked' : ''; ?> class="rounded border-gray-300 text-[#10A37F]">
                    <span class="text-sm text-gray-700">Require lowercase letter (a–z)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="password_require_number" value="1"
                           <?php echo $require_number ? 'checked' : ''; ?> class="rounded border-gray-300 text-[#10A37F]">
                    <span class="text-sm text-gray-700">Require number (0–9)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="password_require_special" value="1"
                           <?php echo $require_special ? 'checked' : ''; ?> class="rounded border-gray-300 text-[#10A37F]">
                    <span class="text-sm text-gray-700">Require special character (!@#$%^&*…)</span>
                </label>
            </div>
        </div>

        <!-- Current Policy Summary -->
        <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-600">
            <strong>Current policy:</strong>
            <ul class="list-disc list-inside text-xs mt-1">
                <li>Minimum length: <strong><?php echo $min_length; ?></strong></li>
                <?php if ($require_upper): ?><li>Uppercase letter required</li><?php endif; ?>
                <?php if ($require_lower): ?><li>Lowercase letter required</li><?php endif; ?>
                <?php if ($require_number): ?><li>Number required</li><?php endif; ?>
                <?php if ($require_special): ?><li>Special character required</li><?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- LOGIN SECURITY -->
    <!-- ============================================ -->
    <div class="mb-6 border-t border-gray-200 pt-4">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-shield-alt text-[#10A37F]"></i> Login Security
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="max_login_attempts">Max Login Attempts before Lockout</label>
                <input type="number" name="max_login_attempts" id="max_login_attempts"
                       value="<?php echo $max_attempts; ?>" min="3" max="10" class="form-input">
                <p class="help-text">Number of failed attempts before temporary lockout. Default: 5</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="lockout_duration_minutes">Lockout Duration (minutes)</label>
                <input type="number" name="lockout_duration_minutes" id="lockout_duration_minutes"
                       value="<?php echo $lockout_minutes; ?>" min="5" max="1440" class="form-input">
                <p class="help-text">How long the account is locked after max attempts. Default: 30</p>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- OTP VERIFICATION (anti-spam) -->
    <!-- ============================================ -->
    <div class="mb-6 border-t border-gray-200 pt-4">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-mobile-alt text-[#10A37F]"></i> OTP Verification (anti-spam)
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="otp_max_requests">Max OTP Requests per Window</label>
                <input type="number" name="otp_max_requests" id="otp_max_requests"
                       value="<?php echo $otp_max; ?>" min="1" max="20" class="form-input">
                <p class="help-text">Sends allowed before the user is blocked. Default: 3</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="otp_request_window_minutes">Rate-Limit Window (minutes)</label>
                <input type="number" name="otp_request_window_minutes" id="otp_request_window_minutes"
                       value="<?php echo $otp_window_min; ?>" min="1" max="120" class="form-input">
                <p class="help-text">Rolling window for the request limit. Default: 10</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="otp_cooldown_seconds">Resend Cooldown (seconds)</label>
                <input type="number" name="otp_cooldown_seconds" id="otp_cooldown_seconds"
                       value="<?php echo $otp_cooldown; ?>" min="20" max="600" class="form-input">
                <p class="help-text">OTP validity + wait before resend/switch. Default: 60</p>
            </div>
        </div>
        <p class="help-text mt-2">Applies to registration (SMS &amp; email) OTP verification. Users see a live retry countdown when the limit is reached.</p>
    </div>

    <!-- ============================================ -->
    <!-- SESSION SECURITY (automatic logout) -->
    <!-- ============================================ -->
    <div class="mb-6 border-t border-gray-200 pt-4">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-hourglass-half text-[#10A37F]"></i> Session Security
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="session_timeout_hours">Auto-Logout after (hours)</label>
                <input type="number" name="session_timeout_hours" id="session_timeout_hours"
                       value="<?php echo $session_timeout; ?>" min="0" max="72" step="0.5" class="form-input">
                <p class="help-text">Users are logged out automatically after this many hours of inactivity. Set to 0 to disable. Default: 2</p>
            </div>
            <div class="form-group">
                <div class="p-3 bg-blue-50 rounded-lg border border-blue-100 text-xs text-blue-800 leading-relaxed">
                    <i class="fas fa-info-circle mr-1"></i>
                    Tracks real user activity (mouse, keyboard, touch, scrolling). If a user leaves the app idle for the set time, they are signed out securely. Recommended: 2–8 hours for office use.
                </div>
            </div>
        </div>
        <div class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200 text-sm text-gray-600">
            <strong>Currently:</strong>
            <?php if ($session_timeout > 0): ?>
                Users are logged out automatically after <strong><?php echo (float)$session_timeout; ?></strong> hour<?php echo $session_timeout == 1 ? '' : 's'; ?> of inactivity.
            <?php else: ?>
                Automatic inactivity logout is <strong>disabled</strong> (sessions remain open indefinitely).
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-2 border-t border-gray-100">
        <button type="reset" onclick="resetForm()" class="btn-secondary flex items-center gap-2">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn-primary flex items-center gap-2">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</form>

<script>
(function() {
    'use strict';

    const form = document.getElementById('securityForm');
    let formChanged = false;

    // Detect unsaved changes
    form.addEventListener('input', function() {
        formChanged = true;
    });

    form.addEventListener('submit', function() {
        formChanged = false;
    });

    // Warn before leaving with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    // Reset function (reloads page to discard changes)
    window.resetForm = function() {
        window.GB.confirm({
            message: 'Reset all fields to their saved values? Unsaved changes will be lost.',
            onConfirm: function () { location.reload(); }
        });
    };

    // Ensure numeric inputs are within bounds on submit
    form.addEventListener('submit', function(e) {
        const minLength = document.getElementById('password_min_length');
        if (minLength) {
            let val = parseInt(minLength.value, 10);
            if (isNaN(val) || val < 6) minLength.value = 6;
            if (val > 20) minLength.value = 20;
        }

        const maxAttempts = document.getElementById('max_login_attempts');
        if (maxAttempts) {
            let val = parseInt(maxAttempts.value, 10);
            if (isNaN(val) || val < 3) maxAttempts.value = 3;
            if (val > 10) maxAttempts.value = 10;
        }

        const lockout = document.getElementById('lockout_duration_minutes');
        if (lockout) {
            let val = parseInt(lockout.value, 10);
            if (isNaN(val) || val < 5) lockout.value = 5;
            if (val > 1440) lockout.value = 1440;
        }

        const otpMax = document.getElementById('otp_max_requests');
        if (otpMax) {
            let val = parseInt(otpMax.value, 10);
            if (isNaN(val) || val < 1) otpMax.value = 1;
            if (val > 20) otpMax.value = 20;
        }

        const otpWindow = document.getElementById('otp_request_window_minutes');
        if (otpWindow) {
            let val = parseInt(otpWindow.value, 10);
            if (isNaN(val) || val < 1) otpWindow.value = 1;
            if (val > 120) otpWindow.value = 120;
        }

        const otpCooldown = document.getElementById('otp_cooldown_seconds');
        if (otpCooldown) {
            let val = parseInt(otpCooldown.value, 10);
            if (isNaN(val) || val < 20) otpCooldown.value = 20;
            if (val > 600) otpCooldown.value = 600;
        }

        const sessionTimeout = document.getElementById('session_timeout_hours');
        if (sessionTimeout) {
            let val = parseFloat(sessionTimeout.value);
            if (isNaN(val) || val < 0) sessionTimeout.value = 0;
            if (val > 72) sessionTimeout.value = 72;
        }
    });

})();
</script>

<style>
.btn-primary {
    background: linear-gradient(135deg, #10A37F, #0D8568);
    color: white;
    padding: 0.6rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16,163,127,0.3);
}
.btn-secondary {
    background: white;
    border: 1px solid #e2e8f0;
    padding: 0.6rem 1.5rem;
    border-radius: 0.75rem;
    font-weight: 500;
    color: #4b5563;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-secondary:hover {
    background: #f8fafc;
}
.form-group {
    margin-bottom: 0.75rem;
}
.form-label {
    display: block;
    font-weight: 600;
    color: #374151;
    font-size: 0.8rem;
    margin-bottom: 0.2rem;
}
.form-input {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border: 1.5px solid #E5E7EB;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    transition: all 0.2s;
    background: white;
}
.form-input:focus {
    border-color: #10A37F;
    outline: none;
    box-shadow: 0 0 0 3px rgba(16,163,127,0.08);
}
.help-text {
    font-size: 0.7rem;
    color: #6B7280;
    margin-top: 0.2rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 480px) {
    #securityForm .btn-primary,
    #securityForm .btn-secondary {
        width: 100%;
        justify-content: center;
        text-align: center;
    }
}
</style>