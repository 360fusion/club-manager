<script setup>
// A read-only, compact picture of one page element, shown in the page builder when the element is collapsed so
// an admin can scan a whole page quickly. Nothing here is editable and nothing is written back to `block`.
import { computed } from 'vue';
import TileMap from '@/Components/Blocks/TileMap.vue';
import { hasCoordinates } from '@/Utils/osmMap';
import { parseYouTubeUrl, youtubeThumbnail } from '@/Utils/youtube';

const props = defineProps({
    block: { type: Object, required: true },
});

const LIST_PREVIEW = 3;

const plain = (html) => {
    const source = String(html ?? '')
        .replace(/<\/(p|div|li|h[1-6]|blockquote)>/gi, '$&\n')
        .replace(/<br\s*\/?>/gi, '\n');
    // DOMParser never runs scripts or loads images, unlike setting innerHTML on an element.
    return (new DOMParser().parseFromString(source, 'text/html').body.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
};

const type = computed(() => props.block.type);
const video = computed(() => parseYouTubeUrl(props.block.url));
const videoThumb = computed(() => props.block.cover_url || (video.value ? youtubeThumbnail(video.value.id) : ''));
const located = computed(() => hasCoordinates(props.block));
const items = computed(() => (Array.isArray(props.block.items) ? props.block.items : []));
const filled = (list, key) => list.filter((item) => item[key]);
const slides = computed(() => (Array.isArray(props.block.slides) ? props.block.slides : []));
const galleryImages = computed(() => filled(items.value, 'url'));

const noticeClass = computed(() => ({
    info: 'bg-blue-50 dark:bg-blue-950/40 border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200',
    warning: 'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200',
    success: 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/60 text-emerald-900 dark:text-emerald-200',
    important: 'bg-rose-50 dark:bg-rose-950/40 border-rose-200 dark:border-rose-800/60 text-rose-900 dark:text-rose-200',
}[props.block.style] || 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200'));

const dynamicNote = computed(() => ({
    news_feed: 'Published news articles, pulled in automatically.',
    news_list: 'Published news articles, pulled in automatically.',
    events_calendar: 'Upcoming events and meetings, pulled in automatically.',
    pricing_cards: 'Active membership plans, pulled in automatically.',
    donation_campaign: 'Active fundraising campaigns, pulled in automatically.',
}[type.value] || ''));

const HEADING = 'text-sm font-bold text-slate-900 dark:text-white';
const BODY = 'text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line';
const MUTED = 'text-[11px] text-slate-400';
const EMPTY = 'italic text-slate-400';
const THUMB = 'rounded-lg object-cover bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700';
</script>

<template>
    <div class="text-xs" data-testid="block-summary">
        <!-- Text -->
        <div v-if="type === 'text' || type === 'rich_text'" class="space-y-1">
            <p v-if="block.heading" :class="HEADING">{{ block.heading }}</p>
            <p v-if="plain(block.content)" :class="[BODY, 'line-clamp-6']">{{ plain(block.content) }}</p>
            <p v-else :class="[BODY, EMPTY]">No text yet.</p>
        </div>

        <!-- Single image -->
        <div v-else-if="type === 'image'" class="flex items-center gap-3">
            <img v-if="block.url" :src="block.url" alt="" loading="lazy" :class="[THUMB, 'w-28 h-20 shrink-0']" />
            <div v-else :class="[THUMB, 'w-28 h-20 shrink-0 flex items-center justify-center text-slate-400']">No image</div>
            <p v-if="block.caption" :class="BODY">{{ block.caption }}</p>
            <p v-else :class="MUTED">Image, {{ block.size || 'large' }}, {{ block.position || 'center' }}</p>
        </div>

        <!-- Gallery -->
        <div v-else-if="type === 'images'" class="space-y-1.5">
            <div class="flex items-center gap-2">
                <img v-for="image in galleryImages.slice(0, 4)" :key="image.id || image.url" :src="image.url" alt="" loading="lazy" :class="[THUMB, 'w-20 h-14']" />
                <span v-if="galleryImages.length > 4" :class="MUTED">+{{ galleryImages.length - 4 }} more</span>
                <span v-if="!galleryImages.length" :class="[MUTED, EMPTY]">No images yet.</span>
            </div>
            <p :class="MUTED">Gallery, {{ block.columns || 3 }} columns, {{ galleryImages.length }} image{{ galleryImages.length === 1 ? '' : 's' }}</p>
        </div>

        <!-- Callout -->
        <div v-else-if="type === 'notice'" :class="['rounded-xl border p-3 space-y-0.5', noticeClass]">
            <p class="text-sm font-bold">{{ block.title || 'Notice' }}</p>
            <p v-if="plain(block.text)" class="line-clamp-3 whitespace-pre-line">{{ plain(block.text) }}</p>
        </div>

        <!-- Button -->
        <div v-else-if="type === 'button'" class="flex items-center gap-3">
            <span class="px-4 py-1.5 rounded-lg bg-blue-600 text-white font-bold">{{ block.label || 'Button' }}</span>
            <span :class="[MUTED, 'truncate font-mono']">{{ block.url || 'No link yet' }}</span>
        </div>

        <!-- YouTube -->
        <div v-else-if="type === 'youtube'" class="flex items-start gap-3">
            <div :class="[THUMB, 'relative w-32 h-[72px] shrink-0 overflow-hidden']">
                <img v-if="videoThumb" :src="videoThumb" alt="" loading="lazy" class="w-full h-full object-cover" />
                <span v-if="video" class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <span class="w-8 h-8 rounded-full bg-black/60 text-white flex items-center justify-center text-[11px]">▶</span>
                </span>
                <span v-else class="absolute inset-0 flex items-center justify-center text-slate-400 text-center px-2">{{ block.url ? 'Not a YouTube link' : 'No video' }}</span>
            </div>
            <div class="min-w-0 space-y-0.5">
                <p v-if="block.title" :class="HEADING">{{ block.title }}</p>
                <p v-if="block.description" :class="[BODY, 'line-clamp-3']">{{ block.description }}</p>
                <p v-if="!block.title && !block.description" :class="MUTED">Video only</p>
            </div>
        </div>

        <!-- Downloads -->
        <div v-else-if="type === 'downloads'" class="space-y-1">
            <p :class="HEADING">
                {{ block.heading || 'Downloads' }}
                <span v-if="block.members_only" class="ml-1 px-1.5 py-0.5 rounded bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 text-[10px] uppercase">Members only</span>
            </p>
            <ul v-if="items.length" :class="BODY">
                <li v-for="item in items.slice(0, LIST_PREVIEW)" :key="item.id" class="truncate">📄 {{ item.title || item.file_name || item.url || 'Untitled' }}</li>
            </ul>
            <p v-else :class="[MUTED, EMPTY]">No files yet.</p>
            <p v-if="items.length > LIST_PREVIEW" :class="MUTED">+{{ items.length - LIST_PREVIEW }} more</p>
        </div>

        <!-- FAQ -->
        <div v-else-if="type === 'faq'" class="space-y-1">
            <p :class="HEADING">{{ block.heading || 'FAQ' }}</p>
            <ul v-if="filled(items, 'question').length" :class="BODY">
                <li v-for="item in filled(items, 'question').slice(0, LIST_PREVIEW)" :key="item.id" class="truncate">❓ {{ item.question }}</li>
            </ul>
            <p v-else :class="[MUTED, EMPTY]">No questions yet.</p>
            <p v-if="filled(items, 'question').length > LIST_PREVIEW" :class="MUTED">+{{ filled(items, 'question').length - LIST_PREVIEW }} more</p>
        </div>

        <!-- Map -->
        <div v-else-if="type === 'map'" class="flex items-start gap-3">
            <div class="w-40 shrink-0">
                <TileMap
                    v-if="located"
                    :lat="Number(block.lat)"
                    :lng="Number(block.lng)"
                    :zoom="Number(block.zoom) || 16"
                    :height="96"
                    :style-key="block.map_style || 'street'"
                    :controls="false"
                    label="Map preview"
                    radius="rounded-lg"
                />
                <div v-else :class="[THUMB, 'h-24 flex items-center justify-center text-slate-400 text-center px-2']">No location set</div>
            </div>
            <div class="min-w-0 space-y-0.5">
                <p v-if="block.heading" :class="HEADING">{{ block.heading }}</p>
                <p v-if="block.location_name" :class="[BODY, 'font-semibold']">{{ block.location_name }}</p>
                <p v-if="block.address" :class="[BODY, 'line-clamp-3']">{{ block.address }}</p>
            </div>
        </div>

        <!-- Call-to-action banner -->
        <div v-else-if="type === 'cta_banner'" class="space-y-1">
            <p v-if="block.eyebrow" :class="[MUTED, 'uppercase tracking-wider font-bold']">{{ block.eyebrow }}</p>
            <p :class="HEADING">{{ block.heading || 'Banner' }}</p>
            <p v-if="block.text" :class="[BODY, 'line-clamp-2']">{{ block.text }}</p>
            <div class="flex flex-wrap gap-2 pt-0.5">
                <span v-if="block.button_label" class="px-3 py-1 rounded-lg bg-blue-600 text-white font-bold">{{ block.button_label }}</span>
                <span v-if="block.button2_label" class="px-3 py-1 rounded-lg border border-slate-300 dark:border-slate-600 font-bold text-slate-700 dark:text-slate-200">{{ block.button2_label }}</span>
            </div>
        </div>

        <!-- Icon cards -->
        <div v-else-if="type === 'feature_cards'" class="space-y-1">
            <p v-if="block.eyebrow" :class="[MUTED, 'uppercase tracking-wider font-bold']">{{ block.eyebrow }}</p>
            <p :class="HEADING">{{ block.heading || 'Icon cards' }}</p>
            <p :class="MUTED">{{ items.length }} card{{ items.length === 1 ? '' : 's' }}, {{ block.columns || 3 }} columns</p>
            <p v-if="items.length" :class="[BODY, 'line-clamp-2']">{{ items.map((item) => item.title).filter(Boolean).join(' · ') }}</p>
        </div>

        <!-- Slideshow -->
        <div v-else-if="type === 'slideshow'" class="space-y-1.5">
            <div class="flex items-center gap-2">
                <img v-for="slide in filled(slides, 'image_url').slice(0, 4)" :key="slide.id || slide.image_url" :src="slide.image_url" alt="" loading="lazy" :class="[THUMB, 'w-20 h-14']" />
                <span v-if="filled(slides, 'image_url').length > 4" :class="MUTED">+{{ filled(slides, 'image_url').length - 4 }} more</span>
                <span v-if="!filled(slides, 'image_url').length" :class="[MUTED, EMPTY]">No photos yet.</span>
            </div>
            <p v-if="block.heading" :class="HEADING">{{ block.heading }}</p>
            <p :class="MUTED">{{ block.autoplay === false ? 'Manual' : `Changes every ${block.interval || 5}s` }}{{ block.full_width ? ', full width' : '' }}</p>
        </div>

        <!-- Stats -->
        <div v-else-if="type === 'stats'" class="space-y-1">
            <p v-if="block.heading" :class="HEADING">{{ block.heading }}</p>
            <div class="flex flex-wrap gap-x-5 gap-y-1">
                <span v-for="item in items" :key="item.id" :class="BODY"><strong class="text-slate-900 dark:text-white">{{ item.number }}{{ item.suffix }}</strong> {{ item.label }}</span>
                <span v-if="!items.length" :class="[MUTED, EMPTY]">No figures yet.</span>
            </div>
        </div>

        <!-- Motto -->
        <div v-else-if="type === 'quote_motto'" class="space-y-0.5">
            <p :class="HEADING">{{ block.heading || 'Motto' }}</p>
            <p v-if="block.text" :class="[BODY, 'line-clamp-2']">{{ block.text }}</p>
            <p v-if="block.tagline" :class="[BODY, 'italic']">{{ block.tagline }}</p>
        </div>

        <!-- Section heading -->
        <div v-else-if="type === 'section_heading'" class="space-y-0.5">
            <p v-if="block.eyebrow" :class="[MUTED, 'uppercase tracking-wider font-bold']">{{ block.eyebrow }}</p>
            <p :class="HEADING">{{ block.title || 'Section heading' }}</p>
            <p v-if="block.intro" :class="[BODY, 'line-clamp-2']">{{ block.intro }}</p>
        </div>

        <!-- Events calendar -->
        <div v-else-if="type === 'calendar'" class="space-y-0.5">
            <p :class="HEADING">🗓️ {{ block.heading || 'Events calendar' }}</p>
            <p :class="MUTED">{{ block.default_view === 'list' ? `List of the next ${block.list_length || 10} events` : 'Month grid' }}</p>
        </div>

        <!-- Hero -->
        <div v-else-if="type === 'hero'" class="space-y-0.5">
            <p :class="HEADING">{{ block.title || 'Hero banner' }}</p>
            <p v-if="block.subtitle" :class="[BODY, 'line-clamp-2']">{{ block.subtitle }}</p>
            <p v-if="block.cta_text" :class="MUTED">Button: {{ block.cta_text }}<span v-if="block.cta2_text"> · {{ block.cta2_text }}</span></p>
        </div>

        <!-- Dynamic feeds -->
        <div v-else-if="dynamicNote" class="space-y-0.5">
            <p :class="HEADING">{{ block.heading || 'Section' }}</p>
            <p :class="MUTED">⚡ {{ dynamicNote }}</p>
        </div>

        <!-- Contact details -->
        <div v-else-if="type === 'contact_details'" class="space-y-1">
            <p v-if="block.eyebrow" :class="[MUTED, 'uppercase tracking-wider font-bold']">{{ block.eyebrow }}</p>
            <p :class="HEADING">{{ block.title || 'Contact details' }}</p>
            <p v-if="block.email" :class="BODY">✉️ {{ block.email }}</p>
            <p v-if="block.times" :class="[BODY, 'line-clamp-2']">🕖 {{ block.times }}</p>
            <p v-if="block.location" :class="[BODY, 'line-clamp-2']">📍 {{ block.location }}</p>
        </div>

        <!-- Contact form -->
        <div v-else-if="type === 'contact_form'" class="space-y-0.5">
            <p :class="HEADING">{{ block.heading || 'Contact form' }}</p>
            <p v-if="block.subtitle" :class="[BODY, 'line-clamp-2']">{{ block.subtitle }}</p>
            <p :class="MUTED">Sends to {{ block.recipient_email || 'the club email' }}</p>
        </div>

        <p v-else :class="MUTED">{{ type }}</p>
    </div>
</template>
