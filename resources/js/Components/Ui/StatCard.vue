<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    caption: { type: String, default: '' },
});
</script>

<template>
    <div class="min-w-0 bg-white border border-slate-200 rounded-2xl p-3 shadow-sm sm:p-4 dark:bg-slate-900 dark:border-slate-800">
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
        <p class="mt-1 font-display text-xl font-semibold sm:text-2xl lining-nums tabular-nums text-slate-900 dark:text-white">{{ value }}</p>
        <p v-if="caption" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ caption }}</p>
    </div>
</template>
