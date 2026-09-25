<script setup>
// Edit form for the Motto block. Mutates `block` in place.
import { LABEL, INPUT, CARD, CHECK, CHECK_LABEL, HINT } from './styles';

defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
});
</script>

<template>
    <div class="space-y-3 text-xs">
        <div>
            <label :for="`block-${index}-motto-heading`" :class="LABEL">Heading</label>
            <input :id="`block-${index}-motto-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Fraternus Amor Maneto" :class="[INPUT, 'font-bold']" />
        </div>
        <div>
            <label :for="`block-${index}-motto-text`" :class="LABEL">Text <span class="font-normal text-slate-400">(optional, plain text)</span></label>
            <textarea :id="`block-${index}-motto-text`" v-model="block.text" rows="4" maxlength="1500" :class="[INPUT, 'font-medium text-xs']"></textarea>
            <p :class="HINT">A new line starts a new paragraph.</p>
        </div>
        <div>
            <label :for="`block-${index}-motto-tagline`" :class="LABEL">Italic tagline <span class="font-normal text-slate-400">(optional)</span></label>
            <input :id="`block-${index}-motto-tagline`" v-model="block.tagline" type="text" maxlength="200" placeholder="e.g. Brotherly Love Remains." :class="INPUT" />
        </div>
        <div :class="CARD">
            <label :class="CHECK_LABEL"><input v-model="block.show_divider" type="checkbox" :class="CHECK" /> Gold divider under the heading</label>
        </div>
    </div>
</template>
