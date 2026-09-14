<script setup>
import { ref, watch, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
    if (activeFolder.value !== 'all') {
      params.append('folder', activeFolder.value);
    }
    if (searchQuery.value) {
      params.append('search', searchQuery.value);
    }
    const res = await fetch(`/clubs/${props.club.slug}/admin/media?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      mediaItems.value = data.media || [];
    }
  } catch (err) {
    console.error('Failed to load media library items:', err);
  } finally {
    isLoading.value = false;
  }
};

onMounted(() => {
  fetchMedia();
});

watch([activeFolder, searchQuery], () => {
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
  let successCount = 0;

  for (let i = 0; i < files.length; i++) {
    const file = files[i];
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
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">File Manager & Spatie Media Library</h1>
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

          <!-- Search & Filter Controls -->
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div class="relative flex-1 max-w-md">
              <input
                v-model="searchQuery"
                type="text"
                placeholder="Search files by name..."
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
              />
              <span class="absolute left-3.5 top-3 text-slate-400 text-xs">🔍</span>
            </div>

            <div class="text-xs font-bold text-slate-500">
              Showing {{ mediaItems.length }} {{ mediaItems.length === 1 ? 'file' : 'files' }} in <span class="text-slate-900 font-extrabold capitalize">{{ activeFolder }}</span>
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
                class="group bg-slate-50 hover:bg-slate-100/80 rounded-2xl border border-slate-200 p-3 transition-all shadow-sm hover:shadow-md flex flex-col justify-between"
              >
                <!-- Image or Icon Box -->
                <div
                  @click="previewItem = item"
                  class="h-40 w-full rounded-xl overflow-hidden bg-white border border-slate-200 flex items-center justify-center relative mb-3 cursor-pointer"
                >
                  <img
                    v-if="isImage(item.mime_type || item.file_name)"
                    :src="item.original_url"
                    :alt="item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                  />
                  <div v-else class="flex flex-col items-center justify-center space-y-1">
                    <span class="text-4xl">{{ getFileIcon(item.mime_type || item.file_name) }}</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
                  </div>

                  <!-- Folder Collection Tag -->
                  <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-slate-900/80 backdrop-blur-sm text-white text-[9px] font-bold uppercase tracking-wider shadow-sm">
                    {{ item.collection_name }}
                  </span>
                </div>

                <!-- File Info -->
                <div class="space-y-1">
                  <div class="font-bold text-slate-900 text-xs truncate" :title="item.file_name">
                    {{ item.file_name }}
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
                    @click="copyUrl(item)"
                    class="px-2.5 py-1 bg-white hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg border border-slate-300 transition-all cursor-pointer flex items-center gap-1"
                    title="Copy direct file URL"
                  >
                    📋 Copy URL
                  </button>

                  <a
                    :href="item.original_url"
                    target="_blank"
                    class="px-2 py-1 text-slate-500 hover:text-sky-600 text-[11px] font-bold transition-colors"
                    title="Open file in new tab"
                  >
                    ↗️ Open
                  </a>

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

    <!-- Image Preview Modal -->
    <div v-if="previewItem" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4" @click="previewItem = null">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 relative" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="font-bold text-slate-900 text-sm truncate">{{ previewItem.file_name }}</div>
          <button type="button" @click="previewItem = null" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <div class="flex items-center justify-center bg-slate-50 rounded-2xl p-2 border border-slate-200 max-h-[60vh] overflow-hidden">
          <img v-if="isImage(previewItem.mime_type || previewItem.file_name)" :src="previewItem.original_url" class="max-h-[55vh] object-contain rounded-xl" />
          <div v-else class="py-12 text-center">
            <span class="text-5xl block mb-2">{{ getFileIcon(previewItem.mime_type || previewItem.file_name) }}</span>
            <span class="text-xs font-bold text-slate-700 block">{{ previewItem.file_name }}</span>
            <span class="text-[11px] text-slate-400 block mt-1">{{ previewItem.human_size }}</span>
          </div>
        </div>

        <div class="flex items-center justify-between pt-2 text-xs">
          <button
            type="button"
            @click="copyUrl(previewItem)"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold rounded-xl border border-indigo-200 transition-all cursor-pointer flex items-center gap-1"
          >
            📋 Copy File URL
          </button>
          <a
            :href="previewItem.original_url"
            target="_blank"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl border border-slate-300 transition-all inline-flex items-center gap-1"
          >
            ↗️ Download / View File
          </a>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
