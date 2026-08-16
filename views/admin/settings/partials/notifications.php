<?php
// views/admin/settings/partials/notifications.php - Notification Templates & SMS Gateway Settings (iProg Only)
// This file is included in the Settings page under the "Notifications" tab

// Load current settings
$templates = [
    'template_submitted' => SettingsHelper::get('template_submitted', ''),
    'template_status_update' => SettingsHelper::get('template_status_update', ''),
    'template_resolved' => SettingsHelper::get('template_resolved', ''),
    'template_escalated' => SettingsHelper::get('template_escalated', ''),
    'template_staff_account_created' => SettingsHelper::get('template_staff_account_created', '')
];

// SMS Settings
$enable_sms = SettingsHelper::get('enable_sms_notifications', 0);
$sms_sender = SettingsHelper::get('sms_sender_name', 'SierraLGU');

// iProg Settings
$iprog_api = SettingsHelper::get('iprog_api_key', '');
$iprog_sender = SettingsHelper::get('iprog_sender_id', '');
$iprog_base_url = SettingsHelper::get('iprog_base_url', 'https://sms.iprogtech.com/api/v1/sms_messages');

// Brevo Email Settings (report receipts). InfinityFree free-tier domains
// aren't compatible with Mailgun, so Brevo is the only supported gateway.
$enable_email = SettingsHelper::get('enable_email_receipts', 1);
$brevo_api = SettingsHelper::get('brevo_api_key', '');
$brevo_sender_email = SettingsHelper::get('brevo_sender_email', 'menro@sanisidro.gov.ph');
$brevo_sender_name = SettingsHelper::get('brevo_sender_name', 'Sierra LGU');

// Generate CSRF token
$csrf_token = InputSanitizer::generateCsrfToken();

