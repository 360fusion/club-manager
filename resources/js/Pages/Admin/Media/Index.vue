<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
});

const activeFolder = ref('all');
const searchQuery = ref('');
const mediaItems = ref([]);
const isLoading = ref(false);
const isUploading = ref(false);
const uploadFileInput = ref(null);
const copyToast = ref('');
const previewItem = ref(null);
const uploadFolder = ref('images');
const isDragging = ref(false);
const uploadStatus = ref('');
const uploadError = ref('');

const filterType = ref('all');
const filterExtension = ref('all');
const filterDate = ref('all');
const sortBy = ref('newest');
const isSavingDetails = ref(false);
const saveSuccessMsg = ref('');

const availableExtensions = ref([]);
const availableMonths = ref([]);

const folders = [
  { id: 'all', label: 'All Files', icon: '📁', bg: 'bg-slate-100', text: 'text-slate-700' },
  { id: 'logos', label: 'Logos', icon: '🖼️', bg: 'bg-indigo-50', text: 'text-indigo-700' },
  { id: 'news', label: 'News Items', icon: '📰', bg: 'bg-blue-50', text: 'text-blue-700' },
  { id: 'newsletters', label: 'Newsletters', icon: '✉️', bg: 'bg-emerald-50', text: 'text-emerald-700' },
  { id: 'images', label: 'Single Images', icon: '📷', bg: 'bg-sky-50', text: 'text-sky-700' },
  { id: 'galleries', label: 'Galleries', icon: '🖼️', bg: 'bg-purple-50', text: 'text-purple-700' },
  { id: 'documents', label: 'Documents', icon: '📄', bg: 'bg-amber-50', text: 'text-amber-700' },
];

const selectableFolders = [
  { id: 'logos', label: 'Logos' },
  { id: 'news', label: 'News Items' },
  { id: 'newsletters', label: 'Newsletters' },
  { id: 'images', label: 'Single Images' },
  { id: 'galleries', label: 'Galleries' },
  { id: 'documents', label: 'Documents' },
];

const getCsrfToken = () => {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta && meta.getAttribute('content')) {
    return meta.getAttribute('content');
  }
  const match = document.cookie.match(new RegExp('(?:^|; )XSRF-TOKEN=([^;]+)'));
  if (match) {
    return decodeURIComponent(match[1]);
  }
  return '';
};

