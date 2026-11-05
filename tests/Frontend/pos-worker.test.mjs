import assert from 'node:assert/strict';
import test from 'node:test';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';

const origin = 'https://pos.example';
function memoryCaches() {
    const all = new Map();
    return {
        async keys() { return [...all.keys()]; },
        async delete(name) { return all.delete(name); },
        async open(name) {
            if (!all.has(name)) all.set(name, new Map());
            const rows = all.get(name);
            const key = input => typeof input === 'string' ? new URL(input, origin).href : input.url;
            return {
                async match(input) { return rows.get(key(input))?.clone(); },
                async put(input, value) { rows.set(key(input), value.clone()); },
                async delete(input) { return rows.delete(key(input)); },
                async keys() { return [...rows.keys()].map(url => new Request(url)); },
                async addAll(urls) { for (const url of urls) rows.set(key(url), new Response('offline fallback')); },
            };
        },
    };
}
async function worker(caches = memoryCaches()) {
    const listeners = {};
    let online = true;
    let status = 200;
    let identity = { userId: '1', branchId: '2' };
    let extraHtml = '';
    const requests = [];
    const assetFailures = new Map();
    const clients = new Map([['tab-a', { id: 'tab-a', url: `${origin}/pos` }], ['tab-b', { id: 'tab-b', url: `${origin}/pos` }]]);
    const self = { location: { origin }, clients: { get: async id => clients.get(id), claim: async () => {} }, skipWaiting: async () => {}, addEventListener: (name, listener) => { listeners[name] = listener; } };
    const fetcher = async request => {
        requests.push(request.url);
        if (!online) throw new TypeError('offline');
        if (assetFailures.has(new URL(request.url).pathname)) return new Response('missing asset', { status: assetFailures.get(new URL(request.url).pathname) });
        if (['/logo.png', '/favicon.png'].includes(new URL(request.url).pathname)) return new Response('public image', { headers: { 'Content-Type': 'image/png' } });
        return new Response('<meta name="pos-offline-user" content="' + identity.userId + '"><meta name="pos-offline-branch" content="' + identity.branchId + '">' + extraHtml + 'private POS shell', { status, headers: { 'Content-Type': 'text/html' } });
    };
    const sandbox = vm.createContext({ self, caches, fetch: fetcher, URL, Request, Response, Map, Set, console });
    vm.runInContext(await readFile(new URL('../../public/sw.js', import.meta.url), 'utf8'), sandbox);
    const lifecycle = async name => { let work; listeners[name]({ waitUntil: promise => { work = promise; } }); await work; };
    await lifecycle('install');
    await lifecycle('activate');
    return {
        caches,
        requests,
        setAssetFailure(path, status = 404) { assetFailures.set(path, status); },
        async resource(url) {
            let response;
            listeners.fetch({ request: new Request(new URL(url, origin)), respondWith: promise => { response = promise; }, waitUntil() {} });
            return response ? await response : null;
        },
        setHtml(value) { extraHtml = value; },
        setOnline(value) { online = value; },
        setStatus(value) { status = value; },
        setIdentity(userId, branchId) { identity = { userId, branchId }; },
        async message(data, clientId = 'tab-a') {
            let work, reply;
            listeners.message({ data, source: clients.get(clientId), ports: [{ postMessage: value => { reply = value; } }], waitUntil: promise => { work = promise; } });
            await work;
            return reply;
        },
        async navigate({ url = '/pos', clientId = '', replacesClientId = '', resultingClientId = 'new-tab' } = {}) {
            let response;
            listeners.fetch({ request: { method: 'GET', mode: 'navigate', url: new URL(url, origin).href }, clientId, replacesClientId, resultingClientId, respondWith: promise => { response = promise; }, waitUntil() {} });
            return response ? await response : null;
        },
    };
}
const cacheMessage = (userId = '1', branchId = '2') => ({ type: 'POS_CACHE_SHELL', userId, branchId, url: `${origin}/pos` });

