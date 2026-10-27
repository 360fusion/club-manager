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
import ContactsTab from '@/Components/Accounting/Tabs/ContactsTab.vue';
import HomeTab from '@/Components/Accounting/Tabs/HomeTab.vue';
import BankAccountsTab from '@/Components/Accounting/Tabs/BankAccountsTab.vue';
import ReportingTab from '@/Components/Accounting/Tabs/ReportingTab.vue';
import GiftAidPanel from '@/Components/Accounting/Reconciliation/GiftAidPanel.vue';
import BankStatementsPanel from '@/Components/Accounting/Reconciliation/BankStatementsPanel.vue';
import CashCodingPanel from '@/Components/Accounting/Reconciliation/CashCodingPanel.vue';
import ReconcilePanel from '@/Components/Accounting/Reconciliation/ReconcilePanel.vue';
import AccountTransactionsPanel from '@/Components/Accounting/Sections/AccountTransactionsPanel.vue';
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
import { Head, Link, useForm, router } from '@inertiajs/vue3';
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
      <div class="bg-slate-900 dark:bg-slate-700 border border-slate-800 rounded-2xl shadow-sm p-1.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 relative z-30">
        <nav class="flex items-center px-1 text-xs sm:text-sm font-semibold text-slate-300 overflow-visible flex-wrap gap-1">
          <button
            type="button"
            @click="navigateTo('home')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'home' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>Home</span>
          </button>

          <button
            type="button"
            @click="navigateTo('sales')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'sales' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>Sales</span>
          </button>

          <button
            type="button"
            @click="navigateTo('purchases')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'purchases' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>Purchases</span>
          </button>

          <button
            type="button"
            @click="navigateTo('reporting')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reporting' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>Reporting</span>
          </button>

          <!-- Accounting Dropdown Menu -->
          <div class="relative accounting-dropdown-container">
            <button
              type="button"
              @click="toggleAccountingDropdown"
              :class="[
                'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap rounded-xl',
                ['accounting', 'bank-accounts', 'chart-of-accounts'].includes(activeTab) ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
              ]"
            >
              <span>Accounting</span>
              <span class="text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showAccountingDropdown }">▾</span>
            </button>

            <div
              v-if="showAccountingDropdown"
              class="absolute left-0 top-full mt-2 w-56 bg-slate-900 dark:bg-slate-700 border border-slate-700 rounded-xl shadow-2xl py-1.5 z-50 text-xs font-semibold text-slate-200 animate-in fade-in zoom-in-95 duration-100"
            >
              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('accounting')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
              >
                <span>🏦</span>
                <span>Bank & Payment Accounts</span>
              </button>

              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('chart-of-accounts')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
              >
                <span>📋</span>
                <span>Chart of Accounts</span>
              </button>

              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('accounting')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 border-t border-slate-800 mt-1 pt-2 cursor-pointer transition-colors"
              >
                <span>📖</span>
                <span>General Ledger & Journals</span>
              </button>

              <button
                v-if="canManageBilling"
                type="button"
                @click="showAccountingDropdown = false; openOpeningBalanceModal()"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
              >
                <span>⚖️</span>
                <span>Set Opening Balances</span>
              </button>
            </div>
          </div>

          <button
            type="button"
            @click="navigateTo('contacts')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'contacts' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>Contacts</span>
          </button>

          <button
            v-if="canManageBilling"
            type="button"
            @click="navigateTo('reconciliation')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reconciliation' ? 'font-extrabold text-white bg-blue-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600'
            ]"
          >
            <span>⚡ Reconciliation</span>
            <span v-if="reconciliation?.unmatched_transactions?.length > 0" class="px-1.5 py-0.5 text-[10px] font-black rounded-full bg-amber-500 text-slate-950">
              {{ reconciliation.unmatched_transactions.length }}
            </span>
          </button>

        </nav>

        <!-- Quick Action Dropdown and Settings Link in Header Bar -->
        <div class="flex items-center gap-2 px-2 py-1 shrink-0">
          <!-- + Add Pop-Out Menu -->
          <div v-if="canManageBilling" class="relative add-menu-container">
            <button
              type="button"
              @click.stop="showAddMenu = !showAddMenu"
              class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
            >
              <span>+ Add</span>
              <span class="text-[10px]">▾</span>
            </button>

            <div
              v-if="showAddMenu"
              class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl py-1.5 z-50 text-xs font-semibold text-slate-700 dark:text-slate-200"
            >
              <Link
                :href="route('admin.accounting.invoices.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white"
              >
                <span>🧾</span>
                <span>Create Invoice</span>
              </Link>
              <Link
                :href="route('admin.accounting.bills.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white"
              >
                <span>📄</span>
                <span>Add Bill</span>
              </Link>
              <Link
                :href="route('admin.accounting.journal.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white"
              >
                <span>📖</span>
                <span>Post Journal</span>
              </Link>
            </div>
          </div>

          <!-- Settings Link (Far Right) -->
          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800/80 dark:hover:bg-slate-600 font-semibold text-xs sm:text-sm rounded-xl transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>⚙️ Settings</span>
          </Link>
        </div>
      </div>

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
      <div v-if="activeTab === 'chart-of-accounts'" class="space-y-6">
        <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
          <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Chart of Accounts</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Categorized nominal ledger accounts for club bookkeeping.</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="openOpeningBalanceModal()"
                class="px-3.5 py-1.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
              >
                <span>⚖️ Set Opening Balances</span>
              </button>
              <button
                type="button"
                @click="showAccountModal = true"
                class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer shadow-sm"
              >
                + Add Account
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800/80 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  <th class="py-3 px-6">Code</th>
                  <th class="py-3 px-6">Account Name</th>
                  <th class="py-3 px-6">Account Type</th>
                  <th class="py-3 px-6 text-right">Current Balance</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200">
                <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
                  <td class="py-3 px-6 font-mono text-slate-500 dark:text-slate-400 font-bold">{{ acc.code }}</td>
                  <td class="py-3 px-6 font-bold text-slate-900 dark:text-white">{{ acc.name }}</td>
                  <td class="py-3 px-6">
                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', getTypeBadge(acc.type)]">
                      {{ acc.type }}
                    </span>
                  </td>
                  <td class="py-3 px-6 text-right font-black text-slate-900 dark:text-white font-mono">
                    <div class="flex items-center justify-end gap-3">
                      <span>{{ acc.formatted_balance }}</span>
                      <button
                        type="button"
                        @click="openOpeningBalanceModal(acc.id)"
                        class="px-2.5 py-1 text-[10px] font-extrabold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-lg border border-blue-200 dark:border-blue-800/60 transition-all cursor-pointer"
                        title="Set opening / carry-over balance for this account"
                      >
                        ⚖️ Carry Over
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- VIEW 8: CONTACTS (Members, Vendors & Business Directory) -->
      <ContactsTab v-if="activeTab === 'contacts'" :can-manage-billing="canManageBilling" :club="club" v-model:contact-filter="contactFilter" :contacts="contacts" :filtered-contacts="filteredContacts" :members="members" />

      <!-- VIEW 8: BANK RECONCILIATION WORKSPACE (XERO COMPLETE DESIGN) -->
      <div v-if="activeTab === 'reconciliation' && canManageBilling" class="space-y-4">
        
        <!-- Remembered matches -->
        <details v-if="(reconciliation?.match_rules || []).length" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm text-xs">
          <summary class="cursor-pointer font-extrabold text-slate-700 dark:text-slate-200">🔁 Remembered matches ({{ reconciliation.match_rules.length }})</summary>
          <p class="mt-2 text-slate-500 dark:text-slate-400">Recurring payees are suggested first once you have matched them. Forget one if it was a mistake.</p>
          <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
            <li v-for="rule in reconciliation.match_rules" :key="rule.id" class="py-2 flex items-center justify-between gap-3">
              <span class="min-w-0">
                <span class="font-mono font-bold text-slate-900 dark:text-white block truncate">{{ rule.pattern }}</span>
                <span class="text-slate-500 dark:text-slate-400">{{ rule.match_type === 'charity_relief' ? 'Charity relief' : `Ledger ${rule.nominal_code}` }} · used {{ rule.hit_count }}×</span>
              </span>
              <button type="button" @click="forgetMatchRule(rule.id)" class="px-2.5 py-1 text-[10px] font-extrabold text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 rounded-lg cursor-pointer hover:bg-rose-50 dark:hover:bg-rose-950/40">Forget</button>
            </li>
          </ul>
        </details>

        <!-- Top Bank Account Balance Header Bar -->
        <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-2">
            <div class="flex items-center gap-3">
              <span class="text-xl">
                <span v-if="activeBankAccount?.bank_name === 'GoCardless' || activeBankAccount?.gocardless_access_token">🟡</span>
                <span v-else-if="activeBankAccount?.bank_name === 'Stripe' || activeBankAccount?.stripe_secret_key">💜</span>
                <span v-else-if="activeBankAccount?.bank_name === 'SumUp' || activeBankAccount?.sumup_api_key">💚</span>
                <span v-else-if="activeBankAccount?.bank_name === 'PayPal' || activeBankAccount?.paypal_client_id">🟦</span>
                <span v-else>💳</span>
              </span>

              <!-- Bank Account Selector Dropdown -->
              <div class="relative">
                <select
                  v-model="selectedBankAccountId"
                  class="bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white font-black text-base rounded-xl px-3 py-1.5 focus:bg-white dark:focus:bg-slate-900 focus:ring-2 focus:ring-blue-500 cursor-pointer"
                >
                  <option
                    v-for="acc in (bankAccounts || [])"
                    :key="acc.id"
                    :value="acc.id"
                  >
                    {{ acc.bank_name }} — {{ acc.account_name }} (Code {{ acc.account_code || '1000' }})
                  </option>
                </select>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
              <div>
                <span class="font-extrabold text-slate-900 dark:text-white text-sm">{{ activeBankAccount?.formatted_statement_balance || ($cs + '0.00') }}</span>
                <span class="text-slate-500 dark:text-slate-400 ml-1">Statement Balance</span>
              </div>
              <div class="border-l border-slate-200 dark:border-slate-800 pl-4">
                <span class="font-extrabold text-slate-900 dark:text-white text-sm">{{ activeBankAccount?.formatted_ledger_balance || ($cs + '0.00') }}</span>
                <span class="text-slate-500 dark:text-slate-400 ml-1">Balance in System</span>
                <a href="#" @click.prevent="showDifferentBalancesModal = true" class="text-blue-600 dark:text-blue-400 hover:underline ml-1 text-[11px] font-bold">— Different balances?</a>
              </div>
            </div>
            <a href="#" @click.prevent="showWhatsThisModal = true" class="text-blue-600 dark:text-blue-400 text-xs font-semibold hover:underline inline-block mt-0.5">What's this?</a>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 self-start md:self-auto">
            <button
              type="button"
              @click="navigateTo('reporting', 'reconciliation_summary')"
              class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline mr-2 cursor-pointer"
            >
              Reconciliation Report
            </button>

            <div class="relative manage-account-container">
              <button
                type="button"
                @click.stop="showManageAccountMenu = !showManageAccountMenu"
                class="px-3 py-1.5 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-lg transition-all flex items-center gap-1 cursor-pointer"
              >
                <span>Manage Account</span>
                <span>▾</span>
              </button>
              <div v-if="showManageAccountMenu" class="absolute right-0 mt-1 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-lg py-1 z-30 text-xs font-semibold text-slate-700 dark:text-slate-200">
                <button type="button" @click="showImportModal = true; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">📂 Import Bank Statement</button>
                <button type="button" @click="reconSubTab = 'bank_statements'; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">📜 Bank Statement History</button>
                <button type="button" @click="reconSubTab = 'cash_coding'; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50">⚡ Bulk Cash Coding</button>
              </div>
            </div>
          </div>
        </div>


        <!-- Sub-Tab Navigation Bar & Compact View Toggle -->
        <div class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1">
          <div class="flex items-center gap-1 overflow-x-auto text-xs font-bold">
            <button
              type="button"
              @click="reconSubTab = 'reconcile'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'reconcile' ? 'border-blue-600 text-blue-700 dark:text-blue-300 font-extrabold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
              ]"
            >
              Reconcile ({{ filteredUnmatchedTx.length }})
            </button>

            <button
              type="button"
              @click="reconSubTab = 'cash_coding'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'cash_coding' ? 'border-blue-600 text-blue-700 dark:text-blue-300 font-extrabold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
              ]"
            >
              Cash coding
            </button>

            <button
              type="button"
              @click="reconSubTab = 'bank_statements'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'bank_statements' ? 'border-blue-600 text-blue-700 dark:text-blue-300 font-extrabold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
              ]"
            >
              Bank statements
            </button>

            <button
              type="button"
              @click="reconSubTab = 'account_transactions'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'account_transactions' ? 'border-blue-600 text-blue-700 dark:text-blue-300 font-extrabold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
              ]"
            >
              Account transactions
            </button>

            <button
              type="button"
              @click="reconSubTab = 'gift_aid'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5',
                reconSubTab === 'gift_aid' ? 'border-amber-600 text-amber-800 dark:text-amber-200 font-extrabold' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
              ]"
            >
              <span>🏛️ Gift Aid &amp; Relief Chest</span>
              <span v-if="reconciliation?.gift_aid_summary?.pending_claim_count > 0" class="px-1.5 py-0.5 text-[10px] font-black rounded-full bg-amber-500 text-slate-950">
                {{ reconciliation.gift_aid_summary.pending_claim_count }}
              </span>
            </button>
          </div>
        </div>

        <!-- Search & Filter Toolbar -->
        <div class="space-y-2">
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800">
            <div class="relative w-full sm:flex-1">
              <input
                v-model="reconSearch"
                type="text"
                placeholder="Search for Payee, Amount, Reference, Description, Cheque No., or Analysis Code"
                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-lg text-xs text-slate-800 dark:text-slate-100 focus:bg-white dark:focus:bg-slate-900 focus:outline-none focus:ring-1 focus:ring-blue-500"
              />
              <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
            </div>

            <button
              type="button"
              @click="showFilterPanel = !showFilterPanel"
              :class="[
                'px-3.5 py-1.5 font-extrabold text-xs rounded-lg border transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap',
                showFilterPanel ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 border-blue-300 dark:border-blue-700/60' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50'
              ]"
            >
              <span>🔻 Filter</span>
            </button>
          </div>

          <!-- Expandable Filter Panel -->
          <div v-if="showFilterPanel" class="bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div>
              <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Transaction Type</label>
              <select v-model="filterType" class="w-full p-1.5 border border-slate-300 dark:border-slate-700 rounded bg-white dark:bg-slate-900">
                <option value="all">All Lines (Spent &amp; Received)</option>
                <option value="spent">Spent Only (Outgoing)</option>
                <option value="received">Received Only (Incoming)</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Min Amount ({{ $cs }})</label>
              <input v-model="filterMinAmount" type="number" step="0.01" placeholder="0.00" class="w-full p-1.5 border border-slate-300 dark:border-slate-700 rounded bg-white dark:bg-slate-900" />
            </div>

            <div>
              <label class="font-bold text-slate-700 dark:text-slate-200 block mb-1">Max Amount ({{ $cs }})</label>
              <input v-model="filterMaxAmount" type="number" step="0.01" placeholder="9999.00" class="w-full p-1.5 border border-slate-300 dark:border-slate-700 rounded bg-white dark:bg-slate-900" />
            </div>

            <div class="flex items-end">
              <button
                type="button"
                @click="filterType = 'all'; filterMinAmount = ''; filterMaxAmount = ''; reconSearch = ''"
                class="w-full py-1.5 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 font-bold rounded"
              >
                Reset Filters
              </button>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 1: RECONCILE WORKSPACE -->
        <ReconcilePanel v-if="reconSubTab === 'reconcile'" :accounts="accounts" :active-details-tx-id="activeDetailsTxId" :active-options-tx-id="activeOptionsTxId" :close-who-dropdown-soon="closeWhoDropdownSoon" :compact-view="compactView" :delete-single-statement-line="deleteSingleStatementLine" :filtered-unmatched-tx="filteredUnmatchedTx" :get-contact-suggestions="getContactSuggestions" :get-row-state="getRowState" :number-format="number_format" :open-manual-match-modal-for-tx="openManualMatchModalForTx" :set-row-tab="setRowTab" :submit-reconcile="submitReconcile" :submit-row-reconcile="submitRowReconcile" :toggle-details-popover="toggleDetailsPopover" :toggle-options-dropdown="toggleOptionsDropdown" @close-details="activeDetailsTxId = null" />

        <!-- SUB-TAB 2: CASH CODING -->
        <CashCodingPanel v-else-if="reconSubTab === 'cash_coding'" :accounts="accounts" :close-who-dropdown-soon="closeWhoDropdownSoon" :contacts="contacts" :filtered-unmatched-tx="filteredUnmatchedTx" :get-contact-suggestions="getContactSuggestions" :get-row-state="getRowState" :number-format="number_format" v-model:selected-cash-coding-tx="selectedCashCodingTx" :submit-bulk-cash-coding="submitBulkCashCoding" :toggle-cash-coding-select-all="toggleCashCodingSelectAll" />

        <!-- SUB-TAB 3: BANK STATEMENTS (Full imported statement lines log) -->
        <BankStatementsPanel v-else-if="reconSubTab === 'bank_statements'" :deletable-statement-lines="deletableStatementLines" :delete-selected-statement-lines="deleteSelectedStatementLines" :filtered-statement-lines="filteredStatementLines" :restorable-statement-lines="restorableStatementLines" :restore-selected-statement-lines="restoreSelectedStatementLines" v-model:selected-statement-line-ids="selectedStatementLineIds" :show-import-modal="showImportModal" v-model:statement-lines-filter="statementLinesFilter" :toggle-statement-lines-select-all="toggleStatementLinesSelectAll" @open-import="showImportModal = true" />

        <!-- SUB-TAB 4: ACCOUNT TRANSACTIONS -->
        <AccountTransactionsPanel v-else-if="reconSubTab === 'account_transactions'" :bills="bills" :club="club" :filtered-account-transactions="filteredAccountTransactions" :invoices="invoices" :reconciliation="reconciliation" />

        <!-- SUB-TAB 5: GIFT AID & RELIEF CHEST RECONCILIATION -->
        <GiftAidPanel v-else-if="reconSubTab === 'gift_aid'" :club="club" :filtered-reconciled-donations="filteredReconciledDonations" :filtered-reconciled-gift-aid-total="filteredReconciledGiftAidTotal" v-model:gift-aid-filter-date-from="giftAidFilterDateFrom" v-model:gift-aid-filter-date-to="giftAidFilterDateTo" v-model:gift-aid-filter-person="giftAidFilterPerson" v-model:gift-aid-filter-search="giftAidFilterSearch" v-model:gift-aid-filter-status="giftAidFilterStatus" :reconciliation="reconciliation" :reset-gift-aid-filters="resetGiftAidFilters" />

      </div>

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
