<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
    <Modal :open="true" title="File details" subtitle="See the file, edit its name and alt text, or copy its link." size="xl" @close="$emit('close')">
      <div class="space-y-4">
        <Alert v-if="saveSuccessMsg" variant="success">{{ saveSuccessMsg }}</Alert>

        <Alert v-if="previewError" variant="danger">
          <div class="flex items-center justify-between gap-2">
            <span>{{ previewError }}</span>
            <button type="button" aria-label="Dismiss" @click="$emit('clear-error')" class="shrink-0 rounded-xl p-1 hover:bg-rose-100 dark:hover:bg-rose-900/40"><UiIcon name="x" /></button>
          </div>
        </Alert>

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
                <span class="flex justify-center text-slate-400"><UiIcon :name="getFileIcon(previewItem.mime_type || previewItem.file_name)" :size="20" /></span>
                <span class="text-sm font-medium text-slate-900 dark:text-white block">{{ previewItem.file_name }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 block">{{ previewItem.human_size }}</span>
              </div>
            </div>

            <!-- Technical File Specifications Table -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2 text-xs dark:border-slate-800 dark:bg-slate-800/50">
              <div class="pb-1 border-b border-slate-200 text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
                File information
              </div>
              <dl class="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-300">
                <div><dt class="text-slate-500 dark:text-slate-400">Folder</dt> <dd class="capitalize font-medium text-slate-900 dark:text-white">{{ previewItem.collection_name }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">File size</dt> <dd class="font-medium text-slate-900 dark:text-white">{{ previewItem.human_size }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">MIME type</dt> <dd class="font-medium text-slate-900 dark:text-white">{{ previewItem.mime_type }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Uploaded on</dt> <dd class="font-medium text-slate-900 dark:text-white">{{ previewItem.created_at }}</dd></div>
              </dl>
            </div>

            <!-- Asset Usage Tracking Section -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2 text-xs dark:border-slate-800 dark:bg-slate-800/50">
              <div class="pb-1 border-b border-slate-200 text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400 flex items-center justify-between">
                <span>Where it is used</span>
                <UiIcon v-if="isLoadingUsages" name="refresh" class="animate-spin text-blue-600 dark:text-blue-400" />
                <Badge v-else :variant="assetUsages.length ? 'success' : 'neutral'" class="normal-case tracking-normal">
                  {{ assetUsages.length }} {{ assetUsages.length === 1 ? 'place' : 'places' }}
                </Badge>
              </div>

              <div v-if="isLoadingUsages" class="py-1.5 text-slate-500 dark:text-slate-400 text-center">
                Checking news, pages and settings…
              </div>

              <div v-else-if="!assetUsages.length" class="py-1.5 text-slate-500 dark:text-slate-400 text-center">
                This file is not used in any news article or club setting.
              </div>

              <div v-else class="space-y-1 pt-0.5 max-h-[100px] overflow-y-auto">
                <div
                  v-for="(u, idx) in assetUsages"
                  :key="idx"
                  class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs"
                >
                  <div class="truncate pr-2">
                    <span class="font-medium text-slate-900 dark:text-white block truncate">{{ u.title }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ u.location }}</span>
                  </div>
                  <Badge variant="info" class="shrink-0">{{ u.type }}</Badge>
                </div>
              </div>
            </div>

            <!-- Version History Section -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-2 text-xs dark:border-slate-800 dark:bg-slate-800/50">
              <div class="pb-1 border-b border-slate-200 text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400 flex items-center justify-between">
                <span>Version history</span>
                <UiIcon v-if="isLoadingVersions" name="refresh" class="animate-spin text-blue-600 dark:text-blue-400" />
                <Badge v-else :variant="mediaVersions.length ? 'success' : 'neutral'" class="normal-case tracking-normal">
                  {{ mediaVersions.length }} {{ mediaVersions.length === 1 ? 'version' : 'versions' }}
                </Badge>
              </div>

              <div v-if="isLoadingVersions" class="py-1.5 text-slate-500 dark:text-slate-400 text-center">
                Loading version history…
              </div>

              <div v-else-if="!mediaVersions.length" class="py-1.5 text-slate-500 dark:text-slate-400 text-center">
                No earlier versions. Replacing or cropping this file saves a version here.
              </div>

              <div v-else class="space-y-1 pt-0.5 max-h-[160px] overflow-y-auto">
                <div
                  v-for="version in mediaVersions"
                  :key="version.id"
                  class="p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs"
                >
                  <div class="truncate pr-2">
                    <span class="font-medium text-slate-900 dark:text-white block truncate">{{ version.file_name }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">
                      {{ version.human_size }} · {{ version.created_at }}<template v-if="version.uploaded_by"> · {{ version.uploaded_by }}</template>
                      <template v-if="version.note"> · {{ version.note }}</template>
                    </span>
                  </div>
                  <div class="flex items-center gap-1 shrink-0">
                    <a
                      :href="version.download_url"
                      target="_blank"
                      aria-label="Download this version"
                      title="Download this version"
                      class="inline-flex items-center rounded-xl border border-slate-300 bg-white p-1.5 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    >
                      <UiIcon name="download" />
                    </a>
                    <Button variant="secondary" size="sm" :disabled="restoringVersionId === version.id" @click="restoreMediaVersion(version)">
                      {{ restoringVersionId === version.id ? 'Restoring…' : 'Restore' }}
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Editable Metadata Form -->
          <div class="space-y-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
            <div class="pb-1 border-b border-slate-200 text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:text-slate-400">
              Details
            </div>

            <Input v-model="previewItem.name" label="Display name" placeholder="Enter a display name" />

            <div class="space-y-1">
              <Input v-model="previewItem.alt_text" label="Alt text (accessibility and SEO)" placeholder="Describe the image for screen readers" />
              <button
                type="button"
                @click="regenerateAltTextFromFilename"
                class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"
                title="Fill in from the file name"
              >
                Generate from the file name
              </button>
            </div>

            <Textarea v-model="previewItem.caption" :rows="2" label="Caption or description" placeholder="Add an optional caption or description" />

            <div class="space-y-1.5">
              <div class="flex items-end gap-2">
                <Input :model-value="previewItem.original_url" readonly label="Link to the file" class="flex-1 font-mono" />
                <Button variant="secondary" class="shrink-0" @click="copyUrl(previewItem)">
                  <template #icon><UiIcon name="copy" /></template>
                  Copy
                </Button>
              </div>
            </div>
          </div>

        </div>
      </div>

      <template #footer>
        <div class="flex w-full flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <Button v-if="isImage(previewItem.mime_type || previewItem.file_name)" variant="secondary" size="sm" @click="openCropper">
              <template #icon><UiIcon name="crop" /></template>
              Crop and resize
            </Button>

            <a
              :href="previewItem.original_url"
              download
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              <UiIcon name="download" />
              Download file
            </a>
            <a
              :href="previewItem.original_url"
              target="_blank"
              class="inline-flex items-center gap-1.5 rounded-xl border border-transparent px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
            >
              <UiIcon name="external-link" />
              Open in a new tab
            </a>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <template v-if="previewItem?.is_trashed || activeFolder === 'trash'">
              <Button size="sm" @click="restoreItem(previewItem)">
                <template #icon><UiIcon name="restore" /></template>
                Restore file
              </Button>
              <Button variant="danger" size="sm" @click="forceDeleteItem(previewItem)">
                <template #icon><UiIcon name="trash" /></template>
                Delete permanently
              </Button>
            </template>
            <template v-else>
              <Button
                v-if="!previewItem?.is_accounting_protected"
                variant="secondary"
                size="sm"
                :loading="isUploadingVersion"
                title="Upload a new version of this file, saving the current one to history"
                @click="triggerVersionUpload"
              >
                <template v-if="!isUploadingVersion" #icon><UiIcon name="upload" /></template>
                {{ isUploadingVersion ? 'Uploading…' : 'Upload a new version' }}
              </Button>
              <input
                ref="fileInput"
                type="file"
                class="hidden"
                @change="uploadNewVersion"
              />

              <Button variant="secondary" size="sm" @click="deleteItem(previewItem)">
                <template #icon><UiIcon name="trash" /></template>
                Move to trash
              </Button>

              <Button size="sm" :loading="isSavingDetails" @click="saveMediaDetails">
                <template v-if="!isSavingDetails" #icon><UiIcon name="save" /></template>
                {{ isSavingDetails ? 'Saving…' : 'Save details' }}
              </Button>
            </template>
          </div>
        </div>
      </template>
    </Modal>
</template>
