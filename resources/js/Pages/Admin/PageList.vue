<script setup>
import { BLOCK_TYPES, BLOCK_COLUMNS, newBlock } from '@/Support/pageBlockCatalog';
import PagePreviewPane from '@/Components/Pages/PagePreviewPane.vue';
import PageEditPane from '@/Components/Pages/PageEditPane.vue';
import PageThemesPane from '@/Components/Pages/PageThemesPane.vue';
import PageHeaderFooterPane from '@/Components/Pages/PageHeaderFooterPane.vue';
import PageRedirectsPane from '@/Components/Pages/PageRedirectsPane.vue';
import PageTablePane from '@/Components/Pages/PageTablePane.vue';
import PageTrashPane from '@/Components/Pages/PageTrashPane.vue';
import PageSettingsPane from '@/Components/Pages/PageSettingsPane.vue';
import { usePageListActions } from '@/Composables/usePageListActions';
import { ANNOUNCEMENT_STYLES, FOOTER_LAYOUTS, HEADER_LAYOUTS, isDefaultPage, lifecycleText, MAX_FOOTER_COLUMN_LINKS, MAX_FOOTER_COLUMNS, normalizeFooterLinkColumns, settingsStateOf } from '@/Utils/pageBuilderSettings';
import { getPageIcon, parseSelectionUrl, selectionUrl as buildSelectionUrl } from '@/Utils/pageBuilderUrl';
import { useLeaveGuard } from '@/Composables/useLeaveGuard';
import { useColourSchemeEditor } from '@/Composables/useColourSchemeEditor';
import { usePageBlockCopy } from '@/Composables/usePageBlockCopy';
import { usePagePreviewLink } from '@/Composables/usePagePreviewLink';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import PageHistoryModal from '@/Components/PageHistoryModal.vue';
import PageChooserModal from '@/Components/PageChooserModal.vue';
import { toLocalInput, fromLocalInput } from '@/Utils/localDateTime';
import { parseYouTubeUrl, parseTimestamp, formatTimestamp } from '@/Utils/youtube';
import { readCollapsed, writeCollapsed } from '@/Utils/collapsedBlocks';
import { SITE_LAYOUTS, SITE_COLOR_SCHEMES, LEGACY_THEME, DEFAULT_THEME_KEY, themeClasses, withSiteStyle, asColourScheme } from '@/Support/siteThemes';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// getPageIcon (Utils/pageBuilderUrl) answers with an emoji; the builder's own lists draw the matching interface icon.
const PAGE_ICON_NAMES = { '🏠': 'home', 'ℹ️': 'info', '🤝': 'users', '📰': 'document', '✉️': 'mail' };
const pageIconName = (page) => PAGE_ICON_NAMES[getPageIcon(page)] || 'document';

// A link in the side navigation, highlighted while its screen is showing.
const navLinkClass = (isActive) => [
    'w-full px-3 py-2 rounded-xl transition-colors flex items-center gap-2.5 cursor-pointer text-left',
    isActive ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white',
];

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


const activeNavSelection = ref(props.selectedId ? String(props.selectedId) : (props.pages.length > 0 ? String(props.pages[0].id) : 'new'));
// 'preview' or 'edit', taken from the address: /pages/7/edit opens the builder, /pages/7/edit?view=preview the preview.
const initialViewMode = () => {
    const view = new URLSearchParams(window.location.search).get('view');
    if (view === 'preview' || view === 'edit') return view;
    return /\/admin\/pages\/(create|\d+\/edit)\/?$/.test(window.location.pathname) ? 'edit' : 'preview';
};
const pageViewMode = ref(initialViewMode());
const isSavedSuccess = ref(false);

const settingsForm = useForm({
    seo_title_suffix: props.websiteSettings?.seo_title_suffix || '',
    seo_meta_description: props.websiteSettings?.seo_meta_description || '',
    custom_domain: props.websiteSettings?.custom_domain || '',
    site_redirect_to_domain: props.websiteSettings?.site_redirect_to_domain ?? false,
    primary_color: props.websiteSettings?.primary_color || '#0369a1',
    contact_email: props.websiteSettings?.contact_email || '',
    phone: props.websiteSettings?.phone || '',
    address: props.websiteSettings?.address || '',
    share_image_url: props.websiteSettings?.share_image_url || '',
    site_icon_url: props.websiteSettings?.site_icon_url || '',
    noindex_site: props.websiteSettings?.noindex_site ?? false,
    analytics_provider: props.websiteSettings?.analytics_provider || 'none',
    analytics_id: props.websiteSettings?.analytics_id || '',
    cookie_banner_enabled: props.websiteSettings?.cookie_banner_enabled ?? false,
    cookie_banner_text: props.websiteSettings?.cookie_banner_text || '',
    cookie_banner_link_label: props.websiteSettings?.cookie_banner_link_label || '',
    cookie_banner_link_url: props.websiteSettings?.cookie_banner_link_url || '',
    not_found_page_id: props.websiteSettings?.not_found_page_id || '',
    revisions_keep: props.websiteSettings?.revisions_keep ?? 15,
    revisions_max_age_days: props.websiteSettings?.revisions_max_age_days ?? 180,
});

const submitWebsiteSettings = () => {
    settingsForm.post(route('admin.pages.settings.update', { clubSlug: props.club.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            isSavedSuccess.value = true;
            setTimeout(() => { isSavedSuccess.value = false; }, 3000);
        },
    });
};


