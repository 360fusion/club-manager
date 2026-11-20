<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { orderColour } from '@/Utils/orderColour';
import { navMode, setNavMode } from '@/Utils/navMode';
import { isNativeApp } from '@/Support/nativeApp';

const props = defineProps({
    tone: { type: String, default: 'default' },
    // Show the "navigation position" choice; only the members layout has one.
    navChoice: { type: Boolean, default: true },
    // The members area's pages ({key, label, href}), listed here on phones, where the bottom tabs replace the top nav.
    links: { type: Array, default: () => [] },
});

const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth?.user);
const initials = computed(() => (user.value?.name || 'ME').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase());

// On a phone the menu is a sheet from the bottom of the screen, moved to <body> so it sits above the bottom tabs.
const isPhone = ref(false);
const phoneQuery = typeof window !== 'undefined' ? window.matchMedia('(max-width: 767px)') : null;

const close = () => { open.value = false; };
const onKey = (event) => { if (event.key === 'Escape') close(); };

watch(open, (isOpen) => {
    isPhone.value = Boolean(phoneQuery?.matches);
    if (typeof document === 'undefined') return;
    document.body.style.overflow = isOpen && isPhone.value ? 'hidden' : '';
    document[isOpen ? 'addEventListener' : 'removeEventListener']('keydown', onKey);
});
onBeforeUnmount(() => {
    if (typeof document === 'undefined') return;
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
});

// Swiping the sheet's handle down closes it.
let touchStart = null;
const onTouchStart = (event) => { touchStart = event.touches[0].clientY; };
const onTouchEnd = (event) => {
    if (touchStart !== null && event.changedTouches[0].clientY - touchStart > 60) close();
    touchStart = null;
};

// Clubs where the user is staff (any role above plain member), so admins can reach them from anywhere.
const adminClubs = computed(() => (page.props.auth?.clubs ?? []).filter((club) => club.status === 'active' && !['member', 'visitor'].includes(club.role)));

// A coach cannot open the dashboard, so send them to the area their role covers.
const adminHref = (club) => (club.role === 'coach' ? route('admin.events.index', { clubSlug: club.slug }) : route('admin.analytics', { slug: club.slug }));

const LINK_ICONS = { dashboard: 'home', calendar: 'calendar', events: 'ticket', meetings: 'users', news: 'document', dues: 'cash', profile: 'user', lodges: 'building', directory: 'search', subscriptions: 'mail' };

// The phone's bottom tabs already reach home and the calendar.
const goTo = computed(() => props.links.filter((link) => !isPhone.value || !['dashboard', 'calendar'].includes(link.key)));
const listsSubscriptions = computed(() => props.links.some((link) => link.key === 'subscriptions'));

const account = computed(() => [
    { label: isNativeApp ? 'App settings' : 'Notification settings', href: route('members.app_settings'), icon: 'settings' },
    { label: 'Profile and photo', href: route('profile.edit'), icon: 'user' },
    { label: 'Password and 2FA', href: route('admin.profile.two-factor'), icon: 'lock' },
    // Already under "Go to" on a phone when the members area lists it.
    { label: 'Email subscriptions', href: route('portal.subscriptions'), icon: 'mail', hide: isPhone.value && listsSubscriptions.value },
].filter((item) => !item.hide));

const buttonClasses = computed(() => (props.tone === 'onDark'
    ? 'border-slate-600 text-slate-100 hover:bg-slate-700'
    : 'border-slate-200 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'));

const panelClasses = computed(() => (isPhone.value
    ? 'fixed inset-x-0 bottom-0 z-[90] max-h-[92dvh] rounded-t-3xl border-t px-3 pt-2 pb-[calc(env(safe-area-inset-bottom,0px)+0.75rem)]'
    : 'absolute right-0 z-50 mt-2 max-h-[80vh] w-64 rounded-2xl border p-2'));

const item = computed(() => ['flex items-center gap-3 rounded-xl px-3 font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800', isPhone.value ? 'min-h-11 text-[15px]' : 'py-2 text-xs']);
const heading = computed(() => ['px-3 pb-1 pt-2 font-medium text-slate-500 dark:text-slate-400', isPhone.value ? 'text-xs' : 'text-[11px]']);
const iconSize = computed(() => (isPhone.value ? 20 : 16));
</script>

