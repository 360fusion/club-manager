<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import BlockRenderer from '@/Components/Blocks/BlockRenderer.vue';
import { themeClasses } from '@/Support/siteThemes';

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

const currentThemeKey = computed(() => props.previewTheme || props.club?.settings?.website_theme || 'classic');
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

    <div :class="['min-h-screen transition-colors duration-300', theme.wrapper]">
        <!-- Theme Preview Top Bar -->
        <div v-if="previewTheme" class="bg-amber-400 text-amber-950 px-4 py-2.5 text-center text-xs font-bold sticky top-0 z-[100] flex items-center justify-center gap-3 shadow-md border-b border-amber-500/30">
            <span>👁️ Theme Preview Mode: <strong>{{ currentThemeKey }}</strong></span>
            <span class="text-[11px] opacity-80">(This theme is currently being previewed and is not applied live to visitors.)</span>
            <Link :href="`/${club.slug}/admin/pages`" class="ml-2 px-2.5 py-1 rounded bg-amber-950 text-white font-bold hover:bg-black transition-colors text-[11px]">
                ⚙️ Back to Website Builder
            </Link>
        </div>

        <!-- Public Website Header / Nav -->
        <header :class="theme.header">
            <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
                <Link :href="getSiteUrl(`/site/${club.slug}`)" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-500 to-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform">
                        🏆
                    </div>
                    <div>
                        <div class="font-extrabold text-lg transition-colors">
                            {{ club.name }}
                        </div>
                        <div v-if="club.tagline" class="text-xs opacity-75">
                            {{ club.tagline }}
                        </div>
                    </div>
                </Link>

                <!-- Navigation Links -->
                <nav class="flex items-center gap-1 sm:gap-2">
                    <Link
                        v-for="item in navigation"
                        :key="item.id"
                        :href="getSiteUrl(item.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${item.slug}`)"
                        :class="page.slug === item.slug || (page.is_homepage && item.is_homepage) ? theme.navActive : theme.navInactive"
                        class="px-3 py-1.5 rounded-xl text-sm font-semibold border transition-all"
                    >
                        {{ item.title }}
                    </Link>

                    <Link :href="`/${club.slug}/admin/subscriptions`" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        💳 Subscriptions
                    </Link>

                    <Link href="/admin/profile" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        ⚙️ Profile & 2FA
                    </Link>

                    <Link href="/login" class="px-3 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                        Log In
                    </Link>

                    <Link
                        :href="`/${club.slug}/overview`"
                        class="ml-2 px-3.5 py-1.5 rounded-xl bg-slate-900 dark:bg-slate-700 border border-slate-800 text-slate-300 hover:text-white text-xs font-semibold"
                    >
                        🔒 Admin Portal
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Page blocks -->
        <main class="py-8">
            <div class="max-w-7xl mx-auto px-6">
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
            </div>
        </main>

        <!-- Public Footer -->
        <footer :class="['mt-20 border-t py-10 text-center text-xs space-y-2', theme.footer]">
            <p>{{ site.footer_copyright || `© ${new Date().getFullYear()} ${club.name}. All rights reserved.` }}</p>
        </footer>
    </div>
</template>
