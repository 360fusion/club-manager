<script setup>
// A small pan-and-zoom OpenStreetMap for placing the Map block's pin: click the map, drag the pin, or use the
// arrow keys. It draws the standard OSM tiles itself, so it needs no map library. Emits `pick` with the new
// position; the parent stores it in the block.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { clampZoom, mapStyle, project, tilesFor, unproject } from '@/Utils/osmMap';

const props = defineProps({
    lat: { type: [Number, String], default: null },
    lng: { type: [Number, String], default: null },
    zoom: { type: [Number, String], default: 16 },
    styleKey: { type: String, default: 'street' },
});

const emit = defineEmits(['pick', 'zoom']);

const TILE = 256;
const HEIGHT = 300;

const box = ref(null);
const width = ref(600);
const zoom = ref(props.lat === null || props.lat === '' ? 5 : clampZoom(props.zoom, props.styleKey));
const center = ref({ lat: 54.5, lng: -2.5 });
let resizeObserver = null;

const num = (v) => (v === null || v === '' || !Number.isFinite(Number(v)) ? null : Number(v));
const pin = computed(() => {
    const lat = num(props.lat);
    const lng = num(props.lng);
    return lat !== null && lng !== null && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? { lat, lng } : null;
});

const round = (n) => Math.round(n * 1e6) / 1e6;

const centrePx = computed(() => project(center.value.lat, center.value.lng, zoom.value));
const left = computed(() => centrePx.value.x - width.value / 2);
const top = computed(() => centrePx.value.y - HEIGHT / 2);

const view = computed(() => tilesFor({ lat: center.value.lat, lng: center.value.lng, zoom: zoom.value, width: width.value, height: HEIGHT, style: props.styleKey }));
const tiles = computed(() => view.value.tiles);
const current = computed(() => mapStyle(props.styleKey));

const pinAt = computed(() => {
    if (!pin.value) return null;
    const p = project(pin.value.lat, pin.value.lng, zoom.value);
    return { x: p.x - left.value, y: p.y - top.value };
});

const pointToLatLng = (px, py) => unproject(left.value + px, top.value + py, zoom.value);
const setPin = (px, py) => {
    const point = pointToLatLng(px, py);
    emit('pick', { lat: round(Math.max(-85, Math.min(85, point.lat))), lng: round(((point.lng + 540) % 360) - 180) });
};

const setZoom = (value) => {
    zoom.value = clampZoom(value, props.styleKey);
    emit('zoom', zoom.value);
};
const changeZoom = (by) => setZoom(zoom.value + by);

// A position that arrives from outside (an address search, typed numbers) recentres the map on it; a pin the
// user is placing here is already on screen, so it is left alone.
let placingHere = false;
watch(pin, (value) => {
    if (!value || placingHere) return;
    center.value = { lat: value.lat, lng: value.lng };
    if (zoom.value < 12) setZoom(17);
}, { immediate: true });

// The zoom is the block's own setting, so the picker always shows what the live map will.
watch(() => props.zoom, (value) => { zoom.value = clampZoom(value, props.styleKey); });
watch(() => props.styleKey, (value) => { zoom.value = clampZoom(zoom.value, value); });

let drag = null;
const relative = (event) => {
    const rect = box.value.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
};
const startPan = (event) => {
    if (event.button !== 0) return;
    box.value.setPointerCapture(event.pointerId);
    drag = { mode: 'pan', sx: event.clientX, sy: event.clientY, cx: centrePx.value.x, cy: centrePx.value.y, moved: false };
};
const startPin = (event) => {
    box.value.setPointerCapture(event.pointerId);
    drag = { mode: 'pin', sx: event.clientX, sy: event.clientY, moved: false };
};
const move = (event) => {
    if (!drag) return;
    const dx = event.clientX - drag.sx;
    const dy = event.clientY - drag.sy;
    if (Math.hypot(dx, dy) > 3) drag.moved = true;
    if (!drag.moved) return;
    if (drag.mode === 'pan') {
        center.value = unproject(drag.cx - dx, drag.cy - dy, zoom.value);
    } else {
        placingHere = true;
        const p = relative(event);
        setPin(p.x, p.y);
    }
};
const end = (event) => {
    if (!drag) return;
    if (drag.mode === 'pan' && !drag.moved) {
        placingHere = true;
        const p = relative(event);
        setPin(p.x, p.y);
    }
    drag = null;
    setTimeout(() => { placingHere = false; }, 0);
};

