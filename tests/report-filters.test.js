'use strict';
// Actual toolbar configurations and callbacks, rendered without an app/database session.
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const cp = require('node:child_process');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
let chromium;
try { ({chromium} = require('playwright')); }
catch (_) { ({chromium} = require('C:/Users/kenne/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright')); }
const pages = {
    all: 'views/admin/all_reports.php', verify: 'views/barangay/verify_reports.php',
    audit: 'views/admin/audit_logs.php', users: 'views/admin/users.php',
    announcements: 'views/shared/announcements.php', analytics: 'views/admin/analytics.php',
    barangay: 'views/barangay/dashboard.php', categories: 'views/admin/settings/partials/categories.php',
    map: 'views/shared/hazard_map.php', my: 'views/citizen/my_reports.php'
};
function source(file) { return fs.readFileSync(path.join(root, file), 'utf8'); }
function fn(text, name) {
    const matches = [...text.matchAll(new RegExp('^function ' + name + '\\([^]*?^}', 'gm'))];
    const match = matches[matches.length - 1];
    assert.ok(match, 'Actual callback exists: ' + name);
    return match[0].replace(/window\.location\.href = ([^;]+);/g, 'window.__target = $1;');
}
function render(kind, variant = '') {
    const text = source(pages[kind]);
    const start = text.indexOf('$ft = [');
    const closing = text.slice(start).match(/^\s*\];\s*$/m);
    assert.ok(closing, 'Toolbar config terminator');
    const end = start + closing.index + closing[0].trimEnd().length;
    let config = text.slice(start, end);
    if (kind === 'users') config = text.slice(text.lastIndexOf('$ft_popover_count = 0;', start), end);
    if (kind === 'announcements') config += text.slice(end, text.indexOf('?>', end));
    if (kind === 'map') config += text.slice(end, text.indexOf('?>', end));
    let callback = kind === 'map' ? 'function applyMapFilters() { window.__applied = (window.__applied || 0) + 1; }' : fn(text, ['analytics','barangay'].includes(kind) ? 'applyDashboardFilters' : 'applyFilters');
    if (kind === 'barangay') callback = fn(text, '_buildAnalyticsUrl') + '\n' + callback;
    const setup = `
        define('BASE_PATH', ${JSON.stringify(root.replace(/\\/g,'/') + '/')}); define('BASE_URL', 'http://localhost/');
        function t($s) { return $s; }
        $search = $search_keyword = $search_query = ''; $reports = $logs = $ft_chips = []; $total = $total_reports = $total_logs = $active_filters = $ft_popover_count = 0;
        $risk_filter = $risk = $risk_level = $category_raw = $residency_filter = $date_from = $date_to = $user_filter = $registered_filter = '';
        $category_filter = ${kind === 'announcements' ? "'all'" : '0'}; $barangay_filter = $date_range = 0; $limit = 20; $sort_order = 'newest';
        $status_filter = ${['audit','categories'].includes(kind) ? "'all'" : "''"}; $action_filter = 'all'; $actions = ['Login', 'Create Report'];
        $users = [['id'=>17,'first_name'=>'Ada','last_name'=>'Santos'],['id'=>42,'first_name'=>'Ben','last_name'=>'Cruz']];
        $ft_cat_options = ['0'=>'All Categories','2'=>'Water','7'=>'Waste']; $ft_barangay_options = ['0'=>'All Barangays','3'=>'Calaba','9'=>'Alua'];
        $display_count = $display_total = 0; $tab_label = 'Citizens'; $users_tab = 'citizens';
        $show_barangay_filter = $show_status_filter = true; $barangay_list = [['id'=>3,'name'=>'Calaba'],['id'=>9,'name'=>'Alua']];
        $is_citizen = ${variant === 'citizen' ? 'true' : 'false'}; $is_admin = !$is_citizen; $broadcast_barangay = 0; $barangays = $barangay_list; $categories = ['Tree Planting','Clean-up Drive'];
        $f_search = $f_barangay = ''; $f_status = $f_risk = 'all'; $f_category = 0;
        $dash_status_options = ['all'=>'All Statuses','pending'=>'Pending','escalated'=>'Escalated']; $dash_risk_options = ['all'=>'All Severities','low'=>'Low','high'=>'High'];
        $dash_barangay_options = [''=>'All Barangays','Calaba'=>'Calaba','Alua'=>'Alua']; $dash_category_options = $ft_cat_options;
        $dash_date_preset = $analytics_date_from = $analytics_date_to = ''; $dash_date_preset_options = [''=>'All Dates','today'=>'Today','week'=>'This Week','month'=>'This Month','year'=>'This Year'];
        $filtered_categories = []; $total_categories = 0; $mapCategoryOptions = [''=>'All Hazards','2'=>'Water','7'=>'Waste'];
        $hazardMapBarangays = ${variant === 'barangay' ? '[]' : '$barangay_list'}; $mapBarangayOptions = [''=>'All Barangays','3'=>'Calaba','9'=>'Alua'];
        $filter_risk = $filter_date = $filter_category_raw = ''; $filter_category = 0;
        ${config}
        // Expose active-chip removal even when no filter has been chosen yet.
        $ft['active_filters'] = 1; $ft['chips_clear_all'] = true; $ft['show_active_row'] = true; $ft['chips'] = ['<span class="filter-chip">Search <button class="chip-remove" data-filter="search">x</button></span>'];
        echo '<!doctype html><html><head><style>body{margin:20px;font-family:Arial}button{cursor:pointer}</style></head><body>';
        ${['all','verify','my'].includes(kind) ? "echo '<input type=\"hidden\" id=\"toolbarStatus\" value=\"\">';" : ''}
        include BASE_PATH.'views/shared/report_filter_toolbar.php';
        echo '<div class="table-section-header"><h2>Records</h2><div class="table-section-actions"><button>Export</button></div></div>';
        echo '<script>function showLoading(){} ${callback.replace(/'/g, "\\'")}</script></body></html>';
    `;
    const result = cp.spawnSync(php, [], {input: '<?php ' + setup, encoding:'utf8'});
    assert.equal(result.status, 0, result.stderr);
    assert.ok(!/Warning:|Fatal error:/.test(result.stdout), result.stdout.slice(0,400));
    return result.stdout;
}
(async function () {
    const browser = await chromium.launch({headless:true, channel:'msedge'});
    try {
        for (const width of [390, 1440]) {
            const page = await browser.newPage({viewport:{width,height:1000}});
            page.setDefaultTimeout(5000);
            const errors = []; page.on('pageerror', e => errors.push(e.message));
            for (const kind of Object.keys(pages)) {
                await page.setContent(render(kind));
                if (kind === 'my') {
                    assert.equal(await page.locator('#filterByBtn').count(), 0, 'My Reports retains chips without Filter By');
                    continue;
                }
                const initial = await page.locator('#filterPopover input').evaluateAll(nodes => Object.fromEntries(nodes.map(n=>[n.id,n.value])));
                await page.locator('#filterByBtn').click();
                const group = page.locator('.pf-chips[data-input]').first();
                const field = await group.getAttribute('data-input');
                const choices = group.locator('.pf-chip');
                await choices.nth(1).click();
                const selected = await choices.nth(1).getAttribute('data-value');
                assert.equal(await page.locator('#'+field).inputValue(), selected, kind+' selection');
                if (await group.getAttribute('data-multi') === '1' && await choices.count() > 2) {
                    await choices.nth(2).click();
                    assert.equal((await page.locator('#'+field).inputValue()).split(',').length,2,kind+' multi-select');
                }
                await page.locator('#popoverClose').click();
                assert.deepEqual(await page.locator('#filterPopover input').evaluateAll(nodes => Object.fromEntries(nodes.map(n=>[n.id,n.value]))),initial,kind+' Cancel restores draft');
                await page.locator('#filterByBtn').click();
                await choices.nth(1).click();
                await page.locator('#popoverApply').click();
                assert.equal(await page.locator('#filterByBtn').getAttribute('aria-expanded'),'false',kind+' Apply closes');
                assert.ok(await page.evaluate(()=>!!window.__target || !!window.__applied),kind+' actual callback runs');
                if (kind === 'audit') {
                    assert.equal(selected,'17','Audit user options preserve actual account ID');
                    assert.equal(new URL(await page.evaluate(()=>window.__target),'http://localhost/').searchParams.get('user'),'17');
                }
                await page.locator('#filterByBtn').click();
                await page.locator('#popoverReset').click();
                assert.equal(await page.locator('#'+field).inputValue(),initial[field],kind+' Reset restores default');
                const dates = page.locator('#filterPopover input[type=date]');
                if (await dates.count() === 2) {
                    await dates.nth(0).fill('2026-10-10'); await dates.nth(1).fill('2026-10-01');
                    await page.locator('#popoverApply').click();
                    assert.equal(await page.locator('#filterByBtn').getAttribute('aria-expanded'),'true',kind+' invalid date stays open');
                    assert.equal(await page.locator('#filterDateError').isVisible(),true,kind+' date error visible');
                    await dates.nth(1).fill('2026-10-12'); await page.locator('#popoverApply').click();
                    const url = new URL(await page.evaluate(()=>window.__target),'http://localhost/');
                    assert.equal(url.searchParams.get('date_from'),'2026-10-10',kind+' from callback');
                    assert.equal(url.searchParams.get('date_to'),'2026-10-12',kind+' to callback');
                } else await page.locator('#popoverApply').click();
                await page.locator('#clearAllFilters').click();
                assert.equal(await page.locator('#'+field).inputValue(),initial[field],kind+' Clear all defaults');
                if (['all','verify'].includes(kind)) {
                    await page.locator('#toolbarStatus').evaluate(node=>node.value='under_review');
                    await page.locator('#filterByBtn').click(); await page.locator('#popoverReset').click();
                    assert.equal(await page.locator('#toolbarStatus').inputValue(),'','Reset includes status chips outside the popup');
                    await page.locator('#popoverClose').click();
                    assert.equal(await page.locator('#toolbarStatus').inputValue(),'under_review','Cancel restores external status after a draft reset');
                    await page.locator('#toolbarStatus').evaluate(node=>node.value='');
                }
                // Every configured control must reach its page's real URL callback.
                const urlKeys = {popoverRisk:'risk',popoverCategory:'category',popoverBarangay:'barangay',popoverUser:'user',toolbarAction:'action',toolbarStatus:'status',toolbarRegistered:'registered',toolbarResidency:'residency',popoverResidency:'residency',popoverDateRange:'date_range',toolbarCategory:'category',toolbarBarangay:'barangay',toolbarSort:'sort',toolbarLimit:'limit',dashStatusFilter:'status',dashRiskFilter:'risk',dashBarangayFilter:'barangay',dashCategoryFilter:'category',barangayStatusFilter:'status',barangayRiskFilter:'risk'};
                const groups = page.locator('.pf-chips[data-input]');
                for (let i=0; i<await groups.count(); i++) {
                    await page.locator('#filterByBtn').click();
                    await page.locator('#popoverReset').click();
                    const current = groups.nth(i), id = await current.getAttribute('data-input');
                    const choice = current.locator('.pf-chip').nth(1), value = await choice.getAttribute('data-value');
                    await choice.click(); await page.locator('#popoverApply').click();
                    if (kind !== 'map') {
                        const url = new URL(await page.evaluate(()=>window.__target),'http://localhost/');
                        assert.equal(url.searchParams.get(urlKeys[id]),value,kind+' '+id+' applies actual option');
                    }
                }
                if (kind === 'map') {
                    await page.evaluate(() => {
                        document.body.insertAdjacentHTML('beforeend', '<div id="sierraHazardMap"></div><span id="sierraMapCount"></span><select id="sierraMapPeriod"><option value="all">All</option><option value="custom">Custom</option></select><input id="sierraMapFrom" type="date"><input id="sierraMapTo" type="date"><div id="sierraMapDates"></div><button data-map-mode="active">Active</button><button data-map-mode="historical">Historical</button>');
                        window.sierraHazardMapData = {defaults:{}, reports:[
                            {id:1,title:'Canal',category_id:2,barangay_id:3,severity_score:12,status:'pending',latitude:15,longitude:120,created_at:'2026-10-09',url:'index.php'},
                            {id:2,title:'Waste',category_id:7,barangay_id:9,severity_score:17,status:'escalated',latitude:15,longitude:120,created_at:'2026-10-02',url:'index.php'},
                            {id:3,title:'Other',category_id:2,barangay_id:9,severity_score:2,status:'pending',latitude:15,longitude:120,created_at:'2026-10-01',url:'index.php'},
                            {id:4,title:'Resolved',category_id:2,barangay_id:3,severity_score:12,status:'resolved',latitude:15,longitude:120,created_at:'2026-09-01',resolved_at:'2026-10-05',url:'index.php'}
                        ]};
                        window.L={map:()=>({setView(){return this;}}),divIcon:x=>x,marker:()=>({bindPopup(){return this;}})};
                        window.MapLayers={addControl(){}};
                        window.SierraMapClusters={layer:()=>({addTo(){},clearLayers(){},addLayers(rows){window.__markers=rows;}})};
                        ['popoverCategory','popoverRisk','popoverBarangay'].forEach(id=>document.getElementById(id).value='');
                    });
                    await page.addScriptTag({content:source('assets/js/hazard-map.js')});
                    assert.equal(await page.evaluate(()=>window.__markers.length),3,'Map starts with active reports only');
                    await page.locator('#filterByBtn').click();
                    for (const [id,values] of Object.entries({popoverCategory:['2','7'],popoverRisk:['high','critical'],popoverBarangay:['3','9']})) {
                        for (const value of values) await page.locator('.pf-chips[data-input="'+id+'"] .pf-chip[data-value="'+value+'"]').click();
                    }
                    await page.locator('#popoverApply').click();
                    assert.equal(await page.evaluate(()=>window.__markers.length),2,'Actual map combines multiple categories, severities and Barangays');
                    await page.locator('#sierraMapPeriod').selectOption('custom');
                    await page.locator('#sierraMapFrom').fill('2026-10-01'); await page.locator('#sierraMapTo').fill('2026-10-08');
                    await page.locator('#sierraMapTo').dispatchEvent('change');
                    assert.equal(await page.evaluate(()=>window.__markers.length),1,'Map custom dates filter actual pins');
                    await page.locator('[data-map-mode=historical]').click();
                    assert.equal(await page.evaluate(()=>window.__markers.length),1,'Historical dates use resolution time and retain the other filters');
                }
                assert.equal(errors.length,0,kind+': '+errors.join(', '));
                console.log('PASS '+kind+' filters at '+width+'px');
            }
            await page.setContent(render('announcements','citizen'));
            assert.equal(await page.locator('#filterByBtn').count(),0,'Citizen announcements keep Filter By hidden');
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
