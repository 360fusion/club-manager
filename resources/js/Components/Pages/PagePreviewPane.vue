<script setup>
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';

// A section of the website builder page, in its own file so the page stays readable.
const selectedColorSchemeId = defineModel('selectedColorSchemeId', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedLayoutId = defineModel('selectedLayoutId', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    club: { type: [Object, Array], default: undefined },
    websiteSettings: { type: [Object, Array], default: undefined },
    latestPosts: { type: [Object, Array], default: undefined },
    upcomingEvents: { type: [Object, Array], default: undefined },
    membershipPlans: { type: [Object, Array], default: undefined },
    donations: { type: [Object, Array], default: undefined },
    calendar: { type: [Object, Array], default: undefined },
    colorSchemes: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    layouts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    legacy: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    applyPreviewTheme: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    currentThemeKey: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    effectivePreviewThemeKey: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    footerPinnedPages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    previewThemeClasses: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    publishedNavPages: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <div class="space-y-4">
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
                    <option :value="legacy.id">{{ legacy.name }}</option>
                    <option v-for="l in layouts" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
                <select
                    v-if="selectedLayoutId !== legacy.id"
                    :value="selectedColorSchemeId"
                    @change="e => selectedColorSchemeId = e.target.value"
                    aria-label="Colour scheme"
                    class="px-3.5 py-1.5 bg-slate-800 border border-slate-700 rounded-xl text-xs font-bold text-white outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer"
                >
                    <option v-for="c in colorSchemes" :key="c.id" :value="c.id">{{ c.name }}</option>
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
</template>
