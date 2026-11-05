import assert from 'node:assert/strict';
import test from 'node:test';
import { createPosOutbox } from '../../resources/js/pos-offline.js';

const context = { userId: 1, cashierId: 2, branchId: 3, shiftId: 4 };
const payload = { items: [{ product_id: 8, quantity: 1 }], expected_total: 12000 };
const id1 = '70000000-0000-4000-8000-000000000001';
const id2 = '70000000-0000-4000-8000-000000000002';
const clone = value => structuredClone(value);
function memoryStorage() {
    const rows = new Map();
    return {
        async add(row) { if (rows.has(row.id)) throw new Error('duplicate'); rows.set(row.id, clone(row)); },
        async get(id) { return clone(rows.get(id)); },
        async put(row) { rows.set(row.id, clone(row)); },
        async remove(id) { rows.delete(id); },
        async list() { return clone([...rows.values()]); },
    };
}

test('persists before transmission and retries unknown results with the original UUID across reload', async () => {
    const storage = memoryStorage();
    const ids = [];
    const outbox = createPosOutbox({ storage, context, send: async row => {
        assert.equal((await storage.get(row.id)).id, id1);
        ids.push(row.payload.client_transaction_id);
        throw new TypeError('connection lost after server commit');
    } });
    assert.equal((await outbox.submit(payload, { id: id1 })).status, 'pending');
    const restored = createPosOutbox({ storage, context, send: async row => {
        ids.push(row.payload.client_transaction_id);
        return { ok: true, status: 200, data: { invoice: 'real-server-invoice' } };
    } });
    assert.equal((await restored.list()).length, 1);
    assert.equal((await restored.retry())[0].status, 'synced');
    assert.deepEqual(ids, [id1, id1]);
    assert.deepEqual(await restored.list(), []);
});

test('never transmits another user, cashier, branch or shift queue', async () => {
    const storage = memoryStorage();
    const original = createPosOutbox({ storage, context });
    await original.enqueue(payload, { id: id1 });
    for (const key of Object.keys(context)) {
        let sends = 0;
        const other = createPosOutbox({ storage, context: { ...context, [key]: 99 }, send: async () => { sends++; } });
        assert.deepEqual(await other.list(), []);
        assert.deepEqual(await other.retry(), []);
        if (key !== 'shiftId') assert.deepEqual(await other.retry(id1), []);
        assert.equal(sends, 0);
    }
    assert.equal((await original.list()).length, 1);
});

test('retains validation and conflict errors while continuing a sequential retry', async () => {
    const storage = memoryStorage();
    let active = 0;
    let maximum = 0;
    const outbox = createPosOutbox({ storage, context, send: async row => {
        active++; maximum = Math.max(maximum, active);
        await new Promise(resolve => setTimeout(resolve, 5));
        active--;
        return { ok: false, status: row.id === id1 ? 422 : 409, data: { message: 'Stock changed', errors: { items: ['Insufficient stock'] } } };
    } });
    await outbox.enqueue(payload, { id: id1 });
    await outbox.enqueue(payload, { id: id2 });
    const results = await outbox.retry();
    assert.deepEqual(results.map(result => result.status), ['error', 'error']);
    assert.equal(maximum, 1);
    assert.equal((await outbox.list())[0].error.message, 'Stock changed');
    assert.equal((await outbox.list())[1].error.status, 409);
});

test('401 and 419 pause retries and retain every pending transaction', async () => {
    for (const status of [401, 419]) {
        const storage = memoryStorage();
        let sends = 0;
        const outbox = createPosOutbox({ storage, context, send: async () => { sends++; return { ok: false, status, data: { message: 'Login required' } }; } });
        await outbox.enqueue(payload, { id: id1 });
        await outbox.enqueue(payload, { id: id2 });
        assert.equal((await outbox.retry())[0].status, 'auth');
        assert.equal(outbox.paused, true);
        await outbox.retry();
        assert.equal(sends, 1);
        assert.equal((await outbox.list()).length, 2);
    }
});

test('a context switch interrupts a running batch before the next send', async () => {
    const storage = memoryStorage();
    let outbox;
    let sends = 0;
    outbox = createPosOutbox({ storage, context, send: async () => {
        sends++;
        outbox.setContext({ ...context, branchId: 99 });
        return { ok: true, status: 200, data: {} };
    } });
    await outbox.enqueue(payload, { id: id1 });
    await outbox.enqueue(payload, { id: id2 });
    await outbox.retry();
    assert.equal(sends, 1);
    outbox.setContext(context);
    assert.equal((await outbox.list()).length, 1);
});

test('durability failures prevent checkout; repeated UUID cannot overwrite a different order', async () => {
    let sends = 0;
    const storage = memoryStorage();
    const outbox = createPosOutbox({ storage, context, send: async () => { sends++; } });
    await outbox.enqueue(payload, { id: id1 });
    await assert.rejects(outbox.enqueue({ ...payload, expected_total: 999 }, { id: id1 }), /different/i);
    const unavailable = createPosOutbox({ context, storage: { ...storage, add: async () => { throw new Error('disk full'); } }, send: async () => { sends++; } });
    await assert.rejects(unavailable.submit(payload, { id: id2 }), /disk full/);
    assert.equal(sends, 0);
});

test('concurrent retry requests do not submit a successfully removed order again', async () => {
    const storage = memoryStorage();
    let sends = 0;
    const outbox = createPosOutbox({ storage, context, send: async () => {
        sends++;
        await new Promise(resolve => setTimeout(resolve, 5));
        return { ok: true, status: 200, data: {} };
    } });
    await outbox.enqueue(payload, { id: id1 });
    await Promise.all([outbox.retry(), outbox.retry()]);
    assert.equal(sends, 1);
});


