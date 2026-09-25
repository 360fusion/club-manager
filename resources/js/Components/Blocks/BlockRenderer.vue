<script setup>
// Renders a page's blocks. Used by both the website builder's live preview and the real public page
// (Public/Site.vue), so a block only has to be written once: what an admin previews is what a visitor sees.
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ObfuscatedEmail from '@/Components/ObfuscatedEmail.vue';
import { parseYouTubeUrl, youtubeEmbedUrl, youtubeThumbnail } from '@/Utils/youtube';
import CtaBannerBlock from '@/Components/Blocks/CtaBannerBlock.vue';
import FaqBlock from '@/Components/Blocks/FaqBlock.vue';
import MapBlock from '@/Components/Blocks/MapBlock.vue';
import DownloadsBlock from '@/Components/Blocks/DownloadsBlock.vue';
import CalendarBlock from '@/Components/Blocks/CalendarBlock.vue';
import HeroBlock from '@/Components/Blocks/HeroBlock.vue';
import SlideshowBlock from '@/Components/Blocks/SlideshowBlock.vue';
import PostHeaderBlock from '@/Components/Blocks/PostHeaderBlock.vue';
import PostAttachmentsBlock from '@/Components/Blocks/PostAttachmentsBlock.vue';
import FeatureCardsBlock from '@/Components/Blocks/FeatureCardsBlock.vue';
import StatsBlock from '@/Components/Blocks/StatsBlock.vue';
import QuoteMottoBlock from '@/Components/Blocks/QuoteMottoBlock.vue';
import SectionHeadingBlock from '@/Components/Blocks/SectionHeadingBlock.vue';
import { toneTheme } from '@/Support/siteThemes';
import { activeSection, sectionTone, sectionBackgroundClass, sectionBackgroundStyle, sectionOverlayClass, sectionPadding } from '@/Support/blockSection';

const props = defineProps({
    blocks: { type: Array, default: () => [] },
    theme: { type: Object, required: true },
    club: { type: Object, required: true },
    latestPosts: { type: Array, default: () => [] },
    upcomingEvents: { type: Array, default: () => [] },
    membershipPlans: { type: Array, default: () => [] },
    donations: { type: Array, default: () => [] },
    // The public calendar for the page's calendar block (month grid + upcoming list), only sent when the page has one.
    calendar: { type: Object, default: null },
    // false in the website builder's own preview: forms are shown but do nothing, so editing a page can
    // never accidentally email someone or take a donation.
    interactive: { type: Boolean, default: true },
    // Wraps an internal link so the "preview this theme" banner can carry the theme along as you click around.
    resolveUrl: { type: Function, default: (url) => url },
});

// ---- theme layout: structural differences beyond colour, see siteThemes.js ---------------------------------------
const isBanded = computed(() => props.theme.layout === 'banded');
const radiusLg = computed(() => props.theme.radiusLg || 'rounded-3xl');
const radiusMd = computed(() => props.theme.radiusMd || 'rounded-2xl');

// A banded theme renders every section full-bleed, alternating tint by position, with the hero always
// taking its own dedicated (non-alternating) band. A boxed/editorial theme keeps the original single
// centered container with vertical spacing between blocks.
//
// A block can also carry its own "Section" settings (see Support/blockSection.js): a full-width band with a colour,
// a photo or a dark navy look, or a boxed panel inside the page. Blocks left on "auto" are drawn exactly as the theme
// draws them; a block with its own band does not take a turn in the alternating white / tint sequence.
const BAND_INNER = 'relative max-w-7xl mx-auto px-6';

// The theme a block sees on its section: light or dark text, and on an accent-coloured band buttons switch to the dark
// brand colour (an accent button on an accent band would disappear).
const sectionTheme = (section, tone) => {
    if (!section) return props.theme;

    const base = toneTheme(props.theme, tone);

    return section.bg === 'accent'
        ? { ...base, heroCta: 'bg-[var(--cm-primary)] hover:brightness-125 text-white font-semibold uppercase tracking-[0.1em]' }
        : base;
};

