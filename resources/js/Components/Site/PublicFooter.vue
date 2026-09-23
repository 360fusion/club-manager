<script setup>
// Renders a club's public-site footer. Used by both the website builder's live preview (Admin/PageList.vue,
// interactive: false) and the real public page (Public/Site.vue, interactive: true) — see PublicHeader.vue.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    club: { type: Object, required: true },
    theme: { type: Object, required: true },
    navigation: { type: Array, default: () => [] },
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
const showSocial = computed(() => props.settings.footer_show_social ?? true);
const showNav = computed(() => props.settings.footer_show_nav ?? false);

const socialLinks = computed(() => [
    { key: 'social_facebook', label: 'Facebook', url: props.settings.social_facebook },
    { key: 'social_instagram', label: 'Instagram', url: props.settings.social_instagram },
    { key: 'social_twitter', label: 'Twitter / X', url: props.settings.social_twitter },
].filter((s) => !!s.url));

// Custom admin-defined columns (e.g. "Useful Links"), only shown in the Columns layout. Columns without a
// title, and links without a label or url, are dropped rather than rendered half-empty.
const linkColumns = computed(() => (props.settings.footer_link_columns || [])
    .filter((col) => col.title && (col.links || []).some((l) => l.label && l.url))
    .map((col) => ({ ...col, links: (col.links || []).filter((l) => l.label && l.url) })));

const navHref = (item) => props.resolveUrl(item.is_homepage ? `/site/${props.club.slug}` : `/site/${props.club.slug}/${item.slug}`);

const copyright = computed(() => props.settings.footer_copyright || `© ${new Date().getFullYear()} ${props.club.name}. All rights reserved.`);
</script>

<template>
    <footer :class="['mt-20 border-t text-xs', theme.footer]">
        <div class="max-w-7xl mx-auto px-6 py-10">
            <div v-if="isColumns" class="grid gap-x-8 gap-y-8 pb-8 text-left grid-cols-[repeat(auto-fit,minmax(140px,1fr))]">
                <div>
                    <div :class="['font-extrabold text-sm mb-1', theme.headingText]">{{ club.name }}</div>
                    <p v-if="club.tagline" :class="[theme.bodyText]">{{ club.tagline }}</p>
                </div>

                <div v-if="showNav && navigation.length">
                    <div class="font-bold uppercase tracking-wider text-[10px] opacity-95 mb-2">Navigate</div>
                    <ul class="space-y-1.5">
                        <li v-for="item in navigation" :key="item.id">
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

            <div v-else-if="showSocial && socialLinks.length" class="flex items-center justify-center gap-5 pb-5">
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
