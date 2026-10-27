<script setup>
import StatCard from '@/Components/Ui/StatCard.vue';
import { formatMoney } from '@/Utils/currency';

// executive_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between gap-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Executive summary and financial ratios</h4>
        <span class="text-xs text-slate-500 dark:text-slate-400">Executive financial health</span>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard label="Net profit margin" :value="`${reports.executive_summary.net_profit_margin_pct}%`" />
        <StatCard label="Operating expense ratio" :value="`${reports.executive_summary.operating_expense_ratio_pct}%`" />
        <StatCard label="Total receivables (A/R)" :value="formatMoney(reports.executive_summary.outstanding_ar)" />
        <StatCard label="Total payables (A/P)" :value="formatMoney(reports.executive_summary.outstanding_ap)" />
      </div>
    </div>
</template>
