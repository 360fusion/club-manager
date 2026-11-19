import { router } from '@inertiajs/vue3';

// The iOS and Android app (mobile/, Capacitor) is this member area in a web view; the native side injects window.Capacitor.
export const isNativeApp = typeof window !== 'undefined' && Boolean(window.Capacitor?.isNativePlatform?.());

// A path on this site only: never another host or a protocol-relative "//host".
export function safePath(path) {
    return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !path.includes('\\') ? path : null;
}

// clubmanager://open?path=/members/... is how the payment return page hands the payer back to the app.
export function pathFromDeepLink(url) {
    try {
        return safePath(new URL(url).searchParams.get('path'));
    } catch {
        return null;
    }
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function saveToken(token) {
    await fetch(route('members.push_devices.store'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ token, platform: window.Capacitor.getPlatform() }),
    });
}

let started = false;

// Called on every page load in the app; push is only set up once someone is signed in.
// shortcut: on Android, push needs google-services.json in the app; the server's androidPush flag stands in for it, so ship both together.
export async function startNativeApp(signedIn, androidPush) {
    if (!isNativeApp || started) return;
    started = true;
    document.documentElement.classList.add('native-app');

    const [{ App }, { PushNotifications }] = await Promise.all([import('@capacitor/app'), import('@capacitor/push-notifications')]);

    App.addListener('appUrlOpen', ({ url }) => {
        const path = pathFromDeepLink(url);
        if (path) router.visit(path);
    });

    PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
        const path = safePath(notification.data?.url);
        if (path) router.visit(path);
    });

    if (!signedIn || (window.Capacitor.getPlatform() === 'android' && !androidPush)) return;

    PushNotifications.addListener('registration', ({ value }) => saveToken(value));

    let { receive } = await PushNotifications.checkPermissions();
    if (receive === 'prompt' || receive === 'prompt-with-rationale') {
        ({ receive } = await PushNotifications.requestPermissions());
    }
    if (receive === 'granted') {
        await PushNotifications.register();
    }
}
