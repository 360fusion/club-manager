<script setup>
import { formatMoney } from '@/Utils/currency';

// executive_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Executive Summary & Financial Ratios</h4>
        <span class="text-xs font-semibold text-blue-700 dark:text-blue-300 font-mono">Executive Financial Health</span>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase block">Net Profit Margin</span>
          <span class="text-xl font-black text-emerald-700 dark:text-emerald-300 block">{{ reports.executive_summary.net_profit_margin_pct }}%</span>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase block">Operating Expense Ratio</span>
          <span class="text-xl font-black text-blue-700 dark:text-blue-300 block">{{ reports.executive_summary.operating_expense_ratio_pct }}%</span>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase block">Total Receivables (A/R)</span>
          <span class="text-xl font-black text-amber-700 dark:text-amber-300 block">{{ formatMoney(reports.executive_summary.outstanding_ar) }}</span>
        </div>
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase block">Total Payables (A/P)</span>
          <span class="text-xl font-black text-rose-700 dark:text-rose-300 block">{{ formatMoney(reports.executive_summary.outstanding_ap) }}</span>
        </div>
      </div>
    </div>
</template>
