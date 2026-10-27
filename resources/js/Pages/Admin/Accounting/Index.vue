<script setup>
import StatementLineDetailsModal from '@/Components/Accounting/Modals/StatementLineDetailsModal.vue';
import AttachmentPreviewModal from '@/Components/Accounting/Modals/AttachmentPreviewModal.vue';
import WhatsThisModal from '@/Components/Accounting/Modals/WhatsThisModal.vue';
import DifferentBalancesModal from '@/Components/Accounting/Modals/DifferentBalancesModal.vue';
import ManualEntryModal from '@/Components/Accounting/Modals/ManualEntryModal.vue';
import ImportStatementModal from '@/Components/Accounting/Modals/ImportStatementModal.vue';
import OpeningBalanceModal from '@/Components/Accounting/Modals/OpeningBalanceModal.vue';
import AccountModal from '@/Components/Accounting/Modals/AccountModal.vue';
import GatewaySyncModal from '@/Components/Accounting/Modals/GatewaySyncModal.vue';
import GoCardlessModal from '@/Components/Accounting/Modals/GoCardlessModal.vue';
import SumUpModal from '@/Components/Accounting/Modals/SumUpModal.vue';
import StripeModal from '@/Components/Accounting/Modals/StripeModal.vue';
import PayPalModal from '@/Components/Accounting/Modals/PayPalModal.vue';
import BankAccountModal from '@/Components/Accounting/Modals/BankAccountModal.vue';
import ChartOfAccountsTab from '@/Components/Accounting/Tabs/ChartOfAccountsTab.vue';
import AccountingNavBar from '@/Components/Accounting/Tabs/AccountingNavBar.vue';
import ContactsTab from '@/Components/Accounting/Tabs/ContactsTab.vue';
import ReconciliationTab from '@/Components/Accounting/Tabs/ReconciliationTab.vue';
import HomeTab from '@/Components/Accounting/Tabs/HomeTab.vue';
import BankAccountsTab from '@/Components/Accounting/Tabs/BankAccountsTab.vue';
import ReportingTab from '@/Components/Accounting/Tabs/ReportingTab.vue';
import PurchasesTab from '@/Components/Accounting/Sections/PurchasesTab.vue';
import SalesTab from '@/Components/Accounting/Sections/SalesTab.vue';
import AccountingSettingsPanel from '@/Components/Accounting/Sections/AccountingSettingsPanel.vue';
import { financialYearLabel } from '@/Utils/financialYear';
import { reportDateRange } from '@/Utils/reportDateRange';
import { accountingPath, accountingStateFromLocation, EXPORTABLE_REPORTS as exportableReports, PDF_EXPORTABLE_REPORTS as pdfExportableReports } from '@/Utils/accountingUrl';
import { useAccountingSettingsForms } from '@/Composables/useAccountingSettingsForms';
import { useLedgerSetupForms } from '@/Composables/useLedgerSetupForms';
import { useAccountingContacts } from '@/Composables/useAccountingContacts';
import { useLedgerDocumentActions } from '@/Composables/useLedgerDocumentActions';
import { useReconciliationRows } from '@/Composables/useReconciliationRows';
import { useStatementLines } from '@/Composables/useStatementLines';
import { useAttachmentViewer } from '@/Composables/useAttachmentViewer';
import { useBankConnections } from '@/Composables/useBankConnections';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

