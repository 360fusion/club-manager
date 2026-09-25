<script setup>
// Edit form for the Image & Text block. Mutates `block` in place.
import RichTextEditor from '@/Components/RichTextEditor.vue';
import LinkField from './LinkField.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER } from './styles';

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
        <div :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <label :for="`block-${index}-mt-image`" :class="LABEL">Photo</label>
                <div class="flex items-center gap-1.5">
                    <button v-if="block.image_url" type="button" :class="MINI_DANGER" @click="block.image_url = ''">🗑️ Clear</button>
                    <button type="button" :class="MINI_BUTTON" @click="$emit('media', 'media_text_image', block)">📁 Media Library</button>
                </div>
            </div>
            <div class="flex gap-3">
                <div class="flex h-20 w-28 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 text-[10px] text-slate-400 dark:border-slate-700 dark:bg-slate-800">
                    <img v-if="block.image_url" :src="block.image_url" alt="" class="h-full w-full object-cover" />
                    <span v-else>No photo</span>
                </div>
                <div class="min-w-0 flex-1 space-y-2">
                    <input :id="`block-${index}-mt-image`" v-model="block.image_url" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
                    <input v-model="block.image_alt" type="text" maxlength="200" placeholder="Describe the photo (for screen readers)" :class="SMALL_INPUT" aria-label="Photo description" />
                </div>
            </div>
            <div>
                <label :for="`block-${index}-mt-layout`" :class="LABEL">Photo position</label>
                <select :id="`block-${index}-mt-layout`" v-model="block.layout" :class="[SELECT, 'max-w-[14rem]']">
                    <option value="image_left">Photo on the left</option>
                    <option value="image_right">Photo on the right</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label :for="`block-${index}-mt-eyebrow`" :class="LABEL">Small label <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-mt-eyebrow`" v-model="block.eyebrow" type="text" maxlength="80" placeholder="e.g. About us" :class="INPUT" />
            </div>
            <div class="sm:col-span-2">
                <label :for="`block-${index}-mt-title`" :class="LABEL">Title</label>
                <input :id="`block-${index}-mt-title`" v-model="block.title" type="text" maxlength="200" :class="[INPUT, 'font-bold']" />
            </div>
        </div>
        <label :class="CHECK_LABEL"><input v-model="block.show_divider" type="checkbox" :class="CHECK" /> Short divider under the title</label>

        <div>
            <label :id="`block-${index}-mt-content-label`" :class="LABEL">Text</label>
            <RichTextEditor v-model="block.content" placeholder="Write formatted text here..." :aria-labelledby="`block-${index}-mt-content-label`" />
        </div>

        <div :class="[CARD, 'grid grid-cols-1 gap-3 sm:grid-cols-2']">
            <div>
                <label :for="`block-${index}-mt-button`" :class="LABEL">Button label <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-mt-button`" v-model="block.button_label" type="text" maxlength="80" :class="[SMALL_INPUT, 'font-bold']" />
            </div>
            <LinkField v-model="block.button_url" :pages="pages" :club="club" label="Button" />
        </div>
    </div>
</template>
