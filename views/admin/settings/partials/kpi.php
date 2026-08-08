<?php
// views/admin/settings/partials/kpi.php - KPI & Insights Configuration
// The MENRO Chief sets the targets that the Insight Engine uses to
// generate the textual recommendations on the dashboards.

// Load current KPI target settings
$kpi_resolution_rate_target = (float)SettingsHelper::get('kpi_resolution_rate_target', 60);
$kpi_sla_response_hours     = (float)SettingsHelper::get('kpi_sla_response_hours', 48);
$kpi_surge_alert_threshold  = (float)SettingsHelper::get('kpi_surge_alert_threshold', 25);
$kpi_hotspot_radius_meters  = (float)SettingsHelper::get('kpi_hotspot_radius_meters', 10);
$kpi_critical_reports_pct   = (float)SettingsHelper::get('kpi_critical_reports_pct', 30);
$kpi_demographic_threshold  = (float)SettingsHelper::get('kpi_demographic_threshold', 10);
$kpi_repeat_min_reports     = (float)SettingsHelper::get('kpi_repeat_min_reports', 3);
$kpi_repeat_window_days     = (float)SettingsHelper::get('kpi_repeat_window_days', 30);

$csrf_token = InputSanitizer::generateCsrfToken();
?>

<style>
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
    .btn-primary {
        background: linear-gradient(135deg, #10A37F, #0D8568);
        color: white;
        border: none;
        transition: all 0.3s ease;
        cursor: pointer;
        padding: 0.6rem 1.5rem;
        border-radius: 0.75rem;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 163, 127, 0.3);
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
    .card-info {
        background: #f0fdf4;
        border-left: 4px solid #10A37F;
        padding: 0.75rem 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .card-info .title {
        font-weight: 600;
        color: #065f46;
        font-size: 0.9rem;
    }
    .card-info .desc {
        font-size: 0.8rem;
        color: #065f46;
        opacity: 0.9;
    }
    .kpi-section {
        margin-bottom: 1.75rem;
    }
    .kpi-section h4 {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.35rem;
    }
    .kpi-section h4 i {
        color: #10A37F;
    }
    .kpi-section p.kpi-desc {
        font-size: 0.8rem;
        color: #6b7280;
        margin-bottom: 0.75rem;
        line-height: 1.5;
    }
    .kpi-input-wrap {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        max-width: 260px;
    }
    .kpi-input-wrap .form-input {
        text-align: right;
        font-weight: 700;
    }
    .kpi-input-wrap .kpi-unit {
        font-size: 0.85rem;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }
    @media (max-width: 640px) {
        .kpi-input-wrap { max-width: 100%; }
    }
</style>

<form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=kpi" id="kpiForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="card-info">
        <div class="title"><i class="fas fa-bullseye mr-1"></i> Insight Engine Targets</div>
        <div class="desc">
            These targets define what the system treats as acceptable performance. When a live KPI
            falls below (or exceeds) one of these thresholds, the dashboards automatically generate
            a textual recommendation to the MENRO Chief.
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 1. RESOLUTION RATE TARGET -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-trophy"></i> Resolution Rate Target</h4>
        <p class="kpi-desc">
            The minimum acceptable barangay resolution rate. When a barangay on the
            <strong>Barangay Performance Leaderboard</strong> drops below this value, the system
            recommends <strong>MENRO backup / intervention</strong> for that barangay.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_resolution_rate_target">
                Minimum Acceptable Barangay Resolution Rate (%)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_resolution_rate_target" id="kpi_resolution_rate_target"
                       value="<?php echo (float)$kpi_resolution_rate_target; ?>" min="0" max="100" step="0.1"
                       class="form-input">
                <span class="kpi-unit">%</span>
            </div>
            <p class="help-text">Default: 60</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 2. MUNICIPAL SLA (SERVICE LEVEL AGREEMENT) -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-stopwatch"></i> Municipal SLA (Service Level Agreement)</h4>
        <p class="kpi-desc">
            The target maximum response time. When the <strong>Average Municipal Response Time</strong>
            chart exceeds this value, the system warns about <strong>dispatch bottlenecks / staffing
            shortfalls</strong>.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_sla_response_hours">
                Target Maximum Response Time (Hours)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_sla_response_hours" id="kpi_sla_response_hours"
                       value="<?php echo (float)$kpi_sla_response_hours; ?>" min="1" max="720" step="0.5"
                       class="form-input">
                <span class="kpi-unit">hours</span>
            </div>
            <p class="help-text">Default: 48 (≈ 2 days)</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 3. SURGE ALERT THRESHOLD -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-chart-line"></i> Surge Alert Threshold</h4>
        <p class="kpi-desc">
            The month-over-month percentage increase that triggers a <strong>surge alert</strong>. When the
            <strong>Seasonal Hazard Trends</strong> chart shows a category (e.g. flooding) rising by at least
            this percentage vs. the previous month, the system recommends <strong>budget reallocation</strong>.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_surge_alert_threshold">
                Category Spike Warning Threshold (%)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_surge_alert_threshold" id="kpi_surge_alert_threshold"
                       value="<?php echo (float)$kpi_surge_alert_threshold; ?>" min="0" max="100" step="0.1"
                       class="form-input">
                <span class="kpi-unit">%</span>
            </div>
            <p class="help-text">Default: 25</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 4. HOTSPOT DEFINITION RADIUS -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-map-marker-alt"></i> Hotspot Definition Radius</h4>
        <p class="kpi-desc">
            The radius used by the <strong>Repeat Offender Locations</strong> chart to decide whether multiple
            reports come from the <strong>same location</strong>. Reports within this radius are grouped as a
            single repeat offender before recommending <strong>CCTV / permanent infrastructure</strong> changes.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_hotspot_radius_meters">
                Repeat Offender Grouping Radius (Meters)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_hotspot_radius_meters" id="kpi_hotspot_radius_meters"
                       value="<?php echo (float)$kpi_hotspot_radius_meters; ?>" min="1" max="500" step="1"
                       class="form-input">
                <span class="kpi-unit">m</span>
            </div>
            <p class="help-text">Default: 10</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 5. CRITICAL REPORTS ALERT THRESHOLD -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-exclamation-triangle"></i> Critical Reports Alert Threshold</h4>
        <p class="kpi-desc">
            The maximum acceptable share of <strong>Critical-severity</strong> reports among all active
            reports. When the <strong>Severity Distribution</strong> chart shows Critical exceeding this
            percentage, the system issues a <strong>high-severity alert</strong> and recommends
            immediate MENRO intervention.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_critical_reports_pct">
                Critical Reports Share Warning (%)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_critical_reports_pct" id="kpi_critical_reports_pct"
                       value="<?php echo (float)$kpi_critical_reports_pct; ?>" min="0" max="100" step="0.1"
                       class="form-input">
                <span class="kpi-unit">%</span>
            </div>
            <p class="help-text">Default: 30</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 6. DEMOGRAPHIC ENGAGEMENT THRESHOLD -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-users"></i> Demographic Engagement Threshold</h4>
        <p class="kpi-desc">
            The minimum share a major demographic group must hold of all reports in the
            <strong>Reports Demographics</strong> chart. If a major group accounts for less than this
            percentage, the system recommends <strong>IEC (Information, Education &amp; Communication)
            campaigns</strong> targeted at that group.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_demographic_threshold">
                Minimum Major-Group Share (%)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_demographic_threshold" id="kpi_demographic_threshold"
                       value="<?php echo (float)$kpi_demographic_threshold; ?>" min="1" max="100" step="0.1"
                       class="form-input">
                <span class="kpi-unit">%</span>
            </div>
            <p class="help-text">Default: 10</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- 7. REPEAT OFFENDER DEFINITION -->
    <!-- ============================================ -->
    <div class="kpi-section">
        <h4><i class="fas fa-sync-alt"></i> Repeat Offender Definition</h4>
        <p class="kpi-desc">
            A location becomes a <strong>repeat offender</strong> in the <strong>Top Repeat Offender
            Locations</strong> chart when it logs more than <em>N</em> distinct reports within the
            rolling window of days below. Repeat offenders trigger <strong>CCTV / permanent
            infrastructural</strong> recommendations.
        </p>
        <div class="form-group">
            <label class="form-label" for="kpi_repeat_min_reports">
                Minimum Distinct Reports
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_repeat_min_reports" id="kpi_repeat_min_reports"
                       value="<?php echo (float)$kpi_repeat_min_reports; ?>" min="2" max="50" step="1"
                       class="form-input">
                <span class="kpi-unit">reports</span>
            </div>
            <p class="help-text">Default: 3</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="kpi_repeat_window_days">
                Rolling Window (Days)
            </label>
            <div class="kpi-input-wrap">
                <input type="number" name="kpi_repeat_window_days" id="kpi_repeat_window_days"
                       value="<?php echo (float)$kpi_repeat_window_days; ?>" min="7" max="365" step="1"
                       class="form-input">
                <span class="kpi-unit">days</span>
            </div>
            <p class="help-text">Default: 30</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-200">
        <button type="reset" onclick="resetKpiForm()" class="btn-secondary">
            <i class="fas fa-undo mr-2"></i>Reset
        </button>
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-2"></i>Save KPI &amp; Insights Settings
        </button>
    </div>
</form>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';

    const form = document.getElementById('kpiForm');

    // Reset form – reload page to discard changes
    window.resetKpiForm = function() {
        if (confirm('Reset all fields to their saved values? Unsaved changes will be lost.')) {
            location.reload();
        }
    };

    // Warn about unsaved changes
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

})();
</script>