const headerFooterForm = useForm({
    header_layout: props.websiteSettings?.header_layout || 'logo_left',
    header_show_logo: props.websiteSettings?.header_show_logo ?? true,
    header_show_tagline: props.websiteSettings?.header_show_tagline ?? true,
    header_cta_enabled: props.websiteSettings?.header_cta_enabled ?? false,
    header_cta_text: props.websiteSettings?.header_cta_text || '',
    header_cta_link: props.websiteSettings?.header_cta_link || '',
    header_show_account_links: props.websiteSettings?.header_show_account_links ?? true,
    footer_layout: props.websiteSettings?.footer_layout || 'simple',
    footer_show_social: props.websiteSettings?.footer_show_social ?? true,
    footer_show_nav: props.websiteSettings?.footer_show_nav ?? false,
    footer_about_text: props.websiteSettings?.footer_about_text || '',
    footer_show_custom_columns: props.websiteSettings?.footer_show_custom_columns ?? true,
    footer_nav_page_ids: Array.isArray(props.websiteSettings?.footer_nav_page_ids) ? props.websiteSettings.footer_nav_page_ids : null,
    // The © and the year are added automatically; only the holder (blank means the club's name) and the wording after it are edited.
    footer_copyright_holder: props.websiteSettings?.footer_copyright_holder && props.websiteSettings.footer_copyright_holder !== props.club.name ? props.websiteSettings.footer_copyright_holder : '',
    footer_copyright_text: props.websiteSettings?.footer_copyright_text ?? 'All rights reserved.',
    social_facebook: props.websiteSettings?.social_facebook || '',
    social_instagram: props.websiteSettings?.social_instagram || '',
    social_twitter: props.websiteSettings?.social_twitter || '',
    social_youtube: props.websiteSettings?.social_youtube || '',
    social_linkedin: props.websiteSettings?.social_linkedin || '',
    social_tiktok: props.websiteSettings?.social_tiktok || '',
    social_whatsapp: props.websiteSettings?.social_whatsapp || '',
    announcement_enabled: props.websiteSettings?.announcement_enabled ?? false,
    announcement_text: props.websiteSettings?.announcement_text || '',
    announcement_link_label: props.websiteSettings?.announcement_link_label || '',
    announcement_link_url: props.websiteSettings?.announcement_link_url || '',
    announcement_style: props.websiteSettings?.announcement_style || 'info',
    announcement_dismissible: props.websiteSettings?.announcement_dismissible ?? true,
    announcement_starts_on: props.websiteSettings?.announcement_starts_on || '',
    announcement_ends_on: props.websiteSettings?.announcement_ends_on || '',
    footer_link_columns: normalizeFooterLinkColumns(props.websiteSettings?.footer_link_columns),
});

const addFooterColumn = () => {
    if (headerFooterForm.footer_link_columns.length >= MAX_FOOTER_COLUMNS) return;
    headerFooterForm.footer_link_columns.push({
        id: 'col-' + Date.now(),
        title: 'Useful Links',
        links: [{ id: 'link-' + Date.now(), label: '', url: '' }],
    });
};

const removeFooterColumn = (colIndex) => {
    headerFooterForm.footer_link_columns.splice(colIndex, 1);
};

const addFooterLink = (colIndex) => {
    const column = headerFooterForm.footer_link_columns[colIndex];
    if (column.links.length >= MAX_FOOTER_COLUMN_LINKS) return;
    column.links.push({ id: 'link-' + Date.now() + '-' + column.links.length, label: '', url: '' });
};

const removeFooterLink = (colIndex, linkIndex) => {
    headerFooterForm.footer_link_columns[colIndex].links.splice(linkIndex, 1);
};

const submitHeaderFooter = () => {
    headerFooterForm.post(route('admin.pages.header_footer.update', { clubSlug: props.club.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            isSavedSuccess.value = true;
            setTimeout(() => { isSavedSuccess.value = false; }, 3000);
        },
    });
};

// Live theme (not the "preview a different theme" one from the Themes tab) — used to preview header/footer
// changes against whatever the club's site actually looks like right now.
// The saved font and corner choices (Website Themes, step 3) laid over the live theme.
const savedSiteStyle = computed(() => ({
    font_pairing: props.club?.settings?.font_pairing || 'theme',
    corner_style: props.club?.settings?.corner_style || 'theme',
}));
const liveThemeClasses = computed(() => withSiteStyle(themeClasses(currentThemeKey.value, customColourSchemes.value), savedSiteStyle.value));

// The social links and the custom link columns share a row when both are showing, so each is stacked inside its half.
const currentYear = new Date().getFullYear();

// The example shown in the custom domain box: built from this club's own address name, never another club's.
const domainExample = computed(() => `www.${props.club?.slug || 'yourclub'}.org.uk`);
const copyrightPreview = computed(() => {
    const holder = (headerFooterForm.footer_copyright_holder || props.club.name || '').trim().replace(/\.+$/, '');
    const text = headerFooterForm.footer_copyright_text.trim();
    return `© ${currentYear} ${holder}.${text ? ` ${text}` : ''}`;
});

const footerDetailsSideBySide = computed(() => headerFooterForm.footer_layout === 'columns');

// Which menu pages the footer's "Navigate" column lists: all of them (null) or the ones chosen in the pop-up.
const showNavChooser = ref(false);
const footerNavPages = computed(() => (Array.isArray(headerFooterForm.footer_nav_page_ids)
    ? publishedNavPages.value.filter((p) => headerFooterForm.footer_nav_page_ids.includes(p.id))
    : publishedNavPages.value));
const applyFooterNavPages = (ids) => {
    headerFooterForm.footer_nav_page_ids = ids;
    showNavChooser.value = false;
};

const announcementPreview = computed(() => (headerFooterForm.announcement_text.trim()
    ? {
        text: headerFooterForm.announcement_text.trim(),
        link_label: headerFooterForm.announcement_link_label.trim(),
        link_url: headerFooterForm.announcement_link_url.trim(),
        style: headerFooterForm.announcement_style,
        dismissible: headerFooterForm.announcement_dismissible,
        key: 'preview',
    }
    : null));

