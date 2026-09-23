<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  label: { type: String, required: true },
  help: { type: String, default: '' },
  /** Abbreviation => number of members who hold it. */
  usage: { type: Object, default: () => ({}) },
  /** Validation messages for this list, keyed like "grand_ranks.0.abbreviation". */
  errors: { type: Object, default: () => ({}) },
  idPrefix: { type: String, default: 'ranks' },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => props.modelValue || []);

const set = (next) => emit('update:modelValue', next);

const update = (index, field, value) => {
  set(rows.value.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
};

const add = () => set([...rows.value, { abbreviation: '', title: '' }]);

const remove = (index) => set(rows.value.filter((_, i) => i !== index));

const move = (index, by) => {
  const target = index + by;
  if (target < 0 || target >= rows.value.length) return;
  const next = [...rows.value];
  [next[index], next[target]] = [next[target], next[index]];
  set(next);
};

const usedBy = (row) => props.usage[(row.abbreviation || '').trim()] || 0;

const errorFor = (index, field) => props.errors[`${props.idPrefix}.${index}.${field}`];
</script>

<template>
  <div class="space-y-3">
    <div>
      <h3 class="text-sm font-bold text-slate-900 dark:text-white">{{ label }}</h3>
      <p v-if="help" class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ help }}</p>
    </div>

    <p v-if="!rows.length" class="text-xs text-slate-400 dark:text-slate-500 py-2">No ranks yet. Add the first one below.</p>

    <ul class="space-y-2">
      <li v-for="(row, index) in rows" :key="index" class="flex flex-wrap items-start gap-2">
        <div class="w-32 shrink-0">
          <input
            :value="row.abbreviation"
            @input="update(index, 'abbreviation', $event.target.value)"
            type="text"
            maxlength="30"
            placeholder="PAGDC"
            :aria-label="`${label}: abbreviation ${index + 1}`"
            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p v-if="errorFor(index, 'abbreviation')" class="text-[11px] font-bold text-rose-500 mt-0.5">{{ errorFor(index, 'abbreviation') }}</p>
        </div>
        <div class="flex-1 min-w-[12rem]">
          <input
            :value="row.title"
            @input="update(index, 'title', $event.target.value)"
            type="text"
            maxlength="120"
            placeholder="Full title (shown beside it in the list)"
            :aria-label="`${label}: title ${index + 1}`"
            class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500"
          />
          <p v-if="errorFor(index, 'title')" class="text-[11px] font-bold text-rose-500 mt-0.5">{{ errorFor(index, 'title') }}</p>
        </div>
        <span v-if="usedBy(row)" class="self-center text-[11px] font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap" :title="`${usedBy(row)} members hold this rank; removing it from the list does not change them`">
          {{ usedBy(row) }} {{ usedBy(row) === 1 ? 'member' : 'members' }}
        </span>
        <div class="flex items-center gap-1 self-center">
          <button type="button" @click="move(index, -1)" :disabled="index === 0" aria-label="Move up" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white disabled:opacity-30 cursor-pointer">↑</button>
          <button type="button" @click="move(index, 1)" :disabled="index === rows.length - 1" aria-label="Move down" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500 hover:text-slate-900 dark:hover:text-white disabled:opacity-30 cursor-pointer">↓</button>
          <button type="button" @click="remove(index)" aria-label="Remove" class="w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-800 text-slate-500 hover:text-rose-600 dark:hover:text-rose-400 cursor-pointer">✕</button>
        </div>
      </li>
    </ul>

    <button type="button" @click="add" class="px-3.5 py-2 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer">
      + Add rank
    </button>
  </div>
</template>
