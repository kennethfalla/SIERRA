<?php
// views/shared/report_filter_toolbar.php
// Shared filter toolbar partial, built to match the "My Reports" toolbar design.
// Expects a `$ft` config array. Supported keys:
//   search_id, search_value, search_placeholder, results_text,
//   inline_selects   [{id, value, min_width, onchange, options}]
//   filter_by        {active, count}
//   popover_fields   [{kind:'select'|'date', id, label, value, default, options}]
//   sort_select      {id, value, default, min_width, options}  (rendered inside Filter By popover)
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
$ft_sort_select        = $ft['sort_select'] ?? null;
$ft_trailing_select    = $ft['trailing_select'] ?? null;
$ft_view_toggle        = $ft['view_toggle'] ?? null;
$ft_active_filters     = (int)($ft['active_filters'] ?? 0);
$ft_chips              = $ft['chips'] ?? [];
$ft_chips_clear_all    = $ft['chips_clear_all'] ?? false;
$ft_chip_clear_map     = $ft['chip_clear_map'] ?? [];
$ft_callback           = $ft['callback'] ?? 'applyFilters';
$ft_filter_count       = (int)($ft_filter_by['count'] ?? 0);
$ft_date_range         = $ft['date_range'] ?? null;
$ft_show_search        = $ft['show_search'] ?? true;
$ft_show_active_row    = $ft['show_active_row'] ?? true;
$ft_compact_breakpoint = max(640, min(1600, (int)($ft['compact_breakpoint'] ?? 640)));
// The shared app header also needs room for its page title and notifications.
$ft_in_header = $ft['in_header'] ?? isset($app_page_title);
if ($ft_in_header) $ft_compact_breakpoint = max(1400, $ft_compact_breakpoint);
$ft_more_icon          = $ft['more_icon'] ?? 'fa-ellipsis-vertical';

