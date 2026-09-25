<script setup>
// A standalone section title: small label, serif heading, divider and a short introduction.
import SectionHeader from '@/Components/Blocks/SectionHeader.vue';

defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});
</script>

<template>
    <section v-if="block.title || block.eyebrow || block.intro" class="mx-auto w-full max-w-4xl">
        <SectionHeader :eyebrow="block.eyebrow" :title="block.title" :intro="block.intro" :divider="block.show_divider !== false" :align="block.align === 'left' ? 'left' : 'center'" :theme="theme" />
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-3xl border-2 border-dashed p-6 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add a title to show this heading
    </section>
</template>
