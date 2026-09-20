<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const id = useId();

const fieldClasses = computed(() => [
    'w-full rounded-xl border px-3 py-2 text-sm transition-colors',
    'bg-white text-slate-900 dark:bg-slate-950 dark:text-white',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    props.error ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700',
]);
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ label }}</label>

        <select
            :id="id"
            :value="modelValue"
            :disabled="disabled"
            :class="fieldClasses"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>

        <p v-if="error" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
