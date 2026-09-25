<script setup>
// Edit form for the Stats block (a row of headline figures). Mutates `block` in place.
import IconPicker from './IconPicker.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
});

const MAX_ITEMS = 8;

if (!Array.isArray(props.block.items)) props.block.items = [];

const newId = () => `stat-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
const addItem = () => {
    if (props.block.items.length < MAX_ITEMS) props.block.items.push({ id: newId(), icon: 'star', number: '', suffix: '', label: '' });
};
const removeItem = (i) => props.block.items.splice(i, 1);
const move = (i, by) => {
    const j = i + by;
    if (j < 0 || j >= props.block.items.length) return;
    const list = props.block.items;
    [list[i], list[j]] = [list[j], list[i]];
};
</script>

<template>
    <div class="space-y-3 text-xs">
        <div>
            <label :for="`block-${index}-stats-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
            <input :id="`block-${index}-stats-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Our lodge in numbers" :class="[INPUT, 'font-bold']" />
        </div>

        <div :class="[CARD, 'grid grid-cols-1 items-end gap-3 sm:grid-cols-2']">
            <div>
                <label :for="`block-${index}-stats-icons`" :class="LABEL">Icons</label>
                <select :id="`block-${index}-stats-icons`" v-model="block.icon_style" :class="SELECT">
                    <option value="circle">In a circle</option>
                    <option value="plain">Plain</option>
                </select>
            </div>
            <label :class="[CHECK_LABEL, 'pb-2']"><input v-model="block.count_up" type="checkbox" :class="CHECK" /> Count up when scrolled into view</label>
        </div>

        <div v-for="(item, i) in block.items" :key="item.id" :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-500">Figure {{ i + 1 }}</span>
                <div class="flex items-center gap-1.5">
                    <button type="button" :class="MINI_BUTTON" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">▲</button>
                    <button type="button" :class="MINI_BUTTON" :disabled="i === block.items.length - 1" aria-label="Move down" @click="move(i, 1)">▼</button>
                    <button type="button" :class="MINI_DANGER" aria-label="Delete" @click="removeItem(i)">🗑️</button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <IconPicker v-model="item.icon" :label="`Figure ${i + 1} icon`" />
                <input v-model="item.number" type="text" maxlength="20" placeholder="Number, e.g. 150" :class="[SMALL_INPUT, 'font-bold']" :aria-label="`Figure ${i + 1} number`" />
                <input v-model="item.suffix" type="text" maxlength="8" placeholder="Suffix, e.g. +" :class="SMALL_INPUT" :aria-label="`Figure ${i + 1} suffix`" />
                <input v-model="item.label" type="text" maxlength="80" placeholder="Label, e.g. Years of history" :class="SMALL_INPUT" :aria-label="`Figure ${i + 1} label`" />
            </div>
        </div>
        <p :class="HINT">Whole numbers count up; anything else (like “£1.2m”) is shown as typed.</p>

        <button type="button" :disabled="block.items.length >= MAX_ITEMS" class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:border-slate-300 disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300" @click="addItem">➕ Add figure</button>
    </div>
</template>
