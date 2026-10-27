<script setup>
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { formatMoney } from '@/Utils/currency';
import { formatDate } from '@/Utils/lodgeDates';

// reconciliation_summary section of Index.vue, kept in its own file so the page stays readable. It only reads what it is given.
// A template cannot reach `window`, so the print button calls this.
const printReport = () => window.print();

defineProps({
    reconciliationSummary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reportEndDate: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reportStartDate: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div>
          <Badge variant="info">Audit and financial compliance</Badge>
          <h3 class="text-xl font-semibold text-slate-900 dark:text-white mt-2">Bank reconciliation summary</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ reconciliationSummary.accountLabel }} — As at {{ formatDate(reportEndDate) }} <span class="text-slate-400">|</span> Period: <span class="font-medium text-slate-900 dark:text-white">{{ formatDate(reportStartDate) }} – {{ formatDate(reportEndDate) }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <Button variant="secondary" @click="printReport">
            <template #icon><UiIcon name="printer" /></template>
            Print / export PDF
          </Button>
        </div>
      </div>

      <!-- Report Cards & Breakdown -->
      <div class="space-y-6 text-xs text-slate-700 dark:text-slate-200">

        <!-- Section 1: System Balance -->
        <Card padding="sm" class="space-y-2">
          <div class="flex justify-between items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
            <span>Balance in system (general ledger)</span>
            <span class="tabular-nums text-lg">{{ formatMoney(reconciliationSummary.ledgerBalance) }}</span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400">Total cleared balance across all approved transactions in your books.</p>
        </Card>

        <!-- Section 2: Outstanding Receipts -->
        <Card padding="sm" class="space-y-3">
          <div class="flex justify-between items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            <h4>Plus: outstanding receipts (unreconciled receive money)</h4>
            <span class="tabular-nums text-emerald-700 dark:text-emerald-300">+{{ formatMoney(reconciliationSummary.receiptsTotal) }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="py-2 px-3 font-medium">Date</th>
                  <th scope="col" class="py-2 px-3 font-medium">Description / payee</th>
                  <th scope="col" class="py-2 px-3 font-medium text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="tx in reconciliationSummary.outstandingReceipts" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td class="py-2 px-3 tabular-nums">{{ tx.transaction_date }}</td>
                  <td class="py-2 px-3 font-medium text-slate-900 dark:text-white">{{ tx.raw_description }}</td>
                  <td class="py-2 px-3 text-right tabular-nums font-medium text-emerald-700 dark:text-emerald-300">{{ tx.formatted_amount }}</td>
                </tr>
                <tr v-if="!reconciliationSummary.outstandingReceipts.length">
                  <td colspan="3" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No outstanding receipts.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>

        <!-- Section 3: Outstanding Payments -->
        <Card padding="sm" class="space-y-3">
          <div class="flex justify-between items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            <h4>Less: outstanding payments (unreconciled spend money)</h4>
            <span class="tabular-nums text-rose-700 dark:text-rose-300">-{{ formatMoney(reconciliationSummary.paymentsTotal) }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="py-2 px-3 font-medium">Date</th>
                  <th scope="col" class="py-2 px-3 font-medium">Description / payee</th>
                  <th scope="col" class="py-2 px-3 font-medium text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="tx in reconciliationSummary.outstandingPayments" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td class="py-2 px-3 tabular-nums">{{ tx.transaction_date }}</td>
                  <td class="py-2 px-3 font-medium text-slate-900 dark:text-white">{{ tx.raw_description }}</td>
                  <td class="py-2 px-3 text-right tabular-nums font-medium text-rose-700 dark:text-rose-300">{{ formatMoney(Math.abs(tx.amount)) }}</td>
                </tr>
                <tr v-if="!reconciliationSummary.outstandingPayments.length">
                  <td colspan="3" class="py-4 px-3 text-center text-slate-500 dark:text-slate-400">No outstanding payments.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </Card>

        <!-- Section 4: Calculated Statement Balance vs Real Bank Balance -->
        <div class="bg-blue-50 dark:bg-blue-950/40 rounded-2xl p-5 border border-blue-200 dark:border-blue-800/60 shadow-sm space-y-4">
          <div class="flex justify-between items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
            <span>Calculated bank statement balance</span>
            <span class="tabular-nums text-lg">{{ formatMoney(reconciliationSummary.calculatedStatementBalance) }}</span>
          </div>
          <div class="flex justify-between items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white border-t border-blue-200 dark:border-blue-800/60 pt-3">
            <span>Actual bank statement feed balance</span>
            <span class="tabular-nums text-lg">{{ formatMoney(reconciliationSummary.statementBalance) }}</span>
          </div>
          <div
            :class="['flex justify-between items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl border', Math.abs(reconciliationSummary.discrepancy) < 0.01 ? 'text-emerald-900 dark:text-emerald-200 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/50' : 'text-amber-900 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/40']"
          >
            <span class="flex items-center gap-2">
              <UiIcon :name="Math.abs(reconciliationSummary.discrepancy) < 0.01 ? 'check' : 'warning'" />
              <span>Unreconciled difference / discrepancy</span>
            </span>
            <span class="tabular-nums">{{ formatMoney(reconciliationSummary.discrepancy) }} {{ Math.abs(reconciliationSummary.discrepancy) < 0.01 ? '(fully reconciled)' : '' }}</span>
          </div>
        </div>

      </div>
    </div>
</template>
