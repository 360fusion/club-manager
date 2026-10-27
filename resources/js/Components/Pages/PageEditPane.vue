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
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
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

// The kit's field look, for the fields its components cannot take: number-valued selects, inputs with their own
// handlers, and labels that share a row with buttons.
const field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500';
const fieldLabel = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
const smallSelect = 'rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
const checkbox = 'h-4 w-4 rounded border-slate-300 accent-blue-600 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700';
const iconButton = 'inline-flex h-7 w-7 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-white hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
const linkButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 whitespace-nowrap';
const tooltip = 'pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150';

// The palette in Support/pageBlockCatalog.js gives each element an emoji; the editor shows an interface icon instead.
const BLOCK_ICONS = {
    text: 'document', rich_text: 'document', image: 'photo', images: 'photo', notice: 'info', button: 'link', youtube: 'eye',
    downloads: 'download', faq: 'info', map: 'globe', cta_banner: 'star', calendar: 'calendar', hero: 'sparkles',
    news_feed: 'clipboard', news_list: 'clipboard', events_calendar: 'calendar', pricing_cards: 'cash', donation_campaign: 'heart',
    contact_details: 'user', contact_form: 'mail', slideshow: 'photo', section_heading: 'tag', feature_cards: 'award',
    stats: 'chart', media_text: 'photo', quote_motto: 'pencil',
};

