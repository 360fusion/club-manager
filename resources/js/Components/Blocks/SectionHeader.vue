<script setup>
// The small label, serif title, short divider and introduction that open a section. Shared by the icon cards, the
// stats row and the standalone Section Heading block so they all look the same in every theme.
defineProps({
    eyebrow: { type: String, default: '' },
    title: { type: String, default: '' },
    intro: { type: String, default: '' },
    divider: { type: Boolean, default: true },
    align: { type: String, default: 'center' },
    theme: { type: Object, required: true },
});
</script>

<template>
    <header v-if="eyebrow || title || intro" :class="['space-y-3', align === 'left' ? 'text-left' : 'text-center']">
        <span v-if="eyebrow" :class="['block text-xs font-semibold uppercase tracking-[0.25em]', theme.accentText]">{{ eyebrow }}</span>
        <h2 v-if="title" :class="['text-3xl sm:text-4xl font-semibold leading-tight tracking-tight', theme.headingText]">{{ title }}</h2>
        <div v-if="divider && (title || eyebrow)" :class="['h-[3px] w-[60px] rounded-sm bg-[var(--cm-accent)]', align === 'left' ? '' : 'mx-auto']"></div>
        <p v-if="intro" :class="['max-w-3xl text-base sm:text-lg leading-relaxed', align === 'left' ? '' : 'mx-auto', theme.bodyText]">{{ intro }}</p>
    </header>
</template>
