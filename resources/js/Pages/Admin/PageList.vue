<script setup>
import { BLOCK_COLUMNS } from '@/Support/pageBlockCatalog';
import PageBuilderNav from '@/Components/Pages/PageBuilderNav.vue';
import PageViewModeBar from '@/Components/Pages/PageViewModeBar.vue';
import PagePreviewPane from '@/Components/Pages/PagePreviewPane.vue';
import PageEditPane from '@/Components/Pages/PageEditPane.vue';
import PageThemesPane from '@/Components/Pages/PageThemesPane.vue';
import PageHeaderFooterPane from '@/Components/Pages/PageHeaderFooterPane.vue';
import PageRedirectsPane from '@/Components/Pages/PageRedirectsPane.vue';
import PagePublishPane from '@/Components/Pages/PagePublishPane.vue';
import PageTablePane from '@/Components/Pages/PageTablePane.vue';
import PageTrashPane from '@/Components/Pages/PageTrashPane.vue';
import PageSettingsPane from '@/Components/Pages/PageSettingsPane.vue';
import PageUnsavedDialog from '@/Components/Pages/PageUnsavedDialog.vue';
import PageThemeConfirmDialog from '@/Components/Pages/PageThemeConfirmDialog.vue';
import { usePageListActions } from '@/Composables/usePageListActions';
import { ANNOUNCEMENT_STYLES, FOOTER_LAYOUTS, HEADER_LAYOUTS, isDefaultPage, lifecycleText, MAX_FOOTER_COLUMN_LINKS, MAX_FOOTER_COLUMNS } from '@/Utils/pageBuilderSettings';
import { getPageIcon, parseSelectionUrl, selectionUrl as buildSelectionUrl } from '@/Utils/pageBuilderUrl';
import { useLeaveGuard } from '@/Composables/useLeaveGuard';
import { usePageBlockCopy } from '@/Composables/usePageBlockCopy';
import { MANAGEMENT_SELECTIONS, usePageEditorForm } from '@/Composables/usePageEditorForm';
import { usePageEditorBlocks } from '@/Composables/usePageEditorBlocks';
import { usePageMediaPicker } from '@/Composables/usePageMediaPicker';
import { useSiteHeaderFooter } from '@/Composables/useSiteHeaderFooter';
import { useSiteSettingsForms } from '@/Composables/useSiteSettingsForms';
import { useSiteThemes } from '@/Composables/useSiteThemes';
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import PageHistoryModal from '@/Components/PageHistoryModal.vue';
import PageChooserModal from '@/Components/PageChooserModal.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    club: {
        type: Object,
        required: true,
    },
    pages: {
        type: Array,
        default: () => [],
    },
    selectedId: {
        type: [Number, String],
        default: null,
    },
    websiteSettings: {
        type: Object,
        default: () => ({}),
    },
    // Only on the Publish screen: versions of the static website and where they are sent (StaticSiteController).
    publishing: {
        type: Object,
        default: null,
    },
    latestPosts: {
        type: Array,
        default: () => [],
    },
    upcomingEvents: {
        type: Array,
        default: () => [],
    },
    membershipPlans: {
        type: Array,
        default: () => [],
    },
    donations: {
        type: Array,
        default: () => [],
    },
    calendar: {
        type: Object,
        default: null,
    },
    clubAddresses: {
        type: Array,
        default: () => [],
    },
    trashedPages: {
        type: Array,
        default: () => [],
    },
});

// The builder's shell: which screen is showing (a page id, 'new' or one of MANAGEMENT_SELECTIONS) and how a page is shown.
// Each screen's own state and actions live in the composables below.
const activeNavSelection = ref(props.selectedId ? String(props.selectedId) : (props.pages.length > 0 ? String(props.pages[0].id) : 'new'));
// 'preview' or 'edit', taken from the address: /pages/7/edit opens the builder, /pages/7/edit?view=preview the preview.
const initialViewMode = () => {
    const view = new URLSearchParams(window.location.search).get('view');
    if (view === 'preview' || view === 'edit') return view;
    return /\/admin\/pages\/(create|\d+\/edit)\/?$/.test(window.location.pathname) ? 'edit' : 'preview';
};
const pageViewMode = ref(initialViewMode());
const isSavedSuccess = ref(false);

const flashSaved = () => {
    isSavedSuccess.value = true;
    setTimeout(() => { isSavedSuccess.value = false; }, 3000);
};