import { currencyCode, formatMoney } from '@/Utils/currency';
const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  accounts: {
    type: Array,
    default: () => [],
  },
  bankAccounts: {
    type: Array,
    default: () => [],
  },
  journalEntries: {
    type: Array,
    default: () => [],
  },
  invoices: {
    type: Array,
    default: () => [],
  },
  bills: {
    type: Array,
    default: () => [],
  },
  members: {
    type: Array,
    default: () => [],
  },
  contacts: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: Object,
    default: () => ({
      total_assets: 0,
      total_liabilities: 0,
      total_equity: 0,
      total_revenue: 0,
      total_expenses: 0,
      net_income: 0,
      unpaid_bills_total: 0,
      unpaid_invoices_total: 0,
    }),
  },
  reports: {
    type: Object,
    default: () => ({
      account_summary: [],
      aged_payables: { current: 0, '1_30': 0, '31_60': 0, '61_90': 0, '90_plus': 0, total: 0, items: [] },
      aged_receivables: { current: 0, '1_30': 0, '31_60': 0, '61_90': 0, '90_plus': 0, total: 0, items: [] },
      balance_sheet: { assets: [], liabilities: [], equity: [], total_assets: 0, total_liabilities: 0, total_equity: 0 },
      cash_summary: { total_cash_on_hand: 0, accounts: [] },
      executive_summary: { net_profit_margin_pct: 0, operating_expense_ratio_pct: 0, total_cash_reserves: 0, outstanding_ar: 0, outstanding_ap: 0 },
      profit_and_loss: { revenues: [], expenses: [], total_revenue: 0, total_expenses: 0, net_income: 0 },
    }),
  },
  settings: {
    type: Object,
    default: () => ({
      company_name: '',
      tax_registration_number: '',
      contact_email: '',
      phone: '',
      address_line_1: '',
      address_line_2: '',
      city: '',
      county: '',
      postcode: '',
      country: 'United Kingdom',
      currency: currencyCode(),
      receipt_footer_notes: '',
      dues_grace_period_days: 14,
      auto_invoice_days_before: 7,
    }),
  },
  vatSettings: {
    type: Object,
    default: () => ({
      enabled: false,
      scheme: 'not_registered',
      vat_number: '',
      flat_rate_percent: null,
      default_rate: 20.00,
      registered_from: null,
    }),
  },
  vatLocked: {
    type: Boolean,
    default: false,
  },
  approvalThreshold: {
    type: Object,
    default: () => ({ enabled: false, amount: null }),
  },
  charityCommission: {
    type: Object,
    default: () => ({ registered: false, charity_number: null }),
  },
  independentExaminer: {
    type: Object,
    default: () => ({ financial_year: new Date().getFullYear(), examiners: [], report: null }),
  },
  fixedAssets: {
    type: Array,
    default: () => [],
  },
  budgetVsActual: {
    type: Object,
    default: () => ({ financial_year: new Date().getFullYear(), rows: [], total_budgeted: 0, total_actual: 0 }),
  },
  annualTreasurerReport: {
    type: Object,
    default: () => ({
      financial_year: new Date().getFullYear(),
      is_closed: false,
      general_fund: { rows: [], total_income: 0, total_expenditure: 0, net_surplus: 0 },
      charity_fund: { collections_total: 0, grants_disbursed_total: 0 },
      bank_accounts: [],
      membership: { in_arrears_count: 0, in_arrears: [] },
      vat: null,
      audit_sign_off: null,
    }),
  },
  recurringBillTemplates: {
    type: Array,
    default: () => [],
  },
  onboarding: {
    type: Object,
    default: () => ({ dismissed: false, steps: [] }),
  },
  canManageBilling: {
    type: Boolean,
    default: true,
  },
  canExportAllData: {
    type: Boolean,
    default: false,
  },
  initialTab: {
    type: String,
    default: null,
  },
  initialReport: {
    type: String,
    default: null,
  },
  reconciliation: {
    type: Object,
    default: () => ({
      unmatched_transactions: [],
      reconciled_transactions: [],
      bank_imports: [],
      unpaid_subscriptions: [],
      unpaid_bills: [],
      gift_aid_summary: {
        total_donations: 0,
        claimable_gift_aid: 0,
        unclaimed_gift_aid: 0,
        claimed_gift_aid: 0,
        reconciled_gift_aid: 0,
        pending_bank_reconciliation_count: 0,
        total_eligible_count: 0,
        formatted_total_donations: formatMoney(0),
        formatted_claimable_gift_aid: formatMoney(0),
        formatted_unclaimed_gift_aid: formatMoney(0),
        formatted_claimed_gift_aid: formatMoney(0),
        formatted_reconciled_gift_aid: formatMoney(0),
      },
    }),
  },
});

const parseUrlState = () => {
  if (typeof window === 'undefined') {
    return { tab: props.initialTab || 'home', report: props.initialReport || null };
  }

  return accountingStateFromLocation(window.location, props.club.slug, { tab: props.initialTab, report: props.initialReport });
};

const initialState = parseUrlState();
const activeTab = ref(initialState.tab);
const selectedReport = ref(initialState.report);

const syncTabWithUrl = () => {
  const { tab, report } = parseUrlState();
  activeTab.value = tab;
  selectedReport.value = report;
};

const selectedBankAccountId = ref(null);
const showConnectBankDropdown = ref(false);
const rowStates = ref({});
const activeOptionsTxId = ref(null);
const showManageAccountMenu = ref(false);
const showAddMenu = ref(false);
const showAccountingDropdown = ref(false);
const showStatementLineDetailsModal = ref(false);

watch(() => props.bankAccounts, (accs) => {
  if (accs && accs.length && !selectedBankAccountId.value) {
    selectedBankAccountId.value = accs[0].id;
  }
}, { immediate: true });

const activeBankAccount = computed(() => {
  if (!props.bankAccounts || !props.bankAccounts.length) return null;
  return props.bankAccounts.find(a => a.id === selectedBankAccountId.value) || props.bankAccounts[0];
});

const filteredAccountTransactions = computed(() => {
  const list = props.reconciliation?.account_transactions || [];
  if (!activeBankAccount.value) return list;
  return list.filter(tx => !tx.bank_account_id || tx.bank_account_id === activeBankAccount.value.id);
});

// Real bank-reconciliation-summary figures for the active bank account, built from
// its actual ledger/statement balances and its actual unmatched transaction lines —
// never placeholder data.
const reconciliationSummary = computed(() => {
  const account = activeBankAccount.value;
  const allUnmatched = props.reconciliation?.unmatched_transactions || [];
  const unmatched = account
    ? allUnmatched.filter(tx => !tx.bank_account_id || tx.bank_account_id === account.id)
    : allUnmatched;

  const outstandingReceipts = unmatched.filter(tx => tx.amount > 0);
  const outstandingPayments = unmatched.filter(tx => tx.amount < 0);
  const receiptsTotal = outstandingReceipts.reduce((sum, tx) => sum + tx.amount, 0);
  const paymentsTotal = outstandingPayments.reduce((sum, tx) => sum + Math.abs(tx.amount), 0);

  const ledgerBalance = account ? account.ledger_balance : 0;
  const statementBalance = account ? account.statement_balance : 0;
  const calculatedStatementBalance = ledgerBalance + receiptsTotal - paymentsTotal;
  const discrepancy = statementBalance - calculatedStatementBalance;

  return {
    accountLabel: account ? `${account.bank_name} (${account.account_name})` : 'No bank account connected',
    ledgerBalance,
    statementBalance,
    calculatedStatementBalance,
    discrepancy,
    outstandingReceipts,
    receiptsTotal,
    outstandingPayments,
    paymentsTotal,
  };
});

