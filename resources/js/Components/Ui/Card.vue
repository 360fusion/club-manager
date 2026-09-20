<script setup>
import { computed } from 'vue';

const props = defineProps({
    padding: { type: String, default: 'md' },
});

const PADDING = {
    none: '',
    sm: 'p-4',
    md: 'p-6',
    lg: 'p-6 sm:p-8',
};

const classes = computed(() => [
    'bg-white border border-slate-200 rounded-2xl shadow-sm',
    'dark:bg-slate-900 dark:border-slate-800',
    PADDING[props.padding] ?? PADDING.md,
]);
</script>

<template>
    <div :class="classes">
        <div v-if="$slots.header" class="mb-4 pb-4 border-b border-slate-200 dark:border-slate-800">
            <slot name="header" />
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-800">
            <slot name="footer" />
        </div>
    </div>
</template>