const publishedNavPages = computed(() => props.pages.filter(p => p.show_in_navigation && p.is_published));
const footerPinnedPages = computed(() => props.pages.filter(p => p.show_in_footer && p.lifecycle_status === 'live'));

const checkingDomain = ref(false);

const checkDomainNow = () => {
    checkingDomain.value = true;
    router.post(route('admin.pages.settings.verify_domain', { clubSlug: props.club.slug }), {}, {
        preserveScroll: true,
        onFinish: () => { checkingDomain.value = false; },
    });
};
const showDeleteConfirmModal = ref(false);
const pageToDelete = ref(null);

// Spatie Media Library Modal State
const showMediaModal = ref(false);
const mediaTarget = ref(null);
const mediaDefaultFolder = ref('pages');

const openMediaLibrary = (type, targetObj = null, folder = 'pages') => {
    mediaTarget.value = { type, targetObj };
    mediaDefaultFolder.value = folder;
    showMediaModal.value = true;
};

const onMediaSelect = (mediaItem) => {
    if (!mediaTarget.value) return;
    const { type, targetObj } = mediaTarget.value;
    if (type === 'block_image' && targetObj) {
        targetObj.url = mediaItem.url;
    } else if (type === 'gallery_image' && targetObj) {
        targetObj.url = mediaItem.url;
    } else if (type === 'video_cover' && targetObj) {
        targetObj.cover_url = mediaItem.url;
    } else if (type === 'cta_image' && targetObj) {
        targetObj.image_url = mediaItem.url;
    } else if (type === 'cta_side_image' && targetObj) {
        targetObj.side_image_url = mediaItem.url;
    } else if (type === 'slideshow_image' && targetObj) {
        targetObj.image_url = mediaItem.url;
    } else if (type === 'media_text_image' && targetObj) {
        targetObj.image_url = mediaItem.url;
    } else if (type === 'hero_image' && targetObj) {
        targetObj.image_url = mediaItem.url;
    } else if (type === 'section_image' && targetObj) {
        targetObj.section = { ...(targetObj.section || {}), bg: 'image', bg_image: mediaItem.url };
    } else if (type === 'page_share_image') {
        form.share_image = mediaItem.url;
    } else if (type === 'site_share_image') {
        settingsForm.share_image_url = mediaItem.url;
    } else if (type === 'site_icon') {
        settingsForm.site_icon_url = mediaItem.url;
    } else if (type === 'download_link' && targetObj) {
        targetObj.url = mediaItem.url;
        if (!targetObj.title) targetObj.title = mediaItem.name || '';
    }
};

// A pasted Shorts link switches a fresh video block to portrait, and a link with a time (?t=90) fills the
// start time; both only when the admin has not already chosen something.
const onYoutubeUrlInput = (block) => {
    const video = parseYouTubeUrl(block.url);
    if (!video) return;
    if (video.isShort && block.aspect === '16:9') block.aspect = '9:16';
    if (video.start && !block.start) block.start = video.start;
};

const setYoutubeTime = (block, field, event) => {
    block[field] = parseTimestamp(event.target.value);
    event.target.value = formatTimestamp(block[field]);
};

const LAYOUTS = SITE_LAYOUTS;
const PRESET_COLOR_SCHEMES = SITE_COLOR_SCHEMES;
// The lodge's own named colour schemes (Colour Scheme step), shown after the built-in ones.
const customColourSchemes = computed(() => props.club?.settings?.custom_color_schemes || []);
const ownColourSchemes = computed(() => customColourSchemes.value.map(asColourScheme).filter(Boolean));
const COLOR_SCHEMES = computed(() => [...PRESET_COLOR_SCHEMES, ...ownColourSchemes.value]);
const LEGACY = LEGACY_THEME;

const currentThemeKey = computed(() => props.club?.settings?.website_theme || DEFAULT_THEME_KEY);

// A theme key is either the legacy `masonic` id, or `${layoutId}:${colorSchemeId}` — the layout and
// colour scheme are picked independently in the gallery below, then combined into that one string.
const keyFor = (layoutId, colorSchemeId) => (layoutId === LEGACY.id ? LEGACY.id : `${layoutId}:${colorSchemeId}`);
const parseKey = (key) => {
    if (key === LEGACY.id) return { layoutId: LEGACY.id, colorSchemeId: null };
    const [layoutId, colorSchemeId] = String(key || '').split(':');
    return {
        layoutId: LAYOUTS.some(l => l.id === layoutId) ? layoutId : DEFAULT_THEME_KEY.split(':')[0],
        colorSchemeId: COLOR_SCHEMES.value.some(c => c.id === colorSchemeId) ? colorSchemeId : DEFAULT_THEME_KEY.split(':')[1],
    };
};

const initialParsed = parseKey(currentThemeKey.value);
const selectedLayoutId = ref(initialParsed.layoutId);
const selectedColorSchemeId = ref(initialParsed.colorSchemeId);
const selectedThemeForModal = ref(null);
const showThemeConfirmModal = ref(false);

const themeForm = useForm({
    website_theme: currentThemeKey.value,
    font_pairing: props.club?.settings?.font_pairing || 'theme',
    corner_style: props.club?.settings?.corner_style || 'theme',
});

// The lodge's own colour schemes (see the composable).
const { MAX_CUSTOM_SCHEMES, canCreateScheme, schemeEditorOpen, schemeError, schemeForm, openNewScheme, editScheme, closeSchemeEditor, saveScheme, deleteScheme, draftColours, draftPalette, draftColoursClose, usedByLiveSite, SCHEME_SHADES } = useColourSchemeEditor({ club: props.club, colorSchemes: COLOR_SCHEMES, selectedColorSchemeId, customColourSchemes, currentThemeKey });