const { settingsForm, submitWebsiteSettings, domainExample, checkingDomain, checkDomainNow, redirectForm, redirects, addRedirect, removeRedirect } = useSiteSettingsForms(props, { flashSaved });

const { headerFooterForm, addFooterColumn, removeFooterColumn, addFooterLink, removeFooterLink, submitHeaderFooter, currentYear, copyrightPreview, footerDetailsSideBySide, publishedNavPages, footerPinnedPages, showNavChooser, footerNavPages, applyFooterNavPages, announcementPreview } = useSiteHeaderFooter(props, { flashSaved });

const { LAYOUTS, LEGACY, COLOR_SCHEMES, customColourSchemes, currentThemeKey, liveThemeClasses, selectedLayoutId, selectedColorSchemeId, selectedThemeForModal, showThemeConfirmModal, themeForm, MAX_CUSTOM_SCHEMES, canCreateScheme, schemeEditorOpen, schemeError, schemeForm, openNewScheme, editScheme, closeSchemeEditor, saveScheme, deleteScheme, draftColours, draftPalette, draftColoursClose, usedByLiveSite, SCHEME_SHADES, previewThemeInBuilder, effectivePreviewThemeKey, previewThemeClasses, applyPreviewTheme, openApplyThemeModal, closeApplyThemeModal, confirmApplyTheme, styleChanged, themeLabel } = useSiteThemes(props, {
    flashSaved,
    previewInBuilder: () => requestNavigation(props.pages.length > 0 ? props.pages[0].id : 'new', 'preview'),
});

const { activePage, form, isDirty, isBypassingDirtyGuard, searchPreview, seoOpen, collapsedBlocks, previewUrl, previewOpen, previewCopied, previewError, openPreviewPanel, renewPreviewLink, copyPreviewLink, submitForm, hasDraft, canDraft, saveDraft, publishDraft, discardDraft, showHistory, restoreNotice, restoreRevision, publishAtLocal, unpublishAtLocal } = usePageEditorForm(props, { activeNavSelection, settingsForm, isSavedSuccess, flashSaved });

const { activeInsertIndex, insertMenuPlacement, insertMenuShift, toggleInsertMenu, applySectionToAll, addBlock, newBlockId, blockCardId, isCollapsed, toggleCollapsed, collapseAll, expandAll, collapsedCount, blockToDelete, blockToDeleteName, confirmRemoveBlock, moveBlockUp, moveBlockDown, duplicateBlock, removeBlockImage, addGalleryImage, removeGalleryImage, onYoutubeUrlInput, setYoutubeTime } = usePageEditorBlocks(props, { form, collapsedBlocks });

const { showMediaModal, mediaDefaultFolder, openMediaLibrary, onMediaSelect } = usePageMediaPicker({ form, settingsForm });

const { copyTargets, copyNotice, copyNoticeIsError, copyBlockTo } = usePageBlockCopy(props.club, form, () => props.pages);

const blockColumns = BLOCK_COLUMNS;

const showUnsavedModal = ref(false);
const pendingNavigationTarget = ref(null); // { selection: string, mode: 'edit'|'preview' }

const selectionUrl = (selection, mode = 'edit') => buildSelectionUrl(props.club.slug, selection, mode);

const currentAddress = () => window.location.pathname + window.location.search;

// Moves the address bar (and history) to the screen being shown, without asking the server for anything.
const showAddressFor = (selection, mode) => {
    const url = selectionUrl(selection, mode);
    if (url !== currentAddress()) router.push({ url, preserveState: true, preserveScroll: true });
};

const requestNavigation = (targetSelection, mode = 'edit') => {
    const selectionStr = String(targetSelection);
    if (activeNavSelection.value === selectionStr && pageViewMode.value === mode) {
        return;
    }
    if (isDirty.value && !isBypassingDirtyGuard.value) {
        pendingNavigationTarget.value = { selection: selectionStr, mode };
        showUnsavedModal.value = true;
    } else {
        executeNavigation(selectionStr, mode);
    }
};

const executeNavigation = (selectionStr, mode = 'edit') => {
    activeNavSelection.value = selectionStr;
    pageViewMode.value = mode;
    showUnsavedModal.value = false;
    pendingNavigationTarget.value = null;
    showAddressFor(selectionStr, mode);
};

