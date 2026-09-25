<script setup>
// Icon cards: an optional heading over a grid of icon, title and short text. On a light section they are soft cards;
// on a dark section (see toneTheme) the same block turns into translucent value cards.
import { computed } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';
import SectionHeader from '@/Components/Blocks/SectionHeader.vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const COLUMNS = {
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
};

const items = computed(() => (Array.isArray(props.block.items) ? props.block.items : []));
const centered = computed(() => props.block.align !== 'left');
const onDark = computed(() => !!props.theme.onDark);

const cardClass = computed(() => {
    const style = props.block.card_style || 'soft';
    if (style === 'plain') return 'p-4';
    if (style === 'outlined') return ['p-8 border transition-all duration-300', onDark.value ? 'border-white/30 hover:border-white/60' : 'border-[var(--cm-accent)]/40 hover:border-[var(--cm-accent)]', props.theme.bodyText];
    return ['p-8', props.theme.cardBg];
});

const iconClass = computed(() => (props.block.icon_style === 'circle'
    ? ['flex h-[70px] w-[70px] items-center justify-center rounded-full text-2xl', onDark.value ? 'bg-white text-[var(--cm-primary)] shadow-md' : 'bg-[var(--cm-accent)]/15 text-[var(--cm-accent-deep)]']
    : ['flex h-10 items-center text-3xl', centered.value ? 'justify-center' : '', onDark.value ? 'text-white' : 'text-[var(--cm-accent)]']));

const tag = (item) => (item.link && props.interactive ? 'a' : 'div');
</script>

<template>
    <section v-if="items.length || block.heading || block.eyebrow" class="mx-auto w-full max-w-6xl space-y-12">
        <SectionHeader :eyebrow="block.eyebrow" :title="block.heading" :intro="block.intro" :divider="block.show_divider !== false" :theme="theme" :align="centered ? 'center' : 'left'" />

        <div :class="['grid gap-6', COLUMNS[block.columns] || COLUMNS[3]]">
            <component
                :is="tag(item)"
                v-for="(item, i) in items"
                :key="item.id || i"
                :href="tag(item) === 'a' ? resolveUrl(item.link) : undefined"
                :class="['block h-full space-y-3', centered ? 'text-center' : 'text-left', radiusMd, cardClass]"
            >
                <div v-if="item.icon || block.numbered" :class="[iconClass, centered ? 'mx-auto' : '']">
                    <Icon v-if="item.icon" :name="item.icon" />
                    <span v-else :class="['font-semibold', theme.headingText]">{{ i + 1 }}</span>
                </div>
                <h3 v-if="item.title" :class="['text-xl font-semibold leading-snug', theme.headingText]">{{ item.title }}</h3>
                <p v-if="item.text" :class="['text-sm leading-relaxed', theme.bodyText]">{{ item.text }}</p>
                <span v-if="item.link && (item.link_label || !interactive)" :class="['inline-block text-xs font-semibold uppercase tracking-[0.15em]', theme.accentText]">{{ item.link_label || 'Learn more' }} →</span>
            </component>
        </div>
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-5xl border-2 border-dashed p-8 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add cards to show them here
    </section>
</template>
