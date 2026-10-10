<?php
// views/admin/settings/partials/reporting.php
// Report submission limits and automatic staff follow-ups.

$csrf_token = InputSanitizer::generateCsrfToken();
$limits = SettingsHelper::getReportLimits();
$reminders = SettingsHelper::getReportReminderSettings();
?>
<style>
    .rl-card {
        background: #ffffff;
        border: 1px solid #edf2ef;
        border-radius: 1rem;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        transition: border-color .2s, box-shadow .2s;
    }
    .rl-card:hover {
        border-color: rgba(16, 163, 127, 0.35);
        box-shadow: 0 4px 14px rgba(16, 163, 127, 0.06);
    }
    .rl-title {
        font-weight: 700;
        color: var(--sierra-type-primary, #203b31);
        font-size: 0.95rem;
        margin-bottom: 0.3rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .rl-desc {
        font-size: 0.78rem;
        color: var(--sierra-type-muted, #63746b);
        line-height: 1.45;
        margin-bottom: 0.6rem;
    }
    .rl-badge {
        font-size: 0.6rem;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .rl-badge.on  { background: #d1fae5; color: #065f46; }
    .rl-badge.off { background: #fee2e2; color: #991b1b; }
    .rl-form-group {
        margin-bottom: 0.9rem;
    }
    .rl-form-group label {
        display: block;
        font-weight: 600;
        color: var(--sierra-type-primary, #203b31);
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }
    .rl-form-group .rl-input {
        width: 100%;
        padding: 0.55rem 0.75rem;
        border: 1.5px solid #e5ece8;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        transition: all 0.2s;
        background: white;
        color: var(--sierra-type-primary, #203b31);
        max-width: 220px;
    }
    .rl-form-group .rl-input:focus {
        border-color: #10A37F;
        outline: none;
        box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.08);
    }
    .rl-help {
        font-size: 0.7rem;
        color: var(--sierra-type-muted, #63746b);
        margin-top: 0.2rem;
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
        background: #d1d5db;
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
    .rl-preview {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 0.9rem 1rem;
        font-size: 0.8rem;
        color: #475569;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 640px) {
        .rl-card { padding: 1rem; }
    }
    @media (max-width: 480px) {
        .rl-form-group .rl-input { max-width: 100%; width: 100%; }
        .btn-primary, .btn-secondary { width: 100%; justify-content: center; text-align: center; }
    }
</style>

<div class="rl-preview mb-5 flex items-start gap-3">
    <i class="fas fa-sliders-h mt-0.5" aria-hidden="true"></i>
    <div>
        <strong>Report settings.</strong> Control citizen submission limits and automatic staff follow-up reminders.
    </div>
</div>

<form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=reporting">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <div class="rl-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="rl-title"><i class="fas fa-bell text-[#10A37F]" aria-hidden="true"></i> Report Follow-up Reminders</div>
                <p class="rl-desc">Notify Barangay about overdue pending reports, and notify MENRO and Barangay about overdue escalated reports.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_report_reminders" value="1" aria-label="Enable report follow-up reminders" <?php echo $reminders['enabled'] ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <div class="rl-form-group">
            <label for="report_reminder_days">Remind after (days)</label>
            <input type="number" class="rl-input" id="report_reminder_days" name="report_reminder_days" min="1" max="365" required value="<?php echo (int)$reminders['days']; ?>">
            <p class="rl-help">Default: 3 days. Pending time starts at submission; escalated time starts at escalation.</p>
        </div>
        <p class="rl-desc">Each report stage sends one in-app reminder. Overdue reports stay on the dashboard until their status changes. Submission limits below work separately.</p>
    </div>

    <!-- Enable Limits Toggle -->
    <div class="rl-card">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="rl-title">
                    <i class="fas fa-stopwatch text-[#10A37F]"></i>
                    Enable Report Rate Limits
                    <span class="rl-badge <?php echo $limits['enabled'] ? 'on' : 'off'; ?>">
                        <?php echo $limits['enabled'] ? 'On' : 'Off'; ?>
                    </span>
                </div>
                <p class="rl-desc">Master switch for both limits below. Turn off to allow unlimited report submissions.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_report_limits" value="1" <?php echo $limits['enabled'] ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
    </div>

    <!-- Daily Limit -->
    <div class="rl-card">
        <div class="rl-title">
            <i class="fas fa-calendar-day text-[#10A37F]"></i>
            Daily Report Limit
        </div>
        <p class="rl-desc">Maximum number of reports a single citizen can submit per calendar day. Enter <strong>0</strong> for unlimited.</p>
        <div class="rl-form-group">
            <label for="report_daily_limit">Reports per day</label>
            <input type="number" name="report_daily_limit" id="report_daily_limit" class="rl-input"
                   min="0" max="100" value="<?php echo (int)$limits['daily_limit']; ?>">
            <div class="rl-help">Range: 0–100. Default: 5.</div>
        </div>
    </div>

    <!-- Min Interval -->
    <div class="rl-card">
        <div class="rl-title">
            <i class="fas fa-hourglass-half text-[#10A37F]"></i>
            Minimum Interval Between Reports
        </div>
        <p class="rl-desc">How long a citizen must wait after submitting a report before they can submit another one. Enter <strong>0</strong> for no wait.</p>
        <div class="rl-form-group">
            <label for="report_min_interval_minutes">Minutes</label>
            <input type="number" name="report_min_interval_minutes" id="report_min_interval_minutes" class="rl-input"
                   min="0" max="1440" value="<?php echo (int)$limits['min_interval_minutes']; ?>">
            <div class="rl-help">Range: 0–1440 (1 day). Default: 10 minutes.</div>
        </div>
    </div>

    <!-- Preview of active rules -->
    <div class="rl-preview mb-5">
        <i class="fas fa-info-circle text-[#10A37F] mr-1"></i>
        <strong>Active rule:</strong>
        <?php if (!$limits['enabled']): ?>
            Rate limits are currently <strong>disabled</strong> — citizens may submit reports freely.
        <?php else: ?>
            Each citizen may submit up to <strong><?php echo $limits['daily_limit'] > 0 ? $limits['daily_limit'] : 'unlimited'; ?> report(s) per day</strong>
            <?php if ($limits['min_interval_minutes'] > 0): ?>
                and must wait <strong><?php echo $limits['min_interval_minutes']; ?> minute(s)</strong> between submissions.
            <?php else: ?>
                with no waiting interval between submissions.
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap gap-3 justify-end pt-2 border-t border-gray-100">
        <button type="button" onclick="location.reload()" class="btn-secondary flex items-center gap-2">
            <i class="fas fa-undo"></i> Reset
        </button>
        <button type="submit" class="btn-primary flex items-center gap-2">
            <i class="fas fa-save"></i> Save Changes
        </button>
    </div>
</form>
