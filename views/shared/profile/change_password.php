<?php
// views/shared/profile/change_password.php - partial, included by views/shared/profile/profile.php
?>
                    <!-- ========== CHANGE PASSWORD ========== -->
                    <div id="section-change-password">
                        <div class="flex items-center gap-2 mb-4">
                            <i class="fas fa-key text-[#10A37F]"></i>
                            <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Change Password</h3>
                        </div>
                        <p class="text-sm text-gray-500 mb-5">Update your password to keep your account secure. Use a strong password you don't use anywhere else.</p>

                        <div id="passwordSuccessMsg" style="display:none;" class="mb-5 p-4 bg-green-50 border-l-4 border-green-500 rounded-xl text-green-700 text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-check-circle text-green-500"></i>
                                <span id="passwordSuccessText"></span>
                            </div>
                        </div>
                        
                        <form method="POST" action="" id="passwordForm" autocomplete="off">
                            <input type="hidden" name="change_password" value="1">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="form-label">Current Password *</label>
                                    <div class="relative">
                                        <input type="password" name="current_password" id="currentPassword" class="form-input pr-10" required>
                                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePwVisibility('currentPassword', this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">New Password *</label>
                                    <div class="relative">
                                        <input type="password" name="new_password" id="newPassword" class="form-input pr-10" required>
                                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePwVisibility('newPassword', this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">Confirm New Password *</label>
                                    <div class="relative">
                                        <input type="password" name="confirm_password" id="confirmPassword" class="form-input pr-10" required>
                                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="togglePwVisibility('confirmPassword', this)"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-5 p-4 bg-gray-50 rounded-xl border border-gray-100">
                                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Password Requirements</p>
                                <div id="pwChecks">
                                    <div class="pw-check" data-rule="min"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> At least <?php echo $p_min; ?> characters</div>
                                    <?php if ($p_upper): ?><div class="pw-check" data-rule="upper"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> At least 1 uppercase letter (A-Z)</div><?php endif; ?>
                                    <?php if ($p_lower): ?><div class="pw-check" data-rule="lower"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> At least 1 lowercase letter (a-z)</div><?php endif; ?>
                                    <?php if ($p_number): ?><div class="pw-check" data-rule="number"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> At least 1 number (0-9)</div><?php endif; ?>
                                    <?php if ($p_special): ?><div class="pw-check" data-rule="special"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> At least 1 special character (!@#$%^&*)</div><?php endif; ?>
                                    <div class="pw-check" data-rule="match"><i class="fas fa-circle text-[8px]"></i><i class="fas fa-check-circle"></i> Password and confirmation match</div>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-100">
                                <button type="submit" class="btn-primary" id="changePasswordBtn">
                                    <i class="fas fa-shield-alt"></i> Change Password
                                </button>
                                <button type="reset" class="btn-secondary">
                                    <i class="fas fa-undo"></i> Clear
                                </button>
                            </div>
                        </form>

                        <p class="text-xs text-gray-400 mt-3"><i class="fas fa-info-circle"></i> An OTP will be sent to your registered mobile number to verify this change.</p>
                    </div>

                    <!-- Password OTP Modal -->
                    <div id="passwordOtpModal" class="crop-modal">
                        <div class="crop-modal-content" style="max-width:420px;">
                            <div class="crop-modal-header">
                                <h3><i class="fas fa-shield-alt"></i> Verify Password Change</h3>
                                <button onclick="closePasswordOtpModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
                            </div>
                            <div class="crop-modal-body" style="padding:24px;">
                                <p class="text-sm text-gray-500 mb-4">We've sent a 6-digit OTP to your registered mobile number. Enter it below to confirm your password change.</p>
                                <input type="text" id="passwordOtpInput" maxlength="6" placeholder="000000" class="form-input text-center text-lg tracking-[0.3em] font-bold" style="letter-spacing:0.3em;" autocomplete="one-time-code" inputmode="numeric">
                                <p class="text-xs text-gray-400 mt-2 text-center">Expires in 10 minutes.</p>
                            </div>
                            <div class="crop-modal-footer">
                                <button onclick="closePasswordOtpModal()" class="btn-secondary">Cancel</button>
                                <button id="verifyPasswordOtpBtn" onclick="verifyPasswordOtp()" class="btn-primary"><i class="fas fa-check"></i> Verify &amp; Change</button>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function() {
                        var passwordForm = document.getElementById('passwordForm');
                        var changePasswordBtn = document.getElementById('changePasswordBtn');
                        var successMsg = document.getElementById('passwordSuccessMsg');
                        var successText = document.getElementById('passwordSuccessText');

                        if (passwordForm) {
                            passwordForm.addEventListener('submit', function(e) {
                                e.preventDefault();

                                var currentPw = document.getElementById('currentPassword').value;
                                var newPw = document.getElementById('newPassword').value;
                                var confirmPw = document.getElementById('confirmPassword').value;

                                if (!currentPw || !newPw || !confirmPw) {
                                    showPwToast('Please fill in all password fields.', 'error');
                                    return;
                                }

                                changePasswordBtn.disabled = true;
                                changePasswordBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validating...';

                                var formData = new FormData();
                                formData.append('action', 'send_password_otp');
                                formData.append('current_password', currentPw);
                                formData.append('new_password', newPw);
                                formData.append('confirm_password', confirmPw);
                                formData.append('csrf_token', '<?php echo $csrf_token; ?>');

                                fetch('<?php echo BASE_URL; ?>index.php?page=profile&action=profile_ajax', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(function(r) { return r.json(); })
                                .then(function(data) {
                                    changePasswordBtn.disabled = false;
                                    changePasswordBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Change Password';
                                    if (data.success) {
                                        openPasswordOtpModal();
                                        showPwToast(data.message || 'OTP sent to your mobile number.', 'success');
                                    } else {
                                        showPwToast(data.message || 'Validation failed.', 'error');
                                    }
                                })
                                .catch(function() {
                                    changePasswordBtn.disabled = false;
                                    changePasswordBtn.innerHTML = '<i class="fas fa-shield-alt"></i> Change Password';
                                    showPwToast('Network error. Please try again.', 'error');
                                });
                            });
                        }

                        window.openPasswordOtpModal = function() {
                            document.getElementById('passwordOtpModal').classList.add('active');
                            document.getElementById('passwordOtpInput').value = '';
                            document.getElementById('passwordOtpInput').focus();
                        };
                        window.closePasswordOtpModal = function() {
                            document.getElementById('passwordOtpModal').classList.remove('active');
                        };

                        window.verifyPasswordOtp = function() {
                            var otp = document.getElementById('passwordOtpInput').value.trim();
                            if (otp.length !== 6) {
                                showPwToast('Please enter the 6-digit OTP.', 'error');
                                return;
                            }
                            var btn = document.getElementById('verifyPasswordOtpBtn');
                            btn.disabled = true;
                            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...';

                            var formData = new FormData();
                            formData.append('action', 'verify_password_otp');
                            formData.append('otp', otp);
                            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

                            fetch('<?php echo BASE_URL; ?>index.php?page=profile&action=profile_ajax', {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-check"></i> Verify &amp; Change';
                                if (data.success) {
                                    window.closePasswordOtpModal();
                                    document.getElementById('currentPassword').value = '';
                                    document.getElementById('newPassword').value = '';
                                    document.getElementById('confirmPassword').value = '';
                                    if (typeof updatePwChecks === 'function') updatePwChecks();
                                    successMsg.style.display = 'block';
                                    successText.textContent = data.message || 'Password changed successfully!';
                                    successMsg.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    showPwToast(data.message || 'Password changed successfully!', 'success');
                                } else {
                                    showPwToast(data.message || 'Invalid or expired OTP.', 'error');
                                }
                            })
                            .catch(function() {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-check"></i> Verify &amp; Change';
                                showPwToast('Network error. Please try again.', 'error');
                            });
                        };

                        function showPwToast(message, type) {
                            var toast = document.createElement('div');
                            var colors = {
                                success: 'bg-green-50 border-green-500 text-green-700',
                                error: 'bg-red-50 border-red-500 text-red-700',
                                info: 'bg-blue-50 border-blue-500 text-blue-700'
                            };
                            var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle' };
                            toast.className = 'fixed bottom-6 right-6 z-[99999] max-w-sm p-4 rounded-xl border-l-4 shadow-lg text-sm font-medium transition-all duration-300 transform translate-y-2 opacity-0 ' + (colors[type] || colors.info);
                            toast.innerHTML = '<div class="flex items-center gap-2"><i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + message + '</span></div>';
                            document.body.appendChild(toast);
                            requestAnimationFrame(function() { toast.style.transform = 'translateY(0)'; toast.style.opacity = '1'; });
                            setTimeout(function() {
                                toast.style.transform = 'translateY(20px)'; toast.style.opacity = '0';
                                setTimeout(function() { toast.remove(); }, 300);
                            }, 3500);
                        }
                    })();
                    </script>
