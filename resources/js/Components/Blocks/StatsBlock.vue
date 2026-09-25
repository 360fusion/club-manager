<script setup>
// A row of headline figures with an icon, a big number (with an optional "+" or "%" suffix) and a label. Whole numbers
// count up when the row scrolls into view; anyone who prefers reduced motion, and the builder's preview, sees the final
// figure straight away.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Icon from '@/Components/Ui/Icon.vue';
import SectionHeader from '@/Components/Blocks/SectionHeader.vue';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const items = computed(() => (Array.isArray(props.block.items) ? props.block.items : []));
const onDark = computed(() => !!props.theme.onDark);

const COLUMNS = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 sm:grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-3',
    4: 'grid-cols-2 lg:grid-cols-4',
};
const columns = computed(() => COLUMNS[Math.min(items.value.length, 4)] || COLUMNS[4]);

const iconClass = computed(() => (props.block.icon_style === 'plain'
    ? ['text-3xl', onDark.value ? 'text-[var(--cm-accent-bright)]' : 'text-[var(--cm-accent)]']
    : ['flex h-[52px] w-[52px] items-center justify-center rounded-full text-lg shadow-lg',
        onDark.value ? 'bg-[var(--cm-primary-soft)] text-white' : 'bg-[var(--cm-primary)] text-[var(--cm-accent)]']));

// ---- count-up ---------------------------------------------------------------------------------------------------
const root = ref(null);
const shown = ref({});
let observer = null;
let frame = 0;

const isWhole = (value) => /^\d{1,9}$/.test(String(value ?? '').trim());
const display = (item) => (item.id in shown.value ? shown.value[item.id] : item.number);
const animates = computed(() => props.interactive && props.block.count_up !== false && items.value.some((item) => isWhole(item.number)));

const run = () => {
    const started = performance.now();
    const duration = 1400;

    const step = (now) => {
        const t = Math.min(1, (now - started) / duration);
        const eased = 1 - (1 - t) ** 3;
        const next = {};
        items.value.forEach((item) => { if (isWhole(item.number)) next[item.id] = String(Math.round(Number(item.number) * eased)); });
        shown.value = next;
        if (t < 1) frame = requestAnimationFrame(step); else shown.value = {};
    };

    frame = requestAnimationFrame(step);
};

const setup = () => {
    if (!animates.value || typeof window === 'undefined' || !root.value) return;
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

    const zero = {};
    items.value.forEach((item) => { if (isWhole(item.number)) zero[item.id] = '0'; });
    shown.value = zero;

    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            observer.disconnect();
            run();
        }
    }, { threshold: 0.4 });
    observer.observe(root.value);
};

onMounted(setup);
watch(() => props.block.count_up, () => { observer?.disconnect(); cancelAnimationFrame(frame); shown.value = {}; setup(); });
onBeforeUnmount(() => { observer?.disconnect(); cancelAnimationFrame(frame); });
</script>

<template>
    <section v-if="items.length" ref="root" class="mx-auto w-full max-w-6xl space-y-10">
        <SectionHeader :title="block.heading" :theme="theme" />
        <div :class="['grid gap-8', columns]">
            <div v-for="(item, i) in items" :key="item.id || i" class="flex flex-col items-center px-4 py-3 text-center">
                <div v-if="item.icon" :class="['mb-4', iconClass]"><Icon :name="item.icon" /></div>
                <div class="leading-none">
                    <span :class="['text-4xl sm:text-5xl font-bold tabular-nums', theme.headingText]">{{ display(item) }}</span><span v-if="item.suffix" :class="['text-4xl sm:text-5xl font-bold', theme.accentText]">{{ item.suffix }}</span>
                </div>
                <span v-if="item.label" :class="['mt-3 block text-xs font-medium uppercase tracking-[0.15em]', theme.bodyText]">{{ item.label }}</span>
            </div>
        </div>
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-5xl border-2 border-dashed p-8 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add figures to show them here
    </section>
</template>