const previewThemeInBuilder = (layoutId, colorSchemeId = null) => {
    selectedLayoutId.value = layoutId;
    if (layoutId !== LEGACY.id) {
        selectedColorSchemeId.value = colorSchemeId || selectedColorSchemeId.value || COLOR_SCHEMES.value[0].id;
    }
    const targetPageId = props.pages.length > 0 ? props.pages[0].id : 'new';
    requestNavigation(targetPageId, 'preview');
};

const effectivePreviewThemeKey = computed(() => keyFor(selectedLayoutId.value, selectedColorSchemeId.value));
const previewThemeClasses = computed(() => withSiteStyle(themeClasses(effectivePreviewThemeKey.value, customColourSchemes.value), { font_pairing: themeForm.font_pairing, corner_style: themeForm.corner_style, theme_colors: draftColours.value }));

const applyPreviewTheme = (themeKey = null) => {
    const targetThemeKey = themeKey || effectivePreviewThemeKey.value;
    themeForm.website_theme = targetThemeKey;
    themeForm.post(route('admin.pages.themes.update', { clubSlug: props.club.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            isSavedSuccess.value = true;
            setTimeout(() => { isSavedSuccess.value = false; }, 3000);
        },
    });
};

const openApplyThemeModal = (themeKey, label) => {
    selectedThemeForModal.value = { key: themeKey, label };
    showThemeConfirmModal.value = true;
};

const confirmApplyTheme = () => {
    if (!selectedThemeForModal.value) return;
    applyPreviewTheme(selectedThemeForModal.value.key);
    showThemeConfirmModal.value = false;
    selectedThemeForModal.value = null;
};

const styleChanged = computed(() => themeForm.font_pairing !== savedSiteStyle.value.font_pairing || themeForm.corner_style !== savedSiteStyle.value.corner_style);

const colorSchemeName = (id) => COLOR_SCHEMES.value.find(c => c.id === id)?.name || '';
const layoutName = (id) => (id === LEGACY.id ? LEGACY.name : LAYOUTS.find(l => l.id === id)?.name || '');
const themeLabel = (key) => {
    if (key === LEGACY.id) return LEGACY.name;
    const { layoutId, colorSchemeId } = parseKey(key);
    return `${layoutName(layoutId)} — ${colorSchemeName(colorSchemeId)}`;
};

const MANAGEMENT_SELECTIONS = ['overview', 'settings', 'themes', 'header_footer', 'redirects'];

const activePage = computed(() => {
    if (activeNavSelection.value === 'new' || MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
        return null;
    }
    return props.pages.find(p => String(p.id) === String(activeNavSelection.value)) || null;
});

const form = useForm({
    id: null,
    title: '',
    slug: '',
    is_published: true,
    is_homepage: false,
    show_in_navigation: true,
    show_in_footer: false,
    is_members_only: false,
    header_style: 'full',
    publish_at: null,
    unpublish_at: null,
    meta_title: '',
    meta_description: '',
    share_image: '',
    noindex: false,
    blocks: [],
});

const initialFormSnapshot = ref('');
const isBypassingDirtyGuard = ref(false);
const showUnsavedModal = ref(false);
const pendingNavigationTarget = ref(null); // { selection: string, mode: 'edit'|'preview' }

// The page's settings (the things that save with the normal Save, never with a draft), in one place so the
// "unsaved changes" check can compare them against what the server holds.


const currentSettings = () => ({
    slug: form.slug,
    is_published: form.is_published,
    is_homepage: form.is_homepage,
    show_in_navigation: form.show_in_navigation,
    show_in_footer: form.show_in_footer,
    is_members_only: form.is_members_only,
    header_style: form.header_style,
    publish_at: form.publish_at,
    unpublish_at: form.unpublish_at,
});

const getFormStateString = (settings = null) => {
    return JSON.stringify({
        id: form.id,
        title: form.title,
        ...(settings ?? currentSettings()),
        meta_title: form.meta_title,
        meta_description: form.meta_description,
        share_image: form.share_image,
        noindex: form.noindex,
        blocks: form.blocks,
    });
};

const takeFormSnapshot = () => {
    initialFormSnapshot.value = getFormStateString();
};

const isDirty = computed(() => {
    if (MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
        return false;
    }
    return initialFormSnapshot.value !== '' && getFormStateString() !== initialFormSnapshot.value;
});

// Redirects: old addresses on this site (after /site/{club}) sent somewhere else.
const redirectForm = useForm({ from_path: '', to_url: '', is_permanent: true });
const redirects = computed(() => props.websiteSettings?.redirects || []);

const flashSaved = () => {
    isSavedSuccess.value = true;
    setTimeout(() => { isSavedSuccess.value = false; }, 3000);
};

const addRedirect = () => {
    redirectForm.post(route('admin.pages.redirects.store', { clubSlug: props.club.slug }), {
        preserveScroll: true,
        onSuccess: () => { redirectForm.reset(); flashSaved(); },
    });
};

const removeRedirect = (id) => {
    router.delete(route('admin.pages.redirects.destroy', { clubSlug: props.club.slug, id }), { preserveScroll: true, onSuccess: flashSaved });
};

// How the page is likely to look in a search engine, from what has been typed so far.
const searchPreview = computed(() => {
    const suffix = settingsForm.seo_title_suffix || `| ${props.club.name}`;
    const title = `${form.meta_title || form.title || 'Page title'} ${suffix}`.trim();
    const description = form.meta_description || settingsForm.seo_meta_description || 'No description yet. Search engines will pick some text from the page.';
    const path = form.is_homepage || form.slug === 'home' ? '' : ` › ${form.slug}`;

    return { title, description, url: `${window.location.host} › site › ${props.club.slug}${path}` };
});

