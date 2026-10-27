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
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Modal from '@/Components/Ui/Modal.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { formatMoney } from '@/Utils/currency';

// Shared looks for the report gallery: a card per report, the preset range buttons, and download links styled as kit buttons.
const reportCardClass = 'group w-full space-y-3 rounded-2xl border border-slate-200 bg-white p-5 text-left shadow-sm transition-colors hover:border-blue-300 hover:bg-blue-50/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-700/60 dark:hover:bg-blue-950/30';
const rangeButtonClass = (isActive) => [
    'inline-flex items-center rounded-xl px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition-colors',
    isActive
        ? 'bg-slate-900 text-white shadow-sm dark:bg-slate-700'
        : 'text-slate-600 hover:bg-white/70 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-900/60 dark:hover:text-white',
];
const linkButtonClass = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 transition-colors focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';

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
    <Card class="space-y-6">
      <div class="border-b border-slate-200 dark:border-slate-800 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Financial reports and statements</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Official double-entry financial statements and accounting reports.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
          <a
            v-if="exportableReports.includes(selectedReport)"
            :href="route('admin.accounting.reports.export', { clubSlug: club.slug, report: selectedReport })"
            :class="linkButtonClass"
          >
            <UiIcon name="download" :size="14" />
            Export CSV
          </a>
          <a
            v-if="pdfExportableReports.includes(selectedReport)"
            :href="route('admin.accounting.reports.export_pdf', { clubSlug: club.slug, report: selectedReport })"
            :class="linkButtonClass"
          >
            <UiIcon name="document" :size="14" />
            Export PDF
          </a>
          <Button
            v-if="selectedReport && selectedReport !== 'reconciliation_summary'"
            variant="ghost"
            size="sm"
            @click="navigateTo('reporting', null)"
          >
            <template #icon><UiIcon name="arrow-left" :size="14" /></template>
            Back to all reports
          </Button>
        </div>
      </div>

      <!-- Global Date Range Filter Bar for Reports -->
      <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-col lg:flex-row lg:items-end justify-between gap-4">
        <div class="space-y-1.5">
          <span class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <UiIcon name="calendar" :size="14" /> Date range
          </span>

          <!-- Preset Buttons -->
          <div class="flex w-fit flex-wrap items-center gap-1 rounded-2xl border border-slate-300/60 bg-slate-200/80 p-1.5 dark:border-slate-700/60 dark:bg-slate-800/80">
            <button
              type="button"
              @click="setQuickRange('this_month')"
              :class="rangeButtonClass(reportQuickRange === 'this_month')"
              :aria-pressed="reportQuickRange === 'this_month'"
            >
              This month
            </button>
            <button
              type="button"
              @click="setQuickRange('last_month')"
              :class="rangeButtonClass(reportQuickRange === 'last_month')"
              :aria-pressed="reportQuickRange === 'last_month'"
            >
              Last month
            </button>
            <button
              type="button"
              @click="setQuickRange('this_year')"
              :class="rangeButtonClass(reportQuickRange === 'this_year')"
              :aria-pressed="reportQuickRange === 'this_year'"
            >
              This year
            </button>
            <button
              type="button"
              @click="setQuickRange('all_time')"
              :class="rangeButtonClass(reportQuickRange === 'all_time')"
              :aria-pressed="reportQuickRange === 'all_time'"
            >
              All time
            </button>
          </div>
        </div>

        <!-- Custom Date Input Pickers -->
        <div class="flex flex-wrap items-end gap-3">
          <Input
            type="date"
            label="From"
            v-model="reportStartDate"
            @change="reportQuickRange = 'custom'"
            class="w-40"
          />
          <Input
            type="date"
            label="To"
            v-model="reportEndDate"
            @change="reportQuickRange = 'custom'"
            class="w-40"
          />
        </div>
      </div>

      <!-- 7 Report Grid Cards Selection -->
      <div v-if="!selectedReport" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- 1. Account Summary -->
        <button type="button" @click="navigateTo('reporting', 'account_summary')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="clipboard" :size="20" /></span>
            <Badge>General ledger</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Account summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Full activity debit/credit summary across chart of accounts.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">{{ reports.account_summary ? reports.account_summary.length : 0 }} accounts</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 2. Aged Payables Summary -->
        <button type="button" @click="navigateTo('reporting', 'aged_payables')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="clock" :size="20" /></span>
            <Badge>Payables</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Aged payables</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Outstanding vendor bills grouped by 0-30, 31-60, 61-90, 90+ days.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Total: {{ formatMoney(reports.aged_payables ? reports.aged_payables.total : 0) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- Independent Examiner's Report -->
        <button v-if="charityCommission.registered" type="button" @click="navigateTo('reporting', 'independent_examiner')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="pencil" :size="20" /></span>
            <Badge>Charity</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Independent examiner's report</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Annual examination of the registered Charity Fund, signed by your examiner.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2 text-xs font-medium">
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- Due to Province -->
        <button type="button" @click="navigateTo('reporting', 'provincial_due')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="building" :size="20" /></span>
            <Badge>Province</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">What's due to Province</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Per-capita dues and Festival contribution for your active members.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">{{ reports.provincial_due?.total_due != null ? formatMoney(reports.provincial_due.total_due) : 'Rate not set' }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 3. Aged Receivables Summary -->
        <button type="button" @click="navigateTo('reporting', 'aged_receivables')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="cash" :size="20" /></span>
            <Badge>Receivables</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Aged receivables</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Outstanding member dues & invoices grouped by aging buckets.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Total: {{ formatMoney(reports.aged_receivables ? reports.aged_receivables.total : 0) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 4. Balance Sheet -->
        <button type="button" @click="navigateTo('reporting', 'balance_sheet')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="chart" :size="20" /></span>
            <Badge>Statement</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Balance sheet</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Assets, liabilities, and retained club equity balance statement.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Assets: {{ formatMoney(summary.total_assets) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 5. Cash Summary -->
        <button type="button" @click="navigateTo('reporting', 'cash_summary')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="bank" :size="20" /></span>
            <Badge>Cash flow</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Cash summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Net cash position in bank and petty cash operating accounts.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Cash: {{ formatMoney(reports.cash_summary ? reports.cash_summary.total_cash_on_hand : 0) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 6. Executive Summary -->
        <button type="button" @click="navigateTo('reporting', 'executive_summary')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="sparkles" :size="20" /></span>
            <Badge>KPI ratios</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Executive summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Key financial performance metrics, profit margins & ratios.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Margin: {{ reports.executive_summary ? reports.executive_summary.net_profit_margin_pct : 0 }}%</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 7. Profit and Loss -->
        <button type="button" @click="navigateTo('reporting', 'profit_and_loss')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="receipt" :size="20" /></span>
            <Badge>P&amp;L</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Profit and loss (P&amp;L)</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Detailed revenue income minus operating expenses statement.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">Net: {{ formatMoney(summary.net_income) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 8. Comparative Annual Income & Expenditure Statement -->
        <button type="button" @click="navigateTo('reporting', 'comparative_income_expenditure')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="document" :size="20" /></span>
            <Badge>Annual statement</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Comparative income and expenditure</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Side-by-side 4-column annual balance statement comparing multi-year totals.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300">Comparing {{ reports.comparative_income_expenditure?.prior_year_label }} vs {{ reports.comparative_income_expenditure?.current_year_label }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 9. Bank Reconciliation Summary -->
        <button type="button" @click="navigateTo('reporting', 'reconciliation_summary')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="check" :size="20" /></span>
            <Badge>Bank audit</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Bank reconciliation summary</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Audit verification comparing the general ledger with the actual bank feed balance.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">System {{ formatMoney(reconciliationSummary.ledgerBalance) }} vs feed {{ formatMoney(reconciliationSummary.statementBalance) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 10. VAT Return -->
        <button v-if="vatSettings.enabled" type="button" @click="navigateTo('reporting', 'vat_return')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="tag" :size="20" /></span>
            <Badge>Tax</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">VAT return</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Output VAT minus input VAT for the current quarter, exportable for HMRC filing.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">{{ reports.vat_return?.quarter_label }}: {{ formatMoney(reports.vat_return?.net_vat_due) }} due</span>
            <span class="text-blue-700 dark:text-blue-300">View return →</span>
          </div>
        </button>

        <!-- 11. Fixed Asset Register -->
        <button type="button" @click="navigateTo('reporting', 'fixed_assets')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="archive" :size="20" /></span>
            <Badge>Assets</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Fixed asset register</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Boats, equipment, regalia and property — cost, depreciation and net book value.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300">{{ fixedAssets.length }} asset{{ fixedAssets.length === 1 ? '' : 's' }} registered</span>
            <span class="text-blue-700 dark:text-blue-300">View register →</span>
          </div>
        </button>

        <!-- 12. Budget vs Actual -->
        <button type="button" @click="navigateTo('reporting', 'budget_vs_actual')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="calendar" :size="20" /></span>
            <Badge>Planning</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Budget vs actual</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Set an annual budget per account and track variance against real income/expenditure.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300 tabular-nums">{{ budgetVsActual.financial_year }}: {{ formatMoney(budgetVsActual.total_actual) }} of {{ formatMoney(budgetVsActual.total_budgeted) }}</span>
            <span class="text-blue-700 dark:text-blue-300">View budget →</span>
          </div>
        </button>

        <!-- 13. Annual Treasurer's Report -->
        <button type="button" @click="navigateTo('reporting', 'treasurer_report')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="award" :size="20" /></span>
            <Badge>Annual report</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Annual Treasurer's report</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">General Fund &amp; Charity Fund statement, bank reconciliation, arrears — ready to present at Installation.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300">{{ annualTreasurerReport.financial_year }}: {{ annualTreasurerReport.is_closed ? 'Books closed' : 'Books open' }}</span>
            <span class="text-blue-700 dark:text-blue-300">View report →</span>
          </div>
        </button>

        <!-- 14. Recurring Vendor Bills -->
        <button type="button" @click="navigateTo('reporting', 'recurring_bills')" :class="reportCardClass">
          <div class="flex items-center justify-between">
            <span class="text-slate-400 transition-colors group-hover:text-blue-600 dark:group-hover:text-blue-400"><UiIcon name="refresh" :size="20" /></span>
            <Badge>Automation</Badge>
          </div>
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white group-hover:text-blue-700 dark:group-hover:text-blue-300">Recurring vendor bills</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Calendar-driven bills — insurance, hall hire, subscriptions — generated automatically when due.</p>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2 text-xs font-medium">
            <span class="text-slate-600 dark:text-slate-300">{{ recurringBillTemplates.filter(t => t.is_active).length }} active schedule{{ recurringBillTemplates.filter(t => t.is_active).length === 1 ? '' : 's' }}</span>
            <span class="text-blue-700 dark:text-blue-300">Manage →</span>
          </div>
        </button>
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
              <h3 class="text-xl font-semibold text-slate-900 dark:text-white">Independent examiner's report — {{ independentExaminer.financial_year }}</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Charity number {{ charityCommission.charity_number }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Button variant="secondary" size="sm" @click="changeExaminerYear(independentExaminer.financial_year - 1)">← {{ independentExaminer.financial_year - 1 }}</Button>
              <Button variant="secondary" size="sm" @click="changeExaminerYear(independentExaminer.financial_year + 1)">{{ independentExaminer.financial_year + 1 }} →</Button>
              <a :href="route('admin.accounting.independent_examiner.export_pdf', { clubSlug: club.slug, year: independentExaminer.financial_year })" target="_blank" :class="linkButtonClass">
                <UiIcon name="document" :size="14" />
                PDF
              </a>
            </div>
          </div>

          <Card v-if="independentExaminer.report" padding="sm" class="space-y-2 text-xs">
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-medium text-slate-900 dark:text-white">{{ independentExaminer.report.examiner_name }}</span>
              <Badge :variant="independentExaminer.report.examined_at ? 'success' : 'warning'">
                {{ independentExaminer.report.examined_at ? `Signed ${independentExaminer.report.examined_at}` : (independentExaminer.report.signature_label || 'Awaiting signature') }}
              </Badge>
            </div>
            <button v-if="canManageBilling && !independentExaminer.report.examined_at" type="button" @click="resendExaminerSignature" class="text-xs font-medium text-blue-700 dark:text-blue-300 hover:underline">Resend signature request</button>
          </Card>

          <form v-if="canManageBilling && !independentExaminer.report?.examined_at" @submit.prevent="submitExaminerRequest" class="bg-white dark:bg-slate-900 rounded-2xl p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
            <h4 class="text-base font-semibold text-slate-900 dark:text-white">{{ independentExaminer.report ? 'Change examiner' : 'Ask an examiner to sign' }}</h4>
            <p v-if="!independentExaminer.examiners.length" class="text-sm text-slate-500 dark:text-slate-400">Invite your examiner to the lodge first and give them the "Examiner / Auditor" role, then choose them here.</p>
            <template v-else>
              <div class="space-y-1.5">
                <label for="examiner-user-id" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Examiner</label>
                <select id="examiner-user-id" v-model="examinerForm.examiner_user_id" :class="fieldClass">
                  <option value="" disabled>Choose examiner…</option>
                  <option v-for="e in independentExaminer.examiners" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <p v-if="examinerForm.errors.examiner_user_id" class="text-xs text-rose-600 dark:text-rose-400">{{ examinerForm.errors.examiner_user_id }}</p>
              </div>
              <Textarea v-model="examinerForm.notes" :rows="2" label="Notes" placeholder="Notes for the report (optional)" />
              <Button type="submit" :disabled="!examinerForm.examiner_user_id || examinerForm.processing">Send signature request</Button>
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

        <Modal :open="!!showAuditConfirmModal" title="Send signature requests?" @close="showAuditConfirmModal = false">
          <div class="space-y-4">
            <p class="text-sm text-slate-500 dark:text-slate-400">Each person below will be emailed a private link to review and sign the {{ annualTreasurerReport.financial_year }} accounts themselves.</p>
            <div class="space-y-2">
              <div v-for="m in auditRecipients" :key="m.id" class="flex items-center justify-between gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 rounded-xl px-3 py-2.5">
                <span class="font-medium text-slate-900 dark:text-white">{{ m.name }}</span>
                <span class="text-slate-500 dark:text-slate-400">{{ m.email }}</span>
              </div>
            </div>
          </div>
          <template #footer>
            <Button variant="secondary" @click="showAuditConfirmModal = false">Cancel</Button>
            <Button :disabled="signOffForm.processing" @click="confirmSignOff">Send signature requests</Button>
          </template>
        </Modal>

        <!-- Report: Recurring Vendor Bills -->
        <RecurringBillsReport v-if="selectedReport === 'recurring_bills'" :bills="bills" :club="club" :delete-recurring-bill="deleteRecurringBill" :recurring-bill-templates="recurringBillTemplates" :toggle-recurring-bill="toggleRecurringBill" />
      </div>
    </Card>
</template>
