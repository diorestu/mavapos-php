const SCOPE_FIELDS = ['userId', 'cashierId', 'branchId', 'shiftId'];
const OWNER_FIELDS = SCOPE_FIELDS.slice(0, 3);
const copy = value => structuredClone(value);

function normalizeContext(context) {
    if (!context || OWNER_FIELDS.some(key => !/^\d+$/.test(String(context[key])) || Number(context[key]) < 1) || (context.shiftId != null && (!/^\d+$/.test(String(context.shiftId)) || Number(context.shiftId) < 1))) {
        throw new Error('POS offline requires a known user, cashier, branch and valid shift context.');
    }
    return Object.fromEntries(SCOPE_FIELDS.map(key => [key, context[key] == null ? null : String(context[key])]));
}

function matches(left, right, fields = SCOPE_FIELDS) {
    return fields.every(key => String(left[key]) === String(right[key]));
}

function canonical(value) {
    if (Array.isArray(value)) return value.map(canonical);
    if (value && typeof value === 'object') return Object.fromEntries(Object.keys(value).sort().map(key => [key, canonical(value[key])]));
    return value;
}

export function createIndexedDbStorage(indexedDB = globalThis.indexedDB) {
    let database;
    const open = () => database ??= new Promise((resolve, reject) => {
        if (!indexedDB) return reject(new Error('Browser storage is unavailable. Keep this order open and reconnect.'));
        const request = indexedDB.open('mavapos-offline', 1);
        request.onupgradeneeded = () => request.result.createObjectStore('orders', { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
        request.onblocked = () => reject(new Error('Close older POS tabs to enable durable browser storage.'));
    });
    const run = async (mode, action) => {
        const db = await open();
        return new Promise((resolve, reject) => {
            const transaction = db.transaction('orders', mode);
            const request = action(transaction.objectStore('orders'));
            // Resolve on transaction commit, not request success: a later quota error must block sending.
            transaction.oncomplete = () => resolve(request.result);
            transaction.onerror = () => reject(transaction.error || request.error);
            transaction.onabort = () => reject(transaction.error || request.error || new Error('Browser storage write aborted.'));
        });
    };
    return {
        add: row => run('readwrite', store => store.add(row)),
        get: id => run('readonly', store => store.get(id)),
        put: row => run('readwrite', store => store.put(row)),
        remove: id => run('readwrite', store => store.delete(id)),
        list: () => run('readonly', store => store.getAll()),
    };
}

export function createCheckoutSender({ url = '/pos/checkout', fetcher = globalThis.fetch } = {}) {
    return async row => {
        const csrf = globalThis.document?.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetcher(url, {
            method: 'POST', credentials: 'same-origin', redirect: 'error',
            headers: {
                'Content-Type': 'application/json', Accept: 'application/json',
                'Idempotency-Key': row.id, ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            },
            body: JSON.stringify(row.payload),
        });
        let data;
        try { data = await response.json(); } catch {
            if (response.ok) throw new Error('The server response could not be confirmed. Retry using this transaction ID.');
            data = { message: `Checkout failed (HTTP ${response.status}).` };
        }
        return { ok: response.ok, status: response.status, data };
    };
}

export function createPosOutbox({ context: initialContext, storage = createIndexedDbStorage(), send = createCheckoutSender(), onSynced } = {}) {
    let context = normalizeContext(initialContext);
    let paused = false;
    let sequence = Promise.resolve();
    const listeners = new Set();
    const notify = () => { for (const listener of listeners) { try { listener(); } catch (error) { console.error(error); } } };
    const serialize = action => {
        const result = sequence.then(action);
        sequence = result.catch(() => {});
        return result;
    };
    const listRows = async owned => (await storage.list())
        .filter(row => row.status !== 'cancelled' && matches(row.context, context, owned ? OWNER_FIELDS : SCOPE_FIELDS))
        .sort((left, right) => left.createdAt.localeCompare(right.createdAt) || left.id.localeCompare(right.id));

    const enqueue = async (payload, { id = globalThis.crypto.randomUUID() } = {}) => {
        if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id)) throw new Error('A valid transaction UUID is required.');
        const capturedContext = copy(context);
        if (!capturedContext.shiftId) throw new Error('A known active shift is required before creating an offline order.');
        if (payload.cashier_shift_id != null && String(payload.cashier_shift_id) !== capturedContext.shiftId) throw new Error('Order shift differs from the active POS context.');
        if (payload.cashier_id != null && String(payload.cashier_id) !== capturedContext.cashierId) throw new Error('Order cashier differs from the active POS context.');
        if (payload.client_transaction_id != null && payload.client_transaction_id !== id) throw new Error('Order transaction ID differs from its durable ID.');
        const immutablePayload = copy({ ...payload, client_transaction_id: id, cashier_shift_id: payload.cashier_shift_id ?? Number(capturedContext.shiftId) });
        const existing = await storage.get(id);
        if (existing) {
            if (existing.status === 'cancelled') throw new Error('This transaction was cancelled. Start a new checkout.');
            if (!matches(existing.context, capturedContext) || JSON.stringify(canonical(existing.payload)) !== JSON.stringify(canonical(immutablePayload))) {
                throw new Error('This transaction ID already belongs to a different order or POS context.');
            }
            return copy(existing);
        }
        const row = { id, context: capturedContext, payload: immutablePayload, createdAt: new Date().toISOString(), attempts: 0, status: 'pending', error: null };
        await storage.add(row);
        notify();
        return copy(row);
    };

    const transmit = async (id, capturedContext, owned = false, pendingOnly = false) => {
        const row = await storage.get(id);
        const fields = owned ? OWNER_FIELDS : SCOPE_FIELDS;
        if (!row || row.status === 'cancelled' || (pendingOnly && row.status !== 'pending') || !matches(context, capturedContext) || !matches(row.context, context, fields)) return null;
        if (paused) return { id, status: 'auth', error: row.error };
        row.attempts++;
        row.lastAttemptAt = new Date().toISOString();
        await storage.put(row);
        let response;
        try {
            response = await send(copy(row));
            if (!response || typeof response.ok !== 'boolean' || !Number.isInteger(response.status)) throw new Error('The checkout result could not be confirmed.');
        } catch (error) {
            row.status = 'pending';
            row.error = { type: 'network', message: error.message || 'Connection unavailable. Retry this transaction after reconnecting.' };
            await storage.put(row);
            notify();
            return { id, status: 'pending', error: copy(row.error) };
        }
        if (response.ok) {
            await storage.remove(id);
            notify();
            if (onSynced && matches(context, capturedContext)) {
                try { await onSynced(response.data, copy(row)); } catch (error) { console.error('POS synchronization callback failed', error); }
            }
            return { id, status: 'synced', data: response.data };
        }
        paused = [401, 419].includes(response.status);
        row.status = paused ? 'auth' : 'error';
        row.error = { type: paused ? 'auth' : 'http', status: response.status, message: response.data?.message || `Checkout failed (HTTP ${response.status}).`, errors: response.data?.errors || null };
        await storage.put(row);
        notify();
        return { id, status: row.status, error: copy(row.error), data: response.data };
    };

    const retryRows = (id, owned) => {
        const capturedContext = copy(context);
        return serialize(async () => {
            const rows = id ? [{ id }] : (await listRows(owned)).filter(row => row.status === 'pending');
            const results = [];
            for (const row of rows) {
                const result = await transmit(row.id, capturedContext, owned, !id);
                if (result) results.push(result);
                if (result?.status === 'auth' || result?.status === 'pending') break;
            }
            return results;
        });
    };

    return {
        get context() { return copy(context); },
        get paused() { return paused; },
        setContext(nextContext) { context = normalizeContext(nextContext); paused = false; notify(); },
        subscribe(listener) { listeners.add(listener); return () => listeners.delete(listener); },
        enqueue,
        cancel(id) {
            return serialize(async () => {
                const row = await storage.get(id);
                if (!row || !matches(row.context, context, OWNER_FIELDS) || row.status !== 'error' || ![400, 403, 404, 422].includes(row.error?.status)) {
                    throw new Error('Only a definitively rejected order can be cancelled.');
                }
                row.status = 'cancelled';
                row.cancelledAt = new Date().toISOString();
                await storage.put(row);
                notify();
                return copy(row);
            });
        },
        async submit(payload, options) {
            const capturedContext = copy(context);
            return serialize(async () => {
                if (!matches(context, capturedContext)) throw new Error('POS context changed before checkout. Review this order in the current session.');
                const row = await enqueue(payload, options);
                return await transmit(row.id, capturedContext) || { id: row.id, status: 'pending' };
            });
        },
        list: () => listRows(false),
        listOwned: () => listRows(true),
        retry: id => retryRows(id, Boolean(id)),
        retryOwned: () => retryRows(null, true),
    };
}

