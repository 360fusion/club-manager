<script setup>
// Renders a club's public-site footer. Used by both the website builder's live preview (Admin/PageList.vue,
// interactive: false) and the real public page (Public/Site.vue, interactive: true) — see PublicHeader.vue.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    club: { type: Object, required: true },
    theme: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
    // Pages the club pinned to the footer menu, separate from the top menu.
    footerLinks: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
    resolveUrl: { type: Function, default: (url) => url },
    interactive: { type: Boolean, default: true },
});

const Tag = computed(() => (props.interactive ? Link : 'span'));
// Social links and custom link-column links are external or arbitrary, so they use a plain anchor rather
// than Inertia's Link even when interactive.
const SocialTag = computed(() => (props.interactive ? 'a' : 'span'));
const LinkTag = SocialTag;

const isColumns = computed(() => props.settings.footer_layout === 'columns');
// Text the club writes to sit under its name on the left of the footer; the club's tagline is used until it does.
const aboutText = computed(() => (props.settings.footer_about_text || '').trim() || props.club.tagline || '');
const showSocial = computed(() => props.settings.footer_show_social ?? true);
const showNav = computed(() => props.settings.footer_show_nav ?? false);

const socialLinks = computed(() => [
    { key: 'social_facebook', label: 'Facebook', url: props.settings.social_facebook },
    { key: 'social_instagram', label: 'Instagram', url: props.settings.social_instagram },
    { key: 'social_twitter', label: 'X', url: props.settings.social_twitter },
    { key: 'social_youtube', label: 'YouTube', url: props.settings.social_youtube },
    { key: 'social_linkedin', label: 'LinkedIn', url: props.settings.social_linkedin },
    { key: 'social_tiktok', label: 'TikTok', url: props.settings.social_tiktok },
    { key: 'social_whatsapp', label: 'WhatsApp', url: props.settings.social_whatsapp },
].filter((s) => !!s.url));

// Custom admin-defined columns (e.g. "Useful Links"), only shown in the Columns layout. Columns without a
// title, and links without a label or url, are dropped rather than rendered half-empty.
const linkColumns = computed(() => (props.settings.footer_show_custom_columns === false ? [] : (props.settings.footer_link_columns || []))
    .filter((col) => col.title && (col.links || []).some((l) => l.label && l.url))
    .map((col) => ({ ...col, links: (col.links || []).filter((l) => l.label && l.url) })));

const footerHref = (item) => props.resolveUrl(item.is_homepage ? `/site/${props.club.slug}` : `/site/${props.club.slug}/${item.slug}`);

// The "Navigate" column lists every menu page unless the club picked some (footer_nav_page_ids).
const footerNavItems = computed(() => (Array.isArray(props.settings.footer_nav_page_ids)
    ? props.navigation.filter((item) => props.settings.footer_nav_page_ids.includes(item.id))
    : props.navigation));

// How many columns the footer shows: the club's own, the menu pages, the pinned pages, each custom column, and social links.
const columnCount = computed(() => 1
    + (props.footerLinks.length ? 1 : 0)
    + (showNav.value && footerNavItems.value.length ? 1 : 0)
    + linkColumns.value.length
    + (showSocial.value && socialLinks.value.length ? 1 : 0));

const navHref = (item) => props.resolveUrl(item.is_homepage ? `/site/${props.club.slug}` : `/site/${props.club.slug}/${item.slug}`);

// "© 2026 The Lodge of Fraternity. All rights reserved." The year is worked out here, when the page is shown, so it is
// always the current one and nobody has to edit it. Keep in step with App\Support\FooterCopyright::line().
const copyright = computed(() => {
    const holder = String(props.settings.footer_copyright_holder || props.club.name || '').replace(/\.+$/, '');
    const text = props.settings.footer_copyright_text ?? 'All rights reserved.';

    return `© ${new Date().getFullYear()} ${holder}.${text ? ` ${text}` : ''}`;
});
</script>