const HEADER_STYLES = [
    { value: 'full', label: 'Full header (menu and buttons)' },
    { value: 'logo_only', label: 'Logo only' },
    { value: 'hidden', label: 'No header at all' },
];
const TEXT_ALIGNS = [
    { value: 'left', label: 'Left aligned' },
    { value: 'center', label: 'Centred' },
    { value: 'right', label: 'Right aligned' },
];
const IMAGE_POSITIONS = [...TEXT_ALIGNS, { value: 'full', label: 'Full-width banner' }];
const IMAGE_SIZES = [
    { value: 'small', label: 'Small (25% width)' },
    { value: 'medium', label: 'Medium (50% width)' },
    { value: 'large', label: 'Large (75% width)' },
    { value: 'full', label: 'Full width (100%)' },
];
const NOTICE_STYLES = [
    { value: 'info', label: 'Information (blue)' },
    { value: 'warning', label: 'Warning (amber)' },
    { value: 'important', label: 'Important (red)' },
    { value: 'success', label: 'Success (green)' },
];
const VIDEO_LAYOUTS = [
    { value: 'stacked', label: 'Video, then text' },
    { value: 'video_only', label: 'Video only' },
    { value: 'side_left', label: 'Video left, text right' },
    { value: 'side_right', label: 'Text left, video right' },
];
const VIDEO_SHAPES = [
    { value: '16:9', label: 'Widescreen 16:9' },
    { value: '4:3', label: 'Classic 4:3' },
    { value: '21:9', label: 'Cinema 21:9' },
    { value: '1:1', label: 'Square 1:1' },
    { value: '9:16', label: 'Portrait 9:16' },
];
const VIDEO_TEXT_ALIGNS = TEXT_ALIGNS.slice(0, 2);
const NEWS_COLUMNS = [
    { value: '1', label: '1 column (a list)' },
    { value: '2', label: '2-column grid' },
    { value: '3', label: '3-column grid (default)' },
    { value: '4', label: '4-column grid' },
    { value: 'masonry', label: 'Masonry grid' },
];
const NEWS_IMAGE_POSITIONS = [
    { value: 'above', label: 'Above the text' },
    { value: 'below', label: 'Below the text' },
    { value: 'left', label: 'On the left' },
    { value: 'right', label: 'On the right' },
    { value: 'alternate', label: 'Alternating left and right' },
];
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
        <Card padding="lg" class="space-y-6">
            <div class="border-b border-slate-100 dark:border-slate-800 pb-5 space-y-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Page details and navigation</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">The page title, and where the page appears in the site's menus.</p>
                </div>

                <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                    <!-- Saving: the first puts your edits on the live page, the second keeps them aside -->
                    <div role="group" aria-label="Save">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Save</p>
                        <div class="flex items-center gap-1.5">
                            <div class="relative group">
                                <Button size="sm" :loading="form.processing" aria-describedby="tip-save" @click="submitForm">
                                    <UiIcon v-if="!form.processing" name="check" :size="14" />
                                    <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? 'Saved' : 'Save page') }}</span>
                                </Button>
                                <span id="tip-save" role="tooltip" :class="[tooltip, 'left-0']">{{ hasDraft ? 'Saves the page as it looks here and makes it live straight away, replacing the draft. To publish the draft as it was saved, use Publish draft.' : 'Saves your changes and makes them live straight away.' }}</span>
                            </div>
                            <div v-if="canDraft" class="relative group">
                                <Button variant="secondary" size="sm" :disabled="form.processing" aria-describedby="tip-draft" class="whitespace-nowrap" @click="saveDraft">Save draft</Button>
                                <span id="tip-draft" role="tooltip" :class="[tooltip, 'left-0']">Keeps your edits aside. Visitors still see the live page until you publish the draft.</span>
                            </div>
                            <div v-if="canDraft" class="relative group">
                                <Button variant="secondary" size="sm" :disabled="!hasDraft || form.processing" aria-describedby="tip-publish-draft" class="whitespace-nowrap" @click="publishDraft">Publish draft</Button>
                                <span id="tip-publish-draft" role="tooltip" :class="[tooltip, 'left-0']">{{ hasDraft ? 'Makes your saved draft live for visitors.' : 'There is no draft to publish. Use Save as draft first.' }}</span>
                            </div>
                            <div v-if="form.id" class="relative group">
                                <Button variant="secondary" size="sm" aria-describedby="tip-history" aria-label="History" @click="showHistory = true">
                                    <UiIcon name="clock" :size="14" />
                                </Button>
                                <span id="tip-history" role="tooltip" :class="[tooltip, 'left-0']">History: earlier saves of this page. Restore one into the editor, then save to keep it.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Previewing: the first is what visitors see now, the second is a secret link that includes drafts -->
                    <div v-if="form.id" role="group" aria-label="Preview">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Preview</p>
                        <div class="flex items-center gap-1.5">
                            <div class="relative group">
                                <a v-if="form.slug && activePage?.lifecycle_status === 'live'" :href="form.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${form.slug}`" target="_blank" rel="noopener" aria-describedby="tip-live" :class="linkButton">
                                    <span>View live</span>
                                    <UiIcon name="external-link" :size="14" />
                                </a>
                                <Button v-else variant="secondary" size="sm" disabled aria-describedby="tip-live" class="whitespace-nowrap">
                                    <span>View live</span>
                                    <UiIcon name="external-link" :size="14" />
                                </Button>
                                <span id="tip-live" role="tooltip" :class="[tooltip, 'right-0']">{{ activePage?.lifecycle_status === 'live' ? 'View live page: opens the page as visitors see it right now, in a new tab.' : "View live page: this page is not live yet, so visitors can't see it. Use Draft preview instead." }}</span>
                            </div>
                            <div class="relative group">
                                <Button variant="secondary" size="sm" :aria-expanded="previewOpen" aria-describedby="tip-private" class="whitespace-nowrap" @click="openPreviewPanel">Draft preview</Button>
                                <span id="tip-private" role="tooltip" :class="[tooltip, 'right-0']">Makes a secret link that shows this page with your draft, even before it is live. Share it with someone to get their feedback.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Private preview link -->
            <div v-if="previewOpen" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 text-sm">
                <p class="font-medium text-slate-900 dark:text-white">Draft preview link</p>
                <p class="text-xs text-slate-600 dark:text-slate-300">Anyone with this link can see this page{{ hasDraft ? ', including its unsaved draft,' : '' }} even if it is not live yet{{ form.is_members_only ? ', and even though it is for members only' : '' }}. Only share it with people you trust.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input :value="previewUrl" readonly aria-label="Draft preview link" @focus="$event.target.select()" :class="[field, 'flex-1 min-w-[220px] font-mono text-xs']" />
                    <Button variant="secondary" size="sm" :disabled="!previewUrl" @click="copyPreviewLink">
                        <UiIcon :name="previewCopied ? 'check' : 'copy'" :size="14" />
                        <span>{{ previewCopied ? 'Copied' : 'Copy link' }}</span>
                    </Button>
                    <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noopener" :class="linkButton">
                        <span>Open</span>
                        <UiIcon name="external-link" :size="14" />
                    </a>
                    <Button variant="secondary" size="sm" :disabled="!previewUrl" @click="renewPreviewLink">New link</Button>
                </div>
                <p v-if="previewError" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ previewError }}</p>
            </div>

            <Input v-model="form.title" label="Page title" placeholder="For example, About our lodge" />

            <div class="flex flex-wrap items-center gap-6 pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input type="checkbox" v-model="form.is_published" :disabled="form.is_homepage || form.slug === 'home'" :class="checkbox" />
                    <span>Published</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input type="checkbox" v-model="form.show_in_navigation" :class="checkbox" />
                    <span>Show in the top menu</span>
                </label>

                <label v-if="!form.is_homepage" class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input type="checkbox" v-model="form.is_members_only" :class="checkbox" />
                    <UiIcon name="lock" :size="14" class="text-slate-500 dark:text-slate-400" />
                    <span>Members only</span>
                </label>

                <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none" title="A separate menu of links at the bottom of every page">
                    <input type="checkbox" v-model="form.show_in_footer" :class="checkbox" />
                    <span>Show in the footer menu</span>
                </label>
            </div>

            <!-- Top menu style and schedule -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <Select v-model="form.header_style" label="Top menu on this page" :options="HEADER_STYLES" hint="Handy for a landing page." />
                <Input v-if="!form.is_homepage" v-model="publishAtLocal" type="datetime-local" label="Go live on (optional)" />
                <div v-if="!form.is_homepage">
                    <Input v-model="unpublishAtLocal" type="datetime-local" label="Come down on (optional)" :error="form.errors.unpublish_at" />
                    <p v-if="form.publish_at && form.unpublish_at && form.unpublish_at <= form.publish_at" class="text-xs text-rose-600 dark:text-rose-400 mt-1">This must be after the go-live time.</p>
                </div>
                <p v-if="!form.is_homepage" class="sm:col-span-3 text-xs text-slate-500 dark:text-slate-400 -mt-2">Times are in your own timezone. The page must also be ticked as Published for these dates to apply.<template v-if="lifecycleText(activePage)"> <strong class="font-medium text-slate-700 dark:text-slate-300">{{ lifecycleText(activePage) }}.</strong></template></p>
            </div>
        </Card>

        <!-- SEO and web address (collapsible) -->
        <Card padding="none">
            <button
                type="button"
                @click="seoOpen = !seoOpen"
                class="w-full flex items-center gap-3 p-6 sm:px-8 text-left cursor-pointer rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                :aria-expanded="seoOpen"
                aria-controls="page-seo-panel"
            >
                <UiIcon name="chevron-down" class="text-slate-500 dark:text-slate-400 transition-transform" :class="seoOpen ? '' : '-rotate-90'" />
                <span class="min-w-0">
                    <span class="block text-base font-semibold text-slate-900 dark:text-white">Search engine optimisation (this page)</span>
                    <span v-if="seoOpen" class="block text-sm text-slate-500 dark:text-slate-400 mt-0.5">The page's web address, and what search engines show for it. Overrides the site-wide SEO defaults; leave the title and description blank to use them.</span>
                    <span v-else class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-mono truncate">/site/{{ club.slug }}/{{ form.is_homepage ? '' : form.slug }}</span>
                </span>
            </button>

            <div v-show="seoOpen" id="page-seo-panel" class="px-6 sm:px-8 pb-6 sm:pb-8 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 sm:items-start gap-4">
                    <Input v-model="form.slug" label="Web address (slug)" placeholder="about" :hint="`/site/${club.slug}/${form.slug || ''}`" />
                    <Input v-model="form.meta_title" label="SEO title" maxlength="255" :placeholder="form.title || 'Page title'" />
                </div>
                <Textarea
                    v-model="form.meta_description"
                    label="Meta description"
                    :rows="3"
                    maxlength="500"
                    placeholder="Site-wide default description"
                    :hint="`${(form.meta_description || '').length}/500. Search engines usually show around 155 characters.`"
                />

                <!-- How it may look in a search engine -->
                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40 p-4 text-left" aria-label="Search result preview">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Search result preview</p>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ searchPreview.url }}</p>
                    <p class="text-base font-semibold text-blue-700 dark:text-blue-400 truncate">{{ searchPreview.title }}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">{{ searchPreview.description }}</p>
                    <p v-if="searchPreview.title.length > 60" class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400">The title is {{ searchPreview.title.length }} characters; search engines usually cut it off after about 60.</p>
                    <p v-if="(form.meta_description || '').length > 160" class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">The description is long; search engines usually cut it off after about 160 characters.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <label for="page-share-image" :class="fieldLabel">Share image</label>
                            <div class="flex items-center gap-1.5">
                                <Button v-if="form.share_image" variant="ghost" size="sm" @click="form.share_image = ''">
                                    <UiIcon name="trash" :size="14" />
                                    <span>Clear</span>
                                </Button>
                                <Button variant="secondary" size="sm" @click="openMediaLibrary('page_share_image', null, 'pages')">
                                    <UiIcon name="folder" :size="14" />
                                    <span>Media library</span>
                                </Button>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <input id="page-share-image" v-model="form.share_image" type="text" :class="[field, 'font-mono text-xs']" placeholder="Site-wide share image is used" />
                            <img v-if="form.share_image" :src="form.share_image" alt="" class="w-16 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">The picture shown when this page is shared on Facebook, WhatsApp or Messages. About 1200 × 630 works best.</p>
                    </div>
                    <div>
                        <span :class="[fieldLabel, 'mb-1.5']">Search engines</span>
                        <label :class="['flex items-start gap-2.5 select-none text-sm', form.is_homepage || form.slug === 'home' ? 'opacity-50' : 'cursor-pointer']">
                            <input type="checkbox" v-model="form.noindex" :disabled="form.is_homepage || form.slug === 'home'" :class="[checkbox, 'mt-0.5']" />
                            <span>
                                <span class="font-medium text-slate-700 dark:text-slate-300">Hide this page from search engines</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">People with the link can still open it. It is also left out of the sitemap. The home page always stays visible.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </Card>

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
                <div v-if="bIdx === 0" class="relative py-1 flex items-center justify-center insert-menu-container">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <button
                            type="button"
                            @click.stop="toggleInsertMenu(0, $event)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 rounded-full text-xs font-medium shadow-sm transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <UiIcon name="plus" :size="14" class="text-blue-600 dark:text-blue-400" />
                            <span>Insert an element at the top</span>
                        </button>

                        <!-- Insert menu -->
                        <div
                            v-if="activeInsertIndex === 0"
                            :class="[
                                'absolute z-40 w-[1100px] max-w-[calc(100vw-3.5rem)] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                            ]"
                            :style="{ translate: `calc(-50% + ${insertMenuShift}px) 0` }"
                        >
                            <div class="px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                <span>Insert an element here</span>
                                <button type="button" aria-label="Close" @click="activeInsertIndex = null" :class="iconButton"><UiIcon name="x" /></button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-1">
                                <div v-for="(col, cIdx) in blockColumns" :key="cIdx" class="space-y-1.5">
                                    <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                        {{ col.title }}
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="item in col.items"
                                            :key="item.type"
                                            type="button"
                                            @click="addBlock(item.type, 0)"
                                            class="w-full flex items-start gap-2.5 p-2 text-left text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-blue-200 dark:hover:border-blue-800/60 rounded-xl transition-colors cursor-pointer group"
                                        >
                                            <span class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-slate-100 text-blue-600 dark:bg-slate-800 dark:text-blue-400">
                                                <UiIcon :name="BLOCK_ICONS[item.type]" />
                                            </span>
                                            <div class="min-w-0">
                                                <span class="block font-medium text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">{{ item.label }}</span>
                                                <span class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight block line-clamp-2">{{ item.desc }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Element card -->
                <div :id="blockCardId(block, bIdx)" :class="['scroll-mt-24 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden transition-shadow hover:shadow-md', newBlockId === block.id ? 'ring-2 ring-blue-500 ring-offset-2 ring-offset-transparent' : '']">

                    <!-- Element header and controls -->
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-slate-100 dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 text-xs font-medium text-slate-800 dark:text-slate-100">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="toggleCollapsed(block)"
                                :class="iconButton"
                                :aria-expanded="!isCollapsed(block)"
                                :title="isCollapsed(block) ? 'Expand to edit this element' : 'Compact this element'"
                                :aria-label="isCollapsed(block) ? 'Expand element' : 'Compact element'"
                            >
                                <UiIcon name="chevron-down" :size="14" class="transition-transform" :class="isCollapsed(block) ? '-rotate-90' : ''" />
                            </button>
                            <span class="w-5 h-5 rounded bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[11px] font-semibold border border-slate-200 dark:border-slate-700">
                                {{ bIdx + 1 }}
                            </span>

                            <!-- Element type -->
                            <Badge>
                                <UiIcon :name="BLOCK_ICONS[block.type]" :size="14" />
                                <template v-if="block.type === 'text' || block.type === 'rich_text'">Text block</template>
                                <template v-else-if="block.type === 'image'">Single image</template>
                                <template v-else-if="block.type === 'images'">Image gallery ({{ block.columns || 3 }} columns)</template>
                                <template v-else-if="block.type === 'notice'">Callout box</template>
                                <template v-else-if="block.type === 'button'">Button link</template>
                                <template v-else-if="block.type === 'youtube'">YouTube video</template>
                                <template v-else-if="block.type === 'downloads'">Downloads</template>
                                <template v-else-if="block.type === 'faq'">FAQ</template>
                                <template v-else-if="block.type === 'map'">Map</template>
                                <template v-else-if="block.type === 'cta_banner'">Call-to-action banner</template>
                                <template v-else-if="block.type === 'calendar'">Events calendar</template>
                                <template v-else-if="block.type === 'hero'">Hero banner</template>
                                <template v-else-if="block.type === 'news_feed' || block.type === 'news_list'">News list ({{ String(block.columns) === 'masonry' ? 'masonry' : (block.columns || 3) + ' columns' }})</template>
                                <template v-else-if="block.type === 'events_calendar'">Events and summons</template>
                                <template v-else-if="block.type === 'pricing_cards'">Membership dues</template>
                                <template v-else-if="block.type === 'donation_campaign'">Donation campaign</template>
                                <template v-else-if="block.type === 'contact_details'">Contact cards</template>
                                <template v-else-if="block.type === 'contact_form'">Contact form</template>
                                <template v-else-if="block.type === 'slideshow'">Slideshow ({{ (block.slides || []).filter((slide) => slide.image_url).length }} photos)</template>
                                <template v-else-if="block.type === 'section_heading'">Section heading</template>
                                <template v-else-if="block.type === 'feature_cards'">Icon cards ({{ block.columns || 3 }} columns)</template>
                                <template v-else-if="block.type === 'stats'">Stats row</template>
                                <template v-else-if="block.type === 'media_text'">Image and text</template>
                                <template v-else-if="block.type === 'quote_motto'">Motto</template>
                            </Badge>
                        </div>

                        <!-- Controls: show/hide, width, copy, save, move, duplicate, delete -->
                        <div class="flex flex-wrap items-center gap-1">
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
                                    :class="smallSelect"
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
                                    :class="smallSelect"
                                    title="Where a narrower element sits"
                                    aria-label="Element position"
                                >
                                    <option value="center">Centred</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </template>
                            <select
                                v-if="copyTargets.length"
                                @change="copyBlockTo(block, $event)"
                                :class="[smallSelect, 'max-w-[7.5rem]']"
                                title="Add a copy of this element to the end of another page"
                                aria-label="Copy this element to another page"
                            >
                                <option value="">Copy to…</option>
                                <option v-for="target in copyTargets" :key="target.id" :value="target.id">{{ target.title }}</option>
                            </select>
                            <Button
                                :variant="isDirty && !isSavedSuccess ? 'primary' : 'secondary'"
                                size="sm"
                                class="mr-1"
                                :disabled="form.processing"
                                title="Save this page"
                                aria-label="Save this page"
                                @click="submitForm()"
                            >
                                <UiIcon :name="isSavedSuccess ? 'check' : 'save'" :size="14" />
                                <span>{{ form.processing ? 'Saving…' : isSavedSuccess ? 'Saved' : 'Save' }}</span>
                            </Button>
                            <button
                                type="button"
                                @click="moveBlockUp(bIdx)"
                                :disabled="bIdx === 0"
                                :class="iconButton"
                                title="Move up"
                                aria-label="Move block up"
                            >
                                <UiIcon name="chevron-down" class="rotate-180" />
                            </button>
                            <button
                                type="button"
                                @click="moveBlockDown(bIdx)"
                                :disabled="bIdx === form.blocks.length - 1"
                                :class="iconButton"
                                title="Move down"
                                aria-label="Move block down"
                            >
                                <UiIcon name="chevron-down" />
                            </button>
                            <button
                                type="button"
                                @click="duplicateBlock(bIdx)"
                                :class="iconButton"
                                title="Duplicate element"
                                aria-label="Duplicate block"
                            >
                                <UiIcon name="copy" />
                            </button>
                            <button
                                type="button"
                                @click="blockToDelete = bIdx"
                                :class="[iconButton, 'hover:!text-rose-600 dark:hover:!text-rose-400']"
                                title="Delete element"
                                aria-label="Delete block"
                            >
                                <UiIcon name="trash" />
                            </button>
                        </div>
                    </div>

                    <!-- Compact view: read-only summary -->
                    <div v-if="isCollapsed(block)" :class="['p-4', block.hidden ? 'opacity-50' : '']">
                        <BlockSummary :block="block" />
                    </div>

                    <!-- Element editors -->
                    <div v-else class="p-4 space-y-3">

                        <!-- 1. Text / rich text -->
                        <div v-if="block.type === 'text' || block.type === 'rich_text'" class="space-y-3">
                            <Input v-model="block.heading" label="Section heading (optional)" placeholder="For example, our history and mission" />
                            <div>
                                <label :id="`block-${bIdx}-content-label`" :class="[fieldLabel, 'mb-1.5']">Text</label>
                                <RichTextEditor v-model="block.content" placeholder="Write formatted text content here..." :aria-labelledby="`block-${bIdx}-content-label`" />
                            </div>
                            <Select v-model="block.text_align" label="Text alignment" :options="TEXT_ALIGNS" class="max-w-[12rem]" />
                        </div>

                        <!-- 2. Single image -->
                        <div v-else-if="block.type === 'image'" class="space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                                        <label :for="`block-${bIdx}-image-url`" :class="fieldLabel">Image URL</label>
                                        <div class="flex items-center gap-1.5">
                                            <Button v-if="block.url" variant="ghost" size="sm" @click="removeBlockImage(block)">
                                                <UiIcon name="trash" :size="14" />
                                                <span>Clear</span>
                                            </Button>
                                            <Button variant="secondary" size="sm" @click="openMediaLibrary('block_image', block, 'pages')">
                                                <UiIcon name="folder" :size="14" />
                                                <span>Media library</span>
                                            </Button>
                                        </div>
                                    </div>
                                    <input :id="`block-${bIdx}-image-url`" v-model="block.url" type="text" placeholder="https://example.com/photo.jpg" :class="[field, 'font-mono text-xs']" />
                                </div>

                                <Input v-model="block.caption" label="Caption and alt text" placeholder="For example, the annual dinner at the lodge" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                <Select v-model="block.position" label="Image position" :options="IMAGE_POSITIONS" />
                                <Select v-model="block.size" label="Image size" :options="IMAGE_SIZES" />
                            </div>

                            <div v-if="block.url" class="pt-2">
                                <span class="text-xs font-medium text-slate-500 dark:text-slate-400 block mb-1">Layout preview</span>
                                <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full']">
                                    <div :class="[
                                        'rounded-xl overflow-hidden border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-1',
                                        block.size === 'small' ? 'w-1/4' : block.size === 'medium' ? 'w-1/2' : block.size === 'large' ? 'w-3/4' : 'w-full'
                                    ]">
                                        <img :src="block.url" alt="" class="w-full h-auto max-h-48 object-cover rounded-lg" />
                                        <p v-if="block.caption" class="text-[11px] text-center text-slate-500 dark:text-slate-400 italic mt-1">{{ block.caption }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Image gallery -->
                        <div v-else-if="block.type === 'images'" class="space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                <div class="flex items-center gap-2">
                                    <label :for="`block-${bIdx}-columns`" :class="fieldLabel">Columns</label>
                                    <select :id="`block-${bIdx}-columns`" v-model="block.columns" :class="[field, 'w-auto']">
                                        <option :value="2">2 columns</option>
                                        <option :value="3">3 columns</option>
                                        <option :value="4">4 columns</option>
                                    </select>
                                </div>

                                <Button variant="secondary" size="sm" @click="addGalleryImage(block)">
                                    <UiIcon name="plus" :size="14" />
                                    <span>Add an image</span>
                                </Button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="(gItem, gIdx) in block.items" :key="gItem.id || gIdx" class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 relative">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Image {{ gIdx + 1 }}</span>
                                        <div class="flex items-center gap-1">
                                            <Button variant="secondary" size="sm" @click="openMediaLibrary('gallery_image', gItem, 'pages')">
                                                <UiIcon name="folder" :size="14" />
                                                <span>Media library</span>
                                            </Button>
                                            <Button variant="ghost" size="sm" :aria-label="`Remove image ${gIdx + 1}`" @click="removeGalleryImage(block, gIdx)">
                                                <UiIcon name="x" :size="14" />
                                            </Button>
                                        </div>
                                    </div>

                                    <Input v-model="gItem.url" :aria-label="`Image ${gIdx + 1} URL`" placeholder="https://example.com/photo.jpg" />
                                    <Input v-model="gItem.caption" :aria-label="`Image ${gIdx + 1} caption / alt text`" placeholder="Caption and alt text" />

                                    <img v-if="gItem.url" :src="gItem.url" alt="" class="w-full h-24 object-cover rounded-lg border border-slate-200 dark:border-slate-800 mt-1" />
                                </div>
                            </div>
                        </div>

                        <!-- 4. Notice / callout box -->
                        <div v-else-if="block.type === 'notice'" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Select v-model="block.style" label="Callout style" :options="NOTICE_STYLES" />
                                <Input v-model="block.title" label="Box title" placeholder="For example, an important announcement" />
                            </div>

                            <Textarea v-model="block.text" label="Notice text" :rows="3" placeholder="Write callout announcement text here..." />
                        </div>

                        <!-- 5. Button link -->
                        <div v-else-if="block.type === 'button'" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Input v-model="block.label" label="Button label" placeholder="For example, Learn more" />

                                <div>
                                    <span :class="[fieldLabel, 'mb-1.5']">Link target</span>
                                    <LinkField v-model="block.url" :pages="pages" :club="club" label="Button link" />
                                </div>

                                <Select v-model="block.align" label="Button alignment" :options="TEXT_ALIGNS" />
                            </div>
                        </div>

                        <!-- 6a. YouTube video -->
                        <div v-else-if="block.type === 'youtube'" class="space-y-3">
                            <div>
                                <label :for="`block-${bIdx}-yt-url`" :class="[fieldLabel, 'mb-1.5']">YouTube link</label>
                                <input :id="`block-${bIdx}-yt-url`" v-model="block.url" @input="onYoutubeUrlInput(block)" type="text" placeholder="https://www.youtube.com/watch?v=... or https://youtu.be/..." :class="[field, 'font-mono text-xs']" />
                                <p v-if="!block.url" class="text-xs text-slate-500 dark:text-slate-400 mt-1">Paste any YouTube link: a normal video, a youtu.be short link or a Short.</p>
                                <p v-else-if="parseYouTubeUrl(block.url)" class="flex items-center gap-1 text-xs text-emerald-700 dark:text-emerald-400 font-medium mt-1"><UiIcon name="check" :size="14" />Video found</p>
                                <p v-else class="flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400 font-medium mt-1"><UiIcon name="warning" :size="14" />That isn't a YouTube video link, so nothing will show on the site.</p>
                            </div>

                            <Input v-model="block.title" label="Name (optional)" maxlength="200" placeholder="For example, Installation ceremony 2026" />
                            <Textarea v-model="block.description" label="Description (optional, plain text)" :rows="3" maxlength="2000" />

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                <Select v-model="block.layout" label="Layout" :options="VIDEO_LAYOUTS" />
                                <Select v-model="block.aspect" label="Shape" :options="VIDEO_SHAPES" />
                                <Select v-model="block.text_align" label="Text and buttons" :options="VIDEO_TEXT_ALIGNS" />
                            </div>

                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                                    <label :for="`block-${bIdx}-yt-cover`" :class="fieldLabel">Cover image (optional)</label>
                                    <div class="flex items-center gap-1.5">
                                        <Button v-if="block.cover_url" variant="ghost" size="sm" @click="block.cover_url = ''">
                                            <UiIcon name="trash" :size="14" />
                                            <span>Clear</span>
                                        </Button>
                                        <Button variant="secondary" size="sm" @click="openMediaLibrary('video_cover', block, 'pages')">
                                            <UiIcon name="folder" :size="14" />
                                            <span>Media library</span>
                                        </Button>
                                    </div>
                                </div>
                                <input :id="`block-${bIdx}-yt-cover`" v-model="block.cover_url" type="text" placeholder="https://example.com/cover.jpg" :class="[field, 'font-mono text-xs']" />
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Without a cover, the YouTube thumbnail is used and loads from YouTube's image server when the page opens. A cover from your media library keeps visitors' details away from YouTube until they press play.</p>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 items-end">
                                <div>
                                    <label :for="`block-${bIdx}-yt-start`" :class="[fieldLabel, 'mb-1.5']">Start at</label>
                                    <input :id="`block-${bIdx}-yt-start`" :value="formatTimestamp(block.start)" @change="setYoutubeTime(block, 'start', $event)" type="text" placeholder="e.g. 1:30" :class="[field, 'font-mono text-xs']" />
                                </div>
                                <div>
                                    <label :for="`block-${bIdx}-yt-end`" :class="[fieldLabel, 'mb-1.5']">Stop at</label>
                                    <input :id="`block-${bIdx}-yt-end`" :value="formatTimestamp(block.end)" @change="setYoutubeTime(block, 'end', $event)" type="text" placeholder="e.g. 4:05" :class="[field, 'font-mono text-xs']" />
                                </div>
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer pb-2">
                                    <input type="checkbox" v-model="block.loop" :class="checkbox" /> Repeat (loop)
                                </label>
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer pb-2">
                                    <input type="checkbox" v-model="block.captions" :class="checkbox" /> Show captions
                                </label>
                            </div>

                            <div class="space-y-3 p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                    <input type="checkbox" v-model="block.show_youtube_link" :class="checkbox" /> Show a "Watch on YouTube" link
                                </label>
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                    <input type="checkbox" v-model="block.button_enabled" :class="checkbox" /> Add a button
                                </label>
                                <div v-if="block.button_enabled" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <Input v-model="block.button_label" label="Button label" maxlength="100" placeholder="For example, Book your place" />
                                    <Input v-model="block.button_url" label="Button link" placeholder="https://..." class="sm:col-span-2" />
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer sm:col-span-3">
                                        <input type="checkbox" v-model="block.button_new_tab" :class="checkbox" /> Open the button link in a new tab
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

                        <!-- 7. News feed and news list -->
                        <div v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-3">
                            <Input v-model="block.heading" label="Section heading" placeholder="For example, latest news" />

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <Select v-model="block.columns" label="Layout and columns" :options="NEWS_COLUMNS" />
                                <Select v-model="block.image_position" label="Featured image position" :options="NEWS_IMAGE_POSITIONS" />

                                <div>
                                    <label :for="`block-${bIdx}-news-limit`" :class="[fieldLabel, 'mb-1.5']">Articles per page</label>
                                    <select :id="`block-${bIdx}-news-limit`" v-model="block.limit" :class="field">
                                        <option :value="3">3 articles per page</option>
                                        <option :value="6">6 articles per page</option>
                                        <option :value="9">9 articles per page</option>
                                        <option :value="12">12 articles per page</option>
                                        <option :value="24">24 articles per page</option>
                                        <option :value="999">Show all articles (no pages)</option>
                                    </select>
                                </div>
                            </div>

                            <Alert variant="info">
                                <strong class="font-medium">Filled in automatically:</strong> shows published news items in a <strong class="font-medium">{{ String(block.columns) === 'masonry' ? 'masonry grid' : (block.columns || 3) + '-column grid' }}</strong>, with Previous and Next page controls when there are more than {{ block.limit == 999 ? 'all articles' : block.limit + ' per page' }}.
                            </Alert>
                        </div>

                        <!-- 8. Events and summons feed -->
                        <div v-else-if="block.type === 'events_calendar'" class="space-y-3">
                            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
                            <Alert variant="info">
                                <strong class="font-medium">Filled in automatically:</strong> shows the club's upcoming events, dinners and meetings.
                            </Alert>
                        </div>

                        <!-- 9. Membership dues -->
                        <div v-else-if="block.type === 'pricing_cards'" class="space-y-3">
                            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
                            <Alert variant="info">
                                <strong class="font-medium">Filled in automatically:</strong> shows the active membership plans and prices set up in the club's settings.
                            </Alert>
                        </div>

                        <!-- 10. Donation campaign -->
                        <div v-else-if="block.type === 'donation_campaign'" class="space-y-3">
                            <Input v-model="block.heading" label="Section heading" placeholder="Section heading" />
                            <Alert variant="info">
                                <strong class="font-medium">Filled in automatically:</strong> shows active fundraising campaigns and their progress bars.
                            </Alert>
                        </div>

                        <!-- 11. Contact details -->
                        <div v-else-if="block.type === 'contact_details'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Input v-model="block.eyebrow" label="Small label above the title" placeholder="For example, CONTACT" />
                                <Input v-model="block.title" label="Section title" placeholder="For example, Get in touch" />
                            </div>

                            <Textarea v-model="block.description" label="Introduction" :rows="2" placeholder="Introductory paragraph..." />

                            <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
                                    Contact cards
                                    <span class="text-xs text-slate-500 dark:text-slate-400 font-normal">(they start with the details from the website settings)</span>
                                </h4>

                                <!-- Email card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <Input v-model="block.email_heading" label="Email heading" placeholder="Email" />
                                    <Input
                                        v-model="block.email"
                                        label="Contact email"
                                        :placeholder="'Default: ' + (settingsForm.contact_email || club.contact_email || 'Not configured')"
                                        hint="Leave blank to use the contact email from the organisation settings."
                                        class="sm:col-span-2"
                                    />
                                </div>

                                <!-- Meeting times card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <Input v-model="block.times_heading" label="Times heading" placeholder="Meeting times" />
                                    <Textarea v-model="block.times" label="Meeting times and schedule" :rows="2" placeholder="e.g. 7:00 pm, 4th Thursday..." class="sm:col-span-2" />
                                </div>

                                <!-- Location card -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                    <Input v-model="block.location_heading" label="Location heading" placeholder="Location" />
                                    <Textarea v-model="block.location" label="Full address" :rows="3" placeholder="e.g. Masonic Hall, Street, Town..." class="sm:col-span-2" />
                                </div>
                            </div>
                        </div>

                        <!-- 12. Contact form -->
                        <div v-else-if="block.type === 'contact_form'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <Input v-model="block.heading" label="Section heading" placeholder="For example, Send us a message" />
                                <Input v-model="block.button_text" label="Send button label" placeholder="For example, Send message" />
                            </div>

                            <Textarea v-model="block.subtitle" label="Subtitle or instructions" :rows="2" placeholder="Subheading or instructions..." />

                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Where messages go</h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="space-y-1.5">
                                        <Input
                                            v-model="block.recipient_email"
                                            type="email"
                                            label="Secretary or recipient email"
                                            :placeholder="'Default: ' + (settingsForm.contact_email || club.contact_email || 'secretary@' + club.slug + '.org.uk')"
                                        />
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <Button variant="ghost" size="sm" @click="block.recipient_email = ''">Use the organisation default</Button>
                                            <Button
                                                v-if="settingsForm.contact_email || club.contact_email"
                                                variant="secondary"
                                                size="sm"
                                                @click="block.recipient_email = settingsForm.contact_email || club.contact_email"
                                            >
                                                <UiIcon name="mail" :size="14" />
                                                <span>Fill in {{ settingsForm.contact_email || club.contact_email }}</span>
                                            </Button>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-tight">
                                            Messages sent through the form go here. Leave blank to use the contact email from the organisation settings ({{ settingsForm.contact_email || club.contact_email || 'not set up' }}).
                                        </p>
                                    </div>

                                    <Input v-model="block.cc_emails" label="CC email addresses (comma-separated)" placeholder="e.g. treasurer@lodge.org, assistant@lodge.org" hint="Copies of each message are sent here." />
                                </div>
                            </div>

                            <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Required fields</h4>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="checkbox" v-model="block.name_required" :class="checkbox" />
                                        Name required
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="checkbox" v-model="block.email_required" :class="checkbox" />
                                        Email required
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="checkbox" v-model="block.phone_required" :class="checkbox" />
                                        Phone required
                                    </label>
                                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300 cursor-pointer">
                                        <input type="checkbox" v-model="block.message_required" :class="checkbox" />
                                        Message required
                                    </label>
                                </div>
                            </div>

                            <Input v-model="block.success_message" label="Message shown after sending" placeholder="For example, Thank you, your message has been sent." />
                        </div>

                        <SectionStyleEditor v-if="block.type !== 'hero'" :block="block" :index="bIdx" :can-apply-to-others="form.blocks.length > 1" @media="(type, target) => openMediaLibrary(type, target || block, 'pages')" @apply-all="applySectionToAll(block)" />
                    </div>
                </div>

                <!-- Insert point below the element -->
                <div class="relative py-1 flex items-center justify-center insert-menu-container">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <button
                            type="button"
                            @click.stop="toggleInsertMenu(bIdx + 1, $event)"
                            class="inline-flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 rounded-full text-xs font-medium shadow-sm transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                            <UiIcon name="plus" :size="14" class="text-blue-600 dark:text-blue-400" />
                            <span>Insert an element here</span>
                        </button>

                        <!-- Insert menu -->
                        <div
                            v-if="activeInsertIndex === bIdx + 1"
                            :class="[
                                'absolute z-40 w-[1100px] max-w-[calc(100vw-3.5rem)] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                            ]"
                            :style="{ translate: `calc(-50% + ${insertMenuShift}px) 0` }"
                        >
                            <div class="px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                <span>Insert an element here</span>
                                <button type="button" aria-label="Close" @click="activeInsertIndex = null" :class="iconButton"><UiIcon name="x" /></button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3 pt-1">
                                <div v-for="(col, cIdx) in blockColumns" :key="cIdx" class="space-y-1.5">
                                    <div class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                        {{ col.title }}
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="item in col.items"
                                            :key="item.type"
                                            type="button"
                                            @click="addBlock(item.type, bIdx + 1)"
                                            class="w-full flex items-start gap-2.5 p-2 text-left text-xs text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-100 dark:border-slate-800 hover:border-blue-200 dark:hover:border-blue-800/60 rounded-xl transition-colors cursor-pointer group"
                                        >
                                            <span class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0 bg-slate-100 text-blue-600 dark:bg-slate-800 dark:text-blue-400">
                                                <UiIcon :name="BLOCK_ICONS[item.type]" />
                                            </span>
                                            <div class="min-w-0">
                                                <span class="block font-medium text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">{{ item.label }}</span>
                                                <span class="text-[11px] text-slate-500 dark:text-slate-400 leading-tight block line-clamp-2">{{ item.desc }}</span>
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
