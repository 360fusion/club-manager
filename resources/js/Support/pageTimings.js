// Measures how long pages take on this member's device and sends them in small batches for the superadmin's page
// speed report (SuperAdminPageSpeedController). Only the page's name, how it was opened and the times are sent.
import { router } from '@inertiajs/vue3';

const queue = [];
const device = () => (window.matchMedia('(max-width: 767px)').matches ? 'phone' : 'desktop');
const installed = () => window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
const xsrfToken = () => decodeURIComponent(document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

function record(timing) {
    queue.push({ ...timing, ms: Math.max(0, Math.round(timing.ms)), device: device(), installed: installed() });
    if (queue.length >= 10) send();
}

function send() {
    if (!queue.length || navigator.onLine === false) return;
    const timings = queue.splice(0, 20);
    fetch('/members/page-timings', {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ timings }),
    }).catch(() => {});
}

/** Called once at start-up for a signed-in member (not on a static website, which has no server to send to). */
export function startPageTimings(initialPage) {
    if (!initialPage?.props?.auth?.user || initialPage.props.staticSite || typeof PerformanceObserver === 'undefined') return;

    // Opened fresh: when the largest thing on the page was drawn (else when the page was ready), and the server's share.
    let largest = null;
    try {
        new PerformanceObserver((list) => { largest = list.getEntries().at(-1)?.startTime ?? largest; }).observe({ type: 'largest-contentful-paint', buffered: true });
    } catch {
        // No LCP in this browser: the page-ready time is used.
    }
    const recordLoad = () => setTimeout(() => {
        const navigation = performance.getEntriesByType('navigation')[0];
        if (!navigation) return;
        record({ page: initialPage.component, kind: 'load', ms: largest ?? navigation.domContentLoadedEventEnd, server_ms: Math.round(navigation.responseStart - navigation.requestStart), cached: false });
    }, 1000);
    // The app usually starts after the page has finished loading (it waits for the page's code).
    if (document.readyState === 'complete') recordLoad();
    else window.addEventListener('load', recordLoad, { once: true });

    // A tap on a link inside the app: from the tap until the new page is drawn. A page taken from a copy fetched ahead
    // of the tap makes no request and fires no "start", so the tap itself is the starting point.
    let tapped = null;
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('a[href]');
        if (link && link.origin === window.location.origin && !link.target && !event.defaultPrevented) {
            tapped = { at: performance.now(), path: link.pathname + link.search };
        }
    }, true);
    router.on('navigate', (event) => {
        // The page's own address: during a page transition the browser's address changes a moment later.
        if (!tapped || event.detail.page.url !== tapped.path) return;
        const { at, path } = tapped;
        tapped = null;
        const page = event.detail.page.component;
        let recorded = false;
        const measure = () => {
            if (recorded) return;
            recorded = true;
            const request = performance.getEntriesByType('resource')
                .filter((entry) => ['xmlhttprequest', 'fetch'].includes(entry.initiatorType) && entry.startTime >= at - 5 && new URL(entry.name).pathname + new URL(entry.name).search === path)
                .at(-1);
            record({ page, kind: 'visit', ms: performance.now() - at, server_ms: request ? Math.round(request.responseStart - request.requestStart) : null, cached: !request });
        };
        // Once the new page is drawn; a page put away mid-tap draws nothing, so it is measured shortly anyway.
        requestAnimationFrame(() => requestAnimationFrame(measure));
        setTimeout(measure, 100);
    });

    // Sent when the app is put away or left, and every half minute in case neither is reported (phones often don't).
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') send();
    });
    window.addEventListener('pagehide', send);
    setInterval(send, 30000);
}
