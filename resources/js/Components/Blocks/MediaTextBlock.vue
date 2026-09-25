<script setup>
// Image & Text: a photo beside a section's title and formatted text, with an optional button. The photo can sit on
// either side; on a phone it stacks above the text.
import { Link } from '@inertiajs/vue3';
import SectionHeader from '@/Components/Blocks/SectionHeader.vue';

defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    resolveUrl: { type: Function, default: (url) => url },
    radiusLg: { type: String, default: 'rounded-3xl' },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const isInternal = (url) => typeof url === 'string' && url.startsWith('/');
</script>

<template>
    <section v-if="block.image_url || block.title || block.eyebrow || block.content" class="mx-auto w-full max-w-6xl">
        <div :class="['grid items-center gap-10 lg:gap-16', block.image_url ? 'md:grid-cols-2' : '']">
            <div v-if="block.image_url" :class="[block.layout === 'image_right' ? 'md:order-2' : '']">
                <img :src="block.image_url" :alt="block.image_alt || ''" loading="lazy" :class="['h-auto w-full object-cover shadow-xl', radiusLg]" />
            </div>

            <div class="space-y-5">
                <SectionHeader :eyebrow="block.eyebrow" :title="block.title" :divider="block.show_divider !== false" align="left" :theme="theme" />
                <div v-if="block.content" :class="['prose max-w-none prose-p:leading-relaxed prose-li:my-1', theme.onDark ? 'prose-invert' : 'dark:prose-invert', theme.bodyText]" v-html="block.content"></div>
                <div v-if="block.button_label && (block.button_url || !interactive)">
                    <component
                        :is="interactive ? (isInternal(block.button_url) ? Link : 'a') : 'span'"
                        :href="interactive ? resolveUrl(block.button_url) : undefined"
                        :class="['inline-block px-7 py-3 text-sm font-bold shadow-lg transition-all hover:scale-105', radiusMd, theme.heroCta]"
                    >{{ block.button_label }}</component>
                </div>
            </div>
        </div>
    </section>
    <section v-else-if="!interactive" :class="['mx-auto max-w-3xl border-2 border-dashed p-6 text-center text-sm font-semibold opacity-70', radiusMd, theme.bodyText]">
        Add a photo, title or text to show this element
    </section>
</template>
