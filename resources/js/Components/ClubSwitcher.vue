<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    // The club the page is limited to, or null for all clubs.
    club: { type: Object, default: null },
    activeTab: { type: String, default: 'dashboard' },
    tone: { type: String, default: 'default' },
    block: { type: Boolean, default: false },
});

const page = usePage();
const open = ref(false);

const clubs = computed(() => (page.props.auth?.clubs ?? []).filter((club) => club.status === 'active'));
const slug = computed(() => props.club?.slug ?? null);

const SCOPED = ['calendar', 'events', 'meetings', 'news', 'dues'];
const allRoutes = { dashboard: 'members.dashboard', calendar: 'members.calendar', events: 'members.events', meetings: 'members.meetings', news: 'members.news', dues: 'members.dues' };
const clubRoutes = { dashboard: 'member.dashboard', calendar: 'member.calendar', events: 'member.events', meetings: 'member.meetings', news: 'member.news', dues: 'member.dues' };

// Switching club keeps you on the same section when that section exists at the other scope.
const target = (clubSlug) => {
    const key = SCOPED.includes(props.activeTab) ? props.activeTab : 'dashboard';

    return clubSlug ? route(clubRoutes[key], { slug: clubSlug }) : route(allRoutes[key]);
};

const buttonClasses = computed(() => (props.tone === 'onDark'
    ? 'border-slate-600 bg-slate-800/60 text-slate-100 hover:bg-slate-700'
    : 'border-slate-200 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'));
</script>

<template>
    <div :class="['relative min-w-0', block ? 'w-full' : '']">
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />

        <button
            type="button"
            :class="['relative z-50 flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-medium transition-colors', block ? 'w-full justify-between' : 'max-w-[14rem]', buttonClasses]"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <span class="truncate">{{ club?.name ?? 'All my clubs' }}</span>
            <svg class="h-4 w-4 shrink-0 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </button>

        <ul v-if="open" role="listbox" class="absolute left-0 z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] space-y-0.5 rounded-2xl border border-slate-200 bg-white p-2 text-slate-800 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            <li>
                <Link :href="target(null)" role="option" :aria-selected="!club" :class="['block rounded-xl px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800', !club ? 'bg-slate-100 dark:bg-slate-800' : '']" @click="open = false">All my clubs</Link>
            </li>
            <li v-for="option in clubs" :key="option.id">
                <Link :href="target(option.slug)" role="option" :aria-selected="option.slug === slug" :class="['flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800', option.slug === slug ? 'bg-slate-100 dark:bg-slate-800' : '']" @click="open = false">
                    <span class="truncate">{{ option.name }}</span>
                    <span class="shrink-0 text-[11px] capitalize text-slate-500 dark:text-slate-400">{{ option.role }}</span>
                </Link>
            </li>
        </ul>
    </div>
</template>
