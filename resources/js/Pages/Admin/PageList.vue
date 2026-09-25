<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import BlockSummary from '@/Components/Blocks/BlockSummary.vue';
import PageHistoryModal from '@/Components/PageHistoryModal.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';
import PageChooserModal from '@/Components/PageChooserModal.vue';
import LinkField from '@/Components/Blocks/Editors/LinkField.vue';
import { postJson } from '@/Utils/postJson';
import { toLocalInput, fromLocalInput, formatLocal } from '@/Utils/localDateTime';
import { parseYouTubeUrl, parseTimestamp, formatTimestamp } from '@/Utils/youtube';
import CtaBannerEditor from '@/Components/Blocks/Editors/CtaBannerEditor.vue';
import FaqEditor from '@/Components/Blocks/Editors/FaqEditor.vue';
import MapEditor from '@/Components/Blocks/Editors/MapEditor.vue';
import DownloadsEditor from '@/Components/Blocks/Editors/DownloadsEditor.vue';
import CalendarEditor from '@/Components/Blocks/Editors/CalendarEditor.vue';
import HeroEditor from '@/Components/Blocks/Editors/HeroEditor.vue';
import FeatureCardsEditor from '@/Components/Blocks/Editors/FeatureCardsEditor.vue';
import SlideshowEditor from '@/Components/Blocks/Editors/SlideshowEditor.vue';
import StatsEditor from '@/Components/Blocks/Editors/StatsEditor.vue';
import QuoteMottoEditor from '@/Components/Blocks/Editors/QuoteMottoEditor.vue';
import SectionHeadingEditor from '@/Components/Blocks/Editors/SectionHeadingEditor.vue';
import SectionStyleEditor from '@/Components/Blocks/Editors/SectionStyleEditor.vue';
import DomainSetupGuide from '@/Components/DomainSetupGuide.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import AnnouncementBar from '@/Components/Site/AnnouncementBar.vue';
import { SITE_LAYOUTS, SITE_COLOR_SCHEMES, LEGACY_THEME, DEFAULT_THEME_KEY, FONT_PAIRINGS, CORNER_STYLES, themeClasses, withSiteStyle, paletteFromBrand, paletteForScheme, asColourScheme, contrastRatio, isHexColour } from '@/Support/siteThemes';

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

const DEFAULT_SLUGS = ['home', 'about', 'join-us', 'news', 'contact'];

const isDefaultPage = (page) => {
    if (!page) return false;
    return page.is_homepage || DEFAULT_SLUGS.includes(page.slug);
};

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

const HEADER_LAYOUTS = [
    { id: 'logo_left', name: 'Logo Left', description: 'Logo & name on the left, navigation and actions on the right — the classic layout.' },
    { id: 'logo_center', name: 'Logo Centered', description: 'Logo & name centered on top, navigation centered underneath.' },
];

const FOOTER_LAYOUTS = [
    { id: 'simple', name: 'Simple', description: 'A single centered line with the copyright notice (and social links, if enabled).' },
    { id: 'columns', name: 'Columns', description: 'Club info, navigation, custom link columns and social links laid out above the copyright line.' },
];

const MAX_FOOTER_COLUMNS = 4;
const MAX_FOOTER_COLUMN_LINKS = 8;

// The server strips any 'id' field it doesn't recognise, so a reloaded column/link may come back without
// one — assign a fresh id in that case, purely for :key stability in the editor.
const normalizeFooterLinkColumns = (columns) => (columns || []).map((col, colIdx) => ({
    id: col.id || `col-${Date.now()}-${colIdx}`,
    title: col.title || '',
    links: (col.links || []).map((link, linkIdx) => ({
        id: link.id || `link-${Date.now()}-${colIdx}-${linkIdx}`,
        label: link.label || '',
        url: link.url || '',
    })),
}));

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
const ANNOUNCEMENT_STYLES = [
    { id: 'info', name: 'Blue' },
    { id: 'success', name: 'Green' },
    { id: 'warning', name: 'Amber' },
    { id: 'dark', name: 'Dark' },
];

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

// ---- the lodge's own colour schemes: a name and two colours, saved beside the built-in ones --------------------
const selectedScheme = computed(() => COLOR_SCHEMES.value.find((c) => c.id === selectedColorSchemeId.value) || COLOR_SCHEMES.value[0]);
const selectedSchemePalette = computed(() => paletteForScheme(selectedScheme.value));
// Keep in step with App\Support\SiteThemes::MAX_CUSTOM_SCHEMES.
const MAX_CUSTOM_SCHEMES = 2;
const canCreateScheme = computed(() => customColourSchemes.value.length < MAX_CUSTOM_SCHEMES);
const schemeEditorOpen = ref(false);
const schemeError = ref('');
const schemeForm = useForm({ id: '', name: '', primary: '', accent: '' });

const newSchemeId = () => `custom-${Math.random().toString(36).slice(2, 10).padEnd(8, '0')}`;
const openNewScheme = () => {
    schemeError.value = '';
    schemeForm.clearErrors();
    schemeForm.id = newSchemeId();
    schemeForm.name = '';
    schemeForm.primary = selectedSchemePalette.value.primary;
    schemeForm.accent = selectedSchemePalette.value.accent;
    schemeEditorOpen.value = true;
};
const editScheme = (scheme) => {
    schemeError.value = '';
    schemeForm.clearErrors();
    schemeForm.id = scheme.id;
    schemeForm.name = scheme.name;
    schemeForm.primary = scheme.vars.primary;
    schemeForm.accent = scheme.vars.accent;
    selectedColorSchemeId.value = scheme.id;
    schemeEditorOpen.value = true;
};
const closeSchemeEditor = () => { schemeEditorOpen.value = false; schemeForm.clearErrors(); };
const saveScheme = () => {
    if (!schemeForm.name.trim()) {
        schemeForm.setError('name', 'Give the colour scheme a name.');
        document.getElementById('scheme-name')?.focus();
        return;
    }

    schemeForm.clearErrors();
    schemeForm.post(route('admin.pages.colour_schemes.save', { clubSlug: props.club.slug }), {
        preserveScroll: true,
        onSuccess: () => {
            selectedColorSchemeId.value = schemeForm.id;
            schemeEditorOpen.value = false;
        },
    });
};
const deleteScheme = (scheme) => {
    if (!window.confirm(`Delete the colour scheme "${scheme.name}"?`)) return;
    schemeError.value = '';
    router.delete(route('admin.pages.colour_schemes.delete', { clubSlug: props.club.slug, schemeId: scheme.id }), {
        preserveScroll: true,
        onSuccess: () => { if (selectedColorSchemeId.value === scheme.id) selectedColorSchemeId.value = PRESET_COLOR_SCHEMES[0].id; },
        onError: (errors) => { schemeError.value = errors.scheme || 'That colour scheme could not be deleted.'; },
    });
};
// While the editor is open the builder preview shows the colours being worked on.
const draftColours = computed(() => (schemeEditorOpen.value && isHexColour(schemeForm.primary) && isHexColour(schemeForm.accent)
    ? { primary: schemeForm.primary, accent: schemeForm.accent }
    : null));
const draftPalette = computed(() => paletteFromBrand(draftColours.value));
const draftColoursClose = computed(() => !!draftColours.value && contrastRatio(draftColours.value.primary, draftColours.value.accent) < 2);
const usedByLiveSite = (scheme) => currentThemeKey.value.endsWith(`:${scheme.id}`);
const SCHEME_SHADES = [
    ['primary', 'Dark sections'], ['primarySoft', 'Dark, softer'], ['night', 'Dark mode'], ['accent', 'Accent'],
    ['accentBright', 'Accent on dark'], ['accentDeep', 'Accent text'], ['cream', 'Light band'], ['ink', 'Headings'], ['muted', 'Body text'],
];

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
const isoOrNull = (value) => (value ? new Date(value).toISOString() : null);

const settingsStateOf = (page) => ({
    slug: page.slug || '',
    is_published: page.is_published ?? true,
    is_homepage: page.is_homepage ?? false,
    show_in_navigation: page.show_in_navigation ?? true,
    show_in_footer: page.show_in_footer ?? false,
    is_members_only: page.is_members_only ?? false,
    header_style: page.header_style || 'full',
    publish_at: isoOrNull(page.publish_at),
    unpublish_at: isoOrNull(page.unpublish_at),
});

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

// Compact view state (see toggleCollapsed): ids of the elements folded down to a summary.
const collapsedBlocks = ref(new Set());

// The private preview link panel (see openPreviewPanel); declared here because loadPageIntoForm resets it.
const previewUrl = ref('');
const previewOpen = ref(false);

