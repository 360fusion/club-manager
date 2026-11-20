// The member area's service worker (registered by resources/js/Support/webPush.js, not in the iOS or Android app).
// 1. Notifications from the website: shows them, puts the unread count on the home screen icon, opens the link.
// 2. Without signal: keeps the last copy of each dinner ticket and summons a member opened, and the dashboard, so they
//    open at the door of a hall with no reception. Signing out, or anyone signing in, deletes every copy.

const PAGES = 'cm-pages-v1';
const ASSETS = 'cm-assets-v1';
// Only these member pages are kept: a ticket, a summons, and the dashboard the home screen icon opens.
const KEPT = [/^\/members\/[^/]+\/events\/\d+\/ticket$/, /^\/members\/[^/]+\/meetings\/\d+\/summons$/, /^\/members\/dashboard$/];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((names) => Promise.all(names.filter((name) => ![PAGES, ASSETS].includes(name)).map((name) => caches.delete(name))))
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

    // Built files have their contents' hash in the name, so a kept copy never goes stale.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(caches.open(ASSETS).then((cache) => cache.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        }))));
        return;
    }

    const inertia = request.headers.get('X-Inertia') === 'true';
    const partial = request.headers.has('X-Inertia-Partial-Data') || request.headers.has('X-Inertia-Partial-Component');
    if (partial || !(request.mode === 'navigate' || inertia) || !KEPT.some((pattern) => pattern.test(url.pathname))) {
        return;
    }

    // The page as a full load and as an Inertia visit are different answers to the same address: keep them apart.
    const key = url.origin + url.pathname + url.search + (inertia ? '#inertia' : '');
    event.respondWith(fetch(request).then((response) => {
        if (response.ok && !response.redirected) {
            const copy = response.clone();
            caches.open(PAGES).then((cache) => cache.put(key, copy));
        }
        return response;
    }).catch(() => caches.open(PAGES).then((cache) => cache.match(key)).then((hit) => hit || Response.error())));
});
