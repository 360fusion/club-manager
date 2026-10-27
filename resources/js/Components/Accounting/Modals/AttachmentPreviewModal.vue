<script setup>


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
    <div
      class="fixed inset-0 z-50 flex flex-col bg-slate-950/90 backdrop-blur-md p-4 sm:p-6 text-white"
      @click="$emit('close')"
    >
      <div
        class="flex-1 flex flex-col max-w-6xl w-full mx-auto bg-slate-900 dark:bg-slate-700 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden"
        @click.stop
      >
        <!-- Modal Toolbar Header -->
        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/90 flex flex-wrap items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3">
            <span class="text-xl">📎</span>
            <div>
              <h3 class="text-sm font-black text-white tracking-tight">{{ previewModal.title }}</h3>
              <p class="text-[11px] font-mono text-slate-400">{{ previewModal.fileName }}</p>
            </div>
          </div>

          <!-- Controls -->
          <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <!-- Zoom Controls -->
            <div class="flex items-center bg-slate-800 rounded-xl p-1 border border-slate-700">
              <button
                type="button"
                @click="zoomOut"
                title="Zoom Out"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                🔍−
              </button>
              <span class="px-2.5 font-mono text-[11px] text-blue-400 w-12 text-center">
                {{ Math.round(zoomLevel * 100) }}%
              </span>
              <button
                type="button"
                @click="zoomIn"
                title="Zoom In"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                🔍+
              </button>
              <button
                type="button"
                @click="resetZoom"
                title="Reset Zoom"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors cursor-pointer border-l border-slate-700 ml-1 text-[11px]"
              >
                ↺ Reset
              </button>
            </div>

            <!-- Rotate control (Image only) -->
            <button
              v-if="!previewModal.mimeType.includes('pdf')"
              type="button"
              @click="rotateImage"
              title="Rotate Image"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl transition-colors cursor-pointer flex items-center gap-1"
            >
              ↻ Rotate
            </button>

            <!-- Open in New Tab -->
            <a
              :href="previewModal.url"
              target="_blank"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl transition-colors cursor-pointer flex items-center gap-1"
            >
              ↗ New Tab
            </a>

            <!-- Delete Attachment -->
            <button
              type="button"
              @click="deleteAttachment"
              class="px-3 py-1.5 bg-rose-950/80 hover:bg-rose-900 text-rose-300 border border-rose-800 rounded-xl transition-colors cursor-pointer flex items-center gap-1 font-extrabold"
            >
              🗑 Delete
            </button>

            <!-- Close Modal -->
            <button
              type="button"
              @click="$emit('close')"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-xl transition-colors font-bold text-sm cursor-pointer ml-2"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Document / Image Viewer Canvas -->
        <div class="flex-1 bg-slate-950 overflow-auto flex items-center justify-center p-6 relative">
          <!-- Image View -->
          <div
            v-if="!previewModal.mimeType.includes('pdf')"
            class="transition-transform duration-150 ease-out origin-center flex items-center justify-center"
            :style="{ transform: `scale(${zoomLevel}) rotate(${rotation}deg)` }"
          >
            <img
              :src="previewModal.url"
              :alt="previewModal.title"
              class="max-w-full max-h-[75vh] object-contain rounded-xl shadow-2xl border border-slate-800"
            />
          </div>

          <!-- PDF View -->
          <iframe
            v-else
            :src="previewModal.url"
            class="w-full h-[75vh] rounded-2xl bg-white dark:bg-slate-900 border border-slate-800 shadow-2xl transition-transform duration-150 ease-out origin-center"
            :style="{ transform: `scale(${zoomLevel})` }"
          ></iframe>
        </div>
      </div>
    </div>
</template>