const decorated = computed(() => {
    let slot = 0;

    // On the public site an element switched off in the builder is not drawn at all (the server already leaves it out);
    // the builder's own preview keeps it, dimmed and labelled, so it can be found and switched back on.
    const visible = props.blocks.map((block, index) => ({ block, index })).filter(({ block }) => !block.hidden || !props.interactive);

    return visible.map(({ block, index }) => {
        const section = block.type === 'hero' ? null : activeSection(block);
        // A slideshow set to "full width" runs edge to edge with no page padding, like a hero.
        const isEdge = block.type === 'slideshow' && !!block.full_width;
        const isBand = section?.mode === 'band' || isEdge;
        const isBoxed = section?.mode === 'contained';
        const tone = sectionTone(section, props.theme);
        const dark = tone === 'dark';
        const position = slot;
        if (!isBand) slot++;

        let outerClass = 'w-full';
        let innerClass = '';

        if (isEdge) {
            outerClass = 'w-full';
            innerClass = '';
        } else if (isBand) {
            outerClass = ['w-full relative', sectionBackgroundClass(section, props.theme), dark ? 'text-white' : ''];
            innerClass = [BAND_INNER, sectionPadding(section, 'py-16 sm:py-20')];
        } else if (isBanded.value) {
            outerClass = ['w-full', block.type === 'hero' ? props.theme.heroBg : position % 2 === 0 ? props.theme.bandA : props.theme.bandB];
            innerClass = 'max-w-7xl mx-auto px-6 py-16 sm:py-20';
        }

        const hasPanelBackground = isBoxed && section.bg !== 'none';

        return {
            key: block.id || index,
            block,
            index,
            band: isBand,
            anchor: block.section?.anchor || '',
            theme: sectionTheme(section, tone),
            outerClass: block.hidden ? [outerClass, 'relative'] : outerClass,
            outerStyle: isBand && !isEdge ? sectionBackgroundStyle(section) : {},
            overlayClass: isBand && !isEdge ? sectionOverlayClass(section) : '',
            hidden: !!block.hidden,
            innerClass: block.hidden ? [innerClass, 'opacity-40'] : innerClass,
            panelClass: isBoxed
                ? ['relative', radiusLg.value, sectionBackgroundClass(section, props.theme), dark ? 'text-white' : '', hasPanelBackground ? 'px-6 sm:px-10' : '', sectionPadding(section, hasPanelBackground ? 'py-8 sm:py-12' : '')]
                : '',
            panelStyle: isBoxed ? sectionBackgroundStyle(section) : {},
            panelOverlayClass: isBoxed ? sectionOverlayClass(section) : '',
        };
    });
});

// Consecutive blocks on a boxed theme share one centred container; a full-width band ends the container so it can run
// edge to edge, and the blocks after it start a new one. A banded theme puts every block in its own band, so needs no groups.
const groups = computed(() => {
    if (isBanded.value) return [{ key: 'all', flow: false, items: decorated.value }];

    const result = [];

    for (const item of decorated.value) {
        const last = result[result.length - 1];

        if (item.band) result.push({ key: `band-${item.key}`, flow: false, items: [item] });
        else if (last?.flow) last.items.push(item);
        else result.push({ key: `flow-${item.key}`, flow: true, items: [item] });
    }

    return result;
});

// Every block (except the full-bleed hero) sits in the same content column, so they all line up. A block can be
// narrowed to three quarters or half of it (block_width) and placed left, centre or right (block_align); the
// widths each block used to set for itself are overridden so "full" means the same width for all of them.
const BLOCK_WIDTHS = { full: 'w-full', three_quarter: 'w-full lg:w-3/4', half: 'w-full lg:w-1/2' };
const BLOCK_ALIGNS = { center: 'mx-auto', left: 'mr-auto', right: 'ml-auto' };
const blockWidthClass = (block) => (block.type === 'hero'
    ? ''
    : [BLOCK_WIDTHS[block.block_width] || BLOCK_WIDTHS.full, BLOCK_ALIGNS[block.block_align] || BLOCK_ALIGNS.center, '[&>section]:!max-w-none']);

// ---- youtube video: click-to-play, so YouTube's player (and its cookies) only load when a visitor asks for it ------
const playingVideos = ref({});
const YOUTUBE_ASPECT = { '16:9': 'aspect-video', '4:3': 'aspect-[4/3]', '21:9': 'aspect-[21/9]', '1:1': 'aspect-square', '9:16': 'aspect-[9/16]' };

const youtubeVideo = (block) => parseYouTubeUrl(block.url);
const isSideBySide = (block) => block.layout === 'side_left' || block.layout === 'side_right';
const hasYoutubeButton = (block) => !!(block.button_enabled && block.button_label && block.button_url);
const hasYoutubeText = (block) => (block.layout !== 'video_only' && !!(block.title || block.description))
    || block.show_youtube_link !== false
    || hasYoutubeButton(block);
const videoKey = (block, index) => block.id || index;
const playVideo = (block, index) => {
    if (props.interactive) playingVideos.value[videoKey(block, index)] = true;
};

// ---- news feed: pagination and layout, one block can hold several of these -------------------------------------
const newsBlockPages = ref({});
const getNewsCurrentPage = (blockId) => newsBlockPages.value[blockId] || 1;
const setNewsPage = (blockId, pageNum) => { newsBlockPages.value[blockId] = pageNum; };

const getNewsTotalPages = (block) => {
    if (!props.latestPosts.length) return 1;
    const perPage = parseInt(block.limit) || 999;
    return Math.ceil(props.latestPosts.length / perPage);
};

const getNewsGridClass = (block) => {
    const cols = String(block.columns || '3');
    if (cols === '1') return 'grid grid-cols-1 gap-6';
    if (cols === '2') return 'grid grid-cols-1 md:grid-cols-2 gap-6';
    if (cols === '4') return 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6';
    if (cols === 'masonry') return 'columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6';
    return 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
};

const getFilteredPosts = (block) => {
    const perPage = parseInt(block.limit) || 999;
    const page = getNewsCurrentPage(block.id);
    const start = (page - 1) * perPage;
    return props.latestPosts.slice(start, start + perPage);
};