<template>
    <footer :class="['mt-20 border-t text-xs', theme.footer]">
        <div class="max-w-7xl mx-auto px-6 py-10">
            <!-- Every column gets the same share of the width: one equal slice per column, on one row up to five -->
            <div v-if="isColumns" :style="{ '--footer-cols': Math.min(columnCount, 5) }" class="grid gap-x-8 gap-y-8 pb-8 text-left grid-cols-1 sm:grid-cols-2 md:grid-cols-[repeat(var(--footer-cols),minmax(0,1fr))]">
                <div class="min-w-0">
                    <div :class="['font-extrabold text-sm mb-1', theme.footerHeading || theme.headingText]">{{ club.name }}</div>
                    <p v-if="aboutText" :class="['whitespace-pre-line break-words', theme.footerBody || theme.bodyText]">{{ aboutText }}</p>
                </div>

                <div v-if="footerLinks.length">
                    <div class="font-bold uppercase tracking-wider text-[10px] opacity-95 mb-2">Quick links</div>
                    <ul class="space-y-1.5">
                        <li v-for="item in footerLinks" :key="item.id">
                            <component :is="Tag" :href="interactive ? footerHref(item) : undefined" class="inline-block py-1 hover:underline">
                                {{ item.title }}
                            </component>
                        </li>
                    </ul>
                </div>

                <div v-if="showNav && footerNavItems.length">
                    <div class="font-bold uppercase tracking-wider text-[10px] opacity-95 mb-2">Navigate</div>
                    <ul class="space-y-1.5">
                        <li v-for="item in footerNavItems" :key="item.id">
                            <component :is="Tag" :href="interactive ? navHref(item) : undefined" class="inline-block py-1 hover:underline">
                                {{ item.title }}
                            </component>
                        </li>
                    </ul>
                </div>

                <div v-for="col in linkColumns" :key="col.id">
                    <div class="font-bold uppercase tracking-wider text-[10px] opacity-95 mb-2">{{ col.title }}</div>
                    <ul class="space-y-1.5">
                        <li v-for="link in col.links" :key="link.id">
                            <component :is="LinkTag" :href="interactive ? resolveUrl(link.url) : undefined" class="inline-block py-1 hover:underline">
                                {{ link.label }}
                            </component>
                        </li>
                    </ul>
                </div>

                <div v-if="showSocial && socialLinks.length">
                    <div class="font-bold uppercase tracking-wider text-[10px] opacity-95 mb-2">Follow</div>
                    <ul class="space-y-1.5">
                        <li v-for="s in socialLinks" :key="s.key">
                            <component :is="SocialTag" :href="interactive ? s.url : undefined" target="_blank" rel="noopener" class="inline-block py-2 hover:underline">
                                {{ s.label }}
                            </component>
                        </li>
                    </ul>
                </div>
            </div>

            <p v-if="!isColumns && (settings.footer_about_text || '').trim()" :class="['text-center whitespace-pre-line break-words max-w-2xl mx-auto pb-5', theme.footerBody || theme.bodyText]">{{ settings.footer_about_text.trim() }}</p>

            <nav v-if="!isColumns && footerLinks.length" aria-label="Footer" class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1 pb-5">
                <component v-for="item in footerLinks" :key="item.id" :is="Tag" :href="interactive ? footerHref(item) : undefined" class="inline-block py-2 font-semibold hover:underline">
                    {{ item.title }}
                </component>
            </nav>

            <div v-if="!isColumns && showSocial && socialLinks.length" class="flex items-center justify-center gap-5 pb-5">
                <component
                    v-for="s in socialLinks"
                    :key="s.key"
                    :is="SocialTag"
                    :href="interactive ? s.url : undefined"
                    target="_blank"
                    rel="noopener"
                    class="inline-block py-2.5 px-1 font-semibold hover:underline"
                >
                    {{ s.label }}
                </component>
            </div>

            <p :class="['text-center', isColumns ? 'pt-6 border-t border-current/10' : '']">{{ copyright }}</p>
        </div>
    </footer>
</template>
