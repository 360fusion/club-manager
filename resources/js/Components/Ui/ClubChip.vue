<script setup>
import { orderColour } from '@/Utils/orderColour';

defineProps({
    name: { type: String, required: true },
    // An order colour key, such as 'sky' or 'red'.
    colour: { type: String, default: 'slate' },
});
</script>

<template>
    <span :class="['inline-flex max-w-full items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium', orderColour(colour).soft]">
        <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', orderColour(colour).dot]" aria-hidden="true" />
        <span class="truncate">{{ name }}</span>
    </span>
</template>
