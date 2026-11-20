import { router } from '@inertiajs/vue3';

// The iOS and Android app (mobile/, Capacitor) is this member area in a web view; the native side injects window.Capacitor.
export const isNativeApp = typeof window !== 'undefined' && Boolean(window.Capacitor?.isNativePlatform?.());
export const isAndroidApp = isNativeApp && window.Capacitor.getPlatform() === 'android';

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

// The optional app lock: Face ID, a fingerprint or the phone's PIN (the AppLock plugin in mobile/ios and mobile/android).
// It is a screen over the app, kept on this phone only; the sign-in itself is the site's as usual.
const LOCK_SETTING = 'cm-app-lock';
const LOCK_AFTER_MS = 60_000;

let appLockPlugin = null;

// Handed back inside an object: Capacitor's plugin proxy answers every property, `then` too, so awaiting the plugin
// itself would wait forever.
async function lockPlugin() {
    if (!appLockPlugin) {
        const { registerPlugin } = await import('@capacitor/core');
        appLockPlugin = registerPlugin('AppLock');
    }
    return { AppLock: appLockPlugin };
}

export function appLockOn() {
    try {
        return localStorage.getItem(LOCK_SETTING) === '1';
    } catch {
        return false;
    }
}

/** Whether this phone can lock the app, and with what ("face", "fingerprint" or "passcode"). */
export async function appLockSupport() {
    if (!isNativeApp) return { available: false };
    return (await lockPlugin()).AppLock.isAvailable().catch(() => ({ available: false }));
}

/** Turning it on asks once, so nobody locks themselves out with a face or finger the phone doesn't know. */
export async function setAppLock(on) {
    if (on) await (await lockPlugin()).AppLock.authenticate({ reason: 'Turn on the app lock' });
    try {
        on ? localStorage.setItem(LOCK_SETTING, '1') : localStorage.removeItem(LOCK_SETTING);
    } catch {
        // Without storage the lock simply stays off.
    }
}

// Face ID / fingerprint sign-in (DeviceSignInController). The server hands this phone a long key once; the AppLock plugin
// keeps it in the Keychain or Keystore, readable only after Face ID or a strong fingerprint. Who it signs in is kept here
// too (not secret), so the sign-in button can say whose it is.
const SIGN_IN_OWNER = 'cm-sign-in-owner';
const SIGN_IN_ASKED = 'cm-sign-in-asked';

const readJson = (name) => {
    try {
        return JSON.parse(localStorage.getItem(name) || 'null');
    } catch {
        return null;
    }
};
const writeJson = (name, value) => {
    try {
        value === null ? localStorage.removeItem(name) : localStorage.setItem(name, JSON.stringify(value));
    } catch {
        // Without storage the button just doesn't name anyone.
    }
};

/** What this phone offers: { available, kind: 'face'|'fingerprint', owner: {id, name}|null } (owner set when a key is stored). */
export async function biometricSignIn() {
    if (!isNativeApp) return { available: false, owner: null };
    const { AppLock } = await lockPlugin();
    const support = await AppLock.signInAvailable().catch(() => ({ available: false }));
    const { exists } = await AppLock.hasSignInKey().catch(() => ({ exists: false }));
    if (!exists && readJson(SIGN_IN_OWNER)) writeJson(SIGN_IN_OWNER, null);
    return { ...support, owner: exists ? readJson(SIGN_IN_OWNER) : null };
}

/** Whether to offer it to this person right after they signed in with their password (asked once per person). */
export async function shouldOfferBiometricSignIn(user) {
    if (!isNativeApp || !user || !/\/(login|two-factor-challenge)$/.test(document.referrer.split('?')[0])) return false;
    if ((readJson(SIGN_IN_ASKED) ?? []).includes(user.id)) return false;
    const state = await biometricSignIn();
    return state.available && state.owner?.id !== user.id;
}

export function declineBiometricSignIn(user) {
    writeJson(SIGN_IN_ASKED, [...new Set([...(readJson(SIGN_IN_ASKED) ?? []), user.id])]);
}

/**
 * Asks the server for this phone's key and stores it behind Face ID or a fingerprint. Throws { password: '...' } when
 * the server wants the password again (more than a few minutes after signing in).
 */
export async function turnOnBiometricSignIn(user, password = null) {
    const response = await fetch(route('members.sign_in_keys.store'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
        body: JSON.stringify({ platform: window.Capacitor.getPlatform(), name: window.Capacitor.getPlatform() === 'ios' ? 'iPhone' : 'Android phone', password }),
    });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw body.errors ?? { key: 'It could not be turned on.' };

    try {
        await (await lockPlugin()).AppLock.saveSignInKey({ key: body.key, reason: 'Turn on sign-in with your face or fingerprint' });
    } catch {
        await forgetServerKey(body.id);
        throw { key: 'It was not turned on.' };
    }

    const previous = readJson(SIGN_IN_OWNER);
    if (previous?.keyId && previous.id === user.id) await forgetServerKey(previous.keyId);
    writeJson(SIGN_IN_OWNER, { id: user.id, name: user.name, keyId: body.id });
    declineBiometricSignIn(user);
}

async function forgetServerKey(id) {
    await fetch(route('members.sign_in_keys.destroy', id), {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrfToken() },
    }).catch(() => {});
}

