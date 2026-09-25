<script setup>
// The club's own website frame (theme, announcement bar, header, footer, cookie notice) around a page that is not
// one of its builder pages, such as an event. The page's content goes in the slot, which is given the resolved
// `theme` so it can use the same card, heading and button classes as the rest of the site.
import { computed } from 'vue';
import PublicHeader from '@/Components/Site/PublicHeader.vue';
import PublicFooter from '@/Components/Site/PublicFooter.vue';
import AnnouncementBar from '@/Components/Site/AnnouncementBar.vue';
import CookieNotice from '@/Components/Site/CookieNotice.vue';
import { themeClasses, withSiteStyle, DEFAULT_THEME_KEY } from '@/Support/siteThemes';

const props = defineProps({
    club: { type: Object, required: true },
    site: { type: Object, default: () => ({}) },
    navigation: { type: Array, default: () => [] },
    footerNavigation: { type: Array, default: () => [] },
});

const theme = computed(() => withSiteStyle(themeClasses(props.club.website_theme || DEFAULT_THEME_KEY, props.site.custom_color_schemes), props.site));
</script>

<template>
    <div :class="['min-h-screen transition-colors duration-300', theme.wrapper]" :style="theme.cssVars">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[200] focus:px-4 focus:py-2 focus:rounded-xl focus:bg-[var(--cm-accent-deep)] focus:text-white focus:font-bold focus:text-sm focus:shadow-lg">
            Skip to main content
        </a>

        <AnnouncementBar :announcement="site.announcement" :club-slug="club.slug" />

        <PublicHeader :club="club" :theme="theme" :navigation="navigation" :current-page="{}" :settings="site" :interactive="true" />

        <main id="main-content">
            <slot :theme="theme" />
        </main>

        <PublicFooter :club="club" :theme="theme" :navigation="navigation" :footer-links="footerNavigation" :settings="site" :interactive="true" />

        <CookieNotice v-if="site.tracking" :tracking="site.tracking" :club-slug="club.slug" />
    </div>
</template>
