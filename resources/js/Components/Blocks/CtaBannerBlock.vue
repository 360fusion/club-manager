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
// "Panel": a navy card with a gold edge and a photo (round by default) beside the text.
const isPanel = computed(() => props.block.style === 'panel');
const IMAGE_SHAPE = { circle: 'rounded-full', rounded: 'rounded-2xl', square: 'rounded-none' };
const panelImageClass = computed(() => ['h-36 w-36 shrink-0 border-[3px] border-[var(--cm-accent)] object-cover shadow-lg sm:h-44 sm:w-44', IMAGE_SHAPE[props.block.image_shape] || IMAGE_SHAPE.circle]);
const centered = computed(() => props.block.align !== 'left');
const hasPrimary = computed(() => !!(props.block.button_label && props.block.button_url));
const hasSecondary = computed(() => !!(props.block.button2_label && props.block.button2_url));
const hasContent = computed(() => !!(props.block.heading || props.block.text || props.block.eyebrow || hasPrimary.value || hasSecondary.value));

const wrapperClass = computed(() => {
    if (isImage.value) return ['relative overflow-hidden text-white', props.radiusLg];
    return ['border', props.radiusLg, isSoft.value ? props.theme.cardBg : props.theme.heroBg];
});

const secondaryClass = computed(() => (isImage.value || isPanel.value
    ? 'border-2 border-white/70 text-white hover:bg-white/10'
    : 'border-2 border-current/40 hover:border-current'));
</script>

<template>
    <section v-if="hasContent && isPanel" class="max-w-5xl mx-auto">
        <div :class="['relative overflow-hidden border-l-[6px] border-l-[color:var(--cm-accent)] bg-gradient-to-br from-[var(--cm-primary)] to-[var(--cm-primary-soft)] text-white shadow-[0_20px_50px_rgba(0,0,0,0.18)]', radiusMd]">
            <div :class="['flex flex-col items-center gap-8 px-6 py-10 sm:px-12 md:gap-12', block.image_side === 'right' ? 'md:flex-row-reverse' : 'md:flex-row']">
                <img v-if="block.side_image_url" :src="block.side_image_url" alt="" loading="lazy" :class="panelImageClass" />
                <div :class="['min-w-0 flex-1 space-y-4', block.side_image_url ? 'text-center md:text-left' : (centered ? 'text-center' : 'text-left')]">
                    <div v-if="block.eyebrow" :class="['inline-block border px-3 py-1 text-xs font-semibold uppercase tracking-[0.25em]', theme.heroPill]">{{ block.eyebrow }}</div>
                    <h2 v-if="block.heading" class="font-serif text-2xl font-semibold leading-tight sm:text-3xl">{{ block.heading }}</h2>
                    <p v-if="block.text" :class="['text-base leading-8 opacity-90 sm:text-lg', block.text_style === 'italic' ? 'italic' : '']">{{ block.text }}</p>
                    <div v-if="hasPrimary || hasSecondary" :class="['flex flex-wrap gap-3 pt-2', block.side_image_url ? 'justify-center md:justify-start' : (centered ? 'justify-center' : 'justify-start')]">
                        <component
                            :is="interactive ? 'a' : 'span'"
                            v-if="hasPrimary"
                            :href="interactive ? resolveUrl(block.button_url) : undefined"
                            :target="block.button_new_tab ? '_blank' : undefined"
                            :rel="block.button_new_tab ? 'noopener' : undefined"
                            :class="['inline-block bg-[var(--cm-accent-bright)] px-9 py-3.5 text-sm font-semibold uppercase tracking-[0.1em] text-[var(--cm-on-accent-bright)] shadow-lg transition-all hover:-translate-y-0.5 hover:brightness-110', radiusMd]"
                        >{{ block.button_label }}</component>
                        <component
                            :is="interactive ? 'a' : 'span'"
                            v-if="hasSecondary"
                            :href="interactive ? resolveUrl(block.button2_url) : undefined"
                            :target="block.button2_new_tab ? '_blank' : undefined"
                            :rel="block.button2_new_tab ? 'noopener' : undefined"
                            :class="['inline-block px-9 py-3.5 text-sm font-semibold transition-all', radiusMd, secondaryClass]"
                        >{{ block.button2_label }}</component>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section v-else-if="hasContent" class="max-w-5xl mx-auto">
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
