import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

/**
 * The website builder's site-wide settings (Website & SEO settings, the custom domain check) and its redirects: old
 * addresses on this site (after /site/{club}) sent somewhere else.
 */
export function useSiteSettingsForms(props, { flashSaved }) {
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
            onSuccess: flashSaved,
        });
    };

    // The example shown in the custom domain box: built from this club's own address name, never another club's.
    const domainExample = computed(() => `www.${props.club?.slug || 'yourclub'}.org.uk`);

    const checkingDomain = ref(false);

    const checkDomainNow = () => {
        checkingDomain.value = true;
        router.post(route('admin.pages.settings.verify_domain', { clubSlug: props.club.slug }), {}, {
            preserveScroll: true,
            onFinish: () => { checkingDomain.value = false; },
        });
    };

    const redirectForm = useForm({ from_path: '', to_url: '', is_permanent: true });
    const redirects = computed(() => props.websiteSettings?.redirects || []);

    const addRedirect = () => {
        redirectForm.post(route('admin.pages.redirects.store', { clubSlug: props.club.slug }), {
            preserveScroll: true,
            onSuccess: () => { redirectForm.reset(); flashSaved(); },
        });
    };

    const removeRedirect = (id) => {
        router.delete(route('admin.pages.redirects.destroy', { clubSlug: props.club.slug, id }), { preserveScroll: true, onSuccess: flashSaved });
    };

    return { settingsForm, submitWebsiteSettings, domainExample, checkingDomain, checkDomainNow, redirectForm, redirects, addRedirect, removeRedirect };
}