const fetchMedia = async () => {
  if (!props.club?.slug) return;
  isLoading.value = true;
  try {
    const params = new URLSearchParams();
    if (activeFolder.value !== 'all') params.append('folder', activeFolder.value);
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (filterType.value !== 'all') params.append('type', filterType.value);
    if (filterExtension.value !== 'all') params.append('extension', filterExtension.value);
    if (filterDate.value !== 'all') params.append('date', filterDate.value);
    if (sortBy.value !== 'newest') params.append('sort', sortBy.value);

    const res = await fetch(`/clubs/${props.club.slug}/admin/media?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      mediaItems.value = data.media || [];
      availableExtensions.value = data.available_extensions || [];
      availableMonths.value = data.available_months || [];
    }
  } catch (err) {
    console.error('Failed to load media library items:', err);
  } finally {
    isLoading.value = false;
  }
};

const resetFilters = () => {
  searchQuery.value = '';
  filterType.value = 'all';
  filterExtension.value = 'all';
  filterDate.value = 'all';
  sortBy.value = 'newest';
};

onMounted(() => {
  fetchMedia();
});

watch([activeFolder, searchQuery, filterType, filterExtension, filterDate, sortBy], () => {
  if (activeFolder.value !== 'all') {
    uploadFolder.value = activeFolder.value;
  }
  fetchMedia();
});

const triggerUpload = () => {
  if (uploadFileInput.value) {
    uploadFileInput.value.click();
  }
};

const uploadFiles = async (filesList) => {
  const files = Array.from(filesList || []);
  if (!files.length) return;

  isUploading.value = true;
  uploadError.value = '';
  uploadStatus.value = '';

  const targetFolder = activeFolder.value !== 'all' ? activeFolder.value : uploadFolder.value;
  const isImageOnlyFolder = ['logos', 'images', 'galleries'].includes(targetFolder);
  const allowedExts = isImageOnlyFolder
    ? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']
    : ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'rtf', 'zip'];

  const maxSizeBytes = 10 * 1024 * 1024; // 10MB

  let successCount = 0;

  for (let i = 0; i < files.length; i++) {
    const file = files[i];

    // Client pre-check 1: 10MB File Size Limit
    if (file.size > maxSizeBytes) {
      uploadError.value = `File "${file.name}" exceeds the 10MB maximum size limit (${(file.size / (1024 * 1024)).toFixed(1)}MB).`;
      isUploading.value = false;
      return;
    }

    // Client pre-check 2: Allowed Extension
    const ext = (file.name.split('.').pop() || '').toLowerCase();
    if (!allowedExts.includes(ext)) {
      uploadError.value = isImageOnlyFolder
        ? `"${file.name}" has an invalid file type for "${targetFolder}". Only image files (JPG, PNG, GIF, WEBP, SVG) are allowed.`
        : `"${file.name}" has an invalid file type. Only document and image files are allowed.`;
      isUploading.value = false;
      return;
    }

    uploadStatus.value = `Uploading file ${i + 1} of ${files.length}: "${file.name}"...`;

    const formData = new FormData();
    formData.append('file', file);
    formData.append('folder', targetFolder);

    try {
      const token = getCsrfToken();
      const res = await fetch(`/clubs/${props.club.slug}/admin/media`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
        body: formData,
      });

      const data = await res.json().catch(() => ({}));

      if (res.ok && data.success && data.media) {
        mediaItems.value.unshift(data.media);
        successCount++;
      } else {
        const msg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Upload failed');
        uploadError.value = `Error uploading "${file.name}": ${msg}`;
        break;
      }
    } catch (err) {
      console.error('Failed to upload file:', err);
      uploadError.value = `Network error uploading "${file.name}". Please check server connection.`;
      break;
    }
  }

  isUploading.value = false;
  uploadStatus.value = '';

  if (successCount > 0 && !uploadError.value) {
    copyToast.value = `Successfully uploaded ${successCount} ${successCount === 1 ? 'file' : 'files'} to "${targetFolder}"!`;
    setTimeout(() => {
      copyToast.value = '';
    }, 4000);
  }
};

const handleFileUpload = (e) => {
  uploadFiles(e.target.files);
  if (e.target) e.target.value = '';
};

const handleDrop = (e) => {
  isDragging.value = false;
  if (e.dataTransfer && e.dataTransfer.files) {
    uploadFiles(e.dataTransfer.files);
  }
};

const copyUrl = (item) => {
  navigator.clipboard.writeText(item.original_url);
  copyToast.value = `Copied URL for "${item.file_name}"!`;
  setTimeout(() => {
    copyToast.value = '';
  }, 3000);
};

const selectedMediaIds = ref([]);
const targetBulkFolder = ref('images');
const assetUsages = ref([]);
const isLoadingUsages = ref(false);

const showCropModal = ref(false);
const cropAspect = ref('free'); // 'free', '1:1', '16:9', '4:3'
const cropRotation = ref(0);
const isCropping = ref(false);
const cropCanvasRef = ref(null);
const cropSourceImageRef = ref(null);

const toggleSelectItem = (id) => {
  if (selectedMediaIds.value.includes(id)) {
    selectedMediaIds.value = selectedMediaIds.value.filter(i => i !== id);
  } else {
    selectedMediaIds.value.push(id);
  }
};

const toggleSelectAll = () => {
  if (selectedMediaIds.value.length === mediaItems.value.length) {
    selectedMediaIds.value = [];
  } else {
    selectedMediaIds.value = mediaItems.value.map(m => m.id);
  }
};

const handleBulkDelete = async () => {
  if (!selectedMediaIds.value.length) return;
  if (!confirm(`Are you sure you want to delete ${selectedMediaIds.value.length} selected files?`)) return;

  try {
    const res = await fetch(`/clubs/${props.club.slug}/admin/media/bulk-delete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({ ids: selectedMediaIds.value }),
    });

    if (res.ok) {
      const data = await res.json();
      mediaItems.value = mediaItems.value.filter(m => !selectedMediaIds.value.includes(m.id));
      selectedMediaIds.value = [];
      copyToast.value = data.message || 'Selected files deleted successfully!';
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed bulk delete:', err);
  }
};

const handleBulkMove = async () => {
  if (!selectedMediaIds.value.length) return;

  try {
    const res = await fetch(`/clubs/${props.club.slug}/admin/media/bulk-move`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        ids: selectedMediaIds.value,
        folder: targetBulkFolder.value,
      }),
    });

    if (res.ok) {
      const data = await res.json();
      fetchMedia();
      selectedMediaIds.value = [];
      copyToast.value = data.message || 'Selected files moved successfully!';
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed bulk move:', err);
  }
};

const fetchUsage = async (id) => {
  assetUsages.value = [];
  isLoadingUsages.value = true;
  try {
    const res = await fetch(`/clubs/${props.club.slug}/admin/media/${id}/usage`);
    if (res.ok) {
      const data = await res.json();
      assetUsages.value = data.usages || [];
    }
  } catch (err) {
    console.error('Failed to fetch asset usage:', err);
  } finally {
    isLoadingUsages.value = false;
  }
};

const openPreview = (item) => {
  previewItem.value = {
    ...item,
    alt_text: item.alt_text || '',
    caption: item.caption || '',
  };
  saveSuccessMsg.value = '';
  fetchUsage(item.id);
};

let cropperInstance = null;

