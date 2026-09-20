<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    caption: { type: String, default: '' },
});
</script>

<template>
    <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm dark:bg-slate-900 dark:border-slate-800">
        <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold text-slate-900 dark:text-white">{{ value }}</p>
        <p v-if="caption" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ caption }}</p>
    </div>
</template>
