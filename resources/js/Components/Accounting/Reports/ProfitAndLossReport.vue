<script setup>
import Card from '@/Components/Ui/Card.vue';
import { formatMoney } from '@/Utils/currency';

// profit_and_loss section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="flex items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        <h4 class="text-base font-semibold text-slate-900 dark:text-white">Profit and loss (income statement)</h4>
        <span class="text-xs font-semibold tabular-nums" :class="reports.profit_and_loss.net_income >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
          Net income: {{ formatMoney(reports.profit_and_loss.net_income) }}
        </span>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <!-- Revenue Section -->
        <Card padding="sm" class="space-y-3">
          <h5 class="text-sm font-semibold text-slate-900 dark:text-white">Revenues and operating income</h5>
          <div class="space-y-1.5">
            <div v-for="r in reports.profit_and_loss.revenues" :key="r.code" class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-1">
              <span class="text-slate-700 dark:text-slate-200">{{ r.code }} - {{ r.name }}</span>
              <span class="tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(r.amount) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between gap-2 font-semibold text-slate-900 dark:text-white text-sm border-t border-slate-200 dark:border-slate-700">
            <span>Total revenue</span>
            <span class="tabular-nums">{{ formatMoney(reports.profit_and_loss.total_revenue) }}</span>
          </div>
        </Card>

        <!-- Expense Section -->
        <Card padding="sm" class="space-y-3">
          <h5 class="text-sm font-semibold text-slate-900 dark:text-white">Operating expenses</h5>
          <div class="space-y-1.5">
            <div v-for="ex in reports.profit_and_loss.expenses" :key="ex.code" class="flex justify-between gap-2 border-b border-slate-100 dark:border-slate-800 pb-1">
              <span class="text-slate-700 dark:text-slate-200">{{ ex.code }} - {{ ex.name }}</span>
              <span class="tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(ex.amount) }}</span>
            </div>
          </div>
          <div class="pt-2 flex justify-between gap-2 font-semibold text-slate-900 dark:text-white text-sm border-t border-slate-200 dark:border-slate-700">
            <span>Total expenses</span>
            <span class="tabular-nums">{{ formatMoney(reports.profit_and_loss.total_expenses) }}</span>
          </div>
        </Card>
      </div>
    </div>
</template>
