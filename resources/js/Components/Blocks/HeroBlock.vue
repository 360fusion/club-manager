<script setup>
// The Hero Banner. Older heroes have a title, subtitle and one button; newer ones can also have a photo (with a
// darkening overlay), a custom or hidden label, a second button, an alignment and a height.
import { computed } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusLg: { type: String, default: 'rounded-3xl' },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const OVERLAY = { light: 'bg-black/30', medium: 'bg-black/50', strong: 'bg-black/70' };

const isEditorial = computed(() => props.theme.layout === 'editorial');
const isBanded = computed(() => props.theme.layout === 'banded');
const hasImage = computed(() => !!props.block.image_url);

// "auto" keeps each layout's own alignment: the editorial layout is left aligned, the rest are centred.
const leftAligned = computed(() => (props.block.align === 'left' ? true : props.block.align === 'center' ? false : isEditorial.value));

const spacing = computed(() => {
    const heights = {
        compact: isEditorial.value ? 'py-10 sm:py-14' : 'py-12 sm:py-16',
        tall: isEditorial.value ? 'py-24 sm:py-40' : 'py-32 sm:py-48',
    };

    return heights[props.block.height] || (isEditorial.value ? 'py-16 sm:py-24' : isBanded.value ? 'py-20 sm:py-28' : 'p-10 sm:p-16');
});

const wrapperClass = computed(() => {
    const base = ['relative overflow-hidden transition-colors', spacing.value, leftAligned.value ? 'text-left' : 'text-center'];
    if (isEditorial.value) return [...base, props.theme.heroBg, hasImage.value ? '!text-white' : ''];
    if (isBanded.value) return [...base, props.theme.heroBg, hasImage.value ? '!text-white' : ''];

    return [...base, 'border', props.radiusLg, props.theme.heroBg, hasImage.value ? '!text-white' : ''];
});

const eyebrow = computed(() => (props.block.hide_eyebrow ? '' : props.block.eyebrow || 'Official Club Website'));
const hasSecond = computed(() => !!(props.block.cta2_text && (props.block.cta2_link || !props.interactive)));
const tag = (link) => (props.interactive && link ? 'a' : 'span');
</script>

<template>
    <section :class="wrapperClass">
        <img v-if="hasImage" :src="block.image_url" alt="" loading="eager" class="absolute inset-0 h-full w-full object-cover" />
        <div v-if="hasImage" :class="['absolute inset-0', OVERLAY[block.overlay] || OVERLAY.medium]"></div>

        <div :class="['relative z-10 space-y-6', leftAligned ? 'max-w-4xl' : 'mx-auto max-w-3xl']">
            <div v-if="eyebrow" :class="['inline-block border px-3 py-1 text-xs font-semibold uppercase tracking-wider', isEditorial ? '' : 'rounded-full', theme.heroPill]">{{ eyebrow }}</div>
            <div v-if="isEditorial && leftAligned" class="h-0.5 w-16 bg-current opacity-40"></div>
            <h2 :class="['text-4xl font-black leading-tight sm:text-6xl', isEditorial ? 'tracking-tight' : '']">{{ block.title || 'Welcome' }}</h2>
            <p v-if="block.subtitle" :class="['text-lg sm:text-xl', isEditorial ? 'opacity-80' : 'opacity-90', leftAligned ? 'max-w-2xl' : '']">{{ block.subtitle }}</p>
            <div v-if="block.cta_text || hasSecond" :class="['flex flex-wrap gap-3 pt-2', leftAligned ? 'justify-start' : 'justify-center']">
                <component
                    :is="tag(block.cta_link)"
                    v-if="block.cta_text"
                    :href="interactive && block.cta_link ? resolveUrl(block.cta_link) : undefined"
                    :class="['inline-block px-7 py-3.5 text-sm font-bold transition-transform hover:scale-105', isEditorial ? '' : 'shadow-xl', radiusMd, theme.heroCta]"
                >{{ block.cta_text }}</component>
                <component
                    :is="tag(block.cta2_link)"
                    v-if="hasSecond"
                    :href="interactive && block.cta2_link ? resolveUrl(block.cta2_link) : undefined"
                    :class="['inline-block border-2 border-current/60 px-7 py-3.5 text-sm font-bold uppercase tracking-wider transition-all hover:border-current hover:bg-white/10', radiusMd]"
                >{{ block.cta2_text }}</component>
            </div>
        </div>
    </section>
</template>
