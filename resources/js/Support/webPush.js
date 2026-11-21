// The member area as a web app on a phone or computer (not in the iOS or Android app, which has its own pushes):
// the service worker in public/sw.js, notifications from the website, and the unread count on the home screen icon.
import { router } from '@inertiajs/vue3';
import { isNativeApp } from '@/Support/nativeApp';
import { startOfflinePages } from '@/Support/offlinePages';

const canUseWorker = () => !isNativeApp && typeof navigator !== 'undefined' && 'serviceWorker' in navigator;

/** Opened from the home screen rather than in a browser tab. */
export const isHomeScreenApp = () => typeof window !== 'undefined'
    && (window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true);

/** An iPhone or iPad only offers notifications to a site added to the home screen. */
export const needsHomeScreenFirst = () => typeof navigator !== 'undefined' && /iPhone|iPad|iPod/.test(navigator.userAgent) && !isHomeScreenApp();

function setBadge(count) {
    if (!('setAppBadge' in navigator)) return;
    (count ? navigator.setAppBadge(count) : navigator.clearAppBadge()).catch(() => {});
}

// Chrome on Android offers to install the site once; the event is kept so the Add to Home Screen tip can ask then.
let installPrompt = null;
export const canPromptInstall = () => installPrompt !== null;
export async function promptInstall() {
    const prompt = installPrompt;
    installPrompt = null;
    if (!prompt) return false;
    prompt.prompt();
    return (await prompt.userChoice).outcome === 'accepted';
}

/** Called once at start-up: a signed-in member gets the service worker, and the icon badge follows the inbox. */
export function startWebApp(initialPage) {
    if (!canUseWorker() || !initialPage?.props?.auth?.user || initialPage.props.staticSite) return;

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt = event;
    });

    navigator.serviceWorker.register('/sw.js').then(() => reclaimSubscription(initialPage.props.webPushKey)).catch(() => {});
    startOfflinePages(initialPage);
    setBadge(initialPage.props.bell?.unread ?? 0);
    router.on('navigate', (event) => setBadge(event.detail.page.props.bell?.unread ?? 0));
}

const keyBytes = (key) => {
    const base64 = key.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(key.length / 4) * 4, '=');
    return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
};

/**
 * A browser that already said yes is saved again for whoever is signed in now: after signing out and back in it would
 * otherwise get nothing, and on a shared browser the last person would keep getting the new person's notifications.
 */
async function reclaimSubscription(publicKey) {
    if (!webPushSupported(publicKey) || Notification.permission !== 'granted') return;
    const subscription = await currentSubscription();
    if (subscription) await send('POST', { platform: 'web', subscription: subscription.toJSON() });
}

export const webPushSupported = (publicKey) => Boolean(publicKey) && canUseWorker() && 'PushManager' in window && 'Notification' in window;

export async function currentSubscription() {
    const registration = await navigator.serviceWorker.getRegistration('/');
    return registration ? registration.pushManager.getSubscription() : null;
}

async function send(method, body) {
    const response = await fetch('/members/push-devices', {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });
    if (!response.ok) throw new Error('This device could not be saved. Try again.');
}

/** Asks the browser for permission and saves this device. Returns false when the member said no. */
export async function turnOnWebPush(publicKey) {
    if ((await Notification.requestPermission()) !== 'granted') return false;

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;
    const subscription = (await registration.pushManager.getSubscription())
        ?? await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: keyBytes(publicKey) });

    await send('POST', { platform: 'web', subscription: subscription.toJSON() });
    return true;
}

export async function turnOffWebPush() {
    const subscription = await currentSubscription();
    if (!subscription) return;

    await send('DELETE', { endpoint: subscription.endpoint });
    await subscription.unsubscribe();
}
