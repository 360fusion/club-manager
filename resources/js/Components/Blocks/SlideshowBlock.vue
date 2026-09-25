<script setup>
// Slideshow: photos that crossfade, with optional slow zoom, dots, arrows, swipe and autoplay, and optional text
// and buttons laid over them (which makes it a hero). Autoplay stops for anyone who prefers reduced motion, while
// the pointer or focus is inside, when the tab is hidden or the slideshow is off screen, and can be paused by
// the visitor. The builder's preview (interactive false) shows the slides but never moves on its own.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusLg: { type: String, default: 'rounded-3xl' },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const HEIGHTS = {
    compact: 'min-h-[280px] sm:min-h-[340px]',
    normal: 'min-h-[360px] sm:min-h-[480px]',
    tall: 'min-h-[440px] sm:min-h-[640px]',
    screen: 'min-h-[calc(100svh-6rem)]',
};
const OVERLAYS = {
    none: '',
    light: 'bg-gradient-to-r from-black/30 via-black/20 to-black/10',
    medium: 'bg-gradient-to-r from-[var(--cm-primary)]/85 via-[var(--cm-primary)]/65 to-[var(--cm-primary)]/45',
    strong: 'bg-gradient-to-r from-[var(--cm-primary)]/95 via-[var(--cm-primary)]/80 to-[var(--cm-primary)]/65',
};
const ZOOMS = ['cm-zoom-a', 'cm-zoom-b', 'cm-zoom-c'];

const slides = computed(() => (Array.isArray(props.block.slides) ? props.block.slides : []).filter((s) => s.image_url));
const count = computed(() => slides.value.length);
const multiple = computed(() => count.value > 1);
const current = ref(0);
const paused = ref(false);
const held = ref(false);
const onScreen = ref(true);
const hidden = ref(false);
const reducedMotion = ref(false);
const root = ref(null);

const centered = computed(() => props.block.align === 'center');
const edge = computed(() => !!props.block.full_width);
const hasText = computed(() => !!(props.block.eyebrow || props.block.heading || props.block.text || hasPrimary.value || hasSecondary.value));
const hasPrimary = computed(() => !!(props.block.button_label && (props.block.button_url || !props.interactive)));
const hasSecondary = computed(() => !!(props.block.button2_label && (props.block.button2_url || !props.interactive)));
const zoom = computed(() => props.block.effect === 'zoom' && !reducedMotion.value);
const seconds = computed(() => Math.min(30, Math.max(2, Number(props.block.interval) || 5)));
const autoplay = computed(() => props.interactive && props.block.autoplay !== false && multiple.value && !reducedMotion.value);
const running = computed(() => autoplay.value && !paused.value && !held.value && onScreen.value && !hidden.value);
const caption = computed(() => slides.value[current.value]?.caption || '');

const go = (index) => { if (count.value) current.value = (index + count.value) % count.value; };
const next = () => go(current.value + 1);
const prev = () => go(current.value - 1);

// ---- autoplay ---------------------------------------------------------------------------------------------------
let timer = null;
const stop = () => { clearInterval(timer); timer = null; };
const start = () => { stop(); if (running.value) timer = setInterval(next, seconds.value * 1000); };
watch([running, seconds, current], start);

// ---- swipe ------------------------------------------------------------------------------------------------------
let startX = null;
const down = (e) => { startX = e.clientX; };
const up = (e) => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 50) (dx < 0 ? next : prev)();
};

let observer = null;
const onVisibility = () => { hidden.value = document.hidden; };

onMounted(() => {
    reducedMotion.value = !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    document.addEventListener('visibilitychange', onVisibility);
    if ('IntersectionObserver' in window && root.value) {
        observer = new IntersectionObserver(([entry]) => { onScreen.value = entry.isIntersecting; }, { threshold: 0.2 });
        observer.observe(root.value);
    }
    start();
});
onBeforeUnmount(() => {
    stop();
    observer?.disconnect();
    document.removeEventListener('visibilitychange', onVisibility);
});
watch(count, (n) => { if (current.value >= n) current.value = 0; });

const tag = (link) => (props.interactive && link ? 'a' : 'span');
</script>

