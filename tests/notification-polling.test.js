'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../assets/js/notification-polling.js'), 'utf8');

function browser({ hidden = false, online = true } = {}) {
    let now = 0, serial = 0, calls = 0;
    const timers = new Map(), events = {}, elements = {};
    let respond = async () => ({ ok: true, status: 200, json: async () => ({ success: true, unread: 0, notif_seq: 0, latest: null }) });
    function element() {
        return { style: {}, children: [], setAttribute() {}, addEventListener() {},
            appendChild(child) { this.children.push(child); child.parentNode = this; if (child.id) elements[child.id] = child; },
            removeChild(child) { this.children = this.children.filter(x => x !== child); child.parentNode = null; }
        };
    }
    const document = {
        hidden, readyState: 'complete', currentScript: { getAttribute: () => '/notifications' },
        addEventListener: (name, fn) => { events[name] = fn; }, body: element(),
        getElementById: id => elements[id] || null, querySelector: () => null,
        createElement: element, createTextNode: text => ({ text })
    };
    const navigator = { onLine: online };
    const context = { document, navigator, AbortController,
        window: { addEventListener: (name, fn) => { events[name] = fn; } },
        Date: class extends Date { static now() { return now; } },
        setTimeout(fn, delay) { const id = ++serial; timers.set(id, { at: now + delay, fn }); return id; },
        clearTimeout(id) { timers.delete(id); },
        fetch(url, options) { calls++; return respond(url, options); }
    };
    vm.runInNewContext(source, context);
    async function flush() { for (let i = 0; i < 12; i++) await Promise.resolve(); }
    return { document, navigator, elements,
        calls: () => calls, respond(fn) { respond = fn; },
        async event(name) { events[name](); await flush(); },
        async advance(ms) {
            const end = now + ms;
            for (;;) {
                const due = [...timers].filter(([, t]) => t.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
                if (!due) break;
                now = due[1].at; timers.delete(due[0]); due[1].fn(); await flush();
            }
            now = end; await flush();
        }
    };
}

(async () => {
    const app = browser();
    await app.advance(2500); assert.equal(app.calls(), 1);
    await app.advance(59999); assert.equal(app.calls(), 1);
    app.respond(async () => ({ ok: true, status: 200, json: async () => ({ success: true, unread: 1, notif_seq: 1, latest: { id: 1, title: 'New report', message: 'Test', created_at: '2026-10-01 10:00:00' } }) }));
    await app.advance(1); assert.equal(app.calls(), 2);
    assert.equal(app.elements.rtToastWrapper.children.length, 1, 'first new notification after an empty baseline shows a toast');
    app.document.hidden = true; await app.event('visibilitychange');
    await app.advance(120000); assert.equal(app.calls(), 2);
    app.document.hidden = false; await app.event('visibilitychange');
    await app.advance(0); assert.equal(app.calls(), 3);
    await app.event('visibilitychange'); await app.advance(0); assert.equal(app.calls(), 3, 'tab changes cannot bypass the interval');
    app.navigator.onLine = false; await app.event('offline');
    await app.advance(120000); assert.equal(app.calls(), 3);
    app.navigator.onLine = true; await app.event('online'); await app.advance(0); assert.equal(app.calls(), 4);
    console.log('PASS 60-second polling, first notification, visibility and offline behavior');

    const outage = browser();
    outage.respond(async () => ({ ok: false, status: 502 }));
    await outage.advance(2500); assert.equal(outage.calls(), 1);
    await outage.advance(119999); assert.equal(outage.calls(), 1);
    await outage.advance(1); assert.equal(outage.calls(), 2);
    await outage.advance(239999); assert.equal(outage.calls(), 2);
    await outage.advance(1); assert.equal(outage.calls(), 3);
    await outage.advance(300000); assert.equal(outage.calls(), 4);
    outage.respond(async () => ({ ok: true, status: 200, json: async () => ({ success: true, unread: 0, notif_seq: 0 }) }));
    await outage.advance(300000); assert.equal(outage.calls(), 5);
    await outage.advance(60000); assert.equal(outage.calls(), 6);
    console.log('PASS outage backoff caps at five minutes and recovers to one minute');

    const slow = browser();
    let aborted = false;
    slow.respond((url, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => { aborted = true; reject(new Error('Timeout')); });
    }));
    await slow.advance(2500); await slow.event('visibilitychange'); await slow.advance(14999);
    assert.equal(slow.calls(), 1); assert.equal(aborted, false);
    await slow.advance(1); assert.equal(aborted, true);
    await slow.advance(119999); assert.equal(slow.calls(), 1);
    await slow.advance(1); assert.equal(slow.calls(), 2);
    console.log('PASS hung requests time out and never overlap');

    const loggedOut = browser();
    loggedOut.respond(async () => ({ ok: false, status: 401 }));
    await loggedOut.advance(2500); await loggedOut.advance(600000);
    await loggedOut.event('visibilitychange'); await loggedOut.advance(0);
    assert.equal(loggedOut.calls(), 1);
    const hidden = browser({ hidden: true }); await hidden.advance(600000); assert.equal(hidden.calls(), 0);
    console.log('PASS expired sessions stop polling; initially hidden tabs stay idle');
})().catch(error => { console.error(error); process.exitCode = 1; });
