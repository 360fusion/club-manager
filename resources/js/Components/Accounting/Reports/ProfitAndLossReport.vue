<script setup>
import { formatMoney } from '@/Utils/currency';

// profit_and_loss section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2">
        <h4 class="text-base font-extrabold text-slate-900 dark:text-white">Profit and Loss (Income Statement)</h4>
        <span class="text-xs font-black font-mono" :class="reports.profit_and_loss.net_income >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
          Net Income: {{ formatMoney(reports.profit_and_loss.net_income) }}
        </span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <!-- Revenue Section -->
        <div class="bg-blue-50/50 dark:bg-blue-950/50 p-4 rounded-2xl border border-blue-100 dark:border-blue-900/40 space-y-3">
          <span class="font-extrabold text-blue-900 dark:text-blue-200 text-sm block">Revenues & Operating Income</span>
          <div class="space-y-1.5">
            <div v-for="r in reports.profit_and_loss.revenues" :key="r.code" class="flex justify-between border-b border-blue-100 dark:border-blue-900/40 pb-1">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ r.code }} - {{ r.name }}</span>
              <span class="font-mono font-bold">{{ formatMoney(r.amount) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between font-black text-blue-900 dark:text-blue-200 text-sm border-t border-blue-200 dark:border-blue-800/60">
            <span>Total Revenue</span>
            <span>{{ formatMoney(reports.profit_and_loss.total_revenue) }}</span>
          </div>
        </div>

        <!-- Expense Section -->
        <div class="bg-rose-50/50 dark:bg-rose-950/50 p-4 rounded-2xl border border-rose-100 dark:border-rose-900/40 space-y-3">
          <span class="font-extrabold text-rose-900 dark:text-rose-200 text-sm block">Operating Expenses</span>
          <div class="space-y-1.5">
            <div v-for="ex in reports.profit_and_loss.expenses" :key="ex.code" class="flex justify-between border-b border-rose-100 dark:border-rose-900/40 pb-1">
              <span class="font-semibold text-slate-700 dark:text-slate-200">{{ ex.code }} - {{ ex.name }}</span>
              <span class="font-mono font-bold">{{ formatMoney(ex.amount) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between font-black text-rose-900 dark:text-rose-200 text-sm border-t border-rose-200 dark:border-rose-800/60">
            <span>Total Expenses</span>
            <span>{{ formatMoney(reports.profit_and_loss.total_expenses) }}</span>
          </div>
        </div>
      </div>
    </div>
</template>
