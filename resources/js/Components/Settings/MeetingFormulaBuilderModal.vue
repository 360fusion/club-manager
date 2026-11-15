<script setup>
// Same rule picker as "Generate Season Meeting Rules" on the meetings page, but instead of
// creating meetings it writes the rule back into the Meeting Formula Rule field, e.g.
// [4th] [Thursday] with Jan–May + Sep–Nov ticked  ->  "4th Thu. 1 To 11 Ex. 6, 7, 8".
// `form` is the page's form; `open` is the page's (v-model:open).
import { computed, ref, watch } from 'vue';
import Button from '@/Components/Ui/Button.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    form: { type: Object, required: true },
});

const open = defineModel('open', { type: Boolean, default: false });

const DAY_NAMES = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const formulaBuilder = ref({ occurrence: '4th', day_of_week: 'Thursday', months: [1, 2, 3, 4, 5, 9, 10, 11] });

// Read an existing rule back into the picker so opening it doesn't discard what's already set.
const parseFormula = (formula) => {
  const parsed = { occurrence: '4th', day_of_week: 'Thursday', months: [1, 2, 3, 4, 5, 9, 10, 11] };
  if (!formula) return parsed;

  const occurrence = formula.match(/^\s*(1st|2nd|3rd|4th|last)/i);
  if (occurrence) parsed.occurrence = occurrence[1].toLowerCase() === 'last' ? 'last' : occurrence[1];

  const day = DAY_NAMES.find(d => new RegExp(`\\b${d.slice(0, 3)}`, 'i').test(formula));
  if (day) parsed.day_of_week = day;

  const range = formula.match(/(\d{1,2})\s*to\s*(\d{1,2})/i);
  if (range) {
    const from = Number(range[1]);
    const to = Number(range[2]);
    const except = (formula.split(/ex\.?/i)[1] || '').match(/\d{1,2}/g)?.map(Number) ?? [];
    parsed.months = [];
    for (let m = from; m <= to; m++) {
      if (!except.includes(m)) parsed.months.push(m);
    }
  }

  return parsed;
};

// Read the field back in each time the picker opens.
watch(open, (isOpen) => {
  if (isOpen) {
    formulaBuilder.value = parseFormula(props.form.meeting_formula);
  }
});

// Months are expressed as a span with the gaps listed as exceptions, matching how lodges write it.
const builtFormula = computed(() => {
  const months = [...formulaBuilder.value.months].sort((a, b) => a - b);
  const dayShort = formulaBuilder.value.day_of_week.slice(0, 3) + '.';
  const occurrence = formulaBuilder.value.occurrence === 'last' ? 'Last' : formulaBuilder.value.occurrence;

  if (!months.length) return `${occurrence} ${dayShort}`;

  const from = months[0];
  const to = months[months.length - 1];
  const except = [];
  for (let m = from; m <= to; m++) {
    if (!months.includes(m)) except.push(m);
  }

  const span = from === to ? `${from}` : `${from} To ${to}`;

  return except.length ? `${occurrence} ${dayShort} ${span} Ex. ${except.join(', ')}` : `${occurrence} ${dayShort} ${span}`;
});

const applyFormulaBuilder = () => {
  props.form.meeting_formula = builtFormula.value;
  open.value = false;
};
</script>

<template>
    <Modal
      :open="open"
      title="Build the meeting formula rule"
      subtitle="Pick the occurrence, weekday and the months the lodge meets."
      size="lg"
      @close="open = false"
    >
      <div class="space-y-5">
        <div class="grid grid-cols-2 gap-4">
          <Select
            v-model="formulaBuilder.occurrence"
            label="Occurrence"
            :options="[
              { value: '1st', label: '1st' },
              { value: '2nd', label: '2nd' },
              { value: '3rd', label: '3rd' },
              { value: '4th', label: '4th' },
              { value: 'last', label: 'Last' },
            ]"
          />

          <Select
            v-model="formulaBuilder.day_of_week"
            label="Day of the week"
            :options="DAY_NAMES.map((d) => ({ value: d, label: d }))"
          />
        </div>

        <fieldset>
          <legend class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-2">Meeting months (untick the recess)</legend>
          <div class="grid grid-cols-4 gap-2 text-sm">
            <label v-for="(mName, idx) in MONTH_NAMES" :key="idx" class="flex items-center gap-1.5 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 cursor-pointer text-slate-700 dark:text-slate-200">
              <input type="checkbox" :value="idx + 1" v-model="formulaBuilder.months" class="h-4 w-4 rounded accent-blue-600" />
              <span>{{ mName }}</span>
            </label>
          </div>
        </fieldset>

        <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
          <span class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Resulting rule</span>
          <span class="font-mono font-medium text-sm text-slate-900 dark:text-white">{{ builtFormula }}</span>
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" @click="open = false">Cancel</Button>
        <Button @click="applyFormulaBuilder">Use this rule</Button>
      </template>
    </Modal>
</template>