const navigateTo = (tabName, reportName = null, bankAccountId = null) => {
  activeTab.value = tabName;
  selectedReport.value = reportName;
  if (bankAccountId) {
    selectedBankAccountId.value = bankAccountId;
  }

  if (typeof window !== 'undefined') {
    const targetUrl = accountingPath(props.club.slug, tabName, reportName);
    if (window.location.pathname !== targetUrl) {
      history.pushState({ tab: tabName, report: reportName }, '', targetUrl);
    }
  }
};

// ── Guided First-Run Setup Checklist ─────────────────────────────────────────
const onboardingDismissedLocally = ref(false);
const showOnboarding = computed(() => {
  if (props.onboarding.dismissed || onboardingDismissedLocally.value) return false;
  return props.onboarding.steps.some((step) => !step.done && !step.skippable);
});

const dismissOnboarding = () => {
  onboardingDismissedLocally.value = true;
  router.post(route('admin.accounting.onboarding.dismiss', props.club.slug), {}, { preserveState: true, preserveScroll: true });
};

const activeDetailsTxId = ref(null);

const toggleDetailsPopover = (txId) => {
  activeDetailsTxId.value = activeDetailsTxId.value === txId ? null : txId;
};

const handleGlobalDocumentClick = (e) => {
  if (!e.target.closest('.who-dropdown-container')) {
    Object.values(rowStates.value).forEach(state => {
      if (state) state.showWhoDropdown = false;
    });
  }
  if (!e.target.closest('.statement-details-container')) {
    activeDetailsTxId.value = null;
  }
  if (!e.target.closest('.statement-options-container')) {
    activeOptionsTxId.value = null;
  }
  if (!e.target.closest('.manage-account-container')) {
    showManageAccountMenu.value = false;
  }
  if (!e.target.closest('.add-menu-container')) {
    showAddMenu.value = false;
  }
  if (!e.target.closest('.accounting-dropdown-container')) {
    showAccountingDropdown.value = false;
  }
  if (!e.target.closest('.connect-bank-dropdown-container')) {
    showConnectBankDropdown.value = false;
  }
};

const handleGlobalKeyDown = (e) => {
  if (e.key === 'Escape') {
    activeDetailsTxId.value = null;
    activeOptionsTxId.value = null;
    showManageAccountMenu.value = false;
    showAddMenu.value = false;
    showStatementLineDetailsModal.value = false;
  }
};

onMounted(() => {
  syncTabWithUrl();
  if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', syncTabWithUrl);
    window.addEventListener('popstate', syncTabWithUrl);
    document.addEventListener('click', handleGlobalDocumentClick);
    document.addEventListener('keydown', handleGlobalKeyDown);
  }
  if (props.reconciliation?.unmatched_transactions?.length > 0) {
    selectedTx.value = props.reconciliation.unmatched_transactions[0];
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('hashchange', syncTabWithUrl);
    window.removeEventListener('popstate', syncTabWithUrl);
    document.removeEventListener('click', handleGlobalDocumentClick);
    document.removeEventListener('keydown', handleGlobalKeyDown);
  }
});

const initialRange = reportDateRange('this_month');
const reportStartDate = ref(initialRange.start);
const reportEndDate = ref(initialRange.end);
const reportQuickRange = ref('this_month');

const setQuickRange = (preset) => {
  reportQuickRange.value = preset;
  const range = reportDateRange(preset);
  if (range) {
    reportStartDate.value = range.start;
    reportEndDate.value = range.end;
  }
};

const showAccountModal = ref(false);
const showImportModal = ref(false);
const showManualModal = ref(false);
const showDifferentBalancesModal = ref(false);
const showWhatsThisModal = ref(false);

const selectedTx = ref(null);
const reconSearch = ref('');
const manualMatchType = ref('member_subscription');
const manualTargetId = ref('');
const manualNominalCode = ref('4000');

const number_format = (val, decimals = 2) => {
  const num = parseFloat(val) || 0;
  return num.toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
};

const reconcileForm = useForm({
  transaction_id: null,
  match_type: '',
  target_id: '',
  nominal_code: 'GENERAL',
});

const { showOpeningBalanceModal, openingBalanceForm, openOpeningBalanceModal, submitOpeningBalance, accountForm, submitAccount, importForm, submitStatementImport } = useLedgerSetupForms(props, { showAccountModal, showImportModal });

const reconSubTab = ref('reconcile');
const compactView = ref(false);
const showFilterPanel = ref(false);

// Gift Aid & Relief Chest Detailed Ledger Filters
const giftAidFilterDateFrom = ref('');
const giftAidFilterDateTo = ref('');
const giftAidFilterPerson = ref('');
const giftAidFilterStatus = ref('all');
const giftAidFilterSearch = ref('');

