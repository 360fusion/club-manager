<script setup>
import { formatMoney } from '@/Utils/currency';

// comparative_income_expenditure section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-3">
        <div>
          <h4 class="text-base font-semibold text-slate-900 dark:text-white">Comparative annual income and expenditure statement</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Lodge and club accounts comparing {{ reports.comparative_income_expenditure?.prior_year_label }} and {{ reports.comparative_income_expenditure?.current_year_label }}.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left border-collapse text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-4 font-medium">Statement category</th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Income<br><span class="text-[11px] font-normal text-slate-400">{{ reports.comparative_income_expenditure?.prior_year_label }}</span></th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Expenditure<br><span class="text-[11px] font-normal text-slate-400">{{ reports.comparative_income_expenditure?.prior_year_label }}</span></th>
              <th scope="col" class="py-3 px-4 font-medium text-right border-l border-slate-200 dark:border-slate-800">Income<br><span class="text-[11px] font-normal text-slate-400">{{ reports.comparative_income_expenditure?.current_year_label }}</span></th>
              <th scope="col" class="py-3 px-4 font-medium text-right">Expenditure<br><span class="text-[11px] font-normal text-slate-400">{{ reports.comparative_income_expenditure?.current_year_label }}</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="(row, idx) in (reports.comparative_income_expenditure?.rows || [])" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="py-3 px-4 font-medium text-slate-900 dark:text-white">{{ row.category }}</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ row.prior_income > 0 ? formatMoney(row.prior_income) : '—' }}</td>
              <td class="py-3 px-4 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ row.prior_expenditure > 0 ? formatMoney(row.prior_expenditure) : '—' }}</td>
              <td class="py-3 px-4 text-right tabular-nums font-medium text-slate-900 dark:text-white border-l border-slate-100 dark:border-slate-800">{{ row.current_income > 0 ? formatMoney(row.current_income) : '—' }}</td>
              <td class="py-3 px-4 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ row.current_expenditure > 0 ? formatMoney(row.current_expenditure) : '—' }}</td>
            </tr>

            <!-- TOTALS -->
            <tr class="bg-slate-100 dark:bg-slate-800 font-semibold text-slate-900 dark:text-white border-t-2 border-slate-300 dark:border-slate-700">
              <td class="py-3 px-4">Totals</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.prior_totals?.income) }}</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.prior_totals?.expenditure) }}</td>
              <td class="py-3 px-4 text-right tabular-nums border-l border-slate-200 dark:border-slate-700">{{ formatMoney(reports.comparative_income_expenditure?.current_totals?.income) }}</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.current_totals?.expenditure) }}</td>
            </tr>

            <!-- BALANCE CARRIED FORWARD (CASH AT BANK) -->
            <tr class="bg-slate-50 dark:bg-slate-800/50 font-medium text-slate-900 dark:text-white">
              <td class="py-3 px-4 italic">Balance carried forward – i.e. cash at bank 31/03</td>
              <td class="py-3 px-4 text-right tabular-nums text-slate-400">—</td>
              <td class="py-3 px-4 text-right tabular-nums font-semibold">{{ formatMoney(reports.comparative_income_expenditure?.prior_balance_carried_forward) }}</td>
              <td class="py-3 px-4 text-right tabular-nums text-slate-400 border-l border-slate-200 dark:border-slate-700">—</td>
              <td class="py-3 px-4 text-right tabular-nums font-semibold">{{ formatMoney(reports.comparative_income_expenditure?.current_balance_carried_forward) }}</td>
            </tr>

            <!-- CASH AT BANK - PLUS EXPENDITURE (RECONCILED TOTALS) -->
            <tr class="bg-slate-900 dark:bg-slate-700 text-white font-semibold text-xs">
              <td class="py-3 px-4">Cash at bank – plus expenditure</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.prior_reconciled) }}</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.prior_reconciled) }}</td>
              <td class="py-3 px-4 text-right tabular-nums border-l border-slate-700 dark:border-slate-600">{{ formatMoney(reports.comparative_income_expenditure?.current_reconciled) }}</td>
              <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(reports.comparative_income_expenditure?.current_reconciled) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
