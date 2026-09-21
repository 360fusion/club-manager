import { onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const read = (key) => {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
};

const write = (key, value) => {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Storage can be full or blocked (private windows); the form still works, it just isn't kept.
    }
};

const remove = (key) => {
    try {
        window.localStorage.removeItem(key);
    } catch {
        // Nothing to clean up.
    }
};

/**
 * Keeps what someone is typing in the browser so a page change, refresh or crash does not lose it, restores it
 * when they come back, and asks before they leave with unsaved changes.
 *
 * `snapshot` returns everything the form holds as plain data; `restore` puts a saved snapshot back. `version`
 * identifies what the draft was based on (for example the event's last-saved time), so a draft that is older
 * than the saved record is ignored rather than overwriting it.
 */
export function useDraft({ key, version, snapshot, restore, leaveMessage = 'You have unsaved changes. Leave without saving? They are kept as a draft on this device.' }) {
    const restored = ref(false);
    const initial = JSON.stringify(snapshot());
    let finished = false;
    let timer = null;

    const isDirty = () => JSON.stringify(snapshot()) !== initial;

    const saved = read(key);

    if (saved && saved.version === version && saved.data && JSON.stringify(saved.data) !== initial) {
        restore(saved.data);
        restored.value = true;
    }

    watch(() => JSON.stringify(snapshot()), () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (finished) return;
            if (isDirty()) write(key, { version, at: Date.now(), data: snapshot() });
            else remove(key);
        }, 400);
    });

    const offBefore = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (finished || visit.method !== 'get' || !isDirty()) return;

        if (!window.confirm(leaveMessage)) event.preventDefault();
    });

    const onUnload = (event) => {
        if (!finished && isDirty()) {
            event.preventDefault();
            event.returnValue = '';
        }
    };
    window.addEventListener('beforeunload', onUnload);

    onBeforeUnmount(() => {
        clearTimeout(timer);
        offBefore();
        window.removeEventListener('beforeunload', onUnload);
    });

    /** Call once the form has been saved: forget the draft and stop warning. */
    const clear = () => {
        finished = true;
        clearTimeout(timer);
        remove(key);
    };

    /** Throw the draft away and reload the page as it was last saved. */
    const discard = () => {
        clear();
        window.location.reload();
    };

    return { restored, clear, discard, isDirty };
}
