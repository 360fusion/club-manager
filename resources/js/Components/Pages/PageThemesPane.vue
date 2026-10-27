<script setup>
import { CORNER_STYLES, FONT_PAIRINGS } from '@/Support/siteThemes';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A section of the website builder page, in its own file so the page stays readable.
const selectedColorSchemeId = defineModel('selectedColorSchemeId', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedLayoutId = defineModel('selectedLayoutId', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    club: { type: [Object, Array], default: undefined },
    layouts: { type: [Object, Array], default: undefined },
    colorSchemes: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    legacy: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    maxCustomSchemes: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    schemeShades: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canCreateScheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeSchemeEditor: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    currentThemeKey: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    customColourSchemes: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteScheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    draftColours: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    draftColoursClose: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    draftPalette: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    editScheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    effectivePreviewThemeKey: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openApplyThemeModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openNewScheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewThemeInBuilder: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    saveScheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    schemeEditorOpen: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    schemeError: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    schemeForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    styleChanged: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    themeForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    themeLabel: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    usedByLiveSite: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Card padding="lg" class="space-y-8">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-4 flex-wrap gap-3">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">Layout and colour scheme</h2>
                    <Badge variant="info">Active: {{ themeLabel(currentThemeKey) }}</Badge>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pick a layout for your site's structure, then a colour scheme to paint it. All page content, news, events, and forms remain unchanged.</p>
            </div>
        </div>

        <!-- Step 1: Layout -->
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">1. Layout</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <button
                    v-for="opt in [legacy, ...layouts]"
                    :key="opt.id"
                    type="button"
                    @click="selectedLayoutId = opt.id"
                    :class="[
                        'text-left rounded-2xl border p-5 space-y-3 transition-all duration-200 cursor-pointer',
                        selectedLayoutId === opt.id ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-md bg-blue-50/20 dark:bg-blue-950/20' : 'border-slate-200/90 dark:border-slate-800/90 hover:border-slate-300 dark:hover:border-slate-700 bg-white dark:bg-slate-900 shadow-sm hover:shadow-md'
                    ]"
                >
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <h4 class="font-semibold text-sm text-slate-900 dark:text-white">{{ opt.name }}</h4>
                        <Badge v-if="selectedLayoutId === opt.id" variant="info">Selected</Badge>
                    </div>
                    <Badge>{{ opt.badge }}</Badge>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ opt.description }}</p>
                    <div v-if="opt.id === legacy.id" class="flex items-center gap-1.5 pt-1">
                        <span v-for="(color, cIdx) in opt.palette" :key="cIdx" :style="{ backgroundColor: color }" class="w-3.5 h-3.5 rounded-full border border-slate-400/40 shadow-sm" :title="color"></span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 ml-1 font-medium">Fixed colours — no colour scheme</span>
                    </div>
                    <div v-else class="flex flex-wrap gap-1.5 pt-1">
                        <Badge v-for="(feat, fIdx) in opt.features" :key="fIdx">{{ feat }}</Badge>
                    </div>
                </button>
            </div>
        </div>

        <!-- Step 2: Colour scheme: the built-in ones and the lodge's own (any layout except the fixed-colour legacy one) -->
        <div v-if="selectedLayoutId !== legacy.id" class="space-y-3">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">2. Colour scheme</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pick a ready-made scheme, or create your own: give it a name and choose two colours, and every other shade (dark sections, light bands, text, buttons and dark mode) is worked out from them. Your schemes are saved here so you can switch between them.</p>

            <div class="grid grid-cols-2 items-stretch gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <div
                    v-for="c in colorSchemes"
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
                            <span class="text-xs font-semibold leading-snug text-slate-900 dark:text-white">{{ c.name }}</span>
                            <UiIcon v-if="selectedColorSchemeId === c.id" name="check" class="text-blue-600 dark:text-blue-400" />
                        </div>
                    </button>
                    <div class="flex h-9 items-center justify-between gap-2 border-t border-slate-200 px-4 dark:border-slate-800">
                        <span class="truncate text-[10px] font-medium text-slate-400">{{ c.custom ? (usedByLiveSite(c) ? 'Your scheme · live' : 'Your scheme') : 'Ready-made' }}</span>
                        <span v-if="c.custom" class="flex gap-1">
                            <button type="button" :aria-label="`Edit ${c.name}`" title="Rename or change colours" class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" @click="editScheme(c)"><UiIcon name="pencil" :size="14" /></button>
                            <button type="button" :aria-label="`Delete ${c.name}`" title="Delete" class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-xl text-slate-500 hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-400" @click="deleteScheme(c)"><UiIcon name="trash" :size="14" /></button>
                        </span>
                    </div>
                </div>

                <button v-if="canCreateScheme" type="button" class="flex h-full min-h-[8.5rem] cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-slate-300 p-4 text-center text-xs font-medium text-slate-500 transition-colors hover:border-blue-400 hover:text-blue-600 dark:border-slate-700 dark:text-slate-400" @click="openNewScheme">
                    <UiIcon name="plus" :size="20" />
                    Create your own colour scheme
                </button>
            </div>
            <p v-if="!canCreateScheme" class="text-[11px] text-slate-400">You can keep {{ maxCustomSchemes }} colour schemes of your own. Delete one to create another.</p>
            <p v-if="schemeError" class="text-xs font-medium text-rose-600 dark:text-rose-400" role="alert">{{ schemeError }}</p>

            <form v-if="schemeEditorOpen" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50" @submit.prevent="saveScheme">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ customColourSchemes.some((x) => x.id === schemeForm.id) ? 'Edit colour scheme' : 'New colour scheme' }}</h4>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <Input v-model="schemeForm.name" label="Name" maxlength="40" placeholder="e.g. Lodge crimson" :error="schemeForm.errors.name" />
                    <div v-for="field in [{ key: 'primary', label: 'Dark colour', hint: 'Hero, dark sections, footer, headings' }, { key: 'accent', label: 'Accent colour', hint: 'Buttons, labels, dividers, icons' }]" :key="field.key" class="space-y-1.5">
                        <label :for="`scheme-${field.key}`" class="block text-xs font-medium text-slate-700 dark:text-slate-300">{{ field.label }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" :value="schemeForm[field.key] || '#000000'" class="h-10 w-14 cursor-pointer rounded-xl border border-slate-300 bg-white p-0.5 dark:border-slate-700 dark:bg-slate-950" :aria-label="`Pick the ${field.label.toLowerCase()}`" @input="schemeForm[field.key] = $event.target.value" />
                            <Input :id="`scheme-${field.key}`" v-model="schemeForm[field.key]" maxlength="7" placeholder="#rrggbb" class="w-28 font-mono" />
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ field.hint }}</p>
                        <p v-if="schemeForm.errors[field.key]" class="text-xs text-rose-600 dark:text-rose-400">Enter the colour as #rrggbb.</p>
                    </div>
                </div>

                <div v-if="draftPalette" class="flex flex-wrap gap-2" aria-label="Colours worked out from your two">
                    <div v-for="[key, label] in schemeShades" :key="key" class="w-20 text-center">
                        <span :style="{ backgroundColor: draftPalette[key] }" class="block h-8 rounded-lg border border-slate-400/30 shadow-sm"></span>
                        <span class="mt-1 block text-[10px] font-semibold leading-tight text-slate-500 dark:text-slate-400">{{ label }}</span>
                    </div>
                </div>
                <p v-else class="text-[11px] font-medium text-amber-700 dark:text-amber-300">Enter both colours as #rrggbb to see the scheme.</p>
                <p v-if="schemeForm.errors.id" class="text-[11px] font-medium text-rose-600 dark:text-rose-400" role="alert">This colour scheme could not be saved. Close the form and try again.</p>
                <p v-if="draftColoursClose" class="text-[11px] font-medium text-amber-700 dark:text-amber-300">These two colours are very close, so accents will be hard to see on dark sections.</p>

                <div class="flex flex-wrap items-center gap-2">
                    <Button type="submit" variant="secondary" size="sm" :disabled="schemeForm.processing || !draftColours">Save colour scheme</Button>
                    <Button variant="ghost" size="sm" @click="closeSchemeEditor">Cancel</Button>
                    <span class="text-[11px] text-slate-400">Saving adds it to the list above; use “Apply theme to live site” to put it on your website.</span>
                </div>
            </form>
        </div>

        <!-- Step 3: Fonts and corners (layered over any theme) -->
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">3. Fonts and corners</h3>
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
                    <span class="flex items-center gap-1 font-semibold text-xs text-slate-900 dark:text-white">{{ f.name }} <UiIcon v-if="themeForm.font_pairing === f.id" name="check" :size="14" class="text-blue-600 dark:text-blue-400" /></span>
                    <span class="block text-[10px] text-slate-500 dark:text-slate-400">{{ f.sample }}</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Corner style">
                <span class="font-medium text-xs text-slate-700 dark:text-slate-300 mr-1">Corners</span>
                <button
                    v-for="c in CORNER_STYLES"
                    :key="c.id"
                    type="button"
                    role="radio"
                    :aria-checked="themeForm.corner_style === c.id"
                    @click="themeForm.corner_style = c.id"
                    :class="['px-3 py-1.5 rounded-xl border text-xs font-medium cursor-pointer', themeForm.corner_style === c.id ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' : 'border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']"
                >{{ c.name }}</button>
            </div>
            <p v-if="themeForm.errors.font_pairing || themeForm.errors.corner_style" class="text-xs text-rose-600 dark:text-rose-400 mt-1">{{ themeForm.errors.font_pairing || themeForm.errors.corner_style }}</p>
        </div>

        <!-- Preview & apply -->
        <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500 dark:text-slate-400">Previewing: <strong class="font-semibold text-slate-800 dark:text-slate-100">{{ themeLabel(effectivePreviewThemeKey) }}</strong></p>
            <div class="flex items-center gap-2 flex-wrap">
                <Button
                    variant="secondary"
                    @click="previewThemeInBuilder(selectedLayoutId, selectedColorSchemeId)"
                    title="Preview this combination in the website builder"
                >
                    <template #icon><UiIcon name="eye" /></template>
                    Preview in builder
                </Button>
                <a
                    :href="`/site/${club.slug}?preview_theme=${effectivePreviewThemeKey}`"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    title="Open a full preview of this combination in a new tab"
                >
                    <UiIcon name="external-link" />
                    Live preview
                </a>

                <Badge v-if="effectivePreviewThemeKey === currentThemeKey && !styleChanged" variant="success">
                    <UiIcon name="check" :size="14" />
                    Active theme
                </Badge>

                <Button
                    v-else
                    @click="openApplyThemeModal(effectivePreviewThemeKey, themeLabel(effectivePreviewThemeKey))"
                >
                    Apply this combination
                </Button>
            </div>
        </div>
    </Card>
</template>
