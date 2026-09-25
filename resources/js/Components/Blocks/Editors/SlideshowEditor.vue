<script setup>
// Edit form for the Slideshow block. Mutates `block` in place.
import LinkField from './LinkField.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    pages: { type: Array, default: () => [] },
    club: { type: Object, required: true },
});

defineEmits(['media']);

const MAX_SLIDES = 12;

if (!Array.isArray(props.block.slides)) props.block.slides = [];

const newId = () => `slide-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
const addSlide = () => {
    if (props.block.slides.length < MAX_SLIDES) props.block.slides.push({ id: newId(), image_url: '', alt: '', caption: '' });
};
const removeSlide = (i) => props.block.slides.splice(i, 1);
const move = (i, by) => {
    const j = i + by;
    if (j < 0 || j >= props.block.slides.length) return;
    const list = props.block.slides;
    [list[i], list[j]] = [list[j], list[i]];
};
</script>

<template>
    <div class="space-y-3 text-xs">
        <div v-for="(slide, i) in block.slides" :key="slide.id" :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-500">Photo {{ i + 1 }}</span>
                <div class="flex items-center gap-1.5">
                    <button type="button" :class="MINI_BUTTON" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">▲</button>
                    <button type="button" :class="MINI_BUTTON" :disabled="i === block.slides.length - 1" aria-label="Move down" @click="move(i, 1)">▼</button>
                    <button type="button" :class="MINI_DANGER" aria-label="Delete photo" @click="removeSlide(i)">🗑️</button>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-[10px] text-slate-400 dark:border-slate-700 dark:bg-slate-800">
                    <img v-if="slide.image_url" :src="slide.image_url" alt="" class="h-full w-full object-cover" />
                    <span v-else>No photo</span>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex items-center gap-1.5">
                        <input v-model="slide.image_url" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" :aria-label="`Photo ${i + 1} address`" />
                        <button type="button" :class="[MINI_BUTTON, 'shrink-0']" @click="$emit('media', 'slideshow_image', slide)">📁 Library</button>
                    </div>
                    <input v-model="slide.alt" type="text" maxlength="200" placeholder="Describe the photo (for screen readers)" :class="SMALL_INPUT" :aria-label="`Photo ${i + 1} description`" />
                    <input v-model="slide.caption" type="text" maxlength="200" placeholder="Caption shown on the photo (optional)" :class="SMALL_INPUT" :aria-label="`Photo ${i + 1} caption`" />
                </div>
            </div>
        </div>
        <button type="button" :disabled="block.slides.length >= MAX_SLIDES" class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:border-slate-300 disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300" @click="addSlide">➕ Add photo</button>
        <p :class="HINT">Up to {{ MAX_SLIDES }} photos. Photos without an address are dropped when you save.</p>

        <div :class="[CARD, 'grid grid-cols-2 gap-3 sm:grid-cols-4']">
            <div>
                <label :for="`block-${index}-ss-height`" :class="LABEL">Height</label>
                <select :id="`block-${index}-ss-height`" v-model="block.height" :class="SELECT">
                    <option value="compact">Compact</option>
                    <option value="normal">Standard</option>
                    <option value="tall">Tall</option>
                    <option value="screen">Full screen</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-ss-effect`" :class="LABEL">Movement</label>
                <select :id="`block-${index}-ss-effect`" v-model="block.effect" :class="SELECT">
                    <option value="fade">Fade</option>
                    <option value="zoom">Fade with slow zoom</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-ss-interval`" :class="LABEL">Seconds per photo</label>
                <input :id="`block-${index}-ss-interval`" v-model.number="block.interval" type="number" min="2" max="30" :class="SELECT" :disabled="block.autoplay === false" />
            </div>
            <div>
                <label :for="`block-${index}-ss-overlay`" :class="LABEL">Darkening</label>
                <select :id="`block-${index}-ss-overlay`" v-model="block.overlay" :class="SELECT">
                    <option value="none">None</option>
                    <option value="light">Light</option>
                    <option value="medium">Medium</option>
                    <option value="strong">Strong</option>
                </select>
            </div>
            <label :class="CHECK_LABEL"><input v-model="block.autoplay" type="checkbox" :class="CHECK" /> Change automatically</label>
            <label :class="CHECK_LABEL"><input v-model="block.pause_on_hover" type="checkbox" :class="CHECK" /> Pause under the pointer</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_dots" type="checkbox" :class="CHECK" /> Show dots</label>
            <label :class="CHECK_LABEL"><input v-model="block.show_arrows" type="checkbox" :class="CHECK" /> Show arrows</label>
            <label :class="[CHECK_LABEL, 'col-span-2 sm:col-span-4']"><input v-model="block.full_width" type="checkbox" :class="CHECK" /> Full width (edge to edge across the page)</label>
        </div>

        <div :class="[CARD, 'space-y-3']">
            <p :class="LABEL">Text over the photos <span class="font-normal text-slate-400">(optional, makes it a hero)</span></p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <input v-model="block.eyebrow" type="text" maxlength="80" placeholder="Small label" :class="SMALL_INPUT" aria-label="Small label" />
                <input v-model="block.heading" type="text" maxlength="200" placeholder="Heading" :class="[SMALL_INPUT, 'font-bold sm:col-span-2']" aria-label="Heading" />
            </div>
            <textarea v-model="block.text" rows="2" maxlength="500" placeholder="Text" :class="SMALL_INPUT" aria-label="Text over the photos"></textarea>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="space-y-2">
                    <input v-model="block.button_label" type="text" maxlength="80" placeholder="Main button label" :class="[SMALL_INPUT, 'font-bold']" aria-label="Main button label" />
                    <LinkField v-model="block.button_url" :pages="pages" :club="club" label="Main button" />
                </div>
                <div class="space-y-2">
                    <input v-model="block.button2_label" type="text" maxlength="80" placeholder="Second button label (outlined)" :class="[SMALL_INPUT, 'font-bold']" aria-label="Second button label" />
                    <LinkField v-model="block.button2_url" :pages="pages" :club="club" label="Second button" />
                </div>
            </div>
            <div>
                <label :for="`block-${index}-ss-align`" :class="LABEL">Text alignment</label>
                <select :id="`block-${index}-ss-align`" v-model="block.align" :class="[SELECT, 'max-w-[12rem]']">
                    <option value="left">Left aligned</option>
                    <option value="center">Centred</option>
                </select>
            </div>
        </div>
    </div>
</template>
