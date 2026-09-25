<script setup>
// Edit form for the Map block. "Find on map" asks the server to look the address up on OpenStreetMap (Nominatim);
// the latitude and longitude can also be typed in. Mutates `block` in place.
import { computed, onBeforeUnmount, ref } from 'vue';
import { postJson } from '@/Utils/postJson';
import { clampZoom, hasCoordinates, mapStyle, osmLargerUrl } from '@/Utils/osmMap';
import MapPicker from './MapPicker.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, HINT } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    club: { type: Object, required: true },
    clubAddresses: { type: Array, default: () => [] },
});

const zoomHint = computed(() => {
    const z = Number(props.block.zoom) || 16;
    if (z <= 6) return 'country';
    if (z <= 10) return 'region';
    if (z <= 13) return 'town';
    if (z <= 15) return 'neighbourhood';
    return 'street';
});

const finding = ref(false);
const message = ref('');
const messageIsError = ref(false);
const candidates = ref([]);
const suggestions = ref([]);
let suggestTimer = null;
let suppressSuggest = false;

const say = (text, isError = false) => {
    message.value = text;
    messageIsError.value = isError;
};

const place = ({ lat, lng }) => {
    props.block.lat = lat;
    props.block.lng = lng;
};

// Search OpenStreetMap for what is in the address box. The best match moves the pin straight away; any other
// matches are listed so the right one can be picked, and the pin can always be dragged to the exact spot.
const find = async (text = null) => {
    const query = (text ?? props.block.address ?? props.block.location_name ?? '').trim();
    if (!query) return say('Type an address first.', true);

    suggestions.value = [];
    finding.value = true;
    say('');
    candidates.value = [];
    const { ok, data } = await postJson(route('admin.pages.geocode', { clubSlug: props.club.slug }), { q: query }, 'Could not reach the map search. Check your connection.');
    finding.value = false;

    if (ok && data.found) {
        place(data.results[0]);
        candidates.value = data.results.length > 1 ? data.results : [];
        say(data.approximate
            ? `Found by "${data.matched}" only, so the pin is close but not exact. Drag it to the right spot.`
            : `Found: ${data.results[0].name}`);
    } else if (ok) {
        say('No match found. Click the map to place the pin yourself, or try the town and postcode.', true);
    } else {
        say(data.message || 'The map search is not available right now. Click the map to place the pin instead.', true);
    }
};

const chooseCandidate = (candidate) => {
    place(candidate);
    say(`Pin moved to: ${candidate.name}`);
};