const getPostImagePos = (block, idx) => {
    const pos = String(block.image_position || 'above');
    return pos === 'alternate' ? (idx % 2 === 0 ? 'left' : 'right') : pos;
};

// ---- donations: the "make a contribution" modal, live pages only -------------------------------------------------
const showDonationModal = ref(false);
const activeDonation = ref(null);
const donationForm = useForm({ donor_name: '', donor_email: '', amount: 50 });

const openDonation = (campaign) => {
    if (!props.interactive) return;
    activeDonation.value = campaign;
    showDonationModal.value = true;
};

const submitDonation = () => {
    if (!activeDonation.value) return;
    donationForm.post(`/site/${props.club.slug}/donations/${activeDonation.value.id}`, {
        onSuccess: () => { showDonationModal.value = false; },
    });
};

// ---- contact form: one shared form, keyed success message per block (a page can have more than one) --------------
const contactForm = useForm({ name: '', email: '', phone: '', message: '', recipient_email: '', cc_emails: '', success_message: '' });
const contactSuccessMap = ref({});

const submitContactForm = (block) => {
    if (!props.interactive) return;

    contactForm.recipient_email = block.recipient_email || props.club.contact_email || props.club.email || '';
    contactForm.cc_emails = block.cc_emails || '';
    contactForm.success_message = block.success_message || 'Thank you! Your message has been sent successfully.';

    contactForm.post(`/site/${props.club.slug}/contact-form`, {
        preserveScroll: true,
        onSuccess: () => {
            contactSuccessMap.value[block.id] = contactForm.success_message;
            contactForm.reset('name', 'email', 'phone', 'message');
        },
    });
};
</script>

