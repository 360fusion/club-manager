import { ref } from 'vue';

const STORAGE_KEY = 'members-nav';

const read = () => {
    try {
        return localStorage.getItem(STORAGE_KEY) === 'side' ? 'side' : 'top';
    } catch (e) {
        return 'top';
    }
};

/**
 * Whether the members area shows its navigation along the top or down the side.
 * Shared and remembered in the browser.
 */
export const navMode = ref(read());

export function setNavMode(mode) {
    navMode.value = mode === 'side' ? 'side' : 'top';

    try {
        localStorage.setItem(STORAGE_KEY, navMode.value);
    } catch (e) {
        // Storage is unavailable (private browsing); the choice still applies for this page.
    }
}