// Halls from the directory, matched as the address is typed. This is answered by our own server, not by
// OpenStreetMap, which does not allow type-ahead searching.
const onAddressInput = () => {
    clearTimeout(suggestTimer);
    if (suppressSuggest) {
        suppressSuggest = false;
        return;
    }
    const term = (props.block.address || '').trim();
    if (term.length < 3) {
        suggestions.value = [];
        return;
    }
    suggestTimer = setTimeout(async () => {
        try {
            const response = await fetch(`${route('admin.pages.places', { clubSlug: props.club.slug })}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            suggestions.value = response.ok ? (await response.json()).places || [] : [];
        } catch {
            suggestions.value = [];
        }
    }, 250);
};

const chooseHall = (hall) => {
    suppressSuggest = true;
    suggestions.value = [];
    props.block.location_name = hall.name;
    props.block.address = hall.address;
    find(hall.address);
};

onBeforeUnmount(() => clearTimeout(suggestTimer));
</script>

<template>
    <div class="space-y-3 text-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label :for="`block-${index}-map-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-map-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Find us" :class="[INPUT, 'font-bold']" />
            </div>
            <div>
                <label :for="`block-${index}-map-name`" :class="LABEL">Place name <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-map-name`" v-model="block.location_name" type="text" maxlength="200" placeholder="e.g. Freemasons' Hall" :class="INPUT" />
            </div>
        </div>

        <div>
            <label :for="`block-${index}-map-address`" :class="LABEL">Address</label>
            <div class="relative">
                <textarea :id="`block-${index}-map-address`" v-model="block.address" rows="2" maxlength="500" placeholder="Start typing a hall name, town or postcode, or the full address" autocomplete="off" :class="[INPUT, 'font-medium text-xs']" @input="onAddressInput"></textarea>
                <ul v-if="suggestions.length" class="absolute z-20 left-0 right-0 mt-1 max-h-60 overflow-auto rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xl" role="listbox" aria-label="Halls in the directory">
                    <li class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Halls in the directory</li>
                    <li v-for="hall in suggestions" :key="hall.id" role="option">
                        <button type="button" class="w-full text-left px-3 py-2 text-xs hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer" @click="chooseHall(hall)">
                            <span class="font-bold text-slate-800 dark:text-slate-100">{{ hall.name }}</span>
                            <span class="block text-slate-500 dark:text-slate-400">{{ hall.address }}</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2">
                <button type="button" :disabled="finding" class="px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-[11px] cursor-pointer disabled:opacity-50" @click="find()">{{ finding ? 'Searching…' : '🔍 Search the map' }}</button>
                <button v-for="option in clubAddresses" :key="option.label" type="button" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] cursor-pointer" @click="block.address = option.address; find(option.address)">Use {{ option.label }}</button>
            </div>
            <p v-if="message" :class="['text-[11px] font-bold mt-1', messageIsError ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400']" role="status">{{ message }}</p>
            <div v-if="candidates.length" class="mt-2 space-y-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Other matches</p>
                <button v-for="candidate in candidates.slice(1)" :key="candidate.lat + ',' + candidate.lng" type="button" class="block w-full text-left px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-[11px] hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer" @click="chooseCandidate(candidate)">{{ candidate.name }}</button>
            </div>
            <p :class="HINT">Halls come from your own directory as you type. "Search the map" asks OpenStreetMap's free Nominatim service, only when you press the button; the position is saved in the block.</p>
        </div>

        <MapPicker :lat="block.lat" :lng="block.lng" :zoom="block.zoom" :style-key="block.map_style || 'street'" @pick="place" @zoom="(z) => (block.zoom = z)" />

        <div :class="[CARD, 'grid grid-cols-2 sm:grid-cols-4 gap-3 items-end']">
            <div>
                <label :for="`block-${index}-map-lat`" :class="LABEL">Latitude</label>
                <input :id="`block-${index}-map-lat`" v-model.number="block.lat" type="number" step="any" min="-90" max="90" placeholder="51.5155" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
            </div>
            <div>
                <label :for="`block-${index}-map-lng`" :class="LABEL">Longitude</label>
                <input :id="`block-${index}-map-lng`" v-model.number="block.lng" type="number" step="any" min="-180" max="180" placeholder="-0.1201" :class="[SMALL_INPUT, 'font-mono text-[11px]']" />
            </div>
            <div>
                <label :for="`block-${index}-map-style`" :class="LABEL">View</label>
                <select :id="`block-${index}-map-style`" v-model="block.map_style" :class="SELECT" @change="block.zoom = clampZoom(block.zoom, block.map_style)">
                    <option value="street">Street map</option>
                    <option value="satellite">Satellite</option>
                    <option value="hybrid">Satellite with labels</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-map-height`" :class="LABEL">Height</label>
                <select :id="`block-${index}-map-height`" v-model="block.height" :class="SELECT">
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                </select>
            </div>
            <div class="col-span-2 sm:col-span-4">
                <label :for="`block-${index}-map-zoom`" :class="LABEL">Zoom level: <span class="font-mono">{{ block.zoom }}</span> <span class="font-normal text-slate-400">({{ zoomHint }})</span></label>
                <input :id="`block-${index}-map-zoom`" v-model.number="block.zoom" type="range" min="3" :max="mapStyle(block.map_style).maxZoom" step="1" class="w-full accent-blue-600" />
                <div class="flex justify-between text-[10px] text-slate-400"><span>Wide (country)</span><span>Town</span><span>Close (street)</span></div>
            </div>
            <label :class="[CHECK_LABEL, 'col-span-2 sm:col-span-4']"><input v-model="block.allow_style_switch" type="checkbox" :class="CHECK" /> Let visitors switch between the street map and satellite</label>
            <p v-if="hasCoordinates(block)" class="col-span-2 sm:col-span-4 text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">✓ Location set. <a :href="osmLargerUrl(block)" target="_blank" rel="noopener" class="underline">Check it on OpenStreetMap</a></p>
            <p v-else class="col-span-2 sm:col-span-4 text-[11px] text-slate-400">No location yet, so nothing will show on the live site.</p>
        </div>

        <div>
            <label :for="`block-${index}-map-notes`" :class="LABEL">Notes <span class="font-normal text-slate-400">(optional: parking, access, plain text)</span></label>
            <textarea :id="`block-${index}-map-notes`" v-model="block.notes" rows="2" maxlength="1000" :class="[INPUT, 'font-medium text-xs']"></textarea>
        </div>

        <div :class="[CARD, 'grid grid-cols-1 sm:grid-cols-3 gap-3 items-end']">
            <div>
                <label :for="`block-${index}-map-layout`" :class="LABEL">Layout</label>
                <select :id="`block-${index}-map-layout`" v-model="block.layout" :class="SELECT">
                    <option value="stacked">Map, then details</option>
                    <option value="map_only">Map only</option>
                    <option value="side_left">Map left, details right</option>
                    <option value="side_right">Details left, map right</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-map-load`" :class="LABEL">Loading</label>
                <select :id="`block-${index}-map-load`" v-model="block.load_mode" :class="SELECT">
                    <option value="click">Visitor presses "Show the map"</option>
                    <option value="immediate">Load straight away</option>
                </select>
            </div>
            <div class="space-y-2">
                <label :class="CHECK_LABEL"><input v-model="block.show_directions" type="checkbox" :class="CHECK" /> "Get directions" button</label>
                <label :class="CHECK_LABEL"><input v-model="block.show_larger_link" type="checkbox" :class="CHECK" /> "Open larger map" button</label>
            </div>
            <p :class="[HINT, 'sm:col-span-3']">Until a visitor presses the button nothing is requested from OpenStreetMap. "Straight away" is simpler but contacts OpenStreetMap on every page view.</p>
        </div>
    </div>
</template>
