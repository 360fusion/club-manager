<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// The image cropper dialog of the media library page, in its own file so the page stays readable.
// The page builds the Cropper on this image once the modal is mounted.
const imageEl = ref(null);
defineExpose({ imageEl });

defineProps({
    applyCrop: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeCropper: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    cropAspect: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isCropping: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewItem: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    rotateCropper: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    setCropAspect: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Modal :open="true" title="Crop and resize image" subtitle="Drag to move the crop area, or drag a corner handle to resize it." size="xl" @close="closeCropper">
      <div class="space-y-4">
        <!-- Aspect ratio presets -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Presets</span>
            <button
              v-for="preset in [{ value: 'free', label: 'Free crop' }, { value: '1:1', label: '1:1 square (logo)' }, { value: '16:9', label: '16:9 banner' }, { value: '4:3', label: '4:3 gallery' }]"
              :key="preset.value"
              type="button"
              :aria-pressed="cropAspect === preset.value"
              @click="setCropAspect(preset.value)"
              :class="[
                'rounded-xl border px-3 py-1.5 text-xs font-medium transition-colors',
                cropAspect === preset.value
                  ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-800/60 dark:bg-blue-950/60 dark:text-blue-300'
                  : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800'
              ]"
            >
              {{ preset.label }}
            </button>
          </div>

          <Button variant="secondary" size="sm" @click="rotateCropper(90)">
            <template #icon><UiIcon name="refresh" /></template>
            Rotate 90°
          </Button>
        </div>

        <!-- Canvas / Image Container for CropperJS -->
        <div class="bg-slate-950 rounded-2xl p-2 h-[420px] w-full overflow-hidden relative border border-slate-800">
          <img
            ref="imageEl"
            :src="previewItem?.original_url"
            alt="Source image for cropping"
            class="max-w-full block"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <Button variant="secondary" @click="closeCropper">Cancel</Button>
          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" :disabled="isCropping" @click="applyCrop('variant')">
              <template #icon><UiIcon name="plus" /></template>
              Save as a new copy
            </Button>
            <Button :loading="isCropping" @click="applyCrop('replace')">
              <template v-if="!isCropping" #icon><UiIcon name="crop" /></template>
              {{ isCropping ? 'Cropping and saving…' : 'Replace and keep a backup' }}
            </Button>
          </div>
        </div>
      </template>
    </Modal>
</template>
