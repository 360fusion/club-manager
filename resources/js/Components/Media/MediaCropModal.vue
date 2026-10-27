<script setup>
import { ref } from 'vue';

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
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4 overflow-y-auto" @click="closeCropper">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-6 relative overflow-hidden" @click.stop>
        <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <span class="text-2xl">✂️</span>
            <div>
              <h3 class="text-lg font-black text-slate-900 dark:text-white">Interactive Image Cropper & Resizer</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Drag to move crop area or grab corner handles to adjust crop box size.</p>
            </div>
          </div>
          <button type="button" @click="closeCropper" class="p-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold rounded-xl text-sm cursor-pointer">✕</button>
        </div>

        <!-- Aspect Ratio Presets Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-2xl border border-slate-200 dark:border-slate-800">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Presets:</span>
            <button
              type="button"
              @click="setCropAspect('free')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === 'free' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              Free Crop
            </button>
            <button
              type="button"
              @click="setCropAspect('1:1')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '1:1' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              1:1 Square (Logo)
            </button>
            <button
              type="button"
              @click="setCropAspect('16:9')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '16:9' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              16:9 Banner
            </button>
            <button
              type="button"
              @click="setCropAspect('4:3')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '4:3' ? 'bg-slate-900 dark:bg-slate-700 text-white shadow' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              4:3 Gallery
            </button>
          </div>

          <button
            type="button"
            @click="rotateCropper(90)"
            class="px-3 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 transition-all cursor-pointer flex items-center gap-1"
          >
            🔄 Rotate 90°
          </button>
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

        <!-- Cropper Action Footer with Variant and Master Backup buttons -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
          <button
            type="button"
            @click="closeCropper"
            class="px-4 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition-all cursor-pointer"
          >
            Cancel
          </button>
          <div class="flex flex-wrap items-center gap-2">
            <button
              type="button"
              @click="applyCrop('variant')"
              :disabled="isCropping"
              class="px-4 py-2.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-blue-700 dark:text-blue-300 font-extrabold text-xs rounded-xl border border-blue-200 dark:border-blue-800/60 shadow-sm transition-all cursor-pointer flex items-center gap-1.5 disabled:opacity-50"
            >
              <span>➕ Save as New Copy (Variant)</span>
            </button>
            <button
              type="button"
              @click="applyCrop('replace')"
              :disabled="isCropping"
              class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-2 disabled:opacity-50"
            >
              <span v-if="isCropping" class="animate-spin text-sm">🔄</span>
              <span v-else>⚡</span>
              <span>{{ isCropping ? 'Cropping & Saving...' : 'Update & Keep Master Backup' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
</template>