// The SEO and URL card starts folded away; it shows the page's address while closed.
const seoOpen = ref(false);

// Compact view state (see toggleCollapsed): ids of the elements folded down to a summary. Remembered per page in
// this browser (see Utils/collapsedBlocks), so it survives reloads of the page and copying an element elsewhere.
const collapsedBlocks = ref(new Set());

// The private preview link panel; declared here because loadPageIntoForm resets it (`form` is declared further up).
const { previewUrl, previewOpen, previewCopied, previewError, resetPreviewLink, openPreviewPanel, renewPreviewLink, copyPreviewLink } = usePagePreviewLink(props.club, form);

const loadPageIntoForm = (page) => {
    collapsedBlocks.value = page?.id ? readCollapsed(props.club.slug, page.id) : new Set();
    resetPreviewLink();
    if (page) {
        // A page with an unpublished draft opens on the draft, since that is the latest version of its content.
        const draft = page.draft_saved_at ? (page.draft_content || {}) : null;
        form.id = page.id;
        Object.assign(form, settingsStateOf(page));
        form.title = (draft ? draft.title : page.title) || '';
        form.meta_title = (draft ? draft.meta_title : page.meta_title) || '';
        form.meta_description = (draft ? draft.meta_description : page.meta_description) || '';
        form.share_image = (draft ? draft.share_image : page.share_image) || '';
        form.noindex = page.noindex ?? false;
        const blocks = draft ? (page.draft_blocks ?? page.blocks) : page.blocks;
        form.blocks = blocks ? JSON.parse(JSON.stringify(blocks)) : [];
    } else {
        form.id = null;
        form.title = 'New Custom Page';
        form.slug = 'new-custom-page';
        form.is_published = true;
        form.is_homepage = false;
        form.show_in_navigation = true;
        form.show_in_footer = false;
        form.is_members_only = false;
        form.header_style = 'full';
        form.publish_at = null;
        form.unpublish_at = null;
        form.meta_title = '';
        form.meta_description = '';
        form.share_image = '';
        form.noindex = false;
        form.blocks = [
            {
                id: 'block-' + Date.now(),
                type: 'hero',
                title: 'Welcome to Our New Page',
                subtitle: 'Discover more about our club',
                cta_text: 'Get Started',
                cta_link: `/site/${props.club.slug}`,
            },
            {
                id: 'block-' + (Date.now() + 1),
                type: 'text',
                heading: 'Page Overview',
                content: '<p>Add detailed custom content here.</p>',
            }
        ];
    }
    // Folding is tracked by block id, so every block needs one.
    form.blocks.forEach((block, i) => { if (!block.id) block.id = `block-${Date.now()}-${i}`; });
    nextTick(() => {
        takeFormSnapshot();
    });
};

// Initialize form
if (activePage.value) {
    loadPageIntoForm(activePage.value);
} else if (activeNavSelection.value === 'new') {
    loadPageIntoForm(null);
}

watch(activeNavSelection, (newVal) => {
    if (MANAGEMENT_SELECTIONS.includes(newVal)) {
        return;
    }
    if (newVal === 'new') {
        loadPageIntoForm(null);
    } else {
        const found = props.pages.find(p => String(p.id) === String(newVal));
        if (found) {
            loadPageIntoForm(found);
        }
    }
});

watch(() => props.pages, (newPages) => {
    if (activeNavSelection.value !== 'new' && !MANAGEMENT_SELECTIONS.includes(activeNavSelection.value)) {
        const current = newPages.find(p => String(p.id) === String(activeNavSelection.value));
        if (current) {
            loadPageIntoForm(current);
        }
    }
}, { deep: true });


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

const blockTypes = BLOCK_TYPES;
const blockColumns = BLOCK_COLUMNS;

const activeInsertIndex = ref(null);
const insertMenuPlacement = ref('down');
// The menu is centred on its button; this nudges it sideways so it never runs off either edge of the window and
// keeps a wider gap on the right than the left.
const insertMenuShift = ref(0);
const INSERT_MENU_WIDTH = 1100;
const INSERT_MENU_MARGIN_LEFT = 16;
const INSERT_MENU_MARGIN_RIGHT = 40;

const toggleInsertMenu = (index, event = null) => {
    if (activeInsertIndex.value === index) {
        activeInsertIndex.value = null;
        return;
    }

    activeInsertIndex.value = index;

    if (event && event.currentTarget) {
        const rect = event.currentTarget.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;

        const width = Math.min(INSERT_MENU_WIDTH, window.innerWidth - INSERT_MENU_MARGIN_LEFT - INSERT_MENU_MARGIN_RIGHT);
        const centredLeft = rect.left + rect.width / 2 - width / 2;
        const clampedLeft = Math.max(INSERT_MENU_MARGIN_LEFT, Math.min(centredLeft, window.innerWidth - INSERT_MENU_MARGIN_RIGHT - width));
        insertMenuShift.value = Math.round(clampedLeft - centredLeft);

        if (spaceBelow < 300 && spaceAbove > spaceBelow) {
            insertMenuPlacement.value = 'up';
        } else {
            insertMenuPlacement.value = 'down';
        }
    } else {
        insertMenuPlacement.value = 'down';
        insertMenuShift.value = 0;
    }
};

const handleDocumentClick = (e) => {
    if (activeInsertIndex.value !== null && !e.target.closest('.insert-menu-container')) {
        activeInsertIndex.value = null;
    }
};

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick);
});

// "Use this look on every element": copies one element's section style (not its anchor) to the others on the page.
const applySectionToAll = (source) => {
    const { anchor: _anchor, ...look } = source.section || {};

    form.blocks.forEach((block) => {
        if (block !== source && block.type !== 'hero') block.section = { ...look, anchor: block.section?.anchor || '' };
    });
};

