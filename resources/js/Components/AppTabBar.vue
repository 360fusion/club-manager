<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

// The bottom tabs: always in the iOS and Android app, and on the web at phone width (wider screens keep the top nav).
const props = defineProps({
    club: { type: Object, default: null },
    activeTab: { type: String, default: 'dashboard' },
});

const page = usePage();
const unread = computed(() => page.props.bell?.unread ?? 0);
const onInbox = computed(() => page.component === 'Members/Notifications');

const tabs = computed(() => [
    { key: 'dashboard', label: 'Home', href: route('members.dashboard'), icon: 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z' },
    { key: 'calendar', label: 'Calendar', href: route('members.calendar'), icon: 'M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 10h18M8 3v4M16 3v4' },
    { key: 'events', label: 'Bookings', href: route('members.events'), icon: 'M3 8a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2a2 2 0 0 0 0 4v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-2a2 2 0 0 0 0-4zM14 7v10' },
    { key: 'inbox', label: 'Inbox', href: route('members.notifications'), icon: 'M4 4h16v16H4zM4 14h5l1 2h4l1-2h5' },
    {
        key: 'lodge',
        label: 'Lodge',
        // Always My lodges: the member's own lodges (each opens its members page), suggestions and those they follow.
        href: route('members.lodges'),
        icon: 'M3 21h18M5 21V10M19 21V10M9.5 21V10M14.5 21V10M2 10l10-6 10 6z',
    },
]);

function isActive(tab) {
    if (tab.key === 'inbox') return onInbox.value;
    if (onInbox.value) return false;
    if (tab.key === 'lodge') return props.activeTab === 'lodges' || (Boolean(props.club) && props.activeTab === 'dashboard');
    if (tab.key === 'dashboard') return !props.club && props.activeTab === 'dashboard';
    return props.activeTab === tab.key;
}
</script>

<template>
    <nav aria-label="App" class="app-tab-bar fixed inset-x-0 bottom-0 z-40 select-none border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] dark:border-slate-800 dark:bg-slate-900">
        <ul class="mx-auto flex max-w-xl">
            <li v-for="tab in tabs" :key="tab.key" class="flex-1">
                <Link
                    :href="tab.href"
                    :prefetch="tab.key === 'inbox' ? 'click' : ['mount', 'click']"
                    :aria-current="isActive(tab) ? 'page' : undefined"
                    :class="['relative flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium', isActive(tab) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400']"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="tab.icon" /></svg>
                    {{ tab.label }}
                    <span v-if="tab.key === 'inbox' && unread" class="absolute top-1.5 left-1/2 ml-1.5 min-w-4 rounded-full bg-blue-600 px-1 text-center text-[10px] leading-4 font-bold text-white">
                        {{ unread > 9 ? '9+' : unread }}<span class="sr-only"> unread</span>
                    </span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