const filteredReconciledDonations = computed(() => {
  const list = props.reconciliation?.reconciled_donations?.collections || [];
  return list.filter(item => {
    if (giftAidFilterDateFrom.value && item.raw_date < giftAidFilterDateFrom.value) {
      return false;
    }
    if (giftAidFilterDateTo.value && item.raw_date > giftAidFilterDateTo.value) {
      return false;
    }
    if (giftAidFilterPerson.value) {
      const p = giftAidFilterPerson.value.toString().toLowerCase();
      const matchId = item.donor_id && item.donor_id.toString() === p;
      const matchName = item.donor_name && item.donor_name.toLowerCase().includes(p);
      const matchCounted = item.counted_by && item.counted_by.toLowerCase().includes(p);
      if (!matchId && !matchName && !matchCounted) return false;
    }
    if (giftAidFilterStatus.value !== 'all') {
      if (giftAidFilterStatus.value === 'reconciled' && item.gift_aid_status !== 'reconciled') return false;
      if (giftAidFilterStatus.value === 'claimed' && item.gift_aid_status !== 'claimed') return false;
      if (giftAidFilterStatus.value === 'pending' && item.gift_aid_status === 'reconciled') return false;
    }
    if (giftAidFilterSearch.value) {
      const s = giftAidFilterSearch.value.toLowerCase();
      const matchType = (item.collection_type || '').toLowerCase().includes(s);
      const matchDonor = (item.donor_name || '').toLowerCase().includes(s);
      const matchNotes = (item.notes || '').toLowerCase().includes(s);
      const matchBank = item.bank_transaction ? ((item.bank_transaction.raw_description || '') + ' ' + (item.bank_transaction.reference || '')).toLowerCase().includes(s) : false;
      if (!matchType && !matchDonor && !matchNotes && !matchBank) return false;
    }
    return true;
  });
});

const filteredReconciledGiftAidTotal = computed(() => {
  return filteredReconciledDonations.value.reduce((acc, item) => acc + (item.gift_aid_amount || 0), 0);
});

const resetGiftAidFilters = () => {
  giftAidFilterDateFrom.value = '';
  giftAidFilterDateTo.value = '';
  giftAidFilterPerson.value = '';
  giftAidFilterStatus.value = 'all';
  giftAidFilterSearch.value = '';
};
const toggleAccountingDropdown = (e) => {
  if (e) e.stopPropagation();
  showAccountingDropdown.value = !showAccountingDropdown.value;
};
const { showBankAccountModal, showPayPalModal, showPayPalSecret, bankAccountForm, payPalForm, showAccountTypeWarning, detectedMismatch, submitBankAccount, applySuggestedTypeAndSubmit, proceedWithCurrentTypeAnyway, submitPayPalAccount, showStripeModal, showStripeSecret, stripeForm, submitStripeAccount, showSumUpModal, showSumUpKey, sumUpForm, submitSumUpAccount, showGoCardlessModal, showGoCardlessToken, showGoCardlessSecret, goCardlessForm, submitGoCardlessAccount, showGatewaySyncModal, gatewaySyncType, gatewaySyncForm, submitGatewaySyncModal, syncPayPalNow, syncStripeNow, syncSumUpNow, syncGoCardlessNow, toggleBankAccountActive } = useBankConnections(props.club);
const selectedCashCodingTx = ref([]);
const { selectedStatementLineIds, statementLinesFilter, filteredStatementLines, deletableStatementLines, restorableStatementLines, toggleStatementLinesSelectAll, deleteSelectedStatementLines, restoreSelectedStatementLines, toggleOptionsDropdown, deleteSingleStatementLine } = useStatementLines(props, { activeBankAccount, activeOptionsTxId });

const selectedStatementLineDetails = ref(null);


const filterType = ref('all');
const filterMinAmount = ref('');
const filterMaxAmount = ref('');

const filteredUnmatchedTx = computed(() => {
  let list = props.reconciliation?.unmatched_transactions || [];
  if (activeBankAccount.value) {
    list = list.filter(t => !t.bank_account_id || t.bank_account_id === activeBankAccount.value.id);
  }
  const q = reconSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(t =>
      t.raw_description?.toLowerCase().includes(q) ||
      t.reference?.toLowerCase().includes(q) ||
      t.formatted_amount?.includes(q) ||
      t.contact_name?.toLowerCase().includes(q)
    );
  }
  if (filterType.value === 'spent') {
    list = list.filter(t => t.amount < 0);
  } else if (filterType.value === 'received') {
    list = list.filter(t => t.amount > 0);
  }
  if (filterMinAmount.value !== '') {
    const min = parseFloat(filterMinAmount.value);
    if (!isNaN(min)) {
      list = list.filter(t => Math.abs(t.amount) >= min);
    }
  }
  if (filterMaxAmount.value !== '') {
    const max = parseFloat(filterMaxAmount.value);
    if (!isNaN(max)) {
      list = list.filter(t => Math.abs(t.amount) <= max);
    }
  }
  return list;
});

const toggleCashCodingSelectAll = () => {
  if (selectedCashCodingTx.value.length === filteredUnmatchedTx.value.length) {
    selectedCashCodingTx.value = [];
  } else {
    selectedCashCodingTx.value = filteredUnmatchedTx.value.map(t => t.id);
  }
};