async function messageWorker(worker, message) {
    return new Promise((resolve, reject) => {
        const channel = new MessageChannel();
        const timeout = setTimeout(() => { channel.port1.close(); reject(new Error('Offline shell setup timed out.')); }, 15000);
        channel.port1.onmessage = event => {
            clearTimeout(timeout);
            channel.port1.close();
            if (event.data?.error) reject(new Error(event.data.error));
            else resolve(event.data);
        };
        worker.postMessage(message, [channel.port2]);
    });
}

export async function initPosOfflineShell({ userId, branchId, url = globalThis.location?.href } = {}) {
    if (!globalThis.navigator?.serviceWorker || !/^\d+$/.test(String(userId)) || !/^\d+$/.test(String(branchId))) return false;
    const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    if (registration.installing) {
        const installing = registration.installing;
        await new Promise((resolve, reject) => {
            const changed = () => {
                if (installing.state === 'activated') resolve();
                else if (installing.state === 'redundant') reject(new Error('Offline worker installation failed.'));
            };
            installing.addEventListener('statechange', changed);
            changed();
        });
    }
    await navigator.serviceWorker.ready;
    const worker = registration.active || navigator.serviceWorker.controller;
    if (!worker) return false;
    return messageWorker(worker, { type: 'POS_CACHE_SHELL', userId: String(userId), branchId: String(branchId), url });
}

export async function purgePosOfflineShell() {
    if (!globalThis.navigator?.serviceWorker) return false;
    const registration = await navigator.serviceWorker.getRegistration('/');
    const worker = registration?.active || navigator.serviceWorker.controller;
    return worker ? messageWorker(worker, { type: 'POS_PURGE_SHELL' }) : false;
}