const addBlock = (type, targetIndex = null) => {
    const block = newBlock(type, { club: props.club, clubAddresses: props.clubAddresses, websiteSettings: props.websiteSettings });

    if (block) {
        const index = targetIndex !== null && targetIndex >= 0 && targetIndex <= form.blocks.length ? targetIndex : form.blocks.length;
        form.blocks.splice(index, 0, block);
        revealBlock(block, index);
    }
    activeInsertIndex.value = null;
};

const newBlockId = ref(null);
const blockCardId = (block, index) => `block-card-${block.id || index}`;

// Bring a block that was just added to the top of the view (the admin may have been looking at another block
// when they added it), and outline it for a moment so it is obvious which one it is.
const revealBlock = (block, index) => {
    newBlockId.value = block.id;
    nextTick(() => {
        document.getElementById(blockCardId(block, index))?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        setTimeout(() => { if (newBlockId.value === block.id) newBlockId.value = null; }, 2500);
    });
};

// Compact view: which elements are folded down to a read-only summary. This is view state only (not part of
// `form.blocks`), so folding never makes the page "unsaved" and is never saved.
const isCollapsed = (block) => collapsedBlocks.value.has(block.id);
const toggleCollapsed = (block) => {
    const next = new Set(collapsedBlocks.value);
    if (!next.delete(block.id)) next.add(block.id);
    collapsedBlocks.value = next;
};
const collapseAll = () => { collapsedBlocks.value = new Set(form.blocks.map((b) => b.id)); };
const expandAll = () => { collapsedBlocks.value = new Set(); };
const collapsedCount = computed(() => form.blocks.filter((b) => collapsedBlocks.value.has(b.id)).length);

// Save the folded elements whenever they change (only for a saved page, and only ids that still exist on it).
watch(collapsedBlocks, (folded) => {
    if (!form.id) return;
    const present = new Set(form.blocks.map((b) => b.id));
    writeCollapsed(props.club.slug, form.id, [...folded].filter((id) => present.has(id)));
});

const removeBlock = (index) => {
    form.blocks.splice(index, 1);
};

// Deleting an element asks first, since it takes its content with it (the page is only changed once saved).
const blockToDelete = ref(null);
const blockToDeleteName = computed(() => {
    const block = blockToDelete.value === null ? null : form.blocks[blockToDelete.value];
    if (!block) return '';
    const label = blockTypes.find((t) => t.type === block.type)?.label || 'element';
    const name = block.heading || block.title || block.label || block.question || '';
    return name ? `${label}: "${String(name).slice(0, 60)}"` : label;
});
const confirmRemoveBlock = () => {
    if (blockToDelete.value !== null) removeBlock(blockToDelete.value);
    blockToDelete.value = null;
};

const moveBlockUp = (index) => {
    if (index > 0) {
        const temp = form.blocks[index];
        form.blocks[index] = form.blocks[index - 1];
        form.blocks[index - 1] = temp;
    }
};

const moveBlockDown = (index) => {
    if (index < form.blocks.length - 1) {
        const temp = form.blocks[index];
        form.blocks[index] = form.blocks[index + 1];
        form.blocks[index + 1] = temp;
    }
};

const duplicateBlock = (index) => {
    const source = form.blocks[index];
    const copy = JSON.parse(JSON.stringify(source));
    copy.id = 'block-' + Date.now();
    form.blocks.splice(index + 1, 0, copy);
    revealBlock(copy, index + 1);
};

const removeBlockImage = (block) => {
    block.url = '';
};

const addGalleryImage = (block) => {
    const gId = block.id + '-g' + Date.now();
    block.items.push({ id: gId, url: '', caption: '' });
};

const removeGalleryImage = (block, idx) => {
    block.items.splice(idx, 1);
};

const submitForm = (onSuccessCallback = null) => {
    isBypassingDirtyGuard.value = true;
    form.post(`/${props.club.slug}/admin/pages`, {
        preserveScroll: true,
        onSuccess: () => {
            isSavedSuccess.value = true;
            nextTick(() => {
                takeFormSnapshot();
                isBypassingDirtyGuard.value = false;
            });
            setTimeout(() => {
                isSavedSuccess.value = false;
            }, 3000);
            if (typeof onSuccessCallback === 'function') {
                onSuccessCallback();
            }
        },
        onError: () => {
            isBypassingDirtyGuard.value = false;
        }
    });
};

// ---- Drafts, private preview, history and copying ---------------------------------------------------------------
const hasDraft = computed(() => !!activePage.value?.draft_saved_at);
// The server keeps a draft only for a page that is already published, so this follows the saved page, not the checkbox.
const canDraft = computed(() => !!form.id && !!activePage.value?.is_published);

// Saving a draft keeps only the page's content aside, so the settings (address, menus, dates...) stay "unsaved"
// until the normal Save; the check below compares them against what the server holds.
const takeDraftSnapshot = () => {
    initialFormSnapshot.value = getFormStateString({ ...settingsStateOf(activePage.value || {}), slug: activePage.value?.slug ?? form.slug });
};

const saveDraft = () => {
    if (!form.id) return;
    isBypassingDirtyGuard.value = true;
    form.post(route('admin.pages.draft.save', { clubSlug: props.club.slug, id: form.id }), {
        preserveScroll: true,
        onSuccess: () => {
            nextTick(() => {
                takeDraftSnapshot();
                isBypassingDirtyGuard.value = false;
            });
            flashSaved();
        },
        onError: () => { isBypassingDirtyGuard.value = false; },
    });
};

const reloadFormFromServer = () => nextTick(() => loadPageIntoForm(activePage.value));

