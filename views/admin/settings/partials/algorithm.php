<?php
// views/admin/settings/partials/algorithm.php - Severity Algorithm Settings
// Manages: Impact Modifier, Density Multiplier, Clustering Radius, Critical Threshold, Verification Bonus

// Load current settings
$impact_modifier_0 = SettingsHelper::get('impact_modifier_0', 0);
$impact_modifier_2 = SettingsHelper::get('impact_modifier_2', 2);
$impact_modifier_4 = SettingsHelper::get('impact_modifier_4', 4);

$density_points_0 = SettingsHelper::get('density_points_0', 0);
$density_points_2 = SettingsHelper::get('density_points_2', 2);
$density_points_4 = SettingsHelper::get('density_points_4', 4);
$density_points_6 = SettingsHelper::get('density_points_6', 6);

$clustering_radius = SettingsHelper::get('clustering_radius_meters', 50);
$critical_threshold = SettingsHelper::get('critical_threshold_score', 15);

// Verification bonus settings
$verification_points_per_upvote = SettingsHelper::get('verification_points_per_upvote', 1);
$verification_max_points = SettingsHelper::get('verification_max_points', 5);

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
    @media (max-width: 640px) {
        .grid-cols-3, .grid-cols-4, .grid-cols-2 {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<form method="POST" action="<?php echo BASE_URL; ?>index.php?page=settings&tab=algorithm" id="algorithmForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ============================================ -->
    <!-- IMPACT MODIFIER POINTS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-bolt text-[#10A37F]"></i>
            Impact Modifier Points
        </h4>
        <p class="text-sm text-gray-500 mb-3">
            Points awarded based on the <strong>impact modifier</strong> (re‑classified by barangay officials).<br>
            Higher impact → higher severity score.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="form-group">
                <label class="form-label" for="impact_modifier_0">Impact 0 (Minor)</label>
                <input type="number" name="impact_modifier_0" id="impact_modifier_0"
                       value="<?php echo (int)$impact_modifier_0; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 0</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="impact_modifier_2">Impact 2 (Moderate)</label>
                <input type="number" name="impact_modifier_2" id="impact_modifier_2"
                       value="<?php echo (int)$impact_modifier_2; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 2</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="impact_modifier_4">Impact 4 (Severe)</label>
                <input type="number" name="impact_modifier_4" id="impact_modifier_4"
                       value="<?php echo (int)$impact_modifier_4; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 4</p>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- DENSITY MULTIPLIER POINTS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-layer-group text-[#10A37F]"></i>
            Density Multiplier Points
        </h4>
        <p class="text-sm text-gray-500 mb-3">
            Points added per <strong>number of nearby reports</strong> within the clustering radius.<br>
            More duplicates → higher severity.
        </p>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="form-group">
                <label class="form-label" for="density_points_0">0 nearby</label>
                <input type="number" name="density_points_0" id="density_points_0"
                       value="<?php echo (int)$density_points_0; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 0</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="density_points_2">1‑2 nearby</label>
                <input type="number" name="density_points_2" id="density_points_2"
                       value="<?php echo (int)$density_points_2; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 2</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="density_points_4">3‑5 nearby</label>
                <input type="number" name="density_points_4" id="density_points_4"
                       value="<?php echo (int)$density_points_4; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 4</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="density_points_6">6+ nearby</label>
                <input type="number" name="density_points_6" id="density_points_6"
                       value="<?php echo (int)$density_points_6; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 6</p>
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- CLUSTERING RADIUS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-arrows-alt-h text-[#10A37F]"></i>
            Clustering Radius
        </h4>
        <p class="text-sm text-gray-500 mb-3">
            Maximum distance (in meters) to consider reports as “nearby” for density calculations.
        </p>
        <div class="form-group max-w-xs">
            <label class="form-label" for="clustering_radius_meters">Radius (meters)</label>
            <input type="number" name="clustering_radius_meters" id="clustering_radius_meters"
                   value="<?php echo (int)$clustering_radius; ?>" min="10" max="500"
                   class="form-input">
            <p class="help-text">Default: 50m (range 10‑500m)</p>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- SEVERITY THRESHOLDS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-flag text-[#10A37F]"></i>
            Severity Thresholds
        </h4>
        <p class="text-sm text-gray-500 mb-3">
            Define the score boundaries for automatic risk classification.<br>
            The <strong>Critical Threshold</strong> is the score above which a report is flagged as <span class="text-red-600 font-semibold">CRITICAL</span>.
        </p>
        <div class="form-group max-w-xs">
            <label class="form-label" for="critical_threshold_score">
                Critical Threshold Score
                <span class="text-xs font-normal text-gray-400 ml-1">(≥ this score → CRITICAL)</span>
            </label>
            <input type="number" name="critical_threshold_score" id="critical_threshold_score"
                   value="<?php echo (int)$critical_threshold; ?>" min="0" max="100"
                   class="form-input">
            <p class="help-text">Default: 15</p>
        </div>
        <div class="card-info mt-3">
            <div class="title">Current Critical Threshold</div>
            <div class="desc">
                Reports with severity score <strong>≥ <?php echo (int)$critical_threshold; ?></strong> will be marked as <span class="text-red-600 font-semibold">CRITICAL</span>.
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- UPVOTE / VERIFICATION BONUS -->
    <!-- ============================================ -->
    <div class="mb-6">
        <h4 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
            <i class="fas fa-thumbs-up text-[#10A37F]"></i>
            Verification Bonus (Upvotes)
        </h4>
        <p class="text-sm text-gray-500 mb-3">
            Each time a citizen confirms an existing report, the report gains points.<br>
            This encourages community validation and raises priority for confirmed issues.
        </p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="form-group">
                <label class="form-label" for="verification_points_per_upvote">Points per Upvote</label>
                <input type="number" name="verification_points_per_upvote" id="verification_points_per_upvote"
                       value="<?php echo (int)$verification_points_per_upvote; ?>" min="0" max="10"
                       class="form-input">
                <p class="help-text">Default: 1</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="verification_max_points">Maximum Bonus from Upvotes</label>
                <input type="number" name="verification_max_points" id="verification_max_points"
                       value="<?php echo (int)$verification_max_points; ?>" min="0" max="20"
                       class="form-input">
                <p class="help-text">Default: 5 (caps total contribution)</p>
            </div>
        </div>
        <div class="card-info mt-3">
            <div class="title">How Verification Bonus Works</div>
            <div class="desc">
                <strong>Total Bonus = min( Upvotes × <?php echo (int)$verification_points_per_upvote; ?>, <?php echo (int)$verification_max_points; ?> )</strong><br>
                Each unique citizen who verifies the same report adds <?php echo (int)$verification_points_per_upvote; ?> point(s), up to a maximum of <?php echo (int)$verification_max_points; ?> points.
                This bonus is added to the severity score alongside impact and density.
            </div>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORMULA PREVIEW -->
    <!-- ============================================ -->
    <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200">
        <h5 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
            <i class="fas fa-calculator text-[#10A37F]"></i>
            Severity Score Calculation
        </h5>
        <p class="text-sm text-gray-600 mt-2">
            <strong>Severity Score = Base Weight + Impact Points + Density Points + Verification Bonus</strong>
        </p>
        <div class="text-xs text-gray-500 mt-1">
            <span class="inline-block bg-gray-200 px-2 py-0.5 rounded">Base Weight</span> (from category, 1‑10)
            + <span class="inline-block bg-gray-200 px-2 py-0.5 rounded">Impact Points</span> (0, 2, or 4)
            + <span class="inline-block bg-gray-200 px-2 py-0.5 rounded">Density Points</span> (0‑6)
            + <span class="inline-block bg-gray-200 px-2 py-0.5 rounded">Verification Bonus</span> (0‑5)
            = <strong>Score (1‑20)</strong>
        </div>
        <div class="mt-2 text-xs text-gray-500">
            <?php
            // Example calculation using current settings
            $exampleBase = 5;
            $exampleImpact = 2;
            $exampleDensity = 4;
            $exampleUpvotes = 3;
            $exampleBonus = min($exampleUpvotes * $verification_points_per_upvote, $verification_max_points);
            $exampleScore = $exampleBase + $exampleImpact + $exampleDensity + $exampleBonus;
            ?>
            <em>Example:</em> Base (5) + Impact 2 (<?php echo $impact_modifier_2; ?> pts) + 4 nearby (<?php echo $density_points_4; ?> pts) + 3 upvotes (<?php echo $exampleBonus; ?> pts)
            → Score = <?php echo $exampleScore; ?>
            <?php if ($exampleScore >= $critical_threshold): ?>
                <span class="text-red-600 font-semibold">→ CRITICAL</span>
            <?php else: ?>
                <span class="text-gray-500">→ Not critical</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- FORM ACTIONS -->
    <!-- ============================================ -->
    <div class="flex flex-wrap gap-3 justify-end pt-4 border-t border-gray-200">
        <button type="reset" onclick="resetForm()" class="btn-secondary">
            <i class="fas fa-undo mr-2"></i>Reset
        </button>
        <button type="submit" class="btn-primary">
            <i class="fas fa-save mr-2"></i>Save Algorithm Settings
        </button>
    </div>
</form>

<!-- ============================================ -->
<!-- JAVASCRIPT -->
<!-- ============================================ -->
<script>
(function() {
    'use strict';

    const form = document.getElementById('algorithmForm');

    // Reset form – reload page to discard changes
    window.resetForm = function() {
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