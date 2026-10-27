<script setup>
import { ref, watch, computed } from 'vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
// The server sends the files a page at a time; `totalFiles` counts every file the filters match.
const currentPage = ref(1);
const hasMore = ref(false);
const totalFiles = ref(0);
const isLoadingMore = ref(false);
const isLoading = ref(false);
const isUploading = ref(false);
const uploadFileInput = ref(null);

const filterType = ref('all');
const filterExtension = ref('all');
const filterDate = ref('all');
const sortBy = ref('newest');

const availableExtensions = ref([]);
const availableMonths = ref([]);
const trashCount = ref(0);
const customFolders = ref([]); // [{ id, name, slug }], from the server

// The fixed folders. Custom folders are never listed here directly — each one lives
// one level inside a fixed folder (see subfoldersOf / activeCustomFolder).
const fixedFolders = [
  { id: 'all', label: 'All files', icon: 'folder', bg: 'bg-slate-100 dark:bg-slate-800', text: 'text-slate-700 dark:text-slate-200' },
  { id: 'summons', label: 'Summonses', icon: 'mail', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'logos', label: 'Logos', icon: 'star', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'news', label: 'News items', icon: 'document', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'events', label: 'Events', icon: 'calendar', bg: 'bg-amber-50 dark:bg-amber-950/40', text: 'text-amber-700 dark:text-amber-300' },
  { id: 'updates', label: 'Updates', icon: 'document', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'newsletters', label: 'Newsletters', icon: 'mail', bg: 'bg-emerald-50 dark:bg-emerald-950/40', text: 'text-emerald-700 dark:text-emerald-300' },
  { id: 'pages', label: 'CMS pages', icon: 'globe', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'images', label: 'Single images', icon: 'photo', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'galleries', label: 'Galleries', icon: 'photo', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'documents', label: 'Documents', icon: 'document', bg: 'bg-amber-50 dark:bg-amber-950/40', text: 'text-amber-700 dark:text-amber-300' },
];

const folders = computed(() => [
  ...fixedFolders,
  { id: 'trash', label: 'Trash bin', icon: 'trash', bg: 'bg-rose-50 dark:bg-rose-950/40', text: 'text-rose-700 dark:text-rose-300' },
]);

const subfoldersOf = (fixedId) => customFolders.value.filter(f => f.parent_slug === fixedId);
const activeCustomFolder = computed(() => customFolders.value.find(f => f.slug === activeFolder.value) || null);
const fixedFolderById = (id) => fixedFolders.find(f => f.id === id);

const fetchMedia = async (append = false) => {
  if (!props.clubSlug) return;
  if (append) isLoadingMore.value = true; else isLoading.value = true;
  try {
    const params = new URLSearchParams();
    if (activeFolder.value !== 'all') params.append('folder', activeFolder.value);
    if (searchQuery.value) params.append('search', searchQuery.value);
    if (filterType.value !== 'all') params.append('type', filterType.value);
    if (filterExtension.value !== 'all') params.append('extension', filterExtension.value);
    if (filterDate.value !== 'all') params.append('date', filterDate.value);
    if (sortBy.value !== 'newest') params.append('sort', sortBy.value);
    const page = append ? currentPage.value + 1 : 1;
    params.append('page', page);

    const res = await fetch(`/${props.clubSlug}/admin/media?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      mediaItems.value = append ? [...mediaItems.value, ...(data.media || [])] : (data.media || []);
      currentPage.value = page;
      hasMore.value = !!data.has_more;
      totalFiles.value = data.total ?? mediaItems.value.length;
      trashCount.value = data.trash_count || 0;
      availableExtensions.value = data.available_extensions || [];
      availableMonths.value = data.available_months || [];
      customFolders.value = data.custom_folders || [];
    }
  } catch (err) {
    console.error('Failed to load media library items:', err);
  } finally {
    isLoading.value = false;
    isLoadingMore.value = false;
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
const uploadStatus = ref('');
const uploadError = ref('');

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
      const res = await fetch(`/${props.clubSlug}/admin/media`, {
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

const restoreItem = async (item, e) => {
  e.stopPropagation();
  try {
    const res = await fetch(`/${props.clubSlug}/admin/media/${item.id}/restore`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      trashCount.value = Math.max(0, trashCount.value - 1);
    }
  } catch (err) {
    console.error('Failed to restore media item:', err);
  }
};

const forceDeleteItem = async (item, e) => {
  e.stopPropagation();
  if (!confirm(`Are you sure you want to PERMANENTLY delete "${item.file_name}"?`)) return;

  try {
    const res = await fetch(`/${props.clubSlug}/admin/media/${item.id}/force`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      trashCount.value = Math.max(0, trashCount.value - 1);
    }
  } catch (err) {
    console.error('Failed to force delete media item:', err);
  }
};

const deleteItem = async (item, e) => {
  e.stopPropagation();
  if (!confirm(`Move "${item.file_name}" to Trash bin?`)) return;

  try {
    const res = await fetch(`/${props.clubSlug}/admin/media/${item.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      trashCount.value++;
    }
  } catch (err) {
    console.error('Failed to delete media item:', err);
  }
};

const getFileIcon = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return 'document';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.webp')) return 'photo';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return 'chart';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return 'document';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return 'archive';
  return 'link';
};

