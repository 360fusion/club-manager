<script setup>
// Edit form for the Call-to-action banner block. Mutates `block` in place, like the inline forms in PageList.vue.
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <label :for="`block-${index}-cta-eyebrow`" :class="LABEL">Small label <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-cta-eyebrow`" v-model="block.eyebrow" type="text" maxlength="80" placeholder="e.g. Join us" :class="INPUT" />
            </div>
            <div class="sm:col-span-2">
                <label :for="`block-${index}-cta-heading`" :class="LABEL">Heading</label>
                <input :id="`block-${index}-cta-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Interested in becoming a member?" :class="[INPUT, 'font-bold']" />
            </div>
        </div>

        <div>
            <label :for="`block-${index}-cta-text`" :class="LABEL">Text <span class="font-normal text-slate-400">(optional, plain text)</span></label>
            <textarea :id="`block-${index}-cta-text`" v-model="block.text" rows="2" maxlength="500" :class="[INPUT, 'font-medium text-xs']"></textarea>
        </div>

        <div :class="[CARD, 'space-y-3']">
            <p :class="LABEL">Main button</p>
            <input v-model="block.button_label" type="text" maxlength="80" placeholder="Label, e.g. Get in touch" :class="[SMALL_INPUT, 'font-bold']" aria-label="Main button label" />
            <LinkField v-model="block.button_url" :pages="pages" :club="club" label="Main button" />
            <label :class="CHECK_LABEL"><input v-model="block.button_new_tab" type="checkbox" :class="CHECK" /> Open in a new tab</label>
        </div>

        <div :class="[CARD, 'space-y-3']">
            <p :class="LABEL">Second button <span class="font-normal text-slate-400">(optional, outlined)</span></p>
            <input v-model="block.button2_label" type="text" maxlength="80" placeholder="Label, e.g. Learn more" :class="[SMALL_INPUT, 'font-bold']" aria-label="Second button label" />
            <LinkField v-model="block.button2_url" :pages="pages" :club="club" label="Second button" />
            <label :class="CHECK_LABEL"><input v-model="block.button2_new_tab" type="checkbox" :class="CHECK" /> Open in a new tab</label>
        </div>

        <div :class="[CARD, 'grid grid-cols-2 sm:grid-cols-4 gap-3']">
            <div>
                <label :for="`block-${index}-cta-style`" :class="LABEL">Style</label>
                <select :id="`block-${index}-cta-style`" v-model="block.style" :class="SELECT">
                    <option value="bold">Bold theme banner</option>
                    <option value="soft">Soft card</option>
                    <option value="image">Photo background</option>
                    <option value="panel">Panel with a side photo</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-cta-align`" :class="LABEL">Alignment</label>
                <select :id="`block-${index}-cta-align`" v-model="block.align" :class="SELECT">
                    <option value="center">Centered</option>
                    <option value="left">Left aligned</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-cta-size`" :class="LABEL">Size</label>
                <select :id="`block-${index}-cta-size`" v-model="block.size" :class="SELECT">
                    <option value="normal">Normal</option>
                    <option value="compact">Compact</option>
                    <option value="large">Large</option>
                </select>
            </div>
            <div v-if="block.style === 'image'">
                <label :for="`block-${index}-cta-overlay`" :class="LABEL">Photo darkening</label>
                <select :id="`block-${index}-cta-overlay`" v-model="block.overlay" :class="SELECT">
                    <option value="light">Light</option>
                    <option value="medium">Medium</option>
                    <option value="strong">Strong</option>
                </select>
            </div>
        </div>

        <div v-if="block.style === 'panel'" :class="[CARD, 'space-y-3']">
            <div class="flex items-center justify-between">
                <label :for="`block-${index}-cta-side-image`" :class="LABEL">Side photo <span class="font-normal text-slate-400">(optional)</span></label>
                <div class="flex items-center gap-1.5">
                    <button v-if="block.side_image_url" type="button" :class="MINI_DANGER" @click="block.side_image_url = ''">🗑️ Clear</button>
                    <button type="button" :class="MINI_BUTTON" @click="$emit('media', 'cta_side_image')">📁 Media Library</button>
                </div>
            </div>
            <input :id="`block-${index}-cta-side-image`" v-model="block.side_image_url" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label :for="`block-${index}-cta-shape`" :class="LABEL">Photo shape</label>
                    <select :id="`block-${index}-cta-shape`" v-model="block.image_shape" :class="SELECT">
                        <option value="circle">Round</option>
                        <option value="rounded">Rounded square</option>
                        <option value="square">Square</option>
                    </select>
                </div>
                <div>
                    <label :for="`block-${index}-cta-side`" :class="LABEL">Photo position</label>
                    <select :id="`block-${index}-cta-side`" v-model="block.image_side" :class="SELECT">
                        <option value="left">Left</option>
                        <option value="right">Right</option>
                    </select>
                </div>
                <div>
                    <label :for="`block-${index}-cta-text-style`" :class="LABEL">Text style</label>
                    <select :id="`block-${index}-cta-text-style`" v-model="block.text_style" :class="SELECT">
                        <option value="normal">Normal</option>
                        <option value="italic">Italic</option>
                    </select>
                </div>
            </div>
        </div>

        <div v-if="block.style === 'image'">
            <div class="flex items-center justify-between mb-1">
                <label :for="`block-${index}-cta-image`" :class="LABEL">Background photo</label>
                <div class="flex items-center gap-1.5">
                    <button v-if="block.image_url" type="button" :class="MINI_DANGER" @click="block.image_url = ''">🗑️ Clear</button>
                    <button type="button" :class="MINI_BUTTON" @click="$emit('media', 'cta_image')">📁 Media Library</button>
                </div>
            </div>
            <input :id="`block-${index}-cta-image`" v-model="block.image_url" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
            <p :class="HINT">Without a photo the banner falls back to the plain theme banner.</p>
        </div>
    </div>
</template>
