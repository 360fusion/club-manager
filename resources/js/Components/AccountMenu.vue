<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { navMode, setNavMode } from '@/Utils/navMode';

const props = defineProps({
    tone: { type: String, default: 'default' },
    // Show the "navigation position" choice; only the members layout has one.
    navChoice: { type: Boolean, default: true },
});

const page = usePage();
const open = ref(false);
const user = computed(() => page.props.auth?.user);
const initials = computed(() => (user.value?.name || 'ME').split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase());

const buttonClasses = computed(() => (props.tone === 'onDark'
    ? 'border-slate-600 text-slate-100 hover:bg-slate-700'
    : 'border-slate-200 text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'));

const item = 'block rounded-xl px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800';
</script>

<template>
    <div class="relative">
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false" />

        <button
            type="button"
            :class="['relative z-50 flex items-center gap-2 rounded-xl border p-1.5 pr-3 transition-colors', buttonClasses]"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="open = !open"
        >
            <span class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-blue-600 text-xs font-bold text-white">
                <img v-if="user?.avatar_url" :src="user.avatar_url" alt="" class="h-full w-full object-cover" />
                <span v-else>{{ initials }}</span>
            </span>
            <span class="hidden max-w-[120px] truncate text-xs font-semibold sm:inline">{{ user?.name }}</span>
        </button>

        <div v-if="open" role="menu" class="absolute right-0 z-50 mt-2 w-64 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 text-slate-800 shadow-xl dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/60">
                <div class="truncate text-xs font-bold text-slate-900 dark:text-white">{{ user?.name }}</div>
                <div class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ user?.email }}</div>
            </div>

            <Link :href="route('profile.edit')" role="menuitem" :class="item" @click="open = false">Profile and photo</Link>
            <Link :href="route('admin.profile.two-factor')" role="menuitem" :class="item" @click="open = false">Password and 2FA</Link>
            <Link :href="route('portal.subscriptions')" role="menuitem" :class="item" @click="open = false">Email subscriptions</Link>
            <Link v-if="user?.is_super_admin" :href="route('superadmin.dashboard')" role="menuitem" class="block rounded-xl px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800" @click="open = false">Platform admin</Link>

            <div class="border-t border-slate-100 pt-1 dark:border-slate-800"><ThemeToggle /></div>

            <div v-if="navChoice" class="hidden border-t border-slate-100 px-3 py-2 dark:border-slate-800 lg:block">
                <p class="mb-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">Navigation</p>
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="group" aria-label="Navigation position">
                    <button v-for="mode in [{ value: 'top', label: 'Top bar' }, { value: 'side', label: 'Sidebar' }]" :key="mode.value" type="button" :aria-pressed="navMode === mode.value" :class="['rounded-lg px-2 py-1.5 text-xs font-semibold transition-colors', navMode === mode.value ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white']" @click="setNavMode(mode.value)">{{ mode.label }}</button>
                </div>
            </div>

            <div class="border-t border-slate-100 pt-1 dark:border-slate-800">
                <Link href="/logout" method="post" as="button" class="w-full rounded-xl px-3 py-2 text-left text-xs font-bold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-slate-800">Log out</Link>
            </div>
        </div>
    </div>
</template>
