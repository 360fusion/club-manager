<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import { SITE_LAYOUTS, SITE_COLOR_SCHEMES, LEGACY_THEME, DEFAULT_THEME_KEY, themeClasses } from '@/Support/siteThemes';

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
const pageViewMode = ref('preview'); // 'preview' or 'edit'
const isSavedSuccess = ref(false);

const settingsForm = useForm({
    seo_title_suffix: props.websiteSettings?.seo_title_suffix || '',
    seo_meta_description: props.websiteSettings?.seo_meta_description || '',
    custom_domain: props.websiteSettings?.custom_domain || '',
    primary_color: props.websiteSettings?.primary_color || '#0369a1',
    contact_email: props.websiteSettings?.contact_email || '',
    phone: props.websiteSettings?.phone || '',
    address: props.websiteSettings?.address || '',
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
    footer_copyright: props.websiteSettings?.footer_copyright || '',
    social_facebook: props.websiteSettings?.social_facebook || '',
    social_instagram: props.websiteSettings?.social_instagram || '',
    social_twitter: props.websiteSettings?.social_twitter || '',
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
const liveThemeClasses = computed(() => themeClasses(currentThemeKey.value));

const publishedNavPages = computed(() => props.pages.filter(p => p.show_in_navigation && p.is_published));

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
    }
};

const LAYOUTS = SITE_LAYOUTS;
const COLOR_SCHEMES = SITE_COLOR_SCHEMES;
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
        colorSchemeId: COLOR_SCHEMES.some(c => c.id === colorSchemeId) ? colorSchemeId : DEFAULT_THEME_KEY.split(':')[1],
    };
};

const initialParsed = parseKey(currentThemeKey.value);
const selectedLayoutId = ref(initialParsed.layoutId);
const selectedColorSchemeId = ref(initialParsed.colorSchemeId);
const selectedThemeForModal = ref(null);
const showThemeConfirmModal = ref(false);

const themeForm = useForm({
    website_theme: currentThemeKey.value,
});

const previewThemeInBuilder = (layoutId, colorSchemeId = null) => {
    selectedLayoutId.value = layoutId;
    if (layoutId !== LEGACY.id) {
        selectedColorSchemeId.value = colorSchemeId || selectedColorSchemeId.value || COLOR_SCHEMES[0].id;
    }
    const targetPageId = props.pages.length > 0 ? props.pages[0].id : 'new';
    requestNavigation(targetPageId, 'preview');
};

const effectivePreviewThemeKey = computed(() => keyFor(selectedLayoutId.value, selectedColorSchemeId.value));
const previewThemeClasses = computed(() => themeClasses(effectivePreviewThemeKey.value));

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

const colorSchemeName = (id) => COLOR_SCHEMES.find(c => c.id === id)?.name || '';
const layoutName = (id) => (id === LEGACY.id ? LEGACY.name : LAYOUTS.find(l => l.id === id)?.name || '');
const themeLabel = (key) => {
    if (key === LEGACY.id) return LEGACY.name;
    const { layoutId, colorSchemeId } = parseKey(key);
    return `${layoutName(layoutId)} — ${colorSchemeName(colorSchemeId)}`;
};

const MANAGEMENT_SELECTIONS = ['overview', 'settings', 'themes', 'header_footer'];

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
    is_members_only: false,
    meta_title: '',
    meta_description: '',
    blocks: [],
});

const initialFormSnapshot = ref('');
const isBypassingDirtyGuard = ref(false);
const showUnsavedModal = ref(false);
const pendingNavigationTarget = ref(null); // { selection: string, mode: 'edit'|'preview' }

