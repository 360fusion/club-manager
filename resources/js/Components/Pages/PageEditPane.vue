<script setup>
import BlockSummary from '@/Components/Blocks/BlockSummary.vue';
import CalendarEditor from '@/Components/Blocks/Editors/CalendarEditor.vue';
import CtaBannerEditor from '@/Components/Blocks/Editors/CtaBannerEditor.vue';
import DownloadsEditor from '@/Components/Blocks/Editors/DownloadsEditor.vue';
import FaqEditor from '@/Components/Blocks/Editors/FaqEditor.vue';
import FeatureCardsEditor from '@/Components/Blocks/Editors/FeatureCardsEditor.vue';
import HeroEditor from '@/Components/Blocks/Editors/HeroEditor.vue';
import LinkField from '@/Components/Blocks/Editors/LinkField.vue';
import MapEditor from '@/Components/Blocks/Editors/MapEditor.vue';
import MediaTextEditor from '@/Components/Blocks/Editors/MediaTextEditor.vue';
import QuoteMottoEditor from '@/Components/Blocks/Editors/QuoteMottoEditor.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import SectionHeadingEditor from '@/Components/Blocks/Editors/SectionHeadingEditor.vue';
import SectionStyleEditor from '@/Components/Blocks/Editors/SectionStyleEditor.vue';
import SlideshowEditor from '@/Components/Blocks/Editors/SlideshowEditor.vue';
import StatsEditor from '@/Components/Blocks/Editors/StatsEditor.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import { formatLocal } from '@/Utils/localDateTime';
import { formatTimestamp, parseYouTubeUrl } from '@/Utils/youtube';

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
        <div v-if="hasDraft" class="rounded-2xl border border-amber-300 dark:border-amber-700/60 bg-amber-50 dark:bg-amber-950/30 p-4 flex flex-wrap items-center justify-between gap-3 text-xs">
            <p class="text-amber-900 dark:text-amber-200"><strong>You are editing an unpublished draft</strong> saved {{ formatLocal(activePage?.draft_saved_at) }}. Visitors still see the live page until you press Publish draft.</p>
            <div class="flex items-center gap-2">
                <button type="button" @click="discardDraft" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-amber-300 dark:border-amber-700/60 text-amber-800 dark:text-amber-200 font-bold cursor-pointer">Discard draft</button>
            </div>
        </div>

        <div v-if="restoreNotice" role="status" class="rounded-2xl border border-blue-200 dark:border-blue-800/60 bg-blue-50 dark:bg-blue-950/30 p-3 text-xs font-bold text-blue-800 dark:text-blue-200">{{ restoreNotice }}</div>
        <div v-if="copyNotice" role="status" :class="['rounded-2xl border p-3 text-xs font-bold', copyNoticeIsError ? 'border-rose-200 dark:border-rose-800/60 bg-rose-50 dark:bg-rose-950/30 text-rose-800 dark:text-rose-200' : 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-800 dark:text-emerald-200']">{{ copyNotice }}</div>

        <!-- Page Particulars Card -->
        <div class="bg-white dark:bg-slate-900 p-6 sm:p-7 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-5 space-y-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Page Particulars & Navigation Settings</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure page title, permalink URL slug, and navigation visibility.</p>
                </div>

                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    <!-- Saving: the first puts your edits on the live page, the second keeps them aside -->
                    <div role="group" aria-label="Save">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Save</p>
                        <div class="flex items-center gap-1.5">
                            <div class="relative group">
                            <button
                                @click="submitForm" aria-describedby="tip-save"
                                :disabled="form.processing"
                                :class="[
                                    'px-2.5 py-1.5 font-bold text-[11px] rounded-xl shadow-md transition-all duration-300 flex items-center gap-1.5 cursor-pointer',
                                    form.processing ? 'bg-blue-500 text-white cursor-wait opacity-80' :
                                    isSavedSuccess ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30 scale-105 ring-2 ring-emerald-400/50' :
                                    'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20'
                                ]"
                            >
                                <svg v-if="form.processing" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <svg v-else-if="isSavedSuccess" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved!' : 'Save page') }}</span>
                            </button>
                                <span id="tip-save" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 left-0">{{ hasDraft ? 'Saves the page as it looks here and makes it live straight away, replacing the draft. To publish the draft as it was saved, use Publish draft.' : 'Saves your changes and makes them live straight away.' }}</span>
                            </div>
                            <div v-if="canDraft" class="relative group">
                                <button type="button" @click="saveDraft" :disabled="form.processing" aria-describedby="tip-draft" class="py-1.5 px-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                                    <span>Save draft</span>
                                </button>
                                <span id="tip-draft" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 left-0">Keeps your edits aside. Visitors still see the live page until you publish the draft.</span>
                            </div>
                            <div v-if="canDraft" class="relative group">
                                <button type="button" @click="publishDraft" :disabled="!hasDraft || form.processing" aria-describedby="tip-publish-draft" :class="['py-1.5 px-2 rounded-xl font-bold text-[11px] border transition-colors flex items-center gap-1 whitespace-nowrap disabled:cursor-not-allowed', hasDraft ? 'bg-amber-500 hover:bg-amber-600 border-amber-500 text-white shadow-md cursor-pointer' : 'bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-400 dark:text-slate-500 opacity-60']">
                                    <span>Publish draft</span>
                                </button>
                                <span id="tip-publish-draft" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 left-0">{{ hasDraft ? 'Makes your saved draft live for visitors.' : 'There is no draft to publish. Use Save as draft first.' }}</span>
                            </div>
                            <div v-if="form.id" class="relative group">
                                <button type="button" @click="showHistory = true" aria-describedby="tip-history" aria-label="History" class="py-1.5 px-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap !px-2">
                                    <span aria-hidden="true">🕘</span>
                                </button>
                                <span id="tip-history" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 left-0">History: earlier saves of this page. Restore one into the editor, then save to keep it.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Previewing: the first is what visitors see now, the second is a secret link that includes drafts -->
                    <div v-if="form.id" role="group" aria-label="Preview">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-1">Preview</p>
                        <div class="flex items-center gap-1.5">
                            <div class="relative group">
                                <a v-if="form.slug && activePage?.lifecycle_status === 'live'" :href="form.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${form.slug}`" target="_blank" rel="noopener" aria-describedby="tip-live" class="py-1.5 px-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                                    <span>View live ↗</span>
                                </a>
                                <button v-else type="button" disabled aria-describedby="tip-live" class="py-1.5 px-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                                    <span>View live ↗</span>
                                </button>
                                <span id="tip-live" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 right-0">{{ activePage?.lifecycle_status === 'live' ? 'View live page: opens the page as visitors see it right now, in a new tab.' : "View live page: this page is not live yet, so visitors can't see it. Use Draft preview instead." }}</span>
                            </div>
                            <div class="relative group">
                                <button type="button" @click="openPreviewPanel" :aria-expanded="previewOpen" aria-describedby="tip-private" class="py-1.5 px-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[11px] border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap">
                                    <span>Draft preview</span>
                                </button>
                                <span id="tip-private" role="tooltip" class="pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150 right-0">Makes a secret link that shows this page with your draft, even before it is live. Share it with someone to get their feedback.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Private preview link -->
            <div v-if="previewOpen" class="rounded-2xl border border-violet-200 dark:border-violet-800/60 bg-violet-50/60 dark:bg-violet-950/20 p-4 space-y-2 text-xs">
                <p class="font-bold text-violet-900 dark:text-violet-200">Draft preview link</p>
                <p class="text-slate-600 dark:text-slate-300">Anyone with this link can see this page{{ hasDraft ? ', including its unsaved draft,' : '' }} even if it is not live yet{{ form.is_members_only ? ', and even though it is for members only' : '' }}. Only share it with people you trust.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input :value="previewUrl" readonly aria-label="Draft preview link" @focus="$event.target.select()" class="flex-1 min-w-[220px] px-3 py-2 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 font-mono text-[11px] text-slate-700 dark:text-slate-200" />
                    <button type="button" :disabled="!previewUrl" @click="copyPreviewLink" class="py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50">{{ previewCopied ? '✓ Copied' : 'Copy link' }}</button>
                    <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noopener" class="py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50">Open ↗</a>
                    <button type="button" :disabled="!previewUrl" @click="renewPreviewLink" class="py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1.5 cursor-pointer disabled:opacity-50">New link</button>
                </div>
                <p v-if="previewError" class="font-bold text-rose-600 dark:text-rose-400">{{ previewError }}</p>
            </div>

            <div class="text-xs">
                <label for="page-title" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page Title</label>
                <input id="page-title" v-model="form.title" type="text" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-semibold outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="e.g. About Our Club" />
            </div>

            <div class="flex flex-wrap items-center gap-6 pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200 select-none">
                    <input type="checkbox" v-model="form.is_published" :disabled="form.is_homepage || form.slug === 'home'" class="w-4 h-4 rounded text-emerald-600 dark:text-emerald-400 focus:ring-emerald-500 accent-emerald-600 cursor-pointer disabled:opacity-50" />
                    <span>Published & Active</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200 select-none">
                    <input type="checkbox" v-model="form.show_in_navigation" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                    <span>Show in Top Header Menu</span>
                </label>

                <label v-if="!form.is_homepage" class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200 select-none">
                    <input type="checkbox" v-model="form.is_members_only" class="w-4 h-4 rounded text-amber-600 dark:text-amber-400 focus:ring-amber-500 accent-amber-600 cursor-pointer" />
                    <span>🔒 Members Only</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-200 select-none" title="A separate menu of links at the bottom of every page">
                    <input type="checkbox" v-model="form.show_in_footer" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                    <span>Show in Footer Menu</span>
                </label>
            </div>

            <!-- Top menu style and schedule -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label for="page-header-style" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Top menu on this page</label>
                    <select id="page-header-style" v-model="form.header_style" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20 font-bold">
                        <option value="full">Full header (menu and buttons)</option>
                        <option value="logo_only">Logo only</option>
                        <option value="hidden">No header at all</option>
                    </select>
                    <p class="text-[10px] text-slate-400 mt-1">Handy for a landing page.</p>
                </div>
                <div v-if="!form.is_homepage">
                    <label for="page-publish-at" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Go live on <span class="font-normal normal-case text-slate-400">(optional)</span></label>
                    <input id="page-publish-at" v-model="publishAtLocal" type="datetime-local" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>
                <div v-if="!form.is_homepage">
                    <label for="page-unpublish-at" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Come down on <span class="font-normal normal-case text-slate-400">(optional)</span></label>
                    <input id="page-unpublish-at" v-model="unpublishAtLocal" type="datetime-local" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20" />
                    <p v-if="form.publish_at && form.unpublish_at && form.unpublish_at <= form.publish_at" class="text-[10px] font-bold text-rose-600 dark:text-rose-400 mt-1">This must be after the go-live time.</p>
                    <p v-if="form.errors.unpublish_at" class="text-[10px] font-bold text-rose-600 dark:text-rose-400 mt-1">{{ form.errors.unpublish_at }}</p>
                </div>
                <p v-if="!form.is_homepage" class="sm:col-span-3 text-[10px] text-slate-400 -mt-2">Times are in your own timezone. The page must also be ticked as Published for these dates to apply.<template v-if="lifecycleText(activePage)"> <strong class="text-slate-600 dark:text-slate-300">{{ lifecycleText(activePage) }}.</strong></template></p>
            </div>
        </div>

        <!-- SEO & URL Card (collapsible) -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm">
            <button
                type="button"
                @click="seoOpen = !seoOpen"
                class="w-full flex items-center gap-3 p-6 sm:px-7 text-left cursor-pointer"
                :aria-expanded="seoOpen"
                aria-controls="page-seo-panel"
            >
                <svg class="w-4 h-4 shrink-0 text-slate-500 dark:text-slate-400 transition-transform" :class="seoOpen ? '' : '-rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                </svg>
                <span class="min-w-0">
                    <span class="block text-base font-bold text-slate-900 dark:text-white">Search Engine Optimization (this page)</span>
                    <span v-if="seoOpen" class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">The page's web address, and what search engines show for it. Overrides the site-wide SEO defaults; leave the title and description blank to use them.</span>
                    <span v-else class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-mono truncate">/site/{{ club.slug }}/{{ form.is_homepage ? '' : form.slug }}</span>
                </span>
            </button>

            <div v-show="seoOpen" id="page-seo-panel" class="px-6 sm:px-7 pb-6 sm:pb-7 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 sm:items-end gap-4 text-xs">
                    <div>
                        <div class="flex flex-wrap items-center gap-x-2 mb-1.5">
                            <label for="page-slug" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px]">URL Slug (Permalink)</label>
                            <span class="text-slate-300">|</span>
                            <span class="text-slate-400 font-mono text-[11px]">/site/{{ club.slug }}/</span>
                        </div>
                        <input id="page-slug" v-model="form.slug" type="text" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-mono font-bold outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="about" />
                    </div>
                    <div>
                        <label for="page-meta-title" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page SEO Title</label>
                        <input id="page-meta-title" v-model="form.meta_title" type="text" maxlength="255" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-semibold outline-none focus:ring-2 focus:ring-blue-500/20" :placeholder="form.title || 'Page title'" />
                    </div>
                </div>
                <div class="text-xs">
                    <label for="page-meta-description" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page Meta Description</label>
                    <textarea id="page-meta-description" v-model="form.meta_description" rows="3" maxlength="500" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Site-wide default description"></textarea>
                    <p class="text-[10px] text-slate-400 mt-1">{{ (form.meta_description || '').length }}/500. Search engines usually show around 155 characters.</p>
                </div>

                <!-- How it may look in a search engine -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40 p-4 text-left" aria-label="Search result preview">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Search result preview</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ searchPreview.url }}</p>
                    <p class="text-base font-semibold text-blue-700 dark:text-blue-400 truncate">{{ searchPreview.title }}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">{{ searchPreview.description }}</p>
                    <p v-if="searchPreview.title.length > 60" class="mt-2 text-[10px] font-bold text-amber-600 dark:text-amber-400">The title is {{ searchPreview.title.length }} characters; search engines usually cut it off after about 60.</p>
                    <p v-if="(form.meta_description || '').length > 160" class="mt-1 text-[10px] font-bold text-amber-600 dark:text-amber-400">The description is long; search engines usually cut it off after about 160 characters.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="page-share-image" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px]">Share image</label>
                            <div class="flex items-center gap-1.5">
                                <button v-if="form.share_image" type="button" @click="form.share_image = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-xl border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                <button type="button" @click="openMediaLibrary('page_share_image', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media library</button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="page-share-image" v-model="form.share_image" type="text" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20 font-mono text-[11px]" placeholder="Site-wide share image is used" />
                            <img v-if="form.share_image" :src="form.share_image" alt="" class="w-16 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0" />
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">The picture shown when this page is shared on Facebook, WhatsApp or Messages. About 1200 × 630 works best.</p>
                    </div>
                    <div>
                        <span class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Search engines</span>
                        <label :class="['flex items-start gap-2.5 select-none', form.is_homepage || form.slug === 'home' ? 'opacity-50' : 'cursor-pointer']">
                            <input type="checkbox" v-model="form.noindex" :disabled="form.is_homepage || form.slug === 'home'" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer disabled:cursor-not-allowed" />
                            <span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">Hide this page from search engines</span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">People with the link can still open it. It is also left out of the sitemap. The home page always stays visible.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact / expand every element -->
        <div v-if="form.blocks.length" class="flex items-center justify-end gap-2 text-xs">
            <span class="text-slate-500 dark:text-slate-400 font-semibold" aria-live="polite">{{ collapsedCount }} of {{ form.blocks.length }} compact</span>
            <div class="inline-flex rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700" role="group" aria-label="Compact or expand all elements">
                <button type="button" @click="collapseAll" :disabled="collapsedCount === form.blocks.length" class="px-3 py-1.5 font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Compact all</button>
                <button type="button" @click="expandAll" :disabled="collapsedCount === 0" class="px-3 py-1.5 font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 border-l border-slate-300 dark:border-slate-700 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">Expand all</button>
            </div>
        </div>

        <!-- Reorderable Block Element Stack List -->
        <div class="space-y-3">
            <template v-for="(block, bIdx) in form.blocks" :key="block.id || bIdx">
                
                <!-- Insert Divider Above First Block (Index 0) -->
                <div v-if="bIdx === 0" class="relative py-1 flex items-center justify-center insert-menu-container">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <button
                            type="button"
                            @click.stop="toggleInsertMenu(0, $event)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 rounded-full text-xs font-bold shadow-sm transition-all cursor-pointer hover:scale-105"
                        >
                            <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 flex items-center justify-center text-xs font-black">+</span>
                            <span>Insert block at top</span>
                        </button>

                        <!-- Insert Menu Dropdown Popover -->
                        <div
                            v-if="activeInsertIndex === 0"
                            :class="[
                                'absolute z-40 w-[1100px] max-w-[calc(100vw-3.5rem)] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                            ]"
                            :style="{ translate: `calc(-50% + ${insertMenuShift}px) 0` }"
                        >
                            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                <span>Insert Element Here</span>
                                <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs cursor-pointer p-1">✕</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-1">
                                <div v-for="(col, cIdx) in blockColumns" :key="cIdx" class="space-y-1.5">
                                    <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                        {{ col.title }}
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="item in col.items"
                                            :key="item.type"
                                            type="button"
                                            @click="addBlock(item.type, 0)"
                                            class="w-full flex items-start gap-2.5 p-2 text-left text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-blue-200 dark:hover:border-blue-800/60 rounded-xl transition-all cursor-pointer group"
                                        >
                                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-black shrink-0 transition-transform group-hover:scale-110" :class="[item.bg, item.color]">
                                                {{ item.icon }}
                                            </span>
                                            <div class="min-w-0">
                                                <span class="block font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">{{ item.label }}</span>
                                                <span class="text-[10px] text-slate-400 font-normal leading-tight block line-clamp-2">{{ item.desc }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Block Item Card Container -->
                <div :id="blockCardId(block, bIdx)" :class="['scroll-mt-24 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md shadow-slate-200/60 overflow-hidden transition-all hover:shadow-lg', newBlockId === block.id ? 'ring-2 ring-blue-500 ring-offset-2 ring-offset-transparent' : '']">
                    
                    <!-- Block Header & Controls -->
                    <div class="flex items-center justify-between px-4 py-2.5 bg-slate-200/80 dark:bg-slate-700/80 border-b border-slate-300/80 dark:border-slate-700/80 text-xs font-bold text-slate-800 dark:text-slate-100">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="toggleCollapsed(block)"
                                class="w-6 h-6 rounded flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white/70 dark:hover:bg-slate-900/60 cursor-pointer"
                                :aria-expanded="!isCollapsed(block)"
                                :title="isCollapsed(block) ? 'Expand to edit this element' : 'Compact this element'"
                                :aria-label="isCollapsed(block) ? 'Expand element' : 'Compact element'"
                            >
                                <svg class="w-3.5 h-3.5 transition-transform" :class="isCollapsed(block) ? '-rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <span class="w-5 h-5 rounded bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[11px] font-black border border-slate-200 dark:border-slate-800">
                                {{ bIdx + 1 }}
                            </span>

                            <!-- Type Badges -->
                            <span v-if="block.type === 'text' || block.type === 'rich_text'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                📝 Text Block
                            </span>
                            <span v-else-if="block.type === 'image'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                🖼️ Single Image
                            </span>
                            <span v-else-if="block.type === 'images'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                🖼️ Image Gallery ({{ block.columns || 3 }} Cols)
                            </span>
                            <span v-else-if="block.type === 'notice'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                📢 Callout Box
                            </span>
                            <span v-else-if="block.type === 'button'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                🔗 Button Link
                            </span>
                            <span v-else-if="block.type === 'youtube'" class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 text-[10px] uppercase font-bold">
                                ▶️ YouTube Video
                            </span>
                            <span v-else-if="block.type === 'downloads'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                📥 Downloads
                            </span>
                            <span v-else-if="block.type === 'faq'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                ❓ FAQ
                            </span>
                            <span v-else-if="block.type === 'map'" class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-[10px] uppercase font-bold">
                                🗺️ Map
                            </span>
                            <span v-else-if="block.type === 'cta_banner'" class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-[10px] uppercase font-bold">
                                📣 Call-to-Action
                            </span>
                            <span v-else-if="block.type === 'calendar'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                🗓️ Events Calendar
                            </span>
                            <span v-else-if="block.type === 'hero'" class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-[10px] uppercase font-bold">
                                🚀 Hero Banner
                            </span>
                            <span v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                📰 News List ({{ String(block.columns) === 'masonry' ? 'Masonry' : (block.columns || 3) + ' Cols' }})
                            </span>
                            <span v-else-if="block.type === 'events_calendar'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                📅 Dynamic Events & Summons
                            </span>
                            <span v-else-if="block.type === 'pricing_cards'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                💳 Membership Dues
                            </span>
                            <span v-else-if="block.type === 'donation_campaign'" class="px-2 py-0.5 rounded bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 text-[10px] uppercase font-bold">
                                💰 Dynamic Donation
                            </span>
                            <span v-else-if="block.type === 'contact_details'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                📇 Contact Cards
                            </span>
                            <span v-else-if="block.type === 'contact_form'" class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 text-[10px] uppercase font-bold">
                                📝 Contact Form
                            </span>
                            <span v-else-if="block.type === 'slideshow'" class="px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 text-[10px] uppercase font-bold">
                                🎞️ Slideshow ({{ (block.slides || []).filter((slide) => slide.image_url).length }} Photos)
                            </span>
                            <span v-else-if="block.type === 'section_heading'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                🔠 Section Heading
                            </span>
                            <span v-else-if="block.type === 'feature_cards'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                🃏 Icon Cards ({{ block.columns || 3 }} Cols)
                            </span>
                            <span v-else-if="block.type === 'stats'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                🔢 Stats Row
                            </span>
                            <span v-else-if="block.type === 'media_text'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                🖼️ Image &amp; Text
                            </span>
                            <span v-else-if="block.type === 'quote_motto'" class="px-2 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase font-bold">
                                ❝ Motto
                            </span>
                        </div>

                        <!-- Control Buttons (Up, Down, Duplicate, Delete) -->
                        <div class="flex items-center gap-1">
                            <ToggleSwitch
                                :model-value="!block.hidden"
                                :label="`Show element ${bIdx + 1} on the live site`"
                                class="mr-1"
                                @update:model-value="block.hidden = !$event"
                            />
                            <template v-if="block.type !== 'hero'">
                                <select
                                    :value="block.block_width || 'full'"
                                    @change="block.block_width = $event.target.value"
                                    class="px-1.5 py-1 rounded-lg text-[11px] font-bold border bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 cursor-pointer"
                                    title="How wide this element is on the page"
                                    aria-label="Element width"
                                >
                                    <option value="full">Full width</option>
                                    <option value="three_quarter">75% width</option>
                                    <option value="half">50% width</option>
                                </select>
                                <select
                                    v-if="block.block_width && block.block_width !== 'full'"
                                    :value="block.block_align || 'center'"
                                    @change="block.block_align = $event.target.value"
                                    class="px-1.5 py-1 rounded-lg text-[11px] font-bold border bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 cursor-pointer"
                                    title="Where a narrower element sits"
                                    aria-label="Element position"
                                >
                                    <option value="center">Centered</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </template>
                            <select
                                v-if="copyTargets.length"
                                @change="copyBlockTo(block, $event)"
                                class="px-1.5 py-1 rounded-lg text-[11px] font-bold border bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 cursor-pointer max-w-[7.5rem]"
                                title="Add a copy of this element to the end of another page"
                                aria-label="Copy this element to another page"
                            >
                                <option value="">Copy to…</option>
                                <option v-for="target in copyTargets" :key="target.id" :value="target.id">{{ target.title }}</option>
                            </select>
                            <button
                                type="button"
                                @click="submitForm()"
                                :disabled="form.processing"
                                :class="[
                                    'mr-1 px-2.5 py-1 rounded-xl text-[11px] font-bold flex items-center gap-1.5 cursor-pointer border transition-colors disabled:opacity-60 disabled:cursor-wait',
                                    isSavedSuccess
                                        ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60'
                                        : isDirty
                                            ? 'bg-blue-600 hover:bg-blue-700 text-white border-blue-600'
                                            : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700'
                                ]"
                                title="Save this page"
                                aria-label="Save this page"
                            >
                                <span aria-hidden="true">{{ isSavedSuccess ? '✓' : '💾' }}</span>
                                <span>{{ form.processing ? 'Saving…' : isSavedSuccess ? 'Saved' : 'Save' }}</span>
                            </button>
                            <button
                                type="button"
                                @click="moveBlockUp(bIdx)"
                                :disabled="bIdx === 0"
                                class="p-1 min-w-6 min-h-6 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white disabled:opacity-30 cursor-pointer"
                                title="Move Up"
                                aria-label="Move block up"
                            >
                                ▲
                            </button>
                            <button
                                type="button"
                                @click="moveBlockDown(bIdx)"
                                :disabled="bIdx === form.blocks.length - 1"
                                class="p-1 min-w-6 min-h-6 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white disabled:opacity-30 cursor-pointer"
                                title="Move Down"
                                aria-label="Move block down"
                            >
                                ▼
                            </button>
                            <button
                                type="button"
                                @click="duplicateBlock(bIdx)"
                                class="p-1 min-w-6 min-h-6 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
                                title="Duplicate Element"
                                aria-label="Duplicate block"
                            >
                                📋
                            </button>
                            <button
                                type="button"
                                @click="blockToDelete = bIdx"
                                class="p-1 min-w-6 min-h-6 flex items-center justify-center text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer"
                                title="Delete Element"
                                aria-label="Delete block"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>

                    <!-- Compact view: read-only summary -->
                    <div v-if="isCollapsed(block)" :class="['p-4', block.hidden ? 'opacity-50' : '']">
                        <BlockSummary :block="block" />
                    </div>

                    <!-- Block Body Editors -->
                    <div v-else class="p-4 space-y-3">
                        
                        <!-- 1. Text / Rich Text Block -->
                        <div v-if="block.type === 'text' || block.type === 'rich_text'" class="space-y-3">
                            <div>
                                <label :for="`block-${bIdx}-heading`" class="block font-bold text-slate-700 dark:text-slate-200 text-xs mb-1">Section Heading (Optional)</label>
                                <input :id="`block-${bIdx}-heading`" v-model="block.heading" type="text" placeholder="e.g. Our History & Mission" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold" />
                            </div>
                            <div>
                                <label :id="`block-${bIdx}-content-label`" class="block font-bold text-slate-700 dark:text-slate-200 text-xs mb-1">Body Text Content</label>
                                <RichTextEditor v-model="block.content" placeholder="Write formatted text content here..." :aria-labelledby="`block-${bIdx}-content-label`" />
                            </div>
                            <div>
                                <label :for="`block-${bIdx}-text-align`" class="block font-bold text-slate-700 dark:text-slate-200 text-xs mb-1">Text alignment</label>
                                <select :id="`block-${bIdx}-text-align`" v-model="block.text_align" class="w-full max-w-[12rem] p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold text-xs">
                                    <option value="left">Left aligned</option>
                                    <option value="center">Centred</option>
                                    <option value="right">Right aligned</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. Single Image Block -->
                        <div v-else-if="block.type === 'image'" class="space-y-3 text-xs">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label :for="`block-${bIdx}-image-url`" class="block font-bold text-slate-700 dark:text-slate-200">Image URL</label>
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                v-if="block.url"
                                                type="button"
                                                @click="removeBlockImage(block)"
                                                class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-xl border border-rose-200 dark:border-rose-800/60 cursor-pointer"
                                            >
                                                🗑️ Clear
                                            </button>
                                            <button
                                                type="button"
                                                @click="openMediaLibrary('block_image', block, 'pages')"
                                                class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 cursor-pointer"
                                            >
                                                📁 Media library
                                            </button>
                                        </div>
                                    </div>
                                    <input :id="`block-${bIdx}-image-url`" v-model="block.url" type="text" placeholder="https://example.com/photo.jpg" class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-caption`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Caption / Alt Text</label>
                                    <input :id="`block-${bIdx}-caption`" v-model="block.caption" type="text" placeholder="e.g. Annual Dinner at the Lodge" class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div>
                                    <label :for="`block-${bIdx}-position`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Image Positioning</label>
                                    <select :id="`block-${bIdx}-position`" v-model="block.position" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="left">Left Aligned</option>
                                        <option value="center">Centered</option>
                                        <option value="right">Right Aligned</option>
                                        <option value="full">Full Width Banner</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-size`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Image Sizing</label>
                                    <select :id="`block-${bIdx}-size`" v-model="block.size" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="small">Small (25% Width)</option>
                                        <option value="medium">Medium (50% Width)</option>
                                        <option value="large">Large (75% Width)</option>
                                        <option value="full">Full Container Width (100%)</option>
                                    </select>
                                </div>
                            </div>

                            <div v-if="block.url" class="pt-2">
                                <span class="text-[10px] font-bold text-slate-400 block mb-1">Preview Thumbnail:</span>
                                <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full']">
                                    <div :class="[
                                        'rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1',
                                        block.size === 'small' ? 'w-1/4' : block.size === 'medium' ? 'w-1/2' : block.size === 'large' ? 'w-3/4' : 'w-full'
                                    ]">
                                        <img :src="block.url" class="w-full h-auto max-h-48 object-cover rounded-lg" />
                                        <p v-if="block.caption" class="text-[11px] text-center text-slate-500 dark:text-slate-400 italic mt-1">{{ block.caption }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Image Gallery Block -->
                        <div v-else-if="block.type === 'images'" class="space-y-3 text-xs">
                            <div class="flex items-center justify-between p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-2">
                                    <label :for="`block-${bIdx}-columns`" class="font-bold text-slate-700 dark:text-slate-200">Grid Columns Layout:</label>
                                    <select :id="`block-${bIdx}-columns`" v-model="block.columns" class="p-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-lg font-bold">
                                        <option :value="2">2 Columns</option>
                                        <option :value="3">3 Columns</option>
                                        <option :value="4">4 Columns</option>
                                    </select>
                                </div>

                                <button
                                    type="button"
                                    @click="addGalleryImage(block)"
                                    class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all cursor-pointer flex items-center gap-1"
                                >
                                    ➕ Add image item
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="(gItem, gIdx) in block.items" :key="gItem.id || gIdx" class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 relative">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-600 dark:text-slate-300 text-[11px]">Image #{{ gIdx + 1 }}</span>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                @click="openMediaLibrary('gallery_image', gItem, 'pages')"
                                                class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 cursor-pointer"
                                            >
                                                📁 Media library
                                            </button>
                                            <button
                                                type="button"
                                                @click="removeGalleryImage(block, gIdx)"
                                                class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-300 font-bold px-1 text-xs cursor-pointer"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    </div>

                                    <input v-model="gItem.url" type="text" :aria-label="`Image ${gIdx + 1} URL`" placeholder="https://example.com/photo.jpg" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg font-mono text-[11px]" />
                                    <input v-model="gItem.caption" type="text" :aria-label="`Image ${gIdx + 1} caption / alt text`" placeholder="Caption / Alt Text" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs" />

                                    <img v-if="gItem.url" :src="gItem.url" class="w-full h-24 object-cover rounded-lg border border-slate-200 dark:border-slate-800 mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- 4. Notice / Callout Box Block -->
                        <div v-else-if="block.type === 'notice'" class="space-y-3 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label :for="`block-${bIdx}-style`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Callout Style</label>
                                    <select :id="`block-${bIdx}-style`" v-model="block.style" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="info">💡 Info Box (Sky Blue)</option>
                                        <option value="warning">⚠️ Warning Box (Amber Gold)</option>
                                        <option value="important">❗ Important Notice (Rose Red)</option>
                                        <option value="success">✅ Success Box (Emerald Green)</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-notice-title`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Box Header Title</label>
                                    <input :id="`block-${bIdx}-notice-title`" v-model="block.title" type="text" placeholder="e.g. Important Announcement" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold" />
                                </div>
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-notice-text`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Notice Body Message</label>
                                <textarea :id="`block-${bIdx}-notice-text`" v-model="block.text" rows="3" placeholder="Write callout announcement text here..." class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-xs"></textarea>
                            </div>
                        </div>

                        <!-- 5. Button Link Block -->
                        <div v-else-if="block.type === 'button'" class="space-y-3 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label :for="`block-${bIdx}-button-label`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Label</label>
                                    <input :id="`block-${bIdx}-button-label`" v-model="block.label" type="text" placeholder="e.g. Learn More →" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold" />
                                </div>

                                <div>
                                    <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link Target</span>
                                    <LinkField v-model="block.url" :pages="pages" :club="club" label="Button link" />
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-button-align`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Alignment</label>
                                    <select :id="`block-${bIdx}-button-align`" v-model="block.align" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="left">Left Aligned</option>
                                        <option value="center">Centered</option>
                                        <option value="right">Right Aligned</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 6a. YouTube Video Block -->
                        <div v-else-if="block.type === 'youtube'" class="space-y-3 text-xs">
                            <div>
                                <label :for="`block-${bIdx}-yt-url`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">YouTube Link</label>
                                <input :id="`block-${bIdx}-yt-url`" v-model="block.url" @input="onYoutubeUrlInput(block)" type="text" placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..." class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                <p v-if="!block.url" class="text-[10px] text-slate-400 mt-1">Paste any YouTube link: a normal video, a youtu.be short link or a Short.</p>
                                <p v-else-if="parseYouTubeUrl(block.url)" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold mt-1">✓ Video found</p>
                                <p v-else class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">✗ That isn't a YouTube video link, so nothing will show on the site.</p>
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-yt-title`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Name <span class="font-normal text-slate-400">(optional)</span></label>
                                <input :id="`block-${bIdx}-yt-title`" v-model="block.title" type="text" maxlength="200" placeholder="e.g. Installation Ceremony 2026" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold" />
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-yt-description`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Description <span class="font-normal text-slate-400">(optional, plain text)</span></label>
                                <textarea :id="`block-${bIdx}-yt-description`" v-model="block.description" rows="3" maxlength="2000" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-xs"></textarea>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div>
                                    <label :for="`block-${bIdx}-yt-layout`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Layout</label>
                                    <select :id="`block-${bIdx}-yt-layout`" v-model="block.layout" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="stacked">Video, then text</option>
                                        <option value="video_only">Video only</option>
                                        <option value="side_left">Video left, text right</option>
                                        <option value="side_right">Text left, video right</option>
                                    </select>
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-yt-aspect`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Shape</label>
                                    <select :id="`block-${bIdx}-yt-aspect`" v-model="block.aspect" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="16:9">Widescreen 16:9</option>
                                        <option value="4:3">Classic 4:3</option>
                                        <option value="21:9">Cinema 21:9</option>
                                        <option value="1:1">Square 1:1</option>
                                        <option value="9:16">Portrait 9:16</option>
                                    </select>
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-yt-align`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Text &amp; buttons</label>
                                    <select :id="`block-${bIdx}-yt-align`" v-model="block.text_align" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                                        <option value="left">Left aligned</option>
                                        <option value="center">Centered</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label :for="`block-${bIdx}-yt-cover`" class="block font-bold text-slate-700 dark:text-slate-200">Cover Image <span class="font-normal text-slate-400">(optional)</span></label>
                                    <div class="flex items-center gap-1.5">
                                        <button v-if="block.cover_url" type="button" @click="block.cover_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-xl border border-rose-200 dark:border-rose-800/60 cursor-pointer">
                                            🗑️ Clear
                                        </button>
                                        <button type="button" @click="openMediaLibrary('video_cover', block, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 cursor-pointer">
                                            📁 Media library
                                        </button>
                                    </div>
                                </div>
                                <input :id="`block-${bIdx}-yt-cover`" v-model="block.cover_url" type="text" placeholder="https://example.com/cover.jpg" class="w-full p-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                <p class="text-[10px] text-slate-400 mt-1">Without a cover, the YouTube thumbnail is used and loads from YouTube's image server when the page opens. A cover from your media library keeps visitors' details away from YouTube until they press play.</p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 items-end">
                                <div>
                                    <label :for="`block-${bIdx}-yt-start`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Start at</label>
                                    <input :id="`block-${bIdx}-yt-start`" :value="formatTimestamp(block.start)" @change="setYoutubeTime(block, 'start', $event)" type="text" placeholder="e.g. 1:30" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-yt-end`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Stop at</label>
                                    <input :id="`block-${bIdx}-yt-end`" :value="formatTimestamp(block.end)" @change="setYoutubeTime(block, 'end', $event)" type="text" placeholder="e.g. 4:05" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                </div>
                                <label class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer pb-2">
                                    <input type="checkbox" v-model="block.loop" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" /> Repeat (loop)
                                </label>
                                <label class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer pb-2">
                                    <input type="checkbox" v-model="block.captions" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" /> Show captions
                                </label>
                            </div>

                            <div class="space-y-3 p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                <label class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                                    <input type="checkbox" v-model="block.show_youtube_link" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" /> Show a "Watch on YouTube" link
                                </label>
                                <label class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                                    <input type="checkbox" v-model="block.button_enabled" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" /> Add a button
                                </label>
                                <div v-if="block.button_enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label :for="`block-${bIdx}-yt-button-label`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Label</label>
                                        <input :id="`block-${bIdx}-yt-button-label`" v-model="block.button_label" type="text" maxlength="100" placeholder="e.g. Book your place" class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-bold" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label :for="`block-${bIdx}-yt-button-url`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Link</label>
                                        <input :id="`block-${bIdx}-yt-button-url`" v-model="block.button_url" type="text" placeholder="https://..." class="w-full p-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
                                    </div>
                                    <label class="flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer sm:col-span-3">
                                        <input type="checkbox" v-model="block.button_new_tab" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" /> Open the button link in a new tab
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 6b. Newer blocks, each with its own editor component -->
                        <CtaBannerEditor v-else-if="block.type === 'cta_banner'" :block="block" :index="bIdx" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
                        <FaqEditor v-else-if="block.type === 'faq'" :block="block" :index="bIdx" />
                        <MapEditor v-else-if="block.type === 'map'" :block="block" :index="bIdx" :club="club" :club-addresses="clubAddresses" />
                        <DownloadsEditor v-else-if="block.type === 'downloads'" :block="block" :index="bIdx" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'documents')" />
                        <CalendarEditor v-else-if="block.type === 'calendar'" :block="block" :index="bIdx" />
                        <FeatureCardsEditor v-else-if="block.type === 'feature_cards'" :block="block" :index="bIdx" :pages="pages" :club="club" />
                        <SlideshowEditor v-else-if="block.type === 'slideshow'" :block="block" :index="bIdx" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
                        <StatsEditor v-else-if="block.type === 'stats'" :block="block" :index="bIdx" />
                        <MediaTextEditor v-else-if="block.type === 'media_text'" :block="block" :index="bIdx" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />
                        <QuoteMottoEditor v-else-if="block.type === 'quote_motto'" :block="block" :index="bIdx" />
                        <SectionHeadingEditor v-else-if="block.type === 'section_heading'" :block="block" :index="bIdx" />
                        <HeroEditor v-else-if="block.type === 'hero'" :block="block" :index="bIdx" :pages="pages" :club="club" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" />

                        <!-- 7. Dynamic News Feed & News List Block -->
                        <div v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-3 text-xs">
                            <div>
                                <label :for="`block-${bIdx}-news-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Section Heading</label>
                                <input :id="`block-${bIdx}-news-heading`" v-model="block.heading" placeholder="e.g. Latest Club News & Articles" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label :for="`block-${bIdx}-news-columns`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">News Layout & Columns</label>
                                    <select :id="`block-${bIdx}-news-columns`" v-model="block.columns" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="1">1 Column (Vertical List)</option>
                                        <option value="2">2 Columns Grid</option>
                                        <option value="3">3 Columns Grid (Default)</option>
                                        <option value="4">4 Columns Grid</option>
                                        <option value="masonry">Masonry Grid Layout</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-news-image-position`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Featured Image Position</label>
                                    <select :id="`block-${bIdx}-news-image-position`" v-model="block.image_position" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="above">Image Above Content (Top Banner)</option>
                                        <option value="below">Image Below Content (Bottom Banner)</option>
                                        <option value="left">Image on Left (Horizontal Layout)</option>
                                        <option value="right">Image on Right (Horizontal Layout)</option>
                                        <option value="alternate">Alternate Left & Right</option>
                                    </select>
                                </div>

                                <div>
                                    <label :for="`block-${bIdx}-news-limit`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Articles Per Page (Pagination)</label>
                                    <select :id="`block-${bIdx}-news-limit`" v-model="block.limit" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500">
                                        <option :value="3">3 Articles per page</option>
                                        <option :value="6">6 Articles per page</option>
                                        <option :value="9">9 Articles per page</option>
                                        <option :value="12">12 Articles per page</option>
                                        <option :value="24">24 Articles per page</option>
                                        <option :value="999">Show All Articles (No pagination)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/80 rounded-xl text-blue-900 dark:text-blue-200 text-xs">
                                ⚡ <strong>Dynamic Paginated News List:</strong> Renders published news items in a <strong>{{ String(block.columns) === 'masonry' ? 'Masonry Grid' : (block.columns || 3) + ' Column' }}</strong> with automatic Previous / Next page controls when articles exceed {{ block.limit == 999 ? 'all articles' : block.limit + ' per page' }}.
                            </div>
                        </div>

                        <!-- 8. Dynamic Events Calendar Block -->
                        <div v-else-if="block.type === 'events_calendar'" class="space-y-3 text-xs">
                            <div>
                                <label :for="`block-${bIdx}-events-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Section Heading</label>
                                <input :id="`block-${bIdx}-events-heading`" v-model="block.heading" placeholder="Section Heading" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                            </div>
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/80 rounded-xl text-emerald-900 dark:text-emerald-200 text-xs">
                                ⚡ <strong>Dynamic Events & Summons Feed:</strong> Automatically displays upcoming club events, dinners, and meetings.
                            </div>
                        </div>

                        <!-- 9. Dynamic Pricing Cards Block -->
                        <div v-else-if="block.type === 'pricing_cards'" class="space-y-3 text-xs">
                            <div>
                                <label :for="`block-${bIdx}-pricing-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Section Heading</label>
                                <input :id="`block-${bIdx}-pricing-heading`" v-model="block.heading" placeholder="Section Heading" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/80 rounded-xl text-blue-900 dark:text-blue-200 text-xs">
                                ⚡ <strong>Dynamic Membership Dues:</strong> Automatically renders active membership plans and pricing packages configured in Club Settings.
                            </div>
                        </div>

                        <!-- 10. Dynamic Donation Campaign Block -->
                        <div v-else-if="block.type === 'donation_campaign'" class="space-y-3 text-xs">
                            <div>
                                <label :for="`block-${bIdx}-donation-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Section Heading</label>
                                <input :id="`block-${bIdx}-donation-heading`" v-model="block.heading" placeholder="Section Heading" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                            </div>
                            <div class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200/80 dark:border-rose-800/80 rounded-xl text-rose-900 dark:text-rose-200 text-xs">
                                ⚡ <strong>Dynamic Fundraising Feed:</strong> Automatically renders active fundraising campaigns and progress bars.
                            </div>
                        </div>

                        <!-- 11. Contact Details Block -->
                        <div v-else-if="block.type === 'contact_details'" class="space-y-4 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label :for="`block-${bIdx}-cd-eyebrow`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Eyebrow Subtitle</label>
                                    <input :id="`block-${bIdx}-cd-eyebrow`" v-model="block.eyebrow" placeholder="e.g. CONTACT" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-cd-title`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Main Section Title</label>
                                    <input :id="`block-${bIdx}-cd-title`" v-model="block.title" placeholder="e.g. Get in Touch" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                                </div>
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-cd-description`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Introductory Description</label>
                                <textarea :id="`block-${bIdx}-cd-description`" v-model="block.description" rows="2" placeholder="Introductory paragraph..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-medium"></textarea>
                            </div>

                            <div class="p-3 bg-amber-50/70 dark:bg-amber-950/70 border border-amber-200/80 dark:border-amber-800/80 rounded-xl space-y-3">
                                <div class="font-bold text-amber-900 dark:text-amber-200 text-xs flex items-center gap-1.5">
                                    <span>📇 Contact Cards Details</span>
                                    <span class="text-[10px] text-amber-700/70 dark:text-amber-300/70 font-normal">(Initial defaults loaded from Website Settings)</span>
                                </div>

                                <!-- Email Card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label :for="`block-${bIdx}-cd-email-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Email Heading</label>
                                        <input :id="`block-${bIdx}-cd-email-heading`" v-model="block.email_heading" placeholder="Email" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label :for="`block-${bIdx}-cd-email`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Contact Email</label>
                                        <input :id="`block-${bIdx}-cd-email`" v-model="block.email" :placeholder="'Dynamic Default: ' + (settingsForm.contact_email || club.contact_email || 'Not configured')" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold" />
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 block">Leave blank to automatically use the Contact Email from Organization Settings.</span>
                                    </div>
                                </div>

                                <!-- Meeting Times Card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label :for="`block-${bIdx}-cd-times-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Times Heading</label>
                                        <input :id="`block-${bIdx}-cd-times-heading`" v-model="block.times_heading" placeholder="Meeting Times" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label :for="`block-${bIdx}-cd-times`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Meeting Times & Schedule</label>
                                        <textarea :id="`block-${bIdx}-cd-times`" v-model="block.times" rows="2" placeholder="e.g. 7:00 pm, 4th Thursday..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold"></textarea>
                                    </div>
                                </div>

                                <!-- Location Card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <div>
                                        <label :for="`block-${bIdx}-cd-location-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Location Heading</label>
                                        <input :id="`block-${bIdx}-cd-location-heading`" v-model="block.location_heading" placeholder="Location" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label :for="`block-${bIdx}-cd-location`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Full Address / Location</label>
                                        <textarea :id="`block-${bIdx}-cd-location`" v-model="block.location" rows="3" placeholder="e.g. Masonic Hall, Street, Town..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 12. Contact Form Block -->
                        <div v-else-if="block.type === 'contact_form'" class="space-y-4 text-xs">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label :for="`block-${bIdx}-cf-heading`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Section Heading</label>
                                    <input :id="`block-${bIdx}-cf-heading`" v-model="block.heading" placeholder="e.g. Send Us a Message" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-cf-button-text`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Submit Button Label</label>
                                    <input :id="`block-${bIdx}-cf-button-text`" v-model="block.button_text" placeholder="e.g. Send Message" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                                </div>
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-cf-subtitle`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Subtitle / Instructions</label>
                                <textarea :id="`block-${bIdx}-cf-subtitle`" v-model="block.subtitle" rows="2" placeholder="Subheading or instructions..." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-medium"></textarea>
                            </div>

                            <div class="p-3.5 bg-blue-50/70 dark:bg-blue-950/70 border border-blue-200/80 dark:border-blue-800/80 rounded-xl space-y-3">
                                <div class="font-bold text-blue-900 dark:text-blue-200 text-xs flex items-center justify-between">
                                    <span>📬 Email Routing & Notification Settings</span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label :for="`block-${bIdx}-cf-recipient`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Secretary / Recipient Email</label>
                                        <div class="space-y-1.5">
                                            <input
                                                :id="`block-${bIdx}-cf-recipient`"
                                                v-model="block.recipient_email"
                                                type="email"
                                                :placeholder="'Dynamic Default: ' + (settingsForm.contact_email || club.contact_email || 'secretary@' + club.slug + '.org.uk')"
                                                class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold text-xs"
                                            />
                                            <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                                                <button 
                                                    type="button" 
                                                    @click="block.recipient_email = ''"
                                                    class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold border border-blue-200 dark:border-blue-800/60 transition-colors cursor-pointer"
                                                >
                                                    ⚡ Use organization default (dynamic)
                                                </button>
                                                <button 
                                                    v-if="settingsForm.contact_email || club.contact_email"
                                                    type="button" 
                                                    @click="block.recipient_email = settingsForm.contact_email || club.contact_email"
                                                    class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold border border-slate-200 dark:border-slate-800 transition-colors cursor-pointer"
                                                >
                                                    ✉️ Fill: {{ settingsForm.contact_email || club.contact_email }}
                                                </button>
                                            </div>
                                            <span class="text-[10px] text-slate-500 dark:text-slate-400 block leading-tight">
                                                Form submissions route here. Leave blank to automatically use the Contact Email from Organization Settings ({{ settingsForm.contact_email || club.contact_email || 'Not configured' }}).
                                            </span>
                                        </div>
                                    </div>

                                    <div>
                                        <label :for="`block-${bIdx}-cf-cc`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">CC Email Addresses (Comma-separated)</label>
                                        <input :id="`block-${bIdx}-cf-cc`" v-model="block.cc_emails" placeholder="e.g. treasurer@lodge.org, assistant@lodge.org" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2 text-slate-900 dark:text-white font-semibold text-xs" />
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 block">Copies of form submissions will be sent here.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                                <div class="font-bold text-slate-800 dark:text-slate-100 text-xs">
                                    ⚙️ Mandatory Field Rules (Admin Configuration)
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <label class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer">
                                        <input type="checkbox" v-model="block.name_required" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" />
                                        Name Required
                                    </label>
                                    <label class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer">
                                        <input type="checkbox" v-model="block.email_required" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" />
                                        Email Required
                                    </label>
                                    <label class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer">
                                        <input type="checkbox" v-model="block.phone_required" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" />
                                        Phone Required
                                    </label>
                                    <label class="flex items-center gap-2 text-slate-700 dark:text-slate-200 font-semibold text-xs cursor-pointer">
                                        <input type="checkbox" v-model="block.message_required" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 accent-blue-600" />
                                        Message Required
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label :for="`block-${bIdx}-cf-success-message`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Success Message Displayed on Submission</label>
                                <input :id="`block-${bIdx}-cf-success-message`" v-model="block.success_message" placeholder="e.g. Thank you! Your message has been sent successfully." class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                            </div>
                        </div>

                        <SectionStyleEditor v-if="block.type !== 'hero'" :block="block" :index="bIdx" :can-apply-to-others="form.blocks.length > 1" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" @apply-all="applySectionToAll(block)" />
                    </div>
                </div>

                <!-- Insert Divider Below Block -->
                <div class="relative py-1 flex items-center justify-center insert-menu-container">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 transition-colors"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <button
                            type="button"
                            @click.stop="toggleInsertMenu(bIdx + 1, $event)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 rounded-full text-xs font-bold shadow-sm transition-all cursor-pointer hover:scale-105"
                        >
                            <span class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 flex items-center justify-center text-xs font-black">+</span>
                            <span>Insert block here</span>
                        </button>

                        <!-- Insert Menu Dropdown Popover -->
                        <div
                            v-if="activeInsertIndex === bIdx + 1"
                            :class="[
                                'absolute z-40 w-[1100px] max-w-[calc(100vw-3.5rem)] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                            ]"
                            :style="{ translate: `calc(-50% + ${insertMenuShift}px) 0` }"
                        >
                            <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                <span>Insert Element Here</span>
                                <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs cursor-pointer p-1">✕</button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-1">
                                <div v-for="(col, cIdx) in blockColumns" :key="cIdx" class="space-y-1.5">
                                    <div class="px-2 py-1 text-[10px] font-black uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                        {{ col.title }}
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="item in col.items"
                                            :key="item.type"
                                            type="button"
                                            @click="addBlock(item.type, bIdx + 1)"
                                            class="w-full flex items-start gap-2.5 p-2 text-left text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-blue-200 dark:hover:border-blue-800/60 rounded-xl transition-all cursor-pointer group"
                                        >
                                            <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-black shrink-0 transition-transform group-hover:scale-110" :class="[item.bg, item.color]">
                                                {{ item.icon }}
                                            </span>
                                            <div class="min-w-0">
                                                <span class="block font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">{{ item.label }}</span>
                                                <span class="text-[10px] text-slate-400 font-normal leading-tight block line-clamp-2">{{ item.desc }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
            <!-- Room below the last block so a newly added block can always be scrolled up to the top of the view -->
            <div v-if="form.blocks.length" class="h-[45vh]" aria-hidden="true"></div>

            <div v-if="!form.blocks.length" class="text-center py-10 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 space-y-3">
                <span class="text-3xl">🧩</span>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400">No content elements added yet.</p>
                <button type="button" @click="addBlock('text')" class="py-2 px-4 rounded-xl bg-blue-600 text-white font-bold text-xs shadow-md">
                    ➕ Add first text block
                </button>
            </div>
        </div>

        <!-- Page Action Footer -->
        <div class="flex items-center justify-between pt-4">
            <button
                v-if="form.id && !isDefaultPage(activePage)"
                @click="triggerDeleteModal(activePage)"
                type="button"
                class="py-2.5 px-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-bold text-xs border border-rose-200 dark:border-rose-800/60 transition-colors cursor-pointer"
            >
                🗑️ Delete custom page
            </button>
            <span v-else class="text-xs text-slate-400 font-semibold">
                ℹ️ Default system pages cannot be deleted (only published/unpublished).
            </span>

            <button
                @click="submitForm"
                :disabled="form.processing"
                :class="[
                    'px-6 py-3 font-bold text-xs rounded-xl shadow-md transition-all duration-300 flex items-center gap-2 cursor-pointer',
                    form.processing ? 'bg-blue-500 text-white cursor-wait opacity-80' :
                    isSavedSuccess ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30 scale-105 ring-2 ring-emerald-400/50' :
                    'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20'
                ]"
            >
                <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved successfully!' : '💾 Save & publish page') }}</span>
            </button>
        </div>
    </div>
</template>
