<script setup>
import MediaDetailsModal from '@/Components/Media/MediaDetailsModal.vue';
import MediaCropModal from '@/Components/Media/MediaCropModal.vue';
import MediaFolderSidebar from '@/Components/Media/MediaFolderSidebar.vue';
import MediaFolderNav from '@/Components/Media/MediaFolderNav.vue';
import MediaFilterBar from '@/Components/Media/MediaFilterBar.vue';
import MediaBulkBar from '@/Components/Media/MediaBulkBar.vue';
import MediaFileGrid from '@/Components/Media/MediaFileGrid.vue';
import { useMediaLibrary, getFileIcon, isImage } from '@/Composables/useMediaLibrary';
import { useMediaInspector } from '@/Composables/useMediaInspector';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
});

const {
  activeFolder, searchQuery, mediaItems, isLoading, isLoadingMore, hasMore, totalFiles, isUploading, uploadFileInput,
  copyToast, previewItem, uploadFolder, isDragging, uploadStatus, uploadError, filterType, filterExtension, filterDate,
  sortBy, availableExtensions, availableMonths, trashCount, showNewFolderForm, newFolderName, isCreatingFolder,
  newFolderError, storage, folders, subfoldersOf, activeCustomFolder, fixedFolderById, activeFolderLabel,
  selectableFolders, fetchMedia, resetFilters, createFolder, deleteFolder, triggerUpload, handleFileUpload, handleDrop,
  copyUrl, selectedMediaIds, targetBulkFolder, toggleSelectItem, toggleSelectAll, handleBulkDelete, handleBulkMove,
  restoreItem, forceDeleteItem, handleBulkRestore, handleBulkForceDelete, deleteItem,
} = useMediaLibrary(props);

const {
  isSavingDetails, saveSuccessMsg, previewError, assetUsages, isLoadingUsages, mediaVersions, isLoadingVersions,
  restoringVersionId, isUploadingVersion, detailsModal, showCropModal, cropAspect, isCropping, cropModal, openPreview,
  regenerateAltTextFromFilename, openCropper, closeCropper, setCropAspect, rotateCropper, applyCrop,
  triggerVersionUpload, uploadNewVersion, restoreMediaVersion, saveMediaDetails,
} = useMediaInspector(props, { mediaItems, previewItem, fetchMedia });
</script>

