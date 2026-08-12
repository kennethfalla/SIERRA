<?php
// views/admin/settings/partials/features.php
// MASTER KILL SWITCHES
// If something breaks, MENRO can turn a feature off here without touching code.
// Every toggle below is wired into its runtime enforcement point.

$csrf_token = InputSanitizer::generateCsrfToken();

$ks = function ($key, $default = 0) {
    return (int)SettingsHelper::get($key, $default) === 1;
};

$maintenance_active = $ks('maintenance_mode');
?>

<style>
    .ks-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #6b7280;
        margin-bottom: 0.6rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .ks-section-title .ks-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #10A37F;
    }
    .ks-card {
        background: #ffffff;
        border: 1px solid #edf2ef;
        border-radius: 1rem;
        overflow: hidden;
        transition: border-color .2s, box-shadow .2s;
    }
    .ks-card:hover {
        border-color: rgba(16, 163, 127, 0.35);
        box-shadow: 0 4px 14px rgba(16, 163, 127, 0.06);
    }
    .ks-card.danger {
        border-color: #fee2e2;
        background: linear-gradient(180deg, #fff7f7, #ffffff);
        box-shadow: 0 4px 18px rgba(239, 68, 68, 0.08);
    }
    .ks-toggle-row {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        padding: 1.1rem 1.25rem;
    }
    .ks-toggle-row:not(:last-child) {
        border-bottom: 1px solid #f3f5f4;
    }
    .ks-toggle-icon {
        width: 42px;
        height: 42px;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }
    .ks-toggle-body { flex: 1; min-width: 0; }
    .ks-toggle-body h4 {
        font-size: 0.92rem;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    .ks-toggle-body p {
        font-size: 0.78rem;
        color: #6b7280;
        margin-top: 0.15rem;
        line-height: 1.45;
    }
    .ks-on-badge {
        font-size: 0.6rem;
        font-weight: 700;
        padding: 0.15rem 0.5rem;
        border-radius: 9999px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .ks-on-badge.on  { background: #d1fae5; color: #065f46; }
    .ks-on-badge.off { background: #fee2e2; color: #991b1b; }
    .ks-on-badge.warn { background: #fef3c7; color: #92400e; }
</style>

<!-- ===== STATUS BANNER ===== -->
<?php if ($maintenance_active): ?>
<div class="mb-5 p-4 rounded-xl border border-red-200 bg-red-50 text-red-800 text-sm flex items-start gap-3">
    <i class="fas fa-exclamation-triangle mt-0.5"></i>
    <div>
        <strong>MAINTENANCE MODE IS ACTIVE.</strong> The public site is showing the maintenance splash. Only admin accounts can keep using the system. Toggle it off below to restore normal access.
    </div>
</div>
<?php else: ?>
<div class="mb-5 p-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 text-sm flex items-start gap-3">
    <i class="fas fa-shield-alt mt-0.5"></i>
    <div>
        These are the <strong>master kill switches</strong>. If something misbehaves in production, MENRO can disable the affected feature here instantly — no code changes or redeploys required.
    </div>
</div>
<?php endif; ?>

<form method="POST" action="<?php echo BASE_URL; ?>controllers/SettingsController.php?tab=features" id="killSwitchForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

    <!-- ============================================================
         KILL SWITCH: MAINTENANCE MODE (site-wide)
         ============================================================ -->
    <div class="ks-card danger mb-6">
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-red-100 text-red-600"><i class="fas fa-power-off"></i></div>
            <div class="ks-toggle-body">
                <h4>Maintenance Mode
                    <span class="ks-on-badge <?php echo $maintenance_active ? 'on' : 'off'; ?>">
                        <?php echo $maintenance_active ? 'ON' : 'OFF'; ?>
                    </span>
                </h4>
                <p>Turns on the site-wide maintenance splash. Public visitors and citizen accounts see a "system under maintenance" page; only logged-in admins can keep using the system. Use this as the ultimate emergency stop.</p>
            </div>
            <label class="toggle-switch" title="Maintenance Mode">
                <input type="checkbox" name="maintenance_mode" <?php echo $maintenance_active ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="maintenance_mode">
    </div>

    <!-- ============================================================
         CITIZEN FEATURES
         ============================================================ -->
    <div class="ks-section-title"><span class="ks-dot"></span> Citizen Features</div>
    <div class="ks-card mb-6">

        <!-- Public Registration -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-sky-100 text-sky-600"><i class="fas fa-user-plus"></i></div>
            <div class="ks-toggle-body">
                <h4>Public Registration
                    <span class="ks-on-badge <?php echo $ks('enable_public_registration') ? 'on' : 'off'; ?>">
                        <?php echo $ks('enable_public_registration') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows new citizens to sign up on the public registration page. Turn off to block new account signups (existing users are unaffected).</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_public_registration" <?php echo $ks('enable_public_registration') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="enable_public_registration">

        <!-- Report Submission -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-emerald-100 text-emerald-600"><i class="fas fa-paper-plane"></i></div>
            <div class="ks-toggle-body">
                <h4>Report Submission
                    <span class="ks-on-badge <?php echo $ks('enable_report_submission') ? 'on' : 'off'; ?>">
                        <?php echo $ks('enable_report_submission') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows citizens to submit new environmental reports. Disable if the intake pipeline is failing so no new reports pile up.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_report_submission" <?php echo $ks('enable_report_submission') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="enable_report_submission">

        <!-- Support / Verification by Citizens -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-teal-100 text-teal-600"><i class="fas fa-thumbs-up"></i></div>
            <div class="ks-toggle-body">
                <h4>Support & Community Verification
                    <span class="ks-on-badge <?php echo $ks('enable_report_support') ? 'on' : 'off'; ?>">
                        <?php echo $ks('enable_report_support') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows citizens to upvote/verify others' reports (the support counter). Disable if the verification logic is counting incorrectly.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_report_support" <?php echo $ks('enable_report_support') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="enable_report_support">

        <!-- Citizen Cancellations -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-amber-100 text-amber-600"><i class="fas fa-ban"></i></div>
            <div class="ks-toggle-body">
                <h4>Citizen Report Cancellations
                    <span class="ks-on-badge <?php echo $ks('allow_citizen_cancellations') ? 'on' : 'off'; ?>">
                        <?php echo $ks('allow_citizen_cancellations') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows residents to cancel their own pending reports. Disable to freeze cancellations (e.g. before a review drive).</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="allow_citizen_cancellations" <?php echo $ks('allow_citizen_cancellations') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="allow_citizen_cancellations">

    </div>

    <!-- ============================================================
         STAFF & SYSTEM FEATURES
         ============================================================ -->
    <div class="ks-section-title"><span class="ks-dot"></span> Staff & System Features</div>
    <div class="ks-card mb-6">

        <!-- Escalation to MENRO -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-orange-100 text-orange-600"><i class="fas fa-arrow-up"></i></div>
            <div class="ks-toggle-body">
                <h4>Escalation to MENRO
                    <span class="ks-on-badge <?php echo $ks('enable_escalation') ? 'on' : 'off'; ?>">
                        <?php echo $ks('enable_escalation') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows barangay officials to escalate reports to MENRO. Disable if the escalation queue or notifications to the MENRO team misbehave.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_escalation" <?php echo $ks('enable_escalation') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="enable_escalation">

        <!-- Announcements -->
        <div class="ks-toggle-row">
            <div class="ks-toggle-icon bg-indigo-100 text-indigo-600"><i class="fas fa-bullhorn"></i></div>
            <div class="ks-toggle-body">
                <h4>Announcements
                    <span class="ks-on-badge <?php echo $ks('enable_announcements') ? 'on' : 'off'; ?>">
                        <?php echo $ks('enable_announcements') ? 'On' : 'Off'; ?>
                    </span>
                </h4>
                <p>Allows staff to create new announcements. Disable to stop new broadcasts while keeping existing announcements visible.</p>
            </div>
            <label class="toggle-switch">
                <input type="checkbox" name="enable_announcements" <?php echo $ks('enable_announcements') ? 'checked' : ''; ?>>
                <span class="toggle-slider"></span>
            </label>
        </div>
        <input type="hidden" name="feature_keys[]" value="enable_announcements">

    </div>

    <!-- ============================================================
         ACTIONS
         ============================================================ -->
    <div class="flex flex-wrap items-center gap-3 pt-2">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-1"></i> Save Kill Switches
        </button>
        <a href="<?php echo BASE_URL; ?>index.php?page=settings&tab=features"
           class="btn-secondary">
            <i class="fas fa-sync-alt mr-1"></i> Reset form
        </a>
        <span class="text-xs text-gray-400">Changes take effect immediately on the next page load.</span>
    </div>
</form>