<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    // 'default' sits on a themed surface; 'onDark' sits on a surface that is dark in both modes.
    tone: { type: String, default: 'default' },
});

const STORAGE_KEY = 'superadmin-theme';

const buttonClasses = computed(() => props.tone === 'onDark'
    ? 'text-slate-300 hover:text-white hover:bg-slate-800'
    : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800');

const trackOffClasses = computed(() => props.tone === 'onDark'
    ? 'bg-slate-600 group-hover:bg-slate-500'
    : 'bg-slate-300 group-hover:bg-slate-400');

const isDark = ref(false);

function apply(dark) {
    isDark.value = dark;
    document.documentElement.classList.toggle('dark', dark);
}

onMounted(() => {
    let stored = null;

    try {
        stored = localStorage.getItem(STORAGE_KEY);
    } catch (e) {
        stored = null;
    }

    apply(stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches);
});

function toggle() {
    apply(!isDark.value);

    try {
        localStorage.setItem(STORAGE_KEY, isDark.value ? 'dark' : 'light');
    } catch (e) {
        // Storage unavailable (private browsing); the theme still applies for this page.
    }
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="isDark"
        @click="toggle"
        :class="['group flex w-full items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl transition-all', buttonClasses]"
    >
        <span class="flex items-center gap-3">
            <svg v-if="isDark" class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg v-else class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <span>{{ isDark ? 'Dark mode' : 'Light mode' }}</span>
        </span>

        <span
            :class="[
                'relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors duration-200',
                isDark ? 'bg-blue-600' : trackOffClasses
            ]"
        >
            <span
                :class="[
                    'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-200',
                    isDark ? 'translate-x-[1.125rem]' : 'translate-x-[0.1875rem]'
                ]"
            />
        </span>
    </button>
</template>
