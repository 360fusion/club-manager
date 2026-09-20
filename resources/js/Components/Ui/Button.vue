<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
    href: { type: String, default: '' },
});

const VARIANTS = {
    primary: 'bg-blue-600 text-white hover:bg-blue-500 border border-transparent',
    secondary: 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 hover:border-slate-400 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-700 dark:hover:bg-slate-800',
    ghost: 'bg-transparent text-slate-600 border border-transparent hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
    danger: 'bg-rose-600 text-white hover:bg-rose-500 border border-transparent',
};

const SIZES = {
    sm: 'px-3 py-1.5 text-xs gap-1.5',
    md: 'px-4 py-2 text-sm gap-2',
    lg: 'px-5 py-2.5 text-sm gap-2',
};

const classes = computed(() => [
    'inline-flex items-center justify-center rounded-xl font-medium transition-colors',
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    VARIANTS[props.variant] ?? VARIANTS.primary,
    SIZES[props.size] ?? SIZES.md,
    props.block ? 'w-full' : '',
]);
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href || undefined"
        :type="href ? undefined : type"
        :disabled="href ? undefined : (disabled || loading)"
        :class="classes"
    >
        <svg v-if="loading" class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
        </svg>
        <slot name="icon" />
        <slot />
    </component>
</template>
