<script setup>
import AccountSummaryReport from '@/Components/Accounting/Reports/AccountSummaryReport.vue';
import AgedPayablesReport from '@/Components/Accounting/Reports/AgedPayablesReport.vue';
import AgedReceivablesReport from '@/Components/Accounting/Reports/AgedReceivablesReport.vue';
import BalanceSheetReport from '@/Components/Accounting/Reports/BalanceSheetReport.vue';
import BudgetVsActualReport from '@/Components/Accounting/Reports/BudgetVsActualReport.vue';
import CashSummaryReport from '@/Components/Accounting/Reports/CashSummaryReport.vue';
import ComparativeIncomeExpenditureReport from '@/Components/Accounting/Reports/ComparativeIncomeExpenditureReport.vue';
import ExecutiveSummaryReport from '@/Components/Accounting/Reports/ExecutiveSummaryReport.vue';
import FixedAssetsReport from '@/Components/Accounting/Sections/FixedAssetsReport.vue';
import ProfitAndLossReport from '@/Components/Accounting/Reports/ProfitAndLossReport.vue';
import ProvincialDueReport from '@/Components/Accounting/Reports/ProvincialDueReport.vue';
import ReconciliationSummaryReport from '@/Components/Accounting/Reports/ReconciliationSummaryReport.vue';
import RecurringBillsReport from '@/Components/Accounting/Sections/RecurringBillsReport.vue';
import TreasurerReport from '@/Components/Accounting/Reports/TreasurerReport.vue';
import VatReturnReport from '@/Components/Accounting/Reports/VatReturnReport.vue';
import { formatMoney } from '@/Utils/currency';

