<script setup>
// Shown above a page that came from the copy saved on this phone (no signal, or too weak), so nobody takes old
// information for new. It goes once the page loads fresh.
import { computed } from 'vue';
import { savedCopy } from '@/Support/offlinePages';

const when = computed(() => {
    const date = savedCopy.savedAt && savedCopy.savedAt !== 'unknown' ? new Date(savedCopy.savedAt) : null;
    if (!date || Number.isNaN(date.getTime())) return '';
    const today = date.toDateString() === new Date().toDateString();

    return today
        ? date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
});
</script>

<template>
    <p v-if="savedCopy.savedAt" role="status" class="flex items-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
        <span class="h-2 w-2 shrink-0 rounded-full bg-amber-500" aria-hidden="true" />
        <span>Saved copy{{ when ? ` from ${when}` : '' }}, shown without signal. It updates when you're back online.</span>
    </p>
</template>
