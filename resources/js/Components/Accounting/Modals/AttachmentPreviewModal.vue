<script setup>
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    deleteAttachment: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resetZoom: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    rotateImage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    rotation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    zoomIn: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    zoomLevel: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    zoomOut: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <!-- A full-screen dark viewer, wider than the kit Modal allows, so it keeps its own frame. -->
    <div
      class="fixed inset-0 z-50 flex flex-col bg-slate-950/90 p-4 text-white backdrop-blur-md sm:p-6"
      @click="$emit('close')"
    >
      <div
        class="mx-auto flex w-full max-w-6xl flex-1 flex-col overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl dark:bg-slate-700"
        @click.stop
      >
        <!-- Toolbar -->
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-slate-900/90 px-6 py-4">
          <div class="flex items-center gap-3">
            <span class="text-slate-400"><UiIcon name="document" :size="20" /></span>
            <div>
              <h3 class="text-sm font-semibold text-white">{{ previewModal.title }}</h3>
              <p class="font-mono text-xs text-slate-400">{{ previewModal.fileName }}</p>
            </div>
          </div>

          <!-- Controls -->
          <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
            <!-- Zoom controls -->
            <div class="flex items-center rounded-xl border border-slate-700 bg-slate-800 p-1">
              <button
                type="button"
                title="Zoom out"
                aria-label="Zoom out"
                class="cursor-pointer rounded-xl px-2.5 py-1 text-slate-200 transition-colors hover:bg-slate-700"
                @click="zoomOut"
              >
                −
              </button>
              <span class="w-12 px-2.5 text-center text-xs tabular-nums text-blue-400">
                {{ Math.round(zoomLevel * 100) }}%
              </span>
              <button
                type="button"
                title="Zoom in"
                aria-label="Zoom in"
                class="cursor-pointer rounded-xl px-2.5 py-1 text-slate-200 transition-colors hover:bg-slate-700"
                @click="zoomIn"
              >
                +
              </button>
              <button
                type="button"
                title="Reset the zoom"
                class="ml-1 cursor-pointer rounded-xl border-l border-slate-700 px-2.5 py-1 text-xs text-slate-300 transition-colors hover:bg-slate-700"
                @click="resetZoom"
              >
                Reset
              </button>
            </div>

            <!-- Rotate control (image only) -->
            <button
              v-if="!previewModal.mimeType.includes('pdf')"
              type="button"
              title="Rotate the image"
              class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-slate-200 transition-colors hover:bg-slate-700"
              @click="rotateImage"
            >
              <UiIcon name="refresh" />
              Rotate
            </button>

            <!-- Open in a new tab -->
            <a
              :href="previewModal.url"
              target="_blank"
              class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-slate-200 transition-colors hover:bg-slate-700"
            >
              <UiIcon name="external-link" />
              New tab
            </a>

            <!-- Delete the attachment -->
            <button
              type="button"
              class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-transparent bg-rose-600 px-3 py-1.5 text-white transition-colors hover:bg-rose-500"
              @click="deleteAttachment"
            >
              <UiIcon name="trash" />
              Delete
            </button>

            <!-- Close -->
            <button
              type="button"
              aria-label="Close"
              class="ml-2 cursor-pointer rounded-xl bg-slate-800 p-1.5 text-slate-400 transition-colors hover:bg-slate-700 hover:text-white"
              @click="$emit('close')"
            >
              <UiIcon name="x" />
            </button>
          </div>
        </div>

        <!-- Document or image viewer -->
        <div class="relative flex flex-1 items-center justify-center overflow-auto bg-slate-950 p-6">
          <!-- Image view -->
          <div
            v-if="!previewModal.mimeType.includes('pdf')"
            class="flex origin-center items-center justify-center transition-transform duration-150 ease-out"
            :style="{ transform: `scale(${zoomLevel}) rotate(${rotation}deg)` }"
          >
            <img
              :src="previewModal.url"
              :alt="previewModal.title"
              class="max-h-[75vh] max-w-full rounded-xl border border-slate-800 object-contain shadow-2xl"
            />
          </div>

          <!-- PDF view -->
          <iframe
            v-else
            :src="previewModal.url"
            class="h-[75vh] w-full origin-center rounded-2xl border border-slate-800 bg-white shadow-2xl transition-transform duration-150 ease-out dark:bg-slate-900"
            :style="{ transform: `scale(${zoomLevel})` }"
          ></iframe>
        </div>
      </div>
    </div>
</template>
