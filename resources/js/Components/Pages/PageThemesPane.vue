<script setup>
import { CORNER_STYLES, FONT_PAIRINGS } from '@/Support/siteThemes';

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
    <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-8">
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
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">{{ opt.name }}</h4>
                        <span v-if="selectedLayoutId === opt.id" class="px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-600 text-white shadow-sm">✓ SELECTED</span>
                    </div>
                    <span class="inline-block px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px] font-bold">{{ opt.badge }}</span>
                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">{{ opt.description }}</p>
                    <div v-if="opt.id === legacy.id" class="flex items-center gap-1.5 pt-1">
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
        <div v-if="selectedLayoutId !== legacy.id" class="space-y-3">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">2. Colour Scheme</h3>
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
            <p v-if="!canCreateScheme" class="text-[11px] text-slate-400">You can keep {{ maxCustomSchemes }} colour schemes of your own. Delete one to create another.</p>
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
                    <div v-for="[key, label] in schemeShades" :key="key" class="w-20 text-center">
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
</template>
