<script setup>
// Renders a club's public-site header. Used by both the website builder's live preview (Admin/PageList.vue,
// interactive: false) and the real public page (Public/Site.vue, interactive: true), so what an admin
// configures is exactly what a visitor sees.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    club: { type: Object, required: true },
    theme: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    currentPage: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    resolveUrl: { type: Function, default: (url) => url },
    // false in the website builder's own preview, so clicking around a mockup header can never navigate you
    // away from the builder.
    interactive: { type: Boolean, default: true },
});

// A non-interactive preview renders plain <span>s instead of <Link>s so nothing is clickable.
const Tag = computed(() => (props.interactive ? Link : 'span'));

const isCentered = computed(() => props.settings.header_layout === 'logo_center');
const showLogo = computed(() => props.settings.header_show_logo ?? true);
const showTagline = computed(() => props.settings.header_show_tagline ?? true);
const showAccountLinks = computed(() => props.settings.header_show_account_links ?? true);
const ctaEnabled = computed(() => !!props.settings.header_cta_enabled && !!props.settings.header_cta_text && !!props.settings.header_cta_link);

const homeHref = computed(() => props.resolveUrl(`/site/${props.club.slug}`));

const navHref = (item) => props.resolveUrl(item.is_homepage ? `/site/${props.club.slug}` : `/site/${props.club.slug}/${item.slug}`);

const isActiveNavItem = (item) => props.currentPage?.slug === item.slug || (props.currentPage?.is_homepage && item.is_homepage);
</script>

<template>
    <header :class="theme.header">
        <div :class="['max-w-7xl mx-auto px-6 flex', isCentered ? 'flex-col items-center py-5 gap-4' : 'min-h-20 py-3 items-center justify-between flex-wrap gap-y-2']">
            <component :is="Tag" :href="interactive ? homeHref : undefined" class="flex items-center gap-3 group shrink-0">
                <template v-if="showLogo">
                    <img
                        v-if="club.logo_url"
                        :src="club.logo_url"
                        :alt="club.name"
                        class="w-10 h-10 rounded-xl object-cover shadow-lg group-hover:scale-105 transition-transform"
                    />
                    <div v-else class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-500 to-blue-600 flex items-center justify-center font-bold text-white shadow-lg shadow-blue-500/20 group-hover:scale-105 transition-transform">
                        🏆
                    </div>
                </template>
                <div>
                    <div :class="['font-extrabold text-lg transition-colors', theme.headingText]">{{ club.name }}</div>
                    <div v-if="showTagline && club.tagline" :class="['text-xs', theme.bodyText]">{{ club.tagline }}</div>
                </div>
            </component>

            <nav :class="['flex items-center gap-1 sm:gap-2 flex-wrap', isCentered ? 'justify-center' : '']">
                <component
                    :is="Tag"
                    v-for="item in navigation"
                    :key="item.id"
                    :href="interactive ? navHref(item) : undefined"
                    :class="isActiveNavItem(item) ? theme.navActive : theme.navInactive"
                    class="px-3 py-1.5 rounded-xl text-sm font-semibold border transition-all"
                >
                    {{ item.title }}
                </component>

                <component
                    :is="Tag"
                    v-if="ctaEnabled"
                    :href="interactive ? resolveUrl(settings.header_cta_link) : undefined"
                    :class="theme.heroCta"
                    class="ml-1 px-3.5 py-1.5 rounded-xl text-xs font-bold shadow transition-all"
                >
                    {{ settings.header_cta_text }}
                </component>

                <template v-if="showAccountLinks">
                    <component :is="Tag" :href="interactive ? '/login' : undefined" :class="theme.navInactive" class="px-3 py-1.5 text-xs font-semibold border border-transparent rounded-xl">
                        Log In
                    </component>
                    <component
                        :is="Tag"
                        :href="interactive ? `/${club.slug}/overview` : undefined"
                        :class="theme.cardBg"
                        class="ml-2 px-3.5 py-1.5 rounded-xl text-xs font-semibold"
                    >
                        🔒 Admin Portal
                    </component>
                </template>
            </nav>
        </div>
    </header>
</template>
