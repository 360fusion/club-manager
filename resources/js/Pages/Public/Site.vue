<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import { themeClasses, DEFAULT_THEME_KEY } from '@/Support/siteThemes';

const props = defineProps({
    club: Object,
    page: Object,
    site: { type: Object, default: () => ({}) },
    navigation: Array,
    latestPosts: Array,
    upcomingEvents: Array,
    membershipPlans: Array,
    donations: Array,
    previewTheme: String,
});

const currentThemeKey = computed(() => props.previewTheme || props.club?.website_theme || DEFAULT_THEME_KEY);
const theme = computed(() => themeClasses(currentThemeKey.value));

const pageTitle = computed(() => `${props.page.meta_title || props.page.title} ${props.site.title_suffix || '- ' + props.club.name}`);
const metaDescription = computed(() => props.page.meta_description || props.site.meta_description || '');

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
    <Head :title="pageTitle">
        <meta v-if="metaDescription" name="description" :content="metaDescription" />
    </Head>

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

        <!-- Public Website Header / Nav -->
        <PublicHeader
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
                :interactive="true"
                :resolve-url="getSiteUrl"
            />
        </main>

        <!-- Public Footer -->
        <PublicFooter
            :club="club"
            :theme="theme"
            :navigation="navigation"
            :settings="site"
            :resolve-url="getSiteUrl"
            :interactive="true"
        />
    </div>
</template>