const publishDraft = () => {
    // Anything typed since the draft was saved is published with it, so the page goes live exactly as it looks here.
    if (isDirty.value) {
        submitForm();
        return;
    }
    router.post(route('admin.pages.draft.publish', { clubSlug: props.club.slug, id: form.id }), {}, { preserveScroll: true, onSuccess: () => { reloadFormFromServer(); flashSaved(); } });
};

const discardDraft = () => {
    if (!confirm('Discard this draft? The live page is not affected, but the draft edits are lost.')) return;
    router.delete(route('admin.pages.draft.discard', { clubSlug: props.club.slug, id: form.id }), { preserveScroll: true, onSuccess: reloadFormFromServer });
};

const showHistory = ref(false);
const restoreNotice = ref('');

const restoreRevision = (content) => {
    form.title = content.title || form.title;
    form.meta_title = content.meta_title || '';
    form.meta_description = content.meta_description || '';
    form.share_image = content.share_image || '';
    form.blocks = JSON.parse(JSON.stringify(content.blocks || []));
    form.blocks.forEach((block, i) => { if (!block.id) block.id = `block-${Date.now()}-${i}`; });
    collapsedBlocks.value = new Set();
    showHistory.value = false;
    restoreNotice.value = 'An earlier version is loaded below. Save to make it live, or leave the page to keep the current one.';
    setTimeout(() => { restoreNotice.value = ''; }, 8000);
};

const { copyTargets, copyNotice, copyNoticeIsError, copyBlockTo } = usePageBlockCopy(props.club, form, () => props.pages);


const publishAtLocal = computed({
    get: () => toLocalInput(form.publish_at),
    set: (value) => { form.publish_at = fromLocalInput(value); },
});
const unpublishAtLocal = computed({
    get: () => toLocalInput(form.unpublish_at),
    set: (value) => { form.unpublish_at = fromLocalInput(value); },
});

const { handleSaveAndProceed, handleDiscardAndProceed, handleStayAndEdit } = useLeaveGuard({ form, isDirty, isBypassingDirtyGuard, showUnsavedModal, pendingNavigationTarget, submitForm, executeNavigation, followAddress });

