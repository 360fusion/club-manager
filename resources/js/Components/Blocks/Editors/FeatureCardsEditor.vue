<script setup>
// Edit form for the Icon Cards block. Mutates `block` in place.
import LinkField from './LinkField.vue';
import IconPicker from './IconPicker.vue';
import { LABEL, INPUT, SMALL_INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
    pages: { type: Array, default: () => [] },
    club: { type: Object, required: true },
});

const MAX_ITEMS = 12;

if (!Array.isArray(props.block.items)) props.block.items = [];

const newId = () => `card-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
const addItem = () => {
    if (props.block.items.length < MAX_ITEMS) props.block.items.push({ id: newId(), icon: 'star', title: '', text: '', link: '', link_label: '' });
};
const removeItem = (i) => props.block.items.splice(i, 1);
const duplicateItem = (i) => {
    if (props.block.items.length < MAX_ITEMS) props.block.items.splice(i + 1, 0, { ...props.block.items[i], id: newId() });
};
const move = (i, by) => {
    const j = i + by;
    if (j < 0 || j >= props.block.items.length) return;
    const list = props.block.items;
    [list[i], list[j]] = [list[j], list[i]];
};
</script>

<template>
    <div class="space-y-3 text-xs">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
                <label :for="`block-${index}-fc-eyebrow`" :class="LABEL">Small label <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-fc-eyebrow`" v-model="block.eyebrow" type="text" maxlength="80" placeholder="e.g. Our foundation" :class="INPUT" />
            </div>
            <div class="sm:col-span-2">
                <label :for="`block-${index}-fc-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-fc-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. What our lodge offers" :class="[INPUT, 'font-bold']" />
            </div>
        </div>
        <div>
            <label :for="`block-${index}-fc-intro`" :class="LABEL">Introduction <span class="font-normal text-slate-400">(optional)</span></label>
            <input :id="`block-${index}-fc-intro`" v-model="block.intro" type="text" maxlength="500" :class="INPUT" />
        </div>

        <div :class="[CARD, 'grid grid-cols-2 items-end gap-3 sm:grid-cols-4']">
            <div>
                <label :for="`block-${index}-fc-columns`" :class="LABEL">Columns</label>
                <select :id="`block-${index}-fc-columns`" v-model.number="block.columns" :class="SELECT">
                    <option :value="2">Two</option>
                    <option :value="3">Three</option>
                    <option :value="4">Four</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-fc-style`" :class="LABEL">Card style</label>
                <select :id="`block-${index}-fc-style`" v-model="block.card_style" :class="SELECT">
                    <option value="soft">Soft card</option>
                    <option value="outlined">Outlined</option>
                    <option value="plain">No card</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-fc-icon-style`" :class="LABEL">Icons</label>
                <select :id="`block-${index}-fc-icon-style`" v-model="block.icon_style" :class="SELECT">
                    <option value="plain">Plain</option>
                    <option value="circle">In a circle</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-fc-align`" :class="LABEL">Text</label>
                <select :id="`block-${index}-fc-align`" v-model="block.align" :class="SELECT">
                    <option value="center">Centred</option>
                    <option value="left">Left aligned</option>
                </select>
            </div>
            <label :class="[CHECK_LABEL, 'sm:col-span-2']"><input v-model="block.show_divider" type="checkbox" :class="CHECK" /> Gold divider under the heading</label>
            <label :class="[CHECK_LABEL, 'sm:col-span-2']"><input v-model="block.numbered" type="checkbox" :class="CHECK" /> Number the cards that have no icon</label>
        </div>

        <div v-for="(item, i) in block.items" :key="item.id" :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-500">Card {{ i + 1 }}</span>
                <div class="flex items-center gap-1.5">
                    <button type="button" :class="MINI_BUTTON" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">▲</button>
                    <button type="button" :class="MINI_BUTTON" :disabled="i === block.items.length - 1" aria-label="Move down" @click="move(i, 1)">▼</button>
                    <button type="button" :class="MINI_BUTTON" aria-label="Duplicate" @click="duplicateItem(i)">📋</button>
                    <button type="button" :class="MINI_DANGER" aria-label="Delete" @click="removeItem(i)">🗑️</button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <IconPicker v-model="item.icon" :label="`Card ${i + 1} icon`" />
                <input v-model="item.title" type="text" maxlength="120" placeholder="Title" :class="[SMALL_INPUT, 'font-bold sm:col-span-2']" :aria-label="`Card ${i + 1} title`" />
            </div>
            <textarea v-model="item.text" rows="2" maxlength="500" placeholder="Short text" :class="[SMALL_INPUT, 'font-medium']" :aria-label="`Card ${i + 1} text`"></textarea>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div class="sm:col-span-2"><LinkField v-model="item.link" :pages="pages" :club="club" :label="`Card ${i + 1} link`" /></div>
                <input v-model="item.link_label" type="text" maxlength="60" placeholder="Link text, e.g. Read more" :class="SMALL_INPUT" :aria-label="`Card ${i + 1} link text`" />
            </div>
        </div>

        <button type="button" :disabled="block.items.length >= MAX_ITEMS" class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-bold text-slate-600 hover:border-slate-300 disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300" @click="addItem">➕ Add card</button>
    </div>
</template>
