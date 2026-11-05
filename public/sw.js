'use strict';

const VERSION = 'v1';
const PUBLIC_CACHE = `mavapos-public-${VERSION}`;
const PRIVATE_CACHE = `mavapos-pos-shell-${VERSION}`;
const META_CACHE = `mavapos-pos-context-${VERSION}`;
const OFFLINE_URL = '/offline.html';
const OPTIONAL_PUBLIC_FILES = new Set(['/logo.png', '/favicon.png']);
const ORIGIN = self.location.origin;
const ACTIVE_KEY = `${ORIGIN}/__pos_offline__/active`;
let operations = Promise.resolve();

function exclusive(action) {
    const result = operations.then(action);
    operations = result.catch(() => {});
    return result;
}

function clientKey(id) {
    return `${ORIGIN}/__pos_offline__/client/${encodeURIComponent(id)}`;
}

function shellKey(scope, url) {
    return `${ORIGIN}/__pos_offline__/shell/${encodeURIComponent(scope)}/${encodeURIComponent(url)}`;
}

async function readMeta(key) {
    const response = await (await caches.open(META_CACHE)).match(key);
    return response ? response.json() : null;
}

async function writeMeta(key, value) {
    await (await caches.open(META_CACHE)).put(key, new Response(JSON.stringify(value), { headers: { 'Content-Type': 'application/json' } }));
}

async function purgePrivate() {
    await caches.delete(PRIVATE_CACHE);
    await caches.delete(META_CACHE);
}

function posUrl(input) {
    const url = new URL(input, ORIGIN);
    return url.origin === ORIGIN && url.pathname === '/pos' ? url.href : null;
}

function publicAsset(input, base = ORIGIN) {
    const url = new URL(input, base);
    const allowed = OPTIONAL_PUBLIC_FILES.has(url.pathname) || (url.pathname.startsWith('/build/assets/') && /\.(js|css|woff2?|ttf|otf|png|jpe?g|webp|gif|svg|ico|avif)$/i.test(url.pathname));
    return url.origin === ORIGIN && allowed ? url.href : null;
}

