// The member area's service worker (registered by resources/js/Support/webPush.js, not in the iOS or Android app).
// 1. Notifications from the website: shows them, puts the unread count on the home screen icon, opens the link.
// 2. Without signal: keeps the last copy of every member page and directory page opened, and saves the main ones ahead
//    (presave, asked for by the app: the tabs, the member's lodges, upcoming summonses and tickets), so the app can be
//    used in a hall with no reception. A page shown from a saved copy says so. Signing out, or anyone signing in,
//    deletes every copy.

const PAGES = 'cm-pages-v2';
const ASSETS = 'cm-assets-v1';
// The "You're offline" screen listing the saved pages; kept apart from PAGES, which signing out wipes.
const SHELL = 'cm-shell-v1';
const OFFLINE = '/offline.html';
// Kept: the member area and the lodge directory. Not downloads (PDFs, spreadsheets, calendar files).
const KEPT = [/^\/members(\/|$)/, /^\/lodges(\/|$)/];
const NOT_KEPT = /\.(pdf|csv|ics|xlsx|zip)$|\/(download|export|pdf|feed|ics)(\/|$)/;
const isKept = (pathname) => KEPT.some((pattern) => pattern.test(pathname)) && !NOT_KEPT.test(pathname);
// On a weak signal the saved copy is shown after this long instead of a page that never arrives.
const WEAK_SIGNAL_MS = 4000;

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL).then((cache) => cache.add(OFFLINE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((names) => Promise.all(names.filter((name) => ![PAGES, ASSETS, SHELL].includes(name)).map((name) => caches.delete(name))))
        .then(() => self.clients.claim()));
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch { data = { body: event.data?.text() }; }

    const shown = self.registration.showNotification(data.title || 'Club Manager', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: { url: data.url || '/members/notifications' },
    });
    const badge = !self.navigator.setAppBadge ? Promise.resolve()
        : (data.badge ? self.navigator.setAppBadge(data.badge) : self.navigator.clearAppBadge()).catch(() => {});

    event.waitUntil(Promise.all([shown, badge]));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/members/notifications', self.location.origin);
    if (url.origin !== self.location.origin) {
        return;
    }

    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const open = windows.find((client) => client.url.startsWith(self.location.origin));
        // A window this worker does not control (after a hard reload) cannot be sent somewhere: open a new one.
        return open
            ? open.navigate(url.href).then((client) => (client || open).focus()).catch(() => self.clients.openWindow(url.href))
            : self.clients.openWindow(url.href);
    }));
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    // Signing out, or anyone signing in (perhaps someone else, on a shared or handed-on phone), wipes the kept pages.
    if (request.method === 'POST' && ['/logout', '/login', '/login/device', '/passkeys/login', '/two-factor-challenge'].includes(url.pathname)) {
        event.waitUntil(caches.delete(PAGES));
        return;
    }

    if (request.method !== 'GET') {
        return;
    }

    // Built files have their contents' hash in the name, so a kept copy never goes stale. The route table's address
    // carries a hash of the routes the same way.
    if (url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/ziggy/')) {
        event.respondWith(caches.open(ASSETS).then((cache) => cache.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        }))));
        return;
    }

    const inertia = request.headers.get('X-Inertia') === 'true';
    const partial = request.headers.has('X-Inertia-Partial-Data') || request.headers.has('X-Inertia-Partial-Component');
    const kept = isKept(url.pathname);

    // Any other page opened without signal shows the saved pages instead of the browser's error.
    if (request.mode === 'navigate' && !kept) {
        event.respondWith(fetch(request).catch(() => offlinePage(url)));
        return;
    }

    if (partial || !(request.mode === 'navigate' || inertia) || !kept) {
        return;
    }

    // The page as a full load and as an Inertia visit are different answers to the same address: keep them apart
    // (with a query parameter: the cache ignores anything after #).
    const key = inertia ? dataKey(url) : url.origin + url.pathname + url.search;
    event.respondWith(pageOrSavedCopy(event, request, url, key, inertia));
});

/**
 * The page from the network, keeping a copy; without signal (or after WEAK_SIGNAL_MS on a weak one) the saved copy,
 * marked with when it was saved. The network answer still updates the copy for next time.
 */
