import test from 'node:test';
import assert from 'node:assert/strict';
import { billingPaymentWatcher } from '../../resources/js/billing-payment-status.js';

function setup(fetcher) {
    let reloads = 0;
    let cleared = false;
    globalThis.document = { visibilityState: 'visible', querySelector: () => ({ content: 'csrf-test' }) };
    globalThis.window = { location: { reload: () => reloads++ }, setInterval: () => 42, clearInterval: () => { cleared = true; } };
    globalThis.fetch = fetcher;
    const watcher = billingPaymentWatcher({ payments: [{ url: '/billings/1/refresh', status: 'pending' }] });
    return { watcher, reloads: () => reloads, cleared: () => cleared };
}

test('changed verified status reloads the billing page with a CSRF-protected JSON request', async () => {
    let options;
    const state = setup(async (url, request) => { options = request; return { ok: true, json: async () => ({ payment_status: 'completed' }) }; });
    await state.watcher.check();
    assert.equal(state.reloads(), 1);
    assert.equal(options.method, 'POST');
    assert.equal(options.headers['X-CSRF-TOKEN'], 'csrf-test');
    assert.equal(options.headers.Accept, 'application/json');
    assert.equal(options.credentials, 'same-origin');
});

test('pending statuses and network failures never reload or assume payment', async () => {
    const pending = setup(async () => ({ ok: true, json: async () => ({ payment_status: 'pending' }) }));
    await pending.watcher.check();
    assert.equal(pending.reloads(), 0);
    const failed = setup(async () => { throw new Error('Offline'); });
    await failed.watcher.check();
    assert.equal(failed.reloads(), 0);
    assert.equal(failed.watcher.checking, false);
});

test('hidden pages and overlapping checks do not issue gateway requests', async () => {
    let count = 0;
    const state = setup(async () => { count++; return { ok: false }; });
    document.visibilityState = 'hidden';
    await state.watcher.check();
    document.visibilityState = 'visible';
    state.watcher.checking = true;
    await state.watcher.check();
    assert.equal(count, 0);
});

test('polling stops and page cleanup clears its timer', async () => {
    const state = setup(async () => ({ ok: true, json: async () => ({ payment_status: 'pending' }) }));
    state.watcher.init();
    assert.equal(state.watcher.timer, 42);
    state.watcher.destroy();
    assert.equal(state.cleared(), true);
    assert.equal(state.watcher.timer, null);
});

test('Snap callbacks verify through the prepared backend URL and never trust callback status or redirects', () => {
    let callbacks;
    let assigned;
    const state = setup(async () => ({ ok: false }));
    window.snap = { pay: (token, options) => { assert.equal(token, 'owned-token'); callbacks = options; } };
    window.location.assign = url => { assigned = url; };
    state.watcher.pay('owned-token', '/billings?order_id=OWNED');
    assert.equal(state.watcher.popupOpen, true);
    callbacks.onSuccess({ transaction_status: 'settlement', redirect_url: 'https://invalid.example' });
    assert.equal(assigned, '/billings?order_id=OWNED');
    assert.equal(state.reloads(), 0);
});

test('closing or failing a popup still checks authoritative status and cannot submit twice', () => {
    for (const event of ['onClose', 'onError', 'onPending']) {
        let callbacks;
        let opened = 0;
        let checked = 0;
        const state = setup(async () => ({ ok: false }));
        window.snap = { pay: (_, options) => { opened++; callbacks = options; } };
        window.location.assign = () => checked++;
        state.watcher.pay('owned-token', '/billings?order_id=OWNED');
        state.watcher.pay('owned-token', '/billings?order_id=OWNED');
        assert.equal(opened, 1);
        callbacks[event]();
        callbacks.onClose();
        assert.equal(checked, 1);
    }
});

test('SDK load failures preserve retry and the hosted checkout fallback', () => {
    const state = setup(async () => ({ ok: false }));
    state.watcher.pay('owned-token', '/billings?order_id=OWNED');
    assert.equal(state.watcher.popupOpen, false);
    assert.equal(state.watcher.snapUnavailable, true);
    assert.ok(state.watcher.paymentError.length > 0);
});

test('polling waits while a Snap popup is active', async () => {
    let calls = 0;
    const state = setup(async () => { calls++; return { ok: false }; });
    state.watcher.popupOpen = true;
    await state.watcher.check();
    assert.equal(calls, 0);
});

test('a status request already in flight cannot reload over an opened popup', async () => {
    let release;
    const state = setup(() => new Promise(resolve => { release = resolve; }));
    const checking = state.watcher.check();
    window.snap = { pay: () => {} };
    state.watcher.pay('owned-token', '/billings?order_id=OWNED');
    release({ ok: true, json: async () => ({ payment_status: 'completed' }) });
    await checking;
    assert.equal(state.reloads(), 0);
});

test('new invoice auto-opens its popup once after the SDK load event', () => {
    let onLoad;
    let opened = 0;
    const state = setup(async () => ({ ok: false }));
    state.watcher.initialCheckout = { token: 'new-token', verifyUrl: '/billings?order_id=NEW' };
    window.addEventListener = (_, fn) => { onLoad = fn; };
    window.snap = { pay: token => { assert.equal(token, 'new-token'); opened++; } };
    state.watcher.init();
    onLoad();
    onLoad();
    assert.equal(opened, 1);
});
