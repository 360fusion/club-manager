<script setup>
// An interface icon by name (see Support/uiIcons.js): an outline drawn in the text colour, 16px unless `size` says
// otherwise (the design system allows 16-20). Decorative, so screen readers skip it: put the meaning in the label.
import { UI_ICONS } from '@/Support/uiIcons';

defineProps({
    name: { type: String, required: true },
    size: { type: Number, default: 16 },
});
</script>

<template>
    <svg v-if="UI_ICONS[name]" :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" class="shrink-0">
        <path :d="UI_ICONS[name]" />
    </svg>
</template>