async function pageOrSavedCopy(event, request, url, key, inertia) {
    const network = fetch(request).then((response) => {
        if (response.ok && !response.redirected) {
            event.waitUntil(caches.open(PAGES).then((cache) => cache.put(key, stamped(response.clone()))));
        }
        return response;
    });
    event.waitUntil(network.catch(() => {}));

    const slow = new Promise((resolve) => setTimeout(() => resolve('slow'), WEAK_SIGNAL_MS));
    const first = await Promise.race([network.catch(() => 'failed'), slow]);
    if (first instanceof Response) {
        if (inertia) tell(event.clientId, { type: 'fresh', url: url.pathname + url.search });
        return first;
    }

    const saved = inertia ? await savedData(event, url, key) : await offlinePage(url, true);
    if (saved) return saved;
    if (first === 'slow') {
        try {
            return await network;
        } catch {
            // No saved copy and no signal after all.
        }
    }
    return inertia ? Response.error() : offlinePage(url);
}

/** A copy to keep, with the time it was saved (shown as "Saved copy from ..."). */
function stamped(response) {
    const headers = new Headers(response.headers);
    headers.set('X-Saved-At', new Date().toISOString());
    return new Response(response.body, { status: response.status, statusText: response.statusText, headers });
}

async function savedData(event, url, key) {
    const hit = await (await caches.open(PAGES)).match(key);
    if (hit) tell(event.clientId, { type: 'saved-copy', url: url.pathname + url.search, savedAt: hit.headers.get('X-Saved-At') });
    return hit;
}

function tell(clientId, message) {
    if (clientId) self.clients.get(clientId).then((client) => client?.postMessage(message)).catch(() => {});
}

// The app asks for the main pages to be saved ahead while there is signal (resources/js/Support/offlinePages.js).
self.addEventListener('message', (event) => {
    if (event.data?.type === 'presave' && Array.isArray(event.data.urls)) {
        // The app records the save only once this answers, so it asks again if the save didn't finish.
        event.waitUntil(presave(event.data.urls, String(event.data.version ?? '')).then((finished) => event.ports[0]?.postMessage(finished ? 'saved' : 'stopped')));
    }
});

async function presave(urls, version) {
    const cache = await caches.open(PAGES);
    const wanted = [...urls];
    const done = new Set();

    while (wanted.length && done.size < 40) {
        const url = new URL(wanted.shift(), self.location.origin);
        const address = url.pathname + url.search;
        if (url.origin !== self.location.origin || done.has(address) || !isKept(url.pathname)) continue;
        done.add(address);

        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version, 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html, application/xhtml+xml' } });
            if (!response.ok || response.redirected || response.headers.get('X-Inertia') !== 'true') continue;
            const page = await response.clone().json();
            await cache.put(dataKey(url), stamped(response));
            // The summonses and tickets the dashboard lists are saved too.
            (page.props?.upNext ?? []).forEach((item) => item?.url && wanted.push(item.url));
        } catch {
            return false; // Signal gone: try again next time.
        }
    }

    return true;
}

const DATA_PARAM = '__inertia';
function dataKey(url) {
    const key = new URL(url.origin + url.pathname + url.search);
    key.searchParams.set(DATA_PARAM, '1');
    return key.href;
}

/**
 * Without signal: the saved copy of the page; or, for a page only saved as its page data (opened by tapping through the
 * app, or saved ahead), a saved full page with that data swapped in, so the app draws it; otherwise the list of saved
 * pages (or nothing, with savedOnly). The page carries when its copy was saved, for the "Saved copy" notice.
 */
async function offlinePage(url, savedOnly = false) {
    const cache = await caches.open(PAGES);
    const address = url.origin + url.pathname + url.search;
    const full = await cache.match(address);
    if (full) return withSavedAt(await full.text(), full.headers.get('X-Saved-At'));

    const data = await cache.match(dataKey(url));
    const shell = data && (await cache.keys()).find((request) => !new URL(request.url).searchParams.has(DATA_PARAM));
    if (data && shell) {
        const json = (await data.text()).replace(/<\//g, '<\\/');
        const html = (await (await cache.match(shell)).text())
            .replace(/(<script data-page="app" type="application\/json">)[\s\S]*?(<\/script>)/, (match, open, close) => open + json + close);
        return withSavedAt(html, data.headers.get('X-Saved-At'));
    }

    if (savedOnly) return null;
    return (await caches.match(OFFLINE)) || Response.error();
}

function withSavedAt(html, savedAt) {
    const marked = html.replace('</head>', `<meta name="cm-saved-at" content="${String(savedAt ?? 'unknown').replace(/[^0-9TZ:.\-a-z]/g, '')}"></head>`);
    return new Response(marked, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
}
