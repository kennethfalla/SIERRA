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
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-save"></i> Update Password
                                </button>
                                <button type="reset" class="btn-secondary">
                                    <i class="fas fa-undo"></i> Clear
                                </button>
                            </div>
                        </form>
                    </div>