<template>
    <section
        v-if="count"
        ref="root"
        role="region"
        aria-roledescription="carousel"
        :aria-label="block.heading || 'Photo slideshow'"
        :class="['group/slides relative flex w-full items-center overflow-hidden bg-[var(--cm-primary)] text-white', HEIGHTS[block.height] || HEIGHTS.normal, edge ? '' : radiusLg]"
        @mouseenter="block.pause_on_hover !== false && (held = true)"
        @mouseleave="held = false"
        @focusin="held = true"
        @focusout="held = false"
        @pointerdown="down"
        @pointerup="up"
    >
        <div class="absolute inset-0" aria-live="off">
            <div
                v-for="(slide, i) in slides"
                :key="slide.id || i"
                role="group"
                aria-roledescription="slide"
                :aria-label="`${i + 1} of ${count}`"
                :aria-hidden="i !== current"
                :class="['absolute inset-0 transition-opacity duration-1000 ease-in-out', i === current ? 'z-[1] opacity-100' : 'opacity-0']"
            >
                <img :src="slide.image_url" :alt="slide.alt || ''" :loading="i === 0 ? 'eager' : 'lazy'" draggable="false" :class="['h-full w-full select-none object-cover', zoom && i === current ? ZOOMS[i % 3] : '']" />
            </div>
        </div>
        <div v-if="block.overlay !== 'none'" :class="['pointer-events-none absolute inset-0 z-[2]', OVERLAYS[block.overlay] || OVERLAYS.medium]"></div>

        <div v-if="hasText" :class="['relative z-[3] mx-auto w-full max-w-7xl px-6 py-16 sm:px-12', centered ? 'text-center' : 'text-left']">
            <div :class="['max-w-3xl space-y-5', centered ? 'mx-auto' : '']">
                <p v-if="block.eyebrow" class="text-xs font-semibold uppercase tracking-[0.3em] text-[var(--cm-accent-bright)]">{{ block.eyebrow }}</p>
                <h2 v-if="block.heading" :class="['text-4xl font-bold leading-tight sm:text-6xl', theme.headingFont]">{{ block.heading }}</h2>
                <p v-if="block.text" class="whitespace-pre-line text-lg leading-relaxed opacity-90 sm:text-xl">{{ block.text }}</p>
                <div v-if="hasPrimary || hasSecondary" :class="['flex flex-wrap gap-3 pt-2', centered ? 'justify-center' : 'justify-start']">
                    <component :is="tag(block.button_url)" v-if="hasPrimary" :href="interactive ? resolveUrl(block.button_url) : undefined" :class="['inline-block bg-[var(--cm-accent-bright)] px-8 py-3.5 text-sm font-semibold uppercase tracking-[0.1em] text-[var(--cm-on-accent-bright)] shadow-lg transition-all hover:-translate-y-0.5 hover:brightness-110', radiusMd]">{{ block.button_label }}</component>
                    <component :is="tag(block.button2_url)" v-if="hasSecondary" :href="interactive ? resolveUrl(block.button2_url) : undefined" :class="['inline-block border-2 border-white/70 px-8 py-3.5 text-sm font-semibold uppercase tracking-[0.1em] text-white transition-all hover:bg-white/10', radiusMd]">{{ block.button2_label }}</component>
                </div>
            </div>
        </div>

        <p v-if="caption" class="absolute bottom-14 left-6 z-[3] max-w-[70%] rounded bg-black/45 px-3 py-1.5 text-sm sm:left-12">{{ caption }}</p>

        <template v-if="multiple">
            <button v-if="block.show_arrows !== false" type="button" aria-label="Previous slide" class="absolute left-3 top-1/2 z-[4] flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/35 text-white opacity-80 transition hover:bg-black/60 hover:opacity-100 focus-visible:opacity-100 sm:left-5" @click="prev">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
            </button>
            <button v-if="block.show_arrows !== false" type="button" aria-label="Next slide" class="absolute right-3 top-1/2 z-[4] flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/35 text-white opacity-80 transition hover:bg-black/60 hover:opacity-100 focus-visible:opacity-100 sm:right-5" @click="next">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7" /></svg>
            </button>

            <div v-if="block.show_dots !== false" class="absolute inset-x-0 bottom-5 z-[4] flex justify-center gap-3">
                <button
                    v-for="(slide, i) in slides"
                    :key="slide.id || i"
                    type="button"
                    :aria-label="`Go to slide ${i + 1}`"
                    :aria-current="i === current ? 'true' : undefined"
                    :class="['h-3 w-3 cursor-pointer rounded-full border-2 transition-all', i === current ? 'scale-110 border-[var(--cm-accent)] bg-[var(--cm-accent)]' : 'border-white/70 bg-transparent hover:border-[var(--cm-accent)]']"
                    @click="go(i)"
                ></button>
            </div>

            <button v-if="autoplay" type="button" :aria-label="paused ? 'Play slideshow' : 'Pause slideshow'" class="absolute bottom-3.5 right-4 z-[4] flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-black/35 text-white transition hover:bg-black/60 sm:right-6" @click="paused = !paused">
                <svg v-if="!paused" viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z" /></svg>
                <svg v-else viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor" aria-hidden="true"><path d="M7 4l13 8-13 8z" /></svg>
            </button>
        </template>
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-5xl border-2 border-dashed p-8 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add photos to show the slideshow here
    </section>
</template>