// A tab of the accounting screen, in its own file so the page stays readable.
const reportStartDate = defineModel('reportStartDate', { type: [String, Number, Boolean, Array, Object], default: undefined });
const reportEndDate = defineModel('reportEndDate', { type: [String, Number, Boolean, Array, Object], default: undefined });
const reportQuickRange = defineModel('reportQuickRange', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showAuditConfirmModal = defineModel('showAuditConfirmModal', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    annualTreasurerReport: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditRecipients: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorOneLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorOneSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorTwoLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    auditorTwoSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bothAuditorsLocked: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    budgetForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    budgetVsActual: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    budgetableAccounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    cancelAuditSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    changeBudgetYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    changeExaminerYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    changeTreasurerReportYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    charityCommission: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeYearForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    confirmSignOff: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteRecurringBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    depreciationForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    examinerForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    exportableReports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    fixedAssets: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getTypeBadge: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    independentExaminer: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    members: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openActivityLog: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    pdfExportableReports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliationSummary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    recurringBillTemplates: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reports: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resendAuditSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resendExaminerSignature: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    runDepreciation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    selectedReport: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    setQuickRange: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    signOffForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitBudget: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitCloseYear: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitExaminerRequest: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitSignOff: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    summary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleRecurringBill: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    vatSettings: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
      <div class="border-b border-slate-100 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h3 class="text-lg font-black text-slate-900 dark:text-white">Financial Reports & Statements</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Official double-entry financial statements and accounting reports.</p>
        </div>
        <div class="flex items-center gap-2 self-start sm:self-auto">
          <a
            v-if="exportableReports.includes(selectedReport)"
            :href="route('admin.accounting.reports.export', { clubSlug: club.slug, report: selectedReport })"
            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1"
          >
            ⬇️ Export CSV
          </a>
          <a
            v-if="pdfExportableReports.includes(selectedReport)"
            :href="route('admin.accounting.reports.export_pdf', { clubSlug: club.slug, report: selectedReport })"
            class="px-3.5 py-1.5 bg-slate-700 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1"
          >
            📄 Export PDF
          </a>
          <button
            v-if="selectedReport && selectedReport !== 'reconciliation_summary'"
            type="button"
            @click="navigateTo('reporting', null)"
            class="px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1"
          >
            ← Back to All Reports
          </button>
        </div>
      </div>

      <!-- Global Date Range Filter Bar for Reports -->
      <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 flex items-center gap-1.5">
            <span>📅</span> Date Range:
          </span>

          <!-- Preset Buttons -->
          <div class="flex flex-wrap items-center gap-1 bg-white dark:bg-slate-900 p-1 rounded-xl border border-slate-200 dark:border-slate-800">
            <button
              type="button"
              @click="setQuickRange('this_month')"
              :class="[
                'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                reportQuickRange === 'this_month' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              This Month
            </button>
            <button
              type="button"
              @click="setQuickRange('last_month')"
              :class="[
                'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                reportQuickRange === 'last_month' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              Last Month
            </button>
            <button
              type="button"
              @click="setQuickRange('this_year')"
              :class="[
                'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                reportQuickRange === 'this_year' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              This Year
            </button>
            <button
              type="button"
              @click="setQuickRange('all_time')"
              :class="[
                'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                reportQuickRange === 'all_time' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800'
              ]"
            >
              All Time
            </button>
          </div>
        </div>

        <!-- Custom Date Input Pickers -->
        <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200">
          <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-xl shadow-xs">
            <span class="text-slate-400 font-medium">From:</span>
            <input
              type="date"
              v-model="reportStartDate"
              @change="reportQuickRange = 'custom'"
              class="bg-transparent font-mono text-xs text-slate-900 dark:text-white focus:outline-none cursor-pointer"
            />
          </div>
          <span class="text-slate-400 font-bold">to</span>
          <div class="flex items-center gap-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 px-3 py-1.5 rounded-xl shadow-xs">
            <span class="text-slate-400 font-medium">To:</span>
            <input
              type="date"
              v-model="reportEndDate"
              @change="reportQuickRange = 'custom'"
              class="bg-transparent font-mono text-xs text-slate-900 dark:text-white focus:outline-none cursor-pointer"
            />
          </div>
        </div>
      </div>

      <!-- 7 Report Grid Cards Selection -->
      <div v-if="!selectedReport" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- 1. Account Summary -->
        <div @click="navigateTo('reporting', 'account_summary')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">📋</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-slate-200 dark:bg-slate-700 group-hover:bg-blue-200 dark:group-hover:bg-blue-900/60 group-hover:text-blue-900 dark:group-hover:text-blue-200 px-2 py-0.5 rounded-full text-slate-700 dark:text-slate-200">General Ledger</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Account Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Full activity debit/credit summary across chart of accounts.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-700 dark:text-blue-300">
            <span>{{ reports.account_summary ? reports.account_summary.length : 0 }} Accounts</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 2. Aged Payables Summary -->
        <div @click="navigateTo('reporting', 'aged_payables')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">📉</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 px-2 py-0.5 rounded-full">Payables</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Aged Payables Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Outstanding vendor bills grouped by 0-30, 31-60, 61-90, 90+ days.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-amber-700 dark:text-amber-300">
            <span>Total: {{ formatMoney(reports.aged_payables ? reports.aged_payables.total : 0) }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- Independent Examiner's Report -->
        <div v-if="charityCommission.registered" @click="navigateTo('reporting', 'independent_examiner')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🖋️</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-purple-100 dark:bg-purple-900/40 text-purple-800 dark:text-purple-200 px-2 py-0.5 rounded-full">Charity</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Independent Examiner's Report</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Annual examination of the registered Charity Fund, signed by your examiner.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-end text-xs font-bold text-purple-700 dark:text-purple-300">
            <span>View Report →</span>
          </div>
        </div>

        <!-- Due to Province -->
        <div @click="navigateTo('reporting', 'provincial_due')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🏛️</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded-full">Province</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">What's Due to Province</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Per-capita dues and Festival contribution for your active members.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-700 dark:text-blue-300">
            <span>{{ reports.provincial_due?.total_due != null ? formatMoney(reports.provincial_due.total_due) : 'Rate not set' }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 3. Aged Receivables Summary -->
        <div @click="navigateTo('reporting', 'aged_receivables')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">📈</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 rounded-full">Receivables</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Aged Receivables Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Outstanding member dues & invoices grouped by aging buckets.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-emerald-700 dark:text-emerald-300">
            <span>Total: {{ formatMoney(reports.aged_receivables ? reports.aged_receivables.total : 0) }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 4. Balance Sheet -->
        <div @click="navigateTo('reporting', 'balance_sheet')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">⚖️</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded-full">Statement</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Balance Sheet</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Assets, liabilities, and retained club equity balance statement.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-700 dark:text-blue-300">
            <span>Assets: {{ formatMoney(summary.total_assets) }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 5. Cash Summary -->
        <div @click="navigateTo('reporting', 'cash_summary')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">💵</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded-full">Cash Flow</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Cash Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Net cash position in bank and petty cash operating accounts.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-700 dark:text-blue-300">
            <span>Cash: {{ formatMoney(reports.cash_summary ? reports.cash_summary.total_cash_on_hand : 0) }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 6. Executive Summary -->
        <div @click="navigateTo('reporting', 'executive_summary')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">📊</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 px-2 py-0.5 rounded-full">KPI Ratios</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Executive Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Key financial performance metrics, profit margins & ratios.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-700 dark:text-blue-300">
            <span>Margin: {{ reports.executive_summary ? reports.executive_summary.net_profit_margin_pct : 0 }}%</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 7. Profit and Loss -->
        <div @click="navigateTo('reporting', 'profit_and_loss')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🧾</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 px-2 py-0.5 rounded-full">P&L</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Profit and Loss (P&L)</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Detailed revenue income minus operating expenses statement.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-emerald-700 dark:text-emerald-300">
            <span>Net: {{ formatMoney(summary.net_income) }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 8. Comparative Annual Income & Expenditure Statement -->
        <div @click="navigateTo('reporting', 'comparative_income_expenditure')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🏛️</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 px-2 py-0.5 rounded-full">Annual Statement</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Comparative Income & Expenditure</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Side-by-side 4-column annual balance statement comparing multi-year totals.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-200">
            <span>Comparing {{ reports.comparative_income_expenditure?.prior_year_label }} vs {{ reports.comparative_income_expenditure?.current_year_label }}</span>
            <span>View Audit Report →</span>
          </div>
        </div>

        <!-- 9. Bank Reconciliation Summary -->
        <div @click="navigateTo('reporting', 'reconciliation_summary')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">💳</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-blue-100 dark:bg-blue-900/40 text-blue-900 dark:text-blue-200 px-2 py-0.5 rounded-full">Bank Audit</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Bank Reconciliation Summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Audit verification comparing General Ledger vs actual bank feed balance.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-blue-800 dark:text-blue-200">
            <span>System {{ formatMoney(reconciliationSummary.ledgerBalance) }} vs Feed {{ formatMoney(reconciliationSummary.statementBalance) }}</span>
            <span>View Audit Report →</span>
          </div>
        </div>

        <!-- 10. VAT Return -->
        <div v-if="vatSettings.enabled" @click="navigateTo('reporting', 'vat_return')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🧮</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-purple-100 dark:bg-purple-900/40 text-purple-900 dark:text-purple-200 px-2 py-0.5 rounded-full">Tax</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">VAT Return</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Output VAT minus input VAT for the current quarter, exportable for HMRC filing.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-purple-800 dark:text-purple-200">
            <span>{{ reports.vat_return?.quarter_label }}: {{ formatMoney(reports.vat_return?.net_vat_due) }} due</span>
            <span>View Return →</span>
          </div>
        </div>

        <!-- 11. Fixed Asset Register -->
        <div @click="navigateTo('reporting', 'fixed_assets')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🚣</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-teal-100 dark:bg-teal-900/40 text-teal-900 dark:text-teal-200 px-2 py-0.5 rounded-full">Assets</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Fixed Asset Register</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Boats, equipment, regalia and property — cost, depreciation and net book value.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-teal-800 dark:text-teal-200">
            <span>{{ fixedAssets.length }} asset{{ fixedAssets.length === 1 ? '' : 's' }} registered</span>
            <span>View Register →</span>
          </div>
        </div>

        <!-- 12. Budget vs Actual -->
        <div @click="navigateTo('reporting', 'budget_vs_actual')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🎯</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-indigo-100 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-200 px-2 py-0.5 rounded-full">Planning</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Budget vs Actual</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set an annual budget per account and track variance against real income/expenditure.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-indigo-800 dark:text-indigo-200">
            <span>{{ budgetVsActual.financial_year }}: {{ formatMoney(budgetVsActual.total_actual) }} of {{ formatMoney(budgetVsActual.total_budgeted) }}</span>
            <span>View Budget →</span>
          </div>
        </div>

        <!-- 13. Annual Treasurer's Report -->
        <div @click="navigateTo('reporting', 'treasurer_report')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🏛️</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 px-2 py-0.5 rounded-full">Annual Report</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Annual Treasurer's Report</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">General Fund &amp; Charity Fund statement, bank reconciliation, arrears — ready to present at Installation.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-amber-800 dark:text-amber-200">
            <span>{{ annualTreasurerReport.financial_year }}: {{ annualTreasurerReport.is_closed ? 'Books closed' : 'Books open' }}</span>
            <span>View Report →</span>
          </div>
        </div>

        <!-- 14. Recurring Vendor Bills -->
        <div @click="navigateTo('reporting', 'recurring_bills')" class="bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50/50 dark:hover:bg-blue-950/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700/60 transition-all cursor-pointer space-y-3 group">
          <div class="flex items-center justify-between">
            <span class="text-2xl group-hover:scale-110 transition-transform">🔁</span>
            <span class="text-[10px] font-black uppercase tracking-wider bg-rose-100 dark:bg-rose-900/40 text-rose-900 dark:text-rose-200 px-2 py-0.5 rounded-full">Automation</span>
          </div>
          <div>
            <h4 class="font-extrabold text-slate-900 dark:text-white text-sm group-hover:text-blue-800 dark:group-hover:text-blue-200">Recurring Vendor Bills</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Calendar-driven bills — insurance, hall hire, subscriptions — generated automatically when due.</p>
          </div>
          <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs font-bold text-rose-800 dark:text-rose-200">
            <span>{{ recurringBillTemplates.filter(t => t.is_active).length }} active schedule{{ recurringBillTemplates.filter(t => t.is_active).length === 1 ? '' : 's' }}</span>
            <span>Manage →</span>
          </div>
        </div>
      </div>

      <!-- Detailed Report Views -->
      <div v-else class="space-y-6">
        <!-- Report 1: Account Summary Detail -->
        <AccountSummaryReport v-if="selectedReport === 'account_summary'" :get-type-badge="getTypeBadge" :reports="reports" />

        <!-- Report 2: Aged Payables Summary Detail -->
        <AgedPayablesReport v-if="selectedReport === 'aged_payables'" :bills="bills" :reports="reports" />

        <!-- Report 3: Aged Receivables Summary Detail -->
        <AgedReceivablesReport v-if="selectedReport === 'aged_receivables'" :invoices="invoices" :reports="reports" />

        <!-- Report 4: Balance Sheet Detail -->
        <BalanceSheetReport v-if="selectedReport === 'balance_sheet'" :reports="reports" />

        <!-- Report 5: Cash Summary Detail -->
        <CashSummaryReport v-if="selectedReport === 'cash_summary'" :reports="reports" />

        <!-- Report 6: Executive Summary Detail -->
        <ExecutiveSummaryReport v-if="selectedReport === 'executive_summary'" :reports="reports" />

        <!-- Report 7: Profit & Loss Detail -->
        <ProfitAndLossReport v-if="selectedReport === 'profit_and_loss'" :reports="reports" />

        <!-- Report 8: Comparative Annual Income & Expenditure Statement Detail -->
        <ComparativeIncomeExpenditureReport v-if="selectedReport === 'comparative_income_expenditure'" :accounts="accounts" :reports="reports" />

        <!-- 9. Bank Reconciliation Summary Report Page View -->
        <ReconciliationSummaryReport v-if="selectedReport === 'reconciliation_summary'" :reconciliation-summary="reconciliationSummary" :report-end-date="reportEndDate" :report-start-date="reportStartDate" />

        <!-- Report: Independent Examiner's Report -->
        <div v-if="selectedReport === 'independent_examiner' && charityCommission.registered" class="space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-800/50 p-6 rounded-2xl border border-slate-200 dark:border-slate-800">
            <div>
              <h3 class="text-xl font-black text-slate-900 dark:text-white">Independent Examiner's Report — {{ independentExaminer.financial_year }}</h3>
              <p class="text-xs text-slate-600 dark:text-slate-300 font-semibold mt-0.5">Charity number {{ charityCommission.charity_number }}</p>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" @click="changeExaminerYear(independentExaminer.financial_year - 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">← {{ independentExaminer.financial_year - 1 }}</button>
              <button type="button" @click="changeExaminerYear(independentExaminer.financial_year + 1)" class="px-3 py-2 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 font-extrabold text-xs rounded-xl cursor-pointer">{{ independentExaminer.financial_year + 1 }} →</button>
              <a :href="route('admin.accounting.independent_examiner.export_pdf', { clubSlug: club.slug, year: independentExaminer.financial_year })" target="_blank" class="px-3 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl cursor-pointer">📄 PDF</a>
            </div>
          </div>

          <div v-if="independentExaminer.report" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="font-bold text-slate-900 dark:text-white">{{ independentExaminer.report.examiner_name }}</span>
              <span :class="['px-2 py-0.5 rounded-lg text-[10px] font-black uppercase border', independentExaminer.report.examined_at ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/60' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/60']">
                {{ independentExaminer.report.examined_at ? `Signed ${independentExaminer.report.examined_at}` : (independentExaminer.report.signature_label || 'Awaiting signature') }}
              </span>
            </div>
            <button v-if="canManageBilling && !independentExaminer.report.examined_at" type="button" @click="resendExaminerSignature" class="text-[11px] font-extrabold text-blue-700 dark:text-blue-300 hover:underline cursor-pointer">Resend signature request</button>
          </div>

          <form v-if="canManageBilling && !independentExaminer.report?.examined_at" @submit.prevent="submitExaminerRequest" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
            <h4 class="font-extrabold text-slate-900 dark:text-white">{{ independentExaminer.report ? 'Change examiner' : 'Ask an examiner to sign' }}</h4>
            <p v-if="!independentExaminer.examiners.length" class="text-slate-500 dark:text-slate-400">Invite your examiner to the lodge first and give them the "Examiner / Auditor" role, then choose them here.</p>
            <template v-else>
              <select v-model="examinerForm.examiner_user_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
                <option value="" disabled>Choose examiner…</option>
                <option v-for="e in independentExaminer.examiners" :key="e.id" :value="e.id">{{ e.name }}</option>
              </select>
              <p v-if="examinerForm.errors.examiner_user_id" class="text-rose-600 dark:text-rose-400 font-semibold">{{ examinerForm.errors.examiner_user_id }}</p>
              <textarea v-model="examinerForm.notes" rows="2" placeholder="Notes for the report (optional)" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl"></textarea>
              <button type="submit" :disabled="!examinerForm.examiner_user_id || examinerForm.processing" class="px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white font-extrabold rounded-xl cursor-pointer disabled:opacity-50">Send signature request</button>
            </template>
          </form>
        </div>

        <!-- Report: Due to Province -->
        <ProvincialDueReport v-if="selectedReport === 'provincial_due'" :members="members" :reports="reports" />

        <!-- Report: VAT Return -->
        <VatReturnReport v-if="selectedReport === 'vat_return'" :club="club" :reports="reports" />

        <!-- Report: Fixed Asset Register -->
        <FixedAssetsReport v-if="selectedReport === 'fixed_assets'" :can-manage-billing="canManageBilling" :club="club" :depreciation-form="depreciationForm" :fixed-assets="fixedAssets" :open-activity-log="openActivityLog" :run-depreciation="runDepreciation" />

        <!-- Report: Budget vs Actual -->
        <BudgetVsActualReport v-if="selectedReport === 'budget_vs_actual'" :budget-form="budgetForm" :budgetable-accounts="budgetableAccounts" :change-budget-year="changeBudgetYear" :submit-budget="submitBudget" :budget-vs-actual="budgetVsActual" :can-manage-billing="canManageBilling" />

        <!-- Report: Annual Treasurer's Report -->
        <TreasurerReport v-if="selectedReport === 'treasurer_report'" :auditor-one-locked="auditorOneLocked" :auditor-one-signature="auditorOneSignature" :auditor-two-locked="auditorTwoLocked" :auditor-two-signature="auditorTwoSignature" :both-auditors-locked="bothAuditorsLocked" :cancel-audit-signature="cancelAuditSignature" :change-treasurer-report-year="changeTreasurerReportYear" :close-year-form="closeYearForm" :resend-audit-signature="resendAuditSignature" :sign-off-form="signOffForm" :submit-close-year="submitCloseYear" :submit-sign-off="submitSignOff" :accounts="accounts" :annual-treasurer-report="annualTreasurerReport" :club="club" :members="members" />

        <div v-if="showAuditConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showAuditConfirmModal = false">
          <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4" @click.stop>
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
              <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Send signature requests?</h3>
              <button type="button" @click="showAuditConfirmModal = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">Each person below will be emailed a private link to review and sign the {{ annualTreasurerReport.financial_year }} accounts themselves.</p>
            <div class="space-y-2">
              <div v-for="m in auditRecipients" :key="m.id" class="flex items-center justify-between text-xs bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2.5">
                <span class="font-bold text-slate-900 dark:text-white">{{ m.name }}</span>
                <span class="text-slate-500 dark:text-slate-400">{{ m.email }}</span>
              </div>
            </div>
            <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-end gap-3">
              <button type="button" @click="showAuditConfirmModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer">Cancel</button>
              <button type="button" @click="confirmSignOff" :disabled="signOffForm.processing" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-md text-xs cursor-pointer disabled:opacity-50">Send signature requests</button>
            </div>
          </div>
        </div>

        <!-- Report: Recurring Vendor Bills -->
        <RecurringBillsReport v-if="selectedReport === 'recurring_bills'" :bills="bills" :club="club" :delete-recurring-bill="deleteRecurringBill" :recurring-bill-templates="recurringBillTemplates" :toggle-recurring-bill="toggleRecurringBill" />
      </div>
    </div>
</template>