<template>
    <div class="relative">
        <button
            type="button"
            :class="['relative z-50 flex items-center gap-2 rounded-xl border p-1.5 pr-3 transition-colors', buttonClasses]"
            :aria-expanded="open"
            aria-haspopup="menu"
            aria-label="Your account"
            @click="open = !open"
        >
            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-xs font-bold text-white">
                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-full w-full object-cover" />
                <span v-else>{{ initials }}</span>
            </span>
            <span class="hidden max-w-[120px] truncate text-xs font-semibold sm:inline">{{ user?.name }}</span>
        </button>

        <Teleport to="body" :disabled="!isPhone">
            <div v-if="open" :class="['fixed inset-0', isPhone ? 'z-[80] bg-slate-950/40' : 'z-40']" @click="close" />

            <div v-if="open" role="menu" aria-label="Your account" :class="['space-y-1 overflow-y-auto overscroll-contain border-slate-200 bg-white text-slate-800 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200', panelClasses]">
                <div v-if="isPhone" class="flex justify-center pb-1 pt-1" @touchstart.passive="onTouchStart" @touchend="onTouchEnd">
                    <span class="h-1.5 w-10 rounded-full bg-slate-300 dark:bg-slate-600" aria-hidden="true" />
                </div>

                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60" @touchstart.passive="onTouchStart" @touchend="onTouchEnd">
                    <span v-if="isPhone" class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-sm font-bold text-white">
                        <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-full w-full object-cover" />
                        <span v-else>{{ initials }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span :class="['block truncate font-bold text-slate-900 dark:text-white', isPhone ? 'text-base' : 'text-xs']">{{ user?.name }}</span>
                        <span :class="['block truncate text-slate-500 dark:text-slate-400', isPhone ? 'text-sm' : 'text-[11px]']">{{ user?.email }}</span>
                    </span>
                    <button v-if="isPhone" type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-slate-500 hover:bg-slate-200 dark:text-slate-400 dark:hover:bg-slate-700" aria-label="Close" @click="close">
                        <UiIcon name="x" :size="20" />
                    </button>
                </div>

                <div v-if="goTo.length" :class="['border-b border-slate-100 pb-1 dark:border-slate-800', isNativeApp ? '' : 'md:hidden']">
                    <p :class="heading">Go to</p>
                    <!-- Two columns on a phone, so the whole sheet fits without scrolling. -->
                    <div :class="isPhone ? 'grid grid-cols-2' : ''">
                        <Link v-for="link in goTo" :key="link.key" :href="link.href" role="menuitem" :class="item" @click="close">
                            <UiIcon v-if="isPhone" :name="LINK_ICONS[link.key] ?? 'chevron-down'" :size="iconSize" />{{ link.label }}
                        </Link>
                    </div>
                </div>

                <div>
                    <p v-if="isPhone" :class="heading">Your account</p>
                    <Link v-for="entry in account" :key="entry.label" :href="entry.href" role="menuitem" :class="item" @click="close">
                        <UiIcon v-if="isPhone" :name="entry.icon" :size="iconSize" />{{ entry.label }}
                    </Link>
                </div>

                <div v-if="adminClubs.length" class="border-t border-slate-100 pt-1 dark:border-slate-800">
                    <p :class="heading">{{ adminClubs.length === 1 ? (isPhone ? 'Lodge admin' : 'Club admin') : 'Clubs you administer' }}</p>
                    <Link v-for="club in adminClubs" :key="club.id" :href="adminHref(club)" role="menuitem" :class="['flex items-center justify-between gap-3 rounded-xl px-3 font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800', isPhone ? 'min-h-11 py-2 text-[15px]' : 'py-2 text-xs']" @click="close">
                        <span class="flex min-w-0 items-center gap-3">
                            <span :class="['h-2.5 w-2.5 shrink-0 rounded-full', orderColour(club.colour).dot]" aria-hidden="true" />
                            <!-- A phone has room for the whole lodge name, so it wraps there. -->
                            <span :class="isPhone ? '' : 'truncate'">{{ adminClubs.length === 1 && !isPhone ? `Admin: ${club.name}` : club.name }}</span>
                        </span>
                        <span class="shrink-0 text-[11px] font-medium capitalize text-slate-500 dark:text-slate-400">{{ club.role }}</span>
                    </Link>
                </div>

                <Link v-if="user?.is_super_admin" :href="route('superadmin.dashboard')" role="menuitem" :class="item" @click="close">
                    <UiIcon v-if="isPhone" name="chart" :size="iconSize" />Platform admin
                </Link>

                <div class="border-t border-slate-100 pt-1 dark:border-slate-800"><ThemeToggle /></div>

                <div v-if="navChoice" class="hidden border-t border-slate-100 px-3 py-2 dark:border-slate-800 lg:block">
                    <p class="mb-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">Navigation</p>
                    <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="group" aria-label="Navigation position">
                        <button v-for="mode in [{ value: 'top', label: 'Top bar' }, { value: 'side', label: 'Sidebar' }]" :key="mode.value" type="button" :aria-pressed="navMode === mode.value" :class="['rounded-xl px-2 py-1.5 text-xs font-semibold transition-colors', navMode === mode.value ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white']" @click="setNavMode(mode.value)">{{ mode.label }}</button>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                    <Link href="/logout" method="post" as="button" :class="isPhone
                        ? 'mt-1 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-rose-200 text-[15px] font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-400 dark:hover:bg-slate-800'
                        : 'w-full rounded-xl px-3 py-2 text-left text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-slate-800'">
                        <UiIcon v-if="isPhone" name="logout" :size="iconSize" />Log out
                    </Link>
                </div>
            </div>
        </Teleport>
    </div>
</template>
