// The element types a page can hold: the palette shown in the insert menu, and the starting content of each new element.

export const BLOCK_TYPES = [
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
    { type: 'media_text', icon: '🖼️', label: 'Image & Text', desc: 'Photo beside a title and formatted text', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
];

export const BLOCK_COLUMNS = [
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
            { type: 'media_text', icon: '🖼️', label: 'Image & Text', desc: 'Photo beside a title and formatted text', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-950/40' },
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
];

/**
 * A new, empty element of the given type, or null for an unknown type.
 * `context` is the page's club, the club's addresses and the website settings, which some elements start from.
 */
export const newBlock = (type, { club, clubAddresses, websiteSettings }) => {
    const id = 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4);
    let block = null;

    if (type === 'text' || type === 'rich_text') {
        block = { id, type: 'text', heading: '', content: '', text_align: 'left' };
    } else if (type === 'image') {
        block = { id, type: 'image', url: '', caption: '', position: 'center', size: 'large' };
    } else if (type === 'images') {
        block = {
            id,
            type: 'images',
            columns: 3,
            items: [
                { id: id + '-g1', url: '', caption: '' },
                { id: id + '-g2', url: '', caption: '' }
            ]
        };
    } else if (type === 'notice') {
        block = { id, type: 'notice', style: 'info', title: 'Important Notice', text: '' };
    } else if (type === 'button') {
        block = { id, type: 'button', label: 'Learn more →', url: '', align: 'center' };
    } else if (type === 'youtube') {
        block = {
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
        block = {
            id, type: 'cta_banner', eyebrow: '', heading: 'Interested in joining us?', text: '',
            button_label: 'Find out more', button_url: `/site/${club.slug}/join-us`, button_new_tab: false,
            button2_label: '', button2_url: '', button2_new_tab: false,
            style: 'bold', overlay: 'medium', align: 'center', size: 'normal', image_url: '',
            side_image_url: '', image_shape: 'circle', image_side: 'left', text_style: 'normal',
        };
    } else if (type === 'faq') {
        block = {
            id, type: 'faq', heading: 'Frequently asked questions', intro: '', behaviour: 'single', columns: 1,
            first_open: false, numbered: false, show_search: true,
            items: [{ id: id + '-q1', question: '', answer: '' }],
        };
    } else if (type === 'map') {
        block = {
            id, type: 'map', heading: '', location_name: '', address: clubAddresses[0]?.address || '', notes: '',
            lat: null, lng: null, zoom: 17, map_style: 'street', allow_style_switch: true, height: 'medium', layout: 'stacked', load_mode: 'click',
            show_directions: true, show_larger_link: true,
        };
    } else if (type === 'downloads') {
        block = {
            id, type: 'downloads', heading: 'Documents', intro: '', layout: 'list', sort: 'manual', open_in: 'new_tab',
            button_label: 'Download', show_type: true, show_size: true, show_date: false, show_search: false,
            members_only: false, items: [],
        };
    } else if (type === 'calendar') {
        block = {
            id, type: 'calendar', heading: "What's on", default_view: 'month', week_starts: 'monday', list_length: 10,
            allow_switch: true, show_times: true, show_location: true, show_price: false, show_subscribe: true,
        };
    } else if (type === 'hero') {
        block = {
            id,
            type: 'hero',
            title: 'Welcome to ' + club.name,
            subtitle: 'Join us for training, events and community.',
            cta_text: 'Explore Membership',
            cta_link: `/site/${club.slug}/join-us`,
            cta2_text: '', cta2_link: '', eyebrow: '', hide_eyebrow: false, image_url: '', overlay: 'medium', align: 'auto', height: 'normal',
        };
    } else if (type === 'slideshow') {
        block = {
            id, type: 'slideshow', height: 'normal', effect: 'zoom', interval: 5, overlay: 'medium', align: 'left',
            autoplay: true, pause_on_hover: true, show_dots: true, show_arrows: true, full_width: false, hero_look: false,
            eyebrow: '', heading: '', text: '', button_label: '', button_url: '', button2_label: '', button2_url: '',
            slides: [{ id: id + '-p1', image_url: '', alt: '', caption: '' }, { id: id + '-p2', image_url: '', alt: '', caption: '' }],
        };
    } else if (type === 'section_heading') {
        block = { id, type: 'section_heading', eyebrow: 'About us', title: 'A tradition of fellowship', intro: '', align: 'center', show_divider: true };
    } else if (type === 'feature_cards') {
        block = {
            id, type: 'feature_cards', eyebrow: '', heading: 'What we offer', intro: '', columns: 3, card_style: 'soft', icon_style: 'plain',
            align: 'center', show_divider: true, numbered: false,
            items: [
                { id: id + '-c1', icon: 'users', title: 'Brotherhood', text: 'A genuine sense of belonging among men from all walks of life.', link: '', link_label: '' },
                { id: id + '-c2', icon: 'heart', title: 'Charity', text: 'Active engagement in charitable events and community fundraising.', link: '', link_label: '' },
                { id: id + '-c3', icon: 'seedling', title: 'Personal growth', text: 'Opportunity to explore your potential and build confidence.', link: '', link_label: '' },
            ],
        };
    } else if (type === 'stats') {
        block = {
            id, type: 'stats', heading: '', icon_style: 'circle', count_up: true,
            items: [
                { id: id + '-s1', icon: 'landmark', number: '150', suffix: '+', label: 'Years of history' },
                { id: id + '-s2', icon: 'users', number: '40', suffix: '+', label: 'Members' },
                { id: id + '-s3', icon: 'calendar-check', number: '8', suffix: '', label: 'Meetings per year' },
            ],
        };
    } else if (type === 'media_text') {
        block = { id, type: 'media_text', eyebrow: '', title: '', image_url: '', image_alt: '', layout: 'image_left', show_divider: true, content: '', button_label: '', button_url: '' };
    } else if (type === 'quote_motto') {
        block = { id, type: 'quote_motto', heading: 'Our motto', text: '', tagline: '', show_divider: true };
    } else if (type === 'news_feed' || type === 'news_list') {
        block = { id, type: 'news_feed', heading: 'Latest Club News', columns: '3', limit: 6 };
    } else if (type === 'events_calendar') {
        block = { id, type: 'events_calendar', heading: 'Upcoming events and meetings', limit: 3 };
    } else if (type === 'pricing_cards') {
        block = { id, type: 'pricing_cards', heading: 'Membership options and dues' };
    } else if (type === 'donation_campaign') {
        block = { id, type: 'donation_campaign', heading: 'Active fundraising campaign' };
    } else if (type === 'contact_details') {
        block = {
            id,
            type: 'contact_details',
            eyebrow: 'CONTACT',
            title: 'Get in touch',
            description: `Whether you're interested in joining ${club.name}, a visiting member, or simply want to learn more, we'd be delighted to hear from you.`,
            email_heading: 'Email',
            email: '',
            times_heading: 'Meeting times',
            times: websiteSettings?.meeting_formula || club.settings?.meeting_formula || '7:00 pm, 4th Thursday\nSept–Nov & Jan–May\nInstallation: April, 5:30 pm',
            location_heading: 'Location',
            location: websiteSettings?.address || club.address || '',
        };
    } else if (type === 'contact_form') {
        block = {
            id,
            type: 'contact_form',
            heading: 'Send us a message',
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

    return block;
};
