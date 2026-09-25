<script setup>
// The account control in the top right of a club's public site. A visitor sees a plain "Login" button; a signed-in
// member sees "Member Area", which opens a menu of their member pages for this club and a Log out button.
// In the website builder's preview (interactive: false) it always shows the "Login" button, as a visitor would see it.
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    club: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
});

const page = usePage();
const open = ref(false);

const user = computed(() => (props.interactive ? page.props.auth?.user : null));

// The user's place in this club, if they belong to it (an active member sees the club's own member pages).
const membership = computed(() => (page.props.auth?.clubs ?? []).find((c) => c.slug === props.club.slug && c.status === 'active') || null);
const isStaff = computed(() => !!membership.value && membership.value.role !== 'member');

const base = computed(() => `/members/${props.club.slug}`);

const links = computed(() => (membership.value
    ? [
        { label: 'Member dashboard', href: base.value },
        { label: 'Events', href: `${base.value}/events` },
        { label: 'News', href: `${base.value}/news` },
        { label: 'Meetings', href: `${base.value}/meetings` },
        { label: 'Calendar', href: `${base.value}/calendar` },
        { label: 'Dues', href: `${base.value}/dues` },
        { label: 'My profile', href: `${base.value}/profile` },
    ]
    : [
        { label: 'Member area', href: '/members/dashboard' },
        { label: 'My profile', href: '/members/profile' },
    ]));

const logout = () => {
    open.value = false;
    router.post('/logout');
};

const item = 'block w-full text-left rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800';
</script>

<template>
    <div class="relative shrink-0" @keydown.esc="open = false">
        <!-- A visitor: one plain Login button -->
        <component
            :is="interactive ? Link : 'span'"
            v-if="!user"
            :href="interactive ? '/login' : undefined"
            :class="['inline-block px-4 py-1.5 rounded-xl text-xs font-bold border transition-colors', theme.cardBg]"
        >
            Login
        </component>

        <!-- A signed-in member: the member area menu -->
        <template v-else>
            <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />

            <button
                type="button"
                :class="['relative z-50 inline-flex items-center gap-1.5 px-4 py-1.5 rounded-xl text-xs font-bold border transition-colors cursor-pointer', theme.cardBg]"
                :aria-expanded="open"
                aria-haspopup="menu"
                @click="open = !open"
            >
                Member Area
                <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" /></svg>
            </button>

            <div v-if="open" role="menu" class="absolute right-0 z-50 mt-2 w-56 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 text-slate-800 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                    <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ user.name }}</div>
                    <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ user.email }}</div>
                </div>

                <Link v-for="link in links" :key="link.href" :href="link.href" role="menuitem" :class="item" @click="open = false">{{ link.label }}</Link>

                <Link v-if="isStaff" :href="`/${club.slug}/overview`" role="menuitem" :class="[item, 'border-t border-slate-100 dark:border-slate-800 rounded-t-none mt-1 pt-2']" @click="open = false">Club admin</Link>

                <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                    <button type="button" role="menuitem" :class="[item, 'text-rose-600 dark:text-rose-400 cursor-pointer']" @click="logout">Log out</button>
                </div>
            </div>
        </template>
    </div>
</template>
