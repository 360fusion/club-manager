<script setup>
import { nextTick, ref, watch } from 'vue';
import MediaBrowser from './MediaBrowser.vue';

// A field that holds a media file's id: a preview, a Choose button, and the library in a dialog. The native <dialog> keeps focus inside and closes on Escape.
//   <MediaPicker v-model="fileId" :team="slug" folder="Pages" kind="image" />
const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    team: { type: String, required: true },
    folder: { type: String, default: '' },
    kind: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'picked']);

const dialog = ref(null);
const shown = ref(false);
const current = ref(null);

watch(() => props.modelValue, (id) => { if (!id) current.value = null; });

const open = async () => { shown.value = true; await nextTick(); dialog.value.showModal(); };
const pick = (file) => {
    current.value = file;
    emit('update:modelValue', file.id);
    emit('picked', file);
    dialog.value.close();
};
const clear = () => { current.value = null; emit('update:modelValue', null); };
</script>

<template>
    <div class="lp-media-picker">
        <p v-if="current">
            <img v-if="current.kind === 'image'" :src="current.url" :alt="current.alt ?? ''" width="48" height="48" style="object-fit:cover;vertical-align:middle;margin-right:8px">
            {{ current.name }}
        </p>
        <p v-else class="lp-muted">{{ modelValue ? $t('A file is chosen.') : $t('Nothing chosen.') }}</p>
        <button class="lp-btn lp-btn--quiet" type="button" aria-haspopup="dialog" @click="open">{{ $t('Choose') }}</button>
        <button v-if="modelValue" class="lp-btn lp-btn--quiet" type="button" @click="clear">{{ $t('Remove') }}</button>
        <dialog v-if="shown" ref="dialog" :aria-label="$t('Choose a file')" style="max-width:720px;width:92vw" @close="shown = false">
            <MediaBrowser :team="team" mode="pick" :kind="kind" :upload-folder="folder" @pick="pick" />
            <button class="lp-btn lp-btn--quiet" type="button" @click="dialog.close()">{{ $t('Close') }}</button>
        </dialog>
    </div>
</template>