// Reconciles the ticked lines one after another, each to the account chosen on its own row. Nothing is sent until every
// ticked line has an account, so a half-coded batch is never posted.
const submitBulkCashCoding = () => {
  const rows = filteredUnmatchedTx.value.filter((t) => selectedCashCodingTx.value.includes(t.id));
  if (rows.length === 0) return;

  const uncoded = rows.filter((t) => !getRowState(t.id, t).what);
  uncoded.forEach((t) => { getRowState(t.id, t).showWhatError = true; });
  if (uncoded.length > 0) return;

  const queue = [...rows];
  const next = () => {
    const tx = queue.shift();
    if (!tx) {
      selectedCashCodingTx.value = [];
      return;
    }
    reconcileForm.transaction_id = tx.id;
    reconcileForm.match_type = 'ledger_account';
    reconcileForm.target_id = 0;
    reconcileForm.nominal_code = getRowState(tx.id, tx).what;
    reconcileForm.post(route('admin.accounting.reconcile', props.club.slug), {
      preserveScroll: true,
      onSuccess: next,
    });
  };
  next();
};

const { submitReconcile, getContactSuggestions, getRowState, closeWhoDropdownSoon, setRowTab, openManualMatchModalForTx, submitRowReconcile } = useReconciliationRows(props, { rowStates, selectedTx, reconcileForm, showManualModal, manualMatchType, manualTargetId, manualNominalCode, contacts: () => filteredContacts.value });


const { contactFilter, filteredContacts } = useAccountingContacts(props);


// VAT Settings Form
const { vatSettingsForm, submitVatSettings, financialYearEndForm, submitFinancialYearEnd, charityCommissionForm, submitCharityCommission, examinerForm, submitExaminerRequest, resendExaminerSignature, changeExaminerYear, approvalThresholdForm, submitApprovalThreshold, depreciationForm, runDepreciation, budgetForm, budgetableAccounts, changeBudgetYear, submitBudget, changeTreasurerReportYear, closeYearForm, submitCloseYear, signOffForm, showAuditConfirmModal, auditorOneSignature, auditorTwoSignature, auditorOneLocked, auditorTwoLocked, bothAuditorsLocked, auditRecipients, submitSignOff, confirmSignOff, resendAuditSignature, cancelAuditSignature, toggleRecurringBill, deleteRecurringBill } = useAccountingSettingsForms(props);

// New Account Form


const { publishInvoice, publishBill, openEditInvoiceModal, openEditBillModal, activityModal, openActivityLog, markInvoicePaid, markBillPaid, forgetMatchRule, approveInvoice, approveBill } = useLedgerDocumentActions(props.club);


// ── Attachment Preview Modal State & Controls ────────────────────────────────
const { previewModal, zoomLevel, rotation, openAttachmentModal, zoomIn, zoomOut, resetZoom, rotateImage, deleteAttachment } = useAttachmentViewer(props.club);


// ── Sales & Purchases Filters ────────────────────────────────────────────────


/** Parse a date string like "12 Sep 2026" → Date */
const parseDate = (str) => {
  if (!str) return null;
  return new Date(str);
};

/** The financial year a date falls in, by the month this club's year ends (Settings), e.g. "2025-26". */
const getFyLabel = (dateStr) => financialYearLabel(dateStr, props.settings?.financial_year_end_month);

/** All calendar years present in invoices */
const salesYears = computed(() => {
  const years = [...new Set((props.invoices || []).map(i => parseDate(i.created_at)?.getFullYear()).filter(Boolean))];
  return years.sort((a, b) => b - a);
});

const billsYears = computed(() => {
  const years = [...new Set((props.bills || []).map(b => parseDate(b.due_date)?.getFullYear()).filter(Boolean))];
  return years.sort((a, b) => b - a);
});

/** All financial years present in invoices */
const salesFyYears = computed(() => {
  const fys = [...new Set((props.invoices || []).map(i => getFyLabel(i.created_at)).filter(Boolean))];
  return fys.sort((a, b) => b.localeCompare(a));
});

const billsFyYears = computed(() => {
  const fys = [...new Set((props.bills || []).map(b => getFyLabel(b.due_date)).filter(Boolean))];
  return fys.sort((a, b) => b.localeCompare(a));
});

/** Filtered invoices */
/** Filtered bills */
// ────────────────────────────────────────────────────────────────────────────
const getTypeBadge = (type) => {
  switch (type) {
    case 'asset': return 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60';
    case 'liability': return 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/60';
    case 'equity': return 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60';
    case 'revenue': return 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/60';
    case 'expense': return 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800/60';
    default: return 'bg-slate-50 dark:bg-slate-800/50 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-800';
  }
};
</script>

