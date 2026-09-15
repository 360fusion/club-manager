<script setup>
import { ref, watch, computed } from 'vue';

const props = defineProps({
  show: Boolean,
  clubSlug: String,
  defaultFolder: {
    type: String,
    default: 'all',
  },
});

const emit = defineEmits(['close', 'select']);

const activeFolder = ref(props.defaultFolder || 'all');
const searchQuery = ref('');
const mediaItems = ref([]);
const isLoading = ref(false);
const isUploading = ref(false);
const uploadFileInput = ref(null);

const filterType = ref('all');
const filterExtension = ref('all');
const filterDate = ref('all');
const sortBy = ref('newest');

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

const fetchMedia = async () => {
  if (!props.clubSlug) return;
  isLoading.value = true;
  try {
    const params = new URLSearchParams();
    if (activeFolder.value !== 'all') params.append('folder', activeFolder.value);
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (filterType.value !== 'all') params.append('type', filterType.value);
    if (filterExtension.value !== 'all') params.append('extension', filterExtension.value);
    if (filterDate.value !== 'all') params.append('date', filterDate.value);
    if (sortBy.value !== 'newest') params.append('sort', sortBy.value);

    const res = await fetch(`/clubs/${props.clubSlug}/admin/media?${params.toString()}`);
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

watch(() => props.show, (newVal) => {
  if (newVal) {
    activeFolder.value = props.defaultFolder || 'all';
    resetFilters();
    fetchMedia();
  }
});

watch([activeFolder, searchQuery, filterType, filterExtension, filterDate, sortBy], () => {
  if (props.show) {
    fetchMedia();
  }
});

const uploadFolder = ref('images');
const isDragging = ref(false);
const uploadStatus = ref('');
const uploadError = ref('');

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
      const res = await fetch(`/clubs/${props.clubSlug}/admin/media`, {
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

const selectItem = (item) => {
  emit('select', {
    id: item.id,
    name: item.name,
    file_name: item.file_name,
    url: item.original_url,
    mime_type: item.mime_type,
    size: item.human_size,
    collection_name: item.collection_name,
  });
  emit('close');
};

const deleteItem = async (item, e) => {
  e.stopPropagation();
  if (!confirm(`Are you sure you want to delete "${item.file_name}" from the media library?`)) return;

  try {
    const res = await fetch(`/clubs/${props.clubSlug}/admin/media/${item.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
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
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-5xl w-full max-h-[90vh] overflow-hidden shadow-2xl border border-slate-200 flex flex-col">
      
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between bg-slate-50/80">
        <div class="flex items-center gap-2.5">
          <span class="text-xl">📁</span>
          <div>
            <h3 class="text-base font-extrabold text-slate-900">File Manager</h3>
            <p class="text-xs text-slate-500">Centralized file repository for logos, news, newsletters, galleries, and documents.</p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Upload Button -->
          <button
            type="button"
            @click="triggerUpload"
            :disabled="isUploading"
            class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50"
          >
            <span v-if="isUploading" class="animate-spin">⏳</span>
            <span v-else>📤</span>
            <span>Upload New File</span>
          </button>
          <input
            ref="uploadFileInput"
            type="file"
            multiple
            class="hidden"
            @change="handleFileUpload"
          />

          <!-- Close Modal -->
          <button
            type="button"
            @click="emit('close')"
            class="p-2 text-slate-400 hover:text-slate-700 font-bold rounded-xl transition-colors cursor-pointer text-sm"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- Modal Body (Sidebar + Content Area) -->
      <div class="flex-1 flex flex-col md:flex-row overflow-hidden">
        
        <!-- Folder Navigation Sidebar -->
        <div class="w-full md:w-64 bg-slate-50 border-r border-slate-200 p-4 space-y-1 overflow-y-auto shrink-0">
          <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider px-3 py-1">
            Media Folders
          </div>

          <button
            v-for="folder in folders"
            :key="folder.id"
            type="button"
            @click="activeFolder = folder.id"
            :class="[
              'w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all text-left cursor-pointer',
              activeFolder === folder.id
                ? 'bg-slate-900 text-white shadow-sm'
                : 'text-slate-700 hover:bg-slate-200/60'
            ]"
          >
            <span class="flex items-center gap-2">
              <span>{{ folder.icon }}</span>
              <span>{{ folder.label }}</span>
            </span>
          </button>
        </div>

        <!-- Main Media Grid Area -->
        <div class="flex-1 flex flex-col p-6 space-y-4 overflow-hidden bg-white">
          
          <!-- Top Search & Inline Filter Controls Bar -->
          <div class="space-y-3 pb-3 border-b border-slate-100">
            <div class="flex items-center justify-between gap-3">
              <!-- Search Box -->
              <div class="relative flex-1">
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search files by name..."
                  class="w-full pl-9 pr-8 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-sky-500"
                />
                <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                <button
                  v-if="searchQuery"
                  type="button"
                  @click="searchQuery = ''"
                  class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-700 text-xs font-bold"
                >
                  ✕
                </button>
              </div>

              <!-- Counter -->
              <div class="text-xs font-bold text-slate-500 shrink-0">
                Showing {{ mediaItems.length }} {{ mediaItems.length === 1 ? 'file' : 'files' }}
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

              <!-- 2. Dynamic Extension Filter (Only available extensions listed) -->
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
                class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-bold rounded-xl border border-rose-200 transition-all cursor-pointer flex items-center gap-1"
                title="Reset all search and filter settings"
              >
                <span>✕ Reset</span>
              </button>
            </div>
          </div>

          <!-- Media Files Scrollable Grid -->
          <div class="flex-1 overflow-y-auto">
            
            <div v-if="isLoading" class="flex items-center justify-center py-16 text-slate-400 text-xs font-semibold">
              <span class="animate-spin text-lg mr-2">🔄</span> Loading Media Library...
            </div>

            <div v-else-if="!mediaItems.length" class="text-center py-16 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
              <span class="text-3xl block mb-2">📁</span>
              <span class="text-xs font-bold text-slate-700 block">No media files in this folder</span>
              <span class="text-[11px] text-slate-400">Click "Upload New File" above to add files to the media library.</span>
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-1">
              <div
                v-for="item in mediaItems"
                :key="item.id"
                @click="selectItem(item)"
                class="group relative bg-slate-50 hover:bg-sky-50/60 rounded-2xl border border-slate-200/90 hover:border-sky-300 p-2.5 transition-all cursor-pointer shadow-sm hover:shadow-md flex flex-col justify-between overflow-hidden"
              >
                <!-- Thumbnail Preview -->
                <div class="h-28 w-full rounded-xl overflow-hidden bg-white border border-slate-200/80 flex items-center justify-center relative mb-2">
                  <img
                    v-if="isImage(item.mime_type || item.file_name)"
                    :src="item.original_url"
                    :alt="item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                  />
                  <div v-else class="flex flex-col items-center justify-center space-y-1">
                    <span class="text-3xl">{{ getFileIcon(item.mime_type || item.file_name) }}</span>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
                  </div>

                  <!-- Folder Collection Badge -->
                  <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-md bg-slate-900/75 backdrop-blur-sm text-white text-[9px] font-bold uppercase tracking-wider">
                    {{ item.collection_name }}
                  </span>
                </div>

                <!-- File Info -->
                <div class="space-y-1">
                  <span class="block font-bold text-slate-900 group-hover:text-sky-700 text-xs truncate" :title="item.file_name">
                    {{ item.file_name }}
                  </span>
                  <div class="flex items-center justify-between text-[10px] text-slate-500 font-medium">
                    <span>{{ item.human_size }}</span>
                    <span>{{ item.created_at }}</span>
                  </div>
                </div>

                <!-- Action Button Overlay -->
                <div class="pt-2 flex items-center justify-between border-t border-slate-200/60 mt-2">
                  <span class="text-[11px] font-bold text-sky-600 group-hover:underline flex items-center gap-1">
                    <span>✓</span> Select File
                  </span>
                  <button
                    type="button"
                    @click="deleteItem(item, $event)"
                    class="text-slate-400 hover:text-rose-600 p-1 text-xs cursor-pointer"
                    title="Delete File"
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
  </div>
</template>