<template>
  <AdminLayout title="File manager" :club="club" active-tab="media">
    <Head :title="`File manager - ${club.name}`" />

    <div class="space-y-6 max-w-6xl mx-auto">

      <PageHeader title="File manager" description="Organise, search and upload club logos, news images, newsletters, galleries and documents.">
        <template #badge>
          <Badge>{{ club.slug }}</Badge>
        </template>
        <template #actions>
          <!-- Target Folder Selection Dropdown -->
          <Select
            v-if="activeFolder === 'all'"
            v-model="uploadFolder"
            aria-label="Upload to folder"
            class="w-48"
            :options="selectableFolders.map(f => ({ value: f.id, label: f.label }))"
          />

          <Button :loading="isUploading" @click="triggerUpload">
            <template v-if="!isUploading" #icon><UiIcon name="upload" /></template>
            {{ isUploading ? 'Uploading…' : 'Upload files' }}
          </Button>
          <input
            ref="uploadFileInput"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.rtf,.zip"
            class="hidden"
            @change="handleFileUpload"
          />
        </template>
      </PageHeader>

      <!-- Main Layout: Folder Sidebar + Media Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">

        <!-- Left Sidebar Folders -->
        <MediaFolderSidebar v-model:active-folder="activeFolder" :folders="folders" :trash-count="trashCount" :storage="storage" />

        <!-- Right Main Media Grid -->
        <div
          @dragover.prevent="isDragging = true"
          @dragenter.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
          :class="[
            'lg:col-span-3 bg-white dark:bg-slate-900 p-6 rounded-2xl border transition-colors shadow-sm space-y-6 relative',
            isDragging ? 'border-blue-500 ring-4 ring-blue-100 dark:ring-blue-900/40' : 'border-slate-200 dark:border-slate-800'
          ]"
        >
          <!-- Drag Over Highlight Overlay -->
          <div v-if="isDragging" class="absolute inset-0 bg-blue-500/10 backdrop-blur-[2px] rounded-2xl border-2 border-dashed border-blue-500 z-30 flex flex-col items-center justify-center gap-1 p-6 text-blue-700 dark:text-blue-300 pointer-events-none">
            <UiIcon name="upload" :size="20" class="animate-bounce" />
            <span class="text-base font-semibold">Drop files here to upload</span>
            <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Target folder: {{ activeFolder !== 'all' ? activeFolder : uploadFolder }}</span>
          </div>

          <MediaFolderNav
            v-model:active-folder="activeFolder"
            v-model:show-new-folder-form="showNewFolderForm"
            v-model:new-folder-name="newFolderName"
            v-model:new-folder-error="newFolderError"
            :active-custom-folder="activeCustomFolder"
            :fixed-folder-by-id="fixedFolderById"
            :subfolders-of="subfoldersOf"
            :is-creating-folder="isCreatingFolder"
            @create="createFolder"
            @delete="deleteFolder"
          />

          <!-- Inline Search & Filter Controls Bar -->
          <MediaFilterBar
            v-model:search-query="searchQuery"
            v-model:filter-type="filterType"
            v-model:filter-extension="filterExtension"
            v-model:filter-date="filterDate"
            v-model:sort-by="sortBy"
            :shown-count="mediaItems.length"
            :has-more="hasMore"
            :total-files="totalFiles"
            :active-folder-label="activeFolderLabel"
            :available-extensions="availableExtensions"
            :available-months="availableMonths"
            @reset="resetFilters"
          />


          <Alert v-if="activeFolder === 'trash'" variant="warning">
            Files in the trash bin are deleted for good 30 days after they were moved here. Restore anything you want to keep before then.
          </Alert>

          <!-- Bulk Actions Floating Control Bar -->
          <MediaBulkBar
            v-if="selectedMediaIds.length > 0"
            v-model:selected-media-ids="selectedMediaIds"
            v-model:target-bulk-folder="targetBulkFolder"
            :active-folder="activeFolder"
            :item-count="mediaItems.length"
            :selectable-folders="selectableFolders"
            @toggle-all="toggleSelectAll"
            @restore="handleBulkRestore"
            @force-delete="handleBulkForceDelete"
            @move="handleBulkMove"
            @delete="handleBulkDelete"
          />

          <!-- Upload Status / Error / Success Alerts -->
          <Alert v-if="uploadStatus" variant="info">
            <span class="flex items-center gap-2">
              <UiIcon name="refresh" class="animate-spin" />
              <span>{{ uploadStatus }}</span>
            </span>
          </Alert>

          <Alert v-if="uploadError" variant="danger">
            <div class="flex items-center justify-between gap-2">
              <span>{{ uploadError }}</span>
              <button type="button" aria-label="Dismiss" @click="uploadError = ''" class="shrink-0 rounded-xl p-1 hover:bg-rose-100 dark:hover:bg-rose-900/40"><UiIcon name="x" /></button>
            </div>
          </Alert>

          <Alert v-if="copyToast" variant="success">{{ copyToast }}</Alert>

          <!-- Files Grid -->
          <MediaFileGrid
            :media-items="mediaItems"
            :is-loading="isLoading"
            :is-loading-more="isLoadingMore"
            :has-more="hasMore"
            :total-files="totalFiles"
            :selected-media-ids="selectedMediaIds"
            :active-folder="activeFolder"
            @open="openPreview"
            @toggle-select="toggleSelectItem"
            @copy="copyUrl"
            @restore="restoreItem"
            @force-delete="forceDeleteItem"
            @delete="deleteItem"
            @upload="triggerUpload"
            @load-more="fetchMedia(true)"
          />

        </div>

      </div>

    </div>

    <!-- Media Asset Details & Inspector Modal -->
    <MediaDetailsModal ref="detailsModal" v-if="previewItem" :active-folder="activeFolder" :asset-usages="assetUsages" :club="club" :copy-url="copyUrl" :delete-item="deleteItem" :force-delete-item="forceDeleteItem" :get-file-icon="getFileIcon" :is-image="isImage" :is-loading-usages="isLoadingUsages" :is-loading-versions="isLoadingVersions" :is-saving-details="isSavingDetails" :is-uploading-version="isUploadingVersion" :media-versions="mediaVersions" :open-cropper="openCropper" :preview-error="previewError" :preview-item="previewItem" :regenerate-alt-text-from-filename="regenerateAltTextFromFilename" :restore-item="restoreItem" :restore-media-version="restoreMediaVersion" :restoring-version-id="restoringVersionId" :save-media-details="saveMediaDetails" :save-success-msg="saveSuccessMsg" :trigger-version-upload="triggerVersionUpload" :upload-new-version="uploadNewVersion" @close="previewItem = null" @clear-error="previewError = ''" />

    <!-- Visual Image Cropper Modal -->
    <MediaCropModal ref="cropModal" v-if="showCropModal" :apply-crop="applyCrop" :close-cropper="closeCropper" :crop-aspect="cropAspect" :is-cropping="isCropping" :preview-item="previewItem" :rotate-cropper="rotateCropper" :set-crop-aspect="setCropAspect" />
  </AdminLayout>
</template>

<style scoped>
:deep(.cropper-container) {
  width: 100% !important;
  height: 100% !important;
}
:deep(.cropper-bg) {
  background-image: none !important;
  background-color: #020617 !important;
}
:deep(.cropper-view-box) {
  outline: 2px solid #38bdf8 !important;
  outline-color: rgba(56, 189, 248, 0.9) !important;
}
:deep(.cropper-line) {
  background-color: #38bdf8 !important;
}
:deep(.cropper-point) {
  background-color: #0284c7 !important;
  width: 8px !important;
  height: 8px !important;
  opacity: 0.95 !important;
  border-radius: 2px !important;
}
:deep(.cropper-point.point-se) {
  width: 12px !important;
  height: 12px !important;
  background-color: #38bdf8 !important;
}
</style>
