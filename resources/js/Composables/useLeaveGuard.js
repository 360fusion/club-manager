import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Leaving the page editor with unsaved changes: the three answers of the "unsaved changes" dialog (save and go,
 * discard and go, stay), the browser's own leave warning, and the check on Inertia visits and address changes.
 * Everything else is the page's own state and actions, handed in.
 */
export function useLeaveGuard({ form, isDirty, isBypassingDirtyGuard, showUnsavedModal, pendingNavigationTarget, submitForm, executeNavigation, followAddress }) {
    const handleSaveAndProceed = () => {
        const target = pendingNavigationTarget.value;
        submitForm(() => {
            if (target) {
                executeNavigation(target.selection, target.mode);
            } else {
                showUnsavedModal.value = false;
            }
        });
    };

    const handleDiscardAndProceed = () => {
        const target = pendingNavigationTarget.value;
        showUnsavedModal.value = false;
        if (target) {
            executeNavigation(target.selection, target.mode);
        }
    };

    const handleStayAndEdit = () => {
        showUnsavedModal.value = false;
        pendingNavigationTarget.value = null;
    };

    const handleBeforeUnload = (e) => {
        if (isDirty.value && !isBypassingDirtyGuard.value) {
            e.preventDefault();
            e.returnValue = '';
        }
    };

    let unbindInertiaGuard = null;
    let unbindNavigate = null;

    onMounted(() => {
        window.addEventListener('beforeunload', handleBeforeUnload);

        unbindNavigate = router.on('navigate', (event) => followAddress(event.detail.page.url));

        unbindInertiaGuard = router.on('before', (event) => {
            if (isDirty.value && !isBypassingDirtyGuard.value && event.detail.visit.method === 'get') {
                const confirmLeave = window.confirm(
                    `⚠️ You have unsaved changes on "${form.title || 'this page'}".\n\nIf you leave now, your edits will be lost.\n\nDo you want to leave without saving?`
                );
                if (!confirmLeave) {
                    event.preventDefault();
                }
            }
        });
    });

    onUnmounted(() => {
        window.removeEventListener('beforeunload', handleBeforeUnload);
        if (unbindInertiaGuard) {
            unbindInertiaGuard();
        }
        if (unbindNavigate) {
            unbindNavigate();
        }
    });

    return { handleSaveAndProceed, handleDiscardAndProceed, handleStayAndEdit };
}
