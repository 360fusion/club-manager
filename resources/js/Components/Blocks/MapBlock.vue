<script setup>
// A map of one place: OpenStreetMap street tiles or satellite imagery, drawn by TileMap (no library, no API key).
// On the live site nothing is requested from the tile servers until a visitor asks for the map (or immediately, if
// the block says so); in the builder it shows straight away.
import { computed, ref } from 'vue';
import TileMap from '@/Components/Blocks/TileMap.vue';
import { hasCoordinates, osmLargerUrl, osmDirectionsUrl, MAP_HEIGHTS } from '@/Utils/osmMap';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
});

const loaded = ref(false);

const located = computed(() => hasCoordinates(props.block));
const showFrame = computed(() => located.value && (!props.interactive || props.block.load_mode === 'immediate' || loaded.value));
const layout = computed(() => props.block.layout || 'stacked');
const sideBySide = computed(() => layout.value === 'side_left' || layout.value === 'side_right');
const height = computed(() => MAP_HEIGHTS[props.block.height] || MAP_HEIGHTS.medium);
const heightClass = computed(() => ({ 256: 'h-64', 384: 'h-96', 512: 'h-[32rem]' }[height.value]));
const hasDetails = computed(() => layout.value !== 'map_only' && !!(props.block.location_name || props.block.address || props.block.notes
    || (located.value && (props.block.show_directions !== false || props.block.show_larger_link !== false))));
const label = computed(() => props.block.location_name || props.block.address || 'this location');
</script>

<template>
    <section v-if="located || !interactive" class="max-w-5xl mx-auto space-y-6">
        <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>

        <div v-if="!located" :class="['flex items-center justify-center border-2 border-dashed text-sm font-semibold opacity-70 text-center p-6', heightClass, radiusMd, theme.bodyText]">
            Find the address or enter a latitude and longitude to show the map
        </div>

        <div v-else :class="['grid gap-6 items-start', sideBySide ? 'md:grid-cols-2' : '']">
            <div :class="[layout === 'side_right' ? 'md:order-2' : '']">
                <div :class="['relative w-full overflow-hidden', showFrame ? '' : ['border shadow-lg', heightClass, radiusMd, theme.cardBg]]">
                    <TileMap
                        v-if="showFrame"
                        :lat="Number(block.lat)"
                        :lng="Number(block.lng)"
                        :zoom="Number(block.zoom) || 16"
                        :height="height"
                        :style-key="block.map_style || 'street'"
                        :allow-switch="block.allow_style_switch !== false"
                        :label="`Map of ${label}`"
                        :radius="radiusMd"
                    />
                    <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                        <span class="text-4xl" aria-hidden="true">📍</span>
                        <p :class="['text-sm max-w-xs', theme.bodyText]">The map is loaded from {{ block.map_style && block.map_style !== 'street' ? 'Esri' : 'OpenStreetMap' }} when you choose to show it.</p>
                        <button type="button" :class="['py-2.5 px-5 font-bold text-sm shadow-lg hover:scale-105 transition-transform cursor-pointer', radiusMd, theme.heroCta]" @click="loaded = true">Show the map</button>
                    </div>
                </div>
            </div>

            <div v-if="hasDetails" class="space-y-3">
                <h3 v-if="block.location_name" :class="['text-xl font-bold', theme.headingText]">{{ block.location_name }}</h3>
                <p v-if="block.address" :class="['whitespace-pre-line', theme.bodyText]">{{ block.address }}</p>
                <p v-if="block.notes" :class="['whitespace-pre-line text-sm opacity-80', theme.bodyText]">{{ block.notes }}</p>
                <div v-if="located && (block.show_directions !== false || block.show_larger_link !== false)" class="flex flex-wrap gap-3 pt-1">
                    <component
                        :is="interactive ? 'a' : 'span'"
                        v-if="block.show_directions !== false"
                        :href="interactive ? osmDirectionsUrl(block) : undefined"
                        target="_blank"
                        rel="noopener"
                        :class="['inline-block py-2.5 px-5 font-bold text-sm shadow-lg transition-all hover:scale-105', radiusMd, theme.heroCta]"
                    >Get directions</component>
                    <component
                        :is="interactive ? 'a' : 'span'"
                        v-if="block.show_larger_link !== false"
                        :href="interactive ? osmLargerUrl(block) : undefined"
                        target="_blank"
                        rel="noopener"
                        :class="['inline-block py-2.5 px-5 font-bold text-sm border transition-all hover:scale-105', radiusMd, theme.cardBg]"
                    >Open larger map</component>
                </div>
            </div>
        </div>
    </section>
</template>
