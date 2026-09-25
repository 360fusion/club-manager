<script setup>
// The "Section style" panel any element can have: whether it sits on the page as the theme draws it, in a full-width
// coloured band, or in a boxed panel, plus the background, text tone, spacing and an anchor name for #links.
// Mutates `block.section` in place (created on first change); the server tidies whatever is sent.
import { computed } from 'vue';
import { SECTION_DEFAULTS } from '@/Support/blockSection';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    // Whether other elements exist to copy this style to.
    canApplyToOthers: { type: Boolean, default: false },
});

const emit = defineEmits(['media', 'apply-all']);

const section = computed(() => ({ ...SECTION_DEFAULTS, ...(props.block.section || {}) }));

const set = (patch) => {
    props.block.section = { ...SECTION_DEFAULTS, ...(props.block.section || {}), ...patch };
};

// Changing the background, text colour or spacing gives the element its own full-width band; the settings do nothing
// while the element is left as the theme draws it.
const setStyle = (patch) => set(section.value.mode === 'auto' ? { mode: 'band', ...patch } : patch);

const PRESETS = [
    { label: 'Theme default', patch: { mode: 'auto', bg: 'none', tone: 'auto' } },
    { label: 'White band', patch: { mode: 'band', bg: 'none', tone: 'auto' } },
    { label: 'Cream band', patch: { mode: 'band', bg: 'tint', tone: 'auto' } },
    { label: 'Navy band', patch: { mode: 'band', bg: 'primary', tone: 'auto' } },
    { label: 'Accent band', patch: { mode: 'band', bg: 'accent', tone: 'auto' } },
    { label: 'Navy panel', patch: { mode: 'contained', bg: 'primary', tone: 'auto' } },
];

const isPreset = (preset) => Object.entries(preset.patch).every(([key, value]) => section.value[key] === value);

const summary = computed(() => {
    const s = section.value;
    if (s.mode === 'auto') return 'Theme default';

    const where = s.mode === 'band' ? 'Full-width' : 'Boxed';
    const what = { none: 'plain', tint: 'tinted', primary: 'dark', accent: 'accent colour', custom: 'custom colour', image: 'photo' }[s.bg] || 'plain';

    return `${where}, ${what}`;
});

const BG_LABELS = { none: 'No background (page colour)', tint: 'Soft tint (cream)', primary: 'Dark theme colour (navy)', accent: 'Accent colour (gold)', custom: 'Custom colour…', image: 'Photo…' };
</script>

