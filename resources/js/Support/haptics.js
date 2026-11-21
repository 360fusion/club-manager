// A light buzz under the finger in the iOS and Android app (Capacitor's Haptics plugin): on a tab tap, and when a reply
// is saved. Nothing happens on the website, where phones would only offer a crude vibration.
import { isNativeApp } from '@/Support/nativeApp';

let plugin = null;
const haptics = async () => {
    plugin ??= import('@capacitor/haptics');
    return plugin;
};

/** A light tap, for switching tabs. */
export function tapFeedback() {
    if (!isNativeApp) return;
    haptics().then(({ Haptics, ImpactStyle }) => Haptics.impact({ style: ImpactStyle.Light })).catch(() => {});
}

/** A success buzz, for an answer that has been saved. */
export function savedFeedback() {
    if (!isNativeApp) return;
    haptics().then(({ Haptics, NotificationType }) => Haptics.notification({ type: NotificationType.Success })).catch(() => {});
}
