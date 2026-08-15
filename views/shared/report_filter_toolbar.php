<?php
// views/shared/report_filter_toolbar.php
// Shared filter toolbar partial, built to match the "My Reports" toolbar design.
// Expects a `$ft` config array. Supported keys:
//   search_id, search_value, search_placeholder, results_text,
//   inline_selects   [{id, value, min_width, onchange, options}]
//   filter_by        {active, count}
//   popover_fields   [{kind:'select'|'date', id, label, value, default, options}]
//   trailing_select  {id, value, min_width, onchange, options}
//   view_toggle      {active:'grid'|'list', grid:'setViewMode(...)', list:'setViewMode(...)'}
//   active_filters, chips, chips_clear_all,
//   chip_clear_map   {dataFilter: {el, clear}}  (optional; falls back to search/category/date)
//   callback         JS function name called to re-apply filters
if (!isset($ft) || !is_array($ft)) {
    return;
}

$ft_search_id          = $ft['search_id'] ?? 'searchInput';
$ft_search_value       = $ft['search_value'] ?? '';
$ft_search_placeholder = $ft['search_placeholder'] ?? 'Search...';
$ft_results_text       = $ft['results_text'] ?? '';
$ft_inline_selects     = $ft['inline_selects'] ?? [];
$ft_filter_by          = $ft['filter_by'] ?? ['active' => false, 'count' => 0];
$ft_popover_fields     = $ft['popover_fields'] ?? [];
$ft_trailing_select    = $ft['trailing_select'] ?? null;
$ft_view_toggle        = $ft['view_toggle'] ?? null;
$ft_active_filters     = (int)($ft['active_filters'] ?? 0);
$ft_chips              = $ft['chips'] ?? [];
$ft_chips_clear_all    = $ft['chips_clear_all'] ?? false;
$ft_chip_clear_map     = $ft['chip_clear_map'] ?? [];
$ft_callback           = $ft['callback'] ?? 'applyFilters';
$ft_filter_count       = (int)($ft_filter_by['count'] ?? 0);