<template>
    <details class="group rounded-xl border border-slate-200 bg-slate-50/60 text-xs dark:border-slate-800 dark:bg-slate-900/40">
        <summary class="flex cursor-pointer select-none items-center justify-between gap-2 px-3 py-2 font-bold text-slate-700 dark:text-slate-200">
            <span>🎨 Section style <span class="font-normal text-slate-400">· {{ summary }}<span v-if="section.anchor"> · #{{ section.anchor }}</span></span></span>
            <span class="text-[10px] font-normal text-slate-400 group-open:hidden">Edit</span>
        </summary>

        <div class="space-y-3 border-t border-slate-200 p-3 dark:border-slate-800">
            <div>
                <p :class="LABEL">Look</p>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Section style presets">
                    <button
                        v-for="preset in PRESETS"
                        :key="preset.label"
                        type="button"
                        :aria-pressed="isPreset(preset)"
                        :class="['rounded-lg border px-2.5 py-1 text-[11px] font-bold cursor-pointer', isPreset(preset) ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300']"
                        @click="set(preset.patch)"
                    >{{ preset.label }}</button>
                </div>
                <p :class="HINT">A full-width band runs edge to edge behind the element. A boxed panel keeps it inside the page column. Changing any setting below gives the element its own band; set Width back to "As the theme draws it" to undo.</p>
            </div>

            <div :class="[CARD, 'grid grid-cols-1 gap-3 sm:grid-cols-2']">
                <div>
                    <label :for="`block-${index}-section-mode`" :class="LABEL">Width</label>
                    <select :id="`block-${index}-section-mode`" :value="section.mode" :class="SELECT" @change="set({ mode: $event.target.value })">
                        <option value="auto">As the theme draws it</option>
                        <option value="band">Full-width band</option>
                        <option value="contained">Boxed panel in the page</option>
                    </select>
                </div>
                <div>
                    <label :for="`block-${index}-section-bg`" :class="LABEL">Background</label>
                    <select :id="`block-${index}-section-bg`" :value="section.bg" :class="SELECT" @change="setStyle({ bg: $event.target.value })">
                        <option v-for="(label, key) in BG_LABELS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <div v-if="section.bg === 'custom'" class="sm:col-span-2">
                    <label :for="`block-${index}-section-color`" :class="LABEL">Colour</label>
                    <div class="flex items-center gap-2">
                        <input :id="`block-${index}-section-color`" type="color" :value="section.bg_color || '#1b2a4a'" class="h-9 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5 dark:border-slate-700" aria-label="Pick a background colour" @input="set({ bg_color: $event.target.value })" />
                        <input :value="section.bg_color" type="text" maxlength="7" placeholder="#1b2a4a" :class="[SMALL_INPUT, 'font-mono text-[11px]']" aria-label="Background colour, as #rrggbb" @input="set({ bg_color: $event.target.value })" />
                    </div>
                </div>

                <div v-if="section.bg === 'image'" class="space-y-2 sm:col-span-2">
                    <div class="flex items-center justify-between">
                        <label :for="`block-${index}-section-image`" :class="LABEL">Background photo</label>
                        <div class="flex items-center gap-1.5">
                            <button v-if="section.bg_image" type="button" :class="MINI_DANGER" @click="set({ bg_image: '' })">🗑️ Clear</button>
                            <button type="button" :class="MINI_BUTTON" @click="emit('media', 'section_image', block)">📁 Media Library</button>
                        </div>
                    </div>
                    <input :id="`block-${index}-section-image`" :value="section.bg_image" type="text" placeholder="https://example.com/photo.jpg" :class="[SMALL_INPUT, 'font-mono text-[11px]']" @input="set({ bg_image: $event.target.value })" />
                    <div>
                        <label :for="`block-${index}-section-overlay`" :class="LABEL">Photo darkening</label>
                        <select :id="`block-${index}-section-overlay`" :value="section.overlay" :class="SELECT" @change="set({ overlay: $event.target.value })">
                            <option value="light">Light</option>
                            <option value="medium">Medium</option>
                            <option value="strong">Strong</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label :for="`block-${index}-section-tone`" :class="LABEL">Text colour</label>
                    <select :id="`block-${index}-section-tone`" :value="section.tone" :class="SELECT" @change="setStyle({ tone: $event.target.value })">
                        <option value="auto">Automatic (light on dark)</option>
                        <option value="light">Dark text</option>
                        <option value="dark">Light text</option>
                    </select>
                </div>
                <div>
                    <label :for="`block-${index}-section-padding`" :class="LABEL">Space above and below</label>
                    <select :id="`block-${index}-section-padding`" :value="section.padding" :class="SELECT" @change="setStyle({ padding: $event.target.value })">
                        <option value="auto">Standard</option>
                        <option value="none">None</option>
                        <option value="sm">Small</option>
                        <option value="md">Medium</option>
                        <option value="lg">Large</option>
                        <option value="xl">Extra large</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[12rem] flex-1">
                    <label :for="`block-${index}-section-anchor`" :class="LABEL">Anchor name <span class="font-normal text-slate-400">(optional)</span></label>
                    <input :id="`block-${index}-section-anchor`" :value="section.anchor" type="text" maxlength="60" placeholder="e.g. about" :class="INPUT" @input="set({ anchor: $event.target.value })" />
                    <p :class="HINT">Lets a button or menu link jump here with #{{ section.anchor || 'about' }}.</p>
                </div>
                <button v-if="canApplyToOthers && section.mode !== 'auto'" type="button" :class="[MINI_BUTTON, 'mb-5 px-3 py-1.5 text-[11px]']" @click="emit('apply-all')">Use this look on every element</button>
            </div>
        </div>
    </details>
</template>
