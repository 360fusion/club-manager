<script setup>
import { computed, useId } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const id = useId();

const fieldClasses = computed(() => [
    'w-full rounded-xl border px-3 py-2 text-sm transition-colors',
    'bg-white text-slate-900 placeholder-slate-400',
    'dark:bg-slate-950 dark:text-white dark:placeholder-slate-500',
    'focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
    'disabled:opacity-50 disabled:cursor-not-allowed',
    props.error
        ? 'border-rose-400 dark:border-rose-700'
        : 'border-slate-300 dark:border-slate-700',
]);
</script>

<template>
    <div class="space-y-1.5">
        <label v-if="label" :for="id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
            {{ label }}
            <span v-if="required" class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span>
        </label>

        <input
            :id="id"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
            :class="fieldClasses"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <p v-if="error" :id="`${id}-error`" class="text-xs text-rose-600 dark:text-rose-400">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