<template>
  <AdminLayout :club="club" title="Accounting" active-tab="accounting">
    <Head :title="`Accounting - ${club.name}`" />

    <div class="space-y-6">
      
      <!-- Sleek Slate Navigation Bar at Top -->
      <AccountingNavBar :active-tab="activeTab" :can-manage-billing="canManageBilling" :club="club" :navigate-to="navigateTo" :open-opening-balance-modal="openOpeningBalanceModal" :reconciliation="reconciliation" :toggle-accounting-dropdown="toggleAccountingDropdown" v-model:show-accounting-dropdown="showAccountingDropdown" v-model:show-add-menu="showAddMenu" />

      <!-- Read-Only Access Notice (examiner role) -->
      <div v-if="!canManageBilling" class="bg-purple-50 dark:bg-purple-950/30 rounded-2xl border border-purple-200 dark:border-purple-800/60 px-4 py-3 flex items-center gap-2.5 text-xs">
        <span class="text-base">🔎</span>
        <span class="font-semibold text-purple-800 dark:text-purple-200">You have read-only access to this club's accounting records. Actions that change the books are hidden.</span>
      </div>

      <HomeTab v-if="activeTab === 'home'" :dismiss-onboarding="dismissOnboarding" :navigate-to="navigateTo" :onboarding="onboarding" :reconciliation="reconciliation" :show-onboarding="showOnboarding" :summary="summary" />


      <!-- VIEW 2: SALES (Member Invoices / Accounts Receivable) -->
      <SalesTab v-if="activeTab === 'sales'" :approve-invoice="approveInvoice" :can-manage-billing="canManageBilling" :club="club" :get-fy-label="getFyLabel" :invoices="invoices" :mark-invoice-paid="markInvoicePaid" :open-activity-log="openActivityLog" :open-attachment-modal="openAttachmentModal" :open-edit-invoice-modal="openEditInvoiceModal" :parse-date="parseDate" :publish-invoice="publishInvoice" :sales-fy-years="salesFyYears" :sales-years="salesYears" />

      <!-- VIEW 3: PURCHASES (Vendor Bills / Accounts Payable) -->
      <PurchasesTab v-if="activeTab === 'purchases'" :accounts="accounts" :approve-bill="approveBill" :bills="bills" :bills-fy-years="billsFyYears" :bills-years="billsYears" :can-manage-billing="canManageBilling" :club="club" :get-fy-label="getFyLabel" :mark-bill-paid="markBillPaid" :open-activity-log="openActivityLog" :open-attachment-modal="openAttachmentModal" :open-edit-bill-modal="openEditBillModal" :parse-date="parseDate" :publish-bill="publishBill" />

      <!-- VIEW 4: REPORTING (Financial Statement Reports) -->
      <ReportingTab v-if="activeTab === 'reporting'" :accounts="accounts" :annual-treasurer-report="annualTreasurerReport" :audit-recipients="auditRecipients" :auditor-one-locked="auditorOneLocked" :auditor-one-signature="auditorOneSignature" :auditor-two-locked="auditorTwoLocked" :auditor-two-signature="auditorTwoSignature" :bills="bills" :both-auditors-locked="bothAuditorsLocked" :budget-form="budgetForm" :budget-vs-actual="budgetVsActual" :budgetable-accounts="budgetableAccounts" :can-manage-billing="canManageBilling" :cancel-audit-signature="cancelAuditSignature" :change-budget-year="changeBudgetYear" :change-examiner-year="changeExaminerYear" :change-treasurer-report-year="changeTreasurerReportYear" :charity-commission="charityCommission" :close-year-form="closeYearForm" :club="club" :confirm-sign-off="confirmSignOff" :delete-recurring-bill="deleteRecurringBill" :depreciation-form="depreciationForm" :examiner-form="examinerForm" :exportable-reports="exportableReports" :fixed-assets="fixedAssets" :get-type-badge="getTypeBadge" :independent-examiner="independentExaminer" :invoices="invoices" :members="members" :navigate-to="navigateTo" :open-activity-log="openActivityLog" :pdf-exportable-reports="pdfExportableReports" :reconciliation="reconciliation" :reconciliation-summary="reconciliationSummary" :recurring-bill-templates="recurringBillTemplates" v-model:report-end-date="reportEndDate" v-model:report-quick-range="reportQuickRange" v-model:report-start-date="reportStartDate" :reports="reports" :resend-audit-signature="resendAuditSignature" :resend-examiner-signature="resendExaminerSignature" :run-depreciation="runDepreciation" :selected-report="selectedReport" :set-quick-range="setQuickRange" v-model:show-audit-confirm-modal="showAuditConfirmModal" :sign-off-form="signOffForm" :submit-budget="submitBudget" :submit-close-year="submitCloseYear" :submit-examiner-request="submitExaminerRequest" :submit-sign-off="submitSignOff" :summary="summary" :toggle-recurring-bill="toggleRecurringBill" :vat-settings="vatSettings" />

      <!-- VIEW 6: ACCOUNTING — BANK & PAYMENT ACCOUNTS HUB DASHBOARD & GENERAL LEDGER -->
      <BankAccountsTab v-if="activeTab === 'accounting' || activeTab === 'bank-accounts'" :bank-accounts="bankAccounts" :can-manage-billing="canManageBilling" :club="club" :journal-entries="journalEntries" :navigate-to="navigateTo" :reconciliation="reconciliation" v-model:show-bank-account-modal="showBankAccountModal" v-model:show-connect-bank-dropdown="showConnectBankDropdown" v-model:show-go-cardless-modal="showGoCardlessModal" v-model:show-pay-pal-modal="showPayPalModal" v-model:show-stripe-modal="showStripeModal" v-model:show-sum-up-modal="showSumUpModal" :sync-go-cardless-now="syncGoCardlessNow" :sync-pay-pal-now="syncPayPalNow" :sync-stripe-now="syncStripeNow" :sync-sum-up-now="syncSumUpNow" :toggle-bank-account-active="toggleBankAccountActive" />

      <!-- VIEW 7: CHART OF ACCOUNTS (Dedicated View & Nominal Ledger) -->
      <ChartOfAccountsTab v-if="activeTab === 'chart-of-accounts'" :accounts="accounts" :get-type-badge="getTypeBadge" :open-opening-balance-modal="openOpeningBalanceModal" v-model:show-account-modal="showAccountModal" />


      <!-- VIEW 8: CONTACTS (Members, Vendors & Business Directory) -->
      <ContactsTab v-if="activeTab === 'contacts'" :can-manage-billing="canManageBilling" :club="club" v-model:contact-filter="contactFilter" :contacts="contacts" :filtered-contacts="filteredContacts" :members="members" />

      <!-- VIEW 8: BANK RECONCILIATION WORKSPACE (XERO COMPLETE DESIGN) -->
      <ReconciliationTab v-if="activeTab === 'reconciliation' && canManageBilling" :accounts="accounts" :active-bank-account="activeBankAccount" :active-options-tx-id="activeOptionsTxId" :bank-accounts="bankAccounts" :bills="bills" :can-manage-billing="canManageBilling" :close-who-dropdown-soon="closeWhoDropdownSoon" :club="club" :compact-view="compactView" :contacts="contacts" :deletable-statement-lines="deletableStatementLines" :delete-selected-statement-lines="deleteSelectedStatementLines" :delete-single-statement-line="deleteSingleStatementLine" :filtered-account-transactions="filteredAccountTransactions" :filtered-reconciled-donations="filteredReconciledDonations" :filtered-reconciled-gift-aid-total="filteredReconciledGiftAidTotal" :filtered-statement-lines="filteredStatementLines" :filtered-unmatched-tx="filteredUnmatchedTx" :forget-match-rule="forgetMatchRule" :get-contact-suggestions="getContactSuggestions" :get-row-state="getRowState" :invoices="invoices" :navigate-to="navigateTo" :number-format="number_format" :open-manual-match-modal-for-tx="openManualMatchModalForTx" :reconciliation="reconciliation" :reset-gift-aid-filters="resetGiftAidFilters" :restorable-statement-lines="restorableStatementLines" :restore-selected-statement-lines="restoreSelectedStatementLines" :set-row-tab="setRowTab" :submit-bulk-cash-coding="submitBulkCashCoding" :submit-reconcile="submitReconcile" :submit-row-reconcile="submitRowReconcile" :summary="summary" :toggle-cash-coding-select-all="toggleCashCodingSelectAll" :toggle-details-popover="toggleDetailsPopover" :toggle-options-dropdown="toggleOptionsDropdown" :toggle-statement-lines-select-all="toggleStatementLinesSelectAll" v-model:active-details-tx-id="activeDetailsTxId" v-model:filter-max-amount="filterMaxAmount" v-model:filter-min-amount="filterMinAmount" v-model:filter-type="filterType" v-model:gift-aid-filter-date-from="giftAidFilterDateFrom" v-model:gift-aid-filter-date-to="giftAidFilterDateTo" v-model:gift-aid-filter-person="giftAidFilterPerson" v-model:gift-aid-filter-search="giftAidFilterSearch" v-model:gift-aid-filter-status="giftAidFilterStatus" v-model:recon-search="reconSearch" v-model:recon-sub-tab="reconSubTab" v-model:selected-bank-account-id="selectedBankAccountId" v-model:selected-cash-coding-tx="selectedCashCodingTx" v-model:selected-statement-line-ids="selectedStatementLineIds" v-model:show-different-balances-modal="showDifferentBalancesModal" v-model:show-filter-panel="showFilterPanel" v-model:show-import-modal="showImportModal" v-model:show-manage-account-menu="showManageAccountMenu" v-model:show-whats-this-modal="showWhatsThisModal" v-model:statement-lines-filter="statementLinesFilter" />


      <!-- VIEW 9: SETTINGS (Financial & Organization Settings) -->
      <AccountingSettingsPanel v-if="activeTab === 'settings'" :approval-threshold-form="approvalThresholdForm" :charity-commission-form="charityCommissionForm" :financial-year-end-form="financialYearEndForm" :number-format="number_format" :submit-approval-threshold="submitApprovalThreshold" :submit-charity-commission="submitCharityCommission" :submit-financial-year-end="submitFinancialYearEnd" :submit-vat-settings="submitVatSettings" :vat-settings-form="vatSettingsForm" :approval-threshold="approvalThreshold" :bills="bills" :can-export-all-data="canExportAllData" :can-manage-billing="canManageBilling" :charity-commission="charityCommission" :club="club" :invoices="invoices" :settings="settings" :vat-locked="vatLocked" />

    </div>

    <!-- Modal 0: Add Bank / Payment Account -->
    <BankAccountModal v-if="showBankAccountModal" :apply-suggested-type-and-submit="applySuggestedTypeAndSubmit" :bank-account-form="bankAccountForm" :detected-mismatch="detectedMismatch" :proceed-with-current-type-anyway="proceedWithCurrentTypeAnyway" :show-account-type-warning="showAccountTypeWarning" :submit-bank-account="submitBankAccount" @close="showBankAccountModal = false" />

    <!-- Modal 0.5: Dedicated PayPal Setup & API Settings -->
    <PayPalModal v-if="showPayPalModal" :pay-pal-form="payPalForm" v-model:show-pay-pal-secret="showPayPalSecret" :submit-pay-pal-account="submitPayPalAccount" @close="showPayPalModal = false" />

    <!-- Modal 0.55: Dedicated Stripe API Setup -->
    <StripeModal v-if="showStripeModal" v-model:show-stripe-secret="showStripeSecret" :stripe-form="stripeForm" :submit-stripe-account="submitStripeAccount" @close="showStripeModal = false" />

    <!-- Modal 0.58: Dedicated SumUp API Setup -->
    <SumUpModal v-if="showSumUpModal" v-model:show-sum-up-key="showSumUpKey" :submit-sum-up-account="submitSumUpAccount" :sum-up-form="sumUpForm" @close="showSumUpModal = false" />

    <!-- Modal 0.59: Dedicated GoCardless API Setup -->
    <GoCardlessModal v-if="showGoCardlessModal" :go-cardless-form="goCardlessForm" v-model:show-go-cardless-secret="showGoCardlessSecret" v-model:show-go-cardless-token="showGoCardlessToken" :submit-go-cardless-account="submitGoCardlessAccount" @close="showGoCardlessModal = false" />

    <!-- Modal 0.6: Custom Date Range Gateway API Sync -->
    <GatewaySyncModal v-if="showGatewaySyncModal" :gateway-sync-form="gatewaySyncForm" :gateway-sync-type="gatewaySyncType" :submit-gateway-sync-modal="submitGatewaySyncModal" @close="showGatewaySyncModal = false" />

    <!-- Modal 1: Add Account to Chart of Accounts -->
    <AccountModal v-if="showAccountModal" :account-form="accountForm" :submit-account="submitAccount" @close="showAccountModal = false" />

    <!-- Modal 6: Set Account Opening / Carry Over Balance -->
    <OpeningBalanceModal v-if="showOpeningBalanceModal" :accounts="accounts" :club="club" :opening-balance-form="openingBalanceForm" :submit-opening-balance="submitOpeningBalance" @close="showOpeningBalanceModal = false" />

    <!-- Modal 7: Import Bank Statement -->
    <ImportStatementModal v-if="showImportModal" :import-form="importForm" :submit-statement-import="submitStatementImport" @close="showImportModal = false" />

    <!-- Modal 8: Manual Target Allocation -->
    <ManualEntryModal v-if="showManualModal" :accounts="accounts" v-model:manual-match-type="manualMatchType" v-model:manual-nominal-code="manualNominalCode" v-model:manual-target-id="manualTargetId" :number-format="number_format" :reconciliation="reconciliation" :selected-tx="selectedTx" :submit-reconcile="submitReconcile" @close="showManualModal = false" />

    <!-- Modal 9: Statement Line Details -->
    <StatementLineDetailsModal v-if="showStatementLineDetailsModal && selectedStatementLineDetails" :delete-single-statement-line="deleteSingleStatementLine" :number-format="number_format" :selected-statement-line-details="selectedStatementLineDetails" @close="showStatementLineDetailsModal = false" />

    <!-- Modal 11: Statement vs System Balance Variance Explanation -->
    <DifferentBalancesModal v-if="showDifferentBalancesModal" :active-bank-account="activeBankAccount" :bills="bills" :filtered-unmatched-tx="filteredUnmatchedTx" :invoices="invoices" :navigate-to="navigateTo" :number-format="number_format" @close="showDifferentBalancesModal = false" />

    <!-- Modal 12: Bank Reconciliation Explanation (What's this?) -->
    <WhatsThisModal v-if="showWhatsThisModal" :bills="bills" :invoices="invoices" :reconciliation="reconciliation" @close="showWhatsThisModal = false" />

    <!-- Modal 10: Attachment Pop-Out Viewer with Zoom & Delete -->
    <AttachmentPreviewModal v-if="previewModal.show" :delete-attachment="deleteAttachment" :preview-modal="previewModal" :reset-zoom="resetZoom" :rotate-image="rotateImage" :rotation="rotation" :zoom-in="zoomIn" :zoom-level="zoomLevel" :zoom-out="zoomOut" @close="previewModal.show = false" />

    <!-- Modal 13: Activity Log -->
    <div v-if="activityModal.show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="activityModal.show = false">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4 my-8" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Activity Log</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ activityModal.label }}</p>
          </div>
          <button type="button" @click="activityModal.show = false" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <div v-if="activityModal.loading" class="text-center py-8 text-xs font-bold text-slate-400">Loading…</div>
        <div v-else-if="!activityModal.entries.length" class="text-center py-8 text-xs font-bold text-slate-400">No activity recorded yet.</div>
        <ul v-else class="space-y-3 max-h-[60vh] overflow-y-auto">
          <li v-for="entry in activityModal.entries" :key="entry.id" class="border border-slate-100 dark:border-slate-800 rounded-xl p-3">
            <div class="flex items-center justify-between gap-2">
              <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">{{ entry.action }}</span>
              <span class="text-[10px] font-mono text-slate-400">{{ entry.created_at }}</span>
            </div>
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200 mt-1.5">{{ entry.summary }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">by {{ entry.user_name }}</p>
          </li>
        </ul>
      </div>
    </div>
  </AdminLayout>
</template>