const loadPageIntoForm = (page) => {
    collapsedBlocks.value = new Set();
    previewUrl.value = '';
    previewOpen.value = false;
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

const getPageIcon = (p) => {
    if (!p) return '📄';
    if (p.is_homepage || p.slug === 'home') return '🏠';
    if (p.slug === 'about') return 'ℹ️';
    if (p.slug === 'join-us') return '🤝';
    if (p.slug === 'news') return '📰';
    if (p.slug === 'contact') return '✉️';
    return '📄';
};

// Every screen in the builder has its own address, so it can be bookmarked, reloaded, opened in a new tab and reached
// with the back button. A page is /pages/7/edit (add ?view=preview for the preview); the rest are named screens.
const SCREEN_PATHS = { new: 'create', settings: 'settings', themes: 'themes', header_footer: 'header-footer', redirects: 'redirects', overview: 'overview' };

const selectionUrl = (selection, mode = 'edit') => {
    const base = `/${props.club.slug}/admin/pages`;
    const key = String(selection);

    if (SCREEN_PATHS[key]) return `${base}/${SCREEN_PATHS[key]}`;

    return `${base}/${key}/edit${mode === 'preview' ? '?view=preview' : ''}`;
};

/** The selection and view mode an address stands for, or null if it is not one of the builder's screens. */
const parseSelectionUrl = (url) => {
    const [path, query = ''] = String(url).split('?');
    const match = path.match(/\/admin\/pages\/([^/]+)(?:\/edit)?\/?$/);

    if (!match) return null;

    const screen = Object.entries(SCREEN_PATHS).find(([, segment]) => segment === match[1]);
    if (screen) return { selection: screen[0], mode: 'edit' };

    if (!/^\d+$/.test(match[1]) || !/\/edit\/?$/.test(path)) return null;

    return { selection: match[1], mode: new URLSearchParams(query).get('view') === 'preview' ? 'preview' : 'edit' };
};

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

// Block Element Types Palette
const blockTypes = [
    { type: 'text', icon: '📝', label: 'Text Block', desc: 'Rich text paragraph or formatted text', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'image', icon: '🖼️', label: 'Single Image', desc: 'Image with positioning & size controls', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'images', icon: '🖼️', label: 'Image Gallery', desc: 'Multi-image grid layout', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'notice', icon: '📢', label: 'Callout Box', desc: 'Highlighted notice or announcement box', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'button', icon: '🔗', label: 'Button Link', desc: 'Call to action button link', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'youtube', icon: '▶️', label: 'YouTube Video', desc: 'Click-to-play video from a YouTube link', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
    { type: 'downloads', icon: '📥', label: 'Downloads', desc: 'List of documents and forms, can be members only', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'faq', icon: '❓', label: 'FAQ', desc: 'Expandable questions and answers', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'map', icon: '🗺️', label: 'Map', desc: 'OpenStreetMap location with directions', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'cta_banner', icon: '📣', label: 'Call-to-Action Banner', desc: 'Heading, text and buttons on a banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'calendar', icon: '🗓️', label: 'Events Calendar', desc: 'Month grid or list of upcoming events', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'hero', icon: '🚀', label: 'Hero Banner', desc: 'Large title & subtitle header banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'news_feed', icon: '📰', label: 'News Items', desc: 'Pulls published articles automatically', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'events_calendar', icon: '📅', label: 'Events & Summons', desc: 'Displays upcoming events & dinners', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'pricing_cards', icon: '💳', label: 'Membership Dues', desc: 'Shows active membership plans', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'donation_campaign', icon: '💰', label: 'Dynamic Donation', desc: 'Fundraising campaign progress bar', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
    { type: 'contact_details', icon: '📇', label: 'Contact Details & Cards', desc: 'Email, meeting times & location cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'contact_form', icon: '📝', label: 'Interactive Contact Form', desc: 'Form with email notification & options', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'slideshow', icon: '🎞️', label: 'Slideshow', desc: 'Fading photos, optionally with text and buttons over them', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'section_heading', icon: '🔠', label: 'Section Heading', desc: 'Small label, serif title, divider and intro', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'feature_cards', icon: '🃏', label: 'Icon Cards', desc: 'Grid of icon, title and text cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'stats', icon: '🔢', label: 'Stats Row', desc: 'Headline figures like 150+ years', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'quote_motto', icon: '❝', label: 'Motto', desc: 'Heading, divider, text and italic tagline', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
];

const blockColumns = computed(() => [
    {
        title: 'Text & Documents',
        items: [
            { type: 'text', icon: '📝', label: 'Text Block', desc: 'Rich text paragraph or formatted text', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'notice', icon: '📢', label: 'Callout Box', desc: 'Highlighted notice or announcement box', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'faq', icon: '❓', label: 'FAQ', desc: 'Expandable questions and answers', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'downloads', icon: '📥', label: 'Downloads', desc: 'List of documents and forms, can be members only', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'button', icon: '🔗', label: 'Button Link', desc: 'Call to action button link', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
        ],
    },
    {
        title: 'Sections & Cards',
        items: [
            { type: 'section_heading', icon: '🔠', label: 'Section Heading', desc: 'Small label, serif title, divider and intro', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'feature_cards', icon: '🃏', label: 'Icon Cards', desc: 'Grid of icon, title and text cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'stats', icon: '🔢', label: 'Stats Row', desc: 'Headline figures like 150+ years', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'quote_motto', icon: '❝', label: 'Motto', desc: 'Heading, divider, text and italic tagline', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
        ],
    },
    {
        title: 'Media & Places',
        items: [
            { type: 'image', icon: '🖼️', label: 'Single Image', desc: 'Image with positioning & size controls', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'images', icon: '🖼️', label: 'Image Gallery', desc: 'Multi-image grid layout', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'youtube', icon: '▶️', label: 'YouTube Video', desc: 'Click-to-play video from a YouTube link', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
            { type: 'map', icon: '🗺️', label: 'Map', desc: 'OpenStreetMap location with directions', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
        ],
    },
    {
        title: 'Banners & Promotion',
        items: [
            { type: 'hero', icon: '🚀', label: 'Hero Banner', desc: 'Large title & subtitle header banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
            { type: 'cta_banner', icon: '📣', label: 'Call-to-Action Banner', desc: 'Heading, text and buttons on a banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
            { type: 'slideshow', icon: '🎞️', label: 'Slideshow', desc: 'Fading photos, optionally with text and buttons over them', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
            { type: 'pricing_cards', icon: '💳', label: 'Membership Dues', desc: 'Shows active membership plans', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'donation_campaign', icon: '💰', label: 'Dynamic Donation', desc: 'Fundraising campaign progress bar', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
        ],
    },
    {
        title: 'Feeds, Events & Contact',
        items: [
            { type: 'news_feed', icon: '📰', label: 'News Items', desc: 'Pulls published articles automatically', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'events_calendar', icon: '📅', label: 'Events & Summons', desc: 'Displays upcoming events & dinners', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'calendar', icon: '🗓️', label: 'Events Calendar', desc: 'Month grid or list of upcoming events', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'contact_details', icon: '📇', label: 'Contact Details & Cards', desc: 'Email, meeting times & location cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'contact_form', icon: '📝', label: 'Interactive Contact Form', desc: 'Form with email notification & options', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
        ],
    },
]);

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
    const id = 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4);
    let newBlock = null;

    if (type === 'text' || type === 'rich_text') {
        newBlock = { id, type: 'text', heading: '', content: '' };
    } else if (type === 'image') {
        newBlock = { id, type: 'image', url: '', caption: '', position: 'center', size: 'large' };
    } else if (type === 'images') {
        newBlock = {
            id,
            type: 'images',
            columns: 3,
            items: [
                { id: id + '-g1', url: '', caption: '' },
                { id: id + '-g2', url: '', caption: '' }
            ]
        };
    } else if (type === 'notice') {
        newBlock = { id, type: 'notice', style: 'info', title: 'Important Notice', text: '' };
    } else if (type === 'button') {
        newBlock = { id, type: 'button', label: 'Learn More →', url: '', align: 'center' };
    } else if (type === 'youtube') {
        newBlock = {
            id,
            type: 'youtube',
            url: '',
            title: '',
            description: '',
            layout: 'stacked',
            width: 'standard',
            aspect: '16:9',
            text_align: 'left',
            cover_url: '',
            start: null,
            end: null,
            loop: false,
            captions: false,
            show_youtube_link: true,
            button_enabled: false,
            button_label: '',
            button_url: '',
            button_new_tab: false,
        };
    } else if (type === 'cta_banner') {
        newBlock = {
            id, type: 'cta_banner', eyebrow: '', heading: 'Interested in joining us?', text: '',
            button_label: 'Find out more', button_url: `/site/${props.club.slug}/join-us`, button_new_tab: false,
            button2_label: '', button2_url: '', button2_new_tab: false,
            style: 'bold', overlay: 'medium', align: 'center', size: 'normal', image_url: '',
            side_image_url: '', image_shape: 'circle', image_side: 'left', text_style: 'normal',
        };
    } else if (type === 'faq') {
        newBlock = {
            id, type: 'faq', heading: 'Frequently asked questions', intro: '', behaviour: 'single', columns: 1,
            first_open: false, numbered: false, show_search: true,
            items: [{ id: id + '-q1', question: '', answer: '' }],
        };
    } else if (type === 'map') {
        newBlock = {
            id, type: 'map', heading: '', location_name: '', address: props.clubAddresses[0]?.address || '', notes: '',
            lat: null, lng: null, zoom: 17, map_style: 'street', allow_style_switch: true, height: 'medium', layout: 'stacked', load_mode: 'click',
            show_directions: true, show_larger_link: true,
        };
    } else if (type === 'downloads') {
        newBlock = {
            id, type: 'downloads', heading: 'Documents', intro: '', layout: 'list', sort: 'manual', open_in: 'new_tab',
            button_label: 'Download', show_type: true, show_size: true, show_date: false, show_search: false,
            members_only: false, items: [],
        };
    } else if (type === 'calendar') {
        newBlock = {
            id, type: 'calendar', heading: "What's on", default_view: 'month', week_starts: 'monday', list_length: 10,
            allow_switch: true, show_times: true, show_location: true, show_price: false, show_subscribe: true,
        };
    } else if (type === 'hero') {
        newBlock = {
            id,
            type: 'hero',
            title: 'Welcome to ' + props.club.name,
            subtitle: 'Join us for training, events and community.',
            cta_text: 'Explore Membership',
            cta_link: `/site/${props.club.slug}/join-us`,
            cta2_text: '', cta2_link: '', eyebrow: '', hide_eyebrow: false, image_url: '', overlay: 'medium', align: 'auto', height: 'normal',
        };
    } else if (type === 'slideshow') {
        newBlock = {
            id, type: 'slideshow', height: 'normal', effect: 'zoom', interval: 5, overlay: 'medium', align: 'left',
            autoplay: true, pause_on_hover: true, show_dots: true, show_arrows: true, full_width: false,
            eyebrow: '', heading: '', text: '', button_label: '', button_url: '', button2_label: '', button2_url: '',
            slides: [{ id: id + '-p1', image_url: '', alt: '', caption: '' }, { id: id + '-p2', image_url: '', alt: '', caption: '' }],
        };
    } else if (type === 'section_heading') {
        newBlock = { id, type: 'section_heading', eyebrow: 'About us', title: 'A tradition of fellowship', intro: '', align: 'center', show_divider: true };
    } else if (type === 'feature_cards') {
        newBlock = {
            id, type: 'feature_cards', eyebrow: '', heading: 'What we offer', intro: '', columns: 3, card_style: 'soft', icon_style: 'plain',
            align: 'center', show_divider: true, numbered: false,
            items: [
                { id: id + '-c1', icon: 'users', title: 'Brotherhood', text: 'A genuine sense of belonging among men from all walks of life.', link: '', link_label: '' },
                { id: id + '-c2', icon: 'heart', title: 'Charity', text: 'Active engagement in charitable events and community fundraising.', link: '', link_label: '' },
                { id: id + '-c3', icon: 'seedling', title: 'Personal growth', text: 'Opportunity to explore your potential and build confidence.', link: '', link_label: '' },
            ],
        };
    } else if (type === 'stats') {
        newBlock = {
            id, type: 'stats', heading: '', icon_style: 'circle', count_up: true,
            items: [
                { id: id + '-s1', icon: 'landmark', number: '150', suffix: '+', label: 'Years of history' },
                { id: id + '-s2', icon: 'users', number: '40', suffix: '+', label: 'Members' },
                { id: id + '-s3', icon: 'calendar-check', number: '8', suffix: '', label: 'Meetings per year' },
            ],
        };
    } else if (type === 'quote_motto') {
        newBlock = { id, type: 'quote_motto', heading: 'Our motto', text: '', tagline: '', show_divider: true };
    } else if (type === 'news_feed' || type === 'news_list') {
        newBlock = { id, type: 'news_feed', heading: 'Latest Club News', columns: '3', limit: 6 };
    } else if (type === 'events_calendar') {
        newBlock = { id, type: 'events_calendar', heading: 'Upcoming Events & Meetings', limit: 3 };
    } else if (type === 'pricing_cards') {
        newBlock = { id, type: 'pricing_cards', heading: 'Membership Options & Dues' };
    } else if (type === 'donation_campaign') {
        newBlock = { id, type: 'donation_campaign', heading: 'Active Fundraising Campaign' };
    } else if (type === 'contact_details') {
        newBlock = {
            id,
            type: 'contact_details',
            eyebrow: 'CONTACT',
            title: 'Get in Touch',
            description: `Whether you're interested in joining ${props.club.name}, a visiting member, or simply want to learn more, we'd be delighted to hear from you.`,
            email_heading: 'Email',
            email: '',
            times_heading: 'Meeting Times',
            times: props.websiteSettings?.meeting_formula || props.club.settings?.meeting_formula || '7:00 pm, 4th Thursday\nSept–Nov & Jan–May\nInstallation: April, 5:30 pm',
            location_heading: 'Location',
            location: props.websiteSettings?.address || props.club.address || 'Stockton Masonic Hall,\nWellington Street,\nStockton-on-Tees, TS18 1RD',
        };
    } else if (type === 'contact_form') {
        newBlock = {
            id,
            type: 'contact_form',
            heading: 'Send Us a Message',
            subtitle: 'Have questions or need assistance? Fill out the form below.',
            recipient_email: '',
            cc_emails: '',
            name_required: true,
            email_required: true,
            phone_required: false,
            message_required: true,
            button_text: 'Send Message',
            success_message: 'Thank you! Your message has been sent successfully.',
        };
    }

    if (newBlock) {
        const index = targetIndex !== null && targetIndex >= 0 && targetIndex <= form.blocks.length ? targetIndex : form.blocks.length;
        form.blocks.splice(index, 0, newBlock);
        revealBlock(newBlock, index);
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

const previewCopied = ref(false);
const previewError = ref('');

const openPreviewPanel = async () => {
    previewOpen.value = !previewOpen.value;
    if (!previewOpen.value || previewUrl.value || !form.id) return;
    await fetchPreviewLink(false);
};

const fetchPreviewLink = async (renew) => {
    previewError.value = '';
    const { ok, data } = await postJson(route('admin.pages.preview_link', { clubSlug: props.club.slug, id: form.id }), { renew }, 'Could not make the link. Check your connection.');
    if (ok) {
        previewUrl.value = data.url;
        previewCopied.value = false;
    } else {
        previewError.value = data.message || 'Could not make the link.';
    }
};

const renewPreviewLink = async () => {
    if (!confirm('Make a new link? The old one will stop working for anyone who has it.')) return;
    await fetchPreviewLink(true);
};

const copyPreviewLink = async () => {
    try {
        await navigator.clipboard.writeText(previewUrl.value);
        previewCopied.value = true;
        setTimeout(() => { previewCopied.value = false; }, 2500);
    } catch {
        previewError.value = 'Could not copy automatically. Select the link and copy it.';
    }
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

const copyTargets = computed(() => props.pages.filter((p) => p.id !== form.id));
const copyNotice = ref('');
const copyNoticeIsError = ref(false);

const copyBlockTo = async (block, event) => {
    const targetId = event.target.value;
    event.target.value = '';
    if (!targetId) return;

    const { ok, data } = await postJson(route('admin.pages.copy_block', { clubSlug: props.club.slug, id: targetId }), { block: JSON.parse(JSON.stringify(block)) }, 'Could not copy that element. Check your connection.');

    copyNoticeIsError.value = !ok;
    copyNotice.value = ok
        ? `Copied to "${data.title}"${data.to_draft ? ' (added to its draft)' : ''}. Open that page to see it.`
        : (data.message || data.errors?.block?.[0] || 'Could not copy that element.');
    setTimeout(() => { copyNotice.value = ''; }, 5000);

    // The page list held by this screen is now out of date for the target; refresh it so editing that page next starts from the copy.
    if (ok) router.reload({ only: ['pages'], preserveScroll: true, preserveState: true });
};

// A page's place in its life, for the labels in the editor and the page list.
const LIFECYCLE = {
    live: { label: 'LIVE', tone: 'emerald' },
    scheduled: { label: 'SCHEDULED', tone: 'amber' },
    expired: { label: 'EXPIRED', tone: 'slate' },
    off: { label: 'NOT PUBLISHED', tone: 'slate' },
};

const lifecycleText = (page) => {
    if (!page) return '';
    if (page.lifecycle_status === 'scheduled') return `Goes live ${formatLocal(page.publish_at)}`;
    if (page.lifecycle_status === 'expired') return `Came down ${formatLocal(page.unpublish_at)}`;
    if (page.lifecycle_status === 'live' && page.unpublish_at && !page.is_homepage) return `Comes down ${formatLocal(page.unpublish_at)}`;
    return '';
};

const publishAtLocal = computed({
    get: () => toLocalInput(form.publish_at),
    set: (value) => { form.publish_at = fromLocalInput(value); },
});
const unpublishAtLocal = computed({
    get: () => toLocalInput(form.unpublish_at),
    set: (value) => { form.unpublish_at = fromLocalInput(value); },
});

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

const togglePublishPage = (p) => {
    if (p.is_homepage || p.slug === 'home') return;
    router.post(`/${props.club.slug}/admin/pages/${p.id}/toggle-publish`, {}, { preserveScroll: true });
};

const duplicatePage = (p) => {
    router.post(`/${props.club.slug}/admin/pages/${p.id}/duplicate`, {}, { preserveScroll: true });
};

const restorePage = (p) => {
    router.post(`/${props.club.slug}/admin/pages/trash/${p.id}/restore`, {}, { preserveScroll: true });
};

const forceDeletePage = (p) => {
    if (!confirm(`Permanently delete "${p.title}"? This cannot be undone.`)) return;
    router.delete(`/${props.club.slug}/admin/pages/trash/${p.id}`, { preserveScroll: true });
};

const movePageUp = (index) => {
    if (index <= 1) return;
    const newPages = [...props.pages];
    const temp = newPages[index];
    newPages[index] = newPages[index - 1];
    newPages[index - 1] = temp;

    const orderIds = newPages.map(p => p.id);
    router.post(`/${props.club.slug}/admin/pages/reorder`, { order: orderIds }, { preserveScroll: true });
};

const movePageDown = (index) => {
    if (index === 0 || index >= props.pages.length - 1) return;
    const newPages = [...props.pages];
    const temp = newPages[index];
    newPages[index] = newPages[index + 1];
    newPages[index + 1] = temp;

    const orderIds = newPages.map(p => p.id);
    router.post(`/${props.club.slug}/admin/pages/reorder`, { order: orderIds }, { preserveScroll: true });
};

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
    <AdminLayout title="Website Builder" :club="club" active-tab="pages">
        <Head :title="`Website Builder - ${club.name}`" />

        <div class="space-y-6 max-w-7xl mx-auto">
            
            <!-- Top Action Bar & Header Card -->
            <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">Website Builder & Navigation</h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                            {{ club.slug }}
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">Manage public site pages, customize layout blocks, preview rendered pages, and arrange navigation links.</p>
                </div>

                <div class="flex items-center gap-2.5 flex-wrap self-start sm:self-auto">
                    <a :href="`/site/${club.slug}`" target="_blank" class="py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-all flex items-center gap-2">
                        <span>🌐 View Live Site</span>
                    </a>
                </div>
            </div>

            <!-- 2-Column Responsive Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
                
                <!-- Left Sidebar Navigation Panel -->
                <div class="lg:col-span-1 space-y-4">
                    
                    <!-- Mobile Page Selector Dropdown -->
                    <div class="lg:hidden bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-2">
                        <label for="mobile-page-selector" class="block text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Website Navigation Page</label>
                        <select id="mobile-page-selector" :value="activeNavSelection" @change="e => requestNavigation(e.target.value)" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                            <optgroup label="Public Website Pages">
                                <option v-for="p in pages" :key="p.id" :value="String(p.id)">
                                    {{ getPageIcon(p) }} {{ p.title }} {{ p.is_homepage ? '(Homepage)' : '' }}
                                </option>
                            </optgroup>
                            <optgroup label="Management">
                                <option value="settings">⚙️ Website & SEO Settings</option>
                                <option value="header_footer">🧭 Header & Footer</option>
                                <option value="themes">🎨 Website Themes</option>
                                <option value="redirects">↪️ Redirects</option>
                                <option value="new">➕ Add Custom New Page</option>
                                <option value="overview">📋 Manage Pages</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- Desktop Vertical Sidebar Navigation Card -->
                    <div class="hidden lg:block bg-white dark:bg-slate-900 p-3.5 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-5 sticky top-6">
                        
                        <!-- Navigation Pages Group -->
                        <div>
                            <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5 flex items-center justify-between">
                                <span>Navigation Pages</span>
                                <span class="bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded text-[9px] font-bold">{{ pages.length }} Pages</span>
                            </div>

                            <div class="space-y-1 text-xs font-bold">
                                <a
                                    v-for="p in pages"
                                    :key="p.id"
                                    :href="selectionUrl(p.id, pageViewMode)"
                                    :aria-current="activeNavSelection === String(p.id) ? 'page' : undefined"
                                    @click="navClick($event, p.id, pageViewMode || 'edit')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left group font-bold',
                                        activeNavSelection === String(p.id) ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span class="text-base leading-none">{{ getPageIcon(p) }}</span>
                                    <span class="truncate font-bold">{{ p.title }}</span>
                                </a>
                            </div>

                            <!-- Add New Page Quick Action -->
                            <a
                                :href="selectionUrl('new', 'edit')"
                                @click="navClick($event, 'new', 'edit')"
                                :class="[
                                    'w-full mt-3 px-3.5 py-2.5 rounded-xl transition-all duration-200 text-xs font-black flex items-center justify-center gap-2 cursor-pointer shadow-sm',
                                    activeNavSelection === 'new'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 ring-2 ring-blue-400/50'
                                        : 'bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white hover:shadow-md hover:scale-[1.01]'
                                ]"
                            >
                                <span class="text-sm leading-none">➕</span>
                                <span>Add New Custom Page</span>
                            </a>
                        </div>

                        <!-- Management & Tools Group -->
                        <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-1">
                            <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Management</div>
                            <div class="space-y-1 text-xs font-bold">
                                <a
                                    :href="selectionUrl('settings')"
                                    :aria-current="activeNavSelection === 'settings' ? 'page' : undefined"
                                    @click="navClick($event, 'settings')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'settings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>⚙️</span> Website & SEO Settings
                                </a>

                                <a
                                    :href="selectionUrl('header_footer')"
                                    :aria-current="activeNavSelection === 'header_footer' ? 'page' : undefined"
                                    @click="navClick($event, 'header_footer')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'header_footer' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>🧭</span> Header & Footer
                                </a>

                                <a
                                    :href="selectionUrl('themes')"
                                    :aria-current="activeNavSelection === 'themes' ? 'page' : undefined"
                                    @click="navClick($event, 'themes')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'themes' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>🎨</span> Website Themes
                                </a>

                                <a
                                    :href="selectionUrl('redirects')"
                                    :aria-current="activeNavSelection === 'redirects' ? 'page' : undefined"
                                    @click="navClick($event, 'redirects')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'redirects' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>↪️</span> Redirects
                                </a>

                                <a
                                    :href="selectionUrl('overview')"
                                    :aria-current="activeNavSelection === 'overview' ? 'page' : undefined"
                                    @click="navClick($event, 'overview')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'overview' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>📋</span> Manage Pages
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Right Main Content Panel -->
                <div class="lg:col-span-3 space-y-6">

                    <!-- View Mode Container (Edit / Live Preview Toggle) -->
                    <div v-if="!MANAGEMENT_SELECTIONS.includes(activeNavSelection)" class="space-y-6">

                        <!-- View Mode Selector Header Card -->
                        <div class="bg-white dark:bg-slate-900 px-6 py-4 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm flex items-center justify-between gap-4 flex-wrap">
                            <div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ form.id ? form.title : 'New Page' }}
                                </h2>
                                <span class="text-xs text-slate-400 font-mono">/site/{{ club.slug }}/{{ form.slug }}</span>
                            </div>

                            <!-- Segmented Pill Control: Edit vs Live Preview -->
                            <div class="inline-flex p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-inner">
                                <a
                                    :href="selectionUrl(activeNavSelection, 'edit')"
                                    :aria-current="pageViewMode === 'edit' ? 'true' : undefined"
                                    @click="navClick($event, activeNavSelection, 'edit')"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'edit' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-md shadow-slate-200' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>✏️ Edit Builder</span>
                                </a>

                                <a
                                    :href="selectionUrl(activeNavSelection, 'preview')"
                                    :aria-current="pageViewMode === 'preview' ? 'true' : undefined"
                                    @click="navClick($event, activeNavSelection, 'preview')"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'preview' ? 'bg-amber-400 text-amber-950 shadow-md shadow-amber-500/20' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>👁️ Live Preview</span>
                                </a>
                            </div>
                        </div>

                        <!-- SUB-VIEW 1: EDIT BUILDER VIEW -->
                        <div v-if="pageViewMode === 'edit'" class="space-y-6">

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
                                                    <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved!' : 'Save Page') }}</span>
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
                                                    <button v-if="form.share_image" type="button" @click="form.share_image = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                                    <button type="button" @click="openMediaLibrary('page_share_image', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media Library</button>
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
                                                        'mr-1 px-2.5 py-1 rounded-lg text-[11px] font-bold flex items-center gap-1.5 cursor-pointer border transition-colors disabled:opacity-60 disabled:cursor-wait',
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
                                                                    class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer"
                                                                >
                                                                    🗑️ Clear
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    @click="openMediaLibrary('block_image', block, 'pages')"
                                                                    class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer"
                                                                >
                                                                    📁 Media Library
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
                                                        ➕ Add Image Item
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
                                                                    class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer"
                                                                >
                                                                    📁 Media Library
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
                                                            <button v-if="block.cover_url" type="button" @click="block.cover_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">
                                                                🗑️ Clear
                                                            </button>
                                                            <button type="button" @click="openMediaLibrary('video_cover', block, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">
                                                                📁 Media Library
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
                                                                        ⚡ Use Organization Default (Dynamic)
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
                                        ➕ Add First Text Block
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
                                    🗑️ Delete Custom Page
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
                                    <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved Successfully!' : '💾 Save & Publish Page') }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- SUB-VIEW 2: LIVE PREVIEW VIEW -->
                        <div v-else-if="pageViewMode === 'preview'" class="space-y-4">
                            <!-- Theme Preview Control Toolbar -->
                            <div class="bg-slate-900 dark:bg-slate-700 text-white p-4 sm:px-6 rounded-3xl border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-md">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span id="theme-preview-select-label" class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                        <span>🎨 Previewing:</span>
                                    </span>
                                    <select
                                        :value="selectedLayoutId"
                                        @change="e => selectedLayoutId = e.target.value"
                                        aria-labelledby="theme-preview-select-label"
                                        class="px-3.5 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                                    >
                                        <option :value="LEGACY.id">{{ LEGACY.name }}</option>
                                        <option v-for="l in LAYOUTS" :key="l.id" :value="l.id">{{ l.name }}</option>
                                    </select>
                                    <select
                                        v-if="selectedLayoutId !== LEGACY.id"
                                        :value="selectedColorSchemeId"
                                        @change="e => selectedColorSchemeId = e.target.value"
                                        aria-label="Colour scheme"
                                        class="px-3.5 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                                    >
                                        <option v-for="c in COLOR_SCHEMES" :key="c.id" :value="c.id">{{ c.name }}</option>
                                    </select>
                                    <span v-if="effectivePreviewThemeKey === currentThemeKey" class="text-[10px] font-bold text-emerald-400">(Active Live)</span>
                                </div>

                                <div class="flex items-center gap-2 shrink-0 flex-nowrap">
                                    <button
                                        v-if="effectivePreviewThemeKey !== currentThemeKey"
                                        type="button"
                                        @click="applyPreviewTheme()"
                                        class="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-blue-600 hover:from-emerald-600 hover:to-blue-700 text-white font-bold text-xs shadow-md transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap shrink-0"
                                    >
                                        <span>✨ Apply This Theme</span>
                                    </button>
                                    <a
                                        :href="`/site/${club.slug}?preview_theme=${effectivePreviewThemeKey}`"
                                        target="_blank"
                                        class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs border border-slate-700 transition-colors flex items-center gap-1.5 whitespace-nowrap shrink-0"
                                    >
                                        <span>🌐 Open Full Site ↗</span>
                                    </a>
                                </div>
                            </div>

                            <!-- Preview Canvas Frame -->
                            <div :class="['font-sans rounded-3xl border overflow-hidden relative transition-colors duration-300', previewThemeClasses.container]" :style="previewThemeClasses.cssVars">

                                <!-- Website Header Preview (same component the real site renders) -->
                                <div class="[&>header]:!static [&>header]:!z-auto">
                                    <PublicHeader
                                        v-if="form.header_style !== 'hidden'"
                                        :mode="form.header_style"
                                        :club="club"
                                        :theme="previewThemeClasses"
                                        :navigation="publishedNavPages"
                                        :current-page="{ slug: form.slug, is_homepage: form.is_homepage }"
                                        :settings="websiteSettings"
                                        :interactive="false"
                                    />
                                </div>

                                <BlockRenderer
                                    :blocks="form.blocks"
                                    :theme="previewThemeClasses"
                                    :club="club"
                                    :latest-posts="latestPosts"
                                    :upcoming-events="upcomingEvents"
                                    :membership-plans="membershipPlans"
                                    :donations="donations"
                                    :calendar="calendar"
                                    :interactive="false"
                                />

                                <!-- Website Footer Preview (same component the real site renders) -->
                                <PublicFooter
                                    :club="club"
                                    :theme="previewThemeClasses"
                                    :navigation="publishedNavPages"
                                    :footer-links="footerPinnedPages"
                                    :settings="websiteSettings"
                                    :interactive="false"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- View Mode: Global Website & SEO Settings -->
                    <div v-else-if="activeNavSelection === 'settings'" class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">🌐 Global Website & SEO Settings</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Manage custom domain, global search engine optimization, and public contact details.</p>
                            </div>
                            <button
                                type="button"
                                @click="submitWebsiteSettings"
                                :disabled="settingsForm.processing"
                                class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                            >
                                <span>💾 Save Website Settings</span>
                            </button>
                        </div>

                        <!-- Saved Success Alert -->
                        <div v-if="isSavedSuccess" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2">
                            <span>✅ Website settings saved successfully!</span>
                        </div>

                        <form @submit.prevent="submitWebsiteSettings" class="space-y-6 text-xs">
                            
                            <!-- Section 1: Custom Domain Setup -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Custom Domain Setup</h3>
                                <div class="p-5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-4">
                                    <div>
                                        <label for="custom-domain" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Custom Domain Name</label>
                                        <input id="custom-domain" v-model="settingsForm.custom_domain" type="text" :placeholder="`e.g. ${domainExample}`" class="w-full sm:w-96 px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Connect your custom domain (e.g. <code class="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100 px-1 py-0.5 rounded font-bold">{{ domainExample }}</code>) to your club portal.</p>
                                    </div>

                                    <DomainSetupGuide
                                        v-if="settingsForm.custom_domain"
                                        :domain="settingsForm.custom_domain"
                                        :status="websiteSettings.domain_status"
                                        :verified-at="websiteSettings.domain_verified_at"
                                        :instructions="websiteSettings.domain_instructions"
                                        :checking="checkingDomain"
                                        @check="checkDomainNow"
                                    />
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Section 2: Search Engine Optimization (SEO) -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Search Engine Optimization (SEO)</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="seo-title-suffix" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Page Title Suffix</label>
                                        <input id="seo-title-suffix" v-model="settingsForm.seo_title_suffix" type="text" placeholder="| The Lodge of Fraternity" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p class="text-[10px] text-slate-400 mt-1">Appended to page titles in browser tabs and search engines.</p>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label for="seo-meta-description" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Default Meta Description</label>
                                        <textarea id="seo-meta-description" v-model="settingsForm.seo_meta_description" rows="3" placeholder="Official homepage for events, membership, news, and history." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-medium text-slate-800 dark:text-slate-100"></textarea>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="site-share-image" class="block font-bold text-slate-700 dark:text-slate-200 mb-1 !mb-0">Default share image</label>
                                            <div class="flex items-center gap-1.5">
                                                <button v-if="settingsForm.share_image_url" type="button" @click="settingsForm.share_image_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                                <button type="button" @click="openMediaLibrary('site_share_image', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media Library</button>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <input id="site-share-image" v-model="settingsForm.share_image_url" type="text" placeholder="https://… or choose from the media library" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                                            <img v-if="settingsForm.share_image_url" :src="settingsForm.share_image_url" alt="" class="w-16 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-1">The picture shown when any page is shared on Facebook, WhatsApp or Messages, unless the page has its own. About 1200 × 630 works best. Falls back to the club logo.</p>
                                        <p v-if="settingsForm.errors.share_image_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.share_image_url }}</p>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <label for="site-icon" class="block font-bold text-slate-700 dark:text-slate-200 mb-1 !mb-0">Site icon (favicon)</label>
                                            <div class="flex items-center gap-1.5">
                                                <button v-if="settingsForm.site_icon_url" type="button" @click="settingsForm.site_icon_url = ''" class="px-2 py-0.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 text-[10px] font-bold rounded-lg border border-rose-200 dark:border-rose-800/60 cursor-pointer">🗑️ Clear</button>
                                                <button type="button" @click="openMediaLibrary('site_icon', null, 'pages')" class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-[10px] font-bold rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">📁 Media Library</button>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <input id="site-icon" v-model="settingsForm.site_icon_url" type="text" placeholder="https://… or choose from the media library" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                                            <img v-if="settingsForm.site_icon_url" :src="settingsForm.site_icon_url" alt="" class="w-12 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 dark:bg-slate-800" />
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-1">The small picture in the browser tab and on a phone home screen. A square image at least 180 × 180 works best.</p>
                                        <p v-if="settingsForm.errors.site_icon_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.site_icon_url }}</p>
                                    </div>

                                </div>

                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" v-model="settingsForm.noindex_site" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                                    <span>
                                        <span class="font-bold text-slate-700 dark:text-slate-200">Hide the whole website from search engines</span>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">Useful while the site is being built. Visitors can still open it. Your pages are listed for search engines automatically at <span class="font-mono">/site/{{ club.slug }}/sitemap.xml</span> unless this is on.</span>
                                    </span>
                                </label>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Section 3: Public Contact Details -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Public Contact Details</h3>
                                <p class="text-[10px] text-slate-400 -mt-2">Header CTA, social links and footer branding have moved to the <button type="button" @click="requestNavigation('header_footer')" class="underline font-bold text-blue-600 dark:text-blue-400 cursor-pointer">Header & Footer</button> tab.</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="public-contact-email" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Public Contact Email</label>
                                        <input id="public-contact-email" v-model="settingsForm.contact_email" type="email" placeholder="admin@club.org" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>

                                    <div>
                                        <label for="public-phone" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Public Phone Number</label>
                                        <input id="public-phone" v-model="settingsForm.phone" type="text" placeholder="+44 20 7946 0912" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                </div>
                            </div>


                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Section 4: Visitor statistics & cookies -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Visitor Statistics &amp; Cookies</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="analytics-provider" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Analytics service</label>
                                        <select id="analytics-provider" v-model="settingsForm.analytics_provider" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                                            <option value="none">None</option>
                                            <option value="plausible">Plausible (no cookies)</option>
                                            <option value="google">Google Analytics 4</option>
                                        </select>
                                        <p class="text-[10px] text-slate-400 mt-1">Plausible counts visits without cookies and needs no consent. Google Analytics uses cookies, so visitors are asked first and nothing loads until they accept.</p>
                                        <p v-if="settingsForm.errors.analytics_provider" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.analytics_provider }}</p>
                                    </div>
                                    <div v-if="settingsForm.analytics_provider !== 'none'">
                                        <label for="analytics-id" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">{{ settingsForm.analytics_provider === 'google' ? 'Measurement ID' : 'Site domain' }}</label>
                                        <input id="analytics-id" v-model="settingsForm.analytics_id" type="text" :placeholder="settingsForm.analytics_provider === 'google' ? 'G-ABC123XYZ' : 'yourlodge.org.uk'" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono" />
                                        <p v-if="settingsForm.errors.analytics_id" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.analytics_id }}</p>
                                    </div>
                                </div>

                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" v-model="settingsForm.cookie_banner_enabled" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                                    <span>
                                        <span class="font-bold text-slate-700 dark:text-slate-200">Show a cookie notice</span>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">Always shown when Google Analytics is chosen. Turn it on for other services too if you want to tell visitors how their visit is counted.</span>
                                    </span>
                                </label>

                                <div v-if="settingsForm.cookie_banner_enabled || settingsForm.analytics_provider === 'google'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label for="cookie-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Notice wording <span class="font-normal text-slate-400">(optional)</span></label>
                                        <textarea id="cookie-text" v-model="settingsForm.cookie_banner_text" rows="2" maxlength="500" placeholder="We use cookies to count visits so we can improve this website." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    </div>
                                    <div>
                                        <label for="cookie-link-label" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link text <span class="font-normal text-slate-400">(optional)</span></label>
                                        <input id="cookie-link-label" v-model="settingsForm.cookie_banner_link_label" type="text" maxlength="60" placeholder="Privacy policy" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label for="cookie-link-url" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link address <span class="font-normal text-slate-400">(optional)</span></label>
                                        <input id="cookie-link-url" v-model="settingsForm.cookie_banner_link_url" type="text" placeholder="/site/{{ club.slug }}/privacy" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                                        <p v-if="settingsForm.errors.cookie_banner_link_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.cookie_banner_link_url }}</p>
                                    </div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Section 5: Page not found -->
                            <div class="space-y-3">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Page Not Found</h3>
                                <div class="sm:max-w-md">
                                    <label for="not-found-page" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Page to show for a missing address</label>
                                    <select id="not-found-page" v-model="settingsForm.not_found_page_id" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-bold">
                                        <option value="">The standard "not found" screen</option>
                                        <option v-for="p in pages.filter(pg => pg.is_published && !pg.is_members_only)" :key="p.id" :value="p.id">{{ p.title }}</option>
                                    </select>
                                    <p class="text-[10px] text-slate-400 mt-1">Build a friendly page (a message and a few links) and pick it here. Visitors still get a proper "not found" status, so search engines don't index the missing address.</p>
                                    <p v-if="settingsForm.errors.not_found_page_id" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.not_found_page_id }}</p>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Section 6: Page history -->
                            <div class="space-y-3">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Page History</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">Every time a page is published or saved, the website builder keeps the earlier version so you can go back to it. To stop this filling up, older versions are deleted automatically once a page has more than the number below, or a version gets too old. The newest version of every page is always kept.</p>
                                <div class="grid grid-cols-1 gap-4 sm:max-w-2xl sm:grid-cols-2">
                                    <div>
                                        <label for="revisions-keep" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Versions kept per page</label>
                                        <input id="revisions-keep" v-model.number="settingsForm.revisions_keep" type="number" min="5" max="50" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold" />
                                        <p class="text-[10px] text-slate-400 mt-1">Between 5 and 50. Fifteen is plenty for most lodges.</p>
                                        <p v-if="settingsForm.errors.revisions_keep" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.revisions_keep }}</p>
                                    </div>
                                    <div>
                                        <label for="revisions-age" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Delete versions older than</label>
                                        <select id="revisions-age" v-model.number="settingsForm.revisions_max_age_days" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold">
                                            <option :value="30">1 month</option>
                                            <option :value="90">3 months</option>
                                            <option :value="180">6 months</option>
                                            <option :value="365">1 year</option>
                                            <option :value="0">Never (only the number above applies)</option>
                                        </select>
                                        <p v-if="settingsForm.errors.revisions_max_age_days" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ settingsForm.errors.revisions_max_age_days }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="settingsForm.processing"
                                    class="py-3 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                                >
                                    <span>💾 Save Website Settings</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- View Mode: Header & Footer Layout -->
                    <div v-else-if="activeNavSelection === 'header_footer'" class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 flex-wrap gap-3">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900 dark:text-white">🧭 Header & Footer</h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Choose a layout and toggle what appears in your public site's header and footer. Colours follow whichever theme is active.</p>
                            </div>
                            <button
                                type="button"
                                @click="submitHeaderFooter"
                                :disabled="headerFooterForm.processing"
                                class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                            >
                                <span>💾 Save Header & Footer</span>
                            </button>
                        </div>

                        <!-- Saved Success Alert -->
                        <div v-if="isSavedSuccess" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold flex items-center gap-2">
                            <span>✅ Header & footer settings saved successfully!</span>
                        </div>

                        <form @submit.prevent="submitHeaderFooter" class="space-y-12 text-xs">

                            <!-- Announcement bar -->
                            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200">Announcement Bar</h3>
                                <label class="flex items-start gap-2.5 cursor-pointer select-none">
                                    <input type="checkbox" v-model="headerFooterForm.announcement_enabled" class="w-4 h-4 mt-0.5 rounded accent-blue-600 cursor-pointer" />
                                    <span>
                                        <span class="font-bold text-slate-700 dark:text-slate-200">Show a bar across the top of every page</span>
                                        <span class="block text-[10px] text-slate-400 mt-0.5">For a date or notice everyone should see, such as "Installation on 12 April". You can give it a first and last day and it comes down by itself.</span>
                                    </span>
                                </label>

                                <div v-if="headerFooterForm.announcement_enabled" class="space-y-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                                    <div :class="['rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700', !announcementPreview ? 'p-3 text-center text-slate-400 italic bg-white dark:bg-slate-900' : '']">
                                        <AnnouncementBar v-if="announcementPreview" :announcement="announcementPreview" club-slug="preview" :interactive="false" />
                                        <span v-else>Type your message to see the bar</span>
                                    </div>

                                    <div>
                                        <label for="announcement-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Message</label>
                                        <input id="announcement-text" v-model="headerFooterForm.announcement_text" type="text" maxlength="200" placeholder="Installation on 12 April at 6pm" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p class="text-[10px] text-slate-400 mt-1">{{ headerFooterForm.announcement_text.length }}/200 characters.</p>
                                        <p v-if="headerFooterForm.errors.announcement_text" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_text }}</p>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="announcement-link-label" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link text <span class="font-normal text-slate-400">(optional)</span></label>
                                            <input id="announcement-link-label" v-model="headerFooterForm.announcement_link_label" type="text" maxlength="60" placeholder="Book your place" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        </div>
                                        <div>
                                            <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link address <span class="font-normal text-slate-400">(optional)</span></span>
                                            <LinkField v-model="headerFooterForm.announcement_link_url" :pages="pages" :club="club" label="Announcement link" />
                                            <p v-if="headerFooterForm.errors.announcement_link_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_link_url }}</p>
                                        </div>
                                        <div>
                                            <label for="announcement-starts" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Show from <span class="font-normal text-slate-400">(optional)</span></label>
                                            <input id="announcement-starts" v-model="headerFooterForm.announcement_starts_on" type="date" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        </div>
                                        <div>
                                            <label for="announcement-ends" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Show until, including this day <span class="font-normal text-slate-400">(optional)</span></label>
                                            <input id="announcement-ends" v-model="headerFooterForm.announcement_ends_on" type="date" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <p v-if="headerFooterForm.errors.announcement_ends_on" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.announcement_ends_on }}</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                                        <div class="flex items-center gap-2" role="group" aria-label="Bar colour">
                                            <span class="font-bold text-slate-700 dark:text-slate-200">Colour</span>
                                            <button v-for="opt in ANNOUNCEMENT_STYLES" :key="opt.id" type="button" @click="headerFooterForm.announcement_style = opt.id" :aria-pressed="headerFooterForm.announcement_style === opt.id" :class="['px-3 py-1.5 rounded-lg border font-bold cursor-pointer', headerFooterForm.announcement_style === opt.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300']">{{ opt.name }}</button>
                                        </div>
                                        <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                            <input type="checkbox" v-model="headerFooterForm.announcement_dismissible" class="w-4 h-4 rounded accent-blue-600 cursor-pointer" />
                                            <span>Visitors can close it</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Header Section -->
                            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200">Header</h3>

                                <!-- Live preview -->
                                <div :class="['rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden [&>header]:!static [&>header]:!z-auto', liveThemeClasses.wrapper]" :style="liveThemeClasses.cssVars">
                                    <PublicHeader
                                        :club="club"
                                        :theme="liveThemeClasses"
                                        :navigation="publishedNavPages"
                                        :current-page="{ is_homepage: true }"
                                        :settings="headerFooterForm.data()"
                                        :interactive="false"
                                    />
                                </div>

                                <!-- Layout picker -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <button
                                        v-for="layout in HEADER_LAYOUTS"
                                        :key="layout.id"
                                        type="button"
                                        @click="headerFooterForm.header_layout = layout.id"
                                        :class="[
                                            'text-left p-4 rounded-2xl border transition-all cursor-pointer',
                                            headerFooterForm.header_layout === layout.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-bold text-slate-900 dark:text-white">{{ layout.name }}</span>
                                            <span v-if="headerFooterForm.header_layout === layout.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓ SELECTED</span>
                                        </div>
                                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                                    </button>
                                </div>

                                <!-- Visibility toggles -->
                                <div class="flex flex-wrap items-center gap-6 pt-1">
                                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.header_show_logo" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Logo</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.header_show_tagline" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Tagline</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.header_show_account_links" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Login / Member Area button</span>
                                    </label>
                                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.header_cta_enabled" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Call-to-Action Button</span>
                                    </label>
                                </div>

                                <div v-if="headerFooterForm.header_cta_enabled" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                                    <div>
                                        <label for="header-cta-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Text</label>
                                        <input id="header-cta-text" v-model="headerFooterForm.header_cta_text" type="text" placeholder="e.g. Join Our Club" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Link</span>
                                        <LinkField v-model="headerFooterForm.header_cta_link" :pages="pages" :club="club" label="Header button" />
                                        <p v-if="headerFooterForm.errors.header_cta_link" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.header_cta_link }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer Section -->
                            <div class="space-y-5 -mx-6 sm:-mx-8 px-3 sm:px-4 pb-2">
                                <h3 class="-mx-3 sm:-mx-4 px-3 sm:px-4 py-3 border-b-2 text-sm font-extrabold bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200">Footer</h3>

                                <!-- Live preview -->
                                <div :class="['rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden', liveThemeClasses.wrapper]" :style="liveThemeClasses.cssVars">
                                    <PublicFooter
                                        :club="club"
                                        :theme="liveThemeClasses"
                                        :navigation="publishedNavPages"
                                        :footer-links="footerPinnedPages"
                                        :settings="headerFooterForm.data()"
                                        :interactive="false"
                                    />
                                </div>

                                <!-- Layout picker -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <button
                                        v-for="layout in FOOTER_LAYOUTS"
                                        :key="layout.id"
                                        type="button"
                                        @click="headerFooterForm.footer_layout = layout.id"
                                        :class="[
                                            'text-left p-4 rounded-2xl border transition-all cursor-pointer',
                                            headerFooterForm.footer_layout === layout.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
                                        ]"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-bold text-slate-900 dark:text-white">{{ layout.name }}</span>
                                            <span v-if="headerFooterForm.footer_layout === layout.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓ SELECTED</span>
                                        </div>
                                        <p class="text-slate-500 dark:text-slate-400 mt-1">{{ layout.description }}</p>
                                    </button>
                                </div>

                                <!-- Text under the club name, on the left of the footer -->
                                <div class="space-y-2 min-w-0">
                                    <label for="footer-about-text" class="block font-bold text-slate-700 dark:text-slate-200">Text under the club name</label>
                                    <textarea id="footer-about-text" v-model="headerFooterForm.footer_about_text" rows="3" maxlength="300" placeholder="e.g. Meeting at Stockton Masonic Hall, 4th Thursday, September to May." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                                    <p class="text-[10px] text-slate-400">{{ headerFooterForm.footer_about_text.length }}/300. Shown under your club's name at the left of the footer. Leave blank to show your club's tagline instead.</p>
                                </div>

                                <!-- Navigation links (Columns layout only): a column of the site's menu pages -->
                                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                                    <div class="flex items-center justify-between gap-2 h-8">
                                        <label class="block font-bold text-slate-700 dark:text-slate-200">Navigation Links</label>
                                        <div class="flex items-center gap-3">
                                            <button v-if="headerFooterForm.footer_show_nav" type="button" @click="showNavChooser = true" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] transition-colors cursor-pointer">Choose Pages</button>
                                            <ToggleSwitch v-model="headerFooterForm.footer_show_nav" label="Show navigation links in the footer" />
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400">A "Navigate" column listing pages from your top menu. Use Choose Pages to pick which ones. To add a page that isn't in the top menu, tick "Show in Footer Menu" on that page instead.</p>
                                    <div v-if="headerFooterForm.footer_show_nav" class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ headerFooterForm.footer_nav_page_ids === null ? `All ${publishedNavPages.length} menu pages` : `${footerNavPages.length} of ${publishedNavPages.length} menu pages` }}</p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <span v-for="navPage in footerNavPages" :key="navPage.id" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300">{{ navPage.title }}</span>
                                            <span v-if="!footerNavPages.length" class="text-slate-400 italic">{{ publishedNavPages.length ? 'No pages chosen, so the column will be empty.' : 'No pages are in the top menu yet.' }}</span>
                                        </div>
                                    </div>
                                    <p v-else class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't list your pages.</p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                <div :class="footerDetailsSideBySide ? '' : 'sm:col-span-2'" class="space-y-3 min-w-0">
                                    <div class="flex items-center justify-between gap-2 h-8">
                                        <label class="block font-bold text-slate-700 dark:text-slate-200">Social Links</label>
                                        <ToggleSwitch v-model="headerFooterForm.footer_show_social" label="Show social links in the footer" />
                                    </div>
                                    <p class="text-[10px] text-slate-400 sm:min-h-[3.25rem]">Where visitors can follow you, such as your Facebook page or YouTube channel. They show in the footer under "Follow". Leave a box empty to hide that link.</p>

                                    <p v-if="!headerFooterForm.footer_show_social" class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't show social links. Your addresses are kept.</p>
                                    <div v-else :class="footerDetailsSideBySide ? '' : 'sm:grid-cols-3'" class="grid grid-cols-1 gap-3 p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                                    <div>
                                        <label for="social-facebook" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Facebook</label>
                                        <input id="social-facebook" v-model="headerFooterForm.social_facebook" type="text" placeholder="https://facebook.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label for="social-instagram" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Instagram</label>
                                        <input id="social-instagram" v-model="headerFooterForm.social_instagram" type="text" placeholder="https://instagram.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label for="social-twitter" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">X (Twitter)</label>
                                        <input id="social-twitter" v-model="headerFooterForm.social_twitter" type="text" placeholder="https://x.com/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p v-if="headerFooterForm.errors.social_twitter" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_twitter }}</p>
                                    </div>
                                    <div>
                                        <label for="social-youtube" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">YouTube</label>
                                        <input id="social-youtube" v-model="headerFooterForm.social_youtube" type="text" placeholder="https://youtube.com/@yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p v-if="headerFooterForm.errors.social_youtube" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_youtube }}</p>
                                    </div>
                                    <div>
                                        <label for="social-linkedin" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">LinkedIn</label>
                                        <input id="social-linkedin" v-model="headerFooterForm.social_linkedin" type="text" placeholder="https://linkedin.com/company/yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p v-if="headerFooterForm.errors.social_linkedin" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_linkedin }}</p>
                                    </div>
                                    <div>
                                        <label for="social-tiktok" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">TikTok</label>
                                        <input id="social-tiktok" v-model="headerFooterForm.social_tiktok" type="text" placeholder="https://tiktok.com/@yourclub" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p v-if="headerFooterForm.errors.social_tiktok" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_tiktok }}</p>
                                    </div>
                                    <div>
                                        <label for="social-whatsapp" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">WhatsApp</label>
                                        <input id="social-whatsapp" v-model="headerFooterForm.social_whatsapp" type="text" placeholder="https://chat.whatsapp.com/…" class="w-full min-w-0 px-3 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                        <p v-if="headerFooterForm.errors.social_whatsapp" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ headerFooterForm.errors.social_whatsapp }}</p>
                                    </div>
                                </div>
                                </div>

                                <!-- Custom footer link columns (Columns layout only) -->
                                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3 min-w-0">
                                    <div class="flex items-center justify-between gap-2 h-8">
                                        <label class="block font-bold text-slate-700 dark:text-slate-200">Custom Link Columns</label>
                                        <div class="flex items-center gap-3">
                                        <!-- One column is all a lodge normally needs, so the button only shows when there is none (after deleting it) -->
                                        <button
                                            v-if="headerFooterForm.footer_show_custom_columns && !headerFooterForm.footer_link_columns.length"
                                            type="button"
                                            @click="addFooterColumn"
                                            :disabled="headerFooterForm.footer_link_columns.length >= MAX_FOOTER_COLUMNS"
                                            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            ➕ Add Column
                                        </button>
                                        <ToggleSwitch v-model="headerFooterForm.footer_show_custom_columns" label="Show custom link columns in the footer" />
                                        </div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 sm:min-h-[3.25rem]">Add extra footer columns for things like "Useful Links" — e.g. your Grand Lodge, Province, or a Data Protection Notice.</p>

                                    <p v-if="!headerFooterForm.footer_show_custom_columns" class="p-3 border border-dashed border-slate-200 dark:border-slate-800 rounded-2xl text-slate-400 text-center">Hidden. The footer won't show these links. Your columns are kept.</p>
                                    <div v-else-if="!headerFooterForm.footer_link_columns.length" class="p-4 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 text-slate-400 text-center">
                                        No custom columns yet.
                                    </div>

                                    <!-- Side by side, as they sit in the site's footer -->
                                    <div v-if="headerFooterForm.footer_show_custom_columns" :class="footerDetailsSideBySide ? '' : 'sm:grid-cols-3'" class="grid grid-cols-1 gap-4 items-start">
                                    <div
                                        v-for="(column, colIndex) in headerFooterForm.footer_link_columns"
                                        :key="column.id"
                                        class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3 min-w-0"
                                    >
                                        <div class="flex items-center gap-2">
                                            <input v-model="column.title" type="text" :aria-label="`Column ${colIndex + 1} heading`" placeholder="Heading, e.g. Useful Links" class="flex-1 min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <button type="button" @click="removeFooterColumn(colIndex)" class="px-3 py-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 font-bold text-[11px] transition-colors cursor-pointer" title="Remove column">
                                                🗑️
                                            </button>
                                        </div>

                                        <div v-for="(link, linkIndex) in column.links" :key="link.id" class="flex items-start gap-1.5">
                                          <div class="flex-1 min-w-0 space-y-1.5">
                                            <input v-model="link.label" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} text`" placeholder="Link text" class="w-full min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <input v-model="link.url" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} URL`" placeholder="https://... or /site/your-club/page" class="w-full min-w-0 px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                          </div>
                                            <button type="button" @click="removeFooterLink(colIndex, linkIndex)" class="px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-[11px] transition-colors cursor-pointer" title="Remove link">
                                                ✕
                                            </button>
                                        </div>

                                        <button
                                            type="button"
                                            @click="addFooterLink(colIndex)"
                                            :disabled="column.links.length >= MAX_FOOTER_COLUMN_LINKS"
                                            class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-[11px] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            ➕ Add Link
                                        </button>
                                    </div>
                                    </div>
                                </div>

                                </div>

                                <!-- Copyright line: its own panel, with the year kept out of the text -->
                                <div class="rounded-2xl border-2 border-dashed border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/40 p-4 sm:p-5 space-y-3">
                                    <div>
                                        <h4 class="text-sm font-extrabold text-slate-900 dark:text-white">Copyright Line</h4>
                                        <p class="text-[10px] text-slate-400 mt-0.5">The © symbol and the year are added for you and change on their own every January, so you never need to edit them.</p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                                        <div>
                                            <span class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Symbol and year</span>
                                            <div class="px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-bold text-slate-500 dark:text-slate-400 select-none" aria-label="Symbol and year, added automatically">© {{ currentYear }} <span class="font-normal text-[10px]">(automatic)</span></div>
                                        </div>
                                        <div>
                                            <label for="footer-copyright-holder" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Copyright holder</label>
                                            <input id="footer-copyright-holder" v-model="headerFooterForm.footer_copyright_holder" type="text" maxlength="150" :placeholder="club.name" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <p class="text-[10px] text-slate-400 mt-1">Leave blank to use {{ club.name }}.</p>
                                        </div>
                                        <div>
                                            <label for="footer-copyright-text" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Wording after it</label>
                                            <input id="footer-copyright-text" v-model="headerFooterForm.footer_copyright_text" type="text" maxlength="200" placeholder="All rights reserved." class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <p class="text-[10px] text-slate-400 mt-1">Optional.</p>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Shown as: <strong class="text-slate-800 dark:text-slate-100">{{ copyrightPreview }}</strong></p>
                                </div>
                            </div>

                            <div class="pt-4 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="headerFooterForm.processing"
                                    class="py-3 px-6 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-lg shadow-blue-600/20 transition-all flex items-center gap-2 disabled:opacity-50 cursor-pointer"
                                >
                                    <span>💾 Save Header & Footer</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- View Mode 3: Website Theme Selection Gallery -->
                    <div v-else-if="activeNavSelection === 'themes'" class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-8">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4 flex-wrap gap-3">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">🎨 Layout & Colour Scheme</h2>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-black bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                        Active: {{ themeLabel(currentThemeKey) }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pick a layout for your site's structure, then a colour scheme to paint it. All page content, news, events, and forms remain unchanged.</p>
                            </div>
                        </div>

                        <!-- Step 1: Layout -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">1. Layout</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <button
                                    v-for="opt in [LEGACY, ...LAYOUTS]"
                                    :key="opt.id"
                                    type="button"
                                    @click="selectedLayoutId = opt.id"
                                    :class="[
                                        'text-left rounded-2xl border p-5 space-y-3 transition-all duration-200 cursor-pointer',
                                        selectedLayoutId === opt.id ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200/90 dark:border-slate-800/90 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex items-center justify-between gap-2 flex-wrap">
                                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">{{ opt.name }}</h4>
                                        <span v-if="selectedLayoutId === opt.id" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-600 text-white shadow-sm">✓ SELECTED</span>
                                    </div>
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-bold">{{ opt.badge }}</span>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ opt.description }}</p>
                                    <div v-if="opt.id === LEGACY.id" class="flex items-center gap-1.5 pt-1">
                                        <span v-for="(color, cIdx) in opt.palette" :key="cIdx" :style="{ backgroundColor: color }" class="w-3.5 h-3.5 rounded-full border border-slate-400/40 shadow-sm" :title="color"></span>
                                        <span class="text-[10px] text-slate-500 dark:text-slate-400 ml-1 font-semibold">Fixed colours — no colour scheme</span>
                                    </div>
                                    <div v-else class="flex flex-wrap gap-1.5 pt-1">
                                        <span v-for="(feat, fIdx) in opt.features" :key="fIdx" class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-[10px] font-semibold border border-slate-200 dark:border-slate-800">
                                            • {{ feat }}
                                        </span>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Colour scheme: the built-in ones and the lodge's own (any layout except the fixed-colour legacy one) -->
                        <div v-if="selectedLayoutId !== LEGACY.id" class="space-y-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">2. Colour Scheme</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Pick a ready-made scheme, or create your own: give it a name and choose two colours, and every other shade (dark sections, light bands, text, buttons and dark mode) is worked out from them. Your schemes are saved here so you can switch between them.</p>

                            <div class="grid grid-cols-2 items-stretch gap-4 sm:grid-cols-3 lg:grid-cols-5">
                                <div
                                    v-for="c in COLOR_SCHEMES"
                                    :key="c.id"
                                    :class="[
                                        'flex h-full min-h-[8.5rem] flex-col rounded-2xl border transition-all duration-200',
                                        selectedColorSchemeId === c.id ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200/90 dark:border-slate-800/90 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md'
                                    ]"
                                >
                                    <button type="button" @click="selectedColorSchemeId = c.id" :aria-pressed="selectedColorSchemeId === c.id" class="flex flex-1 cursor-pointer flex-col gap-3 p-4 text-left">
                                        <div class="flex gap-1">
                                            <span v-for="(color, cIdx) in c.swatch" :key="cIdx" :style="{ backgroundColor: color }" class="h-6 w-6 shrink-0 rounded-lg border border-slate-400/30 shadow-sm"></span>
                                        </div>
                                        <div class="flex items-start justify-between gap-2">
                                            <span class="text-xs font-extrabold leading-snug text-slate-900 dark:text-white">{{ c.name }}</span>
                                            <span v-if="selectedColorSchemeId === c.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓</span>
                                        </div>
                                    </button>
                                    <div class="flex h-9 items-center justify-between gap-2 border-t border-slate-100 px-4 dark:border-slate-800">
                                        <span class="truncate text-[10px] font-semibold text-slate-400">{{ c.custom ? (usedByLiveSite(c) ? 'Your scheme · live' : 'Your scheme') : 'Ready-made' }}</span>
                                        <span v-if="c.custom" class="flex gap-1">
                                            <button type="button" :aria-label="`Edit ${c.name}`" title="Rename or change colours" class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md text-[11px] hover:bg-slate-100 dark:hover:bg-slate-800" @click="editScheme(c)">✏️</button>
                                            <button type="button" :aria-label="`Delete ${c.name}`" title="Delete" class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-md text-[11px] hover:bg-slate-100 dark:hover:bg-slate-800" @click="deleteScheme(c)">🗑️</button>
                                        </span>
                                    </div>
                                </div>

                                <button v-if="canCreateScheme" type="button" class="flex h-full min-h-[8.5rem] cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-300 p-4 text-center text-xs font-bold text-slate-500 transition-colors hover:border-blue-400 hover:text-blue-600 dark:border-slate-700 dark:text-slate-400" @click="openNewScheme">
                                    <span class="text-xl leading-none" aria-hidden="true">＋</span>
                                    Create your own colour scheme
                                </button>
                            </div>
                            <p v-if="!canCreateScheme" class="text-[11px] text-slate-400">You can keep {{ MAX_CUSTOM_SCHEMES }} colour schemes of your own. Delete one to create another.</p>
                            <p v-if="schemeError" class="text-xs font-bold text-rose-600 dark:text-rose-400" role="alert">{{ schemeError }}</p>

                            <form v-if="schemeEditorOpen" class="space-y-4 rounded-2xl border border-blue-300/60 bg-blue-50/30 p-4 dark:border-blue-800/60 dark:bg-blue-950/10" @submit.prevent="saveScheme">
                                <h4 class="text-xs font-extrabold text-slate-800 dark:text-slate-100">{{ customColourSchemes.some((x) => x.id === schemeForm.id) ? 'Edit colour scheme' : 'New colour scheme' }}</h4>

                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div class="space-y-1.5">
                                        <label for="scheme-name" class="block text-xs font-bold text-slate-700 dark:text-slate-200">Name</label>
                                        <input id="scheme-name" v-model="schemeForm.name" type="text" maxlength="40" :aria-invalid="!!schemeForm.errors.name" placeholder="e.g. Lodge crimson" class="w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs font-semibold dark:border-slate-700 dark:bg-slate-900" />
                                        <p v-if="schemeForm.errors.name" class="text-[10px] font-bold text-rose-600 dark:text-rose-400">{{ schemeForm.errors.name }}</p>
                                    </div>
                                    <div v-for="field in [{ key: 'primary', label: 'Dark colour', hint: 'Hero, dark sections, footer, headings' }, { key: 'accent', label: 'Accent colour', hint: 'Buttons, labels, dividers, icons' }]" :key="field.key" class="space-y-1.5">
                                        <label :for="`scheme-${field.key}`" class="block text-xs font-bold text-slate-700 dark:text-slate-200">{{ field.label }}</label>
                                        <div class="flex items-center gap-2">
                                            <input type="color" :value="schemeForm[field.key] || '#000000'" class="h-10 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5 dark:border-slate-700" :aria-label="`Pick the ${field.label.toLowerCase()}`" @input="schemeForm[field.key] = $event.target.value" />
                                            <input :id="`scheme-${field.key}`" v-model="schemeForm[field.key]" type="text" maxlength="7" placeholder="#rrggbb" class="w-28 rounded-xl border border-slate-300 bg-white p-2.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-900" />
                                        </div>
                                        <p class="text-[10px] text-slate-400">{{ field.hint }}</p>
                                        <p v-if="schemeForm.errors[field.key]" class="text-[10px] font-bold text-rose-600 dark:text-rose-400">Enter the colour as #rrggbb.</p>
                                    </div>
                                </div>

                                <div v-if="draftPalette" class="flex flex-wrap gap-2" aria-label="Colours worked out from your two">
                                    <div v-for="[key, label] in SCHEME_SHADES" :key="key" class="w-20 text-center">
                                        <span :style="{ backgroundColor: draftPalette[key] }" class="block h-8 rounded-lg border border-slate-400/30 shadow-sm"></span>
                                        <span class="mt-1 block text-[10px] font-semibold leading-tight text-slate-500 dark:text-slate-400">{{ label }}</span>
                                    </div>
                                </div>
                                <p v-else class="text-[11px] font-bold text-amber-700 dark:text-amber-300">Enter both colours as #rrggbb to see the scheme.</p>
                                <p v-if="schemeForm.errors.id" class="text-[11px] font-bold text-rose-600 dark:text-rose-400" role="alert">This colour scheme could not be saved. Close the form and try again.</p>
                                <p v-if="draftColoursClose" class="text-[11px] font-bold text-amber-700 dark:text-amber-300">These two colours are very close, so accents will be hard to see on dark sections.</p>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="submit" :disabled="schemeForm.processing || !draftColours" class="cursor-pointer rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Save colour scheme</button>
                                    <button type="button" class="cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" @click="closeSchemeEditor">Cancel</button>
                                    <span class="text-[11px] text-slate-400">Saving adds it to the list above; use “Apply Theme to Live Site” to put it on your website.</span>
                                </div>
                            </form>
                        </div>

                        <!-- Step 3: Fonts and corners (layered over any theme) -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">3. Fonts &amp; Corners</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Optional. These sit on top of whichever layout and colours you choose, and use the fonts already on visitors' devices, so nothing is loaded from outside.</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3" role="radiogroup" aria-label="Font style">
                                <button
                                    v-for="f in FONT_PAIRINGS"
                                    :key="f.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="themeForm.font_pairing === f.id"
                                    @click="themeForm.font_pairing = f.id"
                                    :class="['text-left rounded-2xl border p-4 space-y-1 transition-all cursor-pointer', themeForm.font_pairing === f.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200/90 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700']"
                                >
                                    <span class="block text-lg text-slate-900 dark:text-white" :style="f.stack ? { fontFamily: f.stack } : {}">Aa Bb Cc</span>
                                    <span class="block font-extrabold text-xs text-slate-900 dark:text-white">{{ f.name }} <span v-if="themeForm.font_pairing === f.id" class="text-blue-600 dark:text-blue-400">✓</span></span>
                                    <span class="block text-[10px] text-slate-500 dark:text-slate-400">{{ f.sample }}</span>
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Corner style">
                                <span class="font-bold text-xs text-slate-700 dark:text-slate-200 mr-1">Corners</span>
                                <button
                                    v-for="c in CORNER_STYLES"
                                    :key="c.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="themeForm.corner_style === c.id"
                                    @click="themeForm.corner_style = c.id"
                                    :class="['px-3 py-1.5 rounded-lg border text-xs font-bold cursor-pointer', themeForm.corner_style === c.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300']"
                                >{{ c.name }}</button>
                            </div>
                            <p v-if="themeForm.errors.font_pairing || themeForm.errors.corner_style" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ themeForm.errors.font_pairing || themeForm.errors.corner_style }}</p>
                        </div>

                        <!-- Preview & apply -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Previewing: <strong class="text-slate-800 dark:text-slate-100">{{ themeLabel(effectivePreviewThemeKey) }}</strong></p>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button
                                    type="button"
                                    @click="previewThemeInBuilder(selectedLayoutId, selectedColorSchemeId)"
                                    class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-colors cursor-pointer flex items-center gap-1"
                                    title="Preview this combination in the Website Builder"
                                >
                                    <span>👁️ Preview in Builder</span>
                                </button>
                                <a
                                    :href="`/site/${club.slug}?preview_theme=${effectivePreviewThemeKey}`"
                                    target="_blank"
                                    class="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-colors cursor-pointer flex items-center gap-1"
                                    title="Open full live preview with this combination in a new tab"
                                >
                                    <span>🌐 Live ↗</span>
                                </a>

                                <button
                                    v-if="effectivePreviewThemeKey === currentThemeKey && !styleChanged"
                                    disabled
                                    class="px-4 py-2 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 font-extrabold text-xs flex items-center gap-1.5 cursor-default"
                                >
                                    <span>✓ Active Theme</span>
                                </button>

                                <button
                                    v-else
                                    type="button"
                                    @click="openApplyThemeModal(effectivePreviewThemeKey, themeLabel(effectivePreviewThemeKey))"
                                    class="px-4 py-2 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 hover:from-blue-700 hover:to-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer flex items-center gap-1.5"
                                >
                                    <span>✨ Apply This Combination</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- View Mode: Redirects -->
                    <div v-else-if="activeNavSelection === 'redirects'" class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
                            <h2 class="text-xl font-bold text-slate-900 dark:text-white">↪️ Redirects</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Send an old address to a page that exists now, for example from your previous website, so links and search results keep working. Renaming a page's address already keeps the old one working by itself.</p>
                        </div>

                        <div v-if="isSavedSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold">✅ Saved.</div>

                        <form @submit.prevent="addRedirect" class="space-y-4 text-xs">
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                <div>
                                    <label for="redirect-from" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Old address</label>
                                    <div class="flex items-stretch">
                                        <span class="px-3 flex items-center bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-200 dark:border-slate-800 rounded-l-xl font-mono text-[11px] text-slate-500 whitespace-nowrap">/site/{{ club.slug }}</span>
                                        <input id="redirect-from" v-model="redirectForm.from_path" type="text" placeholder="/about-us/history" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 !rounded-l-none font-mono text-[11px]" />
                                    </div>
                                    <p v-if="redirectForm.errors.from_path" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ redirectForm.errors.from_path }}</p>
                                </div>
                                <div>
                                    <label for="redirect-to" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Send visitors to</label>
                                    <input id="redirect-to" v-model="redirectForm.to_url" type="text" :placeholder="`/site/${club.slug}/about`" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                                    <p v-if="redirectForm.errors.to_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ redirectForm.errors.to_url }}</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                    <input type="checkbox" v-model="redirectForm.is_permanent" class="w-4 h-4 rounded accent-blue-600 cursor-pointer" />
                                    <span>Permanent <span class="font-normal text-slate-400">(tells search engines to use the new address instead)</span></span>
                                </label>
                                <button type="submit" :disabled="redirectForm.processing || !redirectForm.from_path || !redirectForm.to_url" class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md disabled:opacity-50 cursor-pointer">Add redirect</button>
                            </div>
                            <p class="text-[10px] text-slate-400">A page that already exists at an address always shows instead of a redirect. Send visitors to a page on this site (starting with /) or to a full web address.</p>
                        </form>

                        <div v-if="redirects.length" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="p-3">Old address</th>
                                        <th class="p-3">Goes to</th>
                                        <th class="p-3">Type</th>
                                        <th class="p-3 text-right"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono text-[11px]">
                                    <tr v-for="r in redirects" :key="r.id">
                                        <td class="p-3 text-slate-700 dark:text-slate-200 break-all">/site/{{ club.slug }}{{ r.from_path }}</td>
                                        <td class="p-3 text-slate-500 dark:text-slate-400 break-all">{{ r.to_url }}</td>
                                        <td class="p-3 font-sans font-bold text-slate-500">{{ r.is_permanent ? 'Permanent' : 'Temporary' }}</td>
                                        <td class="p-3 text-right"><button type="button" @click="removeRedirect(r.id)" class="px-2.5 py-1 rounded-lg font-sans font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 cursor-pointer" :aria-label="`Remove redirect from ${r.from_path}`">Remove</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-else class="text-xs text-slate-400 italic">No redirects yet.</p>
                    </div>

                    <!-- View Mode 4: All Pages Overview Table -->
                    <div v-else class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">Manage Pages</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Comprehensive view of all pages configured for {{ club.name }}.</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs whitespace-nowrap">
                                <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider">
                                    <tr>
                                        <th class="p-4 w-12 text-center">#</th>
                                        <th class="p-4">Page Title</th>
                                        <th class="p-4">URL Slug</th>
                                        <th class="p-4">Blocks</th>
                                        <th class="p-4">Status</th>
                                        <th class="p-4 text-right min-w-[200px]">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                                    <tr v-for="(p, idx) in pages" :key="p.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                                        <td class="p-4 text-center font-bold text-slate-400">
                                            {{ idx + 1 }}
                                        </td>

                                        <td class="p-4 font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                            <span class="text-base">{{ getPageIcon(p) }}</span>
                                            <span>{{ p.title }}</span>
                                        </td>

                                        <td class="p-4 font-mono text-slate-500 dark:text-slate-400">
                                            /{{ p.slug }}
                                        </td>

                                        <td class="p-4">
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                                                {{ p.blocks ? p.blocks.length : 0 }} Blocks
                                            </span>
                                        </td>

                                        <td class="p-4">
                                            <div class="flex flex-wrap items-center gap-1">
                                                <span v-if="p.lifecycle_status === 'live'" class="text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800/60">PUBLISHED</span>
                                                <span v-else-if="p.lifecycle_status === 'scheduled'" class="text-[10px] font-extrabold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800/60" :title="lifecycleText(p)">SCHEDULED</span>
                                                <span v-else-if="p.lifecycle_status === 'expired'" class="text-[10px] font-extrabold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700" :title="lifecycleText(p)">EXPIRED</span>
                                                <span v-else class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">DRAFT</span>
                                                <span v-if="p.draft_saved_at" class="text-[10px] font-extrabold text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-950/40 px-2 py-0.5 rounded border border-violet-200 dark:border-violet-800/60" title="Has unpublished edits">HAS DRAFT</span>
                                            </div>
                                        </td>

                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5 flex-nowrap">
                                                <button
                                                    @click="movePageUp(idx)"
                                                    :disabled="idx <= 1"
                                                    :class="[
                                                        'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                                        idx <= 1 ? 'bg-slate-100 dark:bg-slate-800 text-slate-300 opacity-40 cursor-not-allowed' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800'
                                                    ]"
                                                    :title="idx === 0 ? 'Home page is anchored at top' : (idx === 1 ? 'First non-home page' : 'Move page up')"
                                                >
                                                    ▲
                                                </button>

                                                <button
                                                    @click="movePageDown(idx)"
                                                    :disabled="idx === 0 || idx >= pages.length - 1"
                                                    :class="[
                                                        'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                                        (idx === 0 || idx >= pages.length - 1) ? 'bg-slate-100 dark:bg-slate-800 text-slate-300 opacity-40 cursor-not-allowed' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800'
                                                    ]"
                                                    :title="idx === 0 ? 'Home page is anchored at top' : 'Move page down'"
                                                >
                                                    ▼
                                                </button>

                                                <button
                                                    @click="togglePublishPage(p)"
                                                    :disabled="p.is_homepage || p.slug === 'home'"
                                                    :class="[
                                                        'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                                        p.is_homepage ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 opacity-60 cursor-not-allowed' :
                                                        p.is_published ? 'bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800'
                                                    ]"
                                                    :title="p.is_homepage ? 'Homepage must remain published' : (p.is_published ? 'Published - Click to hide/unpublish' : 'Hidden - Click to publish')"
                                                >
                                                    <svg v-if="p.is_published" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                    </svg>
                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.007 10.007 0 014.122-.963c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21m-4.225-4.225L3 3" />
                                                    </svg>
                                                </button>

                                                <a
                                                    :href="selectionUrl(p.id, 'edit')"
                                                    @click="navClick($event, p.id, 'edit')"
                                                    class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Edit Page Builder"
                                                >
                                                    ✏️
                                                </a>

                                                <a
                                                    :href="selectionUrl(p.id, 'preview')"
                                                    @click="navClick($event, p.id, 'preview')"
                                                    class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Live Page Preview"
                                                >
                                                    👁️
                                                </a>

                                                <a
                                                    :href="p.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${p.slug}`"
                                                    target="_blank"
                                                    class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-xs font-bold transition-colors"
                                                    title="Open Live Site in New Tab"
                                                >
                                                    🌐
                                                </a>

                                                <button
                                                    @click="duplicatePage(p)"
                                                    class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Duplicate Page"
                                                >
                                                    📄
                                                </button>

                                                <button
                                                    v-if="!isDefaultPage(p)"
                                                    @click="triggerDeleteModal(p)"
                                                    class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Delete Custom Page"
                                                >
                                                    🗑️
                                                </button>
                                                <button
                                                    v-else
                                                    disabled
                                                    class="w-8 h-8 rounded-xl bg-slate-50 dark:bg-slate-800/50 text-slate-300 border border-slate-100 dark:border-slate-800 flex items-center justify-center text-xs opacity-40 cursor-not-allowed"
                                                    title="Original default page cannot be deleted"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Trash: recently deleted custom pages -->
                    <div v-if="trashedPages.length" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 dark:border-slate-800">
                            <h2 class="text-base font-bold text-slate-900 dark:text-white">🗑️ Trash</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Deleted pages stay here until restored or removed permanently.</p>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="tp in trashedPages" :key="tp.id" class="p-4 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ tp.title }}</p>
                                    <p class="text-[11px] text-slate-400 font-mono truncate">/site/{{ club.slug }}/{{ tp.slug }}</p>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="restorePage(tp)" class="py-1.5 px-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 font-bold text-[11px] cursor-pointer transition-colors">
                                        ↩️ Restore
                                    </button>
                                    <button @click="forceDeletePage(tp)" class="py-1.5 px-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 font-bold text-[11px] cursor-pointer transition-colors">
                                        Delete Forever
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        <!-- Delete Confirmation Modal -->
        <div v-if="showDeleteConfirmModal" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-page-modal-title"
                v-focus-trap="() => { showDeleteConfirmModal = false; pageToDelete = null; }"
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200 dark:border-slate-800"
            >
                <h3 id="delete-page-modal-title" class="text-lg font-bold text-slate-900 dark:text-white">Delete Page?</h3>
                <p class="text-xs text-slate-600 dark:text-slate-300">Are you sure you want to delete <strong>{{ pageToDelete ? pageToDelete.title : form.title }}</strong>? This action cannot be undone.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button @click="showDeleteConfirmModal = false; pageToDelete = null;" class="py-2 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">Cancel</button>
                    <button @click="confirmDeleteActivePage" class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md cursor-pointer">Confirm Delete</button>
                </div>
            </div>
        </div>

        <!-- Confirm deleting a page element -->
        <div v-if="blockToDelete !== null" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="delete-block-modal-title"
                v-focus-trap="() => { blockToDelete = null; }"
                class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 border border-slate-200 dark:border-slate-800"
            >
                <h3 id="delete-block-modal-title" class="text-lg font-bold text-slate-900 dark:text-white">Delete this element?</h3>
                <p class="text-xs text-slate-600 dark:text-slate-300">Are you sure you want to delete <strong>{{ blockToDeleteName }}</strong>? Its content will be lost once you save the page.</p>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="blockToDelete = null" class="py-2 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">Cancel</button>
                    <button type="button" @click="confirmRemoveBlock" class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-md cursor-pointer">Delete element</button>
                </div>
            </div>
        </div>

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
                    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 sm:p-7 space-y-5 relative overflow-hidden"
                >
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 dark:bg-amber-900/40 border border-amber-200 dark:border-amber-800/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-2xl shrink-0 shadow-inner">
                            ⚠️
                        </div>
                        <div class="space-y-1 pt-0.5">
                            <h3 id="unsaved-changes-modal-title" class="text-lg font-black text-slate-900 dark:text-white leading-tight">Unsaved Changes</h3>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                You have modified <strong class="text-slate-900 dark:text-white font-bold">"{{ form.title || 'this page' }}"</strong>. 
                                What would you like to do with your changes before leaving?
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 pt-2">
                        <button
                            @click="handleSaveAndProceed"
                            :disabled="form.processing"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 hover:from-blue-700 hover:to-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 flex items-center justify-between transition-all cursor-pointer disabled:opacity-50"
                        >
                            <span class="flex items-center gap-2">
                                <span>💾</span>
                                <span>{{ form.processing ? 'Saving Changes...' : 'Save Changes & Proceed' }}</span>
                            </span>
                            <span class="text-[10px] bg-white/20 dark:bg-slate-900/20 px-2 py-0.5 rounded font-black">Recommended</span>
                        </button>

                        <button
                            @click="handleDiscardAndProceed"
                            :disabled="form.processing"
                            class="w-full py-3 px-4 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 font-bold text-xs flex items-center gap-2 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <span>🗑️</span>
                            <span>Discard Changes & Switch Page</span>
                        </button>

                        <button
                            @click="handleStayAndEdit"
                            :disabled="form.processing"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center justify-center gap-2 transition-all cursor-pointer disabled:opacity-50"
                        >
                            <span>✏️</span>
                            <span>Stay & Continue Editing</span>
                        </button>
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
                    class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-2xl max-w-md w-full p-6 sm:p-7 space-y-5 relative overflow-hidden"
                >
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-blue-100 dark:bg-blue-900/40 border border-blue-200 dark:border-blue-800/60 text-blue-600 dark:text-blue-400 flex items-center justify-center text-2xl shrink-0 shadow-inner">
                            🎨
                        </div>
                        <div class="space-y-1 pt-0.5">
                            <h3 id="theme-confirm-modal-title" class="text-lg font-black text-slate-900 dark:text-white leading-tight">Apply New Website Theme?</h3>
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                You are about to switch your active design to <strong class="text-blue-600 dark:text-blue-400 font-bold">"{{ selectedThemeForModal.label }}"</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl text-xs space-y-1.5">
                        <div class="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
                            <span>⚠️ Notice</span>
                        </div>
                        <p class="text-amber-800 dark:text-amber-200 text-[11px] leading-relaxed">
                            This will immediately change the visual appearance, color scheme, and layout of your live public site. 
                            <strong>All your existing pages, news, events, and forms will stay intact.</strong>
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            @click="showThemeConfirmModal = false; selectedThemeForModal = null;"
                            :disabled="themeForm.processing"
                            class="py-2.5 px-4 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-all cursor-pointer"
                        >
                            Cancel
                        </button>
                        <button
                            @click="confirmApplyTheme"
                            :disabled="themeForm.processing"
                            class="py-2.5 px-5 rounded-xl bg-gradient-to-r from-blue-600 to-blue-600 hover:from-blue-700 hover:to-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer flex items-center gap-2"
                        >
                            <span>{{ themeForm.processing ? 'Applying Theme...' : '✨ Apply Theme to Live Site' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AdminLayout>
</template>
