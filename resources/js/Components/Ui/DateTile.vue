<script setup>
import { computed } from 'vue';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
    date: { type: String, required: true },
    // An order colour key, such as 'sky' or 'red'.
    colour: { type: String, default: 'slate' },
    size: { type: String, default: 'md' },
});

const parts = computed(() => {
    const date = new Date(props.date);

    return {
        month: date.toLocaleDateString('en-GB', { month: 'short' }),
        day: date.getDate(),
        year: date.getFullYear(),
        long: date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    };
});

const SIZES = {
    sm: 'h-12 w-12 rounded-xl',
    md: 'h-16 w-16 rounded-2xl sm:h-20 sm:w-20',
};
</script>

<template>
    <div :class="['flex shrink-0 flex-col items-center justify-center shadow-sm', SIZES[size] ?? SIZES.md, orderColour(colour).tile]" :title="parts.long">
        <span :class="['font-semibold uppercase tracking-wide opacity-90', size === 'sm' ? 'text-[9px]' : 'text-[11px]']">{{ parts.month }}</span>
        <span :class="['font-bold leading-none', size === 'sm' ? 'text-lg' : 'text-2xl sm:text-3xl']">{{ parts.day }}</span>
        <span v-if="size !== 'sm'" class="mt-0.5 text-[10px] opacity-80">{{ parts.year }}</span>
    </div>
</template>