let lastWheel = 0;
const wheel = (event) => {
    const now = Date.now();
    if (now - lastWheel < 180) return;
    lastWheel = now;
    changeZoom(event.deltaY < 0 ? 1 : -1);
};

const keys = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
const key = (event) => {
    if (event.key === '+' || event.key === '=') return changeZoom(1);
    if (event.key === '-') return changeZoom(-1);
    const step = keys[event.key];
    if (!step || !pinAt.value) return;
    event.preventDefault();
    placingHere = true;
    setPin(pinAt.value.x + step[0] * 4, pinAt.value.y + step[1] * 4);
    setTimeout(() => { placingHere = false; }, 0);
};

onMounted(() => {
    width.value = box.value.clientWidth || 600;
    resizeObserver = new ResizeObserver(() => { width.value = box.value?.clientWidth || width.value; });
    resizeObserver.observe(box.value);
});
onBeforeUnmount(() => resizeObserver?.disconnect());
</script>

<template>
    <div>
        <div
            ref="box"
            class="relative w-full overflow-hidden rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-200 select-none touch-none cursor-grab active:cursor-grabbing focus:outline-none focus:ring-2 focus:ring-blue-500"
            :style="{ height: `${HEIGHT}px` }"
            tabindex="0"
            role="application"
            aria-label="Map. Click to place the pin, drag the pin to move it, use plus and minus to zoom and the arrow keys to nudge the pin."
            @pointerdown="startPan"
            @pointermove="move"
            @pointerup="end"
            @pointercancel="end"
            @wheel.prevent="wheel"
            @keydown="key"
        >
            <img
                v-for="tile in tiles"
                :key="tile.key"
                :src="tile.src"
                alt=""
                draggable="false"
                referrerpolicy="strict-origin-when-cross-origin"
                class="absolute max-w-none pointer-events-none"
                :style="{ left: `${tile.x}px`, top: `${tile.y}px`, width: `${TILE}px`, height: `${TILE}px` }"
            />

            <svg
                v-if="pinAt"
                viewBox="0 0 24 32"
                class="absolute w-8 h-10 -translate-x-1/2 -translate-y-full cursor-move drop-shadow-lg"
                :style="{ left: `${pinAt.x}px`, top: `${pinAt.y}px` }"
                aria-hidden="true"
                @pointerdown.stop="startPin"
            >
                <path d="M12 1C6.5 1 2.5 5 2.5 10.2 2.5 17.2 12 31 12 31s9.5-13.8 9.5-20.8C21.5 5 17.5 1 12 1z" fill="#dc2626" stroke="#fff" stroke-width="1.5" />
                <circle cx="12" cy="10.5" r="3.6" fill="#fff" />
            </svg>

            <div class="absolute top-2 right-2 flex flex-col gap-1">
                <button type="button" class="w-8 h-8 rounded-lg bg-white/95 text-slate-800 font-bold shadow cursor-pointer hover:bg-white" aria-label="Zoom in" @pointerdown.stop @click="changeZoom(1)">+</button>
                <button type="button" class="w-8 h-8 rounded-lg bg-white/95 text-slate-800 font-bold shadow cursor-pointer hover:bg-white" aria-label="Zoom out" @pointerdown.stop @click="changeZoom(-1)">−</button>
            </div>

            <a :href="current.creditUrl" target="_blank" rel="noopener" class="absolute bottom-0 right-0 max-w-full truncate px-1.5 py-0.5 text-[10px] bg-white/80 text-slate-700 hover:underline" @pointerdown.stop>{{ current.credit }}</a>
        </div>
        <p class="text-[10px] text-slate-400 mt-1">Click the map to drop the pin, or drag the pin to the exact spot. Scroll or use + and − to zoom.</p>
    </div>
</template>
