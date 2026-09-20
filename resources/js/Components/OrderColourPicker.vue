<script setup>
import { ORDER_COLOURS, orderColour } from '@/Utils/orderColour';

defineProps({
    modelValue: { type: String, default: 'slate' },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div role="radiogroup" aria-label="Order colour" class="flex flex-wrap gap-2">
        <button
            v-for="option in ORDER_COLOURS"
            :key="option.key"
            type="button"
            role="radio"
            :aria-checked="modelValue === option.key"
            :aria-label="option.label"
            :title="option.label"
            :disabled="disabled"
            :class="['flex h-8 w-8 items-center justify-center rounded-full border-2 transition disabled:cursor-not-allowed disabled:opacity-60', modelValue === option.key ? 'border-slate-900 ring-2 ring-blue-500 ring-offset-2 dark:border-white dark:ring-offset-slate-900' : 'border-transparent hover:scale-110']"
            @click="$emit('update:modelValue', option.key)"
        >
            <span :class="['h-6 w-6 rounded-full', orderColour(option.key).dot]" />
        </button>
    </div>
</template>
