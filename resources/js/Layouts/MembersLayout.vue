<script setup>
import { computed, provide } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AccountMenu from '@/Components/AccountMenu.vue';
import Alert from '@/Components/Ui/Alert.vue';
import FlashMessages from '@/Components/Ui/FlashMessages.vue';
import ClubSwitcher from '@/Components/ClubSwitcher.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import { navMode } from '@/Utils/navMode';
import AppTabBar from '@/Components/AppTabBar.vue';
import BiometricSignInOffer from '@/Components/BiometricSignInOffer.vue';
import OfflineBanner from '@/Components/OfflineBanner.vue';
import SavedCopyNotice from '@/Components/SavedCopyNotice.vue';
import { pageLoading } from '@/Support/pageLoading';
import PullToRefresh from '@/Components/PullToRefresh.vue';
import { isNativeApp } from '@/Support/nativeApp';

const props = defineProps({
    title: { type: String, default: 'Members' },
    // The club this page is limited to; null on the all-clubs pages.
    club: { type: Object, default: null },
    activeTab: { type: String, default: 'dashboard' },
    // Kept so pages written for the old member layout can pass it unchanged.
    memberRole: { type: String, default: 'member' },
    // The page renders its own <h1> (the lodge directory pages), so the layout leaves its hidden one out.
    ownHeading: { type: Boolean, default: false },
});

const page = usePage();
const slug = computed(() => props.club?.slug ?? null);
const side = computed(() => navMode.value === 'side');

const currentRole = computed(() => (page.props.auth?.clubs ?? []).find((club) => club.slug === slug.value)?.role ?? 'member');
// Members and visitors have no admin screens; every other role is lodge staff.
const isStaff = computed(() => Boolean(props.club) && !['member', 'visitor'].includes(currentRole.value));
// Coaches are not allowed the analytics dashboard, so send them to events instead.
const adminHome = computed(() => (currentRole.value === 'coach' ? route('admin.events.index', { clubSlug: slug.value }) : route('admin.analytics', { slug: slug.value })));

// Only the notices that no form field shows itself; field errors stay next to their fields.
const NOTICE_KEYS = ['cutoff', 'booking_closed', 'rsvp', 'follow'];
const errors = computed(() => NOTICE_KEYS.map((key) => page.props.errors?.[key]).filter(Boolean));

const ICONS = {
    dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    calendar: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    events: 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
    meetings: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    news: 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h11a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z',
    dues: 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
    lodges: 'M12 21s-7-5.6-7-11a7 7 0 1114 0c0 5.4-7 11-7 11zm0-8a3 3 0 100-6 3 3 0 000 6z',
    directory: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    subscriptions: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    profile: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
};

const allRoutes = { dashboard: 'members.dashboard', calendar: 'members.calendar', events: 'members.events', meetings: 'members.events', news: 'members.news', dues: 'members.dues', lodges: 'members.lodges' };
const clubRoutes = { dashboard: 'member.dashboard', calendar: 'member.calendar', events: 'member.events', meetings: 'member.meetings', news: 'member.news', dues: 'member.dues' };

const nav = computed(() => (slug.value
    ? [
        { key: 'dashboard', label: 'Overview' },
        { key: 'calendar', label: 'Calendar' },
        { key: 'events', label: 'Events' },
        { key: 'meetings', label: 'Meetings' },
        { key: 'news', label: 'News' },
        { key: 'dues', label: 'Dues' },
        { key: 'profile', label: 'My details' },
    ].map((item) => ({ ...item, href: item.key === 'profile' ? route('member.profile', { slug: slug.value }) : route(clubRoutes[item.key], { slug: slug.value }) }))
    : [
        { key: 'dashboard', label: 'Dashboard' },
        { key: 'calendar', label: 'Calendar' },
        // Meetings and events share one page across all lodges.
        { key: 'events', label: 'Meetings and bookings' },
        { key: 'news', label: 'News' },
        { key: 'dues', label: 'Dues' },
        { key: 'lodges', label: 'My lodges' },
        { key: 'directory', label: 'Directory' },
        { key: 'subscriptions', label: 'Subscriptions' },
    ].map((item) => ({
        ...item,
        href: item.key === 'directory' ? route('lodges.index') : item.key === 'subscriptions' ? route('portal.subscriptions') : route(allRoutes[item.key]),
    }))));

// This layout renders the page's <h1>, so PageHeader titles become <h2>.
provide('layoutHasHeading', !props.ownHeading);
</script>

