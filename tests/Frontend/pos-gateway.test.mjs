import test from 'node:test';
import assert from 'node:assert/strict';
import { webcrypto } from 'node:crypto';
import { submitPosGateway } from '../../resources/js/pos-gateway.js';
function fixture(fetcher) {
    const records = new Map();
    globalThis.document = { querySelector: () => ({ content: 'csrf-test' }) };
    const storage = { getItem: key => records.get(key), setItem: (key, value) => records.set(key, value), removeItem: key => records.delete(key) };
    return { records, options: { endpoint: '/pos/gateway-checkout', payload: { items: [{ id: 'product-A', quantity: 1 }], customer_phone: '081234567890' }, context: { userId: 1, branchId: 2, shiftId: 3 }, storage, cryptoProvider: webcrypto, fetcher } };
}
test('lost gateway responses retain the same request UUID for retry without storing customer data', async () => {
    const ids = [];let calls = 0;
    const f = fixture(async (_, request) => { ids.push(JSON.parse(request.body).client_transaction_id); if (++calls === 1) throw new Error('Network lost'); return { ok: true, json: async () => ({ id: 1, redirect_url: '/pos/gateway-payments/1' }) }; });
    await assert.rejects(submitPosGateway(f.options));
    assert.equal(f.records.size, 1);
    assert.equal([...f.records.values()][0].includes('081234567890'), false);
    const result = await submitPosGateway(f.options);
    assert.equal(ids[0], ids[1]);assert.equal(result.id, 1);assert.equal(f.records.size, 0);
});
test('gateway validation failures preserve the request ID and never clear the cart implicitly', async () => {
    const f = fixture(async () => ({ ok: false, json: async () => ({ message: 'Stok tidak cukup' }) }));
    await assert.rejects(submitPosGateway(f.options), /Stok tidak cukup/);
    assert.equal(f.records.size, 1);assert.equal(f.options.payload.items.length, 1);
});
test('gateway requests abort after a bounded timeout and preserve their stable request', { timeout: 1000 }, async () => {
    const f = fixture(async (_, request) => new Promise((_, reject) => request.signal?.addEventListener('abort', () => reject(new Error('Request aborted')))));
    await assert.rejects(submitPosGateway({ ...f.options, timeoutMs: 5 }), /aborted/);
    assert.equal(f.records.size, 1);
});
