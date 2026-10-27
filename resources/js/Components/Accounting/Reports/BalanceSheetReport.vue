<script setup>
import { formatMoney } from '@/Utils/currency';

// balance_sheet section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Balance Sheet Statement</h4>
        <span class="text-xs font-extrabold text-blue-700 dark:text-blue-300 font-mono">Assets = Liabilities + Equity</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
        <div class="bg-emerald-50/50 dark:bg-emerald-950/50 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-900/40 space-y-3">
          <span class="font-extrabold text-emerald-900 dark:text-emerald-200 text-sm block">Assets</span>
          <div class="space-y-1.5">
            <div v-for="a in reports.balance_sheet.assets" :key="a.code" class="flex justify-between border-b border-emerald-100 dark:border-emerald-900/40 pb-1">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ a.code }} - {{ a.name }}</span>
              <span class="font-mono font-bold">{{ formatMoney(a.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between font-black text-emerald-900 dark:text-emerald-200 text-sm border-t border-emerald-200 dark:border-emerald-800/60">
            <span>Total Assets</span>
            <span>{{ formatMoney(reports.balance_sheet.total_assets) }}</span>
          </div>
        </div>

        <div class="bg-amber-50/50 dark:bg-amber-950/50 p-4 rounded-2xl border border-amber-100 dark:border-amber-900/40 space-y-3">
          <span class="font-extrabold text-amber-900 dark:text-amber-200 text-sm block">Liabilities</span>
          <div class="space-y-1.5">
            <div v-for="l in reports.balance_sheet.liabilities" :key="l.code" class="flex justify-between border-b border-amber-100 dark:border-amber-900/40 pb-1">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ l.code }} - {{ l.name }}</span>
              <span class="font-mono font-bold">{{ formatMoney(l.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between font-black text-amber-900 dark:text-amber-200 text-sm border-t border-amber-200 dark:border-amber-800/60">
            <span>Total Liabilities</span>
            <span>{{ formatMoney(reports.balance_sheet.total_liabilities) }}</span>
          </div>
        </div>

        <div class="bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border border-blue-100 dark:border-blue-900/40 space-y-3">
          <span class="font-extrabold text-blue-900 dark:text-blue-200 text-sm block">Club Equity</span>
          <div class="space-y-1.5">
            <div v-for="e in reports.balance_sheet.equity" :key="e.code" class="flex justify-between border-b border-blue-100 dark:border-blue-900/40 pb-1">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ e.code }} - {{ e.name }}</span>
              <span class="font-mono font-bold">{{ formatMoney(e.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between font-black text-blue-900 dark:text-blue-200 text-sm border-t border-blue-200 dark:border-blue-800/60">
            <span>Total Equity</span>
            <span>{{ formatMoney(reports.balance_sheet.total_equity) }}</span>
          </div>
        </div>
      </div>
    </div>
</template>
