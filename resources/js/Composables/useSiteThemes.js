import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { SITE_LAYOUTS, SITE_COLOR_SCHEMES, LEGACY_THEME, DEFAULT_THEME_KEY, themeClasses, withSiteStyle, asColourScheme } from '@/Support/siteThemes';
import { useColourSchemeEditor } from '@/Composables/useColourSchemeEditor';

/**
 * The website builder's themes: the live theme, the layout and colour scheme picked for previewing, the font and corner
 * style form, the lodge's own colour schemes and the "apply this theme" confirmation. `previewInBuilder()` is called to
 * show the builder's page preview after a theme is picked for previewing.
 */
export function useSiteThemes(props, { flashSaved, previewInBuilder }) {
    const LAYOUTS = SITE_LAYOUTS;
    const LEGACY = LEGACY_THEME;
    // The lodge's own named colour schemes (Colour Scheme step), shown after the built-in ones.
    const customColourSchemes = computed(() => props.club?.settings?.custom_color_schemes || []);
    const ownColourSchemes = computed(() => customColourSchemes.value.map(asColourScheme).filter(Boolean));
    const COLOR_SCHEMES = computed(() => [...SITE_COLOR_SCHEMES, ...ownColourSchemes.value]);

    const currentThemeKey = computed(() => props.club?.settings?.website_theme || DEFAULT_THEME_KEY);

    // Live theme (not the "preview a different theme" one from the Themes tab) — used to preview header/footer
    // changes against whatever the club's site actually looks like right now.
    // The saved font and corner choices (Website Themes, step 3) laid over the live theme.
    const savedSiteStyle = computed(() => ({
        font_pairing: props.club?.settings?.font_pairing || 'theme',
        corner_style: props.club?.settings?.corner_style || 'theme',
    }));
    const liveThemeClasses = computed(() => withSiteStyle(themeClasses(currentThemeKey.value, customColourSchemes.value), savedSiteStyle.value));

    // A theme key is either the legacy `masonic` id, or `${layoutId}:${colorSchemeId}` — the layout and
    // colour scheme are picked independently in the gallery, then combined into that one string.
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
    const schemeEditor = useColourSchemeEditor({ club: props.club, colorSchemes: COLOR_SCHEMES, selectedColorSchemeId, customColourSchemes, currentThemeKey });

    const previewThemeInBuilder = (layoutId, colorSchemeId = null) => {
        selectedLayoutId.value = layoutId;
        if (layoutId !== LEGACY.id) {
            selectedColorSchemeId.value = colorSchemeId || selectedColorSchemeId.value || COLOR_SCHEMES.value[0].id;
        }
        previewInBuilder();
    };

    const effectivePreviewThemeKey = computed(() => keyFor(selectedLayoutId.value, selectedColorSchemeId.value));
    const previewThemeClasses = computed(() => withSiteStyle(themeClasses(effectivePreviewThemeKey.value, customColourSchemes.value), { font_pairing: themeForm.font_pairing, corner_style: themeForm.corner_style, theme_colors: schemeEditor.draftColours.value }));

    const applyPreviewTheme = (themeKey = null) => {
        const targetThemeKey = themeKey || effectivePreviewThemeKey.value;
        themeForm.website_theme = targetThemeKey;
        themeForm.post(route('admin.pages.themes.update', { clubSlug: props.club.slug }), {
            preserveScroll: true,
            onSuccess: flashSaved,
        });
    };

    const openApplyThemeModal = (themeKey, label) => {
        selectedThemeForModal.value = { key: themeKey, label };
        showThemeConfirmModal.value = true;
    };

    const closeApplyThemeModal = () => {
        showThemeConfirmModal.value = false;
        selectedThemeForModal.value = null;
    };

    const confirmApplyTheme = () => {
        if (!selectedThemeForModal.value) return;
        applyPreviewTheme(selectedThemeForModal.value.key);
        closeApplyThemeModal();
    };

    const styleChanged = computed(() => themeForm.font_pairing !== savedSiteStyle.value.font_pairing || themeForm.corner_style !== savedSiteStyle.value.corner_style);

    const colorSchemeName = (id) => COLOR_SCHEMES.value.find(c => c.id === id)?.name || '';
    const layoutName = (id) => (id === LEGACY.id ? LEGACY.name : LAYOUTS.find(l => l.id === id)?.name || '');
    const themeLabel = (key) => {
        if (key === LEGACY.id) return LEGACY.name;
        const { layoutId, colorSchemeId } = parseKey(key);
        return `${layoutName(layoutId)} — ${colorSchemeName(colorSchemeId)}`;
    };

    return {
        LAYOUTS, LEGACY, COLOR_SCHEMES, customColourSchemes, currentThemeKey, liveThemeClasses, selectedLayoutId, selectedColorSchemeId,
        selectedThemeForModal, showThemeConfirmModal, themeForm, ...schemeEditor, previewThemeInBuilder, effectivePreviewThemeKey,
        previewThemeClasses, applyPreviewTheme, openApplyThemeModal, closeApplyThemeModal, confirmApplyTheme, styleChanged, themeLabel,
    };
}
