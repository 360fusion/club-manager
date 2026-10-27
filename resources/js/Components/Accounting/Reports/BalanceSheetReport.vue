<script setup>
import Card from '@/Components/Ui/Card.vue';
import { formatMoney } from '@/Utils/currency';

// balance_sheet section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Balance sheet</h4>
        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Assets = liabilities + equity</span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
        <Card padding="sm" class="space-y-3">
          <h5 class="text-sm font-semibold text-slate-900 dark:text-white">Assets</h5>
          <div class="space-y-1.5">
            <div v-for="a in reports.balance_sheet.assets" :key="a.code" class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-1">
              <span class="text-slate-700 dark:text-slate-200">{{ a.code }} - {{ a.name }}</span>
              <span class="tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(a.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between gap-2 font-semibold text-slate-900 dark:text-white text-sm border-t border-slate-200 dark:border-slate-700">
            <span>Total assets</span>
            <span class="tabular-nums">{{ formatMoney(reports.balance_sheet.total_assets) }}</span>
          </div>
        </Card>

        <Card padding="sm" class="space-y-3">
          <h5 class="text-sm font-semibold text-slate-900 dark:text-white">Liabilities</h5>
          <div class="space-y-1.5">
            <div v-for="l in reports.balance_sheet.liabilities" :key="l.code" class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-1">
              <span class="text-slate-700 dark:text-slate-200">{{ l.code }} - {{ l.name }}</span>
              <span class="tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(l.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between gap-2 font-semibold text-slate-900 dark:text-white text-sm border-t border-slate-200 dark:border-slate-700">
            <span>Total liabilities</span>
            <span class="tabular-nums">{{ formatMoney(reports.balance_sheet.total_liabilities) }}</span>
          </div>
        </Card>

        <Card padding="sm" class="space-y-3">
          <h5 class="text-sm font-semibold text-slate-900 dark:text-white">Club equity</h5>
          <div class="space-y-1.5">
            <div v-for="e in reports.balance_sheet.equity" :key="e.code" class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-1">
              <span class="text-slate-700 dark:text-slate-200">{{ e.code }} - {{ e.name }}</span>
              <span class="tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(e.balance) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between gap-2 font-semibold text-slate-900 dark:text-white text-sm border-t border-slate-200 dark:border-slate-700">
            <span>Total equity</span>
            <span class="tabular-nums">{{ formatMoney(reports.balance_sheet.total_equity) }}</span>
          </div>
        </Card>
      </div>
    </div>
</template>