// Available placeholders for templates
$placeholders = [
    'General' => [
        '{system_name}' => 'System name (e.g., Sierra)',
        '{login_url}' => 'Login page URL'
    ],
    'User' => [
        '{first_name}' => 'User\'s first name',
        '{last_name}' => 'User\'s last name',
        '{full_name}' => 'User\'s full name',
        '{email}' => 'User\'s email address',
        '{contact_number}' => 'User\'s contact number',
        '{role}' => 'User\'s role (Barangay Official / MENRO Staff)'
    ],
    'Account' => [
        '{temp_password}' => 'Temporary password (for new staff accounts)'
    ],
    'Report' => [
        '{report_id}' => 'Report ID number',
        '{report_title}' => 'Report title',
        '{report_status}' => 'Current status of the report',
        '{barangay_name}' => 'Barangay name',
        '{category_name}' => 'Category name',
        '{severity_score}' => 'Severity score'
    ]
];
?>
<style>
    .template-card {
        background: #FAFAFA;
        border: 1px solid #E5E7EB;
        border-radius: 0.75rem;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        transition: all 0.2s ease;
    }
    .template-card:hover {
        border-color: #10A37F;
        background: #F5FBF6;
    }
    .template-card .template-label {
        font-weight: 600;
        color: #1F2937;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }
    .template-card .template-desc {
        font-size: 0.75rem;
        color: #6B7280;
        margin-bottom: 0.5rem;
    }
    .template-card textarea {
        width: 100%;
        padding: 0.6rem 0.75rem;
        border: 1.5px solid #E5E7EB;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        transition: all 0.2s;
        background: white;
        color: #1F2937;
        resize: vertical;
        min-height: 60px;
        font-family: 'Manrope', sans-serif;
    }
    .template-card textarea:focus {
        border-color: #10A37F;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
    }
    .template-card .char-count {
        text-align: right;
        font-size: 0.7rem;
        color: #9CA3AF;
        margin-top: 0.25rem;
    }
    
    .sms-gateway-card {
        background: white;
        border: 1px solid #E5E7EB;
        border-radius: 0.75rem;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        transition: all 0.2s ease;
    }
    .sms-gateway-card:hover {
        border-color: #10A37F;
    }
    .sms-gateway-card .gateway-title {
        font-weight: 700;
        color: #1F2937;
        font-size: 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .sms-gateway-card .gateway-title .badge {
        font-size: 0.6rem;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-weight: 600;
    }
    .badge-active {
        background: #D1FAE5;
        color: #065F46;
    }
    .badge-inactive {
        background: #FEE2E2;
        color: #991B1B;
    }
    
    .form-group {
        margin-bottom: 0.75rem;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        color: #374151;
        font-size: 0.8rem;
        margin-bottom: 0.2rem;
    }
    .form-group .form-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1.5px solid #E5E7EB;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        transition: all 0.2s;
        background: white;
        color: #1F2937;
    }
    .form-group .form-input:focus {
        border-color: #10A37F;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
    }
    .form-group .help-text {
        font-size: 0.7rem;
        color: #6B7280;
        margin-top: 0.2rem;
    }
    .form-group .help-text a {
        color: #10A37F;
        text-decoration: none;
    }
    .form-group .help-text a:hover {
        text-decoration: underline;
    }
    
    .toggle-switch {
        position: relative;
        width: 48px;
        height: 28px;
        flex-shrink: 0;
        cursor: pointer;
        display: inline-block;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        inset: 0;
        background: #D1D5DB;
        border-radius: 9999px;
        transition: all 0.3s;
    }
    .toggle-slider::before {
        content: '';
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background: white;
        border-radius: 50%;
        transition: all 0.3s;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .toggle-switch input:checked + .toggle-slider {
        background: #10A37F;
    }
    .toggle-switch input:checked + .toggle-slider::before {
        transform: translateX(20px);
    }
    
    .placeholder-badge {
        display: inline-block;
        background: #EDE9FE;
        color: #5B21B6;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.1rem 0.4rem;
        border-radius: 0.25rem;
        font-family: monospace;
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid #DDD6FE;
    }
    .placeholder-badge:hover {
        background: #C4B5FD;
        color: white;
        border-color: #8B5CF6;
    }
    
    .placeholder-group {
        margin-bottom: 0.75rem;
    }
    .placeholder-group .group-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: #6B7280;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.25rem;
        display: block;
    }
    .placeholder-group .placeholders {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
    }
    
    @media (max-width: 768px) {
        .template-card {
            padding: 0.75rem;
        }
        .sms-gateway-card {
            padding: 0.75rem;
        }
        .placeholder-group .placeholders {
            gap: 0.2rem;
        }
        .placeholder-badge {
            font-size: 0.55rem;
            padding: 0.05rem 0.3rem;
        }
    }
</style>

<form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=notifications" enctype="multipart/form-data" id="notificationsForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    
    <!-- ============================================================ -->
    <!-- SECTION 1: PLACEHOLDERS REFERENCE -->
    <!-- ============================================================ -->
    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
        <div class="flex items-start gap-3">
            <i class="fas fa-info-circle text-blue-500 mt-0.5 text-lg"></i>
            <div>
                <h4 class="font-bold text-blue-800 text-sm">Available Placeholders</h4>
                <p class="text-xs text-blue-600 mb-2">Use these placeholders in your templates. They will be replaced with actual data when notifications are sent.</p>
                
                <?php foreach($placeholders as $group => $items): ?>
                <div class="placeholder-group">
                    <span class="group-label"><?php echo $group; ?></span>
                    <div class="placeholders">
                        <?php foreach($items as $placeholder => $description): ?>
                        <span class="placeholder-badge" title="<?php echo htmlspecialchars($description); ?>" onclick="insertPlaceholder(this, '<?php echo $placeholder; ?>')">
                            <?php echo htmlspecialchars($placeholder); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <p class="text-xs text-blue-600 mt-2">
                    <i class="fas fa-mouse-pointer mr-1"></i>
                    Click any placeholder to insert it into the active textarea.
                </p>
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- SECTION 2: EMAIL/SMS TEMPLATES -->
    <!-- ============================================================ -->
    <div class="mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-envelope text-[#10A37F]"></i>
            Notification Templates
        </h3>
        <p class="text-sm text-gray-500 mb-4">Customize the messages sent to users via email and SMS.</p>
        
        <!-- Staff Account Created Template -->
        <div class="template-card" id="template-staff-account">
            <div class="flex justify-between items-start">
                <div>
                    <div class="template-label">
                        <i class="fas fa-user-plus text-[#10A37F] mr-2"></i>
                        Staff Account Created
                    </div>
                    <div class="template-desc">
                        Sent when a new Barangay Official or MENRO Staff account is created. 
                        <span class="text-red-500 font-medium">Includes temporary password!</span>
                    </div>
                </div>
                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">Required</span>
            </div>
            <textarea name="template_staff_account_created" id="template_staff_account_created" rows="4" 
                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($templates['template_staff_account_created'] ?: 'Hello {first_name}, an official {role} account has been created for you. Username: {email}. Temporary Password: {temp_password}. Login: {login_url}'); ?></textarea>
            <div class="char-count">
                <span id="staff_count">0</span> characters
            </div>
        </div>
        
        <!-- Report Submitted Template -->
        <div class="template-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="template-label">
                        <i class="fas fa-paper-plane text-[#10A37F] mr-2"></i>
                        Report Submitted
                    </div>
                    <div class="template-desc">Sent to the citizen when they successfully submit a report.</div>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">Optional</span>
            </div>
            <textarea name="template_submitted" id="template_submitted" rows="3" 
                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($templates['template_submitted']); ?></textarea>
            <div class="char-count">
                <span id="submitted_count">0</span> characters
            </div>
        </div>
        
        <!-- Status Update Template -->
        <div class="template-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="template-label">
                        <i class="fas fa-sync-alt text-[#10A37F] mr-2"></i>
                        Status Update
                    </div>
                    <div class="template-desc">Sent when a report status changes (e.g., In Progress, Escalated).</div>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">Optional</span>
            </div>
            <textarea name="template_status_update" id="template_status_update" rows="3" 
                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($templates['template_status_update']); ?></textarea>
            <div class="char-count">
                <span id="status_count">0</span> characters
            </div>
        </div>
        
        <!-- Report Resolved Template -->
        <div class="template-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="template-label">
                        <i class="fas fa-check-circle text-[#10A37F] mr-2"></i>
                        Report Resolved
                    </div>
                    <div class="template-desc">Sent when a report is marked as resolved.</div>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">Optional</span>
            </div>
            <textarea name="template_resolved" id="template_resolved" rows="3" 
                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($templates['template_resolved']); ?></textarea>
            <div class="char-count">
                <span id="resolved_count">0</span> characters
            </div>
        </div>
        
        <!-- Report Escalated Template -->
        <div class="template-card">
            <div class="flex justify-between items-start">
                <div>
                    <div class="template-label">
                        <i class="fas fa-share-alt text-[#10A37F] mr-2"></i>
                        Report Escalated
                    </div>
                    <div class="template-desc">Sent when a report is escalated to MENRO.</div>
                </div>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full font-medium">Optional</span>
            </div>
            <textarea name="template_escalated" id="template_escalated" rows="3" 
                      oninput="updateCharCount(this)"><?php echo htmlspecialchars($templates['template_escalated']); ?></textarea>
            <div class="char-count">
                <span id="escalated_count">0</span> characters
            </div>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- SECTION 3: Brevo EMAIL SETTINGS (Official Report Receipts) -->
    <!-- ============================================================ -->
    <div class="mb-6 border-t border-gray-200 pt-6">
        <input type="hidden" name="email_gateway" value="brevo">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-envelope-open-text text-[#10A37F]"></i>
            Brevo Email (Report Receipts)
        </h3>
        <p class="text-sm text-gray-500 mb-4">
            InfinityFree does not support PHP <code>mail()</code>, so official report receipt emails are sent through the
            <a href="https://www.brevo.com" target="_blank" rel="noopener" class="text-[#10A37F] font-medium">Brevo</a> API (free tier: 300 emails/day, no custom domain required).
            Email is used <strong>only</strong> for official report receipts — SMS remains the channel for OTP login/reset.
        </p>

        <!-- Enable Email Receipts Toggle -->
        <div class="sms-gateway-card">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="gateway-title">
                        <i class="fas fa-toggle-on text-[#10A37F]"></i>
                        Enable Email Receipts
                        <span class="badge <?php echo $enable_email ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $enable_email ? 'Active' : 'Disabled'; ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">When enabled, a receipt email is sent to citizens when they submit a report, and again whenever its status changes (verified, resolved, rejected, or escalated).</p>
                </div>
                <div class="toggle-switch">
                    <input type="checkbox" name="enable_email_receipts" id="enable_email" value="1" <?php echo $enable_email ? 'checked' : ''; ?> onchange="toggleEmailFields()">
                    <label class="toggle-slider" for="enable_email"></label>
                </div>
            </div>
        </div>

        <!-- Brevo Settings -->
        <div class="sms-gateway-card <?php echo $brevo_api ? 'border-green-200 bg-green-50/30' : 'border-gray-200'; ?>">
            <div class="gateway-title">
                <i class="fas fa-paper-plane text-blue-500"></i>
                Brevo
                <span class="badge <?php echo $brevo_api ? 'badge-active' : 'badge-inactive'; ?>">
                    <?php echo $brevo_api ? 'Configured' : 'Not Configured'; ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-3">Get your API key from <a href="https://app.brevo.com/settings/keys/api" target="_blank" rel="noopener" class="text-[#10A37F] font-medium">Brevo &rarr; SMTP &amp; API &rarr; API Keys</a> (free plan, no credit card, no custom domain needed).</p>

            <div class="form-group">
                <label for="brevo_api_key">Brevo API Key <span class="text-red-500">*</span></label>
                <input type="text" name="brevo_api_key" id="brevo_api_key" class="form-input"
                       value="<?php echo htmlspecialchars($brevo_api); ?>"
                       placeholder="xkeysib-...">
                <div class="help-text">Your Brevo API key (starts with <code>xkeysib-</code>). Keep this secure.</div>
            </div>

            <div class="form-group">
                <label for="brevo_sender_email">Sender Email <span class="text-red-500">*</span></label>
                <input type="email" name="brevo_sender_email" id="brevo_sender_email" class="form-input"
                       value="<?php echo htmlspecialchars($brevo_sender_email); ?>"
                       placeholder="noreply@yourdomain.com">
                <div class="help-text">Must be a verified sender in Brevo (Senders, Domains &amp; Dedicated IPs &rarr; Senders). A plain email address works — no domain verification required on Brevo's free tier.</div>
            </div>

            <div class="form-group">
                <label for="brevo_sender_name">Sender Name</label>
                <input type="text" name="brevo_sender_name" id="brevo_sender_name" class="form-input"
                       value="<?php echo htmlspecialchars($brevo_sender_name); ?>"
                       placeholder="Sierra LGU">
                <div class="help-text">Shown as the sender display name on receipts (e.g., "Sierra LGU").</div>
            </div>
        </div>

        <!-- Email Gateway Status -->
        <div class="sms-gateway-card">
            <div class="gateway-title">
                <i class="fas fa-info-circle text-blue-500"></i>
                Brevo Gateway Status
            </div>
            <?php if ($brevo_api && $brevo_sender_email && $enable_email): ?>
                <div class="p-3 rounded-lg border border-green-200 bg-green-50">
                    <p class="font-semibold text-sm text-green-700">
                        <i class="fas fa-check-circle mr-1"></i>
                        Brevo is configured and ready to send report receipts.
                    </p>
                    <p class="text-xs text-green-600 mt-1">
                        API Key: <?php echo substr($brevo_api, 0, 10); ?>... (masked)
                    </p>
                    <p class="text-xs text-green-600">
                        Sender: <?php echo htmlspecialchars($brevo_sender_name . ' <' . $brevo_sender_email . '>'); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="p-3 rounded-lg border border-yellow-200 bg-yellow-50">
                    <p class="font-semibold text-sm text-yellow-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Brevo is not configured or email receipts are disabled.
                    </p>
                    <ul class="text-xs text-yellow-600 mt-1 list-disc list-inside">
                        <?php if (!$enable_email): ?>
                        <li>Email receipts are disabled. Enable the toggle above.</li>
                        <?php endif; ?>
                        <?php if (empty($brevo_api)): ?>
                        <li>API Key is missing. Enter your Brevo API key.</li>
                        <?php endif; ?>
                        <?php if (empty($brevo_sender_email)): ?>
                        <li>Sender email is missing. Enter and verify a sender email in Brevo.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION 3b: TEST EMAIL (Brevo) -->
    <!-- ============================================================ -->
    <div class="mb-6 border-t border-gray-200 pt-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-vial text-[#10A37F]"></i>
            Test Email
        </h3>
        <p class="text-sm text-gray-500 mb-4">Send a test email to verify your Brevo configuration.</p>

        <div class="sms-gateway-card">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label for="test_email_to" class="font-semibold text-sm text-gray-700 block mb-1">Test Email Address</label>
                    <input type="email" name="test_email_to" id="test_email_to" class="form-input"
                           placeholder="you@example.com">
                </div>
                <button type="button" onclick="sendTestEmail()" class="btn-primary px-6 py-2.5 text-white font-semibold rounded-xl" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test Email
                </button>
            </div>
            <div id="testEmailResult" class="mt-3" style="display: none;"></div>

            <?php if ($brevo_api): ?>
            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <i class="fas fa-check-circle mr-1"></i>
                    Brevo is configured. Click "Send Test Email" to verify.
                </p>
            </div>
            <?php else: ?>
            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Please configure Brevo (API key and sender email) above before testing.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SECTION 4: SMS GATEWAY SETTINGS (iProg Only) -->
    <!-- ============================================================ -->
    <div class="mb-6 border-t border-gray-200 pt-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-sms text-[#10A37F]"></i>
            SMS Gateway Settings (iProg)
        </h3>
        <p class="text-sm text-gray-500 mb-4">Configure your iProg SMS gateway to send SMS notifications.</p>
        
        <!-- Enable SMS Toggle -->
        <div class="sms-gateway-card">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="gateway-title">
                        <i class="fas fa-toggle-on text-[#10A37F]"></i>
                        Enable SMS Notifications
                        <span class="badge <?php echo $enable_sms ? 'badge-active' : 'badge-inactive'; ?>">
                            <?php echo $enable_sms ? 'Active' : 'Disabled'; ?>
                        </span>
                    </div>
                    <p class="text-sm text-gray-500">When enabled, SMS notifications will be sent using iProg.</p>
                </div>
                <div class="toggle-switch">
                    <input type="checkbox" name="enable_sms_notifications" id="enable_sms" value="1" <?php echo $enable_sms ? 'checked' : ''; ?> onchange="toggleSMSFields()">
                    <label class="toggle-slider" for="enable_sms"></label>
                </div>
            </div>
        </div>
        
        <!-- SMS Sender Name -->
        <div class="sms-gateway-card">
            <div class="form-group">
                <label for="sms_sender_name">SMS Sender Name</label>
                <input type="text" name="sms_sender_name" id="sms_sender_name" class="form-input" 
                       value="<?php echo htmlspecialchars($sms_sender); ?>" maxlength="11" 
                       placeholder="SierraLGU">
                <div class="help-text">Appears as the sender name on SMS (max 11 characters).</div>
            </div>
        </div>
        
        <!-- iProg Settings -->
        <div class="sms-gateway-card <?php echo $iprog_api ? 'border-green-200 bg-green-50/30' : 'border-gray-200'; ?>">
            <div class="gateway-title">
                <i class="fas fa-mobile-alt text-blue-500"></i>
                iProg SMS
                <span class="badge <?php echo $iprog_api ? 'badge-active' : 'badge-inactive'; ?>">
                    <?php echo $iprog_api ? 'Configured' : 'Not Configured'; ?>
                </span>
            </div>
            <p class="text-sm text-gray-500 mb-3">Philippine SMS gateway. Get your credentials from your iProg account dashboard.</p>
            
            <div class="form-group">
                <label for="iprog_api_key">API Token <span class="text-red-500">*</span></label>
                <!-- 🔥 FIXED: Changed from type="password" to type="text" so value is always submitted -->
                <input type="text" name="iprog_api_key" id="iprog_api_key" class="form-input" 
                       value="<?php echo htmlspecialchars($iprog_api); ?>" 
                       placeholder="Enter your iProg API Token">
                <div class="help-text">Your iProg API token from your account dashboard. Keep this secure.</div>
            </div>
            
            <div class="form-group">
                <label for="iprog_sender_id">Sender ID (Optional)</label>
                <input type="text" name="iprog_sender_id" id="iprog_sender_id" class="form-input" 
                       value="<?php echo htmlspecialchars($iprog_sender); ?>" 
                       placeholder="SierraLGU">
                <div class="help-text">Overrides the global sender name for iProg messages (max 11 chars). Leave blank to use the global sender name.</div>
            </div>
            
            <div class="form-group">
                <label for="iprog_base_url">API Endpoint</label>
                <input type="text" name="iprog_base_url" id="iprog_base_url" class="form-input" 
                       value="<?php echo htmlspecialchars($iprog_base_url); ?>" 
                       placeholder="https://sms.iprogtech.com/api/v1/sms_messages">
                <div class="help-text">The API endpoint for sending SMS. Use the URL provided by iProg.</div>
            </div>
        </div>
        
        <!-- Gateway Status -->
        <div class="sms-gateway-card">
            <div class="gateway-title">
                <i class="fas fa-info-circle text-blue-500"></i>
                Gateway Status
            </div>
            <?php if ($iprog_api && $enable_sms): ?>
                <div class="p-3 rounded-lg border border-green-200 bg-green-50">
                    <p class="font-semibold text-sm text-green-700">
                        <i class="fas fa-check-circle mr-1"></i>
                        iProg is configured and ready to send SMS.
                    </p>
                    <p class="text-xs text-green-600 mt-1">
                        API Token: <?php echo substr($iprog_api, 0, 8); ?>... (masked)
                    </p>
                    <p class="text-xs text-green-600">
                        Endpoint: <?php echo htmlspecialchars($iprog_base_url); ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="p-3 rounded-lg border border-yellow-200 bg-yellow-50">
                    <p class="font-semibold text-sm text-yellow-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        iProg is not configured or SMS is disabled.
                    </p>
                    <ul class="text-xs text-yellow-600 mt-1 list-disc list-inside">
                        <?php if (!$enable_sms): ?>
                        <li>SMS notifications are disabled. Enable the toggle above.</li>
                        <?php endif; ?>
                        <?php if (empty($iprog_api)): ?>
                        <li>API Token is missing. Enter your iProg API token.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- SECTION 5: TEST SMS -->
    <!-- ============================================================ -->
    <div class="mb-6 border-t border-gray-200 pt-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-vial text-[#10A37F]"></i>
            Test SMS
        </h3>
        <p class="text-sm text-gray-500 mb-4">Send a test SMS to verify your iProg configuration.</p>
        
        <div class="sms-gateway-card">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label for="test_phone" class="font-semibold text-sm text-gray-700 block mb-1">Test Mobile Number</label>
                    <input type="tel" name="test_phone" id="test_phone" class="form-input" 
                           placeholder="09123456789" value="09123456789">
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label for="test_message" class="font-semibold text-sm text-gray-700 block mb-1">Test Message</label>
                    <input type="text" name="test_message" id="test_message" class="form-input" 
                           value="Test SMS from Sierra - Your notification settings are working!">
                </div>
                <button type="button" onclick="sendTestSMS()" class="btn-primary px-6 py-2.5 text-white font-semibold rounded-xl" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
                    <i class="fas fa-paper-plane mr-2"></i>Send Test SMS
                </button>
            </div>
            <div id="testSMSResult" class="mt-3" style="display: none;"></div>
            
            <?php if ($iprog_api && $enable_sms): ?>
            <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <i class="fas fa-check-circle mr-1"></i>
                    iProg is configured and ready. Click "Send Test SMS" to verify.
                </p>
            </div>
            <?php else: ?>
            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-700">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Please configure iProg (API token) and enable SMS notifications above before testing.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-200">
        <button type="reset" onclick="resetForm()" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium text-sm">
            <i class="fas fa-undo mr-2"></i>Reset
        </button>
        <button type="submit" class="btn-primary px-6 py-2.5 text-white font-semibold rounded-xl flex items-center gap-2" style="background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);">
            <i class="fas fa-save"></i> Save Settings
        </button>
    </div>
</form>

<!-- ============================================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================================ -->
<script>
(function() {
    'use strict';
    
    // ============================================
    // DOM REFERENCES
    // ============================================
    const form = document.getElementById('notificationsForm');
    const enableSms = document.getElementById('enable_sms');
    const enableEmail = document.getElementById('enable_email');
    
    // ============================================
    // CHAR COUNTER
    // ============================================
    window.updateCharCount = function(textarea) {
        const count = textarea.value.length;
        const id = textarea.id.replace('template_', '') + '_count';
        const counter = document.getElementById(id);
        if (counter) {
            counter.textContent = count;
            // Color based on length
            if (count > 500) {
                counter.style.color = '#EF4444';
            } else if (count > 300) {
                counter.style.color = '#F59E0B';
            } else {
                counter.style.color = '#9CA3AF';
            }
        }
    };
    
    // Initialize character counts
    document.querySelectorAll('textarea[name^="template_"]').forEach(function(textarea) {
        window.updateCharCount(textarea);
    });
    
    // ============================================
    // PLACEHOLDER INSERTION
    // ============================================
    window.insertPlaceholder = function(element, placeholder) {
        // Find the nearest textarea in the same template card
        const card = element.closest('.template-card');
        if (!card) return;
        
        const textarea = card.querySelector('textarea');
        if (!textarea) return;
        
        // Insert placeholder at cursor position or at the end
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const before = text.substring(0, start);
        const after = text.substring(end);
        
        textarea.value = before + placeholder + after;
        textarea.focus();
        
        // Set cursor position after inserted placeholder
        const newPos = start + placeholder.length;
        textarea.selectionStart = newPos;
        textarea.selectionEnd = newPos;
        
        // Update char count
        window.updateCharCount(textarea);
        
        // Trigger input event for any listeners
        textarea.dispatchEvent(new Event('input'));
    };
    
    // ============================================
    // TOGGLE SMS FIELDS
    // ============================================
    window.toggleSMSFields = function() {
        const isEnabled = enableSms.checked;
        const inputs = document.querySelectorAll('.sms-gateway-card input, .sms-gateway-card select, .sms-gateway-card textarea');
        inputs.forEach(function(input) {
            // Skip the master toggle and all Brevo email fields
            if (input.id !== 'enable_sms' && input.id.indexOf('brevo_') !== 0 && input.id !== 'test_email_to') {
                input.disabled = !isEnabled;
                input.style.opacity = isEnabled ? '1' : '0.5';
            }
        });
        
        // Also toggle the test SMS button
        const testBtn = document.querySelector('[onclick="sendTestSMS()"]');
        if (testBtn) {
            testBtn.disabled = !isEnabled;
            testBtn.style.opacity = isEnabled ? '1' : '0.5';
        }
    };

    // ============================================
    // TOGGLE Brevo EMAIL FIELDS
    // ============================================
    window.toggleEmailFields = function() {
        const isEnabled = enableEmail.checked;
        const inputs = document.querySelectorAll('.sms-gateway-card input, .sms-gateway-card select, .sms-gateway-card textarea');
        inputs.forEach(function(input) {
            if (input.id.indexOf('brevo_') === 0 || input.id === 'test_email_to') {
                input.disabled = !isEnabled;
                input.style.opacity = isEnabled ? '1' : '0.5';
            }
        });
        
        // Also toggle the test email button
        const testBtn = document.querySelector('[onclick="sendTestEmail()"]');
        if (testBtn) {
            testBtn.disabled = !isEnabled;
            testBtn.style.opacity = isEnabled ? '1' : '0.5';
        }
    };
    
    // Initial state
    toggleSMSFields();
    toggleEmailFields();
    
    // ============================================
    // SEND TEST SMS
    // ============================================
    window.sendTestSMS = function() {
        const phone = document.getElementById('test_phone').value.trim();
        const message = document.getElementById('test_message').value.trim();
        const resultDiv = document.getElementById('testSMSResult');
        const testBtn = document.querySelector('[onclick="sendTestSMS()"]');
        
        if (!phone) {
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Please enter a test mobile number.</div>';
            resultDiv.style.display = 'block';
            return;
        }
        
        if (!message) {
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Please enter a test message.</div>';
            resultDiv.style.display = 'block';
            return;
        }
        
        // Disable button and show loading
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        resultDiv.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-700 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Sending test SMS...</div>';
        resultDiv.style.display = 'block';
        
        // Send AJAX request
        const formData = new FormData();
        formData.append('action', 'send_test_sms');
        formData.append('phone', phone);
        formData.append('message', message);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');

        // Include whatever is currently typed into the gateway fields (even if
        // "Save Settings" hasn't been clicked yet) so the test - and the saved
        // record - reflect the key the admin just entered, not a stale one.
        formData.append('iprog_api_key', document.getElementById('iprog_api_key').value.trim());
        formData.append('iprog_sender_id', document.getElementById('iprog_sender_id').value.trim());
        formData.append('iprog_base_url', document.getElementById('iprog_base_url').value.trim());
        formData.append('sms_sender_name', document.getElementById('sms_sender_name').value.trim());
        
        fetch('<?php echo BASE_URL; ?>controllers/SettingsController.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="bg-green-50 border border-green-200 rounded-lg p-3 text-green-700 text-sm"><i class="fas fa-check-circle mr-2"></i>' + data.message + '</div>';
            } else {
                // Show detailed error
                let errorMsg = data.message;
                if (data.diagnostic) {
                    errorMsg += '<br><br><small>Diagnostic: SMS enabled? ' + (data.diagnostic.sms_enabled ? 'Yes' : 'No') + '</small>';
                }
                resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>' + errorMsg + '</div>';
            }
        })
        .catch(function(error) {
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Error sending test SMS: ' + error.message + '</div>';
        })
        .finally(function() {
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Test SMS';
        });
    };
    
    // ============================================
    // SEND TEST EMAIL (Brevo)
    // ============================================
    window.sendTestEmail = function() {
        const toEmail = document.getElementById('test_email_to').value.trim();
        const resultDiv = document.getElementById('testEmailResult');
        const testBtn = document.querySelector('[onclick="sendTestEmail()"]');

        if (!toEmail) {
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Please enter a test email address.</div>';
            resultDiv.style.display = 'block';
            return;
        }

        // Disable button and show loading
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sending...';
        resultDiv.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-700 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Sending test email...</div>';
        resultDiv.style.display = 'block';

        // Send AJAX request
        const formData = new FormData();
        formData.append('action', 'send_test_email');
        formData.append('to_email', toEmail);
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');

        // Include whatever is currently typed into the Brevo fields (even if
        // "Save Settings" hasn't been clicked yet) so the test reflects the
        // key the admin just entered, not a stale one.
        formData.append('brevo_api_key', document.getElementById('brevo_api_key').value.trim());
        formData.append('brevo_sender_email', document.getElementById('brevo_sender_email').value.trim());
        formData.append('brevo_sender_name', document.getElementById('brevo_sender_name').value.trim());

        fetch('<?php echo BASE_URL; ?>controllers/SettingsController.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="bg-green-50 border border-green-200 rounded-lg p-3 text-green-700 text-sm"><i class="fas fa-check-circle mr-2"></i>' + data.message + '</div>';
            } else {
                let errorMsg = data.message;
                if (data.diagnostic) {
                    errorMsg += '<br><br><small>Diagnostic: configured? ' + (data.diagnostic.configured ? 'Yes' : 'No') + ' | enabled? ' + (data.diagnostic.enabled ? 'Yes' : 'No') + '</small>';
                }
                resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>' + errorMsg + '</div>';
            }
        })
        .catch(function(error) {
            resultDiv.innerHTML = '<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm"><i class="fas fa-exclamation-circle mr-2"></i>Error sending test email: ' + error.message + '</div>';
        })
        .finally(function() {
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i>Send Test Email';
        });
    };

    // ============================================
    // RESET FORM
    // ============================================
    window.resetForm = function() {
        if (confirm('Reset all fields to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };
    
    // ============================================
    // UNSAVED CHANGES WARNING
    // ============================================
    let formChanged = false;
    
    form.addEventListener('input', function() {
        formChanged = true;
    });
    
    form.addEventListener('submit', function() {
        formChanged = false;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
    
    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(e) {
        // Ctrl+Enter to submit
        if (e.ctrlKey && e.key === 'Enter') {
            form.dispatchEvent(new Event('submit'));
        }
    });
    
})();
</script>

<!-- ============================================================ -->
<!-- ADDITIONAL CSS FOR PRIMARY BUTTON -->
<!-- ============================================================ -->
<style>
    .btn-primary {
        background: linear-gradient(135deg, #10A37F 0%, #0D8568 100%);
        color: white;
        border: none;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
    }
    .btn-primary:active {
        transform: translateY(0);
    }
    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }
</style>