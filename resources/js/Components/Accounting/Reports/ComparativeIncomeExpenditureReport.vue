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
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
        <div>
          <h4 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
            <span>🏛️ Comparative Annual Income & Expenditure Statement</span>
          </h4>
          <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
            Lodge & Club accounts comparing {{ reports.comparative_income_expenditure?.prior_year_label }} and {{ reports.comparative_income_expenditure?.current_year_label }}.
          </p>
        </div>
      </div>

      <div class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-900 dark:bg-slate-700 text-white font-extrabold uppercase text-[11px] tracking-wider border-b border-slate-800">
              <th class="py-3 px-4 font-black">STATEMENT CATEGORY</th>
              <th class="py-3 px-4 text-right bg-slate-800 text-amber-300">INCOME<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.prior_year_label }}</span></th>
              <th class="py-3 px-4 text-right bg-slate-800 text-rose-300">EXPENDITURE<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.prior_year_label }}</span></th>
              <th class="py-3 px-4 text-right bg-slate-900 dark:bg-slate-700 text-amber-400 border-l border-slate-800">INCOME<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.current_year_label }}</span></th>
              <th class="py-3 px-4 text-right bg-slate-900 dark:bg-slate-700 text-rose-400">EXPENDITURE<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.current_year_label }}</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="(row, idx) in (reports.comparative_income_expenditure?.rows || [])" :key="idx" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
              <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ row.category }}</td>
              <td class="py-3 px-4 text-right font-mono">{{ row.prior_income > 0 ? formatMoney(row.prior_income) : '—' }}</td>
              <td class="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-300">{{ row.prior_expenditure > 0 ? formatMoney(row.prior_expenditure) : '—' }}</td>
              <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300 border-l border-slate-100 dark:border-slate-800">{{ row.current_income > 0 ? formatMoney(row.current_income) : '—' }}</td>
              <td class="py-3 px-4 text-right font-mono text-slate-600 dark:text-slate-300">{{ row.current_expenditure > 0 ? formatMoney(row.current_expenditure) : '—' }}</td>
            </tr>

            <!-- TOTALS -->
            <tr class="bg-slate-100 dark:bg-slate-800 font-extrabold text-slate-900 dark:text-white border-t-2 border-slate-300 dark:border-slate-700">
              <td class="py-3 px-4 uppercase font-black">TOTALS</td>
              <td class="py-3 px-4 text-right font-mono text-emerald-800 dark:text-emerald-200">{{ formatMoney(reports.comparative_income_expenditure?.prior_totals?.income) }}</td>
              <td class="py-3 px-4 text-right font-mono text-rose-800 dark:text-rose-200">{{ formatMoney(reports.comparative_income_expenditure?.prior_totals?.expenditure) }}</td>
              <td class="py-3 px-4 text-right font-mono text-emerald-800 dark:text-emerald-200 border-l border-slate-200 dark:border-slate-800">{{ formatMoney(reports.comparative_income_expenditure?.current_totals?.income) }}</td>
              <td class="py-3 px-4 text-right font-mono text-rose-800 dark:text-rose-200">{{ formatMoney(reports.comparative_income_expenditure?.current_totals?.expenditure) }}</td>
            </tr>

            <!-- BALANCE CARRIED FORWARD (CASH AT BANK) -->
            <tr class="bg-amber-50/70 dark:bg-amber-950/70 font-extrabold text-amber-900 dark:text-amber-200">
              <td class="py-3 px-4 italic font-bold">BALANCE CARRIED FORWARD – i.e. cash at Bank 31/03</td>
              <td class="py-3 px-4 text-right font-mono text-slate-400">—</td>
              <td class="py-3 px-4 text-right font-mono font-black text-amber-900 dark:text-amber-200">{{ formatMoney(reports.comparative_income_expenditure?.prior_balance_carried_forward) }}</td>
              <td class="py-3 px-4 text-right font-mono text-slate-400 border-l border-amber-200 dark:border-amber-800/60">—</td>
              <td class="py-3 px-4 text-right font-mono font-black text-amber-900 dark:text-amber-200">{{ formatMoney(reports.comparative_income_expenditure?.current_balance_carried_forward) }}</td>
            </tr>

            <!-- CASH AT BANK - PLUS EXPENDITURE (RECONCILED TOTALS) -->
            <tr class="bg-slate-900 dark:bg-slate-700 text-white font-black text-xs">
              <td class="py-3 px-4 uppercase font-black tracking-wider">CASH AT BANK – PLUS EXPENDITURE</td>
              <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatMoney(reports.comparative_income_expenditure?.prior_reconciled) }}</td>
              <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatMoney(reports.comparative_income_expenditure?.prior_reconciled) }}</td>
              <td class="py-3 px-4 text-right font-mono text-amber-400 border-l border-slate-800">{{ formatMoney(reports.comparative_income_expenditure?.current_reconciled) }}</td>
              <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatMoney(reports.comparative_income_expenditure?.current_reconciled) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
