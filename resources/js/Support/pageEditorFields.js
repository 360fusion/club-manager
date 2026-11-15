// Shared by the page editor's sections (Components/Pages/PageEdit*.vue).

// The kit's field look, for the fields its components cannot take: number-valued selects, inputs with their own
// handlers, and labels that share a row with buttons.
export const field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500';
export const fieldLabel = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
export const smallSelect = 'rounded-xl border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-700 cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
export const checkbox = 'h-4 w-4 rounded border-slate-300 accent-blue-600 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700';
export const iconButton = 'inline-flex h-7 w-7 items-center justify-center rounded-xl text-slate-500 transition-colors hover:bg-white hover:text-slate-900 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-slate-400 dark:hover:bg-slate-900 dark:hover:text-white';
export const linkButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 whitespace-nowrap';
export const tooltip = 'pointer-events-none absolute top-full mt-2 z-50 w-64 rounded-xl bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-medium leading-snug p-3 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible group-focus-within:opacity-100 group-focus-within:visible transition-opacity duration-150';

// The palette in Support/pageBlockCatalog.js gives each element an emoji; the editor shows an interface icon instead.
export const BLOCK_ICONS = {
    text: 'document', rich_text: 'document', image: 'photo', images: 'photo', notice: 'info', button: 'link', youtube: 'eye',
    downloads: 'download', faq: 'info', map: 'globe', cta_banner: 'star', calendar: 'calendar', hero: 'sparkles',
    news_feed: 'clipboard', news_list: 'clipboard', events_calendar: 'calendar', pricing_cards: 'cash', donation_campaign: 'heart',
    contact_details: 'user', contact_form: 'mail', slideshow: 'photo', section_heading: 'tag', feature_cards: 'award',
    stats: 'chart', media_text: 'photo', quote_motto: 'pencil',
};

export const HEADER_STYLES = [
    { value: 'full', label: 'Full header (menu and buttons)' },
    { value: 'logo_only', label: 'Logo only' },
    { value: 'hidden', label: 'No header at all' },
];
export const TEXT_ALIGNS = [
    { value: 'left', label: 'Left aligned' },
    { value: 'center', label: 'Centred' },
    { value: 'right', label: 'Right aligned' },
];
export const IMAGE_POSITIONS = [...TEXT_ALIGNS, { value: 'full', label: 'Full-width banner' }];
export const IMAGE_SIZES = [
    { value: 'small', label: 'Small (25% width)' },
    { value: 'medium', label: 'Medium (50% width)' },
    { value: 'large', label: 'Large (75% width)' },
    { value: 'full', label: 'Full width (100%)' },
];
export const NOTICE_STYLES = [
    { value: 'info', label: 'Information (blue)' },
    { value: 'warning', label: 'Warning (amber)' },
    { value: 'important', label: 'Important (red)' },
    { value: 'success', label: 'Success (green)' },
];
export const VIDEO_LAYOUTS = [
    { value: 'stacked', label: 'Video, then text' },
    { value: 'video_only', label: 'Video only' },
    { value: 'side_left', label: 'Video left, text right' },
    { value: 'side_right', label: 'Text left, video right' },
];
export const VIDEO_SHAPES = [
    { value: '16:9', label: 'Widescreen 16:9' },
    { value: '4:3', label: 'Classic 4:3' },
    { value: '21:9', label: 'Cinema 21:9' },
    { value: '1:1', label: 'Square 1:1' },
    { value: '9:16', label: 'Portrait 9:16' },
];
export const VIDEO_TEXT_ALIGNS = TEXT_ALIGNS.slice(0, 2);
export const NEWS_COLUMNS = [
    { value: '1', label: '1 column (a list)' },
    { value: '2', label: '2-column grid' },
    { value: '3', label: '3-column grid (default)' },
    { value: '4', label: '4-column grid' },
    { value: 'masonry', label: 'Masonry grid' },
];
export const NEWS_IMAGE_POSITIONS = [
    { value: 'above', label: 'Above the text' },
    { value: 'below', label: 'Below the text' },
    { value: 'left', label: 'On the left' },
    { value: 'right', label: 'On the right' },
    { value: 'alternate', label: 'Alternating left and right' },
];