/** Turns it off on this phone, and on the server when the person who owns the key is signed in. */
export async function turnOffBiometricSignIn(user) {
    const owner = readJson(SIGN_IN_OWNER);
    if (owner?.keyId && owner.id === user?.id) await forgetServerKey(owner.keyId);
    await (await lockPlugin()).AppLock.deleteSignInKey().catch(() => {});
    writeJson(SIGN_IN_OWNER, null);
}

/**
 * On the sign-in page: Face ID or a fingerprint unlocks the key and signs in. A key the server no longer accepts is
 * dropped, so the person signs in with their password and can turn it on again.
 */
export async function signInWithBiometrics({ onFinish } = {}) {
    let key;
    try {
        ({ key } = await (await lockPlugin()).AppLock.readSignInKey({ reason: 'Sign in to Club Manager' }));
    } catch (error) {
        if (error?.code === 'NOT_FOUND') writeJson(SIGN_IN_OWNER, null);
        onFinish?.();
        return;
    }
    router.post(route('login.device'), { key }, {
        onError: async (errors) => {
            if (errors.device_key) await turnOffBiometricSignIn(null);
        },
        onFinish,
    });
}

function cover() {
    let el = document.getElementById('app-lock');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'app-lock';
    el.className = 'cm-nav fixed inset-0 z-[300] flex flex-col items-center justify-center gap-6 bg-slate-800 text-white';
    el.innerHTML = '<div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-blue-600 text-2xl font-black" aria-hidden="true">CM</div>'
        + '<button type="button" class="min-h-11 rounded-xl bg-white px-6 text-sm font-semibold text-slate-900">Unlock Club Manager</button>';
    el.querySelector('button').addEventListener('click', unlock);
    document.body.appendChild(el);
    return el;
}

// Only a passed check unlocks. Showing the Face ID prompt itself sends the app inactive and back, so the
// "back within a minute" rule below must never lift a lock that is waiting on it.
let locked = false;

function lock() {
    locked = true;
    cover();
    unlock();
}

async function unlock() {
    try {
        await (await lockPlugin()).AppLock.authenticate({ reason: 'Unlock Club Manager' });
        locked = false;
        document.getElementById('app-lock')?.remove();
    } catch {
        // Still locked: the button asks again.
    }
}

function startAppLock(App) {
    let leftAt = null;

    // Arriving straight from the sign-in form, the password was just typed: that counts as unlocking.
    const justSignedIn = /\/(login|two-factor-challenge)$/.test(document.referrer.split('?')[0]);
    if (appLockOn() && !justSignedIn) lock();

    App.addListener('appStateChange', ({ isActive }) => {
        if (!appLockOn() || locked) return;
        if (!isActive) {
            // Covered straight away, so the app switcher's snapshot shows nothing private.
            leftAt = Date.now();
            cover();
            return;
        }
        if (leftAt && Date.now() - leftAt > LOCK_AFTER_MS) {
            lock();
        } else {
            document.getElementById('app-lock')?.remove();
        }
    });
}

let started = false;

// Called with the first page the app loads; push is only set up once someone is signed in.
// shortcut: on Android, push needs google-services.json in the app; the server's androidPush flag stands in for it, so ship both together.
export async function startNativeApp(firstPage) {
    if (!isNativeApp || started) return;
    started = true;
    document.documentElement.classList.add('native-app');

    // The clock and battery sit on the navy strip, so they are drawn light (iOS does this itself).
    if (isAndroidApp) {
        import('@capacitor/core').then(({ SystemBars }) => SystemBars.setStyle({ style: 'DARK', bar: 'StatusBar' })).catch(() => {});
    }

    const [{ App }, { PushNotifications }] = await Promise.all([import('@capacitor/app'), import('@capacitor/push-notifications')]);

    App.addListener('appUrlOpen', ({ url }) => {
        const path = pathFromDeepLink(url);
        if (path) router.visit(path);
    });

    PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
        const path = safePath(notification.data?.url);
        if (path) router.visit(path);
    });

    const props = firstPage?.props ?? {};
    if (props.auth?.user) startAppLock(App);
    if (!props.auth?.user || (isAndroidApp && !props.androidPush)) return;

    PushNotifications.addListener('registration', ({ value }) => saveToken(value));

    // The server names this channel on every Android push (PushSender::ANDROID_CHANNEL); creating it again only renames it.
    if (isAndroidApp) {
        await PushNotifications.createChannel({ id: 'club_notices', name: 'Club notices', description: 'Meetings, events, payments and messages from your clubs', importance: 4 }).catch(() => {});
    }

    let { receive } = await PushNotifications.checkPermissions();
    if (receive === 'prompt' || receive === 'prompt-with-rationale') {
        ({ receive } = await PushNotifications.requestPermissions());
    }
    if (receive === 'granted') {
        await PushNotifications.register();

        // Each push sets the icon badge to the unread count; once the inbox is all read, clear it (and the stale alerts).
        const clearWhenRead = (page) => {
            if (page?.props?.bell?.unread === 0) PushNotifications.removeAllDeliveredNotifications().catch(() => {});
        };
        clearWhenRead(firstPage);
        router.on('navigate', (event) => clearWhenRead(event.detail.page));
    }
}