test('cached authenticated shell is available only to its bound browser client on failed navigation', async () => {
    const app = await worker();
    assert.equal((await app.message(cacheMessage())).ok, true);
    app.setOnline(false);
    assert.match(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), /private POS shell$/);
    assert.equal(await (await app.navigate({ resultingClientId: 'stranger' })).text(), 'offline fallback');
    assert.equal(await (await app.navigate({ url: '/sales', clientId: 'tab-a' })).text(), 'offline fallback');
});

test('client bindings survive worker restart and propagate to the replacement client', async () => {
    const original = await worker();
    await original.message(cacheMessage());
    original.setOnline(false);
    await original.navigate({ replacesClientId: 'tab-a', resultingClientId: 'refreshed-tab' });
    const restarted = await worker(original.caches);
    restarted.setOnline(false);
    assert.match(await (await restarted.navigate({ replacesClientId: 'refreshed-tab' })).text(), /private POS shell$/);
});

test('identity changes invalidate the previous shell and logout purges every private client binding', async () => {
    const app = await worker();
    await app.message(cacheMessage());
    app.setIdentity('9', '7');
    await app.message(cacheMessage('9', '7'), 'tab-b');
    app.setOnline(false);
    assert.equal(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), 'offline fallback');
    assert.match(await (await app.navigate({ replacesClientId: 'tab-b' })).text(), /private POS shell$/);
    await app.message({ type: 'POS_PURGE_SHELL' }, 'tab-b');
    assert.equal(await (await app.navigate({ replacesClientId: 'tab-b' })).text(), 'offline fallback');
});

test('a real HTTP authentication or server failure never falls back to authenticated HTML', async () => {
    const app = await worker();
    await app.message(cacheMessage());
    app.setStatus(401);
    assert.equal((await app.navigate({ replacesClientId: 'tab-a' })).status, 401);
    app.setOnline(false);
    assert.equal(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), 'offline fallback');
});

test('shell messages reject non-POS sources and never cache arbitrary private pages', async () => {
    const app = await worker();
    const result = await app.message({ ...cacheMessage(), url: `${origin}/sales` });
    assert.ok(result.error);
    app.setOnline(false);
    assert.equal(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), 'offline fallback');
});


test('shell caching rejects a response belonging to a different signed-in identity', async () => {
    const app = await worker();
    app.setIdentity('99', '2');
    assert.ok((await app.message(cacheMessage())).error);
    app.setOnline(false);
    assert.equal(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), 'offline fallback');
});

test('asset discovery ignores quoted application text inside JavaScript', async () => {
    const app = await worker();
    app.setHtml('<script src="/build/assets/app.js"></script>');
    assert.equal((await app.message(cacheMessage())).ok, true);
    assert.deepEqual(app.requests, [`${origin}/pos`, `${origin}/build/assets/app.js`]);
});


test('referenced public root logo and favicon are available offline without caching private images', async () => {
    const app = await worker();
    app.setHtml('<img src="/logo.png"><link rel="icon" href="/favicon.png"><img src="/storage/avatars/user.png"><img src="/tenant/logo.png"><img src="https://other.example/logo.png">');
    assert.equal((await app.message(cacheMessage())).ok, true);
    assert.deepEqual(app.requests, [`${origin}/pos`, `${origin}/logo.png`, `${origin}/favicon.png`]);
    app.setOnline(false);
    assert.equal(await (await app.resource('/logo.png')).text(), 'public image');
    assert.equal(await (await app.resource('/favicon.png')).text(), 'public image');
    assert.equal(await app.resource('/storage/avatars/user.png'), null);
    assert.equal(await app.resource('/tenant/logo.png'), null);
    assert.equal(await app.resource('https://other.example/logo.png'), null);
    assert.equal(await app.resource('/sales'), null);
});

test('missing optional root icons never prevent preparing the offline POS shell', async () => {
    const app = await worker();
    app.setHtml('<img src="/logo.png"><link rel="icon" href="/favicon.png">');
    app.setAssetFailure('/logo.png');
    app.setAssetFailure('/favicon.png', 500);
    assert.equal((await app.message(cacheMessage())).ok, true);
    app.setOnline(false);
    assert.match(await (await app.navigate({ replacesClientId: 'tab-a' })).text(), /private POS shell$/);
});
