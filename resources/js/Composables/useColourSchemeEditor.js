import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { SITE_COLOR_SCHEMES, contrastRatio, isHexColour, paletteForScheme, paletteFromBrand } from '@/Support/siteThemes';

/**
 * The lodge's own colour schemes in the website builder: a name and two colours, saved beside the built-in ones.
 * `colorSchemes` (every scheme on offer), `selectedColorSchemeId`, `customColourSchemes` and `currentThemeKey` are the
 * page's refs, which the editor reads and, when a scheme is saved or removed, moves the selection of.
 */
export function useColourSchemeEditor({ club, colorSchemes, selectedColorSchemeId, customColourSchemes, currentThemeKey }) {
    // ---- the lodge's own colour schemes: a name and two colours, saved beside the built-in ones --------------------
    const selectedScheme = computed(() => colorSchemes.value.find((c) => c.id === selectedColorSchemeId.value) || colorSchemes.value[0]);
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
        schemeForm.post(route('admin.pages.colour_schemes.save', { clubSlug: club.slug }), {
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
        router.delete(route('admin.pages.colour_schemes.delete', { clubSlug: club.slug, schemeId: scheme.id }), {
            preserveScroll: true,
            onSuccess: () => { if (selectedColorSchemeId.value === scheme.id) selectedColorSchemeId.value = SITE_COLOR_SCHEMES[0].id; },
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

    return { MAX_CUSTOM_SCHEMES, canCreateScheme, schemeEditorOpen, schemeError, schemeForm, openNewScheme, editScheme, closeSchemeEditor, saveScheme, deleteScheme, draftColours, draftPalette, draftColoursClose, usedByLiveSite, SCHEME_SHADES };
}
