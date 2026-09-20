<script setup>
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    size: { type: String, default: 'md' },
});

const emit = defineEmits(['close']);

const SIZES = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-2xl',
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(() => props.open, (open) => {
    if (open) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
}, { immediate: true });

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm"
        @mousedown.self="emit('close')"
    >
        <div
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            :class="['max-h-[calc(100vh-2rem)] w-full overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-slate-900', SIZES[size] ?? SIZES.md]"
        >
            <div class="mb-4 flex items-start justify-between gap-4 border-b border-slate-200 pb-4 dark:border-slate-800">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
                    <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
                </div>
                <button
                    type="button"
                    class="shrink-0 rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                    aria-label="Close"
                    @click="emit('close')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </div>

            <slot />

            <div v-if="$slots.footer" class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
