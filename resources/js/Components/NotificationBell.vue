<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    // 'onDark' when the bell sits on the navy header or sidebar.
    tone: { type: String, default: 'default' },
});

const page = usePage();
const open = ref(false);

const buttonClasses = computed(() => (props.tone === 'onDark'
    ? 'border-slate-600 text-slate-200 hover:bg-slate-700'
    : 'border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'));

const unread = computed(() => page.props.bell?.unread ?? 0);
const recent = computed(() => page.props.bell?.recent ?? []);

const CATEGORIES = {
    summons: { label: 'Summons', classes: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' },
    news: { label: 'News', classes: 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' },
    event: { label: 'Event', classes: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' },
    notice: { label: 'Notice', classes: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
    membership: { label: 'Membership', classes: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
    payment: { label: 'Payment', classes: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300' },
};

const rtf = new Intl.RelativeTimeFormat('en-GB', { numeric: 'auto' });

const ago = (iso) => {
    const minutes = Math.round((new Date(iso) - new Date()) / 60000);
    const abs = Math.abs(minutes);

    if (abs < 1) return 'just now';
    if (abs < 60) return rtf.format(minutes, 'minute');
    if (abs < 60 * 24) return rtf.format(Math.round(minutes / 60), 'hour');

    return rtf.format(Math.round(minutes / 60 / 24), 'day');
};

const markAllRead = () => {
    router.post(route('members.notifications.read_all'), {}, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <div class="relative">
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />

        <button
            type="button"
            :class="['relative z-50 flex h-10 w-10 items-center justify-center rounded-xl border transition-colors', buttonClasses]"
            :aria-label="unread ? `Notifications, ${unread} unread` : 'Notifications'"
            :aria-expanded="open"
            aria-haspopup="true"
            @click="open = !open"
            @keydown.esc="open = false"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0" />
            </svg>
            <span v-if="unread" class="absolute -right-1 -top-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white">
                {{ unread > 99 ? '99+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-[22rem] max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Notifications</h2>
                <button v-if="unread" type="button" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400" @click="markAllRead">Mark all read</button>
            </div>

            <ul v-if="recent.length" class="max-h-96 divide-y divide-slate-100 overflow-y-auto dark:divide-slate-800">
                <li v-for="item in recent" :key="item.id">
                    <Link
                        :href="route('members.notifications.open', { id: item.id })"
                        :class="['flex gap-3 px-4 py-3 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60', item.read ? '' : 'bg-blue-50/50 dark:bg-blue-950/20']"
                        @click="open = false"
                    >
                        <span :class="['mt-1.5 h-2 w-2 shrink-0 rounded-full', item.read ? 'bg-transparent' : 'bg-blue-600']" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                                <span :class="['rounded-full px-2 py-0.5 font-medium', CATEGORIES[item.category]?.classes]">{{ CATEGORIES[item.category]?.label ?? item.category }}</span>
                                <span class="truncate">{{ item.club?.name }}</span>
                                <span aria-hidden="true">·</span>
                                <time :datetime="item.at">{{ ago(item.at) }}</time>
                            </span>
                            <span class="mt-0.5 block truncate text-sm font-medium text-slate-900 dark:text-white">{{ item.title }}</span>
                            <span v-if="item.body" class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ item.body }}</span>
                        </span>
                    </Link>
                </li>
            </ul>
            <p v-else class="px-4 py-8 text-center text-sm text-slate-500 dark:text-slate-400">You're all caught up.</p>

            <Link
                :href="route('members.notifications')"
                class="block border-t border-slate-200 px-4 py-3 text-center text-sm font-medium text-blue-600 hover:bg-slate-50 dark:border-slate-800 dark:text-blue-400 dark:hover:bg-slate-800/60"
                @click="open = false"
            >
                View all notifications
            </Link>
        </div>
    </div>
</template>
