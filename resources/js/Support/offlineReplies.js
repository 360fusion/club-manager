// Replying to a summons without signal (a hall with no reception): the reply is kept on this phone, shown as the
// member's answer marked "waiting for signal", and sent when the signal is back (or the app is next opened). Only the
// signed-in member's own replies are sent; anything left by someone else on a shared phone is dropped.
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const KEY = 'cm-queued-replies';

/** Replies waiting to be sent, by the address they go to: { status, userId, at }. */
export const queuedReplies = reactive(load());

function load() {
    try {
        return JSON.parse(localStorage.getItem(KEY) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

function save() {
    try {
        localStorage.setItem(KEY, JSON.stringify(queuedReplies));
    } catch {
        // Private browsing: kept for this visit only.
    }
}

export const isOffline = () => typeof navigator !== 'undefined' && navigator.onLine === false;

export function queueReply(url, status, userId) {
    queuedReplies[url] = { status, userId, at: Date.now() };
    save();
}

/** The answer waiting to be sent to this address, if there is one. */
export const queuedStatus = (url) => queuedReplies[url]?.status ?? null;

// Laravel's own cookie copy of the form token stays current, unlike the one in the page from when it was opened.
const xsrfToken = () => decodeURIComponent(document.cookie.split('; ').find((cookie) => cookie.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

let sending = false;

/** Sends what is waiting for this member; stops at the first one that can't get through (still no signal). */
export async function sendQueuedReplies(userId) {
    if (sending || isOffline() || !Object.keys(queuedReplies).length) return;
    sending = true;
    let sent = 0;

    try {
        for (const [url, reply] of Object.entries({ ...queuedReplies })) {
            if (reply.userId !== userId) {
                delete queuedReplies[url];
                continue;
            }

            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'text/html, application/xhtml+xml', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
                body: JSON.stringify({ attendance_status: reply.status }),
            });
            // A refusal (the meeting closed, dining no longer open) is not retried: the page shows the real answer.
            if (response.status !== 419) {
                delete queuedReplies[url];
                sent++;
            }
        }
    } catch {
        // Still no signal: tried again later.
    } finally {
        save();
        sending = false;
    }

    if (sent) router.reload({ preserveScroll: true });
}

/** Called once at start-up for a signed-in member: send anything waiting now and whenever the signal comes back. */
export function startOfflineReplies(initialPage) {
    const userId = initialPage?.props?.auth?.user?.id;
    if (!userId || initialPage.props.staticSite) return;

    sendQueuedReplies(userId);
    window.addEventListener('online', () => sendQueuedReplies(userId));
}
