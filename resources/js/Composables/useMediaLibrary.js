import { ref, watch, onMounted, computed } from 'vue';
import { useClipboard } from '@/Composables/useClipboard';

export const getCsrfToken = () => {
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

export const getFileIcon = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return 'document';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.webp')) return 'photo';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return 'chart';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return 'document';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return 'archive';
  return 'link';
};

export const isImage = (mimeOrUrl) => {
  const val = (mimeOrUrl || '').toLowerCase();
  return val.includes('image') || val.match(/\.(jpg|jpeg|png|webp|gif|svg)$/);
};

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

/**
 * The club's media library: the file list and its filters, the folders (fixed and custom), uploads, selection and
 * bulk actions, the trash, and the toast that reports each action. `previewItem` is the file open in the details
 * dialog; the actions here close it when they remove that file from the list.
 */
export function useMediaLibrary(props) {
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

  const availableExtensions = ref([]);
  const availableMonths = ref([]);
  const trashCount = ref(0);
  const customFolders = ref([]); // [{ id, name, slug }], from the server

  const showNewFolderForm = ref(false);
  const newFolderName = ref('');
  const isCreatingFolder = ref(false);
  const newFolderError = ref('');
  const storage = ref({ used_bytes: 0, quota_bytes: null, used_human: '0 KB', quota_human: null, percent: null });

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

  return {
    activeFolder, searchQuery, mediaItems, isLoading, isLoadingMore, hasMore, totalFiles, isUploading, uploadFileInput,
    copyToast, previewItem, uploadFolder, isDragging, uploadStatus, uploadError, filterType, filterExtension, filterDate,
    sortBy, availableExtensions, availableMonths, trashCount, customFolders, showNewFolderForm, newFolderName,
    isCreatingFolder, newFolderError, storage, folders, subfoldersOf, activeCustomFolder, fixedFolderById,
    activeFolderLabel, selectableFolders, fetchMedia, resetFilters, createFolder, deleteFolder, triggerUpload,
    handleFileUpload, handleDrop, copyUrl, selectedMediaIds, targetBulkFolder, toggleSelectItem, toggleSelectAll,
    handleBulkDelete, handleBulkMove, restoreItem, forceDeleteItem, handleBulkRestore, handleBulkForceDelete, deleteItem,
  };
}