const { togglePublishPage, duplicatePage, restorePage, forceDeletePage, movePageUp, movePageDown } = usePageListActions(props.club, () => props.pages);


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
                
                <!-- Left Sidebar Navigation Panel -->
                <div class="lg:col-span-1 space-y-4">
                    
                    <!-- Mobile Page Selector Dropdown -->
                    <Card padding="sm" class="lg:hidden space-y-1.5">
                        <label for="mobile-page-selector" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Website page</label>
                        <!-- A raw select: the kit's Select has no option groups. -->
                        <select id="mobile-page-selector" :value="activeNavSelection" @change="e => requestNavigation(e.target.value)" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white cursor-pointer">
                            <optgroup label="Public website pages">
                                <option v-for="p in pages" :key="p.id" :value="String(p.id)">
                                    {{ p.title }} {{ p.is_homepage ? '(homepage)' : '' }}
                                </option>
                            </optgroup>
                            <optgroup label="Management">
                                <option value="settings">Website & SEO settings</option>
                                <option value="header_footer">Header & footer</option>
                                <option value="themes">Website themes</option>
                                <option value="redirects">Redirects</option>
                                <option value="new">Add a new custom page</option>
                                <option value="overview">Manage pages</option>
                            </optgroup>
                        </select>
                    </Card>

                    <!-- Desktop Vertical Sidebar Navigation Card -->
                    <Card padding="none" class="hidden lg:block p-3 space-y-5 sticky top-6">

                        <!-- Navigation Pages Group -->
                        <div>
                            <div class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                                <span>Navigation pages</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium normal-case tracking-normal text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ pages.length }} pages</span>
                            </div>

                            <div class="space-y-1 text-sm font-medium">
                                <a
                                    v-for="p in pages"
                                    :key="p.id"
                                    :href="selectionUrl(p.id, pageViewMode)"
                                    :aria-current="activeNavSelection === String(p.id) ? 'page' : undefined"
                                    @click="navClick($event, p.id, pageViewMode || 'edit')"
                                    :class="navLinkClass(activeNavSelection === String(p.id))"
                                >
                                    <UiIcon :name="pageIconName(p)" />
                                    <span class="truncate">{{ p.title }}</span>
                                </a>
                            </div>

                            <!-- Add New Page Quick Action -->
                            <a
                                :href="selectionUrl('new', 'edit')"
                                @click="navClick($event, 'new', 'edit')"
                                :class="[
                                    'w-full mt-3 px-3 py-2 rounded-xl border transition-colors text-sm font-medium flex items-center justify-center gap-2 cursor-pointer',
                                    activeNavSelection === 'new'
                                        ? 'bg-blue-600 border-transparent text-white'
                                        : 'bg-white border-slate-300 text-slate-700 hover:bg-slate-50 hover:border-slate-400 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800'
                                ]"
                            >
                                <UiIcon name="plus" />
                                <span>Add new custom page</span>
                            </a>
                        </div>

                        <!-- Management & Tools Group -->
                        <div class="border-t border-slate-200 dark:border-slate-800 pt-4 space-y-1">
                            <div class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Management</div>
                            <div class="space-y-1 text-sm font-medium">
                                <a
                                    :href="selectionUrl('settings')"
                                    :aria-current="activeNavSelection === 'settings' ? 'page' : undefined"
                                    @click="navClick($event, 'settings')"
                                    :class="navLinkClass(activeNavSelection === 'settings')"
                                >
                                    <UiIcon name="settings" /> Website & SEO settings
                                </a>

                                <a
                                    :href="selectionUrl('header_footer')"
                                    :aria-current="activeNavSelection === 'header_footer' ? 'page' : undefined"
                                    @click="navClick($event, 'header_footer')"
                                    :class="navLinkClass(activeNavSelection === 'header_footer')"
                                >
                                    <UiIcon name="resize" /> Header & footer
                                </a>

                                <a
                                    :href="selectionUrl('themes')"
                                    :aria-current="activeNavSelection === 'themes' ? 'page' : undefined"
                                    @click="navClick($event, 'themes')"
                                    :class="navLinkClass(activeNavSelection === 'themes')"
                                >
                                    <UiIcon name="palette" /> Website themes
                                </a>

                                <a
                                    :href="selectionUrl('redirects')"
                                    :aria-current="activeNavSelection === 'redirects' ? 'page' : undefined"
                                    @click="navClick($event, 'redirects')"
                                    :class="navLinkClass(activeNavSelection === 'redirects')"
                                >
                                    <UiIcon name="link" /> Redirects
                                </a>

                                <a
                                    :href="selectionUrl('overview')"
                                    :aria-current="activeNavSelection === 'overview' ? 'page' : undefined"
                                    @click="navClick($event, 'overview')"
                                    :class="navLinkClass(activeNavSelection === 'overview')"
                                >
                                    <UiIcon name="clipboard" /> Manage pages
                                </a>
                            </div>
                        </div>

                    </Card>
                </div>

                <!-- Right Main Content Panel -->
                <div class="lg:col-span-3 space-y-6">

                    <!-- View Mode Container (Edit / Live Preview Toggle) -->
                    <div v-if="!MANAGEMENT_SELECTIONS.includes(activeNavSelection)" class="space-y-6">

                        <!-- View Mode Selector Header Card -->
                        <Card padding="none" class="px-6 py-4 flex items-center justify-between gap-4 flex-wrap">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ form.id ? form.title : 'New page' }}
                                </h2>
                                <span class="text-xs text-slate-500 dark:text-slate-400 font-mono">/site/{{ club.slug }}/{{ form.slug }}</span>
                            </div>

                            <!-- Segmented Pill Control: Edit vs Live Preview -->
                            <div class="inline-flex gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
                                <a
                                    :href="selectionUrl(activeNavSelection, 'edit')"
                                    :aria-current="pageViewMode === 'edit' ? 'true' : undefined"
                                    @click="navClick($event, activeNavSelection, 'edit')"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-medium transition-colors flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'edit' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <UiIcon name="pencil" />
                                    <span>Edit builder</span>
                                </a>

                                <a
                                    :href="selectionUrl(activeNavSelection, 'preview')"
                                    :aria-current="pageViewMode === 'preview' ? 'true' : undefined"
                                    @click="navClick($event, activeNavSelection, 'preview')"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-medium transition-colors flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'preview' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <UiIcon name="eye" />
                                    <span>Live preview</span>
                                </a>
                            </div>
                        </Card>

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

        <!-- Unsaved Changes Interception Modal -->
        <Teleport to="body">
            <div 
                v-if="showUnsavedModal" 
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fade-in"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="unsaved-changes-modal-title"
                    v-focus-trap="handleStayAndEdit"
                    class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-5 relative overflow-hidden"
                >
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <UiIcon name="warning" :size="20" />
                        </div>
                        <div class="space-y-1 pt-0.5">
                            <h3 id="unsaved-changes-modal-title" class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Unsaved changes</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                You have modified <strong class="text-slate-900 dark:text-white font-semibold">"{{ form.title || 'this page' }}"</strong>.
                                What would you like to do with your changes before leaving?
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <Button block :disabled="form.processing" @click="handleSaveAndProceed">
                            <template #icon><UiIcon name="save" /></template>
                            <span>{{ form.processing ? 'Saving changes...' : 'Save changes & proceed' }}</span>
                            <span class="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-medium">Recommended</span>
                        </Button>

                        <Button block variant="danger" :disabled="form.processing" @click="handleDiscardAndProceed">
                            <template #icon><UiIcon name="trash" /></template>
                            Discard changes & switch page
                        </Button>

                        <Button block variant="ghost" :disabled="form.processing" @click="handleStayAndEdit">
                            <template #icon><UiIcon name="pencil" /></template>
                            Stay & continue editing
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Theme Apply Confirmation Modal -->
        <Teleport to="body">
            <div 
                v-if="showThemeConfirmModal && selectedThemeForModal" 
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm animate-fade-in"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="theme-confirm-modal-title"
                    v-focus-trap="() => { showThemeConfirmModal = false; selectedThemeForModal = null; }"
                    class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 space-y-5 relative overflow-hidden"
                >
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <UiIcon name="palette" :size="20" />
                        </div>
                        <div class="space-y-1 pt-0.5">
                            <h3 id="theme-confirm-modal-title" class="text-base font-semibold text-slate-900 dark:text-white leading-tight">Apply a new website theme?</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                You are about to switch your active design to <strong class="text-slate-900 dark:text-white font-semibold">"{{ selectedThemeForModal.label }}"</strong>.
                            </p>
                        </div>
                    </div>

                    <Alert variant="warning" title="Notice">
                        This will immediately change the visual appearance, color scheme, and layout of your live public site.
                        <strong>All your existing pages, news, events, and forms will stay intact.</strong>
                    </Alert>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <Button variant="secondary" :disabled="themeForm.processing" @click="showThemeConfirmModal = false; selectedThemeForModal = null;">
                            Cancel
                        </Button>
                        <Button :disabled="themeForm.processing" @click="confirmApplyTheme">
                            <template #icon><UiIcon v-if="!themeForm.processing" name="sparkles" /></template>
                            {{ themeForm.processing ? 'Applying theme...' : 'Apply theme to live site' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
