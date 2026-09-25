<script setup>
// The public map: tiles for the chosen style with a pin at the block's location, zoom buttons and (optionally) a
// switch between street and satellite. It is view-only (no dragging), so it never fights the page for a swipe.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MAP_STYLES, clampZoom, mapStyle, tilesFor } from '@/Utils/osmMap';

const props = defineProps({
    lat: { type: Number, required: true },
    lng: { type: Number, required: true },
    zoom: { type: Number, default: 16 },
    height: { type: Number, default: 384 },
    styleKey: { type: String, default: 'street' },
    allowSwitch: { type: Boolean, default: true },
    label: { type: String, default: 'Map' },
    radius: { type: String, default: 'rounded-2xl' },
    controls: { type: Boolean, default: true },
});

const box = ref(null);
const width = ref(640);
const style = ref(props.styleKey);
const zoom = ref(clampZoom(props.zoom, props.styleKey));
let observer = null;

watch(() => props.styleKey, (value) => { style.value = value; zoom.value = clampZoom(zoom.value, value); });
watch(() => props.zoom, (value) => { zoom.value = clampZoom(value, style.value); });

const view = computed(() => tilesFor({ lat: props.lat, lng: props.lng, zoom: zoom.value, width: width.value, height: props.height, style: style.value }));
const current = computed(() => mapStyle(style.value));
const change = (by) => { zoom.value = clampZoom(zoom.value + by, style.value); };
const choose = (key) => { style.value = key; zoom.value = clampZoom(zoom.value, key); };

onMounted(() => {
    width.value = box.value.clientWidth || 640;
    observer = new ResizeObserver(() => { width.value = box.value?.clientWidth || width.value; });
    observer.observe(box.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="box" :class="['relative w-full overflow-hidden border shadow-lg bg-slate-200', radius]" :style="{ height: `${height}px` }" role="img" :aria-label="label">
        <img
            v-for="tile in view.tiles"
            :key="tile.key"
            :src="tile.src"
            alt=""
            draggable="false"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
            class="absolute max-w-none select-none"
            :style="{ left: `${tile.x}px`, top: `${tile.y}px`, width: '256px', height: '256px' }"
        />

        <svg viewBox="0 0 24 32" class="absolute w-8 h-10 -translate-x-1/2 -translate-y-full drop-shadow-lg" :style="{ left: '50%', top: '50%' }" aria-hidden="true">
            <path d="M12 1C6.5 1 2.5 5 2.5 10.2 2.5 17.2 12 31 12 31s9.5-13.8 9.5-20.8C21.5 5 17.5 1 12 1z" fill="#dc2626" stroke="#fff" stroke-width="1.5" />
            <circle cx="12" cy="10.5" r="3.6" fill="#fff" />
        </svg>

        <div v-if="controls" class="absolute top-2 right-2 flex flex-col gap-1">
            <button type="button" class="w-8 h-8 rounded-lg bg-white/95 text-slate-800 font-bold shadow cursor-pointer hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Zoom in" :disabled="zoom >= current.maxZoom" @click="change(1)">+</button>
            <button type="button" class="w-8 h-8 rounded-lg bg-white/95 text-slate-800 font-bold shadow cursor-pointer hover:bg-white disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Zoom out" :disabled="zoom <= 3" @click="change(-1)">−</button>
        </div>

        <div v-if="controls && allowSwitch" class="absolute top-2 left-2 inline-flex rounded-lg overflow-hidden shadow text-[11px] font-bold" role="group" aria-label="Map view">
            <button
                v-for="key in ['street', 'satellite']"
                :key="key"
                type="button"
                :aria-pressed="(key === 'street') === (style === 'street')"
                :class="['px-2.5 py-1.5 cursor-pointer', (key === 'street') === (style === 'street') ? 'bg-slate-900 text-white' : 'bg-white/95 text-slate-800 hover:bg-white']"
                @click="choose(key === 'street' ? 'street' : (style === 'hybrid' ? 'hybrid' : 'satellite'))"
            >{{ key === 'street' ? 'Map' : 'Satellite' }}</button>
        </div>

        <a :href="current.creditUrl" target="_blank" rel="noopener" class="absolute bottom-0 right-0 max-w-full truncate px-1.5 py-0.5 text-[10px] bg-white/85 text-slate-700 hover:underline">{{ current.credit }}</a>
    </div>
</template>
