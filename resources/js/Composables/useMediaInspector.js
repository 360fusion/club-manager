import { ref, nextTick, computed } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { getCsrfToken, isImage } from '@/Composables/useMediaLibrary';

/**
 * The media library's details dialog: the file being edited (name, alt text, caption), where it is used, its version
 * history (upload a new version, restore an old one) and the image cropper. `detailsModal` and `cropModal` are the
 * page's template refs to the two dialogs; the cropper is built on the crop dialog's image.
 */
export function useMediaInspector(props, { mediaItems, previewItem, fetchMedia }) {
  const isSavingDetails = ref(false);
  const saveSuccessMsg = ref('');
  const previewError = ref('');

  const assetUsages = ref([]);
  const isLoadingUsages = ref(false);

  const mediaVersions = ref([]);
  const isLoadingVersions = ref(false);
  const restoringVersionId = ref(null);
  const isUploadingVersion = ref(false);
  const detailsModal = ref(null);
  const versionFileInput = computed(() => detailsModal.value?.fileInput ?? null);

  const showCropModal = ref(false);
  const cropAspect = ref('free'); // 'free', '1:1', '16:9', '4:3'
  const cropRotation = ref(0);
  const isCropping = ref(false);
  const cropModal = ref(null);
  const cropSourceImageRef = computed(() => cropModal.value?.imageEl ?? null);

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

  return {
    isSavingDetails, saveSuccessMsg, previewError, assetUsages, isLoadingUsages, mediaVersions, isLoadingVersions,
    restoringVersionId, isUploadingVersion, detailsModal, showCropModal, cropAspect, isCropping, cropModal, openPreview,
    regenerateAltTextFromFilename, openCropper, closeCropper, setCropAspect, rotateCropper, applyCrop,
    triggerVersionUpload, uploadNewVersion, restoreMediaVersion, saveMediaDetails,
  };
}
