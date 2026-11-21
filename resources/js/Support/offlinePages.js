// Using the app without signal (with public/sw.js): the main pages are saved ahead while there is signal, and a page
// shown from a saved copy is marked with when it was saved.
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const PRESAVE_EVERY_MS = 30 * 60 * 1000;
const PRESAVED_KEY = 'cm-presaved-at';

/** The page now on screen came from a saved copy: when it was saved (an ISO time, or 'unknown'), else null. */
export const savedCopy = reactive({ savedAt: null });

const current = () => window.location.pathname + window.location.search;
// The last word from the service worker about each address: the saved copy it served, or that the page was fresh.
const served = new Map();

/** Called once at start-up for a signed-in member using the website (the iOS and Android app has no service worker). */
export function startOfflinePages(initialPage) {
    if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return;

    // A full page opened without signal carries the time of its copy (the service worker adds it).
    savedCopy.savedAt = document.querySelector('meta[name="cm-saved-at"]')?.content ?? null;

    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'saved-copy') served.set(event.data.url, event.data.savedAt || 'unknown');
        if (event.data?.type === 'fresh') served.delete(event.data.url);
        if (event.data?.url === current()) savedCopy.savedAt = served.get(current()) ?? null;
    });
    // Inertia reports the first page as a navigation too: that one keeps what its own copy said.
    let first = true;
    router.on('navigate', () => {
        if (first) {
            first = false;
            return;
        }
        savedCopy.savedAt = served.get(current()) ?? null;
    });

    const presave = () => presaveMainPages(initialPage);
    (window.requestIdleCallback ?? ((callback) => setTimeout(callback, 3000)))(presave);
    window.addEventListener('online', presave);
}

/**
 * While there is signal, at most every half hour: the tabs, each of the member's lodges, and (found by the service
 * worker on the dashboard) their upcoming summonses and tickets.
 */
async function presaveMainPages(initialPage) {
    if (navigator.onLine === false) return;
    try {
        if (Date.now() - Number(localStorage.getItem(PRESAVED_KEY) ?? 0) < PRESAVE_EVERY_MS) return;
    } catch {
        return;
    }

    const registration = await navigator.serviceWorker.ready;
    const clubs = (initialPage.props.auth?.clubs ?? []).filter((club) => club.status === 'active');
    const urls = ['/members/dashboard', '/members/calendar', '/members/events', '/members/notifications', '/members/lodges', ...clubs.map((club) => `/members/${club.slug}`)];

    const channel = new MessageChannel();
    channel.port1.onmessage = (event) => {
        if (event.data !== 'saved') return;
        try {
            localStorage.setItem(PRESAVED_KEY, String(Date.now()));
        } catch {
            // Private browsing: saved again next time.
        }
    };
    registration.active?.postMessage({ type: 'presave', urls, version: initialPage.version }, [channel.port2]);
}
