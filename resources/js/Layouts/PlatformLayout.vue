<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

defineProps({
    title: { type: String, default: 'Members' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const menuOpen = ref(false);

const initials = computed(() => (user.value?.name || 'ME').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase());

const nav = [
    { label: 'Home', href: () => route('members.home'), active: () => route().current('members.home') },
    { label: 'Directory', href: () => route('directory.index'), active: () => route().current('directory.index') },
    { label: 'Subscriptions', href: () => route('portal.subscriptions'), active: () => route().current('portal.subscriptions') },
];
</script>

<template>
    <Head :title="`${title} - ClubManager`" />

    <div class="min-h-screen bg-slate-50 text-slate-800 font-sans dark:bg-slate-950 dark:text-slate-200">
        <header class="sticky top-0 z-40 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <div class="flex items-center gap-6 min-w-0">
                    <Link :href="route('members.home')" class="flex items-center gap-2.5 shrink-0">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-sm font-black text-white">CM</span>
                        <span class="text-base font-bold tracking-tight text-slate-900 dark:text-white">ClubManager</span>
                    </Link>

                    <nav class="hidden items-center gap-1 sm:flex" aria-label="Main">
                        <Link
                            v-for="item in nav"
                            :key="item.label"
                            :href="item.href()"
                            :class="[
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                                item.active()
                                    ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white'
                                    : 'text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                            ]"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

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
                        <span class="hidden max-w-[140px] truncate text-xs font-semibold text-slate-700 dark:text-slate-200 sm:inline">{{ user?.name }}</span>
                    </button>

                    <div
                        v-if="menuOpen"
                        role="menu"
                        class="absolute right-0 z-50 mt-2 w-64 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-700 dark:bg-slate-900"
                    >
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                            <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ user?.name }}</div>
                            <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ user?.email }}</div>
                        </div>

                        <Link :href="route('profile.edit')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Profile and photo</Link>
                        <Link :href="route('admin.profile.two-factor')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Password and 2FA</Link>
                        <Link :href="route('portal.subscriptions')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" @click="menuOpen = false">Email subscriptions</Link>
                        <Link v-if="user?.is_super_admin" :href="route('superadmin.dashboard')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800" @click="menuOpen = false">Platform admin</Link>

                        <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                            <ThemeToggle />
                        </div>

                        <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                            <Link href="/logout" method="post" as="button" class="w-full rounded-xl px-3 py-2 text-left text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-slate-800">Log out</Link>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
