<script setup>
import { ref, onMounted } from 'vue';

const STORAGE_KEY = 'superadmin-theme';

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
        @click="toggle"
        :aria-pressed="isDark"
        :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
        aria-label="Toggle colour theme"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white text-slate-700 text-xs font-medium transition hover:bg-slate-100 hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:border-slate-600"
    >
        <svg v-if="isDark" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
        </svg>
        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
        </svg>
        <span>{{ isDark ? 'Light' : 'Dark' }}</span>
    </button>
</template>