const initCropper = () => {
  if (cropperInstance) {
    cropperInstance.destroy();
    cropperInstance = null;
  }
  if (!cropSourceImageRef.value) return;

  cropperInstance = new Cropper(cropSourceImageRef.value, {
    aspectRatio: NaN,
    viewMode: 1,
    dragMode: 'crop',
    autoCropArea: 0.85,
    responsive: true,
    restore: true,
    checkCrossOrigin: false,
    guides: true,
    center: true,
    highlight: true,
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: true,
  });
};

const openCropper = () => {
  if (!previewItem.value || !isImage(previewItem.value.mime_type || previewItem.value.file_name)) return;
  showCropModal.value = true;
  cropAspect.value = 'free';
  cropRotation.value = 0;

  nextTick(() => {
    setTimeout(() => {
      initCropper();
    }, 150);
  });
};

const closeCropper = () => {
  if (cropperInstance) {
    cropperInstance.destroy();
    cropperInstance = null;
  }
  showCropModal.value = false;
};

const setCropAspect = (aspect) => {
  cropAspect.value = aspect;
  if (!cropperInstance) return;

  if (aspect === '1:1') {
    cropperInstance.setAspectRatio(1);
  } else if (aspect === '16:9') {
    cropperInstance.setAspectRatio(16 / 9);
  } else if (aspect === '4:3') {
    cropperInstance.setAspectRatio(4 / 3);
  } else {
    cropperInstance.setAspectRatio(NaN); // free crop
  }
};

const rotateCropper = (deg) => {
  if (!cropperInstance) return;
  cropperInstance.rotate(deg);
  cropRotation.value = (cropRotation.value + deg) % 360;
};

const applyCrop = async () => {
  if (!cropperInstance || !previewItem.value) return;
  isCropping.value = true;

  try {
    const canvas = cropperInstance.getCroppedCanvas({
      maxWidth: 1920,
      maxHeight: 1920,
      imageSmoothingEnabled: true,
      imageSmoothingQuality: 'high',
    });

    if (!canvas) {
      isCropping.value = false;
      return;
    }

    canvas.toBlob(async (blob) => {
      if (!blob) {
        isCropping.value = false;
        return;
      }
      const formData = new FormData();
      formData.append('file', blob, previewItem.value.file_name);

      const res = await fetch(`/clubs/${props.club.slug}/admin/media/${previewItem.value.id}/crop`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
        },
        body: formData,
      });

      if (res.ok) {
        const data = await res.json();
        if (data.media) {
          const idx = mediaItems.value.findIndex(m => m.id === data.media.id);
          if (idx !== -1) mediaItems.value[idx] = data.media;
          previewItem.value = { ...data.media };
          closeCropper();
          saveSuccessMsg.value = 'Image cropped and updated successfully!';
          setTimeout(() => { saveSuccessMsg.value = ''; }, 3000);
        }
      }
      isCropping.value = false;
    }, 'image/jpeg', 0.9);
  } catch (err) {
    console.error('Failed to crop image:', err);
    isCropping.value = false;
  }
};

const saveMediaDetails = async () => {
  if (!previewItem.value) return;
  isSavingDetails.value = true;
  saveSuccessMsg.value = '';

  try {
    const res = await fetch(`/clubs/${props.club.slug}/admin/media/${previewItem.value.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        name: previewItem.value.name,
        alt_text: previewItem.value.alt_text,
        caption: previewItem.value.caption,
      }),
    });

    if (res.ok) {
      const data = await res.json();
      if (data.media) {
        const idx = mediaItems.value.findIndex(m => m.id === data.media.id);
        if (idx !== -1) {
          mediaItems.value[idx] = data.media;
        }
        previewItem.value = { ...data.media };
        saveSuccessMsg.value = 'Media details saved successfully!';
        setTimeout(() => {
          saveSuccessMsg.value = '';
        }, 3000);
      }
    }
  } catch (err) {
    console.error('Failed to save media details:', err);
  } finally {
    isSavingDetails.value = false;
  }
};

const deleteItem = async (item) => {
  if (!confirm(`Are you sure you want to delete "${item.file_name}" from the media library?`)) return;

  try {
    const res = await fetch(`/clubs/${props.club.slug}/admin/media/${item.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      if (previewItem.value?.id === item.id) {
        previewItem.value = null;
      }
    }
  } catch (err) {
    console.error('Failed to delete media item:', err);
  }
};

const getFileIcon = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return '📄';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.webp')) return '🖼️';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return '📊';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return '📝';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return '📦';
  return '📎';
};

const isImage = (mimeOrUrl) => {
  const val = (mimeOrUrl || '').toLowerCase();
  return val.includes('image') || val.match(/\.(jpg|jpeg|png|webp|gif|svg)$/);
};
</script>

