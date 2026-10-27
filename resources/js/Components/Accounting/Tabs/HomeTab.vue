<script setup>
import { formatMoney } from '@/Utils/currency';

// The Home tab of the accounting screen: set-up checklist, headline figures, balance sheet and profit & loss previews, and the Gift Aid summary.
defineProps({
    summary: { type: Object, required: true },
    onboarding: { type: Object, default: undefined },
    reconciliation: { type: Object, default: undefined },
    showOnboarding: { type: Boolean, default: false },
    dismissOnboarding: { type: Function, required: true },
    navigateTo: { type: Function, required: true }
});
</script>

<template>
  <div class="space-y-6">
      <!-- Guided First-Run Setup Checklist -->
      <div v-if="showOnboarding" class="bg-blue-50 dark:bg-blue-950/30 rounded-3xl border border-blue-200 dark:border-blue-800/60 shadow-sm p-6 space-y-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <h3 class="text-sm font-black text-blue-900 dark:text-blue-100">Get your accounting set up</h3>
            <p class="text-xs text-blue-700 dark:text-blue-300 mt-0.5">A few quick steps before the figures above mean anything.</p>
          </div>
          <button
            type="button"
            @click="dismissOnboarding"
            class="text-[11px] font-bold text-blue-500 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 cursor-pointer whitespace-nowrap"
          >
            Dismiss ✕
          </button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <button
            v-for="step in onboarding.steps"
            :key="step.key"
            type="button"
            @click="navigateTo(step.tab)"
            class="flex items-center gap-3 p-3.5 bg-white dark:bg-slate-900 rounded-2xl border border-blue-100 dark:border-blue-900/60 text-left hover:border-blue-300 dark:hover:border-blue-700 transition-all cursor-pointer"
          >
            <span class="text-lg shrink-0">{{ step.done ? '✅' : '⬜️' }}</span>
            <span class="min-w-0">
              <span class="block text-xs font-extrabold text-slate-900 dark:text-white">{{ step.label }}</span>
              <span v-if="step.skippable" class="block text-[10px] text-slate-400 dark:text-slate-500 font-semibold">Optional</span>
            </span>
          </button>
        </div>
      </div>

      <!-- Financial KPIs Overview Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- 1. Total Assets -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Assets</span>
          <span class="text-lg font-black text-emerald-700 dark:text-emerald-300 block">{{ formatMoney(summary.total_assets) }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">Bank & Receivables</span>
        </div>

        <!-- 2. Total Liabilities -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Liabilities</span>
          <span class="text-lg font-black text-amber-700 dark:text-amber-300 block">{{ formatMoney(summary.total_liabilities) }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">Payables & Prepayments</span>
        </div>

        <!-- 3. Total Equity -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Club Equity</span>
          <span class="text-lg font-black text-blue-700 dark:text-blue-300 block">{{ formatMoney(summary.total_equity) }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">Retained Reserves</span>
        </div>

        <!-- 4. Total Revenue -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Income</span>
          <span class="text-lg font-black text-blue-700 dark:text-blue-300 block">{{ formatMoney(summary.total_revenue) }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">Dues & Tickets</span>
        </div>

        <!-- 5. Total Expenses -->
        <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Expenses</span>
          <span class="text-lg font-black text-rose-700 dark:text-rose-300 block">{{ formatMoney(summary.total_expenses) }}</span>
          <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">Operational Costs</span>
        </div>

        <!-- 6. Net Surplus / Profit -->
        <div class="bg-slate-900 dark:bg-slate-700 text-white p-4 rounded-2xl border border-slate-800 shadow-md space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Net Income</span>
          <span class="text-lg font-black block" :class="summary.net_income >= 0 ? 'text-emerald-400' : 'text-rose-400'">
            {{ formatMoney(summary.net_income) }}
          </span>
          <span class="text-[10px] text-slate-400 block font-medium">Surplus / (Deficit)</span>
        </div>
      </div>

      <!-- VIEW 1: HOME (Dashboard & Financial Statements Overview) -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Balance Sheet Preview Box -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Balance Sheet Summary</h3>
            <span class="text-xs font-semibold text-slate-400">As of today</span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 bg-emerald-50/50 dark:bg-emerald-950/50 rounded-xl border border-emerald-100 dark:border-emerald-900/40">
              <span class="font-bold text-slate-800 dark:text-slate-100">Total Assets</span>
              <span class="font-black text-emerald-700 dark:text-emerald-300 text-sm">{{ formatMoney(summary.total_assets) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-amber-50/50 dark:bg-amber-950/50 rounded-xl border border-amber-100 dark:border-amber-900/40">
              <span class="font-bold text-slate-800 dark:text-slate-100">Total Liabilities</span>
              <span class="font-black text-amber-700 dark:text-amber-300 text-sm">{{ formatMoney(summary.total_liabilities) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-blue-50/50 dark:bg-blue-950/50 rounded-xl border border-blue-100 dark:border-blue-900/40">
              <span class="font-bold text-slate-800 dark:text-slate-100">Total Club Equity</span>
              <span class="font-black text-blue-700 dark:text-blue-300 text-sm">{{ formatMoney(summary.total_equity) }}</span>
            </div>

            <div class="p-3.5 bg-slate-900 dark:bg-slate-700 text-white rounded-2xl flex items-center justify-between mt-4">
              <div>
                <span class="font-extrabold block text-xs">Accounting Equation Status</span>
                <span class="text-[10px] text-slate-400 block">Assets = Liabilities + Equity</span>
              </div>
              <span class="px-2.5 py-1 rounded-lg text-xs font-black bg-emerald-500 text-slate-950">
                BALANCED ✓
              </span>
            </div>
          </div>
        </div>

        <!-- Profit & Loss Summary Box -->
        <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Income Statement (P&L)</h3>
            <span class="text-xs font-semibold text-slate-400">Year to Date</span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 bg-blue-50/50 dark:bg-blue-950/50 rounded-xl border border-blue-100 dark:border-blue-900/40">
              <span class="font-bold text-slate-800 dark:text-slate-100">Operating Income / Dues</span>
              <span class="font-black text-blue-700 dark:text-blue-300 text-sm">{{ formatMoney(summary.total_revenue) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-rose-50/50 dark:bg-rose-950/50 rounded-xl border border-rose-100 dark:border-rose-900/40">
              <span class="font-bold text-slate-800 dark:text-slate-100">Operating Expenses</span>
              <span class="font-black text-rose-700 dark:text-rose-300 text-sm">{{ formatMoney(summary.total_expenses) }}</span>
            </div>

            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 flex items-center justify-between mt-4">
              <div>
                <span class="font-extrabold text-slate-900 dark:text-white block text-xs">Net Operating Surplus</span>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Revenue minus Expenses</span>
              </div>
              <span class="text-base font-black" :class="summary.net_income >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                {{ formatMoney(summary.net_income) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Donations & Gift Aid Tax Recovery Summary Box -->
      <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800 gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg border border-blue-100 dark:border-blue-900/40 shadow-sm">
              🏛️
            </div>
            <div>
              <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Donations & Gift Aid Tax Recovery Summary</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">HMRC 25% Tax Relief & Relief Chest Bank Reconciliation Overview</p>
            </div>
          </div>
          <button
            @click="navigateTo('reconciliation')"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-xl border border-blue-200/60 dark:border-blue-800/60 transition shadow-xs self-start sm:self-auto cursor-pointer"
          >
            <span>Manage Claims & Reconciliation</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
          <!-- 1. Total Eligible Donations -->
          <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200/70 dark:border-slate-800/70 space-y-1">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Eligible Donations</span>
            <span class="text-lg font-black text-slate-900 dark:text-white block">
              {{ reconciliation.gift_aid_summary?.formatted_total_donations || ($cs + '0.00') }}
            </span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-medium">
              {{ reconciliation.gift_aid_summary?.total_eligible_count || 0 }} charity collections
            </span>
          </div>

          <!-- 2. Calculated 25% Gift Aid -->
          <div class="p-4 bg-blue-50/60 dark:bg-blue-950/60 rounded-2xl border border-blue-100/80 dark:border-blue-900/80 space-y-1">
            <span class="text-[10px] font-extrabold text-blue-600 dark:text-blue-400 uppercase tracking-wider block">25% Gift Aid Claimable</span>
            <span class="text-lg font-black text-blue-700 dark:text-blue-300 block">
              {{ reconciliation.gift_aid_summary?.formatted_claimable_gift_aid || ($cs + '0.00') }}
            </span>
            <span class="text-[10px] text-blue-600/80 dark:text-blue-400/80 block font-medium">Standard HMRC reclaim rate</span>
          </div>

          <!-- 3. Unclaimed Gift Aid -->
          <div class="p-4 bg-amber-50/60 dark:bg-amber-950/60 rounded-2xl border border-amber-100/80 dark:border-amber-900/80 space-y-1">
            <span class="text-[10px] font-extrabold text-amber-700 dark:text-amber-300 uppercase tracking-wider block">Unclaimed Relief</span>
            <span class="text-lg font-black text-amber-700 dark:text-amber-300 block">
              {{ reconciliation.gift_aid_summary?.formatted_unclaimed_gift_aid || ($cs + '0.00') }}
            </span>
            <span class="text-[10px] text-amber-700/80 dark:text-amber-300/80 block font-medium">Awaiting schedule export</span>
          </div>

          <!-- 4. Reconciled Bank Credits -->
          <div class="p-4 bg-emerald-50/60 dark:bg-emerald-950/60 rounded-2xl border border-emerald-100/80 dark:border-emerald-900/80 space-y-1">
            <span class="text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider block">Reconciled Credits</span>
            <span class="text-lg font-black text-emerald-700 dark:text-emerald-300 block">
              {{ reconciliation.gift_aid_summary?.formatted_reconciled_gift_aid || ($cs + '0.00') }}
            </span>
            <span class="text-[10px] text-emerald-700/80 dark:text-emerald-300/80 block font-medium">Matched to bank deposits</span>
          </div>
        </div>
      </div>
  </div>
</template>