const isImage = (mimeOrUrl) => {
  const val = (mimeOrUrl || '').toLowerCase();
  return val.includes('image') || val.match(/\.(jpg|jpeg|png|webp|gif|svg)$/);
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="media-library-modal-title"
      v-focus-trap="() => emit('close')"
      class="bg-white dark:bg-slate-900 rounded-2xl max-w-5xl w-full max-h-[90vh] overflow-hidden shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col outline-none"
    >

      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-start justify-between gap-4">
        <div class="min-w-0">
          <h2 id="media-library-modal-title" class="text-base font-semibold text-slate-900 dark:text-white">File manager</h2>
          <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Choose a file, or upload a new one.</p>
        </div>

        <div class="flex items-center gap-2">
          <!-- Upload Button -->
          <Button size="sm" :loading="isUploading" @click="triggerUpload">
            <template v-if="!isUploading" #icon><UiIcon name="upload" /></template>
            Upload files
          </Button>
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
            aria-label="Close file manager"
            class="shrink-0 rounded-xl p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
          >
            <UiIcon name="x" :size="20" />
          </button>
        </div>
      </div>

      <!-- Modal Body (Sidebar + Content Area) -->
      <div class="flex-1 flex flex-col md:flex-row overflow-hidden">

        <!-- Folder Navigation Sidebar -->
        <div class="w-full md:w-64 bg-slate-50 dark:bg-slate-800/50 border-r border-slate-200 dark:border-slate-800 p-4 space-y-1 overflow-y-auto shrink-0">
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider px-3 py-1">
            Folders
          </div>

          <button
            v-for="folder in folders"
            :key="folder.id"
            type="button"
            @click="activeFolder = folder.id"
            :aria-current="activeFolder === folder.id ? 'true' : undefined"
            :class="[
              'w-full flex items-center justify-between px-3 py-2 rounded-xl text-sm font-medium transition-colors text-left',
              activeFolder === folder.id
                ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300'
                : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
            ]"
          >
            <span class="flex items-center gap-2">
              <UiIcon :name="folder.icon" />
              <span>{{ folder.label }}</span>
            </span>
            <Badge v-if="folder.id === 'trash' && trashCount > 0">{{ trashCount }}</Badge>
          </button>
        </div>

        <!-- Main Media Grid Area -->
        <div class="flex-1 flex flex-col p-6 space-y-4 overflow-hidden bg-white dark:bg-slate-900">

          <!-- Breadcrumb: viewing a custom folder, inside one of the fixed folders -->
          <nav v-if="activeCustomFolder" aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">
            <button
              type="button"
              @click="activeFolder = activeCustomFolder.parent_slug"
              class="hover:text-slate-900 dark:hover:text-white hover:underline flex items-center gap-1"
            >
              <UiIcon :name="fixedFolderById(activeCustomFolder.parent_slug)?.icon || 'folder'" />
              <span>{{ fixedFolderById(activeCustomFolder.parent_slug)?.label }}</span>
            </button>
            <span class="text-slate-300 dark:text-slate-600" aria-hidden="true">/</span>
            <span class="flex items-center gap-1 text-slate-900 dark:text-white" aria-current="page"><UiIcon name="folder" /> {{ activeCustomFolder.name }}</span>
          </nav>

          <!-- Subfolders: viewing one of the fixed folders (read-only here — no creation) -->
          <div v-if="activeFolder !== 'all' && activeFolder !== 'trash' && !activeCustomFolder && subfoldersOf(activeFolder).length" class="flex flex-wrap gap-1.5 shrink-0">
            <button
              v-for="sub in subfoldersOf(activeFolder)"
              :key="sub.id"
              type="button"
              @click="activeFolder = sub.slug"
              class="px-3 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium rounded-full flex items-center gap-1.5"
            >
              <UiIcon name="folder" />
              <span>{{ sub.name }}</span>
            </button>
          </div>

          <!-- Top Search & Inline Filter Controls Bar -->
          <div class="space-y-3 pb-3 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center justify-between gap-3">
              <!-- Search Box -->
              <div class="relative flex-1">
                <label for="media-library-search" class="sr-only">Search files by name</label>
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><UiIcon name="search" /></span>
                <input
                  id="media-library-search"
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search files by name"
                  class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-9 pr-9 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500"
                />
                <button
                  v-if="searchQuery"
                  type="button"
                  @click="searchQuery = ''"
                  aria-label="Clear search"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded-xl p-1 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200"
                >
                  <UiIcon name="x" />
                </button>
              </div>

              <!-- Counter -->
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">
                Showing {{ mediaItems.length }}<template v-if="hasMore"> of {{ totalFiles }}</template> {{ totalFiles === 1 ? 'file' : 'files' }}
              </div>
            </div>

            <!-- Inline Filter Dropdowns Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 items-end">
              <Select
                v-model="filterType"
                label="Type"
                :options="[{ value: 'all', label: 'All types' }, { value: 'image', label: 'Images only' }, { value: 'document', label: 'Documents only' }]"
              />

              <!-- Only the extensions the club's files actually have -->
              <Select
                v-model="filterExtension"
                label="Extension"
                :options="[{ value: 'all', label: 'All extensions' }, ...availableExtensions.map(ext => ({ value: ext, label: `.${ext.toUpperCase()}` }))]"
              />

              <Select
                v-model="filterDate"
                label="Date added"
                :options="[{ value: 'all', label: 'All dates' }, ...availableMonths.map(m => ({ value: m.value, label: m.label }))]"
              />

              <Select
                v-model="sortBy"
                label="Sort"
                :options="[
                  { value: 'newest', label: 'Newest first' },
                  { value: 'oldest', label: 'Oldest first' },
                  { value: 'name_asc', label: 'Name (A to Z)' },
                  { value: 'name_desc', label: 'Name (Z to A)' },
                  { value: 'size_desc', label: 'Size (largest)' },
                  { value: 'size_asc', label: 'Size (smallest)' },
                ]"
              />
            </div>

            <div v-if="searchQuery || filterType !== 'all' || filterExtension !== 'all' || filterDate !== 'all' || sortBy !== 'newest'">
              <Button variant="ghost" size="sm" title="Reset all search and filter settings" @click="resetFilters">
                <template #icon><UiIcon name="x" /></template>
                Reset filters
              </Button>
            </div>
          </div>

          <!-- Media Files Scrollable Grid -->
          <div class="flex-1 overflow-y-auto">

            <div v-if="isLoading" class="flex items-center justify-center gap-2 py-16 text-slate-500 dark:text-slate-400 text-sm">
              <UiIcon name="refresh" class="animate-spin" /> Loading files…
            </div>

            <div v-else-if="!mediaItems.length" class="text-center py-16 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/50 dark:bg-slate-800/50 space-y-1">
              <span class="flex justify-center text-slate-400 mb-2"><UiIcon name="folder" :size="20" /></span>
              <span class="text-sm font-medium text-slate-900 dark:text-white block">No files in this folder</span>
              <span class="text-xs text-slate-500 dark:text-slate-400">Use "Upload files" above to add one.</span>
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 p-1">
              <div
                v-for="item in mediaItems"
                :key="item.id"
                @click="selectItem(item)"
                class="group relative bg-white dark:bg-slate-900 hover:bg-blue-50/60 dark:hover:bg-blue-950/40 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 p-2.5 transition-all cursor-pointer shadow-sm hover:shadow-md flex flex-col justify-between overflow-hidden"
              >
                <!-- Thumbnail Preview -->
                <div class="h-28 w-full rounded-xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800/80 flex items-center justify-center relative mb-2">
                  <img
                    v-if="isImage(item.mime_type || item.file_name)"
                    :src="item.original_url"
                    :alt="item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                  />
                  <div v-else class="flex flex-col items-center justify-center space-y-1">
                    <span class="text-slate-400"><UiIcon :name="getFileIcon(item.mime_type || item.file_name)" :size="20" /></span>
                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
                  </div>

                  <!-- Folder Collection Badge & Variant / Trashed Tags -->
                  <div class="absolute top-1.5 left-1.5 flex flex-col gap-1 items-start z-10">
                    <Badge>{{ item.collection_name }}</Badge>
                    <Badge v-if="item.is_variant" variant="info">Variant</Badge>
                    <Badge v-if="item.is_trashed" variant="danger">Trashed</Badge>
                  </div>
                </div>

                <!-- File Info -->
                <div class="space-y-1">
                  <span class="block font-medium text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300 text-xs truncate" :title="item.file_name">
                    {{ item.file_name }}
                  </span>
                  <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ item.human_size }}</span>
                    <span>{{ item.created_at }}</span>
                  </div>
                </div>

                <!-- Action Button Overlay -->
                <div class="pt-2 flex items-center justify-between border-t border-slate-200 dark:border-slate-800 mt-2">
                  <span v-if="activeFolder !== 'trash' && !item.is_trashed" class="text-xs font-medium text-blue-600 dark:text-blue-400 group-hover:underline flex items-center gap-1">
                    <UiIcon name="check" /> Select file
                  </span>
                  <Badge v-else variant="danger">In the trash</Badge>

                  <div class="flex items-center gap-1">
                    <template v-if="activeFolder === 'trash' || item.is_trashed">
                      <button
                        type="button"
                        @click="restoreItem(item, $event)"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-2 py-0.5 text-[11px] font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        title="Restore file"
                      >
                        <UiIcon name="restore" />
                        Restore
                      </button>
                      <button
                        type="button"
                        @click="forceDeleteItem(item, $event)"
                        class="rounded-xl p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                        title="Delete permanently"
                        aria-label="Delete permanently"
                      >
                        <UiIcon name="trash" />
                      </button>
                    </template>
                    <template v-else>
                      <button
                        type="button"
                        @click="deleteItem(item, $event)"
                        class="rounded-xl p-1 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                        title="Move to trash"
                        aria-label="Move to trash"
                      >
                        <UiIcon name="trash" />
                      </button>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="hasMore && !isLoading" class="flex justify-center pt-3">
              <Button variant="secondary" size="sm" :disabled="isLoadingMore" @click="fetchMedia(true)">
                {{ isLoadingMore ? 'Loading…' : `Load more files (${mediaItems.length} of ${totalFiles})` }}
              </Button>
            </div>

          </div>

        </div>

      </div>

    </div>
  </div>
</template>
