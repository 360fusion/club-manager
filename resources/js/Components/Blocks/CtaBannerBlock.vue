<script setup>
// Call-to-action banner: a heading, short text and up to two buttons, on a themed band, a soft card or a photo.
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
const PADDING = { compact: 'py-8 px-6 sm:px-10', normal: 'py-12 px-6 sm:px-12', large: 'py-20 px-6 sm:px-16' };

const isImage = computed(() => props.block.style === 'image' && !!props.block.image_url);
const isSoft = computed(() => props.block.style === 'soft');
const centered = computed(() => props.block.align !== 'left');
const hasPrimary = computed(() => !!(props.block.button_label && props.block.button_url));
const hasSecondary = computed(() => !!(props.block.button2_label && props.block.button2_url));
const hasContent = computed(() => !!(props.block.heading || props.block.text || props.block.eyebrow || hasPrimary.value || hasSecondary.value));

const wrapperClass = computed(() => {
    if (isImage.value) return ['relative overflow-hidden text-white', props.radiusLg];
    return ['border', props.radiusLg, isSoft.value ? props.theme.cardBg : props.theme.heroBg];
});

const secondaryClass = computed(() => (isImage.value
    ? 'border-2 border-white/70 text-white hover:bg-white/10'
    : 'border-2 border-current/40 hover:border-current'));
</script>

<template>
    <section v-if="hasContent" class="max-w-5xl mx-auto">
        <div :class="wrapperClass">
            <img v-if="isImage" :src="block.image_url" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover" />
            <div v-if="isImage" :class="['absolute inset-0', OVERLAY[block.overlay] || OVERLAY.medium]"></div>

            <div :class="['relative space-y-4', PADDING[block.size] || PADDING.normal, centered ? 'text-center' : 'text-left']">
                <div v-if="block.eyebrow" :class="['inline-block px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider border', isImage ? 'border-white/40 text-white' : theme.heroPill]">{{ block.eyebrow }}</div>
                <h2 v-if="block.heading" :class="['font-black leading-tight', block.size === 'large' ? 'text-4xl sm:text-5xl' : block.size === 'compact' ? 'text-2xl sm:text-3xl' : 'text-3xl sm:text-4xl', isSoft ? theme.headingText : '']">{{ block.heading }}</h2>
                <p v-if="block.text" :class="['text-base sm:text-lg leading-relaxed whitespace-pre-line max-w-3xl', centered ? 'mx-auto' : '', isSoft ? theme.bodyText : 'opacity-90']">{{ block.text }}</p>

                <div v-if="hasPrimary || hasSecondary" :class="['flex flex-wrap gap-3 pt-2', centered ? 'justify-center' : 'justify-start']">
                    <component
                        :is="interactive ? 'a' : 'span'"
                        v-if="hasPrimary"
                        :href="interactive ? resolveUrl(block.button_url) : undefined"
                        :target="block.button_new_tab ? '_blank' : undefined"
                        :rel="block.button_new_tab ? 'noopener' : undefined"
                        :class="['inline-block py-3 px-7 font-bold text-sm shadow-xl hover:scale-105 transition-transform', radiusMd, theme.heroCta]"
                    >{{ block.button_label }}</component>
                    <component
                        :is="interactive ? 'a' : 'span'"
                        v-if="hasSecondary"
                        :href="interactive ? resolveUrl(block.button2_url) : undefined"
                        :target="block.button2_new_tab ? '_blank' : undefined"
                        :rel="block.button2_new_tab ? 'noopener' : undefined"
                        :class="['inline-block py-3 px-7 font-bold text-sm transition-all', radiusMd, secondaryClass]"
                    >{{ block.button2_label }}</component>
                </div>
            </div>
        </div>
    </section>
    <section v-else-if="!interactive" :class="['max-w-5xl mx-auto border-2 border-dashed p-8 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add a heading, text or a button to show this banner
    </section>
</template>
