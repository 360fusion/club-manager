<script setup>
import { formatMoney } from '@/Utils/currency';

// cash_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Cash & Bank Accounts Summary</h4>
        <span class="text-xs font-black text-blue-800 dark:text-blue-200 font-mono">Net Cash Reserve: {{ formatMoney(reports.cash_summary.total_cash_on_hand) }}</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div v-for="acc in reports.cash_summary.accounts" :key="acc.code" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
          <div>
            <span class="font-mono font-bold text-blue-700 dark:text-blue-300 block">{{ acc.code }}</span>
            <span class="font-bold text-slate-900 dark:text-white text-sm block">{{ acc.name }}</span>
          </div>
          <span class="text-base font-black font-mono text-emerald-700 dark:text-emerald-300">{{ formatMoney(acc.balance) }}</span>
        </div>
      </div>
    </div>
</template>
