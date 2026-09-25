<script setup>
// FAQ: native <details> rows (keyboard and screen-reader friendly, no JS needed), optionally one open at a time,
// always expanded, numbered, two columns or searchable. Answers are rich text that was sanitised when saved.
import { computed, ref } from 'vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const query = ref('');

const items = computed(() => (props.block.items || []).filter((item) => item.question || item.answer));
const searchable = computed(() => props.block.show_search !== false && items.value.length > 8);
const expanded = computed(() => props.block.behaviour === 'expanded');

const plain = (html) => String(html || '').replace(/<[^>]*>/g, ' ');
const visible = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return items.value;
    return items.value.filter((item) => `${item.question} ${plain(item.answer)}`.toLowerCase().includes(q));
});
const groupName = computed(() => (props.block.behaviour === 'single' ? `faq-${props.block.id || 'x'}` : undefined));
const isOpen = (index) => !!query.value.trim() || (props.block.first_open === true && index === 0);
</script>

<template>
    <section v-if="items.length || !interactive" class="max-w-4xl mx-auto space-y-6">
        <div v-if="block.heading || block.intro" class="space-y-2">
            <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>
            <p v-if="block.intro" :class="['whitespace-pre-line', theme.bodyText]">{{ block.intro }}</p>
        </div>

        <input
            v-if="searchable"
            v-model="query"
            type="search"
            placeholder="Search the questions"
            aria-label="Search the questions"
            :class="['w-full px-4 py-2.5 text-sm border bg-transparent', radiusMd, theme.bodyText]"
        />

        <p v-if="!items.length" :class="['text-sm opacity-70', theme.bodyText]">Add questions and answers to show them here.</p>

        <div :class="['grid gap-3 items-start', Number(block.columns) === 2 ? 'md:grid-cols-2 md:gap-x-6' : '']">
            <template v-for="(item, index) in visible" :key="item.id || index">
                <div v-if="expanded" :class="['p-5 space-y-2 border', radiusMd, theme.cardBg]">
                    <h3 :class="['font-bold text-base', theme.headingText]"><span v-if="block.numbered" class="opacity-60 mr-1">{{ index + 1 }}.</span>{{ item.question }}</h3>
                    <div :class="['prose dark:prose-invert max-w-none text-sm', theme.bodyText]" v-html="item.answer"></div>
                </div>

                <details v-else :name="groupName" :open="isOpen(index)" :class="['group border', radiusMd, theme.cardBg]">
                    <summary :class="['flex items-center justify-between gap-4 cursor-pointer select-none list-none px-5 py-4 font-bold text-base [&::-webkit-details-marker]:hidden', theme.headingText]">
                        <span><span v-if="block.numbered" class="opacity-60 mr-1">{{ index + 1 }}.</span>{{ item.question }}</span>
                        <svg viewBox="0 0 20 20" class="w-5 h-5 shrink-0 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 8l5 5 5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </summary>
                    <div :class="['prose dark:prose-invert max-w-none px-5 pb-5 text-sm', theme.bodyText]" v-html="item.answer"></div>
                </details>
            </template>
        </div>

        <p v-if="items.length && !visible.length" :class="['text-sm opacity-70', theme.bodyText]">No questions match your search.</p>
    </section>
</template>