<template>
    <Head :title="`${title} - ClubManager`" />

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[200] focus:px-4 focus:py-2 focus:rounded-xl focus:bg-blue-600 focus:text-white focus:font-bold focus:text-sm focus:shadow-lg">
        Skip to main content
    </a>

    <div class="min-h-dvh bg-slate-100 font-sans text-slate-800 dark:bg-slate-950 dark:text-slate-200 lg:flex">
        <aside v-if="side" class="cm-nav sticky top-0 hidden h-dvh w-64 shrink-0 flex-col gap-6 overflow-y-auto bg-slate-800 p-4 text-slate-300 lg:flex">
            <Link :href="route('members.dashboard')" class="flex items-center gap-2.5 px-2 pt-1" aria-label="ClubManager home">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-sm font-black text-white shadow-md shadow-blue-600/30">CM</span>
                <span class="text-base font-bold tracking-tight text-white">ClubManager</span>
            </Link>

            <ClubSwitcher :club="club" :active-tab="activeTab" tone="onDark" block />

            <nav aria-label="Members" class="flex-1">
                <ul class="space-y-0.5">
                    <li v-for="item in nav" :key="item.key">
                        <Link
                            :href="item.href"
                            prefetch
                            :aria-current="activeTab === item.key ? 'page' : undefined"
                            :class="['flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium transition-colors', activeTab === item.key ? 'bg-slate-700/70 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white']"
                        >
                            <svg class="h-5 w-5 shrink-0" :class="activeTab === item.key ? 'text-blue-400' : 'text-slate-500 dark:text-slate-400'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="ICONS[item.key]" /></svg>
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <Link v-if="isStaff" :href="adminHome" class="flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-600/20 hover:bg-blue-500">
                Open club admin
            </Link>
        </aside>

        <div class="min-w-0 flex-1">
            <header :class="['cm-nav app-top-bar sticky top-0 z-40 border-b border-slate-800 bg-slate-800 text-slate-200', side ? 'lg:hidden' : '']">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-3 py-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <Link :href="route('members.dashboard')" class="flex shrink-0 items-center gap-2" aria-label="ClubManager home">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-sm font-black text-white shadow-md shadow-blue-600/30">CM</span>
                            <span class="hidden text-base font-bold tracking-tight text-white md:inline">ClubManager</span>
                        </Link>
                        <ClubSwitcher :club="club" :active-tab="activeTab" tone="onDark" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Link v-if="isStaff" :href="adminHome" class="hidden rounded-xl border border-blue-400/40 bg-blue-500/10 px-3 py-2 text-xs font-semibold text-blue-200 hover:bg-blue-500/20 sm:block">Admin</Link>
                        <NotificationBell tone="onDark" />
                        <AccountMenu tone="onDark" :links="nav" />
                    </div>
                </div>

                <!-- Phones (the app, and the web below md) use the bottom tabs instead; the other pages are in the account menu. -->
                <nav v-if="!isNativeApp" class="mx-auto hidden max-w-6xl overflow-x-auto px-4 sm:px-6 md:block" aria-label="Members">
                    <ul class="flex gap-1">
                        <li v-for="item in nav" :key="item.key">
                            <Link
                                :href="item.href"
                                prefetch
                                :aria-current="activeTab === item.key ? 'page' : undefined"
                                :class="['-mb-px block whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors', activeTab === item.key ? 'border-blue-400 text-white' : 'border-transparent text-slate-400 hover:text-white']"
                            >
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </nav>
            </header>

            <div v-if="side" class="sticky top-0 z-40 hidden items-center justify-between gap-3 border-b border-slate-200 bg-white/90 px-6 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90 lg:flex">
                <p class="truncate text-sm font-medium text-slate-500 dark:text-slate-400">
                    <span class="text-slate-900 dark:text-white">{{ club?.name ?? 'All my lodges' }}</span>
                    <span aria-hidden="true"> / </span>{{ title }}
                </p>
                <div class="flex items-center gap-2">
                    <NotificationBell />
                    <AccountMenu />
                </div>
            </div>

            <main id="main-content" :class="['mx-auto max-w-6xl space-y-4 px-3 py-5 sm:px-6 sm:py-8', isNativeApp ? 'pb-28' : 'pb-28 md:pb-8']">
                <h1 v-if="!ownHeading" class="sr-only">{{ title }}</h1>
                <SavedCopyNotice />
                <FlashMessages />
                <Alert v-if="errors.length" variant="danger" title="That didn't work"><ul class="list-disc pl-4"><li v-for="message in errors" :key="message">{{ message }}</li></ul></Alert>

                <!-- A tab opened instantly: placeholders until its own data arrives (Support/pageLoading.js). -->
                <div v-if="pageLoading.instant" class="space-y-4" aria-busy="true" aria-label="Loading">
                    <div class="h-8 w-2/5 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-800" />
                    <div v-for="n in 3" :key="n" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                        <div class="h-3 w-1/2 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                        <div class="h-3 w-5/6 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                    </div>
                </div>
                <slot v-else />
            </main>
        </div>
    </div>

    <AppTabBar :club="club" :active-tab="activeTab" :class="isNativeApp ? '' : 'md:hidden'" />
    <BiometricSignInOffer v-if="isNativeApp" />
    <OfflineBanner />
    <PullToRefresh />
</template>