// Every report toolbar uses the same two primary controls: Search and Filter By.
// Preserve the IDs used by each page's existing filtering code.
foreach ($ft_inline_selects as $sel) {
    $options = $sel['options'] ?? [];
    $firstOption = reset($options);
    $ft_popover_fields[] = [
        'kind' => 'select', 'id' => $sel['id'] ?? '',
        'label' => $sel['label'] ?? preg_replace('/^All\s+/i', '', (string)$firstOption),
        'value' => $sel['value'] ?? '', 'default' => (string)array_key_first($options ?: ['' => '']),
        'options' => $options,
    ];
}
if ($ft_trailing_select) {
    $ft_popover_fields[] = [
        'kind' => 'select', 'id' => $ft_trailing_select['id'] ?? '',
        'label' => $ft_trailing_select['label'] ?? 'Items per page',
        'value' => $ft_trailing_select['value'] ?? '',
        'default' => (string)array_key_first($ft_trailing_select['options'] ?? ['' => '']),
        'options' => $ft_trailing_select['options'] ?? [],
    ];
}
$ft_filter_count = $ft_active_filters;

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
        --ft-forest: #0d8568;
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
        border-radius: 12px;
        padding: 10px 16px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 8px;
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
        border-radius: 8px;
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
        border-radius: 8px;
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
        border-radius: 8px;
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
        border-radius: 8px;
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
        max-width: calc(100vw - 32px);
        max-height: calc(100vh - 16px);
        overflow-y: auto;
        overscroll-behavior: contain;
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
    .ft-toolbar .popover-field.span-full {
        grid-column: 1 / -1;
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

    /* ===== Centered chip-based filter modal (photo-style) ===== */
    .filter-popover-backdrop {
        position: fixed;
        inset: 0;
        z-index: 10000;
        background: rgba(15, 23, 42, .45);
        display: none;
    }
    .filter-popover-backdrop.open { display: block; }
    .ft-toolbar .popover-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }
    .ft-toolbar .popover-close {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 1px solid var(--ft-border-light);
        background: #fff;
        color: #334155;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .18s ease;
    }
    .ft-toolbar .popover-close:hover { background: #f1f5f4; }
    .ft-toolbar .popover-reset-all {
        border: 0;
        background: transparent;
        color: var(--ft-forest);
        font-weight: 700;
        font-size: .82rem;
        cursor: pointer;
        padding: 6px 4px;
    }
    .ft-toolbar .pf-group { padding: 14px 0; border-top: 1px solid #eef2f0; }
    .ft-toolbar .pf-group:first-of-type { border-top: 0; padding-top: 0; }
    .ft-toolbar .pf-group-title { font-size: .95rem; font-weight: 800; color: #0f172a; margin-bottom: 10px; }
    .ft-toolbar .pf-chips { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .ft-toolbar .pf-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 14px;
        border: 1.5px solid #dbe7e0;
        border-radius: 999px;
        background: #fff;
        color: #334155;
        font-size: .8rem;
        font-weight: 600;
        cursor: pointer;
        transition: border-color .15s ease, color .15s ease;
    }
    .ft-toolbar .pf-chip:hover { border-color: #a9d8c6; }
    .ft-toolbar .pf-chip.active { border-color: var(--ft-forest); color: var(--ft-forest); }
    .ft-toolbar .pf-chip .pf-check {
        width: 17px;
        height: 17px;
        border-radius: 50%;
        border: 1.5px solid #cbd5d0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: transparent;
        font-size: .55rem;
        flex-shrink: 0;
        transition: background .15s ease, border-color .15s ease, color .15s ease;
    }
    .ft-toolbar .pf-chip.active .pf-check { background: var(--ft-forest); border-color: var(--ft-forest); color: #fff; }
    .ft-toolbar .pf-field-hidden { position: absolute !important; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
    .ft-toolbar .pf-date-box {
        display: flex;
        align-items: center;
        gap: 10px;
        border: 1.5px solid #dbe7e0;
        border-radius: 14px;
        padding: 12px 14px;
    }
    .ft-toolbar .pf-date-box label { margin: 0; text-transform: none; letter-spacing: 0; font-size: .82rem; color: #64748b; }
    .ft-toolbar .pf-date-box input[type="date"] { border: 0; background: transparent; padding: 0; flex: 1; }
    .ft-toolbar .popover-actions { position: sticky; bottom: 0; background: var(--ft-white); padding-top: 14px; border-top: 1px solid #eef2f0; margin-top: 4px; }
    .ft-toolbar .popover-btn-apply { width: 100%; justify-content: center; border-radius: 999px; padding: 14px; font-size: .95rem; }
    .ft-toolbar .date-range-wrapper {
        position: relative;
    }
    .ft-toolbar .dr-presets {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 12px;
    }
    .ft-toolbar .dr-preset {
        padding: 6px 12px;
        border: 1.5px solid var(--ft-border-light);
        background: var(--ft-gray-50);
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--ft-gray-700);
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    .ft-toolbar .dr-preset:hover {
        border-color: var(--ft-forest);
        color: var(--ft-forest);
        background: var(--ft-forest-light);
    }
    .ft-toolbar .dr-preset.active {
        background: var(--ft-forest);
        border-color: var(--ft-forest);
        color: var(--ft-white);
    }
    .ft-toolbar .dr-custom {
        margin-bottom: 0;
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
        border-radius: 1.5rem;
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
        border-radius: 0 0 12px 12px;
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
        border-radius: 16px;
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
    @media (max-width: <?php echo $ft_compact_breakpoint; ?>px) {
        .ft-toolbar .toolbar-search { min-width: 100%; }
        .ft-toolbar .toolbar-results { width: 100%; justify-content: space-between; }
    }

    /* Active filter chips stay on ONE line on mobile: horizontally scrollable */
    @media (max-width: <?php echo $ft_compact_breakpoint; ?>px) {
        .ft-toolbar .active-filters-row {
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            scroll-snap-type: x proximity;
        }
        .ft-toolbar .active-filters-row::-webkit-scrollbar { display: none; }
        .ft-toolbar .active-filters-label,
        .ft-toolbar .filter-chip,
        .ft-toolbar .chips-clear-all { flex: 0 0 auto; }
        .ft-toolbar .filter-chip { scroll-snap-align: start; }
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
    @media (max-width: <?php echo $ft_compact_breakpoint; ?>px) {
        .ft-toolbar .reports-toolbar {
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
        }
        .ft-toolbar .toolbar-search {
            flex: 1 1 auto;
            min-width: 0;
        }
        /* Popover must fit small phones: drop the 320px min-width and stack date fields */
        .ft-toolbar .filter-popover {
            min-width: 0;
        }
        .ft-toolbar .popover-grid {
            grid-template-columns: 1fr;
        }
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
            border-radius: 8px;
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
            animation: ftBackdropIn 0.24s ease;
        }
        @keyframes ftBackdropIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        :root {
            --ft-sheet-100: 100vh;
            --ft-drawer-peek: 38vh;
        }
        @supports (height: 1dvh) {
            :root {
                --ft-sheet-100: 100dvh;
                --ft-drawer-peek: 38dvh;
            }
        }
        body.ft-sheet-open {
            overflow: hidden;
            overscroll-behavior: none;
            touch-action: none;
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
            border-radius: 16px 16px 0 0;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.18);
            height: var(--ft-sheet-100);
            max-height: var(--ft-sheet-100);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            transform: translateY(calc(var(--ft-sheet-100) - var(--ft-drawer-peek)));
            transition: transform 0.32s cubic-bezier(0.32, 0.72, 0, 1);
            animation: ftSheetUp 0.32s cubic-bezier(0.32, 0.72, 0, 1);
            will-change: transform;
        }
        .ft-toolbar .ft-more-controls.open:not(.ft-more-expanded) {
            touch-action: none;
        }
        .ft-toolbar .ft-more-controls.open.ft-more-expanded {
            transform: translateY(0);
            touch-action: pan-y;
        }
        .ft-toolbar .ft-more-grab {
            display: block;
            width: 40px;
            height: 4px;
            margin: 0 auto 2px;
            border-radius: 999px;
            background: var(--ft-border);
            align-self: center;
            flex-shrink: 0;
            cursor: grab;
            touch-action: none;
        }
        .ft-toolbar .ft-more-grab:active {
            cursor: grabbing;
        }
        @keyframes ftSheetUp {
            from { transform: translateY(100%); }
            to { transform: translateY(calc(var(--ft-sheet-100) - var(--ft-drawer-peek))); }
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
            display: inline-flex;
            width: 100%;
        }
        .ft-toolbar .ft-more-controls.open .view-btn {
            flex: 1 1 0;
            padding: 0.45rem 1rem;
            font-size: 0.8rem;
        }
        .ft-toolbar .ft-more-controls.open .filter-popover {
            left: 0;
            right: 0;
        }
    }

    /* ===== Tablet / narrow desktop (641px – 900px) ===== */
    @media (min-width: 641px) and (max-width: 900px) {
        .ft-toolbar .reports-toolbar {
            gap: 8px;
        }
        .ft-toolbar .toolbar-search {
            flex: 1 1 100%;
            min-width: 100%;
        }
        .ft-toolbar .toolbar-search:last-child,
        .ft-toolbar .toolbar-search + .ft-more-controls {
            margin-top: 0;
        }
        .ft-toolbar .toolbar-results {
            margin-left: auto;
            flex-wrap: wrap;
            justify-content: flex-end;
            row-gap: 6px;
        }
        .ft-toolbar .toolbar-results-text {
            white-space: normal;
            text-align: right;
        }
        .ft-toolbar .toolbar-divider {
            display: none;
        }
    }
    /* Same compact control pattern on desktop, tablet, and mobile. */
    .ft-toolbar .ft-more-btn,
    .ft-toolbar .ft-more-controls-header,
    .ft-toolbar .ft-more-backdrop,
    .ft-toolbar .ft-more-grab { display: none !important; }
    .ft-toolbar .ft-more-controls { display: contents !important; }
    .ft-toolbar .reports-toolbar { flex-wrap: wrap; }
    .ft-toolbar .toolbar-search { flex: 1 1 240px; min-width: 0; }
    .ft-toolbar .filter-popover-wrapper { flex: 0 0 auto; }
    .ft-toolbar .filter-popover .date-range-wrapper { margin-top: 14px; }
    .ft-toolbar #ftDateRangeBtn { display: none !important; }
    .ft-toolbar #ftDateRangePopover {
        display: block !important; position: static !important;
        border: 0; box-shadow: none; padding: 0; min-width: 0;
        max-width: none; max-height: none; overflow: visible; animation: none;
    }
    @media (max-width: 1400px) {
        .ft-toolbar .reports-toolbar { flex-direction: row; }
        .ft-toolbar .toolbar-search { flex: 1 1 160px; min-width: 0; }
        .ft-toolbar .toolbar-results { width: 100%; justify-content: flex-start; }
    }

.table-section-header { display:flex; flex-direction:row; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.table-section-header > div:first-child { text-align:left; }
.table-section-header h2 { font-size:14px; font-weight:800; color:#244c3d; }
.table-section-actions { display:flex; align-items:center; justify-content:flex-end; gap:8px; margin-left:auto; flex-wrap:wrap; }
.table-section-header .table-section-actions .export-dropdown,.table-section-header .table-section-actions .btn-export-trigger { width:auto; }
.table-section-actions .table-filter-controls { margin:0; padding:0; border:0; box-shadow:none; background:none; width:auto; }
.table-filter-portal { position:static!important; margin:0!important; padding:0!important; }
.table-filter-portal .filter-popover { z-index:10010; max-width:calc(100vw - 16px); max-height:calc(100dvh - 24px); overflow-y:auto; }
@media(max-width:520px){.table-section-actions{gap:6px}.table-section-actions button{font-size:11px!important}}
</style>

<div class="ft-toolbar">
    <div class="reports-toolbar<?php echo $ft_active_filters > 0 ? ' style-has-chips' : ''; ?>"
         style="<?php echo $ft_active_filters > 0 ? 'border-radius: 12px 12px 0 0;' : ''; ?>">

        <!-- Search (always visible, incl. mobile) -->
        <?php if ($ft_show_search): ?>
        <div class="toolbar-search">
            <i class="fas fa-search"></i>
            <input type="text" id="<?php echo htmlspecialchars($ft_search_id); ?>"
                   value="<?php echo htmlspecialchars($ft_search_value); ?>"
                   placeholder="<?php echo htmlspecialchars($ft_search_placeholder); ?>">
        </div>
        <?php endif; ?>

        <!-- Mobile-only "more filters" trigger (3 dots) -->
        <button type="button" class="ft-more-btn" id="ftMoreBtn" aria-label="More filters" aria-expanded="false">
            <i class="fas <?php echo htmlspecialchars($ft_more_icon, ENT_QUOTES); ?>"></i>
            <?php if ($ft_active_filters > 0): ?>
                <span class="ft-more-badge"><?php echo (int)$ft_active_filters; ?></span>
            <?php endif; ?>
        </button>

        <!-- Controls hidden on mobile behind the 3-dot menu (unchanged on desktop) -->
        <div class="ft-more-controls" id="ftMoreControls">
            <div class="ft-more-grab" id="ftMoreGrab" aria-hidden="true"></div>
            <div class="ft-more-controls-header">
                <span>Filters &amp; Sort</span>
                <button type="button" class="ft-more-close" id="ftMoreClose" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>

            <!-- Filter By popover -->
            <?php if (!empty($ft_popover_fields) || $ft_sort_select || $ft_date_range || $ft_view_toggle): ?>
            <div class="filter-popover-wrapper">
                <button type="button" class="toolbar-filter-btn <?php echo $ft_filter_count > 0 ? 'active' : ''; ?>" id="filterByBtn">
                    <i class="fas fa-sliders-h"></i> Filter By
                    <?php if ($ft_filter_count > 0): ?>
                        <span class="filter-count-badge"><?php echo (int)$ft_filter_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="filter-popover" id="filterPopover" role="dialog" aria-modal="true" aria-label="Filter results" tabindex="-1">
                    <div class="popover-head">
                        <button type="button" class="popover-close" id="popoverClose" aria-label="Close filters"><i class="fas fa-times"></i></button>
                        <button type="button" class="popover-reset-all" id="popoverReset">Reset all</button>
                    </div>
                    <?php foreach ($ft_popover_fields as $pf): ?>
                        <?php if (($pf['kind'] ?? 'date') !== 'select') continue; ?>
                        <?php $pf_multi = !empty($pf['multi']); ?>
                        <div class="pf-group">
                            <div class="pf-group-title"><?php echo htmlspecialchars($pf['label'] ?? ''); ?></div>
                            <input type="hidden" id="<?php echo htmlspecialchars($pf['id'] ?? ''); ?>" value="<?php echo htmlspecialchars((string)($pf['value'] ?? '')); ?>">
                            <div class="pf-chips" role="group" aria-label="<?php echo htmlspecialchars($pf['label'] ?? 'Filter'); ?>" data-input="<?php echo htmlspecialchars($pf['id'] ?? ''); ?>" data-multi="<?php echo $pf_multi ? '1' : '0'; ?>">
                                <?php foreach (($pf['options'] ?? []) as $opt_value => $opt_label): ?>
                                <button type="button" class="pf-chip" data-value="<?php echo htmlspecialchars((string)$opt_value); ?>">
                                    <span class="pf-check"><i class="fas fa-check"></i></span><?php echo htmlspecialchars((string)$opt_label); ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php $ft_dates = array_values(array_filter($ft_popover_fields, fn($field) => ($field['kind'] ?? 'date') === 'date')); ?>
                    <?php if ($ft_dates): ?>
                    <section class="pf-group">
                        <h2 class="pf-group-title">Timeframe</h2>
                        <?php if (count($ft_dates) === 2): ?>
                        <div class="pf-chips pf-date-presets" data-from="<?php echo htmlspecialchars($ft_dates[0]['id']); ?>" data-to="<?php echo htmlspecialchars($ft_dates[1]['id']); ?>">
                            <?php foreach (['today' => 'Today', 'week' => 'Last 7 Days', 'month' => 'Last 30 Days', 'year' => 'YTD'] as $preset => $label): ?>
                            <button type="button" class="pf-chip" data-period="<?php echo $preset; ?>" aria-pressed="false"><span class="pf-check"><i class="fas fa-check" aria-hidden="true"></i></span><?php echo $label; ?></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <div class="pf-date-grid">
                            <?php foreach ($ft_dates as $field): ?>
                            <div class="pf-date-box"><label for="<?php echo htmlspecialchars($field['id']); ?>"><?php echo htmlspecialchars($field['label'] ?? 'Date'); ?></label><input type="date" id="<?php echo htmlspecialchars($field['id']); ?>" value="<?php echo htmlspecialchars($field['value'] ?? ''); ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                    <?php if ($ft_sort_select): ?>
                    <div class="pf-group">
                        <div class="pf-group-title"><?php echo htmlspecialchars($ft_sort_select['label'] ?? 'Sort By'); ?></div>
                        <input type="hidden" id="<?php echo htmlspecialchars($ft_sort_select['id'] ?? ''); ?>" value="<?php echo htmlspecialchars((string)($ft_sort_select['value'] ?? '')); ?>">
                        <div class="pf-chips" data-input="<?php echo htmlspecialchars($ft_sort_select['id'] ?? ''); ?>" data-multi="0">
                            <?php foreach (($ft_sort_select['options'] ?? []) as $so_value => $so_label): ?>
                            <button type="button" class="pf-chip" data-value="<?php echo htmlspecialchars((string)$so_value); ?>">
                                <span class="pf-check"><i class="fas fa-check"></i></span><?php echo htmlspecialchars((string)$so_label); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($ft_view_toggle): ?>
                    <div class="pf-group">
                        <div class="pf-group-title">View</div>
                        <div class="view-toggle" id="ftViewToggle">
                            <button type="button" id="gridViewBtn" aria-label="Grid view" class="view-btn <?php echo ($ft_view_toggle['active'] ?? '') === 'grid' ? 'active' : ''; ?>" onclick="<?php echo htmlspecialchars($ft_view_toggle['grid'] ?? ''); ?>"><i class="fas fa-th"></i> Grid</button>
                            <button type="button" id="listViewBtn" aria-label="List view" class="view-btn <?php echo ($ft_view_toggle['active'] ?? '') === 'list' ? 'active' : ''; ?>" onclick="<?php echo htmlspecialchars($ft_view_toggle['list'] ?? ''); ?>"><i class="fas fa-list"></i> List</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($ft_date_range)): ?>
                    <div class="pf-group">
                        <div class="pf-group-title">Timeframe</div>
                        <div class="date-range-wrapper">
                            <button type="button" id="ftDateRangeBtn" hidden>Date Range</button>
                            <div class="filter-popover open" id="ftDateRangePopover">
                                <div class="popover-title">Date Range</div>
                                <div class="dr-presets" id="ftRangePresets">
                                    <?php foreach (($ft_date_range['presets'] ?? []) as $dr_value => $dr_label): ?>
                                    <button type="button" class="dr-preset <?php echo ((string)($ft_date_range['preset'] ?? '') === (string)$dr_value) ? 'active' : ''; ?>" data-preset="<?php echo htmlspecialchars((string)$dr_value, ENT_QUOTES); ?>"><?php echo htmlspecialchars((string)$dr_label); ?></button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="popover-grid full-width dr-custom">
                                    <div class="popover-field"><label for="ftRangeFrom">From</label><input type="date" id="ftRangeFrom" value="<?php echo htmlspecialchars((string)($ft_date_range['from'] ?? ''), ENT_QUOTES); ?>"></div>
                                    <div class="popover-field"><label for="ftRangeTo">To</label><input type="date" id="ftRangeTo" value="<?php echo htmlspecialchars((string)($ft_date_range['to'] ?? ''), ENT_QUOTES); ?>"></div>
                                </div>
                                <input type="hidden" id="ftRangePreset" value="<?php echo htmlspecialchars((string)($ft_date_range['preset'] ?? ''), ENT_QUOTES); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="popover-actions">
                        <button type="button" class="popover-btn-apply" id="popoverApply"><i class="fas fa-check" style="margin-right:4px"></i>Apply Filter</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($ft_results_text !== ''): ?>
            <div class="toolbar-divider"></div>
            <div class="toolbar-results">
                <?php if ($ft_results_text !== ''): ?>
                    <span class="toolbar-results-text"><?php echo $ft_results_text; ?></span>
                <?php endif; ?>

            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Backdrop for the mobile "more filters" sheet -->
    <div class="ft-more-backdrop" id="ftMoreBackdrop"></div>

    <?php if ($ft_show_active_row && $ft_active_filters > 0): ?>
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
        popoverFields: <?php echo json_encode(array_merge(
            array_map(function ($pf) {
                return ['id' => $pf['id'] ?? '', 'default' => $pf['default'] ?? ''];
            }, $ft_popover_fields),
            $ft_sort_select ? [['id' => $ft_sort_select['id'] ?? '', 'default' => $ft_sort_select['default'] ?? '']] : []
        )); ?>
    };
    if (!document.querySelector('.ft-toolbar .reports-toolbar')) return;

    var searchInput = document.getElementById(FT.searchId);
    if (!searchInput) { searchInput = { value: '', addEventListener: function () {}, focus: function () {} }; }
    var filterBtn = document.getElementById('filterByBtn');
    var filterPopover = document.getElementById('filterPopover');
    var searchTimer = null;
    // Every page uses the same body-level dialog, including header-only pages.
    if (filterPopover) {
        var filterPortal = document.createElement('div');
        filterPortal.className = 'ft-toolbar table-filter-portal';
        filterPortal.appendChild(filterPopover);
        document.body.appendChild(filterPortal);
        filterBtn.setAttribute('aria-controls', filterPopover.id);
        filterBtn.setAttribute('aria-expanded', 'false');
        filterBtn.setAttribute('aria-haspopup', 'dialog');
    }

    // ===== Mobile "3 dots" more menu (bottom sheet) =====
    var moreBtn = document.getElementById('ftMoreBtn');
    var moreControls = document.getElementById('ftMoreControls');
    var moreBackdrop = document.getElementById('ftMoreBackdrop');
    var moreClose = document.getElementById('ftMoreClose');
    var mobileQuery = window.matchMedia('(max-width: <?php echo $ft_compact_breakpoint; ?>px)');

    // Keep report-list search available in the filter sheet when the header is compact.
    var searchWrap = document.getElementById(FT.searchId)?.closest('.toolbar-search');
    function placeHeaderSearch() {
        if (!<?php echo $ft_in_header ? 'true' : 'false'; ?> || !searchWrap || !moreControls || !moreBtn || !document.querySelector('.app-mobile-header')) return;
        if (mobileQuery.matches) {
            moreControls.querySelector('.ft-more-controls-header').after(searchWrap);
        } else {
            moreBtn.before(searchWrap);
        }
    }
    placeHeaderSearch();
    mobileQuery.addEventListener('change', placeHeaderSearch);

    function clearSheetHold() {
        if (!moreControls) return;
        moreControls.style.transform = '';
        moreControls.style.transition = '';
        moreControls.style.animation = '';
        moreControls.style.touchAction = '';
        moreControls.style.userSelect = '';
        moreControls.style.height = '';
        moreControls.style.maxHeight = '';
    }
    function openMore() {
        if (!moreControls) return;
        document.body.classList.add('ft-sheet-open');
        moreControls.classList.add('open');
        clearSheetHold();
        moreBackdrop && moreBackdrop.classList.add('open');
        moreBtn && moreBtn.classList.add('active');
        moreBtn && moreBtn.setAttribute('aria-expanded', 'true');
    }
    function closeMore() {
        if (!moreControls) return;
        document.body.classList.remove('ft-sheet-open');
        moreControls.classList.remove('open');
        moreControls.classList.remove('ft-more-expanded');
        clearSheetHold();
        moreBackdrop && moreBackdrop.classList.remove('open');
        moreBtn && moreBtn.classList.remove('active');
        moreBtn && moreBtn.setAttribute('aria-expanded', 'false');
        closeFilterPopover(false);
        var drPopInline = document.getElementById('ftDateRangePopover');
        if (drPopInline) { drPopInline.classList.remove('open'); drPopInline.style.position = ''; drPopInline.style.left = ''; drPopInline.style.top = ''; }
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

    // ===== Draggable bottom sheet: peek by default, swipe up to full, swipe down to close =====
    var sheetGrab = document.getElementById('ftMoreGrab');
    var dragY = null;
    var dragging = false;
    var dragBaseOffset = 0;
    var sheetFull = 0;
    var sheetPeekOffset = 0;
    function measureSheetLimits() {
        var h = moreControls ? moreControls.offsetHeight : 0;
        sheetFull = h > 0 ? h : (window.innerHeight || document.documentElement.clientHeight);
        sheetPeekOffset = Math.round(sheetFull * 0.62);
    }
    function currentOffset() {
        var tf = window.getComputedStyle(moreControls).transform;
        if (!tf || tf === 'none') return 0;
        var m = tf.match(/matrix\(([^)]+)\)/);
        if (!m) return 0;
        var v = m[1].split(',');
        return parseFloat(v[5]) || 0;
    }
    function grabY(e) {
        return (e.touches && e.touches.length) ? e.touches[0].clientY : e.clientY;
    }
    function canGrab(e) {
        if (!moreControls || !moreControls.classList.contains('open')) return false;
        if (moreControls.scrollTop > 0) return false;
        if (e.target.closest && e.target.closest('.filter-popover, .date-range-wrapper, .toolbar-results, select, input, button, a, label')) return false;
        return true;
    }
    function gDown(y) {
        measureSheetLimits();
        dragBaseOffset = currentOffset();
        moreControls.style.animation = 'none';
        moreControls.style.transition = 'none';
        moreControls.style.touchAction = 'none';
        moreControls.style.userSelect = 'none';
        dragY = y;
        dragging = true;
    }
    function gMove(y) {
        var offset = Math.max(0, Math.min(sheetFull, dragBaseOffset + (y - dragY)));
        moreControls.style.transform = 'translateY(' + offset + 'px)';
    }
    function gEnd(y) {
        if (!dragging) return;
        var dy = y - dragY;
        var wasFull = dragBaseOffset < 20;
        dragging = false;
        dragY = null;
        moreControls.style.touchAction = '';
        moreControls.style.userSelect = '';
        moreControls.style.transition = 'transform 0.3s cubic-bezier(0.32, 0.72, 0, 1)';
        var snap = function (target, expanded) {
            moreControls.classList.toggle('ft-more-expanded', expanded);
            moreControls.style.transform = 'translateY(' + target + 'px)';
            setTimeout(clearSheetHold, 300);
        };
        if (dy < -10) {
            snap(0, true);
        } else if (dy > sheetFull * 0.2) {
            moreControls.style.transform = 'translateY(' + sheetFull + 'px)';
            setTimeout(closeMore, 250);
        } else {
            snap(wasFull ? 0 : sheetPeekOffset, wasFull);
        }
    }
    function onGrabDown(e) {
        if (!canGrab(e)) return;
        if (moreControls.setPointerCapture) {
            try { moreControls.setPointerCapture(e.pointerId); } catch (err) {}
        }
        if (e.cancelable) e.preventDefault();
        gDown(grabY(e));
    }
    function onGrabMove(e) {
        if (!dragging) return;
        if (e.cancelable) e.preventDefault();
        gMove(grabY(e));
    }
    function onGrabEnd(e) {
        if (!dragging) return;
        if (e.cancelable) e.preventDefault();
        gEnd(grabY(e));
    }
    if (window.PointerEvent) {
        sheetGrab && sheetGrab.addEventListener('pointerdown', onGrabDown);
        moreControls && moreControls.addEventListener('pointerdown', onGrabDown);
        document.addEventListener('pointermove', onGrabMove);
        document.addEventListener('pointerup', onGrabEnd);
        document.addEventListener('pointercancel', onGrabEnd);
    } else {
        sheetGrab && sheetGrab.addEventListener('touchstart', onGrabDown);
        moreControls && moreControls.addEventListener('touchstart', onGrabDown);
        document.addEventListener('touchmove', onGrabMove, { passive: false });
        document.addEventListener('touchend', onGrabEnd);
        document.addEventListener('mousedown', onGrabDown);
        document.addEventListener('mousemove', onGrabMove);
        document.addEventListener('mouseup', onGrabEnd);
    }
    // Belt & braces: never let the browser scroll/refresh behind the gesture.
    document.addEventListener('touchmove', function (e) {
        if (dragging && e.cancelable) e.preventDefault();
    }, { passive: false });
    sheetGrab && sheetGrab.addEventListener('click', function () {
        if (moreControls && moreControls.classList.contains('open')) {
            moreControls.classList.toggle('ft-more-expanded');
        }
    });

    // ===== Grid/List view toggle stays inside the 3-dot sheet on mobile =====
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

    // Keep Search in the page header and place Filter By beside the table export.
    document.addEventListener('DOMContentLoaded', function () {
        const explicitActions = document.querySelector('[data-filter-actions]');
        const heading = explicitActions || document.querySelector('.table-section-header');
        const wrapper = filterBtn && filterBtn.closest('.filter-popover-wrapper');
        if (!heading || !wrapper) return;
        let actions = explicitActions || heading.querySelector('.table-section-actions');
        if (!actions) {
            actions = document.createElement('div');
            actions.className = 'table-section-actions';
            const exportControl = heading.querySelector('.export-dropdown, .export-dropdown-wrapper, [id="exportDropdownWrap"]');
            if (exportControl) actions.appendChild(exportControl);
            else {
                const exportButton = Array.from(heading.querySelectorAll('button,a')).find(el => /export/i.test(el.textContent));
                if (exportButton) actions.appendChild(exportButton);
            }
            heading.appendChild(actions);
        }
        const slot = document.createElement('div');
        slot.className = 'ft-toolbar table-filter-controls';
        slot.appendChild(wrapper);
        const exportControl = actions.querySelector('.export-dropdown, .export-dropdown-wrapper');
        if (explicitActions && exportControl) actions.insertBefore(slot, exportControl);
        else actions.prepend(slot);
    });

    // One dialog lifecycle for header and table filters on every screen size.
    var popBackdrop = document.createElement('div');
    popBackdrop.className = 'filter-popover-backdrop';
    document.body.appendChild(popBackdrop);
    var previousOverflow = '';
    var filterDraft = [];

    function syncFilterChips() {
        if (!filterPopover) return;
        var rangePreset = document.getElementById('ftRangePreset');
        filterPopover.querySelectorAll('.dr-preset').forEach(function(button) {
            var selected = !!rangePreset && rangePreset.value === button.dataset.preset;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-pressed', String(selected));
        });
        filterPopover.querySelectorAll('.pf-chips[data-input]').forEach(function(group) {
            var input = document.getElementById(group.dataset.input);
            var raw = (input && input.value) ? String(input.value) : '';
            var values = raw.split(',').filter(function(v) { return v !== ''; });
            if (!values.length) values = [''];
            group.querySelectorAll('.pf-chip').forEach(function(chip) {
                var selected = values.indexOf(chip.dataset.value) !== -1;
                chip.classList.toggle('active', selected);
                chip.setAttribute('aria-pressed', String(selected));
            });
        });
    }
    function clearDatePresetChips() {
        filterPopover.querySelectorAll('.pf-date-presets .pf-chip').forEach(function(chip) {
            chip.classList.remove('active'); chip.setAttribute('aria-pressed', 'false');
        });
    }
    function closeFilterPopover(apply) {
        if (!filterPopover || !filterPopover.classList.contains('open')) return;
        if (!apply) {
            filterDraft.forEach(function(field) { field.element.value = field.value; });
            syncFilterChips();
            clearDatePresetChips();
        }
        filterPopover.classList.remove('open');
        popBackdrop.classList.remove('open');
        filterBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = previousOverflow;
        filterBtn.focus();
    }
    filterBtn && filterBtn.addEventListener('click', function(event) {
        event.stopPropagation();
        if (filterPopover.classList.contains('open')) { closeFilterPopover(false); return; }
        filterDraft = Array.from(filterPopover.querySelectorAll('input,select')).map(function(element) {
            return {element: element, value: element.value};
        });
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        syncFilterChips();
        filterPopover.classList.add('open');
        popBackdrop.classList.add('open');
        filterBtn.setAttribute('aria-expanded', 'true');
        document.getElementById('popoverClose').focus();
    });
    popBackdrop.addEventListener('click', function() { closeFilterPopover(false); });
    var popCloseBtn = document.getElementById('popoverClose');
    popCloseBtn && popCloseBtn.addEventListener('click', function() { closeFilterPopover(false); });
    document.addEventListener('keydown', function(event) {
        if (!filterPopover || !filterPopover.classList.contains('open')) return;
        if (event.key === 'Escape') { event.preventDefault(); closeFilterPopover(false); }
        if (event.key !== 'Tab') return;
        var controls = Array.from(filterPopover.querySelectorAll('button:not(:disabled),input:not([type="hidden"]),a[href]')).filter(function(el) { return el.getClientRects().length; });
        if (!controls.length) return;
        var first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    var applyBtn = document.getElementById('popoverApply');
    applyBtn && applyBtn.addEventListener('click', function() { closeFilterPopover(true); ftRun(); });
    var resetBtn = document.getElementById('popoverReset');
    resetBtn && resetBtn.addEventListener('click', function() {
        FT.popoverFields.forEach(function(field) {
            var element = document.getElementById(field.id);
            if (!element) return;
            element.value = field.default;
            if (element.tagName === 'SELECT' && element.selectedIndex < 0) element.selectedIndex = 0;
        });
        ['ftRangeFrom', 'ftRangeTo', 'ftRangePreset'].forEach(function(id) {
            var element = document.getElementById(id); if (element) element.value = '';
        });
        filterPopover.querySelectorAll('.dr-preset').forEach(function(button) { button.classList.remove('active'); });
        clearDatePresetChips();
        syncFilterChips();
    });
    document.querySelectorAll('.pf-chips[data-input]').forEach(function(group) {
        var input = document.getElementById(group.dataset.input);
        var multi = group.dataset.multi === '1';
        var allChip = group.querySelector('.pf-chip[data-value=""], .pf-chip[data-value="0"]');
        var allValue = allChip ? allChip.dataset.value : '';
        group.querySelectorAll('.pf-chip').forEach(function(chip) {
            chip.addEventListener('click', function() {
                if (!input) return;
                var value = chip.dataset.value;
                var isAll = value === '' || value === '0';
                if (!multi || isAll) {
                    input.value = value;
                } else {
                    var values = input.value ? String(input.value).split(',').filter(function(v) { return v !== '' && v !== '0'; }) : [];
                    var idx = values.indexOf(value);
                    if (idx === -1) values.push(value); else values.splice(idx, 1);
                    input.value = values.length ? values.join(',') : allValue;
                }
                syncFilterChips();
            });
        });
    });
    document.querySelectorAll('.pf-date-presets').forEach(function(group) {
        var from = document.getElementById(group.dataset.from), to = document.getElementById(group.dataset.to);
        group.querySelectorAll('[data-period]').forEach(function(chip) {
            chip.addEventListener('click', function() {
                var end = new Date(), start = new Date(end);
                if (chip.dataset.period === 'week') start.setDate(start.getDate() - 6);
                if (chip.dataset.period === 'month') start.setDate(start.getDate() - 29);
                if (chip.dataset.period === 'year') start = new Date(end.getFullYear(), 0, 1);
                var format = function(date) { return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0'); };
                from.value = format(start); to.value = format(end);
                clearDatePresetChips();
                chip.classList.add('active'); chip.setAttribute('aria-pressed', 'true');
            });
        });
        [from, to].forEach(function(input) { input.addEventListener('change', clearDatePresetChips); });
    });
    syncFilterChips();
    // ===== Optional merged Date Range picker (rendered when $ft['date_range'] is set) =====
    var drBtn = document.getElementById('ftDateRangeBtn');
    var drPop = document.getElementById('ftDateRangePopover');
    if (drBtn && drPop) {
        var drFrom = document.getElementById('ftRangeFrom');
        var drTo = document.getElementById('ftRangeTo');
        var drPreset = document.getElementById('ftRangePreset');
        var drPresets = Array.prototype.slice.call(document.querySelectorAll('#ftRangePresets .dr-preset'));

        function drClose() {
            drPop.classList.remove('open');
            drPop.style.position = '';
            drPop.style.left = '';
            drPop.style.top = '';
        }
        function drPosition() {
            drPop.style.position = 'fixed';
            var btnRect = drBtn.getBoundingClientRect();
            var popW = drPop.offsetWidth || 320;
            var popH = drPop.offsetHeight;
            var viewW = window.innerWidth || document.documentElement.clientWidth;
            var viewH = window.innerHeight || document.documentElement.clientHeight;
            var left = btnRect.left;
            if (left + popW > viewW - 8) left = Math.max(8, viewW - popW - 8);
            var top = btnRect.bottom + 8;
            if (top + popH > viewH - 8) top = Math.max(8, btnRect.top - popH - 8);
            drPop.style.left = left + 'px';
            drPop.style.top = top + 'px';
        }
        drBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var inSheet = moreControls && moreControls.classList.contains('open');
            var willOpen = !drPop.classList.contains('open');
            drPop.classList.toggle('open');
            if (willOpen) {
                // Show first, then measure so height is real (not 0 from display:none)
                // and clamp fully inside the viewport.
                if (inSheet) { drPop.style.position = ''; drPop.style.left = ''; drPop.style.top = ''; }
                else drPosition();
            }
        });
        document.addEventListener('click', function (e) {
            if (drPop.classList.contains('open') && !drPop.contains(e.target) && e.target !== drBtn) drClose();
        });
        drPop.addEventListener('click', function (e) { e.stopPropagation(); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && drPop.classList.contains('open')) drClose();
        });

        function drFillPreset(v) {
            drPresets.forEach(function (p) {
                var selected = p.getAttribute('data-preset') === v;
                p.classList.toggle('active', selected);
                p.setAttribute('aria-pressed', String(selected));
            });
            drPreset.value = v;
            if (v && v !== 'all') {
                var today = new Date();
                var ymd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
                var f = new Date(today);
                if (v === 'week') { f.setDate(today.getDate() - 6); }
                else if (v === 'month') { f = new Date(today.getFullYear(), today.getMonth(), 1); }
                else if (v === 'year') { f = new Date(today.getFullYear(), 0, 1); }
                drFrom.value = ymd(f);
                drTo.value = ymd(today);
            } else {
                drFrom.value = '';
                drTo.value = '';
            }
        }
        drPresets.forEach(function (p) {
            p.addEventListener('click', function () { drFillPreset(p.getAttribute('data-preset')); });
        });
        [drFrom, drTo].forEach(function (input) {
            input.addEventListener('change', function () {
                drPreset.value = '';
                syncFilterChips();
            });
        });
        var drApplyBtn = document.getElementById('ftRangeApply');
        drApplyBtn && drApplyBtn.addEventListener('click', function () { drClose(); ftRun(); });
        var drResetBtn = document.getElementById('ftRangeReset');
        drResetBtn && drResetBtn.addEventListener('click', function () {
            drPreset.value = '';
            drFrom.value = '';
            drTo.value = '';
            drPresets.forEach(function (p) { p.classList.remove('active'); });
            drClose();
            ftRun();
        });
    }

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
