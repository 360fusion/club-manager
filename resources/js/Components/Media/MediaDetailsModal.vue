<script setup>
import { ref } from 'vue';

// The file details dialog of the media library page (details, usage, version history), in its own file so the page stays readable.
// The page opens the file picker through the exposed fileInput.
const fileInput = ref(null);
defineExpose({ fileInput });
defineEmits(['close', 'clear-error']);

defineProps({
    activeFolder: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    assetUsages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyUrl: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteItem: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    forceDeleteItem: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getFileIcon: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isImage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isLoadingUsages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isLoadingVersions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isSavingDetails: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isUploadingVersion: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    mediaVersions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openCropper: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewItem: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    regenerateAltTextFromFilename: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoreItem: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoreMediaVersion: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoringVersionId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    saveMediaDetails: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    saveSuccessMsg: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    triggerVersionUpload: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    uploadNewVersion: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-4xl w-full py-4 px-6 sm:px-8 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-3.5 relative overflow-hidden" @click.stop>
        
        <!-- Modal Top Header -->
        <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ getFileIcon(previewItem.mime_type || previewItem.file_name) }}</span>
            <div>
              <h3 class="text-lg font-black text-slate-900 dark:text-white tracking-tight">Media Asset Inspector</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">View asset, edit display name & alt text, or copy direct asset URL.</p>
            </div>
          </div>

          <button type="button" @click="$emit('close')" class="p-2 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold rounded-xl transition-colors cursor-pointer text-sm">✕</button>
        </div>

        <!-- Success Toast Alert -->
        <div v-if="saveSuccessMsg" class="p-2.5 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-200 text-xs font-bold rounded-xl flex items-center gap-2 animate-in fade-in">
          <span>✓</span>
          <span>{{ saveSuccessMsg }}</span>
        </div>

        <!-- Error Toast Alert -->
        <div v-if="previewError" class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 text-rose-800 dark:text-rose-200 text-xs font-bold rounded-xl flex items-center justify-between gap-2 animate-in fade-in">
          <span class="flex items-center gap-2"><span>⚠️</span><span>{{ previewError }}</span></span>
          <button type="button" @click="$emit('clear-error')" class="text-rose-500 hover:text-rose-800 dark:hover:text-rose-200">✕</button>
        </div>

        <!-- Main Inspector Layout: Left Visual Preview + Right Editable Metadata -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
          
          <!-- Left Column: Visual Image / File Preview Box & Technical Specs -->
          <div class="space-y-3">
            <div class="bg-slate-50 dark:bg-slate-800/50 rounded-2xl p-3 border border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center min-h-[160px] max-h-[240px] overflow-hidden relative">
              <img
                v-if="isImage(previewItem.mime_type || previewItem.file_name)"
                :src="previewItem.original_url"
                :alt="previewItem.alt_text || previewItem.name"
                class="max-h-[220px] w-auto object-contain rounded-xl shadow-sm"
              />
              <div v-else class="py-6 text-center space-y-1.5">
                <span class="text-5xl block">{{ getFileIcon(previewItem.mime_type || previewItem.file_name) }}</span>
                <span class="text-xs font-extrabold text-slate-800 dark:text-slate-100 block">{{ previewItem.file_name }}</span>
                <span class="text-[11px] font-semibold text-slate-400 block">{{ previewItem.human_size }}</span>
              </div>
            </div>

            <!-- Technical File Specifications Table -->
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
              <div class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200/60 dark:border-slate-800/60">
                Technical Specifications
              </div>
              <div class="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-300 font-semibold">
                <div><span class="text-slate-400 block text-[10px]">Folder:</span> <span class="capitalize text-slate-900 dark:text-white font-bold">{{ previewItem.collection_name }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">File Size:</span> <span class="text-slate-900 dark:text-white font-bold">{{ previewItem.human_size }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">MIME Type:</span> <span class="text-slate-900 dark:text-white font-bold">{{ previewItem.mime_type }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">Uploaded On:</span> <span class="text-slate-900 dark:text-white font-bold">{{ previewItem.created_at }}</span></div>
              </div>
            </div>

            <!-- Asset Usage Tracking Section -->
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
              <div class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
                <span>Asset Usage Tracking</span>
                <span v-if="isLoadingUsages" class="animate-spin text-blue-600 dark:text-blue-400">🔄</span>
                <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="assetUsages.length ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                  {{ assetUsages.length }} {{ assetUsages.length === 1 ? 'Location' : 'Locations' }}
                </span>
              </div>

              <div v-if="isLoadingUsages" class="py-1.5 text-slate-400 font-semibold text-center">
                Checking asset references across news & settings...
              </div>

              <div v-else-if="!assetUsages.length" class="py-1.5 text-slate-400 font-medium italic text-center text-[11px]">
                This asset is not currently published on news articles or club settings.
              </div>

              <div v-else class="space-y-1 pt-0.5 max-h-[100px] overflow-y-auto">
                <div
                  v-for="(u, idx) in assetUsages"
                  :key="idx"
                  class="p-1.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs"
                >
                  <div class="truncate pr-2">
                    <span class="font-bold text-slate-900 dark:text-white block truncate">{{ u.title }}</span>
                    <span class="text-[10px] text-slate-400 font-medium">{{ u.location }}</span>
                  </div>
                  <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold border border-blue-200 dark:border-blue-800/60 shrink-0">
                    {{ u.type }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Version History Section -->
            <div class="bg-slate-50 dark:bg-slate-800/50 p-3 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
              <div class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between">
                <span>Version History</span>
                <span v-if="isLoadingVersions" class="animate-spin text-blue-600 dark:text-blue-400">🔄</span>
                <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="mediaVersions.length ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300'">
                  {{ mediaVersions.length }} {{ mediaVersions.length === 1 ? 'Version' : 'Versions' }}
                </span>
              </div>

              <div v-if="isLoadingVersions" class="py-1.5 text-slate-400 font-semibold text-center">
                Loading version history...
              </div>

              <div v-else-if="!mediaVersions.length" class="py-1.5 text-slate-400 font-medium italic text-center text-[11px]">
                No previous versions. Replacing or cropping this file will save a version here.
              </div>

              <div v-else class="space-y-1 pt-0.5 max-h-[160px] overflow-y-auto">
                <div
                  v-for="version in mediaVersions"
                  :key="version.id"
                  class="p-1.5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs"
                >
                  <div class="truncate pr-2">
                    <span class="font-bold text-slate-900 dark:text-white block truncate">{{ version.file_name }}</span>
                    <span class="text-[10px] text-slate-400 font-medium">
                      {{ version.human_size }} · {{ version.created_at }}<template v-if="version.uploaded_by"> · {{ version.uploaded_by }}</template>
                      <template v-if="version.note"> · {{ version.note }}</template>
                    </span>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <a
                      :href="version.download_url"
                      target="_blank"
                      class="px-2 py-1 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[10px] font-bold border border-slate-200 dark:border-slate-800"
                    >
                      ⬇️
                    </a>
                    <button
                      type="button"
                      @click="restoreMediaVersion(version)"
                      :disabled="restoringVersionId === version.id"
                      class="px-2 py-1 rounded-xl bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 text-[10px] font-bold border border-amber-300 dark:border-amber-700/60 disabled:opacity-50 cursor-pointer"
                    >
                      {{ restoringVersionId === version.id ? '...' : '↺ Restore' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Editable Metadata Form -->
          <div class="space-y-3 bg-slate-50/50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800">
            <div class="font-extrabold text-slate-900 dark:text-white uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200 dark:border-slate-800">
              Editable Asset Metadata
            </div>

            <!-- 1. Display Title / Name -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800 dark:text-slate-100">Display Title / Asset Name</label>
              <input
                v-model="previewItem.name"
                type="text"
                placeholder="Enter display title..."
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shadow-sm"
              />
            </div>

            <!-- 2. Alt Text (Accessibility & SEO) -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800 dark:text-slate-100 flex items-center justify-between">
                <span>Alt Text (Accessibility & SEO)</span>
                <button
                  type="button"
                  @click="regenerateAltTextFromFilename"
                  class="text-[10px] text-blue-600 dark:text-blue-400 font-bold hover:underline cursor-pointer"
                  title="Fill in from the file name"
                >
                  ↺ Generate from filename
                </button>
              </label>
              <input
                v-model="previewItem.alt_text"
                type="text"
                placeholder="Descriptive alt text for screen readers..."
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shadow-sm"
              />
            </div>

            <!-- 3. Caption / Description -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800 dark:text-slate-100">Caption / Description</label>
              <textarea
                v-model="previewItem.caption"
                rows="2"
                placeholder="Add optional caption or description..."
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 shadow-sm resize-none"
              ></textarea>
            </div>

            <!-- 4. Direct Asset Link Box -->
            <div class="space-y-1 pt-0.5">
              <label class="block text-xs font-extrabold text-slate-800 dark:text-slate-100">Direct Asset Link (URL)</label>
              <div class="flex items-center gap-2">
                <input
                  :value="previewItem.original_url"
                  readonly
                  type="text"
                  class="w-full px-3 py-1.5 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl text-[11px] font-mono text-slate-600 dark:text-slate-300 focus:outline-none select-all"
                />
                <button
                  type="button"
                  @click="copyUrl(previewItem)"
                  class="px-3 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-100 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm transition-all shrink-0 cursor-pointer flex items-center gap-1"
                >
                  📋 Copy
                </button>
              </div>
            </div>

          </div>

        </div>

        <!-- Modal Bottom Actions Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2.5 border-t border-slate-100 dark:border-slate-800 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="isImage(previewItem.mime_type || previewItem.file_name)"
              type="button"
              @click="openCropper"
              class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer"
            >
              <span>✂️ Visual crop & resize</span>
            </button>

            <a
              :href="previewItem.original_url"
              download
              class="px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-bold rounded-xl border border-slate-300 dark:border-slate-700 transition-all inline-flex items-center gap-1.5"
            >
              <span>⬇️ Download file</span>
            </a>
            <a
              :href="previewItem.original_url"
              target="_blank"
              class="px-3.5 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl border border-slate-200 dark:border-slate-800 transition-all inline-flex items-center gap-1.5"
            >
              <span>↗️ Open in new tab</span>
            </a>
          </div>

          <div class="flex items-center gap-2">
            <template v-if="previewItem?.is_trashed || activeFolder === 'trash'">
              <button
                type="button"
                @click="restoreItem(previewItem)"
                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer inline-flex items-center gap-1.5"
              >
                ↻ Restore file
              </button>
              <button
                type="button"
                @click="forceDeleteItem(previewItem)"
                class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs rounded-xl shadow-sm transition-all cursor-pointer inline-flex items-center gap-1.5"
              >
                🔥 Delete permanently
              </button>
            </template>
            <template v-else>
              <button
                v-if="!previewItem?.is_accounting_protected"
                type="button"
                @click="triggerVersionUpload"
                :disabled="isUploadingVersion"
                class="px-3.5 py-1.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 font-bold text-xs rounded-xl border border-amber-300 dark:border-amber-700/60 transition-all cursor-pointer inline-flex items-center gap-1.5 disabled:opacity-50"
                title="Upload a new version of this file, saving the current one to history"
              >
                <span v-if="isUploadingVersion" class="animate-spin">🔄</span>
                <span v-else>⤴️</span>
                <span>{{ isUploadingVersion ? 'Uploading...' : 'Upload new version' }}</span>
              </button>
              <input
                ref="fileInput"
                type="file"
                class="hidden"
                @change="uploadNewVersion"
              />

              <button
                type="button"
                @click="saveMediaDetails"
                :disabled="isSavingDetails"
                class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-extrabold rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
              >
                <span v-if="isSavingDetails" class="animate-spin">🔄</span>
                <span v-else>💾</span>
                <span>{{ isSavingDetails ? 'Saving...' : 'Save asset details' }}</span>
              </button>

              <button
                type="button"
                @click="deleteItem(previewItem)"
                class="px-3.5 py-1.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-bold rounded-xl border border-rose-200 dark:border-rose-800/60 transition-all cursor-pointer inline-flex items-center gap-1.5"
              >
                <span>🗑️ Move to trash</span>
              </button>
            </template>
          </div>
        </div>

      </div>
    </div>
</template>