test('owned queues retry a closed original shift without changing its stored payload', async () => {
    const storage = memoryStorage();
    const old = createPosOutbox({ storage, context });
    await old.enqueue({ ...payload, cashier_shift_id: 4, client_created_at: '2026-11-04T01:00:00.000Z' }, { id: id1 });
    let sent;
    const current = createPosOutbox({ storage, context: { ...context, shiftId: 5 }, send: async row => {
        sent = row;
        return { ok: true, status: 200, data: {} };
    } });
    assert.equal((await current.list()).length, 0);
    assert.equal((await current.listOwned()).length, 1);
    await current.retryOwned();
    assert.equal(sent.context.shiftId, '4');
    assert.equal(sent.payload.cashier_shift_id, 4);
    assert.equal(sent.payload.client_created_at, '2026-11-04T01:00:00.000Z');
});

test('only definitively rejected orders can be cancelled and cancellation retains the durable audit row', async () => {
    const storage = memoryStorage();
    const outbox = createPosOutbox({ storage, context, send: async () => ({ ok: false, status: 422, data: { message: 'Price changed' } }) });
    await outbox.enqueue(payload, { id: id1 });
    await assert.rejects(outbox.cancel(id1), /rejected/i);
    await outbox.retry();
    await outbox.cancel(id1);
    assert.deepEqual(await outbox.listOwned(), []);
    assert.equal((await storage.get(id1)).status, 'cancelled');
    assert.equal((await storage.get(id1)).payload.client_transaction_id, id1);
    await assert.rejects(outbox.submit(payload, { id: id1 }), /cancelled/i);
    assert.deepEqual(await outbox.retry(id1), []);
});

test('a user without an active shift may reconcile owned orders but cannot create a new order', async () => {
    const storage = memoryStorage();
    const old = createPosOutbox({ storage, context });
    await old.enqueue(payload, { id: id1 });
    const current = createPosOutbox({ storage, context: { ...context, shiftId: null }, send: async row => {
        assert.equal(row.payload.cashier_shift_id, 4);
        return { ok: true, status: 200, data: {} };
    } });
    assert.equal((await current.listOwned()).length, 1);
    await assert.rejects(current.enqueue(payload, { id: id2 }), /shift/i);
    assert.equal((await current.retryOwned())[0].status, 'synced');
});

test('HTTP rejections require an explicit retry even when stock or login changes', async () => {
    for (const status of [400, 401, 403, 404, 409, 419, 422, 500]) {
        const storage = memoryStorage();
        let resolved = false;
        let sends = 0;
        const send = async row => {
            sends++;
            assert.equal(row.payload.client_transaction_id, id1);
            return resolved ? { ok: true, status: 200, data: {} } : { ok: false, status, data: { message: 'Rejected' } };
        };
        const original = createPosOutbox({ storage, context, send });
        await original.submit(payload, { id: id1 });
        resolved = true;
        original.setContext(context);
        assert.deepEqual(await original.retry(), []);
        assert.deepEqual(await original.retryOwned(), []);
        const restored = createPosOutbox({ storage, context, send });
        assert.deepEqual(await restored.retryOwned(), []);
        assert.equal(sends, 1);
        assert.equal((await restored.retry(id1))[0].status, 'synced');
        assert.equal(sends, 2);
    }
});

test('automatic retry rechecks persisted status before sending if another tab rejects the order', async () => {
    const storage = memoryStorage();
    const original = createPosOutbox({ storage, context });
    await original.enqueue(payload, { id: id1 });
    let sends = 0;
    const otherTabStorage = { ...storage, get: async id => {
        const row = await storage.get(id);
        row.status = 'error';
        row.error = { type: 'http', status: 409, message: 'Rejected in another tab' };
        await storage.put(row);
        return row;
    } };
    const outbox = createPosOutbox({ storage: otherTabStorage, context, send: async () => { sends++; } });
    assert.deepEqual(await outbox.retryOwned(), []);
    assert.equal(sends, 0);
});

test('a 409 conflict cannot be cancelled because its UUID may already identify a committed checkout', async () => {
    const storage = memoryStorage();
    const outbox = createPosOutbox({ storage, context, send: async () => ({ ok: false, status: 409, data: { message: 'Transaction context changed' } }) });
    await outbox.submit(payload, { id: id1 });
    await assert.rejects(outbox.cancel(id1), /rejected/i);
    assert.equal((await outbox.listOwned())[0].id, id1);
    assert.equal((await storage.get(id1)).status, 'error');
});

test('a concurrent background queue scan cannot consume a foreground submission and report it pending', async () => {
    const storage = memoryStorage();
    let releaseScan, announceScan;
    const scanStarted = new Promise(resolve => { announceScan = resolve; });
    const scanGate = new Promise(resolve => { releaseScan = resolve; });
    let sends = 0;
    const slowStorage = { ...storage, list: async () => {
        announceScan();
        await scanGate;
        return storage.list();
    } };
    const outbox = createPosOutbox({ storage: slowStorage, context, send: async () => {
        sends++;
        return { ok: true, status: 200, data: { sale: { id: 99 } } };
    } });
    const background = outbox.retryOwned();
    await scanStarted;
    const foreground = outbox.submit(payload, { id: id1 });
    await new Promise(resolve => setImmediate(resolve));
    releaseScan();
    await background;
    const result = await foreground;
    assert.equal(result.status, 'synced');
    assert.equal(result.data.sale.id, 99);
    assert.equal(sends, 1);
    assert.deepEqual(await storage.list(), []);
});
