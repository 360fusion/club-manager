<script setup>
// A motto or closing statement: a large serif heading, a short divider, a paragraph and an italic tagline.
import { computed } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const paragraphs = computed(() => String(props.block.text || '').split(/\n{1,}/).map((p) => p.trim()).filter(Boolean));
const hasContent = computed(() => !!(props.block.heading || paragraphs.value.length || props.block.tagline));
</script>

<template>
    <section v-if="hasContent" class="mx-auto w-full max-w-3xl space-y-6 text-center">
        <h2 v-if="block.heading" :class="['text-3xl sm:text-5xl font-semibold leading-tight tracking-tight', theme.headingText]">{{ block.heading }}</h2>
        <div v-if="block.show_divider !== false && (block.heading || paragraphs.length)" class="mx-auto h-[3px] w-[60px] rounded-sm bg-[var(--cm-accent)]"></div>
        <p v-for="(paragraph, i) in paragraphs" :key="i" :class="['text-base sm:text-lg leading-loose', theme.bodyText]">{{ paragraph }}</p>
        <p v-if="block.tagline" :class="['font-serif text-xl sm:text-2xl italic', theme.onDark ? 'text-[var(--cm-accent-bright)]' : 'text-[var(--cm-accent-deep)]']">{{ block.tagline }}</p>
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-3xl border-2 border-dashed p-8 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add a heading, text or tagline to show this motto
    </section>
</template>
