<script setup>
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
          <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-900/40 px-2.5 py-1 rounded-full border border-blue-200 dark:border-blue-800/60">
            Audit &amp; Financial Compliance
          </span>
          <h3 class="text-xl font-black text-slate-900 dark:text-white mt-2">Bank Reconciliation Summary</h3>
          <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">
            {{ reconciliationSummary.accountLabel }} — As at {{ formatDate(reportEndDate) }} <span class="text-slate-400">|</span> Period: <span class="text-blue-900 dark:text-blue-200 font-extrabold">{{ formatDate(reportStartDate) }} – {{ formatDate(reportEndDate) }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" @click="printReport" class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer shadow-sm flex items-center gap-1.5">
            🖨️ Print / Export PDF
          </button>
        </div>
      </div>

      <!-- Report Cards & Breakdown -->
      <div class="space-y-6 text-xs font-medium text-slate-800 dark:text-slate-100">

        <!-- Section 1: System Balance -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2">
          <div class="flex justify-between items-center text-sm font-extrabold text-slate-900 dark:text-white">
            <span>Balance in System (General Ledger)</span>
            <span class="font-mono text-lg text-slate-900 dark:text-white">{{ formatMoney(reconciliationSummary.ledgerBalance) }}</span>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">Total cleared balance across all approved transactions in your books.</p>
        </div>

        <!-- Section 2: Outstanding Receipts -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex justify-between items-center text-xs font-black text-emerald-800 dark:text-emerald-200 border-b border-emerald-200 dark:border-emerald-800/60 pb-2">
            <span class="uppercase tracking-wider">PLUS: Outstanding Receipts (Unreconciled Receive Money)</span>
            <span class="font-mono text-sm">+{{ formatMoney(reconciliationSummary.receiptsTotal) }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
                <tr>
                  <th class="py-2 px-3">Date</th>
                  <th class="py-2 px-3">Description / Payee</th>
                  <th class="py-2 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100">
                <tr v-for="tx in reconciliationSummary.outstandingReceipts" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td class="py-2 px-3 font-mono">{{ tx.transaction_date }}</td>
                  <td class="py-2 px-3 font-bold text-slate-900 dark:text-white">{{ tx.raw_description }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ tx.formatted_amount }}</td>
                </tr>
                <tr v-if="!reconciliationSummary.outstandingReceipts.length">
                  <td colspan="3" class="py-4 px-3 text-center text-slate-400 italic">No outstanding receipts.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 3: Outstanding Payments -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
          <div class="flex justify-between items-center text-xs font-black text-rose-800 dark:text-rose-200 border-b border-rose-200 dark:border-rose-800/60 pb-2">
            <span class="uppercase tracking-wider">LESS: Outstanding Payments (Unreconciled Spend Money)</span>
            <span class="font-mono text-sm">-{{ formatMoney(reconciliationSummary.paymentsTotal) }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-bold uppercase text-[10px]">
                <tr>
                  <th class="py-2 px-3">Date</th>
                  <th class="py-2 px-3">Description / Payee</th>
                  <th class="py-2 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold text-slate-800 dark:text-slate-100">
                <tr v-for="tx in reconciliationSummary.outstandingPayments" :key="tx.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                  <td class="py-2 px-3 font-mono">{{ tx.transaction_date }}</td>
                  <td class="py-2 px-3 font-bold text-slate-900 dark:text-white">{{ tx.raw_description }}</td>
                  <td class="py-2 px-3 text-right font-mono font-bold text-rose-700 dark:text-rose-300">{{ formatMoney(Math.abs(tx.amount)) }}</td>
                </tr>
                <tr v-if="!reconciliationSummary.outstandingPayments.length">
                  <td colspan="3" class="py-4 px-3 text-center text-slate-400 italic">No outstanding payments.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 4: Calculated Statement Balance vs Real Bank Balance -->
        <div class="bg-gradient-to-br from-blue-50 dark:from-blue-950/40 to-blue-50/50 dark:to-blue-950/50 rounded-2xl p-5 border border-blue-200 dark:border-blue-800/60 shadow-sm space-y-4">
          <div class="flex justify-between items-center text-sm font-extrabold text-blue-950 dark:text-blue-100">
            <span>Calculated Bank Statement Balance</span>
            <span class="font-mono text-lg text-blue-950 dark:text-blue-100">{{ formatMoney(reconciliationSummary.calculatedStatementBalance) }}</span>
          </div>
          <div class="flex justify-between items-center text-sm font-extrabold text-slate-900 dark:text-white border-t border-blue-200 dark:border-blue-800/60 pt-3">
            <span>Actual Bank Statement Feed Balance</span>
            <span class="font-mono text-lg text-slate-900 dark:text-white">{{ formatMoney(reconciliationSummary.statementBalance) }}</span>
          </div>
          <div
            :class="['flex justify-between items-center text-xs font-black px-4 py-2.5 rounded-xl border', Math.abs(reconciliationSummary.discrepancy) < 0.01 ? 'text-emerald-800 dark:text-emerald-200 bg-emerald-100 dark:bg-emerald-900/40 border-emerald-300 dark:border-emerald-700/60' : 'text-amber-800 dark:text-amber-200 bg-amber-100 dark:bg-amber-900/40 border-amber-300 dark:border-amber-700/60']"
          >
            <span class="flex items-center gap-2 text-sm">
              <span>{{ Math.abs(reconciliationSummary.discrepancy) < 0.01 ? '✅' : '⚠️' }}</span>
              <span>Unreconciled Difference / Discrepancy</span>
            </span>
            <span class="font-mono text-sm">{{ formatMoney(reconciliationSummary.discrepancy) }} {{ Math.abs(reconciliationSummary.discrepancy) < 0.01 ? '(Fully Reconciled)' : '' }}</span>
          </div>
        </div>

      </div>
    </div>
</template>
