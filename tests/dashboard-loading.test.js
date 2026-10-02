'use strict';
const assert = require('node:assert/strict');
const test = require('node:test');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/dashboard-loading.js'), 'utf8');

function browser(initialLoad = true) {
    let now = 0, serial = 0;
    const timers = new Map(), events = {};
    const overlay = { hidden: true, dataset: { initialLoad: initialLoad ? '1' : '0' }, classList: { remove() {} } };
    const schedule = (fn, delay = 0) => { const id = ++serial; timers.set(id, { fn, at: now + delay }); return id; };
    const window = {
        setTimeout: schedule, addEventListener: (name, fn) => { events[name] = fn; },
        requestAnimationFrame: fn => schedule(fn, 16)
    };
    vm.runInNewContext(source, {
        window, document: { getElementById: () => overlay, readyState: 'loading' },
        performance: { now: () => now }, clearTimeout: id => timers.delete(id)
    });
    return { overlay, window, events, advance(ms) {
        const end = now + ms;
        while (true) {
            const next = [...timers.entries()].filter(([, item]) => item.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
            if (!next) break;
            timers.delete(next[0]); now = next[1].at; next[1].fn();
        }
        now = end;
    } };
}
test('fast initial load never flashes the skeleton', () => {
    const b = browser(); b.advance(100); b.events.load(); b.advance(1000);
    assert.equal(b.overlay.hidden, true);
});
test('slow initial load displays until the page has rendered', () => {
    const b = browser(); b.advance(649); assert.equal(b.overlay.hidden, true);
    b.advance(1); assert.equal(b.overlay.hidden, false);
    b.events.load(); b.advance(31); assert.equal(b.overlay.hidden, false);
    b.advance(1); assert.equal(b.overlay.hidden, true);
});
test('later pages do not show loading even when slow', () => {
    const b = browser(false); b.advance(10000); assert.equal(b.overlay.hidden, true);
});
test('login transition is delayed and can be cancelled', () => {
    const b = browser(false); b.window.SierraDashboardLoading.show();
    b.advance(650); assert.equal(b.overlay.hidden, false);
    b.window.SierraDashboardLoading.hide(); assert.equal(b.overlay.hidden, true);
});
test('failed resources and back navigation cannot trap the overlay', () => {
    const b = browser(); b.advance(12000); assert.equal(b.overlay.hidden, true);
    const back = browser(); back.advance(650); back.events.pageshow({ persisted: true });
    assert.equal(back.overlay.hidden, true);
});
