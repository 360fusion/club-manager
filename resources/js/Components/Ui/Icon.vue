<script setup>
// A block icon by key (see Support/icons.js). It is one em tall and takes its colour from the text, so size it with
// the parent's font size and colour it with a text colour class. Renders nothing for an unknown key.
import { computed } from 'vue';
import { BLOCK_ICONS } from '@/Support/icons';

const props = defineProps({
    name: { type: String, default: '' },
});

const icon = computed(() => BLOCK_ICONS[props.name] || null);
</script>

<template>
    <svg v-if="icon" :viewBox="icon.viewBox" fill="currentColor" aria-hidden="true" focusable="false" class="inline-block h-[1em] w-auto shrink-0">
        <path :d="icon.path" />
    </svg>
</template>