async function cachePublicAssets(html, base) {
    const cache = await caches.open(PUBLIC_CACHE);
    const queue = [];
    const visited = new Set();
    const collect = (text, parent) => {
        for (const match of text.matchAll(/["'`]([^"'`\s<>]+)["'`]/g)) {
            try {
                const input = match[1];
                const parsed = new URL(input, parent);
                const isOptional = parsed.origin === ORIGIN && OPTIONAL_PUBLIC_FILES.has(parsed.pathname);
                const isReference = isOptional || input.startsWith('/build/assets/') || input.startsWith(`${ORIGIN}/build/assets/`) || input.startsWith('./') || input.startsWith('../') || /\.css(\?|$)/.test(parent);
                const asset = isReference ? publicAsset(input, parent) : null;
                if (asset && !visited.has(asset)) queue.push(asset);
            } catch { /* Only well-formed public asset URLs belong in this cache. */ }
        }
    };
    collect(html, base);
    while (queue.length && visited.size < 100) {
        const url = queue.shift();
        if (visited.has(url)) continue;
        visited.add(url);
        let response = await cache.match(url);
        if (!response) {
            const optional = OPTIONAL_PUBLIC_FILES.has(new URL(url).pathname);
            try {
                response = await fetch(new Request(url, { credentials: 'same-origin' }));
                if (!response.ok || response.redirected || (optional && !response.headers.get('Content-Type')?.startsWith('image/'))) {
                    if (optional) continue;
                    throw new Error('POS assets are not ready for offline use.');
                }
                await cache.put(url, response.clone());
            } catch (error) {
                if (optional) continue;
                throw error;
            }
        }
        if (/\.(css|js)(\?|$)/.test(url)) collect(await response.text(), url);
    }
}

function identityMatches(html, userId, branchId) {
    const meta = name => {
        for (const match of html.matchAll(/<meta\b[^>]*>/gi)) {
            const attributes = Object.fromEntries([...match[0].matchAll(/([\w-]+)\s*=\s*["']([^"']*)["']/g)].map(attribute => [attribute[1].toLowerCase(), attribute[2]]));
            if (attributes.name === name) return attributes.content;
        }
        return null;
    };
    return meta('pos-offline-user') === userId && meta('pos-offline-branch') === branchId;
}

async function warmShell(event) {
    const { userId, branchId } = event.data;
    if (!/^[1-9]\d*$/.test(String(userId)) || !/^[1-9]\d*$/.test(String(branchId))) throw new Error('A known POS identity is required.');
    const source = event.source?.id ? await self.clients.get(event.source.id) : null;
    const url = posUrl(event.data.url);
    if (!source || !posUrl(source.url) || !url || source.url !== url) throw new Error('Only the current POS page can prepare its offline shell.');
    const scope = `${userId}:${branchId}`;
    const current = await readMeta(ACTIVE_KEY);
    if (current && current.scope !== scope) await purgePrivate();
    let response;
    try {
        response = await fetch(new Request(url, { credentials: 'same-origin', cache: 'no-store', redirect: 'follow' }));
    } catch (error) {
        const cached = current?.scope === scope && await (await caches.open(PRIVATE_CACHE)).match(shellKey(scope, url));
        if (!cached) throw error;
        await writeMeta(clientKey(source.id), { scope, url });
        return { ok: true, offline: true };
    }
    if (!response.ok || response.redirected || (response.url && !posUrl(response.url)) || !response.headers.get('Content-Type')?.includes('text/html')) {
        await purgePrivate();
        throw new Error('The authenticated POS page could not be prepared.');
    }
    const html = await response.clone().text();
    if (!identityMatches(html, String(userId), String(branchId))) {
        await purgePrivate();
        throw new Error('POS identity changed. Reload online before using offline checkout.');
    }
    await cachePublicAssets(html, url);
    await (await caches.open(PRIVATE_CACHE)).put(shellKey(scope, url), response);
    await writeMeta(ACTIVE_KEY, { scope });
    await writeMeta(clientKey(source.id), { scope, url });
    return { ok: true, offline: false };
}

async function offlineNavigation(event) {
    return exclusive(async () => {
        const url = posUrl(event.request.url);
        const previousId = event.replacesClientId || event.clientId;
        const binding = previousId ? await readMeta(clientKey(previousId)) : null;
        const active = await readMeta(ACTIVE_KEY);
        if (url && binding?.scope === active?.scope && binding?.url === url) {
            const shell = await (await caches.open(PRIVATE_CACHE)).match(shellKey(binding.scope, url));
            if (shell) {
                if (event.resultingClientId) await writeMeta(clientKey(event.resultingClientId), binding);
                return shell;
            }
        }
        return await (await caches.open(PUBLIC_CACHE)).match(OFFLINE_URL) || new Response('Reconnect to open POS.', { status: 503, headers: { 'Content-Type': 'text/plain' } });
    });
}

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        await (await caches.open(PUBLIC_CACHE)).addAll([OFFLINE_URL]);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const name of await caches.keys()) {
            if ((name.startsWith('mavapos-') && ![PUBLIC_CACHE, PRIVATE_CACHE, META_CACHE].includes(name)) || name === 'offline-cache-v2') await caches.delete(name);
        }
        await self.clients.claim();
    })());
});

self.addEventListener('message', event => {
    if (!['POS_CACHE_SHELL', 'POS_PURGE_SHELL'].includes(event.data?.type)) return;
    event.waitUntil(exclusive(async () => {
        try {
            const result = event.data.type === 'POS_PURGE_SHELL' ? (await purgePrivate(), { ok: true }) : await warmShell(event);
            event.ports?.[0]?.postMessage(result);
        } catch (error) {
            event.ports?.[0]?.postMessage({ error: error.message });
        }
    }));
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);
    if (url.origin !== ORIGIN) return;
    if (url.pathname === '/logout' && event.request.method === 'POST') {
        event.respondWith(exclusive(async () => { await purgePrivate(); return fetch(event.request); }));
        return;
    }
    if (event.request.method !== 'GET') return;
    if (event.request.mode === 'navigate') {
        event.respondWith((async () => {
            try {
                const response = await fetch(event.request);
                if ([401, 419].includes(response.status) || (response.redirected && new URL(response.url).pathname !== '/pos')) await exclusive(purgePrivate);
                return response;
            } catch {
                return offlineNavigation(event);
            }
        })());
    } else if (publicAsset(url.href)) {
        event.respondWith((async () => await (await caches.open(PUBLIC_CACHE)).match(event.request) || fetch(event.request))());
    }
});