<template>
    <div>
        <div v-for="group in groups" :key="group.key" :class="group.flow ? 'max-w-7xl mx-auto px-6 py-8 space-y-16' : ''">
        <div v-for="item in group.items" :id="item.anchor || undefined" :key="item.key" :class="[item.outerClass, item.anchor ? 'scroll-mt-24' : '']" :style="item.outerStyle">
        <div v-if="item.overlayClass" :class="['absolute inset-0', item.overlayClass]"></div>
        <span v-if="item.hidden" class="absolute left-3 top-3 z-20 rounded-full bg-[var(--cm-night)] px-3 py-1 text-[11px] font-bold text-white shadow-lg">Hidden on the live site</span>
        <div :class="item.innerClass">
        <div :class="item.panelClass" :style="item.panelStyle">
        <div v-if="item.panelOverlayClass" :class="['absolute inset-0', item.panelOverlayClass]"></div>
        <!-- Each block sees the theme for its own section (light or dark) under the name `theme`. -->
        <template v-for="{ block, index, theme } in [item]" :key="item.key">
        <div :class="[blockWidthClass(block), item.panelOverlayClass ? 'relative' : '']">

            <!-- 1. Hero banner -->
            <HeroBlock v-if="block.type === 'hero'" :block="block" :theme="theme" :interactive="interactive" :resolve-url="resolveUrl" :radius-lg="radiusLg" :radius-md="radiusMd" />

            <!-- 2. Text -->
            <section v-else-if="block.type === 'text' || block.type === 'rich_text'" :class="['prose max-w-4xl mx-auto', theme.onDark ? 'prose-invert' : 'dark:prose-invert']">
                <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold mb-4', theme.headingText]">{{ block.heading }}</h2>
                <div :class="theme.bodyText" v-html="block.content"></div>
            </section>

            <!-- 3. Single image -->
            <section v-else-if="block.type === 'image' && block.url" class="max-w-5xl mx-auto">
                <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : 'justify-center']">
                    <figure :class="['space-y-2', block.size === 'small' ? 'w-full sm:w-1/3' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full']">
                        <img :src="block.url" :alt="block.caption || 'Image'" :class="['w-full h-auto border border-neutral-800 shadow-xl object-cover', radiusMd]" />
                        <figcaption v-if="block.caption" class="text-xs text-center text-neutral-400 italic">{{ block.caption }}</figcaption>
                    </figure>
                </div>
            </section>

            <!-- 4. Image gallery -->
            <section v-else-if="block.type === 'images' && block.items?.length" class="max-w-6xl mx-auto space-y-4">
                <div :class="['grid gap-4', block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3']">
                    <div v-for="(item, iIdx) in block.items" :key="item.id || iIdx" class="space-y-2">
                        <img v-if="item.url" :src="item.url" :alt="item.caption || 'Gallery image'" :class="['w-full h-48 sm:h-56 object-cover border border-neutral-800 shadow-md', radiusMd]" />
                        <p v-if="item.caption" class="text-xs text-center text-neutral-400 italic">{{ item.caption }}</p>
                    </div>
                </div>
            </section>

            <!-- 5. Callout box -->
            <section v-else-if="block.type === 'notice'" class="max-w-4xl mx-auto">
                <div :class="['p-6 border space-y-2', radiusMd,
                    block.style === 'warning' ? 'bg-amber-500/10 border-amber-500/30 text-amber-800 dark:text-amber-200' :
                    block.style === 'important' ? 'bg-rose-500/10 border-rose-500/30 text-rose-800 dark:text-rose-200' :
                    block.style === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-800 dark:text-emerald-200' :
                    'bg-[var(--cm-accent)]/10 border-[var(--cm-accent)]/30 text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]']">
                    <h4 v-if="block.title" class="font-extrabold text-base flex items-center gap-2"><span>📢</span> {{ block.title }}</h4>
                    <p class="text-sm font-medium leading-relaxed">{{ block.text }}</p>
                </div>
            </section>

            <!-- 6. Button link -->
            <section v-else-if="block.type === 'button' && (block.url || !interactive)" class="max-w-4xl mx-auto">
                <div :class="['flex', block.align === 'left' ? 'justify-start' : block.align === 'right' ? 'justify-end' : 'justify-center']">
                    <component :is="interactive ? 'a' : 'span'" :href="interactive ? resolveUrl(block.url) : undefined" :class="['py-3 px-6 font-bold text-sm shadow-lg hover:scale-105 transition-all', radiusMd, theme.heroCta]">
                        {{ block.label || 'Learn More →' }}
                    </component>
                </div>
            </section>

            <!-- 6b. YouTube video -->
            <section v-else-if="block.type === 'youtube' && (youtubeVideo(block) || !interactive)" class="mx-auto">
                <div v-if="!youtubeVideo(block)" :class="['flex items-center justify-center aspect-video border-2 border-dashed text-sm font-semibold opacity-70 text-center p-4', radiusMd, theme.bodyText]">
                    Paste a YouTube link to show the video here
                </div>
                <div v-else :class="['grid gap-6 items-start', isSideBySide(block) ? 'md:grid-cols-2' : '']">
                    <div :class="[block.layout === 'side_right' ? 'md:order-2' : '']">
                        <div :class="['relative w-full overflow-hidden shadow-xl border border-neutral-800 bg-black', radiusMd, YOUTUBE_ASPECT[block.aspect] || 'aspect-video', block.aspect === '9:16' ? 'max-w-sm mx-auto' : '']">
                            <iframe
                                v-if="interactive && playingVideos[videoKey(block, index)]"
                                :src="youtubeEmbedUrl(youtubeVideo(block).id, { start: block.start, end: block.end, loop: block.loop, captions: block.captions })"
                                :title="block.title || 'YouTube video'"
                                class="absolute inset-0 w-full h-full"
                                allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                                referrerpolicy="strict-origin-when-cross-origin"
                                allowfullscreen
                            ></iframe>
                            <component
                                :is="interactive ? 'button' : 'div'"
                                v-else
                                :type="interactive ? 'button' : undefined"
                                :aria-label="`Play video: ${block.title || 'YouTube video'}`"
                                class="group absolute inset-0 w-full h-full cursor-pointer"
                                @click="playVideo(block, index)"
                            >
                                <img :src="block.cover_url || youtubeThumbnail(youtubeVideo(block).id)" alt="" loading="lazy" class="w-full h-full object-cover" />
                                <span class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition-colors">
                                    <span class="w-16 h-16 sm:w-20 sm:h-20 rounded-full bg-white/95 text-neutral-900 flex items-center justify-center shadow-2xl group-hover:scale-110 transition-transform">
                                        <svg viewBox="0 0 24 24" class="w-7 h-7 sm:w-9 sm:h-9 ml-1 fill-current" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                                    </span>
                                </span>
                            </component>
                        </div>
                    </div>

                    <div v-if="hasYoutubeText(block)" :class="['space-y-3', block.text_align === 'center' ? 'text-center' : 'text-left']">
                        <h2 v-if="block.layout !== 'video_only' && block.title" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.title }}</h2>
                        <p v-if="block.layout !== 'video_only' && block.description" :class="['whitespace-pre-line leading-relaxed', theme.bodyText]">{{ block.description }}</p>
                        <div v-if="block.show_youtube_link !== false || hasYoutubeButton(block)" :class="['flex flex-wrap gap-3 pt-1', block.text_align === 'center' ? 'justify-center' : 'justify-start']">
                            <component
                                :is="interactive ? 'a' : 'span'"
                                v-if="block.show_youtube_link !== false"
                                :href="interactive ? `https://www.youtube.com/watch?v=${youtubeVideo(block).id}` : undefined"
                                target="_blank"
                                rel="noopener"
                                :class="['inline-block py-2.5 px-5 font-bold text-sm border transition-all hover:scale-105', radiusMd, theme.cardBg]"
                            >
                                ▶ Watch on YouTube
                            </component>
                            <component
                                :is="interactive ? 'a' : 'span'"
                                v-if="hasYoutubeButton(block)"
                                :href="interactive ? resolveUrl(block.button_url) : undefined"
                                :target="block.button_new_tab ? '_blank' : undefined"
                                :rel="block.button_new_tab ? 'noopener' : undefined"
                                :class="['inline-block py-2.5 px-5 font-bold text-sm shadow-lg transition-all hover:scale-105', radiusMd, theme.heroCta]"
                            >
                                {{ block.button_label }}
                            </component>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6c. Newer blocks, each in its own component -->
            <CtaBannerBlock v-else-if="block.type === 'cta_banner'" :block="block" :theme="theme" :interactive="interactive" :resolve-url="resolveUrl" :radius-lg="radiusLg" :radius-md="radiusMd" />
            <SlideshowBlock v-else-if="block.type === 'slideshow'" :block="block" :theme="theme" :interactive="interactive" :resolve-url="resolveUrl" :radius-lg="radiusLg" :radius-md="radiusMd" />
            <PostHeaderBlock v-else-if="block.type === 'post_header'" :block="block" :theme="theme" :interactive="interactive" :resolve-url="resolveUrl" :radius-lg="radiusLg" />
            <PostAttachmentsBlock v-else-if="block.type === 'post_attachments'" :block="block" :theme="theme" :radius-md="radiusMd" />
            <FeatureCardsBlock v-else-if="block.type === 'feature_cards'" :block="block" :theme="theme" :interactive="interactive" :resolve-url="resolveUrl" :radius-md="radiusMd" />
            <StatsBlock v-else-if="block.type === 'stats'" :block="block" :theme="theme" :interactive="interactive" :radius-md="radiusMd" />
            <QuoteMottoBlock v-else-if="block.type === 'quote_motto'" :block="block" :theme="theme" :interactive="interactive" :radius-md="radiusMd" />
            <SectionHeadingBlock v-else-if="block.type === 'section_heading'" :block="block" :theme="theme" :interactive="interactive" :radius-md="radiusMd" />
            <FaqBlock v-else-if="block.type === 'faq'" :block="block" :theme="theme" :interactive="interactive" :radius-md="radiusMd" />
            <MapBlock v-else-if="block.type === 'map'" :block="block" :theme="theme" :interactive="interactive" :radius-md="radiusMd" />
            <DownloadsBlock v-else-if="block.type === 'downloads'" :block="block" :theme="theme" :club="club" :interactive="interactive" :resolve-url="resolveUrl" :radius-md="radiusMd" />
            <CalendarBlock v-else-if="block.type === 'calendar'" :block="block" :theme="theme" :club="club" :calendar="calendar" :interactive="interactive" :radius-md="radiusMd" :radius-lg="radiusLg" />

            <!-- 7. Membership pricing -->
            <section v-else-if="block.type === 'pricing_cards'" class="max-w-6xl mx-auto space-y-6">
                <h2 :class="['text-2xl sm:text-3xl font-extrabold text-center', theme.headingText]">{{ block.heading || 'Membership Options & Dues' }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="plan in membershipPlans" :key="plan.id" :class="['p-6 space-y-4 flex flex-col justify-between', radiusLg, theme.cardBg]">
                        <div>
                            <span :class="['px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider border', theme.heroPill]">{{ plan.billing_period }}</span>
                            <h3 :class="['text-xl font-bold mt-2', theme.headingText]">{{ plan.name }}</h3>
                            <p :class="['text-xs mt-1', theme.bodyText]">{{ plan.description }}</p>
                        </div>
                        <div class="pt-4 border-t border-neutral-800/20 flex items-baseline justify-between">
                            <div>
                                <span :class="['text-2xl font-black', theme.headingText]">{{ $cs }}{{ plan.price }}</span>
                                <span :class="['text-xs', theme.bodyText]"> / {{ plan.billing_period }}</span>
                            </div>
                            <Link v-if="interactive" :href="`/${club.slug}/admin/subscriptions`" :class="['py-2 px-4 font-bold text-xs', radiusMd, theme.heroCta]">Subscribe →</Link>
                            <span v-else :class="['text-xs font-bold', theme.accentText]">Subscribe →</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 8. Donation campaign -->
            <section v-else-if="block.type === 'donation_campaign'" class="space-y-6">
                <div v-for="d in donations" :key="d.id" :class="['p-8 sm:p-10 space-y-6', radiusLg, theme.heroBg]">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[var(--cm-accent)]/10 text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] border border-[var(--cm-accent)]/20 uppercase tracking-wider">🎁 Active Fundraising Campaign</span>
                            <h2 class="text-2xl sm:text-3xl font-black mt-2">{{ d.campaign_name }}</h2>
                            <p class="text-sm mt-1 max-w-2xl opacity-90">{{ d.description }}</p>
                        </div>
                        <button type="button" :disabled="!interactive" @click="openDonation(d)" class="py-3.5 px-6 rounded-2xl bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] font-bold text-sm shadow-xl shadow-[var(--cm-accent)]/20 hover:scale-105 transition-transform disabled:opacity-70 disabled:hover:scale-100 disabled:cursor-not-allowed">
                            💖 Make a Contribution
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm opacity-90">
                            <span>Raised: <strong :class="['font-bold', theme.accentText]">{{ $cs }}{{ d.current_amount }}</strong></span>
                            <span>Target: <strong>{{ $cs }}{{ d.target_amount }}</strong></span>
                        </div>
                        <div class="w-full h-4 rounded-full bg-black/20 border border-black/10 overflow-hidden p-0.5">
                            <div class="h-full rounded-full bg-[var(--cm-accent)] transition-all duration-500" :style="{ width: `${d.percentage}%` }"></div>
                        </div>
                        <div :class="['text-right text-xs font-bold', theme.accentText]">{{ d.percentage }}% Funded ({{ d.contributions_count }} Donors)</div>
                    </div>
                </div>
            </section>

            <!-- 9. News feed -->
            <section v-else-if="block.type === 'news_feed' || block.type === 'news_list'" class="space-y-6">
                <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>

                <div :class="getNewsGridClass(block)">
                    <template v-for="(post, pIdx) in getFilteredPosts(block)" :key="post.id">
                        <component :is="interactive && post.slug ? Link : 'div'" :href="interactive && post.slug ? resolveUrl(`/site/${club.slug}/news/${post.slug}`) : undefined" :class="[theme.cardBg, radiusMd, 'overflow-hidden transition-all', interactive && post.slug ? 'hover:-translate-y-0.5' : '', String(block.columns) === 'masonry' ? 'break-inside-avoid inline-block w-full mb-6' : '',
                            post.cover_image_url && ['left', 'right'].includes(getPostImagePos(block, pIdx)) ? 'grid grid-cols-1 sm:grid-cols-3 gap-0' : 'flex flex-col justify-between']">
                            <template v-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'left'">
                                <div class="sm:col-span-1 min-h-[160px] bg-neutral-800 overflow-hidden"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                                <div class="sm:col-span-2 p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                    <span v-if="interactive && post.slug" :class="['inline-block pt-1 text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">Read more →</span>
                                </div>
                            </template>
                            <template v-else-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'right'">
                                <div class="sm:col-span-2 p-6 space-y-2 order-2 sm:order-1">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                    <span v-if="interactive && post.slug" :class="['inline-block pt-1 text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">Read more →</span>
                                </div>
                                <div class="sm:col-span-1 min-h-[160px] bg-neutral-800 overflow-hidden order-1 sm:order-2"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                            </template>
                            <template v-else-if="post.cover_image_url && getPostImagePos(block, pIdx) === 'below'">
                                <div class="p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                    <span v-if="interactive && post.slug" :class="['inline-block pt-1 text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">Read more →</span>
                                </div>
                                <div class="h-48 w-full bg-neutral-800 overflow-hidden border-t border-black/10"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                            </template>
                            <template v-else-if="post.cover_image_url">
                                <div class="h-48 w-full bg-neutral-800 overflow-hidden border-b border-black/10"><img :src="post.cover_image_url" :alt="post.title" class="w-full h-full object-cover" /></div>
                                <div class="p-6 space-y-2">
                                    <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                    <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                    <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                    <span v-if="interactive && post.slug" :class="['inline-block pt-1 text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">Read more →</span>
                                </div>
                            </template>
                            <div v-else class="p-6 space-y-2">
                                <div class="flex items-center justify-between text-xs opacity-70"><span v-if="post.author_name">Published by {{ post.author_name }}</span><span>{{ post.published_at }}</span></div>
                                <h3 :class="['text-lg font-bold leading-snug', theme.headingText]">{{ post.title }}</h3>
                                <p :class="['text-sm leading-relaxed line-clamp-3', theme.bodyText]">{{ post.excerpt || post.content }}</p>
                                    <span v-if="interactive && post.slug" :class="['inline-block pt-1 text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">Read more →</span>
                            </div>
                        </component>
                    </template>
                </div>

                <div v-if="getNewsTotalPages(block) > 1" class="flex items-center justify-between pt-6 border-t border-neutral-800/20">
                    <button type="button" @click="setNewsPage(block.id, getNewsCurrentPage(block.id) - 1)" :disabled="getNewsCurrentPage(block.id) <= 1" :class="[theme.cardBg, radiusMd, 'py-2.5 px-4 text-xs font-bold flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer']">← Previous</button>
                    <span :class="['text-xs font-bold', theme.bodyText]">Page <span :class="theme.headingText">{{ getNewsCurrentPage(block.id) }}</span> of {{ getNewsTotalPages(block) }}</span>
                    <button type="button" @click="setNewsPage(block.id, getNewsCurrentPage(block.id) + 1)" :disabled="getNewsCurrentPage(block.id) >= getNewsTotalPages(block)" :class="[theme.cardBg, radiusMd, 'py-2.5 px-4 text-xs font-bold flex items-center gap-1.5 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer']">Next →</button>
                </div>
            </section>

            <!-- 10. Upcoming events -->
            <section v-else-if="block.type === 'events_calendar'" class="space-y-6">
                <h2 :class="['text-2xl font-bold', theme.headingText]">{{ block.heading || 'Upcoming Events & Formal Dinners' }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="e in upcomingEvents" :key="e.id" :class="['p-6 space-y-4', radiusMd, theme.cardBg]">
                        <h3 :class="['text-lg font-bold', theme.headingText]">{{ e.title }}</h3>
                        <p :class="['text-xs', theme.bodyText]">📍 {{ e.location }} • 🕒 {{ e.starts_at }}</p>
                        <Link v-if="interactive" :href="`/site/${club.slug}/events/${e.slug}`" :class="['block w-full py-2.5 px-4 bg-black/10 dark:bg-white/10 text-center font-bold text-xs', radiusMd]">Event details & booking →</Link>
                    </div>
                </div>
            </section>

            <!-- 11. Contact details -->
            <section v-else-if="block.type === 'contact_details'" class="py-8 space-y-10 text-center">
                <div class="max-w-3xl mx-auto space-y-3">
                    <span v-if="block.eyebrow" class="text-xs font-bold text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] uppercase tracking-widest block">{{ block.eyebrow }}</span>
                    <h2 :class="['text-3xl sm:text-5xl font-extrabold tracking-tight', theme.headingText]">{{ block.title || 'Get in Touch' }}</h2>
                    <div class="w-12 h-1 bg-[var(--cm-accent)] mx-auto my-4 rounded-full"></div>
                    <p v-if="block.description" :class="['text-sm sm:text-base leading-relaxed max-w-2xl mx-auto', theme.bodyText]">{{ block.description }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
                    <div v-for="card in [
                        { icon: '✉️', heading: block.email_heading || 'Email', value: block.email || club.contact_email, kind: 'email' },
                        { icon: '📅', heading: block.times_heading || 'Meeting Times', value: block.times || club.meeting_formula, kind: 'text' },
                        { icon: '📍', heading: block.location_heading || 'Location', value: block.location || club.address, kind: 'text' },
                    ]" :key="card.heading" :class="['p-8 text-center space-y-4 transition-all', radiusMd, theme.cardBg]">
                        <div class="w-14 h-14 rounded-full bg-[var(--cm-accent)]/10 border border-[var(--cm-accent)]/30 text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] flex items-center justify-center mx-auto text-xl">{{ card.icon }}</div>
                        <h3 :class="['font-bold text-lg', theme.headingText]">{{ card.heading }}</h3>
                        <ObfuscatedEmail v-if="card.kind === 'email' && card.value" :email="card.value" custom-class="text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)] hover:underline font-semibold text-sm sm:text-base break-all cursor-pointer transition-colors" />
                        <p v-else-if="card.value" :class="['text-sm whitespace-pre-line leading-relaxed', theme.bodyText]">{{ card.value }}</p>
                        <span v-else class="text-neutral-500 dark:text-neutral-400 text-xs italic">Not configured</span>
                    </div>
                </div>
            </section>

            <!-- 12. Contact form -->
            <section v-else-if="block.type === 'contact_form'" class="py-8 space-y-8 max-w-3xl mx-auto">
                <div class="text-center space-y-3">
                    <h2 :class="['text-3xl sm:text-5xl font-extrabold tracking-tight', theme.headingText]">{{ block.heading || 'Send Us a Message' }}</h2>
                    <div class="w-12 h-1 bg-[var(--cm-accent)] mx-auto my-2 rounded-full"></div>
                    <p v-if="block.subtitle" :class="['text-sm sm:text-base leading-relaxed max-w-xl mx-auto', theme.bodyText]">{{ block.subtitle }}</p>
                </div>

                <div v-if="interactive && contactSuccessMap[block.id]" :class="['p-4 sm:p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-sm font-semibold flex items-center gap-3', radiusMd]">
                    <span class="text-xl">✅</span><span>{{ contactSuccessMap[block.id] }}</span>
                </div>

                <form @submit.prevent="submitContactForm(block)" :class="['p-6 sm:p-10 shadow-2xl space-y-6 text-sm', radiusLg, theme.cardBg]">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label :for="`contact-name-${block.id}`" class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Your Name <span v-if="block.name_required" class="text-rose-500">*</span></label>
                            <input :id="`contact-name-${block.id}`" v-model="contactForm.name" type="text" :disabled="!interactive" :required="interactive && block.name_required !== false" placeholder="e.g. John Doe" :class="['w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 p-3.5 focus:border-[var(--cm-accent)] focus:ring-1 focus:ring-[var(--cm-accent)] transition-colors disabled:opacity-70 disabled:cursor-not-allowed', radiusMd]" />
                            <span v-if="contactForm.errors.name" class="text-xs text-rose-500 mt-1 block" role="alert">{{ contactForm.errors.name }}</span>
                        </div>
                        <div>
                            <label :for="`contact-email-${block.id}`" class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Email Address <span v-if="block.email_required !== false" class="text-rose-500">*</span></label>
                            <input :id="`contact-email-${block.id}`" v-model="contactForm.email" type="email" :disabled="!interactive" :required="interactive && block.email_required !== false" placeholder="e.g. john@example.org" :class="['w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 p-3.5 focus:border-[var(--cm-accent)] focus:ring-1 focus:ring-[var(--cm-accent)] transition-colors disabled:opacity-70 disabled:cursor-not-allowed', radiusMd]" />
                            <span v-if="contactForm.errors.email" class="text-xs text-rose-500 mt-1 block" role="alert">{{ contactForm.errors.email }}</span>
                        </div>
                    </div>
                    <div>
                        <label :for="`contact-phone-${block.id}`" class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Phone Number <span v-if="block.phone_required" class="text-rose-500">*</span></label>
                        <input :id="`contact-phone-${block.id}`" v-model="contactForm.phone" type="tel" :disabled="!interactive" :required="interactive && !!block.phone_required" placeholder="e.g. +44 7123 456789" :class="['w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 p-3.5 focus:border-[var(--cm-accent)] focus:ring-1 focus:ring-[var(--cm-accent)] transition-colors disabled:opacity-70 disabled:cursor-not-allowed', radiusMd]" />
                        <span v-if="contactForm.errors.phone" class="text-xs text-rose-500 mt-1 block" role="alert">{{ contactForm.errors.phone }}</span>
                    </div>
                    <div>
                        <label :for="`contact-message-${block.id}`" class="block text-xs font-bold uppercase tracking-wider mb-2 opacity-80">Message <span v-if="block.message_required !== false" class="text-rose-500">*</span></label>
                        <textarea :id="`contact-message-${block.id}`" v-model="contactForm.message" rows="5" :disabled="!interactive" :required="interactive && block.message_required !== false" placeholder="Write your message or inquiry here..." :class="['w-full bg-black/5 dark:bg-white/5 border border-black/10 dark:border-white/10 p-3.5 focus:border-[var(--cm-accent)] focus:ring-1 focus:ring-[var(--cm-accent)] transition-colors disabled:opacity-70 disabled:cursor-not-allowed', radiusMd]"></textarea>
                        <span v-if="contactForm.errors.message" class="text-xs text-rose-500 mt-1 block" role="alert">{{ contactForm.errors.message }}</span>
                    </div>
                    <button type="submit" :disabled="!interactive || contactForm.processing" :class="['w-full py-4 font-black text-sm uppercase tracking-wider shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed', radiusMd, theme.heroCta]">
                        <span>{{ contactForm.processing ? 'Sending Message...' : (block.button_text || 'Send Message') }}</span><span>→</span>
                    </button>
                    <p v-if="!interactive" class="text-[11px] text-center opacity-70">🔒 Submissions will route to: <strong>{{ block.recipient_email || club.contact_email || club.email || 'not set yet' }}</strong><span v-if="block.cc_emails"> (CC: {{ block.cc_emails }})</span></p>
                </form>
            </section>
        </div>
        </template>
        </div>
        </div>
        </div>
        </div>

        <!-- Donation modal (live pages only) -->
        <div v-if="interactive && showDonationModal" class="fixed inset-0 z-50 bg-black/70 backdrop-blur-md flex items-center justify-center p-4">
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="donation-modal-title"
                v-focus-trap="() => { showDonationModal = false; }"
                class="bg-white dark:bg-[var(--cm-night-soft)] border border-neutral-200 dark:border-neutral-800 rounded-3xl max-w-md w-full p-6 space-y-6 shadow-2xl"
            >
                <div class="flex items-start justify-between border-b border-neutral-200 dark:border-neutral-800 pb-4">
                    <div>
                        <h3 id="donation-modal-title" class="text-xl font-bold text-neutral-900 dark:text-white">Make a Donation</h3>
                        <p class="text-xs text-[var(--cm-accent-deep)] dark:text-[var(--cm-accent-bright)]">{{ activeDonation?.campaign_name }}</p>
                    </div>
                    <button type="button" @click="showDonationModal = false" class="text-neutral-400 hover:text-neutral-700 dark:hover:text-white font-bold" aria-label="Close">✕</button>
                </div>
                <form @submit.prevent="submitDonation" class="space-y-4 text-sm">
                    <div>
                        <label for="donation-donor-name" class="block text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1">Your Full Name</label>
                        <input id="donation-donor-name" v-model="donationForm.donor_name" type="text" required placeholder="e.g. Jane Smith" class="w-full bg-neutral-50 dark:bg-[var(--cm-night-soft)] border border-neutral-200 dark:border-neutral-700 rounded-xl p-3 text-neutral-900 dark:text-white" />
                    </div>
                    <div>
                        <label for="donation-donor-email" class="block text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1">Email Address</label>
                        <input id="donation-donor-email" v-model="donationForm.donor_email" type="email" required placeholder="e.g. jane@example.com" class="w-full bg-neutral-50 dark:bg-[var(--cm-night-soft)] border border-neutral-200 dark:border-neutral-700 rounded-xl p-3 text-neutral-900 dark:text-white" />
                    </div>
                    <div>
                        <label for="donation-amount" class="block text-xs font-bold text-neutral-500 dark:text-neutral-400 uppercase tracking-wider mb-1">Donation Amount ({{ $cs }})</label>
                        <input id="donation-amount" v-model="donationForm.amount" type="number" min="1" required class="w-full bg-neutral-50 dark:bg-[var(--cm-night-soft)] border border-neutral-200 dark:border-neutral-700 rounded-xl p-3 text-neutral-900 dark:text-white font-bold text-lg" />
                    </div>
                    <button type="submit" :disabled="donationForm.processing" class="w-full py-3.5 rounded-xl bg-[var(--cm-accent)] hover:brightness-110 text-[var(--cm-on-accent)] font-bold text-sm shadow-lg shadow-[var(--cm-accent)]/20 disabled:opacity-60">Confirm & Process Donation</button>
                </form>
            </div>
        </div>
    </div>
</template>
