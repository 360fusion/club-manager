<script setup>
// Edit form for the FAQ block: questions with rich-text answers. Mutates `block` in place.
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { LABEL, INPUT, SELECT, CARD, CHECK, CHECK_LABEL, MINI_BUTTON, MINI_DANGER } from './styles';

const props = defineProps({
    block: { type: Object, required: true },
    index: { type: Number, required: true },
});

const MAX_ITEMS = 60;

if (!Array.isArray(props.block.items)) props.block.items = [];

const newId = () => `faq-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
const addItem = () => {
    if (props.block.items.length < MAX_ITEMS) props.block.items.push({ id: newId(), question: '', answer: '' });
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
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label :for="`block-${index}-faq-heading`" :class="LABEL">Heading <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-faq-heading`" v-model="block.heading" type="text" maxlength="200" placeholder="e.g. Frequently asked questions" :class="[INPUT, 'font-bold']" />
            </div>
            <div>
                <label :for="`block-${index}-faq-intro`" :class="LABEL">Intro <span class="font-normal text-slate-400">(optional)</span></label>
                <input :id="`block-${index}-faq-intro`" v-model="block.intro" type="text" maxlength="500" :class="INPUT" />
            </div>
        </div>

        <div :class="[CARD, 'grid grid-cols-2 sm:grid-cols-4 gap-3 items-end']">
            <div>
                <label :for="`block-${index}-faq-behaviour`" :class="LABEL">Behaviour</label>
                <select :id="`block-${index}-faq-behaviour`" v-model="block.behaviour" :class="SELECT">
                    <option value="single">One open at a time</option>
                    <option value="multiple">Several can be open</option>
                    <option value="expanded">Always expanded</option>
                </select>
            </div>
            <div>
                <label :for="`block-${index}-faq-columns`" :class="LABEL">Columns</label>
                <select :id="`block-${index}-faq-columns`" v-model.number="block.columns" :class="SELECT">
                    <option :value="1">One</option>
                    <option :value="2">Two</option>
                </select>
            </div>
            <label :class="[CHECK_LABEL, 'pb-2']"><input v-model="block.first_open" type="checkbox" :class="CHECK" /> First one open</label>
            <label :class="[CHECK_LABEL, 'pb-2']"><input v-model="block.numbered" type="checkbox" :class="CHECK" /> Number them</label>
            <label :class="[CHECK_LABEL, 'sm:col-span-4']"><input v-model="block.show_search" type="checkbox" :class="CHECK" /> Show a search box when there are more than 8 questions</label>
        </div>

        <div v-for="(item, i) in block.items" :key="item.id" :class="[CARD, 'space-y-2']">
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-slate-500">Question {{ i + 1 }}</span>
                <div class="flex items-center gap-1.5">
                    <button type="button" :class="MINI_BUTTON" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)">▲</button>
                    <button type="button" :class="MINI_BUTTON" :disabled="i === block.items.length - 1" aria-label="Move down" @click="move(i, 1)">▼</button>
                    <button type="button" :class="MINI_BUTTON" aria-label="Duplicate" @click="duplicateItem(i)">📋</button>
                    <button type="button" :class="MINI_DANGER" aria-label="Delete" @click="removeItem(i)">🗑️</button>
                </div>
            </div>
            <input v-model="item.question" type="text" maxlength="300" placeholder="The question" :class="[INPUT, 'font-bold']" :aria-label="`Question ${i + 1}`" />
            <RichTextEditor v-model="item.answer" placeholder="The answer (you can add links and lists)…" />
        </div>

        <button type="button" :disabled="block.items.length >= MAX_ITEMS" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-slate-300 text-slate-600 dark:text-slate-300 font-bold text-[11px] cursor-pointer disabled:opacity-40" @click="addItem">➕ Add question</button>
    </div>
</template>