<template>
  <AdminLayout title="File Manager" :club="club" active-tab="media">
    <Head :title="`File Manager - ${club.name}`" />

    <div class="space-y-6 max-w-6xl mx-auto">
      
      <!-- Top Action Bar -->
      <div class="bg-white p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">File Manager</h1>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-sky-50 text-sky-700 border border-sky-200">
              {{ club.slug }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-slate-500 mt-1">Organize, search, and upload club logos, news images, newsletters, galleries, and downloadable documents.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 self-start sm:self-auto">
          <!-- Target Folder Selection Dropdown -->
          <div v-if="activeFolder === 'all'" class="flex items-center gap-1.5 bg-slate-50 px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700">
            <span class="text-slate-400">Target:</span>
            <select v-model="uploadFolder" class="bg-transparent font-bold text-slate-900 focus:outline-none cursor-pointer">
              <option v-for="f in selectableFolders" :key="f.id" :value="f.id">{{ f.label }}</option>
            </select>
          </div>

          <!-- Upload Button -->
          <button
            type="button"
            @click="triggerUpload"
            :disabled="isUploading"
            class="px-5 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition-all duration-200 flex items-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <span v-if="isUploading" class="animate-spin text-sm">🔄</span>
            <span v-else class="text-sm">📤</span>
            <span>{{ isUploading ? 'Uploading...' : 'Upload New File' }}</span>
          </button>
          <input
            ref="uploadFileInput"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,application/pdf,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.rtf,.zip"
            class="hidden"
            @change="handleFileUpload"
          />
        </div>
      </div>

      <!-- Main Layout: Folder Sidebar + Media Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
        
        <!-- Left Sidebar Folders -->
        <div class="lg:col-span-1 bg-white p-4 rounded-3xl border border-slate-200/80 shadow-sm space-y-3 sticky top-6">
          <div class="text-[11px] font-black uppercase tracking-wider text-slate-400 px-3 pt-1">
            Media Folders
          </div>

          <div class="space-y-1">
            <button
              v-for="folder in folders"
              :key="folder.id"
              type="button"
              @click="activeFolder = folder.id"
              :class="[
                'w-full flex items-center justify-between px-4 py-3 rounded-2xl text-xs font-bold transition-all text-left cursor-pointer',
                activeFolder === folder.id
                  ? 'bg-slate-900 text-white shadow-md'
                  : 'text-slate-700 hover:bg-slate-100'
              ]"
            >
              <span class="flex items-center gap-2.5">
                <span class="text-sm">{{ folder.icon }}</span>
                <span>{{ folder.label }}</span>
              </span>
            </button>
          </div>
        </div>

        <!-- Right Main Media Grid -->
        <div
          @dragover.prevent="isDragging = true"
          @dragenter.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleDrop"
          :class="[
            'lg:col-span-3 bg-white p-6 sm:p-8 rounded-3xl border transition-all duration-200 shadow-sm space-y-6 relative',
            isDragging ? 'border-sky-500 ring-4 ring-sky-100 bg-sky-50/20' : 'border-slate-200/80'
          ]"
        >
          <!-- Drag Over Highlight Overlay -->
          <div v-if="isDragging" class="absolute inset-0 bg-sky-500/10 backdrop-blur-[2px] rounded-3xl border-2 border-dashed border-sky-500 z-30 flex flex-col items-center justify-center p-6 text-sky-700 pointer-events-none">
            <span class="text-5xl animate-bounce mb-2">📥</span>
            <span class="text-base font-extrabold">Drop files here to upload</span>
            <span class="text-xs font-bold text-sky-600 mt-1">Target folder: {{ activeFolder !== 'all' ? activeFolder : uploadFolder }}</span>
          </div>

          <!-- Inline Search & Filter Controls Bar -->
          <div class="space-y-4 pb-4 border-b border-slate-100">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
              
              <!-- Search Input Bar -->
              <div class="relative flex-1">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search files by name..."
                  class="w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
                />
                <span class="absolute left-3.5 top-2.5 text-slate-400 text-xs">🔍</span>
                <button
                  v-if="searchQuery"
                  type="button"
                  @click="searchQuery = ''"
                  class="absolute right-3 top-2 text-slate-400 hover:text-slate-700 text-xs font-bold"
                >
                  ✕
                </button>
              </div>

              <!-- Counter Badge -->
              <div class="text-xs font-bold text-slate-500 shrink-0">
                Showing {{ mediaItems.length }} {{ mediaItems.length === 1 ? 'file' : 'files' }} in <span class="text-slate-900 font-extrabold capitalize">{{ activeFolder }}</span>
              </div>
            </div>

            <!-- Inline Filter Dropdowns Grid -->
            <div class="flex flex-wrap items-center gap-2 pt-1">
              
              <!-- 1. Category / Type Filter -->
              <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                <span class="text-slate-400">Type:</span>
                <select v-model="filterType" class="bg-transparent font-extrabold text-slate-900 focus:outline-none cursor-pointer">
                  <option value="all">All Types</option>
                  <option value="image">🖼️ Images Only</option>
                  <option value="document">📄 Documents Only</option>
                </select>
              </div>

              <!-- 2. Dynamic File Extension Filter (Only available extensions listed) -->
              <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                <span class="text-slate-400">Extension:</span>
                <select v-model="filterExtension" class="bg-transparent font-extrabold text-slate-900 focus:outline-none cursor-pointer">
                  <option value="all">All Extensions</option>
                  <option v-for="ext in availableExtensions" :key="ext" :value="ext">
                    .{{ ext.toUpperCase() }}
                  </option>
                </select>
              </div>

              <!-- 3. Dynamic Date Added Filter (Year / Month) -->
              <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                <span class="text-slate-400">Date:</span>
                <select v-model="filterDate" class="bg-transparent font-extrabold text-slate-900 focus:outline-none cursor-pointer">
                  <option value="all">All Dates</option>
                  <option v-for="m in availableMonths" :key="m.value" :value="m.value">
                    📅 {{ m.label }}
                  </option>
                </select>
              </div>

              <!-- 4. Sort Order -->
              <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1.5 rounded-xl border border-slate-200 text-[11px] font-bold text-slate-700">
                <span class="text-slate-400">Sort:</span>
                <select v-model="sortBy" class="bg-transparent font-extrabold text-slate-900 focus:outline-none cursor-pointer">
                  <option value="newest">Newest First</option>
                  <option value="oldest">Oldest First</option>
                  <option value="name_asc">Name (A to Z)</option>
                  <option value="name_desc">Name (Z to A)</option>
                  <option value="size_desc">Size (Largest)</option>
                  <option value="size_asc">Size (Smallest)</option>
                </select>
              </div>

              <!-- Reset Filters Button -->
              <button
                v-if="searchQuery || filterType !== 'all' || filterExtension !== 'all' || filterDate !== 'all' || sortBy !== 'newest'"
                type="button"
                @click="resetFilters"
                class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-bold rounded-xl border border-rose-200 transition-all cursor-pointer flex items-center gap-1"
                title="Reset all search and filter settings"
              >
                <span>✕ Reset Filters</span>
              </button>
            </div>
          </div>

          <!-- Bulk Actions Floating Control Bar -->
          <div v-if="selectedMediaIds.length > 0" class="p-4 bg-slate-900 text-white rounded-2xl shadow-xl flex flex-wrap items-center justify-between gap-3 animate-in fade-in slide-in-from-top-2">
            <div class="flex items-center gap-3">
              <span class="px-2.5 py-1 bg-sky-500 text-white rounded-xl text-xs font-black">
                {{ selectedMediaIds.length }} Selected
              </span>
              <button
                type="button"
                @click="toggleSelectAll"
                class="text-xs font-bold text-slate-300 hover:text-white underline cursor-pointer"
              >
                {{ selectedMediaIds.length === mediaItems.length ? 'Deselect All' : 'Select All' }}
              </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <!-- Target Folder Selection for Bulk Move -->
              <div class="flex items-center gap-1.5 bg-slate-800 px-3 py-1.5 rounded-xl border border-slate-700 text-xs">
                <span class="text-slate-400">Move to:</span>
                <select v-model="targetBulkFolder" class="bg-transparent font-bold text-white focus:outline-none cursor-pointer">
                  <option v-for="f in selectableFolders" :key="f.id" :value="f.id" class="bg-slate-900 text-white">{{ f.label }}</option>
                </select>
              </div>

              <button
                type="button"
                @click="handleBulkMove"
                class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl shadow transition-all cursor-pointer flex items-center gap-1"
              >
                📁 Move
              </button>

              <button
                type="button"
                @click="handleBulkDelete"
                class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow transition-all cursor-pointer flex items-center gap-1"
              >
                🗑️ Delete Selected
              </button>

              <button
                type="button"
                @click="selectedMediaIds = []"
                class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-all cursor-pointer"
              >
                ✕ Clear
              </button>
            </div>
          </div>

          <!-- Upload Status / Error / Success Alerts -->
          <div v-if="uploadStatus" class="p-3.5 bg-sky-50 border border-sky-200 text-sky-800 text-xs font-bold rounded-xl flex items-center gap-2.5 animate-pulse">
            <span class="animate-spin text-sm">🔄</span>
            <span>{{ uploadStatus }}</span>
          </div>

          <div v-if="uploadError" class="p-3.5 bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold rounded-xl flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
              <span>⚠️</span>
              <span>{{ uploadError }}</span>
            </div>
            <button type="button" @click="uploadError = ''" class="text-rose-500 hover:text-rose-800 text-xs font-bold">✕</button>
          </div>

          <div v-if="copyToast" class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-xl flex items-center gap-2 animate-in fade-in">
            <span>✓</span>
            <span>{{ copyToast }}</span>
          </div>

          <!-- Files Grid -->
          <div>
            <div v-if="isLoading" class="flex items-center justify-center py-20 text-slate-400 text-xs font-semibold">
              <span class="animate-spin text-xl mr-2">🔄</span> Loading Media Library...
            </div>

            <div v-else-if="!mediaItems.length" class="text-center py-20 border-2 border-dashed border-slate-200 rounded-3xl bg-slate-50/50 space-y-3">
              <span class="text-4xl block">📁</span>
              <div>
                <span class="text-sm font-bold text-slate-800 block">No media files in this folder</span>
                <span class="text-xs text-slate-400">Drag & drop files here or click below to upload.</span>
              </div>
              <button
                type="button"
                @click="triggerUpload"
                class="px-4 py-2 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-xl border border-sky-200 transition-all inline-flex items-center gap-1.5 cursor-pointer mt-2"
              >
                <span>📤 Choose Files to Upload</span>
              </button>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div
                v-for="item in mediaItems"
                :key="item.id"
                :class="[
                  'group rounded-2xl border p-3 transition-all shadow-sm hover:shadow-md flex flex-col justify-between relative',
                  selectedMediaIds.includes(item.id)
                    ? 'bg-sky-50/80 border-sky-400 ring-2 ring-sky-300'
                    : 'bg-slate-50 hover:bg-slate-100/80 border-slate-200'
                ]"
              >
                <!-- Image or Icon Box -->
                <div
                  @click="openPreview(item)"
                  class="h-40 w-full rounded-xl overflow-hidden bg-white border border-slate-200 flex items-center justify-center relative mb-3 cursor-pointer group-hover:border-sky-300"
                >
                  <img
                    v-if="isImage(item.mime_type || item.file_name)"
                    :src="item.original_url"
                    :alt="item.alt_text || item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                  />
                  <div v-else class="flex flex-col items-center justify-center space-y-1">
                    <span class="text-4xl">{{ getFileIcon(item.mime_type || item.file_name) }}</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
                  </div>

                  <!-- Multi-Select Checkbox -->
                  <div class="absolute top-2 right-2 z-20" @click.stop>
                    <input
                      type="checkbox"
                      :checked="selectedMediaIds.includes(item.id)"
                      @change="toggleSelectItem(item.id)"
                      class="w-4 h-4 rounded text-sky-600 border-slate-300 focus:ring-sky-500 cursor-pointer shadow-sm"
                    />
                  </div>

                  <!-- Folder Collection Tag -->
                  <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-sm text-white text-[9px] font-bold uppercase tracking-wider shadow-sm">
                    {{ item.collection_name }}
                  </span>

                  <!-- Hover Inspect Overlay -->
                  <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-bold gap-1">
                    <span>🔍 Details & Edit</span>
                  </div>
                </div>

                <!-- File Info -->
                <div class="space-y-1 cursor-pointer" @click="openPreview(item)">
                  <div class="font-bold text-slate-900 text-xs truncate group-hover:text-sky-700" :title="item.name || item.file_name">
                    {{ item.name || item.file_name }}
                  </div>
                  <div v-if="item.alt_text" class="text-[10px] text-slate-500 font-semibold italic truncate">
                    Alt: "{{ item.alt_text }}"
                  </div>
                  <div class="flex items-center justify-between text-[10px] text-slate-500 font-semibold">
                    <span>{{ item.human_size }}</span>
                    <span>{{ item.created_at }}</span>
                  </div>
                </div>

                <!-- Card Bottom Controls -->
                <div class="pt-3 border-t border-slate-200/80 mt-3 flex items-center justify-between gap-1">
                  <button
                    type="button"
                    @click="openPreview(item)"
                    class="px-2.5 py-1 bg-white hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg border border-slate-300 transition-all cursor-pointer flex items-center gap-1"
                    title="View details & edit metadata"
                  >
                    🔍 Details
                  </button>

                  <button
                    type="button"
                    @click="copyUrl(item)"
                    class="px-2 py-1 text-slate-500 hover:text-sky-600 text-[11px] font-bold transition-colors cursor-pointer"
                    title="Copy direct file URL"
                  >
                    📋 Copy
                  </button>

                  <button
                    type="button"
                    @click="deleteItem(item)"
                    class="p-1 text-slate-400 hover:text-rose-600 text-xs transition-colors cursor-pointer"
                    title="Delete file"
                  >
                    🗑️
                  </button>
                </div>
              </div>
            </div>

          </div>

        </div>

      </div>

    </div>

    <!-- Media Asset Details & Inspector Modal -->
    <div v-if="previewItem" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="previewItem = null">
      <div class="bg-white rounded-3xl max-w-4xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative overflow-hidden" @click.stop>
        
        <!-- Modal Top Header -->
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div class="flex items-center gap-3">
            <span class="text-2xl">{{ getFileIcon(previewItem.mime_type || previewItem.file_name) }}</span>
            <div>
              <h3 class="text-lg font-black text-slate-900 tracking-tight">Media Asset Inspector</h3>
              <p class="text-xs text-slate-500">View asset, edit display name & alt text, or copy direct asset URL.</p>
            </div>
          </div>

          <button type="button" @click="previewItem = null" class="p-2 text-slate-400 hover:text-slate-700 font-bold rounded-xl transition-colors cursor-pointer text-sm">✕</button>
        </div>

        <!-- Success Toast Alert -->
        <div v-if="saveSuccessMsg" class="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-xl flex items-center gap-2 animate-in fade-in">
          <span>✓</span>
          <span>{{ saveSuccessMsg }}</span>
        </div>

        <!-- Main Inspector Layout: Left Visual Preview + Right Editable Metadata -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
          
          <!-- Left Column: Visual Image / File Preview Box & Technical Specs -->
          <div class="space-y-4">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 flex flex-col items-center justify-center min-h-[260px] max-h-[380px] overflow-hidden relative">
              <img
                v-if="isImage(previewItem.mime_type || previewItem.file_name)"
                :src="previewItem.original_url"
                :alt="previewItem.alt_text || previewItem.name"
                class="max-h-[320px] w-auto object-contain rounded-xl shadow-sm"
              />
              <div v-else class="py-12 text-center space-y-2">
                <span class="text-6xl block">{{ getFileIcon(previewItem.mime_type || previewItem.file_name) }}</span>
                <span class="text-sm font-extrabold text-slate-800 block">{{ previewItem.file_name }}</span>
                <span class="text-xs font-semibold text-slate-400 block">{{ previewItem.human_size }}</span>
              </div>
            </div>

            <!-- Technical File Specifications Table -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2 text-xs">
              <div class="font-extrabold text-slate-900 uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200/60">
                Technical Specifications
              </div>
              <div class="grid grid-cols-2 gap-2 text-slate-600 font-semibold">
                <div><span class="text-slate-400 block text-[10px]">Folder:</span> <span class="capitalize text-slate-900 font-bold">{{ previewItem.collection_name }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">File Size:</span> <span class="text-slate-900 font-bold">{{ previewItem.human_size }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">MIME Type:</span> <span class="text-slate-900 font-bold">{{ previewItem.mime_type }}</span></div>
                <div><span class="text-slate-400 block text-[10px]">Uploaded On:</span> <span class="text-slate-900 font-bold">{{ previewItem.created_at }}</span></div>
              </div>
            </div>

            <!-- Asset Usage Tracking Section -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2 text-xs">
              <div class="font-extrabold text-slate-900 uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200/60 flex items-center justify-between">
                <span>Asset Usage Tracking</span>
                <span v-if="isLoadingUsages" class="animate-spin text-sky-600">🔄</span>
                <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-black" :class="assetUsages.length ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">
                  {{ assetUsages.length }} {{ assetUsages.length === 1 ? 'Location' : 'Locations' }}
                </span>
              </div>

              <div v-if="isLoadingUsages" class="py-2 text-slate-400 font-semibold text-center">
                Checking asset references across news & settings...
              </div>

              <div v-else-if="!assetUsages.length" class="py-2 text-slate-400 font-medium italic text-center">
                This asset is not currently published on news articles or club settings.
              </div>

              <div v-else class="space-y-1.5 pt-1">
                <div
                  v-for="(u, idx) in assetUsages"
                  :key="idx"
                  class="p-2 bg-white rounded-xl border border-slate-200 flex items-center justify-between text-xs"
                >
                  <div class="truncate pr-2">
                    <span class="font-bold text-slate-900 block truncate">{{ u.title }}</span>
                    <span class="text-[10px] text-slate-400 font-medium">{{ u.location }}</span>
                  </div>
                  <span class="px-2 py-0.5 rounded bg-sky-50 text-sky-700 text-[10px] font-bold border border-sky-200 shrink-0">
                    {{ u.type }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Column: Editable Metadata Form -->
          <div class="space-y-4 bg-slate-50/50 p-5 rounded-2xl border border-slate-200">
            <div class="font-extrabold text-slate-900 uppercase tracking-wider text-[10px] text-slate-400 pb-1 border-b border-slate-200">
              Editable Asset Metadata
            </div>

            <!-- 1. Display Title / Name -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800">Display Title / Asset Name</label>
              <input
                v-model="previewItem.name"
                type="text"
                placeholder="Enter display title..."
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
              />
            </div>

            <!-- 2. Alt Text (Accessibility & SEO) -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800 flex items-center justify-between">
                <span>Alt Text (Accessibility & SEO)</span>
                <span class="text-[10px] text-sky-600 font-bold">Auto-generated from filename</span>
              </label>
              <input
                v-model="previewItem.alt_text"
                type="text"
                placeholder="Descriptive alt text for screen readers..."
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
              />
            </div>

            <!-- 3. Caption / Description -->
            <div class="space-y-1">
              <label class="block text-xs font-extrabold text-slate-800">Caption / Description</label>
              <textarea
                v-model="previewItem.caption"
                rows="3"
                placeholder="Add optional caption or description..."
                class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm resize-none"
              ></textarea>
            </div>

            <!-- 4. Direct Asset Link Box -->
            <div class="space-y-1 pt-1">
              <label class="block text-xs font-extrabold text-slate-800">Direct Asset Link (URL)</label>
              <div class="flex items-center gap-2">
                <input
                  :value="previewItem.original_url"
                  readonly
                  type="text"
                  class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-xl text-[11px] font-mono text-slate-600 focus:outline-none select-all"
                />
                <button
                  type="button"
                  @click="copyUrl(previewItem)"
                  class="px-3 py-2 bg-white hover:bg-slate-100 text-slate-800 text-xs font-bold rounded-xl border border-slate-200 shadow-sm transition-all shrink-0 cursor-pointer flex items-center gap-1"
                >
                  📋 Copy
                </button>
              </div>
            </div>

            <!-- Save Metadata Button -->
            <div class="pt-2">
              <button
                type="button"
                @click="saveMediaDetails"
                :disabled="isSavingDetails"
                class="w-full py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
              >
                <span v-if="isSavingDetails" class="animate-spin">🔄</span>
                <span v-else>💾</span>
                <span>{{ isSavingDetails ? 'Saving Metadata...' : 'Save Asset Details' }}</span>
              </button>
            </div>
          </div>

        </div>

        <!-- Modal Bottom Actions Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-100 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <button
              v-if="isImage(previewItem.mime_type || previewItem.file_name)"
              type="button"
              @click="openCropper"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition-all inline-flex items-center gap-1.5 cursor-pointer"
            >
              <span>✂️ Visual Crop & Resize</span>
            </button>

            <a
              :href="previewItem.original_url"
              download
              class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl border border-slate-300 transition-all inline-flex items-center gap-1.5"
            >
              <span>⬇️ Download File</span>
            </a>
            <a
              :href="previewItem.original_url"
              target="_blank"
              class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 font-bold rounded-xl border border-slate-200 transition-all inline-flex items-center gap-1.5"
            >
              <span>↗️ Open in New Tab</span>
            </a>
          </div>

          <button
            type="button"
            @click="deleteItem(previewItem)"
            class="px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold rounded-xl border border-rose-200 transition-all cursor-pointer inline-flex items-center gap-1.5"
          >
            <span>🗑️ Delete Asset</span>
          </button>
        </div>

      </div>
    </div>

    <!-- Visual Image Cropper Modal -->
    <div v-if="showCropModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 backdrop-blur-md p-4 overflow-y-auto" @click="closeCropper">
      <div class="bg-white rounded-3xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl border border-slate-200 space-y-6 relative overflow-hidden" @click.stop>
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="text-2xl">✂️</span>
            <div>
              <h3 class="text-lg font-black text-slate-900">Interactive Image Cropper & Resizer</h3>
              <p class="text-xs text-slate-500">Drag to move crop area or grab corner handles to adjust crop box size.</p>
            </div>
          </div>
          <button type="button" @click="closeCropper" class="p-2 text-slate-400 hover:text-slate-700 font-bold rounded-xl text-sm cursor-pointer">✕</button>
        </div>

        <!-- Aspect Ratio Presets Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-200">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-extrabold text-slate-500 uppercase tracking-wider">Presets:</span>
            <button
              type="button"
              @click="setCropAspect('free')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === 'free' ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100']"
            >
              Free Crop
            </button>
            <button
              type="button"
              @click="setCropAspect('1:1')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '1:1' ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100']"
            >
              1:1 Square (Logo)
            </button>
            <button
              type="button"
              @click="setCropAspect('16:9')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '16:9' ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100']"
            >
              16:9 Banner
            </button>
            <button
              type="button"
              @click="setCropAspect('4:3')"
              :class="['px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer', cropAspect === '4:3' ? 'bg-slate-900 text-white shadow' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-100']"
            >
              4:3 Gallery
            </button>
          </div>

          <button
            type="button"
            @click="rotateCropper(90)"
            class="px-3 py-1.5 bg-white hover:bg-slate-100 text-slate-800 text-xs font-bold rounded-xl border border-slate-200 transition-all cursor-pointer flex items-center gap-1"
          >
            🔄 Rotate 90°
          </button>
        </div>

        <!-- Canvas / Image Container for CropperJS -->
        <div class="bg-slate-900 rounded-2xl p-4 flex items-center justify-center min-h-[350px] max-h-[460px] overflow-hidden relative">
          <div class="max-h-[420px] w-full flex items-center justify-center">
            <img
              ref="cropSourceImageRef"
              :src="previewItem?.original_url"
              alt="Source image for cropping"
              class="max-h-[420px] max-w-full block"
            />
          </div>
        </div>

        <!-- Cropper Action Footer -->
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <button
            type="button"
            @click="closeCropper"
            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="applyCrop"
            :disabled="isCropping"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-2 disabled:opacity-50"
          >
            <span v-if="isCropping" class="animate-spin text-sm">🔄</span>
            <span v-else>✂️</span>
            <span>{{ isCropping ? 'Cropping & Saving...' : 'Save & Apply Crop' }}</span>
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
