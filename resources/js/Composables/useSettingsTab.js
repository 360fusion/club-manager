import { onMounted, onUnmounted, ref, watch } from 'vue';

/**
 * The open section of the club settings page. It opens from the URL (`#tab`, else `?tab=`, else General),
 * follows the hash when it changes, and writes the open section back into the hash.
 */
export function useSettingsTab(validTabs) {
    const getTabFromUrl = () => {
        const hash = typeof window !== 'undefined' ? window.location.hash.replace('#', '').trim() : '';
        if (hash && validTabs.includes(hash)) {
            return hash;
        }
        const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
        const tabParam = searchParams ? searchParams.get('tab') : null;
        if (tabParam && validTabs.includes(tabParam)) {
            return tabParam;
        }
        return 'general';
    };

    const activeTab = ref(getTabFromUrl());

    const syncTabWithUrl = () => {
        const tabFromUrl = getTabFromUrl();
        if (tabFromUrl !== activeTab.value) {
            activeTab.value = tabFromUrl;
        }
    };

    watch(activeTab, (newTab) => {
        if (typeof window !== 'undefined' && window.location.hash.replace('#', '') !== newTab) {
            history.replaceState(null, '', '#' + newTab);
        }
    });

    onMounted(() => {
        activeTab.value = getTabFromUrl();
        if (typeof window !== 'undefined') {
            window.addEventListener('hashchange', syncTabWithUrl);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('hashchange', syncTabWithUrl);
        }
    });

    return activeTab;
}
