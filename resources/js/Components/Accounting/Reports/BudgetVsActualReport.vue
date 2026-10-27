<script setup>
import { formatMoney } from '@/Utils/currency';

// budget_vs_actual section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
defineProps({
    budgetForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    budgetableAccounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    changeBudgetYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitBudget: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    budgetVsActual: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <h3 class="text-xl font-black text-slate-900 dark:text-white">Budget vs Actual — {{ budgetVsActual.financial_year }}</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">Enter a budget per account, then compare against real ledger activity for the year.</p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="changeBudgetYear(budgetVsActual.financial_year - 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">← {{ budgetVsActual.financial_year - 1 }}</button>
          <button type="button" @click="changeBudgetYear(budgetVsActual.financial_year + 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">{{ budgetVsActual.financial_year + 1 }} →</button>
        </div>
      </div>

      <form @submit.prevent="submitBudget" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
            <tr>
              <th class="py-2 px-3">Account</th>
              <th class="py-2 px-3">Type</th>
              <th class="py-2 px-3 text-right">Budgeted</th>
              <th class="py-2 px-3 text-right">Actual</th>
              <th class="py-2 px-3 text-right">Variance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100 bg-white dark:bg-slate-900">
            <tr v-for="acc in budgetableAccounts" :key="acc.id">
              <td class="py-2 px-3 font-bold text-slate-900 dark:text-white">{{ acc.code }} — {{ acc.name }}</td>
              <td class="py-2 px-3 capitalize">{{ acc.type }}</td>
              <td class="py-2 px-3 text-right">
                <input v-model="budgetForm.lines[acc.id]" :disabled="!canManageBilling" type="number" step="0.01" min="0" class="w-28 px-2 py-1 text-right bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg disabled:opacity-60" />
              </td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).actual || 0) }}</td>
              <td
                class="py-2 px-3 text-right font-mono"
                :class="((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).variance || 0) > 0 ? (acc.type === 'expense' ? 'text-rose-600' : 'text-emerald-600') : 'text-slate-500'"
              >
                {{ formatMoney((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).variance || 0) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-100 dark:bg-slate-800 font-extrabold text-slate-900 dark:text-white">
              <td colspan="2" class="py-2 px-3 uppercase">Totals</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(budgetVsActual.total_budgeted) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(budgetVsActual.total_actual) }}</td>
              <td class="py-2 px-3 text-right font-mono">{{ formatMoney(budgetVsActual.total_actual - budgetVsActual.total_budgeted) }}</td>
            </tr>
          </tfoot>
        </table>
        <div v-if="canManageBilling" class="p-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800">
          <button type="submit" :disabled="budgetForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs rounded-xl cursor-pointer disabled:opacity-50">
            Save Budget
          </button>
        </div>
      </form>
    </div>
</template>
