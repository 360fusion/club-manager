<script setup>
// Downloads: a list or cards of documents. Uploaded files are served by the site's own gated route, so a
// members-only block never exposes a file address; link items open wherever they point.
import { computed, ref } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    club: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const query = ref('');

const TYPES = {
    pdf: ['PDF', '📄'], doc: ['DOC', '📝'], docx: ['DOC', '📝'], rtf: ['DOC', '📝'], txt: ['TXT', '📝'],
    xls: ['XLS', '📊'], xlsx: ['XLS', '📊'], csv: ['CSV', '📊'], ppt: ['PPT', '📽️'], pptx: ['PPT', '📽️'], zip: ['ZIP', '🗜️'],
};

const extension = (item) => {
    const name = item.source === 'upload' ? item.file_name : String(item.url || '').split(/[?#]/)[0];
    const match = String(name || '').toLowerCase().match(/\.([a-z0-9]{2,5})$/);
    return match ? match[1] : '';
};
const kind = (item) => TYPES[extension(item)] || (item.source === 'link' ? ['LINK', '🔗'] : ['FILE', '📎']);
const titleOf = (item) => item.title || item.file_name || 'Untitled document';
const href = (item) => (item.source === 'upload' ? `/site/${props.club.slug}/files/${item.media_id}` : props.resolveUrl(item.url));
const size = (bytes) => {
    if (!bytes) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};
const dateOf = (item) => (item.added_at ? new Date(`${item.added_at}T12:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '');

const items = computed(() => {
    let list = [...(props.block.items || [])];
    if (props.block.sort === 'newest') list.sort((a, b) => String(b.added_at || '').localeCompare(String(a.added_at || '')));
    if (props.block.sort === 'name') list.sort((a, b) => titleOf(a).localeCompare(titleOf(b)));
    const q = query.value.trim().toLowerCase();
    if (q) list = list.filter((item) => `${titleOf(item)} ${item.description || ''} ${item.group || ''}`.toLowerCase().includes(q));
    return list;
});

const groups = computed(() => {
    const map = new Map();
    items.value.forEach((item) => {
        const key = item.group || '';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(item);
    });
    return [...map.entries()].map(([name, list]) => ({ name, list }));
});

const searchable = computed(() => props.block.show_search === true && (props.block.items || []).length > 6);
const cards = computed(() => props.block.layout === 'cards');
const buttonLabel = computed(() => props.block.button_label || 'Download');
</script>

<template>
    <section v-if="block.locked || (block.items || []).length || !interactive" class="max-w-4xl mx-auto space-y-6">
        <div v-if="block.heading || block.intro" class="space-y-2">
            <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>
            <p v-if="block.intro" :class="['whitespace-pre-line', theme.bodyText]">{{ block.intro }}</p>
        </div>

        <div v-if="block.locked" :class="['p-6 border flex items-center gap-4', radiusMd, theme.cardBg]">
            <span class="text-3xl" aria-hidden="true">🔒</span>
            <div class="space-y-1">
                <p :class="['font-bold', theme.headingText]">These documents are for members.</p>
                <p :class="['text-sm', theme.bodyText]"><a href="/login" class="underline font-semibold">Log in</a> to view them.</p>
            </div>
        </div>

        <template v-else>
            <p v-if="!(block.items || []).length" :class="['text-sm opacity-70', theme.bodyText]">Upload files or add links to list them here.</p>

            <input v-if="searchable" v-model="query" type="search" placeholder="Search the documents" aria-label="Search the documents" :class="['w-full px-4 py-2.5 text-sm border bg-transparent', radiusMd, theme.bodyText]" />

            <div v-for="group in groups" :key="group.name" class="space-y-3">
                <h3 v-if="group.name" :class="['text-sm font-extrabold uppercase tracking-wider opacity-80', theme.headingText]">{{ group.name }}</h3>
                <div :class="cards ? 'grid gap-4 sm:grid-cols-2' : 'space-y-3'">
                    <div v-for="item in group.list" :key="item.id" :class="['p-4 border flex gap-4', cards ? 'flex-col' : 'flex-col sm:flex-row sm:items-center', radiusMd, theme.cardBg]">
                        <div class="flex items-start gap-3 flex-1 min-w-0">
                            <span class="text-2xl leading-none" aria-hidden="true">{{ kind(item)[1] }}</span>
                            <div class="min-w-0 space-y-1">
                                <p :class="['font-bold break-words', theme.headingText]">{{ titleOf(item) }}</p>
                                <p v-if="item.description" :class="['text-sm whitespace-pre-line', theme.bodyText]">{{ item.description }}</p>
                                <p v-if="(block.show_type !== false) || (block.show_size !== false && size(item.size)) || (block.show_date && dateOf(item))" :class="['text-xs opacity-70 flex flex-wrap gap-x-3', theme.bodyText]">
                                    <span v-if="block.show_type !== false" class="font-bold">{{ kind(item)[0] }}</span>
                                    <span v-if="block.show_size !== false && size(item.size)">{{ size(item.size) }}</span>
                                    <span v-if="block.show_date && dateOf(item)">Added {{ dateOf(item) }}</span>
                                </p>
                            </div>
                        </div>
                        <component
                            :is="interactive ? 'a' : 'span'"
                            :href="interactive ? href(item) : undefined"
                            :target="block.open_in === 'download' ? undefined : '_blank'"
                            :rel="block.open_in === 'download' ? undefined : 'noopener'"
                            :download="interactive && block.open_in === 'download' && item.source === 'upload' ? '' : undefined"
                            :class="['inline-block py-2 px-4 font-bold text-sm text-center shadow-md transition-all hover:scale-105 shrink-0', radiusMd, theme.heroCta]"
                        >{{ buttonLabel }}</component>
                    </div>
                </div>
            </div>

            <p v-if="(block.items || []).length && !items.length" :class="['text-sm opacity-70', theme.bodyText]">No documents match your search.</p>
        </template>
    </section>
</template>
