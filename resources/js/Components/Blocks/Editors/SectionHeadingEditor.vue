<script setup>
// Edit form for the Section Heading block. Mutates `block` in place.
import { LABEL, INPUT, SELECT, CARD, CHECK, CHECK_LABEL } from './styles';

defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
});
</script>

<template>
    <div class="space-y-3 text-xs">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label :for="`block-${index}-sh-eyebrow`" :class="LABEL">Small label <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-sh-eyebrow`" v-model="block.eyebrow" type="text" maxlength="80" placeholder="e.g. About us" :class="INPUT" />
            </div>
            <div class="sm:col-span-2">
                <label :for="`block-${index}-sh-title`" :class="LABEL">Title</label>
                <input :id="`block-${index}-sh-title`" v-model="block.title" type="text" maxlength="200" placeholder="e.g. A tradition of fellowship" :class="[INPUT, 'font-bold']" />
            </div>
        </div>
        <div>
            <label :for="`block-${index}-sh-intro`" :class="LABEL">Introduction <span class="font-normal text-slate-400">(optional)</span></label>
            <textarea :id="`block-${index}-sh-intro`" v-model="block.intro" rows="2" maxlength="500" :class="[INPUT, 'font-medium text-xs']"></textarea>
        </div>
        <div :class="[CARD, 'grid grid-cols-1 items-end gap-3 sm:grid-cols-2']">
            <div>
                <label :for="`block-${index}-sh-align`" :class="LABEL">Alignment</label>
                <select :id="`block-${index}-sh-align`" v-model="block.align" :class="SELECT">
                    <option value="center">Centred</option>
                    <option value="left">Left aligned</option>
                </select>
            </div>
            <label :class="[CHECK_LABEL, 'pb-2']"><input v-model="block.show_divider" type="checkbox" :class="CHECK" /> Gold divider</label>
        </div>
    </div>
</template>
