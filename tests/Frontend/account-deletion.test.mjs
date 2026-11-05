import test from 'node:test';
import assert from 'node:assert/strict';
import { accountDeletionManager } from '../../resources/js/account-deletion.js';

function fixture(rows, failure = null) {
    const effects = [];
    const data = accountDeletionManager(7, {}, {
        storage: { async list() { if (failure) throw failure; return rows; } },
        async purgeShell() { effects.push('purged'); },
    });
    const event = { preventDefault() {}, target: { submit() { effects.push('submitted'); } } };
    return { data, event, effects };
}

test('unconfirmed financial orders prevent account deletion without changing the outbox', async () => {
    for (const status of ['pending', 'auth', 'error']) {
        const rows = [{ context: { userId: '7' }, status }];
        const { data, event, effects } = fixture(rows);
        await data.submit(event);
        assert.match(data.queueError, /sinkronisasi/);
        assert.deepEqual(effects, []);
        assert.equal(rows[0].status, status);
    }
});

test('cancelled or other user orders are retained while account form submits after shell purge', async () => {
    const rows = [{ context: { userId: '7' }, status: 'cancelled' }, { context: { userId: '8' }, status: 'pending' }];
    const { data, event, effects } = fixture(rows);
    await data.submit(event);
    assert.deepEqual(effects, ['purged', 'submitted']);
    assert.equal(rows.length, 2);
});

test('storage errors prevent deletion instead of assuming there are no pending orders', async () => {
    const { data, event, effects } = fixture([], new Error('Storage blocked'));
    await data.submit(event);
    assert.ok(data.queueError);
    assert.deepEqual(effects, []);
    assert.equal(data.busy, false);
});

test('offline deletion stays on the confirmation screen without submitting a request', async () => {
    let submitted = false;
    const data = accountDeletionManager(7, {}, { isOnline: () => false, storage: { async list() { throw new Error('Not expected while offline'); } } });
    await data.submit({ preventDefault() {}, target: { submit() { submitted = true; } } });
    assert.match(data.queueError, /internet/);
    assert.equal(submitted, false);
    assert.equal(data.busy, false);
});
