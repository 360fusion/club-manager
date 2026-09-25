<script setup>
// Edit form for the Hero Banner. Mutates `block` in place.
import LinkField from './LinkField.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    pages: { type: Array, default: () => [] },
    club: { type: Object, required: true },
});

defineEmits(['media']);
</script>

<template>
    <div class="space-y-3 text-xs">
        <div>
            <label :for="`block-${index}-hero-title`" :class="LABEL">Banner Title</label>
            <input :id="`block-${index}-hero-title`" v-model="block.title" placeholder="Hero Title" :class="[INPUT, 'font-semibold']" />
        </div>
        <div>
            <label :for="`block-${index}-hero-subtitle`" :class="LABEL">Banner Subtitle</label>
            <input :id="`block-${index}-hero-subtitle`" v-model="block.subtitle" placeholder="Hero Subtitle" :class="INPUT" />
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label :for="`block-${index}-hero-cta-text`" :class="LABEL">CTA Button Text</label>
                <input :id="`block-${index}-hero-cta-text`" v-model="block.cta_text" placeholder="e.g. Join Us" :class="[INPUT, 'font-semibold']" />
            </div>
            <div>
                <span :class="LABEL">CTA Button Target</span>
                <LinkField v-model="block.cta_link" :pages="pages" :club="club" label="Hero button" />
            </div>
            <div>
                <label :for="`block-${index}-hero-cta2-text`" :class="LABEL">Second button <span class="font-normal text-slate-400">(optional, outlined)</span></label>
                <input :id="`block-${index}-hero-cta2-text`" v-model="block.cta2_text" maxlength="80" placeholder="e.g. Discover more" :class="[INPUT, 'font-semibold']" />
            </div>
            <div>
                <span :class="LABEL">Second button target</span>
                <LinkField v-model="block.cta2_link" :pages="pages" :club="club" label="Second hero button" />
            </div>
        </div>

        <div :class="[CARD, 'grid grid-cols-2 items-end gap-3 sm:grid-cols-4']">
            <div class="col-span-2">
                <label :for="`block-${index}-hero-eyebrow`" :class="LABEL">Small label above the title</label>
                <input :id="`block-${index}-hero-eyebrow`" v-model="block.eyebrow" maxlength="80" placeholder="Official Club Website" :class="SMALL_INPUT" :disabled="block.hide_eyebrow" />
            </div>
            <label :class="[CHECK_LABEL, 'col-span-2 pb-2']"><input v-model="block.hide_eyebrow" type="checkbox" :class="CHECK" /> Hide the label</label>
            <div>
                <label :for="`block-${index}-hero-align`" :class="LABEL">Alignment</label>
                <select :id="`block-${index}-hero-align`" v-model="block.align" :class="SELECT">
                    <option value="auto">Theme default</option>
                    <option value="center">Centred</option>
                    <option value="left">Left aligned</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-hero-height`" :class="LABEL">Height</label>
                <select :id="`block-${index}-hero-height`" v-model="block.height" :class="SELECT">
                    <option value="normal">Standard</option>
                    <option value="compact">Compact</option>
                    <option value="tall">Tall</option>
                </select>
            </div>
            <div v-if="block.image_url">
                <label :for="`block-${index}-hero-overlay`" :class="LABEL">Photo darkening</label>
                <select :id="`block-${index}-hero-overlay`" v-model="block.overlay" :class="SELECT">
                    <option value="light">Light</option>
                    <option value="medium">Medium</option>
                    <option value="strong">Strong</option>
                </select>
            </div>
        </div>

        <div>
            <div class="mb-1 flex items-center justify-between">
                <label :for="`block-${index}-hero-image`" :class="LABEL">Background photo <span class="font-normal text-slate-400">(optional)</span></label>
                <div class="flex items-center gap-1.5">
                    <button v-if="block.image_url" type="button" :class="MINI_DANGER" @click="block.image_url = ''">🗑️ Clear</button>
                    <button type="button" :class="MINI_BUTTON" @click="$emit('media', 'hero_image')">📁 Media Library</button>
                </div>
            </div>
            <input :id="`block-${index}-hero-image`" v-model="block.image_url" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
            <p :class="HINT">Without a photo the banner uses the theme's own hero colours.</p>
        </div>
    </div>
</template>
