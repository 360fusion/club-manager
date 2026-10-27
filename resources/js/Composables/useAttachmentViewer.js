import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * The attachment pop-out of an invoice or bill: the file on show, zoom and rotation, and deleting the attachment.
 * `club` is the page's club, whose slug the delete route needs.
 */
export function useAttachmentViewer(club) {
    const previewModal = ref({
      show: false,
      type: '', // 'invoice' or 'bill'
      itemId: null,
      title: '',
      url: '',
      mimeType: '',
      fileName: '',
    });

    const zoomLevel = ref(1);
    const rotation = ref(0);

    const openAttachmentModal = (item, type) => {
      if (!item || !item.attachment) return;
      previewModal.value = {
        show: true,
        type,
        itemId: item.id,
        title: item.title || item.bill_number || item.invoice_number || 'Attachment',
        url: item.attachment.original_url,
        mimeType: item.attachment.mime_type || (item.attachment.file_name?.endsWith('.pdf') ? 'application/pdf' : 'image/png'),
        fileName: item.attachment.file_name || 'document',
      };
      zoomLevel.value = 1;
      rotation.value = 0;
    };

    const zoomIn = () => {
      if (zoomLevel.value < 3) zoomLevel.value = parseFloat((zoomLevel.value + 0.25).toFixed(2));
    };

    const zoomOut = () => {
      if (zoomLevel.value > 0.4) zoomLevel.value = parseFloat((zoomLevel.value - 0.25).toFixed(2));
    };

    const resetZoom = () => {
      zoomLevel.value = 1;
      rotation.value = 0;
    };

    const rotateImage = () => {
      rotation.value = (rotation.value + 90) % 360;
    };

    const deleteAttachment = () => {
      if (!confirm('Are you sure you want to delete this attachment? This action cannot be undone.')) return;
      const routeName = previewModal.value.type === 'invoice'
        ? 'admin.accounting.invoices.attachment.destroy'
        : 'admin.accounting.bills.attachment.destroy';

      router.delete(route(routeName, { clubSlug: club.slug, id: previewModal.value.itemId }), {
        onSuccess: () => {
          previewModal.value.show = false;
        },
      });
    };

    return { previewModal, zoomLevel, rotation, openAttachmentModal, zoomIn, zoomOut, resetZoom, rotateImage, deleteAttachment };
}
