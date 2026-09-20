<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    title: { type: String, default: 'Members' },
    // The club this page is limited to; null on the all-clubs pages.
    club: { type: Object, default: null },
    activeTab: { type: String, default: 'dashboard' },
    // Kept so pages written for the old member layout can pass it unchanged.
    memberRole: { type: String, default: 'member' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const menuOpen = ref(false);
const switcherOpen = ref(false);

const slug = computed(() => props.club?.slug ?? null);

const memberClubs = computed(() => (page.props.auth?.clubs ?? []).filter((club) => club.status === 'active'));

const currentRole = computed(() => memberClubs.value.find((club) => club.slug === slug.value)?.role ?? 'member');

const initials = computed(() => (user.value?.name || 'ME').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase());

const flash = computed(() => page.props.flash ?? {});
// Only the notices that no form field shows itself; field errors stay next to their fields.
const NOTICE_KEYS = ['cutoff', 'booking_closed', 'rsvp'];
const errors = computed(() => NOTICE_KEYS.map((key) => page.props.errors?.[key]).filter(Boolean));

const SCOPED = ['calendar', 'events', 'meetings', 'news', 'dues'];

const allRoutes = { dashboard: 'members.dashboard', calendar: 'members.calendar', events: 'members.events', meetings: 'members.meetings', news: 'members.news', dues: 'members.dues' };
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
        { key: 'events', label: 'Events' },
        { key: 'meetings', label: 'Meetings' },
        { key: 'news', label: 'News' },
        { key: 'dues', label: 'Dues' },
        { key: 'directory', label: 'Directory' },
        { key: 'subscriptions', label: 'Subscriptions' },
    ].map((item) => ({
        ...item,
        href: item.key === 'directory' ? route('directory.index') : item.key === 'subscriptions' ? route('portal.subscriptions') : route(allRoutes[item.key]),
    }))));

// Switching club keeps you on the same section when that section exists at the other scope.
const switchTo = (target) => {
    const key = SCOPED.includes(props.activeTab) ? props.activeTab : 'dashboard';

    return target ? route(clubRoutes[key], { slug: target }) : route(allRoutes[key]);
};
</script>

<template>
    <Head :title="`${title} - ClubManager`" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-800 dark:bg-slate-950 dark:text-slate-200">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('members.dashboard')" class="flex shrink-0 items-center gap-2" aria-label="ClubManager home">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-sm font-black text-white">CM</span>
                        <span class="hidden text-base font-bold tracking-tight text-slate-900 dark:text-white md:inline">ClubManager</span>
                    </Link>

                    <div class="relative min-w-0">
                        <div v-if="switcherOpen" class="fixed inset-0 z-40" @click="switcherOpen = false" />
                        <button
                            type="button"
                            class="relative z-50 flex max-w-[14rem] items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            :aria-expanded="switcherOpen"
                            aria-haspopup="listbox"
                            @click="switcherOpen = !switcherOpen"
                        >
                            <span class="truncate">{{ club?.name ?? 'All my clubs' }}</span>
                            <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                        </button>

                        <ul v-if="switcherOpen" role="listbox" class="absolute left-0 z-50 mt-2 w-72 max-w-[calc(100vw-2rem)] space-y-0.5 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <li>
                                <Link :href="switchTo(null)" role="option" :aria-selected="!club" class="block rounded-xl px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800" :class="!club ? 'bg-slate-100 dark:bg-slate-800' : ''" @click="switcherOpen = false">All my clubs</Link>
                            </li>
                            <li v-for="option in memberClubs" :key="option.id">
                                <Link :href="switchTo(option.slug)" role="option" :aria-selected="option.slug === slug" class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800" :class="option.slug === slug ? 'bg-slate-100 dark:bg-slate-800' : ''" @click="switcherOpen = false">
                                    <span class="truncate">{{ option.name }}</span>
                                    <span class="shrink-0 text-[11px] capitalize text-slate-500 dark:text-slate-400">{{ option.role }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link v-if="club && currentRole !== 'member'" :href="route('admin.analytics', { slug })" class="hidden rounded-xl border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-300 sm:block">
                        Admin
                    </Link>

                    <NotificationBell />

                    <div class="relative">
                        <div v-if="menuOpen" class="fixed inset-0 z-40" @click="menuOpen = false" />

                        <button
                            type="button"
                            class="relative z-50 flex items-center gap-2 rounded-xl border border-slate-200 p-1.5 pr-3 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                            :aria-expanded="menuOpen"
                            aria-haspopup="menu"
                            @click="menuOpen = !menuOpen"
                        >
                            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-xs font-bold text-white">
                                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-full w-full object-cover" />
                                <span v-else>{{ initials }}</span>
                            </span>
                            <span class="hidden max-w-[120px] truncate text-xs font-semibold text-slate-700 dark:text-slate-200 sm:inline">{{ user?.name }}</span>
                        </button>

                        <div v-if="menuOpen" role="menu" class="absolute right-0 z-50 mt-2 w-64 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900">
                            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                                <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ user?.name }}</div>
                                <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ user?.email }}</div>
                            </div>

                            <Link :href="route('profile.edit')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Profile and photo</Link>
                            <Link :href="route('admin.profile.two-factor')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Password and 2FA</Link>
                            <Link :href="route('portal.subscriptions')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Email subscriptions</Link>
                            <Link v-if="user?.is_super_admin" :href="route('superadmin.dashboard')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800" @click="menuOpen = false">Platform admin</Link>

                            <div class="border-t border-slate-100 pt-1 dark:border-slate-800"><ThemeToggle /></div>

                            <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                                <Link href="/logout" method="post" as="button" class="w-full rounded-xl px-3 py-2 text-left text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-slate-800">Log out</Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="mx-auto max-w-6xl overflow-x-auto px-4 sm:px-6" aria-label="Members">
                <ul class="flex gap-1">
                    <li v-for="item in nav" :key="item.key">
                        <Link
                            :href="item.href"
                            :aria-current="activeTab === item.key ? 'page' : undefined"
                            :class="['-mb-px block whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors', activeTab === item.key ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-300' : 'border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white']"
                        >
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl space-y-4 px-4 py-8 sm:px-6">
            <Alert v-if="flash.success" variant="success">{{ flash.success }}</Alert>
            <Alert v-if="flash.error" variant="danger">{{ flash.error }}</Alert>
            <Alert v-if="errors.length" variant="danger" title="That didn't work"><ul class="list-disc pl-4"><li v-for="message in errors" :key="message">{{ message }}</li></ul></Alert>

            <slot />
        </main>
    </div>
</template>
