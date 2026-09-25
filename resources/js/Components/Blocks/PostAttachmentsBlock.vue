<script setup>
// The files attached to a news article, as download links under the article. Built by the server, like PostHeaderBlock.
defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const icon = (file) => {
    const name = `${file.mime_type || ''} ${file.name || ''}`.toLowerCase();
    if (name.includes('pdf')) return '📄';
    if (name.includes('image') || /\.(png|jpe?g|gif|webp)\b/.test(name)) return '🖼️';
    if (name.includes('sheet') || /\.(xlsx?|csv)\b/.test(name)) return '📊';
    if (name.includes('word') || /\.docx?\b/.test(name)) return '📝';
    return '📎';
};
</script>

<template>
    <section v-if="block.items?.length" class="mx-auto w-full max-w-3xl space-y-3">
        <h2 :class="['text-sm font-semibold uppercase tracking-[0.15em]', theme.accentText]">Files &amp; documents</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <a v-for="(file, i) in block.items" :key="i" :href="file.url" target="_blank" rel="noopener" :class="['flex items-center gap-3 p-4 text-sm', radiusMd, theme.cardBg]">
                <span class="text-xl" aria-hidden="true">{{ icon(file) }}</span>
                <span class="min-w-0">
                    <span :class="['block truncate font-semibold', theme.headingText]">{{ file.name }}</span>
                    <span v-if="file.size" :class="['text-xs', theme.bodyText]">{{ file.size }}</span>
                </span>
            </a>
        </div>
    </section>
</template>
