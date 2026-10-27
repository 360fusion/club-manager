<script setup>
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

// The kit's field look; the layout and scheme ids keep their own :value/@change handling.
const selectField = 'rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-900 cursor-pointer focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
</script>

<template>
    <div class="space-y-4">
        <!-- Theme preview toolbar -->
        <Card padding="sm" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3 flex-wrap">
                <span id="theme-preview-select-label" class="text-xs font-medium text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                    <UiIcon name="palette" :size="14" class="text-slate-500 dark:text-slate-400" />
                    <span>Previewing</span>
                </span>
                <select
                    :value="selectedLayoutId"
                    @change="e => selectedLayoutId = e.target.value"
                    aria-labelledby="theme-preview-select-label"
                    :class="selectField"
                >
                    <option :value="legacy.id">{{ legacy.name }}</option>
                    <option v-for="l in layouts" :key="l.id" :value="l.id">{{ l.name }}</option>
                </select>
                <select
                    v-if="selectedLayoutId !== legacy.id"
                    :value="selectedColorSchemeId"
                    @change="e => selectedColorSchemeId = e.target.value"
                    aria-label="Colour scheme"
                    :class="selectField"
                >
                    <option v-for="c in colorSchemes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <Badge v-if="effectivePreviewThemeKey === currentThemeKey" variant="success">Live on the site</Badge>
            </div>

            <div class="flex items-center gap-2 shrink-0 flex-nowrap">
                <Button
                    v-if="effectivePreviewThemeKey !== currentThemeKey"
                    size="sm"
                    class="whitespace-nowrap shrink-0"
                    @click="applyPreviewTheme()"
                >
                    <UiIcon name="sparkles" :size="14" />
                    <span>Apply this theme</span>
                </Button>
                <a
                    :href="`/site/${club.slug}?preview_theme=${effectivePreviewThemeKey}`"
                    target="_blank"
                    class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 whitespace-nowrap shrink-0"
                >
                    <UiIcon name="globe" :size="14" />
                    <span>Open the full site</span>
                    <UiIcon name="external-link" :size="14" />
                </a>
            </div>
        </Card>

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