// A plain click on a navigation link switches screens instantly (keeping the unsaved-changes check); a modified click
// (new tab, new window, download) is left to the browser, which opens the address.
const navClick = (event, selection, mode = 'edit') => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    requestNavigation(selection, mode);
};

// The back and forward buttons: show whichever screen the address now names.
const followAddress = (url) => {
    const target = parseSelectionUrl(url);

    if (!target || (target.selection === activeNavSelection.value && target.mode === pageViewMode.value)) return;

    if (isDirty.value && !isBypassingDirtyGuard.value) {
        // Put the address back and ask first, as for any other way of leaving unsaved edits.
        router.replace({ url: selectionUrl(activeNavSelection.value, pageViewMode.value), preserveState: true, preserveScroll: true });
        pendingNavigationTarget.value = { selection: target.selection, mode: target.mode };
        showUnsavedModal.value = true;
        return;
    }

    activeNavSelection.value = target.selection;
    pageViewMode.value = target.mode;
};

const { handleSaveAndProceed, handleDiscardAndProceed, handleStayAndEdit } = useLeaveGuard({ form, isDirty, isBypassingDirtyGuard, showUnsavedModal, pendingNavigationTarget, submitForm, executeNavigation, followAddress });

const { togglePublishPage, duplicatePage, restorePage, forceDeletePage, movePageUp, movePageDown } = usePageListActions(props.club, () => props.pages);


const showDeleteConfirmModal = ref(false);
const pageToDelete = ref(null);

const triggerDeleteModal = (page) => {
    if (isDefaultPage(page)) return;
    pageToDelete.value = page;
    showDeleteConfirmModal.value = true;
};

const confirmDeleteActivePage = () => {
    const targetId = pageToDelete.value ? pageToDelete.value.id : form.id;
    if (!targetId) return;

    router.delete(`/${props.club.slug}/admin/pages/${targetId}`, {
        onSuccess: () => {
            showDeleteConfirmModal.value = false;
            pageToDelete.value = null;
            activeNavSelection.value = props.pages.length > 0 ? String(props.pages[0].id) : 'overview';
        }
    });
};

</script>