const getFormStateString = () => {
    return JSON.stringify({
        id: form.id,
        title: form.title,
        slug: form.slug,
        is_published: form.is_published,
        is_homepage: form.is_homepage,
        show_in_navigation: form.show_in_navigation,
        is_members_only: form.is_members_only,
        meta_title: form.meta_title,
        meta_description: form.meta_description,
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

const loadPageIntoForm = (page) => {
    if (page) {
        form.id = page.id;
        form.title = page.title || '';
        form.slug = page.slug || '';
        form.is_published = page.is_published ?? true;
        form.is_homepage = page.is_homepage ?? false;
        form.show_in_navigation = page.show_in_navigation ?? true;
        form.is_members_only = page.is_members_only ?? false;
        form.meta_title = page.meta_title || '';
        form.meta_description = page.meta_description || '';
        form.blocks = page.blocks ? JSON.parse(JSON.stringify(page.blocks)) : [];
    } else {
        form.id = null;
        form.title = 'New Custom Page';
        form.slug = 'new-custom-page';
        form.is_published = true;
        form.is_homepage = false;
        form.show_in_navigation = true;
        form.is_members_only = false;
        form.meta_title = '';
        form.meta_description = '';
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
};

const selectPage = (pageId) => {
    requestNavigation(pageId, pageViewMode.value || 'edit');
};

const selectPageForEdit = (pageId) => {
    requestNavigation(pageId, 'edit');
};

const selectPageForPreview = (pageId) => {
    requestNavigation(pageId, 'preview');
};

const startCreateNewPage = () => {
    requestNavigation('new', 'edit');
};

// Block Element Types Palette
const blockTypes = [
    { type: 'text', icon: '📝', label: 'Text Block', desc: 'Rich text paragraph or formatted text', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'image', icon: '🖼️', label: 'Single Image', desc: 'Image with positioning & size controls', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'images', icon: '🖼️', label: 'Image Gallery', desc: 'Multi-image grid layout', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'notice', icon: '📢', label: 'Callout Box', desc: 'Highlighted notice or announcement box', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'button', icon: '🔗', label: 'Button Link', desc: 'Call to action button link', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'hero', icon: '🚀', label: 'Hero Banner', desc: 'Large title & subtitle header banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
    { type: 'news_feed', icon: '📰', label: 'News Items', desc: 'Pulls published articles automatically', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'events_calendar', icon: '📅', label: 'Events & Summons', desc: 'Displays upcoming events & dinners', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'pricing_cards', icon: '💳', label: 'Membership Dues', desc: 'Shows active membership plans', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
    { type: 'donation_campaign', icon: '💰', label: 'Dynamic Donation', desc: 'Fundraising campaign progress bar', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
    { type: 'contact_details', icon: '📇', label: 'Contact Details & Cards', desc: 'Email, meeting times & location cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
    { type: 'contact_form', icon: '📝', label: 'Interactive Contact Form', desc: 'Form with email notification & options', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
];

const blockColumns = computed(() => [
    {
        title: 'Content & Media (Left Column)',
        items: [
            { type: 'text', icon: '📝', label: 'Text Block', desc: 'Rich text paragraph or formatted text', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'image', icon: '🖼️', label: 'Single Image', desc: 'Image with positioning & size controls', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'images', icon: '🖼️', label: 'Image Gallery', desc: 'Multi-image grid layout', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'button', icon: '🔗', label: 'Button Link', desc: 'Call to action button link', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'notice', icon: '📢', label: 'Callout Box', desc: 'Highlighted notice or announcement box', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
        ],
    },
    {
        title: 'Banners & Dynamic Feeds',
        items: [
            { type: 'hero', icon: '🚀', label: 'Hero Banner', desc: 'Large title & subtitle header banner', color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/40' },
            { type: 'news_feed', icon: '📰', label: 'News Items', desc: 'Pulls published articles automatically', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'events_calendar', icon: '📅', label: 'Events & Summons', desc: 'Displays upcoming events & dinners', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
            { type: 'pricing_cards', icon: '💳', label: 'Membership Dues', desc: 'Shows active membership plans', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
        ],
    },
    {
        title: 'Contact & Forms',
        items: [
            { type: 'donation_campaign', icon: '💰', label: 'Dynamic Donation', desc: 'Fundraising campaign progress bar', color: 'text-rose-600 dark:text-rose-400', bg: 'bg-rose-50 dark:bg-rose-950/40' },
            { type: 'contact_details', icon: '📇', label: 'Contact Details & Cards', desc: 'Email, meeting times & location cards', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
            { type: 'contact_form', icon: '📝', label: 'Interactive Contact Form', desc: 'Form with email notification & options', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-50 dark:bg-blue-950/40' },
        ],
    },
]);

const activeInsertIndex = ref(null);
const insertMenuPlacement = ref('down');

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

        if (spaceBelow < 300 && spaceAbove > spaceBelow) {
            insertMenuPlacement.value = 'up';
        } else {
            insertMenuPlacement.value = 'down';
        }
    } else {
        insertMenuPlacement.value = 'down';
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
    } else if (type === 'hero') {
        newBlock = {
            id,
            type: 'hero',
            title: 'Welcome to ' + props.club.name,
            subtitle: 'Join us for training, events and community.',
            cta_text: 'Explore Membership',
            cta_link: `/site/${props.club.slug}/join-us`
        };
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
        if (targetIndex !== null && targetIndex >= 0 && targetIndex <= form.blocks.length) {
            form.blocks.splice(targetIndex, 0, newBlock);
        } else {
            form.blocks.push(newBlock);
        }
    }
    activeInsertIndex.value = null;
};

const removeBlock = (index) => {
    form.blocks.splice(index, 1);
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

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);

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
});

const togglePublishPage = (p) => {
    if (p.is_homepage || p.slug === 'home') return;
    router.post(`/${props.club.slug}/admin/pages/${p.id}/toggle-publish`, {}, { preserveScroll: true });
};

const duplicatePage = (p) => {
    router.post(`/${props.club.slug}/admin/pages/${p.id}/duplicate`, {}, { preserveScroll: true });
};

const duplicateActivePage = () => {
    if (!form.id) return;
    duplicatePage({ id: form.id });
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
                                <button
                                    v-for="p in pages"
                                    :key="p.id"
                                    @click="selectPage(p.id)"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left group font-bold',
                                        activeNavSelection === String(p.id) ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span class="text-base leading-none">{{ getPageIcon(p) }}</span>
                                    <span class="truncate font-bold">{{ p.title }}</span>
                                </button>
                            </div>

                            <!-- Add New Page Quick Action -->
                            <button
                                @click="startCreateNewPage"
                                :class="[
                                    'w-full mt-3 px-3.5 py-2.5 rounded-xl transition-all duration-200 text-xs font-black flex items-center justify-center gap-2 cursor-pointer shadow-sm',
                                    activeNavSelection === 'new'
                                        ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30 ring-2 ring-blue-400/50'
                                        : 'bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white hover:shadow-md hover:scale-[1.01]'
                                ]"
                            >
                                <span class="text-sm leading-none">➕</span>
                                <span>Add New Custom Page</span>
                            </button>
                        </div>

                        <!-- Management & Tools Group -->
                        <div class="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-1">
                            <div class="px-3 text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Management</div>
                            <div class="space-y-1 text-xs font-bold">
                                <button
                                    @click="requestNavigation('settings')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'settings' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>⚙️</span> Website & SEO Settings
                                </button>

                                <button
                                    @click="requestNavigation('header_footer')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'header_footer' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>🧭</span> Header & Footer
                                </button>

                                <button
                                    @click="requestNavigation('themes')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'themes' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>🎨</span> Website Themes
                                </button>

                                <button
                                    @click="requestNavigation('overview')"
                                    :class="[
                                        'w-full px-3.5 py-2.5 rounded-xl transition-all flex items-center gap-2.5 cursor-pointer text-left',
                                        activeNavSelection === 'overview' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/20' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>📋</span> Manage Pages
                                </button>
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
                                <button
                                    type="button"
                                    @click="pageViewMode = 'edit'"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'edit' ? 'bg-white dark:bg-slate-900 text-blue-700 dark:text-blue-300 shadow-md shadow-slate-200' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>✏️ Edit Builder</span>
                                </button>

                                <button
                                    type="button"
                                    @click="pageViewMode = 'preview'"
                                    :class="[
                                        'px-4 py-2 rounded-xl text-xs font-extrabold transition-all flex items-center gap-2 cursor-pointer',
                                        pageViewMode === 'preview' ? 'bg-amber-400 text-amber-950 shadow-md shadow-amber-500/20' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white'
                                    ]"
                                >
                                    <span>👁️ Live Preview</span>
                                </button>
                            </div>
                        </div>

                        <!-- SUB-VIEW 1: EDIT BUILDER VIEW -->
                        <div v-if="pageViewMode === 'edit'" class="space-y-6">
                            
                            <!-- Page Particulars Card -->
                            <div class="bg-white dark:bg-slate-900 p-6 sm:p-7 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
                                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-4">
                                    <div>
                                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Page Particulars & Navigation Settings</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Configure page title, permalink URL slug, and navigation visibility.</p>
                                    </div>

                                    <div class="flex items-center gap-2.5">
                                        <button
                                            @click="submitForm"
                                            :disabled="form.processing"
                                            :class="[
                                                'px-4 py-2 font-bold text-xs rounded-xl shadow-md transition-all duration-300 flex items-center gap-2 cursor-pointer',
                                                form.processing ? 'bg-blue-500 text-white cursor-wait opacity-80' :
                                                isSavedSuccess ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-600/30 scale-105 ring-2 ring-emerald-400/50' :
                                                'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-600/20'
                                            ]"
                                        >
                                            <svg v-if="form.processing" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <svg v-else-if="isSavedSuccess" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? '✓ Saved!' : 'Save Page') }}</span>
                                        </button>

                                        <a v-if="form.id && form.slug" :href="form.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${form.slug}`" target="_blank" class="py-2 px-3 rounded-xl bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-xs border border-blue-200 dark:border-blue-800/60 transition-colors flex items-center gap-1.5">
                                            <span>🔗 Preview Link</span>
                                        </a>

                                        <button v-if="form.id" type="button" @click="duplicateActivePage" class="py-2 px-3 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs border border-slate-300 dark:border-slate-700 transition-colors flex items-center gap-1.5 cursor-pointer">
                                            <span>📄 Duplicate</span>
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <label for="page-title" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page Title</label>
                                        <input id="page-title" v-model="form.title" type="text" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-semibold outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="e.g. About Our Club" />
                                    </div>

                                    <div>
                                        <div class="flex items-center gap-2 mb-1.5">
                                            <label for="page-slug" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px]">URL Slug (Permalink)</label>
                                            <span class="text-slate-300">|</span>
                                            <span class="text-slate-400 font-mono text-[11px]">/site/{{ club.slug }}/</span>
                                        </div>
                                        <div>
                                            <input id="page-slug" v-model="form.slug" type="text" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-mono font-bold outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="about" />
                                        </div>
                                    </div>
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
                                </div>
                            </div>

                            <!-- SEO Card -->
                            <div class="bg-white dark:bg-slate-900 p-6 sm:p-7 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-4">
                                <div>
                                    <h3 class="text-base font-bold text-slate-900 dark:text-white">Search Engine Optimization (this page)</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Overrides the site-wide SEO defaults for just this page. Leave blank to use the site defaults.</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <label for="page-meta-title" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page SEO Title</label>
                                        <input id="page-meta-title" v-model="form.meta_title" type="text" maxlength="255" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-semibold outline-none focus:ring-2 focus:ring-blue-500/20" :placeholder="form.title || 'Page title'" />
                                    </div>
                                    <div>
                                        <label for="page-meta-description" class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider text-[11px] mb-1.5">Page Meta Description</label>
                                        <input id="page-meta-description" v-model="form.meta_description" type="text" maxlength="500" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 focus:border-blue-500 rounded-xl p-3 text-slate-900 dark:text-white font-medium outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Site-wide default description" />
                                    </div>
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
                                                    'absolute z-40 w-[680px] max-w-[90vw] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 -translate-x-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                                    insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                                                ]"
                                            >
                                                <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                                    <span>Insert Element Here</span>
                                                    <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs cursor-pointer p-1">✕</button>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
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
                                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md shadow-slate-200/60 overflow-hidden transition-all hover:shadow-lg">
                                        
                                        <!-- Block Header & Controls -->
                                        <div class="flex items-center justify-between px-4 py-2.5 bg-slate-200/80 dark:bg-slate-700/80 border-b border-slate-300/80 dark:border-slate-700/80 text-xs font-bold text-slate-800 dark:text-slate-100">
                                            <div class="flex items-center gap-2">
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
                                            </div>

                                            <!-- Control Buttons (Up, Down, Duplicate, Delete) -->
                                            <div class="flex items-center gap-1">
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
                                                    @click="removeBlock(bIdx)"
                                                    class="p-1 min-w-6 min-h-6 flex items-center justify-center text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer"
                                                    title="Delete Element"
                                                    aria-label="Delete block"
                                                >
                                                    🗑️
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Block Body Editors -->
                                        <div class="p-4 space-y-3">
                                            
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
                                                        <label :for="`block-${bIdx}-button-url`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Link Target URL</label>
                                                        <input :id="`block-${bIdx}-button-url`" v-model="block.url" type="text" placeholder="https://..." class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
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

                                            <!-- 6. Hero Banner Block -->
                                            <div v-else-if="block.type === 'hero'" class="space-y-3 text-xs">
                                                <div>
                                                    <label :for="`block-${bIdx}-hero-title`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Banner Title</label>
                                                    <input :id="`block-${bIdx}-hero-title`" v-model="block.title" placeholder="Hero Title" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-900 dark:text-white font-semibold" />
                                                </div>
                                                <div>
                                                    <label :for="`block-${bIdx}-hero-subtitle`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Banner Subtitle</label>
                                                    <input :id="`block-${bIdx}-hero-subtitle`" v-model="block.subtitle" placeholder="Hero Subtitle" class="w-full bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100" />
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label :for="`block-${bIdx}-hero-cta-text`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">CTA Button Text</label>
                                                        <input :id="`block-${bIdx}-hero-cta-text`" v-model="block.cta_text" placeholder="e.g. Join Us" class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 w-full font-semibold" />
                                                    </div>
                                                    <div>
                                                        <label :for="`block-${bIdx}-hero-cta-link`" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">CTA Button Target Link</label>
                                                        <input :id="`block-${bIdx}-hero-cta-link`" v-model="block.cta_link" placeholder="e.g. /site/lodge-of-fraternity/join-us" class="bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 text-slate-800 dark:text-slate-100 w-full font-mono text-[11px]" />
                                                    </div>
                                                </div>
                                            </div>

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
                                                    'absolute z-40 w-[680px] max-w-[90vw] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-3 space-y-2 animate-in fade-in zoom-in-95 duration-100 left-1/2 -translate-x-1/2 max-h-[calc(100vh-100px)] overflow-y-auto',
                                                    insertMenuPlacement === 'up' ? 'bottom-full mb-2' : 'top-full mt-2'
                                                ]"
                                            >
                                                <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 dark:border-slate-800 flex items-center justify-between sticky top-0 bg-white dark:bg-slate-900 z-10">
                                                    <span>Insert Element Here</span>
                                                    <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-xs cursor-pointer p-1">✕</button>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1">
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
                                    :interactive="false"
                                />

                                <!-- Website Footer Preview (same component the real site renders) -->
                                <PublicFooter
                                    :club="club"
                                    :theme="previewThemeClasses"
                                    :navigation="publishedNavPages"
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
                                        <input id="custom-domain" v-model="settingsForm.custom_domain" type="text" placeholder="e.g. members.oxfordboating.org" class="w-full sm:w-96 px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 font-mono font-bold" />
                                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">Connect your custom domain (e.g. <code class="bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-100 px-1 py-0.5 rounded font-bold">members.oxfordboating.org</code>) to your club portal.</p>
                                    </div>

                                    <div v-if="settingsForm.custom_domain" class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl space-y-2 text-amber-900 dark:text-amber-200">
                                        <div class="flex items-center justify-between gap-3 flex-wrap">
                                            <div class="font-bold text-xs">DNS Configuration Instructions:</div>
                                            <span
                                                :class="[
                                                    'px-2.5 py-0.5 rounded-full text-[10px] font-extrabold border',
                                                    websiteSettings.domain_status === 'active'
                                                        ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800/60'
                                                        : 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800/60'
                                                ]"
                                            >
                                                {{ websiteSettings.domain_status === 'active' ? `✅ Active${websiteSettings.domain_verified_at ? ' since ' + websiteSettings.domain_verified_at : ''}` : '⏳ Pending verification' }}
                                            </span>
                                        </div>
                                        <p class="text-[11px]">Add a CNAME record at your DNS provider pointing your subdomain/domain to this server's target hostname.</p>
                                        <div class="font-mono text-[11px] bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-amber-200 dark:border-amber-800/60 font-bold">
                                            Host: {{ websiteSettings.domain_instructions?.host }} • Type: {{ websiteSettings.domain_instructions?.type }} • Target: {{ websiteSettings.domain_instructions?.target }}
                                        </div>
                                        <button
                                            type="button"
                                            @click="checkDomainNow"
                                            :disabled="checkingDomain"
                                            class="px-3.5 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-[11px] shadow-sm transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-wait"
                                        >
                                            {{ checkingDomain ? 'Checking…' : '🔄 Check Now' }}
                                        </button>
                                    </div>
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

                        <form @submit.prevent="submitHeaderFooter" class="space-y-8 text-xs">

                            <!-- Header Section -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Header</h3>

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
                                        <span>Show Log In / Admin Portal Links</span>
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
                                        <label for="header-cta-link" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Button Link</label>
                                        <input id="header-cta-link" v-model="headerFooterForm.header_cta_link" type="text" placeholder="e.g. /site/oxford-boating/join-us" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                </div>
                            </div>

                            <hr class="border-slate-100 dark:border-slate-800" />

                            <!-- Footer Section -->
                            <div class="space-y-4">
                                <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider">Footer</h3>

                                <!-- Live preview -->
                                <div :class="['rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden', liveThemeClasses.wrapper]" :style="liveThemeClasses.cssVars">
                                    <PublicFooter
                                        :club="club"
                                        :theme="liveThemeClasses"
                                        :navigation="publishedNavPages"
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

                                <!-- Visibility toggles -->
                                <div class="flex flex-wrap items-center gap-6 pt-1">
                                    <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.footer_show_social" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Social Links</span>
                                    </label>
                                    <label v-if="headerFooterForm.footer_layout === 'columns'" class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                                        <input type="checkbox" v-model="headerFooterForm.footer_show_nav" class="w-4 h-4 rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 accent-blue-600 cursor-pointer" />
                                        <span>Show Navigation Links</span>
                                    </label>
                                </div>

                                <div v-if="headerFooterForm.footer_show_social" class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl">
                                    <div>
                                        <label for="social-facebook" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Facebook Page URL</label>
                                        <input id="social-facebook" v-model="headerFooterForm.social_facebook" type="text" placeholder="https://facebook.com/yourclub" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div>
                                        <label for="social-instagram" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Instagram Profile URL</label>
                                        <input id="social-instagram" v-model="headerFooterForm.social_instagram" type="text" placeholder="https://instagram.com/yourclub" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="social-twitter" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Twitter / X Handle URL</label>
                                        <input id="social-twitter" v-model="headerFooterForm.social_twitter" type="text" placeholder="https://x.com/yourclub" class="w-full px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                    </div>
                                </div>

                                <!-- Custom footer link columns (Columns layout only) -->
                                <div v-if="headerFooterForm.footer_layout === 'columns'" class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <label class="block font-bold text-slate-700 dark:text-slate-200">Custom Link Columns</label>
                                        <button
                                            type="button"
                                            @click="addFooterColumn"
                                            :disabled="headerFooterForm.footer_link_columns.length >= MAX_FOOTER_COLUMNS"
                                            class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                                        >
                                            ➕ Add Column
                                        </button>
                                    </div>
                                    <p class="text-[10px] text-slate-400">Add extra footer columns for things like "Useful Links" — e.g. your Grand Lodge, Province, or a Data Protection Notice.</p>

                                    <div v-if="!headerFooterForm.footer_link_columns.length" class="p-4 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 text-slate-400 text-center">
                                        No custom columns yet.
                                    </div>

                                    <div
                                        v-for="(column, colIndex) in headerFooterForm.footer_link_columns"
                                        :key="column.id"
                                        class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <input v-model="column.title" type="text" :aria-label="`Column ${colIndex + 1} heading`" placeholder="Column heading, e.g. Useful Links" class="flex-1 px-3.5 py-2.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <button type="button" @click="removeFooterColumn(colIndex)" class="px-3 py-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 hover:bg-red-100 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 font-bold text-[11px] transition-colors cursor-pointer" title="Remove column">
                                                🗑️
                                            </button>
                                        </div>

                                        <div v-for="(link, linkIndex) in column.links" :key="link.id" class="flex items-center gap-2">
                                            <input v-model="link.label" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} text`" placeholder="Link text" class="w-1/3 px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
                                            <input v-model="link.url" type="text" :aria-label="`Column ${colIndex + 1}, link ${linkIndex + 1} URL`" placeholder="https://... or /site/your-club/page" class="flex-1 px-3.5 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
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

                                <div>
                                    <label for="footer-copyright" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Footer Copyright Line</label>
                                    <input id="footer-copyright" v-model="headerFooterForm.footer_copyright" type="text" placeholder="© 2026 Lodge of Fraternity. All rights reserved." class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500" />
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

                        <!-- Step 2: Colour scheme (any layout except the fixed-colour legacy one) -->
                        <div v-if="selectedLayoutId !== LEGACY.id" class="space-y-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">2. Colour Scheme</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                                <button
                                    v-for="c in COLOR_SCHEMES"
                                    :key="c.id"
                                    type="button"
                                    @click="selectedColorSchemeId = c.id"
                                    :class="[
                                        'text-left rounded-2xl border p-4 space-y-3 transition-all duration-200 cursor-pointer',
                                        selectedColorSchemeId === c.id ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200/90 dark:border-slate-800/90 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md'
                                    ]"
                                >
                                    <div class="flex gap-1">
                                        <span v-for="(color, cIdx) in c.swatch" :key="cIdx" :style="{ backgroundColor: color }" class="w-6 h-6 rounded-lg border border-slate-400/30 shadow-sm"></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-extrabold text-xs text-slate-900 dark:text-white">{{ c.name }}</span>
                                        <span v-if="selectedColorSchemeId === c.id" class="text-[10px] font-black text-blue-600 dark:text-blue-400">✓</span>
                                    </div>
                                </button>
                            </div>
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
                                    v-if="effectivePreviewThemeKey === currentThemeKey"
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
                                            <span v-if="p.is_published" class="text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800/60">PUBLISHED</span>
                                            <span v-else class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">DRAFT</span>
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

                                                <button
                                                    @click="selectPageForEdit(p.id)"
                                                    class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Edit Page Builder"
                                                >
                                                    ✏️
                                                </button>

                                                <button
                                                    @click="selectPageForPreview(p.id)"
                                                    class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                                    title="Live Page Preview"
                                                >
                                                    👁️
                                                </button>

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

        <!-- Spatie Media Library Modal Component -->
        <MediaLibraryModal
            :show="showMediaModal"
            :club="club"
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
