<script setup>
import MediaDetailsModal from '@/Components/Media/MediaDetailsModal.vue';
import MediaCropModal from '@/Components/Media/MediaCropModal.vue';
import { useClipboard } from '@/Composables/useClipboard';
import { ref, watch, onMounted, nextTick, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
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
const isLoadingMore = ref(false);
// The server sends the files a page at a time; `totalFiles` counts every file the filters match.
const currentPage = ref(1);
const hasMore = ref(false);
const totalFiles = ref(0);
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
const previewError = ref('');

const availableExtensions = ref([]);
const availableMonths = ref([]);
const trashCount = ref(0);
const customFolders = ref([]); // [{ id, name, slug }], from the server

const showNewFolderForm = ref(false);
const newFolderName = ref('');
const isCreatingFolder = ref(false);
const newFolderError = ref('');

// The 11 fixed folders. Custom folders are never listed here directly — each one
// lives one level inside a fixed folder (see subfoldersOf / activeCustomFolder).
const fixedFolders = [
  { id: 'all', label: 'All files', icon: 'folder', bg: 'bg-slate-100 dark:bg-slate-800', text: 'text-slate-700 dark:text-slate-200' },
  { id: 'summons', label: 'Summonses', icon: 'mail', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'logos', label: 'Logos', icon: 'star', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'news', label: 'News items', icon: 'document', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'newsletters', label: 'Newsletters', icon: 'mail', bg: 'bg-emerald-50 dark:bg-emerald-950/40', text: 'text-emerald-700 dark:text-emerald-300' },
  { id: 'images', label: 'Single images', icon: 'photo', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'galleries', label: 'Galleries', icon: 'photo', bg: 'bg-blue-50 dark:bg-blue-950/40', text: 'text-blue-700 dark:text-blue-300' },
  { id: 'documents', label: 'Documents', icon: 'document', bg: 'bg-amber-50 dark:bg-amber-950/40', text: 'text-amber-700 dark:text-amber-300' },
];

const folders = computed(() => [
  ...fixedFolders,
  { id: 'trash', label: 'Trash bin', icon: 'trash', bg: 'bg-rose-50 dark:bg-rose-950/40', text: 'text-rose-700 dark:text-rose-300' },
]);

// Direct custom-folder children of a fixed folder id, for the subfolder strip.
const subfoldersOf = (fixedId) => customFolders.value.filter(f => f.parent_slug === fixedId);

// If activeFolder is itself a custom folder's slug, its record (for the breadcrumb).
const activeCustomFolder = computed(() => customFolders.value.find(f => f.slug === activeFolder.value) || null);

const fixedFolderById = (id) => fixedFolders.find(f => f.id === id);

const activeFolderLabel = computed(() => activeCustomFolder.value?.name || fixedFolderById(activeFolder.value)?.label || activeFolder.value);

const selectableFolders = computed(() => fixedFolders
  .filter(f => f.id !== 'all')
  .flatMap(f => [
    { id: f.id, label: f.label },
    ...subfoldersOf(f.id).map(c => ({ id: c.slug, label: `— ${c.name}` })),
  ]));

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

const fetchMedia = async (append = false) => {
  if (!props.club?.slug) return;
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

    const res = await fetch(`/${props.club.slug}/admin/media?${params.toString()}`);
    if (res.ok) {
      const data = await res.json();
      mediaItems.value = append ? [...mediaItems.value, ...(data.media || [])] : (data.media || []);
      currentPage.value = page;
      hasMore.value = !!data.has_more;
      totalFiles.value = data.total ?? mediaItems.value.length;
      trashCount.value = data.trash_count || 0;
      availableExtensions.value = data.available_extensions || [];
      availableMonths.value = data.available_months || [];
      if (data.storage) storage.value = data.storage;
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

// Only callable while viewing a fixed folder — that folder becomes the new folder's parent.
const createFolder = async () => {
  const name = newFolderName.value.trim();
  if (!name || isCreatingFolder.value || activeFolder.value === 'all' || activeFolder.value === 'trash') return;

  isCreatingFolder.value = true;
  newFolderError.value = '';

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/folders`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify({ name, parent: activeFolder.value }),
    });
    const data = await res.json();

    if (res.ok && data.folder) {
      customFolders.value.push(data.folder);
      customFolders.value.sort((a, b) => a.name.localeCompare(b.name));
      newFolderName.value = '';
      showNewFolderForm.value = false;
      activeFolder.value = data.folder.slug;
    } else {
      newFolderError.value = data.message || 'Failed to create folder.';
    }
  } catch (err) {
    console.error('Failed to create folder:', err);
    newFolderError.value = 'Failed to create folder.';
  } finally {
    isCreatingFolder.value = false;
  }
};

// folder is a raw custom-folder record: { id, name, slug, parent_slug }.
const deleteFolder = async (folder) => {
  if (!confirm(`Delete the "${folder.name}" folder? This only works if it's empty.`)) return;

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/folders/${folder.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });
    const data = await res.json();

    if (res.ok && data.success) {
      customFolders.value = customFolders.value.filter(f => f.id !== folder.id);
      if (activeFolder.value === folder.slug) {
        activeFolder.value = folder.parent_slug;
      }
    } else {
      copyToast.value = data.message || 'Failed to delete folder.';
      setTimeout(() => { copyToast.value = ''; }, 4000);
    }
  } catch (err) {
    console.error('Failed to delete folder:', err);
  }
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
      const res = await fetch(`/${props.club.slug}/admin/media`, {
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

const { copy } = useClipboard();
const copyUrl = async (item) => {
  if (!(await copy(item.original_url))) return;
  copyToast.value = `Copied URL for "${item.file_name}"!`;
  setTimeout(() => {
    copyToast.value = '';
  }, 3000);
};

const selectedMediaIds = ref([]);
const targetBulkFolder = ref('images');
const assetUsages = ref([]);
const isLoadingUsages = ref(false);

const mediaVersions = ref([]);
const isLoadingVersions = ref(false);
const restoringVersionId = ref(null);
const isUploadingVersion = ref(false);
const detailsModal = ref(null);
const versionFileInput = computed(() => detailsModal.value?.fileInput ?? null);

const storage = ref({ used_bytes: 0, quota_bytes: null, used_human: '0 KB', quota_human: null, percent: null });

const showCropModal = ref(false);
const cropAspect = ref('free'); // 'free', '1:1', '16:9', '4:3'
const cropRotation = ref(0);
const isCropping = ref(false);
const cropModal = ref(null);
const cropSourceImageRef = computed(() => cropModal.value?.imageEl ?? null);

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
    const res = await fetch(`/${props.club.slug}/admin/media/bulk-delete`, {
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
    const res = await fetch(`/${props.club.slug}/admin/media/bulk-move`, {
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
    const res = await fetch(`/${props.club.slug}/admin/media/${id}/usage`);
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

const fetchVersions = async (id) => {
  mediaVersions.value = [];
  isLoadingVersions.value = true;
  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${id}/versions`);
    if (res.ok) {
      const data = await res.json();
      mediaVersions.value = data.versions || [];
    }
  } catch (err) {
    console.error('Failed to fetch version history:', err);
  } finally {
    isLoadingVersions.value = false;
  }
};

const openPreview = (item) => {
  previewItem.value = {
    ...item,
    alt_text: item.alt_text || '',
    caption: item.caption || '',
  };
  saveSuccessMsg.value = '';
  previewError.value = '';
  fetchUsage(item.id);
  fetchVersions(item.id);
};

// Mirrors MediaAdminController::formatTitleFromFilename() so "Generate from filename" matches
// what a fresh upload gets automatically.
const regenerateAltTextFromFilename = () => {
  if (!previewItem.value?.file_name) return;
  const nameWithoutExt = previewItem.value.file_name.replace(/\.[^.]+$/, '');
  const clean = nameWithoutExt.replace(/[_\-.]+/g, ' ').trim();
  // PHP's ucwords() only capitalizes each word's first letter and leaves the rest untouched.
  previewItem.value.alt_text = clean.replace(/\S+/g, (word) => word.charAt(0).toUpperCase() + word.slice(1));
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
    viewMode: 1, // Keep crop box strictly within image boundaries
    dragMode: 'crop',
    autoCropArea: 0.8,
    responsive: true,
    restore: false,
    checkCrossOrigin: false,
    guides: true,
    center: true,
    highlight: true,
    cropBoxMovable: true,
    cropBoxResizable: true,
    toggleDragModeOnDblclick: false,
    zoomable: false,
    zoomOnWheel: false,
    background: false,
    minContainerWidth: 300,
    minContainerHeight: 300,
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

const applyCrop = async (mode = 'replace') => {
  if (!cropperInstance || !previewItem.value) return;
  isCropping.value = true;
  previewError.value = '';

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
      formData.append('save_mode', mode);

      const res = await fetch(`/${props.club.slug}/admin/media/${previewItem.value.id}/crop`, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Accept': 'application/json',
        },
        body: formData,
      });

      const data = await res.json();

      if (res.ok && data.media) {
        if (mode === 'variant') {
          mediaItems.value.unshift(data.media);
          saveSuccessMsg.value = 'Saved as new cropped variant copy!';
        } else {
          const idx = mediaItems.value.findIndex(m => m.id === data.media.id);
          if (idx !== -1) mediaItems.value[idx] = data.media;
          previewItem.value = { ...previewItem.value, ...data.media };
          saveSuccessMsg.value = 'Image updated! Previous version saved to history.';
          fetchVersions(data.media.id);
          fetchMedia();
        }
        closeCropper();
        setTimeout(() => { saveSuccessMsg.value = ''; }, 4000);
      } else {
        previewError.value = data.message || 'Failed to save the cropped image.';
        closeCropper();
      }
      isCropping.value = false;
    }, 'image/jpeg', 0.9);
  } catch (err) {
    console.error('Failed to crop image:', err);
    isCropping.value = false;
  }
};

const triggerVersionUpload = () => {
  versionFileInput.value?.click();
};

const uploadNewVersion = async (event) => {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file || !previewItem.value) return;

  isUploadingVersion.value = true;
  saveSuccessMsg.value = '';
  previewError.value = '';

  try {
    const formData = new FormData();
    formData.append('file', file);

    const res = await fetch(`/${props.club.slug}/admin/media/${previewItem.value.id}/replace`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
      body: formData,
    });

    const data = await res.json();

    if (res.ok && data.media) {
      const idx = mediaItems.value.findIndex(m => m.id === data.media.id);
      if (idx !== -1) mediaItems.value[idx] = data.media;
      previewItem.value = { ...previewItem.value, ...data.media };
      saveSuccessMsg.value = 'File replaced. Previous version saved to history.';
      fetchVersions(data.media.id);
      fetchMedia();
      setTimeout(() => { saveSuccessMsg.value = ''; }, 4000);
    } else {
      previewError.value = data.message || 'Failed to upload new version.';
    }
  } catch (err) {
    console.error('Failed to upload new version:', err);
    previewError.value = 'Failed to upload new version.';
  } finally {
    isUploadingVersion.value = false;
  }
};

const restoreMediaVersion = async (version) => {
  if (!previewItem.value || restoringVersionId.value) return;
  if (!confirm(`Restore "${previewItem.value.name}" to the version from ${version.created_at}? The current file will be saved to history first.`)) return;

  restoringVersionId.value = version.id;
  saveSuccessMsg.value = '';
  previewError.value = '';

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${previewItem.value.id}/versions/${version.id}/restore`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    const data = await res.json();

    if (res.ok && data.media) {
      const idx = mediaItems.value.findIndex(m => m.id === data.media.id);
      if (idx !== -1) mediaItems.value[idx] = data.media;
      previewItem.value = { ...previewItem.value, ...data.media };
      saveSuccessMsg.value = 'File restored to the selected version.';
      fetchVersions(data.media.id);
      fetchMedia();
      setTimeout(() => { saveSuccessMsg.value = ''; }, 4000);
    } else {
      previewError.value = data.message || 'Failed to restore version.';
    }
  } catch (err) {
    console.error('Failed to restore version:', err);
    previewError.value = 'Failed to restore version.';
  } finally {
    restoringVersionId.value = null;
  }
};

const saveMediaDetails = async () => {
  if (!previewItem.value) return;
  isSavingDetails.value = true;
  saveSuccessMsg.value = '';

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${previewItem.value.id}`, {
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

const restoreItem = async (item) => {
  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${item.id}/restore`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      const data = await res.json();
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      if (previewItem.value?.id === item.id) {
        previewItem.value = null;
      }
      trashCount.value = Math.max(0, trashCount.value - 1);
      copyToast.value = data.message || `Restored "${item.file_name}" from Trash!`;
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed to restore media item:', err);
  }
};

const forceDeleteItem = async (item) => {
  if (!confirm(`Are you sure you want to PERMANENTLY delete "${item.file_name}"? This action cannot be undone.`)) return;

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${item.id}/force`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      const data = await res.json();
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      if (previewItem.value?.id === item.id) {
        previewItem.value = null;
      }
      trashCount.value = Math.max(0, trashCount.value - 1);
      copyToast.value = data.message || `Permanently deleted "${item.file_name}".`;
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed to force delete media item:', err);
  }
};

const handleBulkRestore = async () => {
  if (!selectedMediaIds.value.length) return;

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/bulk-restore`, {
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
      trashCount.value = Math.max(0, trashCount.value - selectedMediaIds.value.length);
      selectedMediaIds.value = [];
      copyToast.value = data.message || 'Selected files restored successfully!';
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed bulk restore:', err);
  }
};

const handleBulkForceDelete = async () => {
  if (!selectedMediaIds.value.length) return;
  if (!confirm(`Are you sure you want to PERMANENTLY delete ${selectedMediaIds.value.length} selected files? This action CANNOT be undone.`)) return;

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/bulk-force-delete`, {
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
      trashCount.value = Math.max(0, trashCount.value - selectedMediaIds.value.length);
      selectedMediaIds.value = [];
      copyToast.value = data.message || 'Selected files permanently deleted!';
      setTimeout(() => { copyToast.value = ''; }, 3000);
    }
  } catch (err) {
    console.error('Failed bulk force delete:', err);
  }
};

const deleteItem = async (item) => {
  if (!confirm(`Move "${item.file_name}" to Trash bin?`)) return;

  try {
    const res = await fetch(`/${props.club.slug}/admin/media/${item.id}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': getCsrfToken(),
        'Accept': 'application/json',
      },
    });

    if (res.ok) {
      const data = await res.json();
      mediaItems.value = mediaItems.value.filter(m => m.id !== item.id);
      trashCount.value++;
      if (previewItem.value?.id === item.id) {
        previewItem.value = null;
      }
      copyToast.value = data.message || `Moved "${item.file_name}" to Trash bin.`;
      setTimeout(() => { copyToast.value = ''; }, 3000);
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
        <Card padding="sm" class="lg:col-span-1 space-y-3 sticky top-6">
          <div class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 px-3 pt-1">
            Folders
          </div>

          <div class="space-y-1">
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
              <span class="flex items-center gap-2.5">
                <UiIcon :name="folder.icon" />
                <span>{{ folder.label }}</span>
              </span>
              <Badge v-if="folder.id === 'trash' && trashCount > 0">{{ trashCount }}</Badge>
            </button>
          </div>

          <!-- Storage Usage -->
          <div v-if="storage.quota_bytes !== null" class="px-3 pt-2 pb-1 space-y-1.5">
            <div class="flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <span>Storage used</span>
              <span>{{ storage.percent }}%</span>
            </div>
            <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="storage.percent >= 90 ? 'bg-rose-500' : storage.percent >= 70 ? 'bg-amber-500' : 'bg-blue-500'"
                :style="{ width: Math.min(100, storage.percent || 0) + '%' }"
              ></div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
              {{ storage.used_human }} of {{ storage.quota_human }} used
            </div>
          </div>
        </Card>

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

          <!-- Breadcrumb: viewing a custom folder, inside one of the fixed folders -->
          <nav v-if="activeCustomFolder" aria-label="Breadcrumb" class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 pb-1">
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

          <!-- Subfolders: viewing one of the fixed folders -->
          <div v-if="activeFolder !== 'all' && activeFolder !== 'trash' && !activeCustomFolder" class="space-y-2 pb-1">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Subfolders</span>
              <Button variant="ghost" size="sm" @click="showNewFolderForm = !showNewFolderForm; newFolderError = ''; newFolderName = ''">
                <template #icon><UiIcon name="plus" /></template>
                New folder
              </Button>
            </div>

            <div v-if="showNewFolderForm" class="flex items-center gap-2">
              <Input
                v-model="newFolderName"
                :placeholder="`New folder inside ${fixedFolderById(activeFolder)?.label}`"
                aria-label="New folder name"
                maxlength="60"
                class="flex-1"
                @keyup.enter="createFolder"
                @keyup.escape="showNewFolderForm = false"
              />
              <Button size="sm" class="shrink-0" :loading="isCreatingFolder" :disabled="!newFolderName.trim()" @click="createFolder">
                Add
              </Button>
            </div>
            <p v-if="newFolderError" class="text-xs text-rose-600 dark:text-rose-400">{{ newFolderError }}</p>

            <div v-if="subfoldersOf(activeFolder).length" class="flex flex-wrap gap-1.5">
              <div
                v-for="sub in subfoldersOf(activeFolder)"
                :key="sub.id"
                class="group relative"
              >
                <button
                  type="button"
                  @click="activeFolder = sub.slug"
                  class="pl-3 pr-8 py-1.5 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium rounded-full flex items-center gap-1.5"
                >
                  <UiIcon name="folder" />
                  <span>{{ sub.name }}</span>
                </button>
                <button
                  type="button"
                  @click.stop="deleteFolder(sub)"
                  title="Delete folder"
                  :aria-label="`Delete the ${sub.name} folder`"
                  class="absolute right-1.5 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 focus-visible:opacity-100 transition-opacity p-0.5 rounded-full text-slate-400 hover:text-rose-600 dark:hover:text-rose-400"
                >
                  <UiIcon name="x" />
                </button>
              </div>
            </div>
          </div>

          <!-- Inline Search & Filter Controls Bar -->
          <div class="space-y-4 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">

              <!-- Search Input Bar -->
              <div class="relative flex-1">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"><UiIcon name="search" /></span>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search files by name"
                  aria-label="Search files by name"
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

              <!-- Counter Badge -->
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">
                Showing {{ mediaItems.length }}<template v-if="hasMore"> of {{ totalFiles }}</template> {{ totalFiles === 1 ? 'file' : 'files' }} in <span class="text-slate-900 dark:text-white font-semibold">{{ activeFolderLabel }}</span>
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

          <Alert v-if="activeFolder === 'trash'" variant="warning">
            Files in the trash bin are deleted for good 30 days after they were moved here. Restore anything you want to keep before then.
          </Alert>

          <!-- Bulk Actions Floating Control Bar -->
          <div v-if="selectedMediaIds.length > 0" class="p-3 bg-blue-50 border border-blue-200 dark:bg-blue-950/40 dark:border-blue-800/60 rounded-2xl flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <Badge variant="info">{{ selectedMediaIds.length }} selected</Badge>
              <Button variant="ghost" size="sm" @click="toggleSelectAll">
                {{ selectedMediaIds.length === mediaItems.length ? 'Deselect all' : 'Select all' }}
              </Button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <template v-if="activeFolder === 'trash'">
                <Button variant="secondary" size="sm" @click="handleBulkRestore">
                  <template #icon><UiIcon name="restore" /></template>
                  Restore selected
                </Button>
                <Button variant="danger" size="sm" @click="handleBulkForceDelete">
                  <template #icon><UiIcon name="trash" /></template>
                  Delete permanently
                </Button>
              </template>
              <template v-else>
                <!-- Target Folder Selection for Bulk Move -->
                <Select
                  v-model="targetBulkFolder"
                  aria-label="Move selected files to"
                  class="w-44"
                  :options="selectableFolders.map(f => ({ value: f.id, label: f.label }))"
                />

                <Button variant="secondary" size="sm" @click="handleBulkMove">
                  <template #icon><UiIcon name="folder" /></template>
                  Move
                </Button>

                <Button variant="secondary" size="sm" @click="handleBulkDelete">
                  <template #icon><UiIcon name="trash" /></template>
                  Move to trash
                </Button>
              </template>

              <Button variant="ghost" size="sm" @click="selectedMediaIds = []">
                <template #icon><UiIcon name="x" /></template>
                Clear
              </Button>
            </div>
          </div>

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
          <div>
            <div v-if="isLoading" class="flex items-center justify-center gap-2 py-20 text-slate-500 dark:text-slate-400 text-sm">
              <UiIcon name="refresh" class="animate-spin" /> Loading files…
            </div>

            <div v-else-if="!mediaItems.length" class="text-center py-20 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50/50 dark:bg-slate-800/50 space-y-3">
              <span class="flex justify-center text-slate-400"><UiIcon name="folder" :size="20" /></span>
              <div>
                <span class="text-sm font-medium text-slate-900 dark:text-white block">No files in this folder</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">Drag and drop files here, or choose them below.</span>
              </div>
              <Button variant="secondary" size="sm" class="mt-2" @click="triggerUpload">
                <template #icon><UiIcon name="upload" /></template>
                Choose files to upload
              </Button>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              <div
                v-for="item in mediaItems"
                :key="item.id"
                :class="[
                  'group rounded-2xl border p-3 transition-all shadow-sm hover:shadow-md flex flex-col justify-between relative',
                  selectedMediaIds.includes(item.id)
                    ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-400 ring-2 ring-blue-200 dark:ring-blue-800/60'
                    : 'bg-white dark:bg-slate-900 hover:border-slate-300 dark:hover:border-slate-700 border-slate-200 dark:border-slate-800'
                ]"
              >
                <!-- Image or Icon Box -->
                <div
                  @click="openPreview(item)"
                  class="h-40 w-full rounded-xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-center relative mb-3 cursor-pointer group-hover:border-blue-300 dark:group-hover:border-blue-700/60"
                >
                  <img
                    v-if="isImage(item.mime_type || item.file_name)"
                    :src="item.original_url"
                    :alt="item.alt_text || item.name"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                  />
                  <div v-else class="flex flex-col items-center justify-center space-y-1">
                    <span class="text-slate-400"><UiIcon :name="getFileIcon(item.mime_type || item.file_name)" :size="20" /></span>
                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ item.file_name.split('.').pop() }}</span>
                  </div>

                  <!-- Multi-Select Checkbox -->
                  <div class="absolute top-2 right-2 z-20" @click.stop>
                    <input
                      type="checkbox"
                      :checked="selectedMediaIds.includes(item.id)"
                      @change="toggleSelectItem(item.id)"
                      :aria-label="`Select ${item.name || item.file_name}`"
                      class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950"
                    />
                  </div>

                  <!-- Folder Collection Tag & Badges -->
                  <div class="absolute top-2 left-2 flex flex-col gap-1 items-start z-10">
                    <Badge>{{ item.collection_name }}</Badge>
                    <Badge v-if="item.is_variant" variant="info">Variant</Badge>
                    <Badge v-if="item.is_trashed" variant="danger">Trashed</Badge>
                    <Badge v-if="item.version_count > 0">
                      {{ item.version_count }} {{ item.version_count === 1 ? 'version' : 'versions' }}
                    </Badge>
                  </div>

                  <!-- Hover Inspect Overlay -->
                  <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-xs font-medium gap-1">
                    <UiIcon name="eye" />
                    <span>Details and edit</span>
                  </div>
                </div>

                <!-- File Info -->
                <div class="space-y-1 cursor-pointer" @click="openPreview(item)">
                  <div class="font-medium text-slate-900 dark:text-white text-sm truncate group-hover:text-blue-700 dark:group-hover:text-blue-300" :title="item.name || item.file_name">
                    {{ item.name || item.file_name }}
                  </div>
                  <div v-if="item.alt_text" class="text-[11px] text-slate-500 dark:text-slate-400 italic truncate">
                    Alt: "{{ item.alt_text }}"
                  </div>
                  <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                    <span>{{ item.human_size }}</span>
                    <span>{{ item.created_at }}</span>
                  </div>
                </div>

                <!-- Card Bottom Controls -->
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 mt-3 flex items-center justify-between gap-1">
                  <Button variant="secondary" size="sm" title="View details and edit" @click="openPreview(item)">
                    <template #icon><UiIcon name="eye" /></template>
                    Details
                  </Button>

                  <Button variant="ghost" size="sm" title="Copy the file's link" @click="copyUrl(item)">
                    <template #icon><UiIcon name="copy" /></template>
                    Copy
                  </Button>

                  <template v-if="activeFolder === 'trash' || item.is_trashed">
                    <Button variant="secondary" size="sm" title="Restore the file from the trash" @click="restoreItem(item)">
                      <template #icon><UiIcon name="restore" /></template>
                      Restore
                    </Button>
                    <button
                      type="button"
                      @click="forceDeleteItem(item)"
                      class="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                      title="Delete permanently"
                      aria-label="Delete permanently"
                    >
                      <UiIcon name="trash" />
                    </button>
                  </template>
                  <template v-else>
                    <button
                      type="button"
                      @click="deleteItem(item)"
                      class="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                      title="Move to trash"
                      aria-label="Move to trash"
                    >
                      <UiIcon name="trash" />
                    </button>
                  </template>
                </div>
              </div>
            </div>

            <div v-if="hasMore && !isLoading" class="flex justify-center pt-2">
              <Button variant="secondary" size="sm" :disabled="isLoadingMore" @click="fetchMedia(true)">
                {{ isLoadingMore ? 'Loading…' : `Load more files (${mediaItems.length} of ${totalFiles})` }}
              </Button>
            </div>

          </div>

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
