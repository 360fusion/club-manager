<script setup>
import Button from '@/Components/Ui/Button.vue';
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
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Budget vs actual — {{ budgetVsActual.financial_year }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Enter a budget per account, then compare against real ledger activity for the year.</p>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="secondary" size="sm" @click="changeBudgetYear(budgetVsActual.financial_year - 1)">← {{ budgetVsActual.financial_year - 1 }}</Button>
          <Button variant="secondary" size="sm" @click="changeBudgetYear(budgetVsActual.financial_year + 1)">{{ budgetVsActual.financial_year + 1 }} →</Button>
        </div>
      </div>

      <form @submit.prevent="submitBudget" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-3 px-3 font-medium">Account</th>
              <th scope="col" class="py-3 px-3 font-medium">Type</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Budgeted</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Actual</th>
              <th scope="col" class="py-3 px-3 font-medium text-right">Variance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900">
            <tr v-for="acc in budgetableAccounts" :key="acc.id">
              <td class="py-2 px-3 font-medium text-slate-900 dark:text-white">{{ acc.code }} — {{ acc.name }}</td>
              <td class="py-2 px-3 capitalize">{{ acc.type }}</td>
              <td class="py-2 px-3 text-right">
                <input v-model="budgetForm.lines[acc.id]" :disabled="!canManageBilling" type="number" step="0.01" min="0" :aria-label="`Budget for ${acc.name}`" class="w-28 rounded-xl border border-slate-300 bg-white px-2 py-1 text-right text-xs tabular-nums text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
              </td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).actual || 0) }}</td>
              <td
                class="py-2 px-3 text-right tabular-nums"
                :class="((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).variance || 0) > 0 ? (acc.type === 'expense' ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400') : 'text-slate-500 dark:text-slate-400'"
              >
                {{ formatMoney((budgetVsActual.rows.find(r => r.account_id === acc.id) || {}).variance || 0) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-slate-100 dark:bg-slate-800 font-semibold text-slate-900 dark:text-white">
              <td colspan="2" class="py-2 px-3">Totals</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(budgetVsActual.total_budgeted) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(budgetVsActual.total_actual) }}</td>
              <td class="py-2 px-3 text-right tabular-nums">{{ formatMoney(budgetVsActual.total_actual - budgetVsActual.total_budgeted) }}</td>
            </tr>
          </tfoot>
        </table>
        <div v-if="canManageBilling" class="p-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800">
          <Button type="submit" :disabled="budgetForm.processing">
            Save budget
          </Button>
        </div>
      </form>
    </div>
</template>
