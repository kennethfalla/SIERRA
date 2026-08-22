<?php
// views/shared/profile/personal_info.php - partial, included by views/shared/profile/profile.php
?>
                    <!-- ========== PERSONAL INFORMATION ========== -->
                    <div id="section-personal-info">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-id-card text-[#10A37F]"></i>
                                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Personal Information</h3>
                            </div>
                            <button id="editToggleBtn" class="btn-secondary inline-flex items-center gap-1.5 md:gap-2 w-full sm:w-auto justify-center flex-shrink-0">
                                <i class="fas fa-pen text-xs md:text-sm"></i> Edit Profile
                            </button>
                        </div>
                        
                        <!-- View Mode -->
                        <div id="viewSection">
                            <div class="space-y-5">
                                <!-- Basic Information Section -->
                                <div class="bg-white rounded-lg border border-gray-100 p-4">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <i class="fas fa-user text-[#10A37F]"></i> Basic Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="metric-item">
                                            <span class="metric-label">First Name</span>
                                            <span class="metric-value"><?php echo htmlspecialchars($user['first_name']); ?></span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Last Name</span>
                                            <span class="metric-value"><?php echo htmlspecialchars($user['last_name']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information Section -->
                                <div class="bg-white rounded-lg border border-gray-100 p-4">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <i class="fas fa-phone text-[#10A37F]"></i> Contact Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="metric-item">
                                            <span class="metric-label">Email Address</span>
                                            <span class="metric-value"><?php echo htmlspecialchars($user['email']); ?></span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Mobile Number</span>
                                            <span class="metric-value"><?php echo htmlspecialchars($user['contact_number']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information Section -->
                                <div class="bg-white rounded-lg border border-gray-100 p-4">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <i class="fas fa-map-marked-alt text-[#10A37F]"></i> Address Information
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="metric-item">
                                            <span class="metric-label">Resident Type</span>
                                            <span class="metric-value">
                                                <?php if($user['is_resident']): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-check-circle"></i> Resident
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-info-circle"></i> Non-Resident
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if($user['is_resident']): ?>
                                            <!-- Resident Address Fields -->
                                            <div class="metric-item">
                                                <span class="metric-label">Barangay</span>
                                                <span class="metric-value"><?php echo htmlspecialchars($barangay_name ?: '—'); ?></span>
                                            </div>
                                            <div class="metric-item md:col-span-2">
                                                <span class="metric-label">Purok/Street/Subdivision</span>
                                                <span class="metric-value"><?php echo htmlspecialchars($user['purok_street'] ?? '—'); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <!-- Non-Resident Address Fields -->
                                            <div class="metric-item">
                                                <span class="metric-label">Province</span>
                                                <span class="metric-value"><?php echo htmlspecialchars($user['province'] ?? '—'); ?></span>
                                            </div>
                                            <div class="metric-item">
                                                <span class="metric-label">Municipality</span>
                                                <span class="metric-value"><?php echo htmlspecialchars($user['municipality'] ?? '—'); ?></span>
                                            </div>
                                            <div class="metric-item md:col-span-2">
                                                <span class="metric-label">Barangay/Street Address</span>
                                                <span class="metric-value"><?php echo htmlspecialchars($user['non_resident_address'] ?? '—'); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Account Information Section -->
                                <div class="bg-white rounded-lg border border-gray-100 p-4">
                                    <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <i class="fas fa-shield-alt text-[#10A37F]"></i> Account Status
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div class="metric-item">
                                            <span class="metric-label">Account Status</span>
                                            <span class="metric-value">
                                                <?php if($user['is_active']): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-green-50 text-green-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-check-circle"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-red-50 text-red-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-times-circle"></i> Inactive
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Verification Status</span>
                                            <span class="metric-value">
                                                <?php if($user['is_verified']): ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-badge-check"></i> Verified
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-yellow-50 text-yellow-700 text-xs rounded-full font-semibold">
                                                        <i class="fas fa-exclamation-circle"></i> Unverified
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="metric-item">
                                            <span class="metric-label">Member Since</span>
                                            <span class="metric-value"><?php echo $join_date; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Mode -->
                        <div id="editSection" style="display: none;">

                            <!-- Centered avatar + name, mockup-style -->
                            <div class="flex flex-col items-center text-center gap-2 mb-6">
                                <div class="avatar" style="width:88px;height:88px;font-size:1.9rem;" id="avatarContainerEdit" title="Change profile photo">
                                    <?php if ($profile_pic_url): ?>
                                        <img src="<?php echo $profile_pic_url; ?>" alt="Profile">
                                    <?php else: ?>
                                        <span class="initials"><?php echo $initials; ?></span>
                                    <?php endif; ?>
                                    <div class="avatar-upload-overlay"><i class="fas fa-camera text-sm"></i></div>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-800"><?php echo htmlspecialchars($full_name); ?></h3>
                                    <p class="text-xs text-gray-400">@<?php echo htmlspecialchars(strtolower(str_replace(' ', '', $full_name))); ?></p>
                                </div>
                            </div>

                            <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
                                <input type="hidden" name="update_profile" value="1">
                                <input type="hidden" name="cropped_image" id="croppedImage" value="">

                                <div class="space-y-3">
                                    <!-- Full name -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div class="boxed-field">
                                            <label class="boxed-label">First Name</label>
                                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        <div class="boxed-field">
                                            <label class="boxed-label">Last Name</label>
                                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Contact -->
                                    <div class="boxed-field">
                                        <div class="flex items-center justify-between">
                                            <label class="boxed-label">Mobile Number</label>
                                            <span id="phoneVerifiedBadge" style="display:none;" class="inline-flex items-center gap-1 text-[10px] font-bold text-green-600">
                                                <i class="fas fa-check-circle"></i> Verified
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="tel" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number']); ?>" pattern="09[0-9]{9}" required class="flex-1">
                                            <button type="button" id="verifyPhoneBtn" onclick="sendPhoneOtp()" style="display:none;" class="btn-secondary whitespace-nowrap text-xs">
                                                <i class="fas fa-shield-alt"></i> Verify Number
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1" id="phoneHint">Changing your number requires SMS verification.</p>
                                    </div>
                                    <div class="boxed-field">
                                        <div class="flex items-center justify-between">
                                            <label class="boxed-label">Email Address</label>
                                            <span id="emailVerifiedBadge" style="display:none;" class="inline-flex items-center gap-1 text-[10px] font-bold text-green-600">
                                                <i class="fas fa-check-circle"></i> Verified
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="flex-1">
                                            <button type="button" id="confirmEmailBtn" onclick="sendEmailConfirm()" style="display:none;" class="btn-secondary whitespace-nowrap text-xs">
                                                <i class="fas fa-envelope-check"></i> Confirm Email
                                            </button>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-1" id="emailHint">Changing your email requires a confirmation code.</p>
                                    </div>

                                    <!-- Address -->
                                    <?php if($user['is_resident']): ?>
                                        <div class="boxed-field">
                                            <label class="boxed-label">Barangay</label>
                                            <select name="barangay_id">
                                                <option value="">Select Barangay</option>
                                                <?php foreach($barangays as $b): ?>
                                                <option value="<?php echo $b['id']; ?>" <?php echo ($user['barangay_id'] == $b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="boxed-field">
                                            <label class="boxed-label">Purok/Street/Subdivision</label>
                                            <input type="text" name="purok_street" value="<?php echo htmlspecialchars($user['purok_street'] ?? ''); ?>">
                                        </div>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div class="boxed-field">
                                                <label class="boxed-label">Province</label>
                                                <input type="text" name="province" value="<?php echo htmlspecialchars($user['province'] ?? ''); ?>" readonly>
                                            </div>
                                            <div class="boxed-field">
                                                <label class="boxed-label">Municipality</label>
                                                <input type="text" name="municipality" value="<?php echo htmlspecialchars($user['municipality'] ?? ''); ?>" readonly>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-400 -mt-1">Province and municipality can't be changed. Contact support if needed.</p>
                                        <div class="boxed-field">
                                            <label class="boxed-label">Barangay/Street Address</label>
                                            <input type="text" name="non_resident_address" value="<?php echo htmlspecialchars($user['non_resident_address'] ?? ''); ?>">
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex flex-wrap gap-3 mt-6">
                                    <button type="submit" class="btn-primary w-full md:w-auto justify-center">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                    <button type="button" id="cancelEditBtn" class="btn-secondary">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>