<template>
    <AdminLayout title="Website builder" :club="club" active-tab="pages">
        <Head :title="`Website builder - ${club.name}`" />

        <div class="space-y-6 max-w-7xl mx-auto">

            <!-- Top Action Bar & Header Card -->
            <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white">Website builder and navigation</h2>
                        <Badge variant="info">{{ club.slug }}</Badge>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage public site pages, customize layout blocks, preview rendered pages, and arrange navigation links.</p>
                </div>

                <div class="flex items-center gap-2 flex-wrap self-start sm:self-auto">
                    <!-- A plain link, since the public site opens in a new tab outside the admin. -->
                    <a :href="`/site/${club.slug}`" target="_blank" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <UiIcon name="globe" />
                        View live site
                    </a>
                </div>
            </Card>

            <!-- 2-Column Responsive Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                
                <PageBuilderNav :pages="pages" :active-nav-selection="activeNavSelection" :page-view-mode="pageViewMode" :selection-url="selectionUrl" :nav-click="navClick" :request-navigation="requestNavigation" />

                <!-- Right Main Content Panel -->
                <div class="lg:col-span-3 space-y-6">

                    <!-- View Mode Container (Edit / Live Preview Toggle) -->
                    <div v-if="!MANAGEMENT_SELECTIONS.includes(activeNavSelection)" class="space-y-6">

                        <PageViewModeBar :club="club" :form="form" :active-nav-selection="activeNavSelection" :page-view-mode="pageViewMode" :selection-url="selectionUrl" :nav-click="navClick" />

                        <!-- SUB-VIEW 1: EDIT BUILDER VIEW -->
                        <PageEditPane :club="club" :pages="pages" :club-addresses="clubAddresses" v-if="pageViewMode === 'edit'" v-model:active-insert-index="activeInsertIndex" :active-page="activePage" :add-block="addBlock" :add-gallery-image="addGalleryImage" :apply-section-to-all="applySectionToAll" :block-card-id="blockCardId" :block-columns="blockColumns" v-model:block-to-delete="blockToDelete" :can-draft="canDraft" :collapse-all="collapseAll" :collapsed-count="collapsedCount" :copy-block-to="copyBlockTo" :copy-notice="copyNotice" :copy-notice-is-error="copyNoticeIsError" :copy-preview-link="copyPreviewLink" :copy-targets="copyTargets" :discard-draft="discardDraft" :duplicate-block="duplicateBlock" :expand-all="expandAll" :form="form" :has-draft="hasDraft" :insert-menu-placement="insertMenuPlacement" :insert-menu-shift="insertMenuShift" :is-collapsed="isCollapsed" :is-default-page="isDefaultPage" :is-dirty="isDirty" :is-saved-success="isSavedSuccess" :lifecycle-text="lifecycleText" :move-block-down="moveBlockDown" :move-block-up="moveBlockUp" :new-block-id="newBlockId" :on-youtube-url-input="onYoutubeUrlInput" :open-media-library="openMediaLibrary" :open-preview-panel="openPreviewPanel" :preview-copied="previewCopied" :preview-error="previewError" :preview-open="previewOpen" :preview-url="previewUrl" v-model:publish-at-local="publishAtLocal" :publish-draft="publishDraft" :remove-block-image="removeBlockImage" :remove-gallery-image="removeGalleryImage" :renew-preview-link="renewPreviewLink" :restore-notice="restoreNotice" :save-draft="saveDraft" :search-preview="searchPreview" v-model:seo-open="seoOpen" :set-youtube-time="setYoutubeTime" :settings-form="settingsForm" v-model:show-history="showHistory" :submit-form="submitForm" :toggle-collapsed="toggleCollapsed" :toggle-insert-menu="toggleInsertMenu" :trigger-delete-modal="triggerDeleteModal" v-model:unpublish-at-local="unpublishAtLocal" />

                        <!-- SUB-VIEW 2: LIVE PREVIEW VIEW -->
                        <PagePreviewPane :club="club" :website-settings="websiteSettings" :latest-posts="latestPosts" :upcoming-events="upcomingEvents" :membership-plans="membershipPlans" :donations="donations" :calendar="calendar" v-else-if="pageViewMode === 'preview'" :color-schemes="COLOR_SCHEMES" :layouts="LAYOUTS" :legacy="LEGACY" :apply-preview-theme="applyPreviewTheme" :current-theme-key="currentThemeKey" :effective-preview-theme-key="effectivePreviewThemeKey" :footer-pinned-pages="footerPinnedPages" :form="form" :preview-theme-classes="previewThemeClasses" :published-nav-pages="publishedNavPages" v-model:selected-color-scheme-id="selectedColorSchemeId" v-model:selected-layout-id="selectedLayoutId" />
                    </div>

                    <!-- View Mode: Global Website & SEO Settings -->
                    <PageSettingsPane :club="club" :pages="pages" :website-settings="websiteSettings" v-else-if="activeNavSelection === 'settings'" :check-domain-now="checkDomainNow" :checking-domain="checkingDomain" :domain-example="domainExample" :form="form" :is-saved-success="isSavedSuccess" :open-media-library="openMediaLibrary" :request-navigation="requestNavigation" :settings-form="settingsForm" :submit-website-settings="submitWebsiteSettings" />

                    <!-- View Mode: Header & Footer Layout -->
                    <PageHeaderFooterPane :club="club" :pages="pages" v-else-if="activeNavSelection === 'header_footer'" :announcement-styles="ANNOUNCEMENT_STYLES" :footer-layouts="FOOTER_LAYOUTS" :header-layouts="HEADER_LAYOUTS" :max-footer-columns="MAX_FOOTER_COLUMNS" :max-footer-column-links="MAX_FOOTER_COLUMN_LINKS" :add-footer-column="addFooterColumn" :add-footer-link="addFooterLink" :announcement-preview="announcementPreview" :copyright-preview="copyrightPreview" :current-year="currentYear" :footer-details-side-by-side="footerDetailsSideBySide" :footer-nav-pages="footerNavPages" :footer-pinned-pages="footerPinnedPages" :form="form" :header-footer-form="headerFooterForm" :is-saved-success="isSavedSuccess" :live-theme-classes="liveThemeClasses" :published-nav-pages="publishedNavPages" :remove-footer-column="removeFooterColumn" :remove-footer-link="removeFooterLink" :submit-header-footer="submitHeaderFooter" @choose-nav="showNavChooser = true" />

                    <!-- View Mode 3: Website Theme Selection Gallery -->
                    <PageThemesPane :club="club" :layouts="LAYOUTS" v-else-if="activeNavSelection === 'themes'" :color-schemes="COLOR_SCHEMES" :legacy="LEGACY" :max-custom-schemes="MAX_CUSTOM_SCHEMES" :scheme-shades="SCHEME_SHADES" :can-create-scheme="canCreateScheme" :close-scheme-editor="closeSchemeEditor" :current-theme-key="currentThemeKey" :custom-colour-schemes="customColourSchemes" :delete-scheme="deleteScheme" :draft-colours="draftColours" :draft-colours-close="draftColoursClose" :draft-palette="draftPalette" :edit-scheme="editScheme" :effective-preview-theme-key="effectivePreviewThemeKey" :form="form" :open-apply-theme-modal="openApplyThemeModal" :open-new-scheme="openNewScheme" :preview-theme-in-builder="previewThemeInBuilder" :save-scheme="saveScheme" :scheme-editor-open="schemeEditorOpen" :scheme-error="schemeError" :scheme-form="schemeForm" :style-changed="styleChanged" :theme-form="themeForm" :theme-label="themeLabel" :used-by-live-site="usedByLiveSite" v-model:selected-color-scheme-id="selectedColorSchemeId" v-model:selected-layout-id="selectedLayoutId" />

                    <!-- View Mode: Redirects -->
                    <PagePublishPane v-else-if="activeNavSelection === 'publish'" :club="club" :publishing="publishing" />

                    <PageRedirectsPane :club="club" v-else-if="activeNavSelection === 'redirects'" :add-redirect="addRedirect" :form="form" :is-saved-success="isSavedSuccess" :redirect-form="redirectForm" :redirects="redirects" :remove-redirect="removeRedirect" />

                    <!-- View Mode 4: All Pages Overview Table -->
                    <PageTablePane v-else :club="club" :pages="pages" :get-page-icon="getPageIcon" :lifecycle-text="lifecycleText" :move-page-up="movePageUp" :move-page-down="movePageDown" :toggle-publish-page="togglePublishPage" :selection-url="selectionUrl" :nav-click="navClick" :duplicate-page="duplicatePage" :is-default-page="isDefaultPage" :trigger-delete-modal="triggerDeleteModal" />

                    <!-- Trash: recently deleted custom pages -->
                    <PageTrashPane v-if="trashedPages.length" :club="club" :trashed-pages="trashedPages" :restore-page="restorePage" :force-delete-page="forceDeletePage" />

                </div>

            </div>

        </div>

        <!-- Delete Confirmation Modal -->
        <ConfirmDialog
            :open="showDeleteConfirmModal"
            title="Delete this page?"
            confirm-label="Delete page"
            @close="showDeleteConfirmModal = false; pageToDelete = null;"
            @confirm="confirmDeleteActivePage"
        >
            Are you sure you want to delete <strong>{{ pageToDelete ? pageToDelete.title : form.title }}</strong>? This action cannot be undone.
        </ConfirmDialog>

        <!-- Confirm deleting a page element -->
        <ConfirmDialog
            :open="blockToDelete !== null"
            title="Delete this element?"
            confirm-label="Delete element"
            @close="blockToDelete = null"
            @confirm="confirmRemoveBlock"
        >
            Are you sure you want to delete <strong>{{ blockToDeleteName }}</strong>? Its content will be lost once you save the page.
        </ConfirmDialog>

        <PageChooserModal
            :show="showNavChooser"
            title="Choose footer pages"
            intro="Tick the pages the footer's Navigate column should list. These are the pages in your top menu."
            :pages="publishedNavPages"
            :selected="headerFooterForm.footer_nav_page_ids"
            @close="showNavChooser = false"
            @apply="applyFooterNavPages"
        />

        <PageHistoryModal :show="showHistory" :club-slug="club.slug" :page-id="form.id" @close="showHistory = false" @restore="restoreRevision" />

        <!-- Spatie Media Library Modal Component -->
        <MediaLibraryModal
            :show="showMediaModal"
            :club-slug="club.slug"
            :default-folder="mediaDefaultFolder"
            @close="showMediaModal = false"
            @select="onMediaSelect"
        />

        <PageUnsavedDialog :open="showUnsavedModal" :form="form" @save="handleSaveAndProceed" @discard="handleDiscardAndProceed" @stay="handleStayAndEdit" />

        <PageThemeConfirmDialog :theme="showThemeConfirmModal ? selectedThemeForModal : null" :theme-form="themeForm" @cancel="closeApplyThemeModal" @confirm="confirmApplyTheme" />

    </AdminLayout>
</template>
