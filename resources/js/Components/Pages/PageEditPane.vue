<script setup>
import BlockSummary from '@/Components/Blocks/BlockSummary.vue';
import PageEditBlockFields from '@/Components/Pages/PageEditBlockFields.vue';
import PageEditBlockHeader from '@/Components/Pages/PageEditBlockHeader.vue';
import PageEditDetailsCard from '@/Components/Pages/PageEditDetailsCard.vue';
import PageEditInsertPoint from '@/Components/Pages/PageEditInsertPoint.vue';
import PageEditSeoCard from '@/Components/Pages/PageEditSeoCard.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { formatLocal } from '@/Utils/localDateTime';

// A section of the website builder page, in its own file so the page stays readable.
const activeInsertIndex = defineModel('activeInsertIndex', { type: [String, Number, Boolean, Array, Object], default: undefined });
const blockToDelete = defineModel('blockToDelete', { type: [String, Number, Boolean, Array, Object], default: undefined });
const publishAtLocal = defineModel('publishAtLocal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const seoOpen = defineModel('seoOpen', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showHistory = defineModel('showHistory', { type: [String, Number, Boolean, Array, Object], default: undefined });
const unpublishAtLocal = defineModel('unpublishAtLocal', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    club: { type: [Object, Array], default: undefined },
    pages: { type: [Object, Array], default: undefined },
    clubAddresses: { type: [Object, Array], default: undefined },
    activePage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    addBlock: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    addGalleryImage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    applySectionToAll: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    blockCardId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    blockColumns: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canDraft: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    collapseAll: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    collapsedCount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyBlockTo: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyNotice: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyNoticeIsError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyPreviewLink: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    copyTargets: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    discardDraft: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    duplicateBlock: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    expandAll: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    hasDraft: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    insertMenuPlacement: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    insertMenuShift: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isCollapsed: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isDefaultPage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isDirty: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isSavedSuccess: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    lifecycleText: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    moveBlockDown: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    moveBlockUp: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    newBlockId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    onYoutubeUrlInput: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openMediaLibrary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openPreviewPanel: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewCopied: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewOpen: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewUrl: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    publishDraft: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    removeBlockImage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    removeGalleryImage: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    renewPreviewLink: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoreNotice: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    saveDraft: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    searchPreview: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    setYoutubeTime: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    settingsForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleCollapsed: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleInsertMenu: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    triggerDeleteModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <div class="space-y-6">

        <!-- Unpublished draft -->
        <Alert v-if="hasDraft" variant="warning">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p><strong class="font-medium">You are editing an unpublished draft</strong> saved {{ formatLocal(activePage?.draft_saved_at) }}. Visitors still see the live page until you press Publish draft.</p>
                <Button variant="secondary" size="sm" @click="discardDraft">Discard draft</Button>
            </div>
        </Alert>

        <Alert v-if="restoreNotice" variant="info">{{ restoreNotice }}</Alert>
        <Alert v-if="copyNotice" :variant="copyNoticeIsError ? 'danger' : 'success'">{{ copyNotice }}</Alert>

        <!-- Page details card -->
        <PageEditDetailsCard
            v-model:show-history="showHistory"
            v-model:publish-at-local="publishAtLocal"
            v-model:unpublish-at-local="unpublishAtLocal"
            :form="form"
            :club="club"
            :active-page="activePage"
            :has-draft="hasDraft"
            :can-draft="canDraft"
            :is-saved-success="isSavedSuccess"
            :preview-open="previewOpen"
            :preview-url="previewUrl"
            :preview-copied="previewCopied"
            :preview-error="previewError"
            :submit-form="submitForm"
            :save-draft="saveDraft"
            :publish-draft="publishDraft"
            :open-preview-panel="openPreviewPanel"
            :copy-preview-link="copyPreviewLink"
            :renew-preview-link="renewPreviewLink"
            :lifecycle-text="lifecycleText"
        />

        <!-- SEO and web address (collapsible) -->
        <PageEditSeoCard v-model:seo-open="seoOpen" :form="form" :club="club" :search-preview="searchPreview" :open-media-library="openMediaLibrary" />

        <!-- Compact / expand every element -->
        <div v-if="form.blocks.length" class="flex items-center justify-end gap-2 text-xs">
            <span class="text-slate-500 dark:text-slate-400 font-medium" aria-live="polite">{{ collapsedCount }} of {{ form.blocks.length }} compact</span>
            <div class="inline-flex items-center gap-1" role="group" aria-label="Compact or expand all elements">
                <Button variant="secondary" size="sm" :disabled="collapsedCount === form.blocks.length" @click="collapseAll">Compact all</Button>
                <Button variant="secondary" size="sm" :disabled="collapsedCount === 0" @click="expandAll">Expand all</Button>
            </div>
        </div>

        <!-- Reorderable list of the page's elements -->
        <div class="space-y-3">
            <template v-for="(block, bIdx) in form.blocks" :key="block.id || bIdx">

                <!-- Insert point above the first element (index 0) -->
                <PageEditInsertPoint
                    v-if="bIdx === 0"
                    v-model:active-insert-index="activeInsertIndex"
                    :index="0"
                    label="Insert an element at the top"
                    :block-columns="blockColumns"
                    :insert-menu-placement="insertMenuPlacement"
                    :insert-menu-shift="insertMenuShift"
                    :add-block="addBlock"
                    :toggle-insert-menu="toggleInsertMenu"
                />

                <!-- Element card -->
                <div :id="blockCardId(block, bIdx)" :class="['scroll-mt-24 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-shadow hover:shadow-md', newBlockId === block.id ? 'ring-2 ring-blue-500 ring-offset-2 ring-offset-transparent' : '']">

                    <!-- Element header and controls -->
                    <PageEditBlockHeader
                        v-model:block-to-delete="blockToDelete"
                        :block="block"
                        :index="bIdx"
                        :form="form"
                        :copy-targets="copyTargets"
                        :is-dirty="isDirty"
                        :is-saved-success="isSavedSuccess"
                        :copy-block-to="copyBlockTo"
                        :duplicate-block="duplicateBlock"
                        :is-collapsed="isCollapsed"
                        :move-block-down="moveBlockDown"
                        :move-block-up="moveBlockUp"
                        :submit-form="submitForm"
                        :toggle-collapsed="toggleCollapsed"
                    />

                    <!-- Compact view: read-only summary -->
                    <div v-if="isCollapsed(block)" :class="['p-4', block.hidden ? 'opacity-50' : '']">
                        <BlockSummary :block="block" />
                    </div>

                    <!-- Element editors -->
                    <PageEditBlockFields
                        v-else
                        :block="block"
                        :index="bIdx"
                        :form="form"
                        :club="club"
                        :pages="pages"
                        :club-addresses="clubAddresses"
                        :settings-form="settingsForm"
                        :add-gallery-image="addGalleryImage"
                        :apply-section-to-all="applySectionToAll"
                        :on-youtube-url-input="onYoutubeUrlInput"
                        :open-media-library="openMediaLibrary"
                        :remove-block-image="removeBlockImage"
                        :remove-gallery-image="removeGalleryImage"
                        :set-youtube-time="setYoutubeTime"
                    />
                </div>

                <!-- Insert point below the element -->
                <PageEditInsertPoint
                    v-model:active-insert-index="activeInsertIndex"
                    :index="bIdx + 1"
                    :block-columns="blockColumns"
                    :insert-menu-placement="insertMenuPlacement"
                    :insert-menu-shift="insertMenuShift"
                    :add-block="addBlock"
                    :toggle-insert-menu="toggleInsertMenu"
                />
            </template>
            <!-- Room below the last block so a newly added block can always be scrolled up to the top of the view -->
            <div v-if="form.blocks.length" class="h-[45vh]" aria-hidden="true"></div>

            <div v-if="!form.blocks.length" class="flex flex-col items-center text-center py-10 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                <span class="w-10 h-10 rounded-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-400">
                    <UiIcon name="document" :size="20" />
                </span>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No content elements added yet.</p>
                <Button variant="secondary" size="sm" @click="addBlock('text')">
                    <UiIcon name="plus" :size="14" />
                    <span>Add a first text block</span>
                </Button>
            </div>
        </div>

        <!-- Page actions -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
            <Button
                v-if="form.id && !isDefaultPage(activePage)"
                variant="danger"
                @click="triggerDeleteModal(activePage)"
            >
                <UiIcon name="trash" />
                <span>Delete custom page</span>
            </Button>
            <span v-else class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 font-medium">
                <UiIcon name="info" :size="14" />
                Default system pages cannot be deleted (only published or unpublished).
            </span>

            <Button size="lg" :loading="form.processing" @click="submitForm">
                <UiIcon v-if="!form.processing" :name="isSavedSuccess ? 'check' : 'save'" />
                <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? 'Saved' : 'Save and publish page') }}</span>
            </Button>
        </div>
    </div>
</template>
