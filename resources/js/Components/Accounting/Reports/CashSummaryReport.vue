<script setup>
import { formatMoney } from '@/Utils/currency';

// cash_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Cash and bank accounts summary</h4>
        <span class="text-xs font-medium text-slate-600 dark:text-slate-300 tabular-nums">Net cash reserve: {{ formatMoney(reports.cash_summary.total_cash_on_hand) }}</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div v-for="acc in reports.cash_summary.accounts" :key="acc.code" class="p-4 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between gap-3">
          <div>
            <span class="tabular-nums text-slate-500 dark:text-slate-400 block">{{ acc.code }}</span>
            <span class="font-medium text-slate-900 dark:text-white text-sm block">{{ acc.name }}</span>
          </div>
          <span class="text-base font-semibold tabular-nums text-slate-900 dark:text-white">{{ formatMoney(acc.balance) }}</span>
        </div>
      </div>
    </div>
</template>
