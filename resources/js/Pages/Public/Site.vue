<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import AnnouncementBar from '@/Components/Site/AnnouncementBar.vue';
import CookieNotice from '@/Components/Site/CookieNotice.vue';
import { themeClasses, withSiteStyle, DEFAULT_THEME_KEY } from '@/Support/siteThemes';

const props = defineProps({
    club: Object,
    page: Object,
    site: { type: Object, default: () => ({}) },
    navigation: Array,
    footerNavigation: { type: Array, default: () => [] },
    // Set when the page is opened through its private preview link (see PageAdminController::previewLink).
    preview: { type: Object, default: null },
    latestPosts: Array,
    upcomingEvents: Array,
    membershipPlans: Array,
    donations: Array,
    calendar: { type: Object, default: null },
    previewTheme: String,
});

const currentThemeKey = computed(() => props.previewTheme || props.club?.website_theme || DEFAULT_THEME_KEY);
const theme = computed(() => withSiteStyle(themeClasses(currentThemeKey.value), props.site));

const pageTitle = computed(() => `${props.page.meta_title || props.page.title} ${props.site.title_suffix || '- ' + props.club.name}`);

const getSiteUrl = (urlPath) => {
    if (!urlPath) return '';
    if (!props.previewTheme) return urlPath;
    if (urlPath.startsWith('/site/') || urlPath.includes('/site/')) {
        const separator = urlPath.includes('?') ? '&' : '?';
        return `${urlPath}${separator}preview_theme=${encodeURIComponent(props.previewTheme)}`;
    }
    return urlPath;
};
</script>

<template>
    <!-- The description, share image and the rest of the head are written by the server (app.blade.php). -->
    <Head :title="pageTitle" />

    <div :class="['min-h-screen transition-colors duration-300', theme.wrapper]" :style="theme.cssVars">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[200] focus:px-4 focus:py-2 focus:rounded-xl focus:bg-blue-600 focus:text-white focus:font-bold focus:text-sm focus:shadow-lg">
            Skip to main content
        </a>

        <!-- Theme Preview Top Bar -->
        <div v-if="previewTheme" class="bg-amber-400 text-amber-950 px-4 py-2.5 text-center text-xs font-bold sticky top-0 z-[100] flex items-center justify-center gap-3 shadow-md border-b border-amber-500/30">
            <span>👁️ Theme Preview Mode: <strong>{{ currentThemeKey }}</strong></span>
            <span class="text-[11px] opacity-80">(This theme is currently being previewed and is not applied live to visitors.)</span>
            <Link :href="`/${club.slug}/admin/pages`" class="ml-2 px-2.5 py-1 rounded bg-amber-950 text-white font-bold hover:bg-black transition-colors text-[11px]">
                ⚙️ Back to Website Builder
            </Link>
        </div>

        <AnnouncementBar :announcement="site.announcement" :club-slug="club.slug" />

        <!-- Draft preview of a page that may not be live yet -->
        <div v-if="preview" class="bg-violet-600 text-white px-4 py-2.5 text-center text-xs font-bold sticky top-0 z-[100] shadow-md">
            🔒 Draft preview<span v-if="preview.has_draft"> of the unpublished draft</span>. <span v-if="!preview.is_live" class="opacity-90">This page is not live, so only people with this link can see it.</span><span v-else class="opacity-90">Visitors see the live page.</span>
        </div>

        <!-- Public Website Header / Nav (a page can show only the logo, or no header at all) -->
        <PublicHeader
            v-if="page.header_style !== 'hidden'"
            :mode="page.header_style"
            :club="club"
            :theme="theme"
            :navigation="navigation"
            :current-page="page"
            :settings="site"
            :resolve-url="getSiteUrl"
            :interactive="true"
        />

        <!-- Page blocks -->
        <main id="main-content">
            <h1 class="sr-only">{{ page.meta_title || page.title }}</h1>
            <BlockRenderer
                :blocks="page.blocks"
                :theme="theme"
                :club="club"
                :latest-posts="latestPosts"
                :upcoming-events="upcomingEvents"
                :membership-plans="membershipPlans"
                :donations="donations"
                :calendar="calendar"
                :interactive="true"
                :resolve-url="getSiteUrl"
            />
        </main>

        <!-- Public Footer -->
        <PublicFooter
            :club="club"
            :theme="theme"
            :navigation="navigation"
            :footer-links="footerNavigation"
            :settings="site"
            :resolve-url="getSiteUrl"
            :interactive="true"
        />

        <CookieNotice v-if="site.tracking && !previewTheme && !preview" :tracking="site.tracking" :club-slug="club.slug" />
    </div>
</template>
