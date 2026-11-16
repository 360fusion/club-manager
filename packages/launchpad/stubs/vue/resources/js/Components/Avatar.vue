<script setup>
// The person's photo, or the first letter of their name on a coloured disc (as Launchpad's Blade <x-launchpad::avatar>).
import { computed } from 'vue';

const props = defineProps({
    url: { type: String, default: null },
    name: { type: String, default: '' },
    size: { type: Number, default: 32 },
});

const letter = computed(() => (props.name || '?').charAt(0).toUpperCase());
</script>

<template>
    <img v-if="url" class="lp-avatar lp-avatar--photo" :src="url" alt="" :width="size" :height="size" :style="{ width: `${size}px`, height: `${size}px` }">
    <span v-else class="lp-avatar" aria-hidden="true" :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.42)}px` }">{{ letter }}</span>
</template>
