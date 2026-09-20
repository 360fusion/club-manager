<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'info' },
    title: { type: String, default: '' },
});

const VARIANTS = {
    info: 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800/60 dark:text-blue-200',
    success: 'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800/50 dark:text-emerald-200',
    warning: 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/30 dark:border-amber-800/40 dark:text-amber-200',
    danger: 'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800/50 dark:text-rose-200',
};

const classes = computed(() => [
    'rounded-xl border px-4 py-3 text-sm',
    VARIANTS[props.variant] ?? VARIANTS.info,
]);
</script>

<template>
    <div :class="classes" role="status">
        <p v-if="title" class="font-medium mb-0.5">{{ title }}</p>
        <div class="text-[13px] leading-relaxed opacity-90"><slot /></div>
    </div>
</template>