// Fallback chip-clearing map (used when the host page does not provide chip_clear_map):
//   'search' -> search input, 'category' -> first inline select that has an "all" option,
//   plus popover date fields mapped by normalizing their label ("Date From" -> date_from).
$ft_category_el = null;
foreach ($ft_inline_selects as $sel) {
    $opts = $sel['options'] ?? [];
    if (array_key_exists('all', $opts)) { $ft_category_el = $sel['id'] ?? null; break; }
}
$ft_date_map = [];
foreach ($ft_popover_fields as $pf) {
    if (empty($pf['id'])) continue;
    $key = trim(strtolower(str_replace(' ', '_', preg_replace('/[^A-Za-z ]/', '', $pf['label'] ?? ''))));
    if ($key !== '') $ft_date_map[$key] = $pf['id'];
}
?>
<style>
    .ft-toolbar {
        --ft-forest: #2D5A27;
        --ft-forest-light: #E8F0E7;
        --ft-forest-mid: #3A7332;
        --ft-border: #D1D5DB;
        --ft-border-light: #E5E7EB;
        --ft-white: #FFFFFF;
        --ft-gray-50: #F9FAFB;
        --ft-gray-500: #6B7280;
        --ft-gray-700: #374151;
        --ft-gray-800: #1F2937;
    }
    .ft-toolbar .reports-toolbar {
        background: var(--ft-white);
        border: 1px solid var(--ft-border);
        border-radius: 14px;
        padding: 10px 16px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        position: relative;
    }
    .ft-toolbar .reports-toolbar.style-has-chips {
        margin-bottom: 0;
    }
    .ft-toolbar .toolbar-search {
        position: relative;
        flex: 1 1 220px;
        min-width: 180px;
    }
    .ft-toolbar .toolbar-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
        font-size: 0.8rem;
        pointer-events: none;
    }
    .ft-toolbar .toolbar-search input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1.5px solid var(--ft-border-light);
        border-radius: 10px;
        font-size: 0.85rem;
        color: var(--ft-gray-800);
        background: var(--ft-gray-50);
        transition: all 0.2s ease;
        outline: none;
    }
    .ft-toolbar .toolbar-search input:focus {
        border-color: var(--ft-forest);
        background: var(--ft-white);
        box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
    }
    .ft-toolbar .toolbar-search input::placeholder {
        color: #9CA3AF;
    }
    .ft-toolbar .toolbar-select {
        appearance: none;
        padding: 8px 32px 8px 12px;
        border: 1.5px solid var(--ft-border-light);
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--ft-gray-700);
        background: var(--ft-gray-50);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        cursor: pointer;
        transition: all 0.2s ease;
        outline: none;
        white-space: nowrap;
    }
    .ft-toolbar .toolbar-select:focus {
        border-color: var(--ft-forest);
        background-color: var(--ft-white);
        box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
    }
    .ft-toolbar .toolbar-select:hover {
        border-color: var(--ft-forest);
    }
    .ft-toolbar .toolbar-divider {
        width: 1px;
        height: 28px;
        background: var(--ft-border-light);
        flex-shrink: 0;
    }
    .ft-toolbar .toolbar-filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border: 1.5px solid var(--ft-border-light);
        border-radius: 10px;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--ft-gray-700);
        background: var(--ft-gray-50);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        white-space: nowrap;
    }
    .ft-toolbar .toolbar-filter-btn:hover {
        border-color: var(--ft-forest);
        color: var(--ft-forest);
        background: var(--ft-forest-light);
    }
    .ft-toolbar .toolbar-filter-btn.active {
        border-color: var(--ft-forest);
        color: var(--ft-forest);
        background: var(--ft-forest-light);
    }
    .ft-toolbar .toolbar-filter-btn .filter-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 9px;
        background: var(--ft-forest);
        color: var(--ft-white);
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 1;
    }
    .ft-toolbar .filter-popover-wrapper {
        position: relative;
    }
    .ft-toolbar .filter-popover {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 50;
        background: var(--ft-white);
        border: 1px solid var(--ft-border);
        border-radius: 12px;
        box-shadow: 0 12px 36px -8px rgba(0, 0, 0, 0.12), 0 4px 12px -4px rgba(0, 0, 0, 0.06);
        padding: 16px;
        min-width: 320px;
        display: none;
        animation: ftPopoverIn 0.2s ease;
    }
    .ft-toolbar .filter-popover.open {
        display: block;
    }
    @keyframes ftPopoverIn {
        from { opacity: 0; transform: translateY(-6px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .ft-toolbar .popover-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--ft-gray-500);
        margin-bottom: 12px;
    }
    .ft-toolbar .popover-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .ft-toolbar .popover-grid.full-width {
        grid-template-columns: 1fr;
    }
    .ft-toolbar .popover-field label {
        display: block;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--ft-gray-500);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }
    .ft-toolbar .popover-field select {
        width: 100%;
        padding: 7px 30px 7px 10px;
        border: 1.5px solid var(--ft-border-light);
        border-radius: 8px;
        font-size: 0.82rem;
        color: var(--ft-gray-700);
        background: var(--ft-gray-50);
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 12 12'%3E%3Cpath fill='%236B7280' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        cursor: pointer;
        outline: none;
        transition: all 0.2s ease;
    }
    .ft-toolbar .popover-field select:focus {
        border-color: var(--ft-forest);
        box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
    }
    .ft-toolbar .popover-field input[type="date"] {
        width: 100%;
        padding: 7px 10px;
        border: 1.5px solid var(--ft-border-light);
        border-radius: 8px;
        font-size: 0.82rem;
        color: var(--ft-gray-700);
        background: var(--ft-gray-50);
        outline: none;
        transition: all 0.2s ease;
    }
    .ft-toolbar .popover-field input[type="date"]:focus {
        border-color: var(--ft-forest);
        box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.10);
    }
    .ft-toolbar .popover-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 14px;
        padding-top: 12px;
        border-top: 1px solid var(--ft-border-light);
    }
    .ft-toolbar .popover-btn-apply {
        padding: 7px 18px;
        background: var(--ft-forest);
        color: var(--ft-white);
        border: none;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .ft-toolbar .popover-btn-apply:hover {
        background: var(--ft-forest-mid);
        box-shadow: 0 4px 12px rgba(45, 90, 39, 0.2);
    }
    .ft-toolbar .popover-btn-reset {
        padding: 7px 14px;
        background: var(--ft-white);
        color: var(--ft-gray-500);
        border: 1.5px solid var(--ft-border-light);
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .ft-toolbar .popover-btn-reset:hover {
        border-color: #EF4444;
        color: #EF4444;
        background: #FEF2F2;
    }
    .ft-toolbar .toolbar-results {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-left: auto;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .ft-toolbar .toolbar-results-text {
        font-size: 0.8rem;
        color: var(--ft-gray-500);
        font-weight: 500;
    }
    .ft-toolbar .toolbar-results-text strong {
        color: var(--ft-gray-800);
        font-weight: 700;
    }
    .ft-toolbar .view-toggle {
        background: #f1f5f9;
        border-radius: 2rem;
        padding: 0.2rem;
        display: inline-flex;
        gap: 0.2rem;
    }
    .ft-toolbar .view-btn {
        padding: 0.25rem 0.7rem;
        border-radius: 1.5rem;
        font-size: 0.7rem;
        font-weight: 500;
        cursor: pointer;
        background: transparent;
        color: #64748b;
        transition: all 0.2s;
    }
    @media (min-width: 640px) {
        .ft-toolbar .view-btn {
            padding: 0.375rem 1rem;
            font-size: 0.875rem;
        }
    }
    .ft-toolbar .view-btn.active {
        background: white;
        color: #10A37F;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .ft-toolbar .view-btn:hover:not(.active) {
        color: #10A37F;
    }
    .ft-toolbar .active-filters-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: var(--ft-white);
        border: 1px solid var(--ft-border);
        border-top: none;
        border-radius: 0 0 14px 14px;
        margin-top: -1px;
        margin-bottom: 1.5rem;
    }
    .ft-toolbar .active-filters-label {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--ft-gray-500);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-right: 2px;
    }
    .ft-toolbar .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px 4px 12px;
        background: var(--ft-forest-light);
        color: var(--ft-forest);
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        transition: all 0.15s ease;
    }
    .ft-toolbar .filter-chip:hover {
        background: #D4E4D2;
    }
    .ft-toolbar .filter-chip .chip-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: rgba(45, 90, 39, 0.15);
        color: var(--ft-forest);
        font-size: 0.55rem;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
        line-height: 1;
    }
    .ft-toolbar .filter-chip .chip-remove:hover {
        background: #c53030;
        color: white;
    }
    .ft-toolbar .chips-clear-all {
        font-size: 0.72rem;
        color: var(--ft-gray-500);
        text-decoration: none;
        font-weight: 500;
        margin-left: 4px;
        transition: color 0.15s ease;
    }
    .ft-toolbar .chips-clear-all:hover {
        color: #c53030;
    }
    @media (max-width: 640px) {
        .ft-toolbar .toolbar-search { min-width: 100%; }
        .ft-toolbar .toolbar-results { width: 100%; justify-content: space-between; }
    }

    /* ===== Mobile "3 dots" more menu ===== */
    .ft-toolbar .ft-more-btn {
        display: none;
    }
    .ft-toolbar .ft-more-controls {
        display: contents;
    }
    .ft-toolbar .ft-more-controls-header {
        display: none;
    }
    .ft-toolbar .ft-more-backdrop {
        display: none;
    }
    @media (max-width: 640px) {
        .ft-toolbar .reports-toolbar {
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
        }
        .ft-toolbar .toolbar-search {
            flex: 1 1 auto;
            min-width: 0;
        }
        /* View toggle gets JS-repositioned to sit right after search on mobile */
        .ft-toolbar .view-toggle {
            flex-shrink: 0;
        }
        .ft-toolbar .ft-more-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: 1.5px solid var(--ft-border-light);
            background: var(--ft-gray-50);
            color: var(--ft-gray-700);
            cursor: pointer;
        }
        .ft-toolbar .ft-more-btn.active {
            border-color: var(--ft-forest);
            color: var(--ft-forest);
            background: var(--ft-forest-light);
        }
        .ft-toolbar .ft-more-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 8px;
            background: var(--ft-forest);
            color: var(--ft-white);
            font-size: 0.6rem;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
        }
        .ft-toolbar .ft-more-backdrop.open {
            display: block;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            z-index: 190;
        }
        .ft-toolbar .ft-more-controls {
            display: none;
        }
        .ft-toolbar .ft-more-controls.open {
            display: flex;
            flex-direction: column;
            gap: 12px;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 200;
            background: var(--ft-white);
            padding: 14px 16px calc(16px + env(safe-area-inset-bottom));
            border-radius: 18px 18px 0 0;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.18);
            max-height: 75vh;
            overflow-y: auto;
            animation: ftSheetUp 0.22s ease;
        }
        @keyframes ftSheetUp {
            from { transform: translateY(16px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .ft-toolbar .ft-more-controls-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--ft-gray-800);
            padding-bottom: 6px;
            border-bottom: 1px solid var(--ft-border-light);
        }
        .ft-toolbar .ft-more-close {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: none;
            background: var(--ft-gray-50);
            color: var(--ft-gray-500);
            cursor: pointer;
        }
        .ft-toolbar .ft-more-controls.open .toolbar-select,
        .ft-toolbar .ft-more-controls.open .filter-popover-wrapper,
        .ft-toolbar .ft-more-controls.open .toolbar-filter-btn {
            width: 100%;
        }
        .ft-toolbar .ft-more-controls.open .toolbar-divider {
            display: none;
        }
        .ft-toolbar .ft-more-controls.open .toolbar-results {
            width: 100%;
            margin-left: 0;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        .ft-toolbar .ft-more-controls.open .view-toggle {
            display: none;
        }
        .ft-toolbar .ft-more-controls.open .filter-popover {
            left: 0;
            right: 0;
        }
    }
</style>

<div class="ft-toolbar">
    <div class="reports-toolbar<?php echo $ft_active_filters > 0 ? ' style-has-chips' : ''; ?>"
         style="<?php echo $ft_active_filters > 0 ? 'border-radius: 14px 14px 0 0;' : ''; ?>">

        <!-- Search (always visible, incl. mobile) -->
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="<?php echo htmlspecialchars($ft_search_id); ?>"
                   value="<?php echo htmlspecialchars($ft_search_value); ?>"
                   placeholder="<?php echo htmlspecialchars($ft_search_placeholder); ?>">
        </div>

        <!-- Mobile-only "more filters" trigger (3 dots) -->
        <button type="button" class="ft-more-btn" id="ftMoreBtn" aria-label="More filters" aria-expanded="false">
            <i class="fas fa-ellipsis-vertical"></i>
            <?php if ($ft_active_filters > 0): ?>
                <span class="ft-more-badge"><?php echo (int)$ft_active_filters; ?></span>
            <?php endif; ?>
        </button>

        <!-- Controls hidden on mobile behind the 3-dot menu (unchanged on desktop) -->
        <div class="ft-more-controls" id="ftMoreControls">
            <div class="ft-more-controls-header">
                <span>Filters &amp; Sort</span>
                <button type="button" class="ft-more-close" id="ftMoreClose" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>

            <!-- Inline selects -->
            <?php foreach ($ft_inline_selects as $sel): ?>
                <?php
                $sel_id   = $sel['id'] ?? '';
                $sel_value = (string)($sel['value'] ?? '');
                $sel_min   = !empty($sel['min_width']) ? 'min-width:' . htmlspecialchars($sel['min_width']) . ';' : '';
                $sel_onchange = !empty($sel['onchange']) ? $sel['onchange'] : ($ft_callback . '()');
                $sel_options  = $sel['options'] ?? [];
                ?>
                <select id="<?php echo htmlspecialchars($sel_id); ?>" class="toolbar-select"
                        style="<?php echo $sel_min; ?>" onchange="<?php echo htmlspecialchars($sel_onchange); ?>">
                    <?php foreach ($sel_options as $opt_value => $opt_label): ?>
                        <option value="<?php echo htmlspecialchars((string)$opt_value); ?>"
                            <?php echo ($sel_value === (string)$opt_value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars((string)$opt_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endforeach; ?>

            <!-- Filter By popover -->
            <?php if (!empty($ft_popover_fields)): ?>
            <div class="filter-popover-wrapper">
                <button type="button" class="toolbar-filter-btn <?php echo $ft_filter_count > 0 ? 'active' : ''; ?>" id="filterByBtn">
                    <i class="fas fa-sliders-h"></i> Filter By
                    <?php if ($ft_filter_count > 0): ?>
                        <span class="filter-count-badge"><?php echo (int)$ft_filter_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="filter-popover" id="filterPopover">
                    <div class="popover-title">Refine Results</div>
                    <div class="popover-grid<?php echo count($ft_popover_fields) <= 1 ? ' full-width' : ''; ?>">
                        <?php foreach ($ft_popover_fields as $pf): ?>
                            <div class="popover-field">
                                <label><?php echo htmlspecialchars($pf['label'] ?? ''); ?></label>
                                <?php if (($pf['kind'] ?? 'date') === 'select'): ?>
                                    <select id="<?php echo htmlspecialchars($pf['id'] ?? ''); ?>">
                                        <?php foreach (($pf['options'] ?? []) as $opt_value => $opt_label): ?>
                                            <option value="<?php echo htmlspecialchars((string)$opt_value); ?>"
                                                <?php echo ((string)($pf['value'] ?? '') === (string)$opt_value) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars((string)$opt_label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <input type="date" id="<?php echo htmlspecialchars($pf['id'] ?? ''); ?>"
                                           value="<?php echo htmlspecialchars($pf['value'] ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="popover-actions">
                        <button type="button" class="popover-btn-reset" id="popoverReset"><i class="fas fa-undo" style="font-size:0.7rem"></i> Reset</button>
                        <button type="button" class="popover-btn-apply" id="popoverApply"><i class="fas fa-check" style="font-size:0.7rem; margin-right:4px"></i>Apply Filters</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($ft_results_text !== '' || $ft_view_toggle || $ft_trailing_select): ?>
            <div class="toolbar-divider"></div>
            <div class="toolbar-results">
                <?php if ($ft_results_text !== ''): ?>
                    <span class="toolbar-results-text"><?php echo $ft_results_text; ?></span>
                <?php endif; ?>

                <?php if ($ft_view_toggle): ?>
                    <!-- View toggle: lives here on desktop; JS moves it up next to Search on mobile -->
                    <span id="ftViewTogglePlaceholder" style="display:none"></span>
                    <div class="view-toggle" id="ftViewToggle">
                        <button type="button" id="gridViewBtn" class="view-btn <?php echo ($ft_view_toggle['active'] ?? '') === 'grid' ? 'active' : ''; ?>"
                                onclick="<?php echo htmlspecialchars($ft_view_toggle['grid'] ?? ''); ?>"><i class="fas fa-th"></i></button>
                        <button type="button" id="listViewBtn" class="view-btn <?php echo ($ft_view_toggle['active'] ?? '') === 'list' ? 'active' : ''; ?>"
                                onclick="<?php echo htmlspecialchars($ft_view_toggle['list'] ?? ''); ?>"><i class="fas fa-list"></i></button>
                    </div>
                <?php endif; ?>

                <?php if ($ft_trailing_select): ?>
                    <?php
                    $ts_id   = $ft_trailing_select['id'] ?? '';
                    $ts_value = (string)($ft_trailing_select['value'] ?? '');
                    $ts_min   = !empty($ft_trailing_select['min_width']) ? 'min-width:' . htmlspecialchars($ft_trailing_select['min_width']) . ';' : '';
                    $ts_onchange = $ft_trailing_select['onchange'] ?? ($ft_callback . '()');
                    $ts_options  = $ft_trailing_select['options'] ?? [];
                    ?>
                    <select id="<?php echo htmlspecialchars($ts_id); ?>" class="toolbar-select"
                            style="<?php echo $ts_min; ?>" onchange="<?php echo htmlspecialchars($ts_onchange); ?>">
                        <?php foreach ($ts_options as $opt_value => $opt_label): ?>
                            <option value="<?php echo htmlspecialchars((string)$opt_value); ?>"
                                <?php echo ($ts_value === (string)$opt_value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string)$opt_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Backdrop for the mobile "more filters" sheet -->
    <div class="ft-more-backdrop" id="ftMoreBackdrop"></div>

    <?php if ($ft_active_filters > 0): ?>
    <div class="active-filters-row">
        <span class="active-filters-label">Active:</span>
        <?php foreach ($ft_chips as $chip): ?>
            <?php if (!empty($chip)): echo $chip; endif; ?>
        <?php endforeach; ?>
        <?php if ($ft_chips_clear_all): ?>
        <a href="#" class="chips-clear-all" id="clearAllFilters">Clear all</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var FT = {
        searchId: '<?php echo htmlspecialchars($ft_search_id, ENT_QUOTES); ?>',
        categoryEl: '<?php echo htmlspecialchars($ft_category_el ?? 'toolbarCategory', ENT_QUOTES); ?>',
        dateMap: <?php echo json_encode($ft_date_map); ?>,
        clearMap: <?php echo json_encode($ft_chip_clear_map); ?>,
        callback: '<?php echo htmlspecialchars($ft_callback, ENT_QUOTES); ?>',
        popoverFields: <?php echo json_encode(array_map(function ($pf) {
            return ['id' => $pf['id'] ?? '', 'default' => $pf['default'] ?? ''];
        }, $ft_popover_fields)); ?>
    };
    if (!document.getElementById(FT.searchId)) return;

    var searchInput = document.getElementById(FT.searchId);
    var filterBtn = document.getElementById('filterByBtn');
    var filterPopover = document.getElementById('filterPopover');
    var searchTimer = null;

    // ===== Mobile "3 dots" more menu (bottom sheet) =====
    var moreBtn = document.getElementById('ftMoreBtn');
    var moreControls = document.getElementById('ftMoreControls');
    var moreBackdrop = document.getElementById('ftMoreBackdrop');
    var moreClose = document.getElementById('ftMoreClose');
    var mobileQuery = window.matchMedia('(max-width: 640px)');

    function openMore() {
        if (!moreControls) return;
        moreControls.classList.add('open');
        moreBackdrop && moreBackdrop.classList.add('open');
        moreBtn && moreBtn.classList.add('active');
        moreBtn && moreBtn.setAttribute('aria-expanded', 'true');
    }
    function closeMore() {
        if (!moreControls) return;
        moreControls.classList.remove('open');
        moreBackdrop && moreBackdrop.classList.remove('open');
        moreBtn && moreBtn.classList.remove('active');
        moreBtn && moreBtn.setAttribute('aria-expanded', 'false');
        filterPopover && filterPopover.classList.remove('open');
    }
    moreBtn && moreBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (moreControls.classList.contains('open')) { closeMore(); } else { openMore(); }
    });
    moreClose && moreClose.addEventListener('click', closeMore);
    moreBackdrop && moreBackdrop.addEventListener('click', closeMore);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeMore();
    });

    // ===== Move the grid/list view toggle next to Search on mobile =====
    var viewToggle = document.getElementById('ftViewToggle');
    var viewTogglePlaceholder = document.getElementById('ftViewTogglePlaceholder');
    var viewToggleMoved = false;
    function layoutViewToggle() {
        if (!viewToggle || !moreBtn) return;
        if (mobileQuery.matches && !viewToggleMoved) {
            moreBtn.parentNode.insertBefore(viewToggle, moreBtn);
            viewToggleMoved = true;
        } else if (!mobileQuery.matches && viewToggleMoved && viewTogglePlaceholder) {
            viewTogglePlaceholder.parentNode.insertBefore(viewToggle, viewTogglePlaceholder.nextSibling);
            viewToggleMoved = false;
        }
    }
    layoutViewToggle();
    if (mobileQuery.addEventListener) {
        mobileQuery.addEventListener('change', layoutViewToggle);
    } else if (mobileQuery.addListener) {
        mobileQuery.addListener(layoutViewToggle);
    }
    window.addEventListener('resize', layoutViewToggle);

    function ftRun() {
        if (typeof window[FT.callback] === 'function') window[FT.callback]();
    }

    function ftClearChip(filter) {
        if (FT.clearMap && FT.clearMap[filter]) {
            var el = document.getElementById(FT.clearMap[filter].el);
            if (el) el.value = FT.clearMap[filter].clear;
            return;
        }
        if (filter === 'search') {
            searchInput.value = '';
        } else if (filter === 'category' || filter === FT.categoryEl) {
            var catEl = document.getElementById(FT.categoryEl);
            if (catEl) catEl.value = 'all';
        } else if (FT.dateMap[filter]) {
            var dateEl = document.getElementById(FT.dateMap[filter]);
            if (dateEl) dateEl.value = '';
        }
    }

    function ftClearAll() {
        if (FT.clearMap && Object.keys(FT.clearMap).length) {
            Object.keys(FT.clearMap).forEach(function (k) {
                var el = document.getElementById(FT.clearMap[k].el);
                if (el) el.value = FT.clearMap[k].clear;
            });
            return;
        }
        searchInput.value = '';
        var catEl = document.getElementById(FT.categoryEl);
        if (catEl) catEl.value = 'all';
        Object.keys(FT.dateMap).forEach(function (k) {
            var el = document.getElementById(FT.dateMap[k]);
            if (el) el.value = '';
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(ftRun, 400);
    });
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { clearTimeout(searchTimer); ftRun(); }
    });

    // Popover toggle
    filterBtn && filterBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        filterPopover.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (filterPopover && !filterPopover.contains(e.target) && e.target !== filterBtn) {
            filterPopover.classList.remove('open');
        }
    });
    filterPopover && filterPopover.addEventListener('click', function (e) { e.stopPropagation(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && filterPopover && filterPopover.classList.contains('open')) {
            filterPopover.classList.remove('open');
        }
    });

    // Popover apply / reset
    var applyBtn = document.getElementById('popoverApply');
    applyBtn && applyBtn.addEventListener('click', function () {
        filterPopover.classList.remove('open');
        ftRun();
    });
    var resetBtn = document.getElementById('popoverReset');
    resetBtn && resetBtn.addEventListener('click', function () {
        FT.popoverFields.forEach(function (f) {
            var el = document.getElementById(f.id);
            if (el) el.value = f.default;
        });
        if (typeof window.ftResetPopover === 'function') window.ftResetPopover();
        filterPopover.classList.remove('open');
        ftRun();
    });

    // Filter chips + clear all (delegated, so it also works for AJAX-re-rendered chips)
    document.addEventListener('click', function (e) {
        var rem = e.target.closest('.chip-remove');
        if (rem) {
            e.preventDefault();
            ftClearChip(rem.getAttribute('data-filter'));
            ftRun();
            return;
        }
        var clearAll = e.target.closest('#clearAllFilters');
        if (clearAll) {
            e.preventDefault();
            ftClearAll();
            ftRun();
        }
    });
})();
</script>