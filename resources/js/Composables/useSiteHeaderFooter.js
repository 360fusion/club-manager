import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { MAX_FOOTER_COLUMN_LINKS, MAX_FOOTER_COLUMNS, normalizeFooterLinkColumns } from '@/Utils/pageBuilderSettings';

/**
 * The website builder's header and footer screen: its form, the custom footer link columns, the footer's chosen menu
 * pages, and the copyright and announcement previews.
 */
export function useSiteHeaderFooter(props, { flashSaved }) {
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
            onSuccess: flashSaved,
        });
    };

    const currentYear = new Date().getFullYear();

    const copyrightPreview = computed(() => {
        const holder = (headerFooterForm.footer_copyright_holder || props.club.name || '').trim().replace(/\.+$/, '');
        const text = headerFooterForm.footer_copyright_text.trim();
        return `© ${currentYear} ${holder}.${text ? ` ${text}` : ''}`;
    });

    // The social links and the custom link columns share a row when both are showing, so each is stacked inside its half.
    const footerDetailsSideBySide = computed(() => headerFooterForm.footer_layout === 'columns');

    const publishedNavPages = computed(() => props.pages.filter(p => p.show_in_navigation && p.is_published));
    const footerPinnedPages = computed(() => props.pages.filter(p => p.show_in_footer && p.lifecycle_status === 'live'));

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

    return {
        headerFooterForm, addFooterColumn, removeFooterColumn, addFooterLink, removeFooterLink, submitHeaderFooter, currentYear, copyrightPreview,
        footerDetailsSideBySide, publishedNavPages, footerPinnedPages, showNavChooser, footerNavPages, applyFooterNavPages, announcementPreview,
    };
}
