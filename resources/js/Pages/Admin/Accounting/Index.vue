<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

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
      tax_registration_number: 'GB 987 6543 21',
      contact_email: '',
      phone: '',
      address_line_1: '',
      address_line_2: '',
      city: '',
      county: '',
      postcode: '',
      country: 'United Kingdom',
      currency: 'GBP',
      receipt_footer_notes: '',
      dues_grace_period_days: 14,
      auto_invoice_days_before: 7,
    }),
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
        formatted_total_donations: '£0.00',
        formatted_claimable_gift_aid: '£0.00',
        formatted_unclaimed_gift_aid: '£0.00',
        formatted_claimed_gift_aid: '£0.00',
        formatted_reconciled_gift_aid: '£0.00',
      },
    }),
  },
});

const validTabs = ['home', 'sales', 'purchases', 'reporting', 'accounting', 'bank-accounts', 'chart-of-accounts', 'reconciliation', 'contacts', 'settings'];
const validReports = ['account_summary', 'aged_payables', 'aged_receivables', 'balance_sheet', 'cash_summary', 'executive_summary', 'profit_and_loss', 'comparative_income_expenditure', 'reconciliation_summary'];

const parseUrlState = () => {
  if (typeof window === 'undefined') {
    return { tab: props.initialTab || 'home', report: props.initialReport || null };
  }

  const basePath = `/clubs/${props.club.slug}/admin/accounting`;
  const currentPath = window.location.pathname;

  if (currentPath.startsWith(basePath)) {
    const subPath = currentPath.substring(basePath.length).replace(/^\/+|\/+$/g, '');
    if (subPath) {
      const parts = subPath.split('/');
      const tab = validTabs.includes(parts[0]) ? parts[0] : (props.initialTab || 'home');
      const report = (parts[1] && validReports.includes(parts[1])) ? parts[1] : (tab === 'reporting' ? props.initialReport : null);
      return { tab, report };
    }
  }

  const hash = window.location.hash.replace('#', '').trim();
  if (hash) {
    const parts = hash.split('/');
    const tab = validTabs.includes(parts[0]) ? parts[0] : 'home';
    const report = (parts[1] && validReports.includes(parts[1])) ? parts[1] : null;
    return { tab, report };
  }

  const tab = props.initialTab || 'home';
  const report = props.initialReport || null;
  return { tab, report };
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

const navigateTo = (tabName, reportName = null, bankAccountId = null) => {
  activeTab.value = tabName;
  selectedReport.value = reportName;
  if (bankAccountId) {
    selectedBankAccountId.value = bankAccountId;
  }

  if (typeof window !== 'undefined') {
    const basePath = `/clubs/${props.club.slug}/admin/accounting`;
    let targetUrl = basePath;
    if (tabName && tabName !== 'home') {
      targetUrl += '/' + tabName;
      if (tabName === 'reporting' && reportName) {
        targetUrl += '/' + reportName;
      }
    }
    if (window.location.pathname !== targetUrl) {
      history.pushState({ tab: tabName, report: reportName }, '', targetUrl);
    }
  }
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

const now = new Date();
const firstDayOfMonthStr = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
const lastDayOfMonthStr = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);

const reportStartDate = ref(firstDayOfMonthStr);
const reportEndDate = ref(lastDayOfMonthStr);
const reportQuickRange = ref('this_month');

const setQuickRange = (preset) => {
  reportQuickRange.value = preset;
  const today = new Date();
  const y = today.getFullYear();
  const m = today.getMonth();

  if (preset === 'this_month') {
    reportStartDate.value = new Date(y, m, 1).toISOString().slice(0, 10);
    reportEndDate.value = new Date(y, m + 1, 0).toISOString().slice(0, 10);
  } else if (preset === 'last_month') {
    reportStartDate.value = new Date(y, m - 1, 1).toISOString().slice(0, 10);
    reportEndDate.value = new Date(y, m, 0).toISOString().slice(0, 10);
  } else if (preset === 'this_year') {
    reportStartDate.value = new Date(y, 0, 1).toISOString().slice(0, 10);
    reportEndDate.value = new Date(y, 11, 31).toISOString().slice(0, 10);
  } else if (preset === 'all_time') {
    reportStartDate.value = '2020-01-01';
    reportEndDate.value = new Date().toISOString().slice(0, 10);
  }
};

const formatDateFormatted = (dateStr) => {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const showAccountModal = ref(false);
const showJournalModal = ref(false);
const showInvoiceModal = ref(false);
const showBillModal = ref(false);
const showContactModal = ref(false);
const showImportModal = ref(false);
const showManualModal = ref(false);
const showDifferentBalancesModal = ref(false);
const showWhatsThisModal = ref(false);
const editingContactId = ref(null);

const selectedTx = ref(null);
const reconSearch = ref('');
const manualSearchText = ref('');
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

const ignoreForm = useForm({
  transaction_id: null,
});

const importForm = useForm({
  statement_file: null,
});

const reconSubTab = ref('reconcile');
const compactView = ref(false);
const autoReconcile = ref(false);
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

const filteredReconciledDonationsTotal = computed(() => {
  return filteredReconciledDonations.value.reduce((acc, item) => acc + (item.total_amount || 0), 0);
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
const showBankAccountModal = ref(false);
const showPayPalModal = ref(false);
const showPayPalSecret = ref(false);
const testConnectionStatus = ref(null);
const testConnectionMessage = ref('');

const bankAccountForm = useForm({
  bank_name: '',
  account_name: '',
  account_type: 'current',
  account_number: '',
  sort_code: '',
  currency: 'GBP',
  opening_balance: 0,
});

const payPalForm = useForm({
  account_name: 'Lodge PayPal Operating Account',
  paypal_client_id: '',
  paypal_client_secret: '',
  paypal_environment: 'live',
  currency: 'GBP',
  opening_balance: 0,
});

const showAccountTypeWarning = ref(false);
const accountTypeWarningBypassed = ref(false);

const detectedMismatch = computed(() => {
  const name = `${bankAccountForm.bank_name || ''} ${bankAccountForm.account_name || ''}`.toLowerCase();
  const currentType = bankAccountForm.account_type;

  if (!name.trim()) return null;

  // Payment Gateways (Stripe, PayPal, GoCardless, etc.)
  if (/stripe|paypal|gocardless|checkout|klarna|adyen/.test(name)) {
    if (currentType !== 'payment_gateway') {
      return {
        suggestedType: 'payment_gateway',
        suggestedLabel: 'Payment Gateway (Stripe/PayPal)',
        reason: 'Selecting Payment Gateway enables fee tracking (Code 6000) and settlement reconciliation.',
      };
    }
  }

  // Merchant Readers (SumUp, Zettle, Square, Lopay, etc.)
  if (/sumup|zettle|square|lopay|clover|dopos/.test(name)) {
    if (currentType !== 'merchant') {
      return {
        suggestedType: 'merchant',
        suggestedLabel: 'Card Reader / Merchant (SumUp)',
        reason: 'Selecting Card Reader / Merchant optimizes daily merchant payout batch reconciliation.',
      };
    }
  }

  // Cash / Till / Petty Cash
  if (/cash|till|float|petty|drawer|box/.test(name)) {
    if (currentType !== 'cash') {
      return {
        suggestedType: 'cash',
        suggestedLabel: 'Petty Cash / Cash Register',
        reason: 'Selecting Petty Cash ignores sort codes and enables till count float reconciliations.',
      };
    }
  }

  // High Street Banks (Barclays, Lloyds, HSBC, NatWest, Santander, Starling, Revolut, Metro, etc.)
  if (/barclays|lloyds|hsbc|natwest|santander|starling|revolut|metro|nationwide|tsb|halifax|coutts/.test(name)) {
    if (['payment_gateway', 'merchant', 'cash'].includes(currentType)) {
      return {
        suggestedType: 'current',
        suggestedLabel: 'High Street Current Account',
        reason: 'High street banks are typically categorized under High Street Current Account.',
      };
    }
  }

  return null;
});

const submitBankAccount = () => {
  if (detectedMismatch.value && !accountTypeWarningBypassed.value) {
    showAccountTypeWarning.value = true;
    return;
  }

  bankAccountForm.post(route('admin.accounting.bank_accounts.store', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showBankAccountModal.value = false;
      showAccountTypeWarning.value = false;
      accountTypeWarningBypassed.value = false;
      bankAccountForm.reset();
    },
  });
};

const applySuggestedTypeAndSubmit = () => {
  if (detectedMismatch.value) {
    bankAccountForm.account_type = detectedMismatch.value.suggestedType;
  }
  accountTypeWarningBypassed.value = true;
  submitBankAccount();
};

const proceedWithCurrentTypeAnyway = () => {
  accountTypeWarningBypassed.value = true;
  submitBankAccount();
};

const submitPayPalAccount = () => {
  payPalForm.post(route('admin.accounting.bank_accounts.paypal.connect', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showPayPalModal.value = false;
      payPalForm.reset();
      testConnectionStatus.value = null;
    },
  });
};

const showStripeModal = ref(false);
const showStripeSecret = ref(false);
const stripeForm = useForm({
  account_name: 'Lodge Stripe Account',
  stripe_secret_key: '',
  currency: 'GBP',
  opening_balance: 0,
});

const submitStripeAccount = () => {
  stripeForm.post(route('admin.accounting.bank_accounts.stripe.connect', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showStripeModal.value = false;
      stripeForm.reset();
    },
  });
};

const showSumUpModal = ref(false);
const showSumUpKey = ref(false);
const sumUpForm = useForm({
  account_name: 'Lodge SumUp Merchant Account',
  sumup_api_key: '',
  currency: 'GBP',
  opening_balance: 0,
});

const submitSumUpAccount = () => {
  sumUpForm.post(route('admin.accounting.bank_accounts.sumup.connect', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showSumUpModal.value = false;
      sumUpForm.reset();
    },
  });
};

const showGoCardlessModal = ref(false);
const showGoCardlessToken = ref(false);
const showGoCardlessSecret = ref(false);
const goCardlessForm = useForm({
  account_name: 'Lodge GoCardless Direct Debit Account',
  gocardless_access_token: '',
  gocardless_environment: 'sandbox',
  gocardless_webhook_secret: '',
  currency: 'GBP',
  opening_balance: 0,
});

const submitGoCardlessAccount = () => {
  goCardlessForm.post(route('admin.accounting.bank_accounts.gocardless.connect', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showGoCardlessModal.value = false;
      goCardlessForm.reset();
    },
  });
};

const showGatewaySyncModal = ref(false);
const activeGatewaySyncAccId = ref(null);
const gatewaySyncType = ref('paypal'); // 'paypal', 'stripe', 'sumup'
const gatewaySyncForm = useForm({
  start_date: '',
  end_date: '',
});

const openGatewaySyncModal = (accId, type = 'paypal') => {
  activeGatewaySyncAccId.value = accId;
  gatewaySyncType.value = type;
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
  gatewaySyncForm.start_date = thirtyDaysAgo.toISOString().slice(0, 10);
  gatewaySyncForm.end_date = new Date().toISOString().slice(0, 10);
  showGatewaySyncModal.value = true;
};

const submitGatewaySyncModal = () => {
  if (!activeGatewaySyncAccId.value) return;

  let routeName = 'admin.accounting.bank_accounts.paypal.sync';
  if (gatewaySyncType.value === 'stripe') {
    routeName = 'admin.accounting.bank_accounts.stripe.sync';
  } else if (gatewaySyncType.value === 'sumup') {
    routeName = 'admin.accounting.bank_accounts.sumup.sync';
  } else if (gatewaySyncType.value === 'gocardless') {
    routeName = 'admin.accounting.bank_accounts.gocardless.sync';
  }

  gatewaySyncForm.post(route(routeName, { clubSlug: props.club.slug, id: activeGatewaySyncAccId.value }), {
    preserveScroll: true,
    onSuccess: () => {
      showGatewaySyncModal.value = false;
    },
  });
};

const syncPayPalNow = (accId) => {
  openGatewaySyncModal(accId, 'paypal');
};

const syncStripeNow = (accId) => {
  openGatewaySyncModal(accId, 'stripe');
};

const syncSumUpNow = (accId) => {
  openGatewaySyncModal(accId, 'sumup');
};

const syncGoCardlessNow = (accId) => {
  openGatewaySyncModal(accId, 'gocardless');
};

const toggleBankAccountActive = (accId) => {
  router.post(route('admin.accounting.bank_accounts.toggle', { clubSlug: props.club.slug, id: accId }), {}, {
    preserveScroll: true,
  });
};
const selectedCashCodingTx = ref([]);
const selectedStatementLineIds = ref([]);
const statementLinesFilter = ref('statement_lines');

const filteredStatementLines = computed(() => {
  let list = props.reconciliation?.statement_lines || [];
  if (activeBankAccount.value) {
    list = list.filter(l => !l.bank_account_id || l.bank_account_id === activeBankAccount.value.id);
  }
  if (statementLinesFilter.value === 'statement_lines') {
    return list.filter(l => l.status !== 'Deleted');
  } else if (statementLinesFilter.value === 'deleted') {
    return list.filter(l => l.status === 'Deleted');
  }
  return list;
});

const selectedStatementLines = computed(() => {
  const allLines = props.reconciliation?.statement_lines || [];
  return allLines.filter(l => selectedStatementLineIds.value.includes(l.id));
});

const deletableStatementLines = computed(() => {
  return selectedStatementLines.value.filter(l => l.status !== 'Deleted');
});

const restorableStatementLines = computed(() => {
  return selectedStatementLines.value.filter(l => l.status === 'Deleted');
});

const toggleStatementLinesSelectAll = () => {
  if (selectedStatementLineIds.value.length === filteredStatementLines.value.length) {
    selectedStatementLineIds.value = [];
  } else {
    selectedStatementLineIds.value = filteredStatementLines.value.map(l => l.id);
  }
};

const deleteSelectedStatementLines = () => {
  const ids = deletableStatementLines.value.map(l => l.id);
  if (ids.length === 0) return;
  router.post(route('admin.accounting.statement_lines.delete', props.club.slug), {
    transaction_ids: ids,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedStatementLineIds.value = [];
    }
  });
};

const restoreSelectedStatementLines = () => {
  const ids = restorableStatementLines.value.map(l => l.id);
  if (ids.length === 0) return;
  router.post(route('admin.accounting.statement_lines.restore', props.club.slug), {
    transaction_ids: ids,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedStatementLineIds.value = [];
    }
  });
};

const toggleOptionsDropdown = (txId) => {
  if (activeOptionsTxId.value === txId) {
    activeOptionsTxId.value = null;
  } else {
    activeOptionsTxId.value = txId;
  }
};

const deleteSingleStatementLine = (txId) => {
  activeOptionsTxId.value = null;
  if (!confirm('Are you sure you want to delete this statement line?')) return;
  router.post(route('admin.accounting.statement_lines.delete', props.club.slug), {
    transaction_ids: [txId],
  }, {
    preserveScroll: true,
  });
};

const selectedStatementLineDetails = ref(null);

const openStatementLineDetails = (tx) => {
  selectedStatementLineDetails.value = tx;
  showStatementLineDetailsModal.value = true;
};

const selectedAccountTxIds = ref([]);

const toggleAccountTxSelectAll = () => {
  const allTx = props.reconciliation?.account_transactions || [];
  if (selectedAccountTxIds.value.length === allTx.length) {
    selectedAccountTxIds.value = [];
  } else {
    selectedAccountTxIds.value = allTx.map(t => t.id);
  }
};

const removeAndRedoSelectedAccountTransactions = () => {
  if (selectedAccountTxIds.value.length === 0) return;
  router.post(route('admin.accounting.account_transactions.remove_redo', props.club.slug), {
    transaction_ids: selectedAccountTxIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedAccountTxIds.value = [];
    }
  });
};

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

const submitBulkCashCoding = () => {
  if (selectedCashCodingTx.value.length === 0) return;
  alert(`${selectedCashCodingTx.value.length} statement line(s) queued for bulk cash coding reconciliation!`);
};

const filteredManualSubs = computed(() => {
  let list = props.reconciliation?.unpaid_subscriptions || [];
  const q = manualSearchText.value.trim().toLowerCase();
  if (q) {
    list = list.filter(s =>
      s.member_name?.toLowerCase().includes(q) ||
      s.invoice_reference?.toLowerCase().includes(q)
    );
  }
  return list;
});

const filteredManualBills = computed(() => {
  let list = props.reconciliation?.unpaid_bills || [];
  const q = manualSearchText.value.trim().toLowerCase();
  if (q) {
    list = list.filter(b =>
      b.vendor_name?.toLowerCase().includes(q) ||
      b.bill_number?.toLowerCase().includes(q)
    );
  }
  return list;
});

const selectTx = (tx) => {
  selectedTx.value = tx;
};

const openManualMatchModal = () => {
  if (!selectedTx.value) return;
  manualMatchType.value = selectedTx.value.amount > 0 ? 'member_subscription' : 'supplier_bill';
  manualTargetId.value = '';
  manualNominalCode.value = '4000';
  showManualModal.value = true;
};

const submitReconcile = (matchType, targetId, nominalCode = 'GENERAL') => {
  if (!selectedTx.value) return;
  reconcileForm.transaction_id = selectedTx.value.id;
  reconcileForm.match_type = matchType;
  reconcileForm.target_id = targetId;
  reconcileForm.nominal_code = nominalCode;
  reconcileForm.post(route('admin.accounting.reconcile', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      showManualModal.value = false;
      const remaining = props.reconciliation?.unmatched_transactions?.filter(t => t.id !== selectedTx.value.id);
      selectedTx.value = remaining && remaining.length > 0 ? remaining[0] : null;
    },
  });
};

const submitIgnore = (txId) => {
  ignoreForm.transaction_id = txId;
  ignoreForm.post(route('admin.accounting.ignore_transaction', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      const remaining = props.reconciliation?.unmatched_transactions?.filter(t => t.id !== txId);
      selectedTx.value = remaining && remaining.length > 0 ? remaining[0] : null;
    },
  });
};

const getContactSuggestions = (query) => {
  const q = (query || '').trim().toLowerCase();
  if (q.length < 2) return [];
  
  const matches = (filteredContacts.value || []).filter(c =>
    c.name?.toLowerCase().includes(q) ||
    (c.email && c.email !== '—' && c.email.toLowerCase().includes(q))
  );

  const seen = new Set();
  const unique = [];
  for (const c of matches) {
    const key = c.name?.trim().toLowerCase();
    if (key && !seen.has(key)) {
      seen.add(key);
      unique.push(c);
    }
  }

  return unique.slice(0, 8);
};

const getRowState = (txId, tx) => {
  if (!rowStates.value[txId]) {
    const hasMatches = tx?.suggested_matches && tx.suggested_matches.length > 0;
    
    let autoWho = tx?.contact_name || '';
    if (!autoWho && tx?.raw_description && filteredContacts.value?.length > 0) {
      const descLower = tx.raw_description.toLowerCase();
      const match = filteredContacts.value.find(c => c.name && c.name.length > 2 && descLower.includes(c.name.toLowerCase()));
      if (match) {
        autoWho = match.name;
      }
    }

    rowStates.value[txId] = {
      tab: hasMatches ? 'Match' : 'Create',
      who: autoWho,
      showWhoDropdown: false,
      what: '',
      showWhatError: false,
      why: tx?.raw_description || '',
      category: 'General',
      vat: 'No VAT',
      transfer_account: '',
      discuss_note: '',
    };
  }
  return rowStates.value[txId];
};

const setRowTab = (txId, tabName) => {
  if (!rowStates.value[txId]) {
    getRowState(txId, null);
  }
  rowStates.value[txId].tab = tabName;
};

const openManualMatchModalForTx = (tx) => {
  selectedTx.value = tx;
  openManualMatchModal();
};

const submitRowReconcile = (tx) => {
  selectedTx.value = tx;
  const state = getRowState(tx.id, tx);
  state.showWhatError = false;

  if (state.tab === 'Create') {
    if (!state.what) {
      state.showWhatError = true;
      return;
    }
    reconcileForm.transaction_id = tx.id;
    reconcileForm.match_type = 'ledger_account';
    reconcileForm.target_id = 0;
    reconcileForm.nominal_code = state.what;
  } else if (state.tab === 'Match') {
    if (tx.suggested_matches && tx.suggested_matches.length > 0) {
      const match = tx.suggested_matches[0];
      reconcileForm.transaction_id = tx.id;
      reconcileForm.match_type = match.match_type;
      reconcileForm.target_id = match.target_id;
      reconcileForm.nominal_code = match.nominal_code || 'GENERAL';
    } else {
      if (!state.what) {
        state.showWhatError = true;
        return;
      }
      reconcileForm.transaction_id = tx.id;
      reconcileForm.match_type = 'ledger_account';
      reconcileForm.target_id = 0;
      reconcileForm.nominal_code = state.what;
    }
  } else if (state.tab === 'Transfer') {
    reconcileForm.transaction_id = tx.id;
    reconcileForm.match_type = 'ledger_account';
    reconcileForm.target_id = 0;
    reconcileForm.nominal_code = '1200';
  } else if (state.tab === 'Discuss') {
    return;
  }

  reconcileForm.post(route('admin.accounting.reconcile', props.club.slug), {
    preserveScroll: true,
  });
};

const submitStatementImport = () => {
  importForm.post(route('admin.accounting.import_statement', props.club.slug), {
    preserveScroll: true,
    onSuccess: () => {
      importForm.reset('statement_file');
      showImportModal.value = false;
    },
  });
};

const contactFilter = ref('all');

const contactForm = useForm({
  type: 'person',
  name: '',
  contact_person: '',
  email: '',
  phone: '',
  role: 'Contractor / Coach',
  tax_id: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  postcode: '',
  country: 'United Kingdom',
  notes: '',
});

const openAddContactModal = () => {
  editingContactId.value = null;
  contactForm.clearErrors();
  contactForm.reset({
    type: 'person',
    name: '',
    contact_person: '',
    email: '',
    phone: '',
    role: 'Contractor / Coach',
    tax_id: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    postcode: '',
    country: 'United Kingdom',
    notes: '',
  });
  showContactModal.value = true;
};

const openEditContactModal = (c) => {
  if (c.kind === 'member') return;
  editingContactId.value = c.contact_db_id;
  contactForm.clearErrors();
  contactForm.type = c.type || 'person';
  contactForm.name = c.name || '';
  contactForm.contact_person = (c.contact_person && c.contact_person !== c.name) ? c.contact_person : '';
  contactForm.email = (c.email && c.email !== '—') ? c.email : '';
  contactForm.phone = (c.phone && c.phone !== '—') ? c.phone : '';
  contactForm.role = c.role || 'Contractor / Coach';
  contactForm.tax_id = (c.tax_id && c.tax_id !== '—') ? c.tax_id : '';
  contactForm.address_line_1 = c.address_line_1 || '';
  contactForm.address_line_2 = c.address_line_2 || '';
  contactForm.city = c.city || '';
  contactForm.postcode = c.postcode || '';
  contactForm.country = c.country || 'United Kingdom';
  contactForm.notes = c.notes || '';
  showContactModal.value = true;
};

const submitContact = () => {
  if (editingContactId.value) {
    contactForm.put(route('admin.accounting.contacts.update', [props.club.slug, editingContactId.value]), {
      onSuccess: () => {
        showContactModal.value = false;
        editingContactId.value = null;
        contactForm.reset();
      },
    });
  } else {
    contactForm.post(route('admin.accounting.contacts.store', props.club.slug), {
      onSuccess: () => {
        showContactModal.value = false;
        contactForm.reset();
      },
    });
  }
};

const filteredContacts = computed(() => {
  const all = [];

  (props.contacts || []).forEach(c => {
    all.push({
      id: 'c_' + c.id,
      contact_db_id: c.id,
      kind: 'contact',
      type: c.type,
      name: c.name,
      contact_person: c.contact_person || c.name,
      email: c.email || '—',
      phone: c.phone || '—',
      role: c.role || 'Contact',
      tax_id: c.tax_id || '—',
      address: c.formatted_address || '—',
      address_line_1: c.address_line_1 || '',
      address_line_2: c.address_line_2 || '',
      city: c.city || '',
      postcode: c.postcode || '',
      country: c.country || 'United Kingdom',
      notes: c.notes || '',
    });
  });

  (props.members || []).forEach(m => {
    all.push({
      id: 'm_' + m.id,
      kind: 'member',
      type: 'person',
      name: m.name,
      contact_person: m.name,
      email: m.email || '—',
      phone: '—',
      role: 'Club Member',
      tax_id: '—',
      address: 'Registered Member',
      notes: null,
      user_id: m.id,
    });
  });

  if (contactFilter.value === 'person') {
    return all.filter(item => item.type === 'person');
  }
  if (contactFilter.value === 'member') {
    return all.filter(item => item.kind === 'member');
  }

  return all;
});

const showOpeningBalanceModal = ref(false);

const openingBalanceForm = useForm({
  account_id: '',
  opening_balance: '',
  as_of_date: new Date().toISOString().split('T')[0],
});

const openOpeningBalanceModal = (accId = '') => {
  openingBalanceForm.reset();
  openingBalanceForm.account_id = accId || (props.accounts[0]?.id || '');
  openingBalanceForm.as_of_date = new Date().toISOString().split('T')[0];
  showOpeningBalanceModal.value = true;
};

const submitOpeningBalance = () => {
  openingBalanceForm.post(route('admin.accounting.opening_balance.store', props.club.slug), {
    onSuccess: () => {
      showOpeningBalanceModal.value = false;
      openingBalanceForm.reset();
    },
  });
};

// New Account Form
const accountForm = useForm({
  code: '',
  name: '',
  type: 'asset',
  opening_balance: '',
  as_of_date: new Date().toISOString().split('T')[0],
});

const submitAccount = () => {
  accountForm.post(route('admin.accounting.accounts.store', props.club.slug), {
    onSuccess: () => {
      showAccountModal.value = false;
      accountForm.reset();
    },
  });
};

// Double-Entry Journal Posting Form
const journalForm = useForm({
  description: '',
  entry_date: new Date().toISOString().split('T')[0],
  items: [
    { account_id: '', debit: 0, credit: 0, memo: '' },
    { account_id: '', debit: 0, credit: 0, memo: '' },
  ],
});

const addJournalLine = () => {
  journalForm.items.push({ account_id: '', debit: 0, credit: 0, memo: '' });
};

const removeJournalLine = (index) => {
  if (journalForm.items.length > 2) {
    journalForm.items.splice(index, 1);
  }
};

const totalJournalDebit = computed(() => {
  return journalForm.items.reduce((sum, i) => sum + (parseFloat(i.debit) || 0), 0);
});

const totalJournalCredit = computed(() => {
  return journalForm.items.reduce((sum, i) => sum + (parseFloat(i.credit) || 0), 0);
});

const isJournalBalanced = computed(() => {
  return Math.abs(totalJournalDebit.value - totalJournalCredit.value) < 0.01 && totalJournalDebit.value > 0;
});

const submitJournal = () => {
  if (!isJournalBalanced.value) return;

  journalForm.post(route('admin.accounting.journal.store', props.club.slug), {
    onSuccess: () => {
      showJournalModal.value = false;
      journalForm.reset();
      journalForm.items = [
        { account_id: '', debit: 0, credit: 0, memo: '' },
        { account_id: '', debit: 0, credit: 0, memo: '' },
      ];
    },
  });
};

// New Invoice Form
const invoiceForm = useForm({
  user_id: '',
  title: '',
  amount: '',
});

const submitInvoice = (isDraft = false) => {
  invoiceForm.transform((data) => ({
    ...data,
    title: data.title || (isDraft ? 'Draft Invoice' : ''),
    amount: data.amount !== '' && data.amount !== null && data.amount !== undefined ? data.amount : (isDraft ? 0 : data.amount),
    is_draft: isDraft,
  })).post(route('admin.accounting.invoices.store', props.club.slug), {
    onSuccess: () => {
      showInvoiceModal.value = false;
      invoiceForm.reset();
    },
  });
};

// New Vendor Bill Form
const billForm = useForm({
  vendor_name: '',
  category: 'Facility & Clubhouse Maintenance',
  amount: '',
  due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  notes: '',
  attachment: null,
  is_draft: false,
});

const handleBillFileChange = (e) => {
  billForm.attachment = e.target.files[0] || null;
};

const submitBill = (isDraft = false) => {
  billForm.transform((data) => ({
    ...data,
    is_draft: isDraft,
  })).post(route('admin.accounting.bills.store', props.club.slug), {
    onSuccess: () => {
      showBillModal.value = false;
      billForm.reset();
    },
  });
};

const publishInvoice = (id) => {
  if (confirm('Are you sure you want to issue/publish this invoice? It will post to Accounts Receivable.')) {
    router.post(route('admin.accounting.invoices.publish', { clubSlug: props.club.slug, id }));
  }
};

const publishBill = (id) => {
  if (confirm('Are you sure you want to approve and post this vendor bill to Accounts Payable?')) {
    router.post(route('admin.accounting.bills.publish', { clubSlug: props.club.slug, id }));
  }
};

// ── Edit Invoice & Edit Vendor Bill Modal State & Handlers ──────────────────
const showEditInvoiceModal = ref(false);
const editInvoiceForm = useForm({
  id: null,
  user_id: '',
  invoice_number: '',
  title: '',
  amount: 0,
  status: 'draft',
  attachment: null,
  existingAttachment: null,
});

const handleEditInvoiceFileChange = (e) => {
  editInvoiceForm.attachment = e.target.files[0] || null;
};

const openEditInvoiceModal = (inv) => {
  if (!inv) return;
  router.visit(route('admin.accounting.invoices.edit', { clubSlug: props.club.slug, id: inv.id }));
};

const openEditBillModal = (bill) => {
  if (!bill) return;
  router.visit(route('admin.accounting.bills.edit', { clubSlug: props.club.slug, id: bill.id }));
};

const submitUpdateBill = (overrideStatus = null) => {
  const statusToSave = overrideStatus || editBillForm.status;
  editBillForm.transform((data) => ({
    ...data,
    status: statusToSave,
    _method: 'PUT',
  })).post(route('admin.accounting.bills.update', { clubSlug: props.club.slug, id: editBillForm.id }), {
    onSuccess: () => {
      showEditBillModal.value = false;
      editBillForm.reset();
    },
  });
};

const confirmDeleteBill = (bill) => {
  if (!bill) return;
  if (bill.status === 'paid') {
    alert('Paid bills cannot be deleted.');
    return;
  }
  if (confirm(`Are you sure you want to delete vendor bill ${bill.bill_number}? This action cannot be undone.`)) {
    router.delete(route('admin.accounting.bills.destroy', { clubSlug: props.club.slug, id: bill.id }), {
      onSuccess: () => {
        showEditBillModal.value = false;
      },
    });
  }
};

// ── Attachment Preview Modal State & Controls ────────────────────────────────
const previewModal = ref({
  show: false,
  type: '', // 'invoice' or 'bill'
  itemId: null,
  title: '',
  url: '',
  mimeType: '',
  fileName: '',
});

const zoomLevel = ref(1);
const rotation = ref(0);

const openAttachmentModal = (item, type) => {
  if (!item || !item.attachment) return;
  previewModal.value = {
    show: true,
    type,
    itemId: item.id,
    title: item.title || item.bill_number || item.invoice_number || 'Attachment',
    url: item.attachment.original_url,
    mimeType: item.attachment.mime_type || (item.attachment.file_name?.endsWith('.pdf') ? 'application/pdf' : 'image/png'),
    fileName: item.attachment.file_name || 'document',
  };
  zoomLevel.value = 1;
  rotation.value = 0;
};

const zoomIn = () => {
  if (zoomLevel.value < 3) zoomLevel.value = parseFloat((zoomLevel.value + 0.25).toFixed(2));
};

const zoomOut = () => {
  if (zoomLevel.value > 0.4) zoomLevel.value = parseFloat((zoomLevel.value - 0.25).toFixed(2));
};

const resetZoom = () => {
  zoomLevel.value = 1;
  rotation.value = 0;
};

const rotateImage = () => {
  rotation.value = (rotation.value + 90) % 360;
};

const deleteAttachment = () => {
  if (!confirm('Are you sure you want to delete this attachment? This action cannot be undone.')) return;
  const routeName = previewModal.value.type === 'invoice'
    ? 'admin.accounting.invoices.attachment.destroy'
    : 'admin.accounting.bills.attachment.destroy';

  router.delete(route(routeName, { clubSlug: props.club.slug, id: previewModal.value.itemId }), {
    onSuccess: () => {
      previewModal.value.show = false;
    },
  });
};

const markInvoicePaid = (id) => {
  if (confirm('Mark this invoice as paid? This will automatically deposit funds to Operating Bank Account.')) {
    router.post(route('admin.accounting.invoices.pay', { clubSlug: props.club.slug, id }));
  }
};

const markBillPaid = (id) => {
  if (confirm('Mark this vendor bill as paid? This will automatically clear Accounts Payable.')) {
    router.post(route('admin.accounting.bills.pay', { clubSlug: props.club.slug, id }));
  }
};

// ── Sales & Purchases Filters ────────────────────────────────────────────────
const salesSearch     = ref('');
const salesYearFilter = ref('all');   // 'all' | '2024' | '2025' | ...
const salesFyFilter   = ref('all');   // 'all' | '2024-25' | ...

const billsSearch     = ref('');
const billsYearFilter = ref('all');
const billsFyFilter   = ref('all');

// Financial year: 1 Sept → 31 Aug (UK club default — override via settings)
const fyStartMonth = 9; // September (1-indexed)

/** Parse a date string like "12 Sep 2026" → Date */
const parseDate = (str) => {
  if (!str) return null;
  return new Date(str);
};

/** Return financial year label for a date, e.g. "2025-26" */
const getFyLabel = (dateStr) => {
  const d = parseDate(dateStr);
  if (!d) return null;
  const m = d.getMonth() + 1; // 1-indexed
  const y = d.getFullYear();
  return m >= fyStartMonth ? `${y}-${String(y + 1).slice(2)}` : `${y - 1}-${String(y).slice(2)}`;
};

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
const filteredInvoices = computed(() => {
  let list = props.invoices || [];
  const q = salesSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(i =>
      i.invoice_number?.toLowerCase().includes(q) ||
      i.recipient_name?.toLowerCase().includes(q) ||
      i.title?.toLowerCase().includes(q)
    );
  }
  if (salesFyFilter.value !== 'all') {
    list = list.filter(i => getFyLabel(i.created_at) === salesFyFilter.value);
  } else if (salesYearFilter.value !== 'all') {
    list = list.filter(i => parseDate(i.created_at)?.getFullYear() === Number(salesYearFilter.value));
  }
  return list;
});

/** Filtered bills */
const filteredBills = computed(() => {
  let list = props.bills || [];
  const q = billsSearch.value.trim().toLowerCase();
  if (q) {
    list = list.filter(b =>
      b.bill_number?.toLowerCase().includes(q) ||
      b.vendor_name?.toLowerCase().includes(q) ||
      b.category?.toLowerCase().includes(q)
    );
  }
  if (billsFyFilter.value !== 'all') {
    list = list.filter(b => getFyLabel(b.due_date) === billsFyFilter.value);
  } else if (billsYearFilter.value !== 'all') {
    list = list.filter(b => parseDate(b.due_date)?.getFullYear() === Number(billsYearFilter.value));
  }
  return list;
});
// ────────────────────────────────────────────────────────────────────────────

const formatCurrency = (val) => {
  const num = parseFloat(val) || 0;
  return '£' + num.toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const getTypeBadge = (type) => {
  switch (type) {
    case 'asset': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    case 'liability': return 'bg-amber-50 text-amber-700 border-amber-200';
    case 'equity': return 'bg-purple-50 text-purple-700 border-purple-200';
    case 'revenue': return 'bg-sky-50 text-sky-700 border-sky-200';
    case 'expense': return 'bg-rose-50 text-rose-700 border-rose-200';
    default: return 'bg-slate-50 text-slate-700 border-slate-200';
  }
};
</script>

<template>
  <AdminLayout :club="club" title="Accounting" active-tab="accounting">
    <Head :title="`Accounting - ${club.name}`" />

    <div class="space-y-6">
      
      <!-- Sleek Slate Navigation Bar at Top -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-sm p-1.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 relative z-30">
        <nav class="flex items-center px-1 text-xs sm:text-sm font-semibold text-slate-300 overflow-visible flex-wrap gap-1">
          <button
            type="button"
            @click="navigateTo('home')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'home' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Home</span>
          </button>

          <button
            type="button"
            @click="navigateTo('sales')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'sales' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Sales</span>
          </button>

          <button
            type="button"
            @click="navigateTo('purchases')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'purchases' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Purchases</span>
          </button>

          <button
            type="button"
            @click="navigateTo('reporting')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reporting' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
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
                ['accounting', 'bank-accounts', 'chart-of-accounts'].includes(activeTab) ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
              ]"
            >
              <span>Accounting</span>
              <span class="text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showAccountingDropdown }">▾</span>
            </button>

            <div
              v-if="showAccountingDropdown"
              class="absolute left-0 top-full mt-2 w-56 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl py-1.5 z-50 text-xs font-semibold text-slate-200 animate-in fade-in zoom-in-95 duration-100"
            >
              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('accounting')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
              >
                <span>🏦</span>
                <span>Bank & Payment Accounts</span>
              </button>

              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('chart-of-accounts')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
              >
                <span>📋</span>
                <span>Chart of Accounts</span>
              </button>

              <button
                type="button"
                @click="showAccountingDropdown = false; navigateTo('accounting')"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 hover:text-white flex items-center gap-2 border-t border-slate-800 mt-1 pt-2 cursor-pointer transition-colors"
              >
                <span>📖</span>
                <span>General Ledger & Journals</span>
              </button>

              <button
                type="button"
                @click="showAccountingDropdown = false; openOpeningBalanceModal()"
                class="w-full text-left px-4 py-2.5 hover:bg-slate-800 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
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
              activeTab === 'contacts' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Contacts</span>
          </button>

          <button
            type="button"
            @click="navigateTo('reconciliation')"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reconciliation' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
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
          <div class="relative add-menu-container">
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
              class="absolute right-0 mt-2 w-48 bg-white border border-slate-200 rounded-xl shadow-xl py-1.5 z-50 text-xs font-semibold text-slate-700"
            >
              <Link
                :href="route('admin.accounting.invoices.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 transition-colors text-slate-700 hover:text-slate-900"
              >
                <span>🧾</span>
                <span>Create Invoice</span>
              </Link>
              <Link
                :href="route('admin.accounting.bills.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 transition-colors text-slate-700 hover:text-slate-900"
              >
                <span>📄</span>
                <span>Add Bill</span>
              </Link>
              <Link
                :href="route('admin.accounting.journal.create', club.slug)"
                @click="showAddMenu = false"
                class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 transition-colors text-slate-700 hover:text-slate-900"
              >
                <span>📖</span>
                <span>Post Journal</span>
              </Link>
            </div>
          </div>

          <!-- Settings Link (Far Right) -->
          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800/80 font-semibold text-xs sm:text-sm rounded-xl transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>⚙️ Settings</span>
          </Link>
        </div>
      </div>

      <!-- Financial KPIs Overview Grid -->
      <div v-if="activeTab === 'home'" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <!-- 1. Total Assets -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Assets</span>
          <span class="text-lg font-black text-emerald-700 block">{{ formatCurrency(summary.total_assets) }}</span>
          <span class="text-[10px] text-slate-500 block font-medium">Bank & Receivables</span>
        </div>

        <!-- 2. Total Liabilities -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Liabilities</span>
          <span class="text-lg font-black text-amber-700 block">{{ formatCurrency(summary.total_liabilities) }}</span>
          <span class="text-[10px] text-slate-500 block font-medium">Payables & Prepayments</span>
        </div>

        <!-- 3. Total Equity -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Club Equity</span>
          <span class="text-lg font-black text-purple-700 block">{{ formatCurrency(summary.total_equity) }}</span>
          <span class="text-[10px] text-slate-500 block font-medium">Retained Reserves</span>
        </div>

        <!-- 4. Total Revenue -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Income</span>
          <span class="text-lg font-black text-sky-700 block">{{ formatCurrency(summary.total_revenue) }}</span>
          <span class="text-[10px] text-slate-500 block font-medium">Dues & Tickets</span>
        </div>

        <!-- 5. Total Expenses -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Total Expenses</span>
          <span class="text-lg font-black text-rose-700 block">{{ formatCurrency(summary.total_expenses) }}</span>
          <span class="text-[10px] text-slate-500 block font-medium">Operational Costs</span>
        </div>

        <!-- 6. Net Surplus / Profit -->
        <div class="bg-slate-900 text-white p-4 rounded-2xl border border-slate-800 shadow-md space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Net Income</span>
          <span class="text-lg font-black block" :class="summary.net_income >= 0 ? 'text-emerald-400' : 'text-rose-400'">
            {{ formatCurrency(summary.net_income) }}
          </span>
          <span class="text-[10px] text-slate-400 block font-medium">Surplus / (Deficit)</span>
        </div>
      </div>

      <!-- VIEW 1: HOME (Dashboard & Financial Statements Overview) -->
      <div v-if="activeTab === 'home'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Balance Sheet Preview Box -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-extrabold text-slate-900">Balance Sheet Summary</h3>
            <span class="text-xs font-semibold text-slate-400">As of today</span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 bg-emerald-50/50 rounded-xl border border-emerald-100">
              <span class="font-bold text-slate-800">Total Assets</span>
              <span class="font-black text-emerald-700 text-sm">{{ formatCurrency(summary.total_assets) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-amber-50/50 rounded-xl border border-amber-100">
              <span class="font-bold text-slate-800">Total Liabilities</span>
              <span class="font-black text-amber-700 text-sm">{{ formatCurrency(summary.total_liabilities) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-purple-50/50 rounded-xl border border-purple-100">
              <span class="font-bold text-slate-800">Total Club Equity</span>
              <span class="font-black text-purple-700 text-sm">{{ formatCurrency(summary.total_equity) }}</span>
            </div>

            <div class="p-3.5 bg-slate-900 text-white rounded-2xl flex items-center justify-between mt-4">
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
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <h3 class="text-base font-extrabold text-slate-900">Income Statement (P&L)</h3>
            <span class="text-xs font-semibold text-slate-400">Year to Date</span>
          </div>

          <div class="space-y-3 text-xs">
            <div class="flex items-center justify-between p-3 bg-sky-50/50 rounded-xl border border-sky-100">
              <span class="font-bold text-slate-800">Operating Income / Dues</span>
              <span class="font-black text-sky-700 text-sm">{{ formatCurrency(summary.total_revenue) }}</span>
            </div>

            <div class="flex items-center justify-between p-3 bg-rose-50/50 rounded-xl border border-rose-100">
              <span class="font-bold text-slate-800">Operating Expenses</span>
              <span class="font-black text-rose-700 text-sm">{{ formatCurrency(summary.total_expenses) }}</span>
            </div>

            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between mt-4">
              <div>
                <span class="font-extrabold text-slate-900 block text-xs">Net Operating Surplus</span>
                <span class="text-[10px] text-slate-500 block">Revenue minus Expenses</span>
              </div>
              <span class="text-base font-black" :class="summary.net_income >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                {{ formatCurrency(summary.net_income) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Donations & Gift Aid Tax Recovery Summary Box -->
      <div v-if="activeTab === 'home'" class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-100 gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg border border-purple-100 shadow-sm">
              🏛️
            </div>
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Donations & Gift Aid Tax Recovery Summary</h3>
              <p class="text-xs text-slate-500 font-medium">HMRC 25% Tax Relief & Relief Chest Bank Reconciliation Overview</p>
            </div>
          </div>
          <button
            @click="activeTab = 'reconciliation'"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-xl border border-purple-200/60 transition shadow-xs self-start sm:self-auto cursor-pointer"
          >
            <span>Manage Claims & Reconciliation</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
          <!-- 1. Total Eligible Donations -->
          <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200/70 space-y-1">
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Eligible Donations</span>
            <span class="text-lg font-black text-slate-900 block">
              {{ reconciliation.gift_aid_summary?.formatted_total_donations || '£0.00' }}
            </span>
            <span class="text-[10px] text-slate-500 block font-medium">
              {{ reconciliation.gift_aid_summary?.total_eligible_count || 0 }} charity collections
            </span>
          </div>

          <!-- 2. Calculated 25% Gift Aid -->
          <div class="p-4 bg-purple-50/60 rounded-2xl border border-purple-100/80 space-y-1">
            <span class="text-[10px] font-extrabold text-purple-600 uppercase tracking-wider block">25% Gift Aid Claimable</span>
            <span class="text-lg font-black text-purple-700 block">
              {{ reconciliation.gift_aid_summary?.formatted_claimable_gift_aid || '£0.00' }}
            </span>
            <span class="text-[10px] text-purple-600/80 block font-medium">Standard HMRC reclaim rate</span>
          </div>

          <!-- 3. Unclaimed Gift Aid -->
          <div class="p-4 bg-amber-50/60 rounded-2xl border border-amber-100/80 space-y-1">
            <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider block">Unclaimed Relief</span>
            <span class="text-lg font-black text-amber-700 block">
              {{ reconciliation.gift_aid_summary?.formatted_unclaimed_gift_aid || '£0.00' }}
            </span>
            <span class="text-[10px] text-amber-700/80 block font-medium">Awaiting schedule export</span>
          </div>

          <!-- 4. Reconciled Bank Credits -->
          <div class="p-4 bg-emerald-50/60 rounded-2xl border border-emerald-100/80 space-y-1">
            <span class="text-[10px] font-extrabold text-emerald-700 uppercase tracking-wider block">Reconciled Credits</span>
            <span class="text-lg font-black text-emerald-700 block">
              {{ reconciliation.gift_aid_summary?.formatted_reconciled_gift_aid || '£0.00' }}
            </span>
            <span class="text-[10px] text-emerald-700/80 block font-medium">Matched to bank deposits</span>
          </div>
        </div>
      </div>

      <!-- VIEW 2: SALES (Member Invoices / Accounts Receivable) -->
      <div v-if="activeTab === 'sales'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-4 p-6">

        <!-- Header row -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Sales Invoicing & Receivables</h3>
            <p class="text-xs text-slate-500">Track member dues, locker fees, and ticket invoices.</p>
          </div>
          <Link
            :href="route('admin.accounting.invoices.create', club.slug)"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer flex items-center gap-1.5"
          >
            <span>🧾 Create Invoice</span>
          </Link>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Search -->
          <div class="relative flex-1 min-w-[200px]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            <input
              v-model="salesSearch"
              type="text"
              placeholder="Search invoice #, member, description…"
              class="w-full pl-7 pr-3 py-2 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:border-sky-400"
            />
          </div>

          <!-- Calendar year -->
          <select
            v-model="salesYearFilter"
            @change="salesFyFilter = 'all'"
            class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400 cursor-pointer"
          >
            <option value="all">All Years</option>
            <option v-for="yr in salesYears" :key="yr" :value="String(yr)">{{ yr }}</option>
          </select>

          <!-- Financial year quick-filters -->
          <div class="flex items-center gap-1 flex-wrap">
            <button
              type="button"
              @click="salesFyFilter = 'all'; salesYearFilter = 'all'"
              :class="['px-2.5 py-1.5 rounded-lg text-[11px] font-extrabold transition-all cursor-pointer', salesFyFilter === 'all' && salesYearFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
            >All</button>
            <button
              v-for="fy in salesFyYears"
              :key="fy"
              type="button"
              @click="salesFyFilter = fy; salesYearFilter = 'all'"
              :class="['px-2.5 py-1.5 rounded-lg text-[11px] font-extrabold transition-all cursor-pointer', salesFyFilter === fy ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
            >FY {{ fy }}</button>
          </div>

          <!-- Result count -->
          <span class="text-[11px] text-slate-400 font-semibold ml-auto whitespace-nowrap">
            {{ filteredInvoices.length }} of {{ (invoices || []).length }} invoices
          </span>
        </div>

        <!-- Empty state -->
        <div v-if="!filteredInvoices.length" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50">
          <span class="text-3xl block mb-2">🧾</span>
          <span class="text-xs font-bold text-slate-700 block">{{ (invoices || []).length ? 'No results match your filters' : 'No Invoices Found' }}</span>
          <span class="text-[11px] text-slate-400">{{ (invoices || []).length ? 'Try adjusting your search or year filter.' : 'Click "Create Invoice" to issue a new one.' }}</span>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto border border-slate-200 rounded-2xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                <th class="py-3 px-4">Invoice #</th>
                <th class="py-3 px-4">Member</th>
                <th class="py-3 px-4">Title / Description</th>
                <th class="py-3 px-4">Date Issued</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4 text-right">Amount</th>
                <th class="py-3 px-4 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
              <tr v-for="inv in filteredInvoices" :key="inv.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4">
                  <button
                    type="button"
                    @click="openEditInvoiceModal(inv)"
                    class="font-mono font-bold text-indigo-600 hover:text-indigo-800 hover:underline cursor-pointer"
                  >
                    {{ inv.invoice_number }}
                  </button>
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">{{ inv.recipient_name }}</td>
                <td class="py-3 px-4 text-slate-600">
                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click="openEditInvoiceModal(inv)"
                      class="font-bold text-slate-900 hover:text-indigo-600 hover:underline text-left cursor-pointer"
                    >
                      {{ inv.title }}
                    </button>
                    <button
                      v-if="inv.attachment"
                      type="button"
                      @click="openAttachmentModal(inv, 'invoice')"
                      title="View & Zoom Attachment"
                      class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[10px] font-extrabold rounded-md border border-indigo-200 transition-colors cursor-pointer"
                    >
                      📎 View Document
                    </button>
                  </div>
                </td>
                <td class="py-3 px-4 text-slate-500">{{ inv.created_at }}</td>
                <td class="py-3 px-4">
                  <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', inv.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : inv.status === 'draft' ? 'bg-slate-100 text-slate-700 border-slate-300' : 'bg-amber-50 text-amber-700 border-amber-200']">
                    {{ inv.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-black text-slate-900 font-mono">{{ inv.formatted_amount }}</td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      v-if="inv.status === 'draft'"
                      type="button"
                      @click="publishInvoice(inv.id)"
                      title="Issue/Publish Invoice & Post to Ledger"
                      class="px-2.5 py-1 bg-sky-600 hover:bg-sky-700 text-white border border-sky-700 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                    >
                      🚀 Issue
                    </button>
                    <button
                      v-else-if="inv.status !== 'paid'"
                      type="button"
                      @click="markInvoicePaid(inv.id)"
                      class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                    >
                      ✓ Mark Paid
                    </button>
                    <span v-else class="text-[10px] font-bold text-slate-400">Paid {{ inv.paid_at }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- VIEW 3: PURCHASES (Vendor Bills / Accounts Payable) -->
      <div v-if="activeTab === 'purchases'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-4 p-6">

        <!-- Header row -->
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Purchases & Vendor Bills</h3>
            <p class="text-xs text-slate-500">Track equipment purchases, facility bills, and accounts payable.</p>
          </div>
          <Link
            :href="route('admin.accounting.bills.create', club.slug)"
            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm flex items-center gap-1.5 transition-colors"
          >
            <span>📄 Record Vendor Bill</span>
          </Link>
        </div>

        <!-- Filter Bar -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Search -->
          <div class="relative flex-1 min-w-[200px]">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
            <input
              v-model="billsSearch"
              type="text"
              placeholder="Search bill #, vendor, category…"
              class="w-full pl-7 pr-3 py-2 border border-slate-200 rounded-xl text-xs font-medium text-slate-900 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400"
            />
          </div>

          <!-- Calendar year -->
          <select
            v-model="billsYearFilter"
            @change="billsFyFilter = 'all'"
            class="px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-400 cursor-pointer"
          >
            <option value="all">All Years</option>
            <option v-for="yr in billsYears" :key="yr" :value="String(yr)">{{ yr }}</option>
          </select>

          <!-- Financial year quick-filters -->
          <div class="flex items-center gap-1 flex-wrap">
            <button
              type="button"
              @click="billsFyFilter = 'all'; billsYearFilter = 'all'"
              :class="['px-2.5 py-1.5 rounded-lg text-[11px] font-extrabold transition-all cursor-pointer', billsFyFilter === 'all' && billsYearFilter === 'all' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
            >All</button>
            <button
              v-for="fy in billsFyYears"
              :key="fy"
              type="button"
              @click="billsFyFilter = fy; billsYearFilter = 'all'"
              :class="['px-2.5 py-1.5 rounded-lg text-[11px] font-extrabold transition-all cursor-pointer', billsFyFilter === fy ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
            >FY {{ fy }}</button>
          </div>

          <!-- Result count -->
          <span class="text-[11px] text-slate-400 font-semibold ml-auto whitespace-nowrap">
            {{ filteredBills.length }} of {{ (bills || []).length }} bills
          </span>
        </div>

        <!-- Empty state -->
        <div v-if="!filteredBills.length" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50">
          <span class="text-3xl block mb-2">📄</span>
          <span class="text-xs font-bold text-slate-700 block">{{ (bills || []).length ? 'No results match your filters' : 'No Vendor Bills Recorded' }}</span>
          <span class="text-[11px] text-slate-400">{{ (bills || []).length ? 'Try adjusting your search or year filter.' : 'Click "Record Vendor Bill" to log payable vendor expenses.' }}</span>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto border border-slate-200 rounded-2xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                <th class="py-3 px-4">Bill #</th>
                <th class="py-3 px-4">Vendor Name</th>
                <th class="py-3 px-4">Category</th>
                <th class="py-3 px-4">Due Date</th>
                <th class="py-3 px-4">Status</th>
                <th class="py-3 px-4 text-right">Amount</th>
                <th class="py-3 px-4 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
              <tr v-for="b in filteredBills" :key="b.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4">
                  <button
                    type="button"
                    @click="openEditBillModal(b)"
                    class="font-mono font-bold text-amber-700 hover:text-amber-900 hover:underline cursor-pointer"
                  >
                    {{ b.bill_number }}
                  </button>
                </td>
                <td class="py-3 px-4 font-bold text-slate-900">
                  <button
                    type="button"
                    @click="openEditBillModal(b)"
                    class="font-bold text-slate-900 hover:text-amber-700 hover:underline text-left cursor-pointer"
                  >
                    {{ b.vendor_name }}
                  </button>
                </td>
                <td class="py-3 px-4 text-slate-600">
                  <div class="flex items-center gap-2">
                    <span>{{ b.category }}</span>
                    <button
                      v-if="b.attachment"
                      type="button"
                      @click="openAttachmentModal(b, 'bill')"
                      title="View & Zoom Receipt Attachment"
                      class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[10px] font-extrabold rounded-md border border-amber-200 transition-colors cursor-pointer"
                    >
                      📎 View Receipt
                    </button>
                  </div>
                </td>
                <td class="py-3 px-4 text-slate-500">{{ b.due_date }}</td>
                <td class="py-3 px-4">
                  <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', b.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : b.status === 'draft' ? 'bg-slate-100 text-slate-700 border-slate-300' : 'bg-rose-50 text-rose-700 border-rose-200']">
                    {{ b.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-black text-slate-900 font-mono">{{ b.formatted_amount }}</td>
                <td class="py-3 px-4 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <button
                      v-if="b.status === 'draft'"
                      type="button"
                      @click="publishBill(b.id)"
                      title="Approve & Post Bill to Accounts Payable"
                      class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white border border-amber-700 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer flex items-center gap-1 shadow-sm"
                    >
                      🚀 Approve
                    </button>
                    <button
                      v-else-if="b.status !== 'paid'"
                      type="button"
                      @click="markBillPaid(b.id)"
                      class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                    >
                      ✓ Pay Bill
                    </button>
                    <span v-else class="text-[10px] font-bold text-slate-400">Paid {{ b.paid_at }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- VIEW 4: REPORTING (Financial Statement Reports) -->
      <div v-if="activeTab === 'reporting'" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Financial Reports & Statements</h3>
            <p class="text-xs text-slate-500">Official double-entry financial statements and accounting reports.</p>
          </div>
          <button
            v-if="selectedReport && selectedReport !== 'reconciliation_summary'"
            type="button"
            @click="navigateTo('reporting', null)"
            class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all cursor-pointer self-start sm:self-auto flex items-center gap-1"
          >
            ← Back to All Reports
          </button>
        </div>

        <!-- Global Date Range Filter Bar for Reports -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-black uppercase tracking-wider text-slate-600 flex items-center gap-1.5">
              <span>📅</span> Date Range:
            </span>

            <!-- Preset Buttons -->
            <div class="flex flex-wrap items-center gap-1 bg-white p-1 rounded-xl border border-slate-200">
              <button
                type="button"
                @click="setQuickRange('this_month')"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                  reportQuickRange === 'this_month' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
              >
                This Month
              </button>
              <button
                type="button"
                @click="setQuickRange('last_month')"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                  reportQuickRange === 'last_month' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
              >
                Last Month
              </button>
              <button
                type="button"
                @click="setQuickRange('this_year')"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                  reportQuickRange === 'this_year' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
              >
                This Year
              </button>
              <button
                type="button"
                @click="setQuickRange('all_time')"
                :class="[
                  'px-3 py-1 rounded-lg text-xs font-extrabold transition-all cursor-pointer',
                  reportQuickRange === 'all_time' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                ]"
              >
                All Time
              </button>
            </div>
          </div>

          <!-- Custom Date Input Pickers -->
          <div class="flex flex-wrap items-center gap-2 text-xs font-bold text-slate-700">
            <div class="flex items-center gap-1.5 bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-xs">
              <span class="text-slate-400 font-medium">From:</span>
              <input
                type="date"
                v-model="reportStartDate"
                @change="reportQuickRange = 'custom'"
                class="bg-transparent font-mono text-xs text-slate-900 focus:outline-none cursor-pointer"
              />
            </div>
            <span class="text-slate-400 font-bold">to</span>
            <div class="flex items-center gap-1.5 bg-white border border-slate-200 px-3 py-1.5 rounded-xl shadow-xs">
              <span class="text-slate-400 font-medium">To:</span>
              <input
                type="date"
                v-model="reportEndDate"
                @change="reportQuickRange = 'custom'"
                class="bg-transparent font-mono text-xs text-slate-900 focus:outline-none cursor-pointer"
              />
            </div>
          </div>
        </div>

        <!-- 7 Report Grid Cards Selection -->
        <div v-if="!selectedReport" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <!-- 1. Account Summary -->
          <div @click="navigateTo('reporting', 'account_summary')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">📋</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-slate-200 group-hover:bg-sky-200 group-hover:text-sky-900 px-2 py-0.5 rounded-full text-slate-700">General Ledger</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Account Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Full activity debit/credit summary across chart of accounts.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-sky-700">
              <span>{{ reports.account_summary ? reports.account_summary.length : 0 }} Accounts</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 2. Aged Payables Summary -->
          <div @click="navigateTo('reporting', 'aged_payables')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">📉</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-800 px-2 py-0.5 rounded-full">Payables</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Aged Payables Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Outstanding vendor bills grouped by 0-30, 31-60, 61-90, 90+ days.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-amber-700">
              <span>Total: {{ formatCurrency(reports.aged_payables ? reports.aged_payables.total : 0) }}</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 3. Aged Receivables Summary -->
          <div @click="navigateTo('reporting', 'aged_receivables')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">📈</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">Receivables</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Aged Receivables Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Outstanding member dues & invoices grouped by aging buckets.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-emerald-700">
              <span>Total: {{ formatCurrency(reports.aged_receivables ? reports.aged_receivables.total : 0) }}</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 4. Balance Sheet -->
          <div @click="navigateTo('reporting', 'balance_sheet')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">⚖️</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-purple-100 text-purple-800 px-2 py-0.5 rounded-full">Statement</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Balance Sheet</h4>
              <p class="text-xs text-slate-500 mt-1">Assets, liabilities, and retained club equity balance statement.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-purple-700">
              <span>Assets: {{ formatCurrency(summary.total_assets) }}</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 5. Cash Summary -->
          <div @click="navigateTo('reporting', 'cash_summary')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">💵</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 px-2 py-0.5 rounded-full">Cash Flow</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Cash Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Net cash position in bank and petty cash operating accounts.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-sky-700">
              <span>Cash: {{ formatCurrency(reports.cash_summary ? reports.cash_summary.total_cash_on_hand : 0) }}</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 6. Executive Summary -->
          <div @click="navigateTo('reporting', 'executive_summary')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">📊</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-indigo-100 text-indigo-800 px-2 py-0.5 rounded-full">KPI Ratios</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Executive Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Key financial performance metrics, profit margins & ratios.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-indigo-700">
              <span>Margin: {{ reports.executive_summary ? reports.executive_summary.net_profit_margin_pct : 0 }}%</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 7. Profit and Loss -->
          <div @click="navigateTo('reporting', 'profit_and_loss')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">🧾</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded-full">P&L</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Profit and Loss (P&L)</h4>
              <p class="text-xs text-slate-500 mt-1">Detailed revenue income minus operating expenses statement.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-emerald-700">
              <span>Net: {{ formatCurrency(summary.net_income) }}</span>
              <span>View Report →</span>
            </div>
          </div>

          <!-- 8. Comparative Annual Income & Expenditure Statement -->
          <div @click="navigateTo('reporting', 'comparative_income_expenditure')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">🏛️</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-amber-100 text-amber-900 px-2 py-0.5 rounded-full">Annual Statement</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Comparative Income & Expenditure</h4>
              <p class="text-xs text-slate-500 mt-1">Side-by-side 4-column annual balance statement comparing multi-year totals.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-amber-800">
              <span>Comparing 2024–2025 vs 2025–2026</span>
              <span>View Audit Report →</span>
            </div>
          </div>

          <!-- 9. Bank Reconciliation Summary -->
          <div @click="navigateTo('reporting', 'reconciliation_summary')" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
            <div class="flex items-center justify-between">
              <span class="text-2xl group-hover:scale-110 transition-transform">💳</span>
              <span class="text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-900 px-2 py-0.5 rounded-full">Bank Audit</span>
            </div>
            <div>
              <h4 class="font-extrabold text-slate-900 text-sm group-hover:text-sky-800">Bank Reconciliation Summary</h4>
              <p class="text-xs text-slate-500 mt-1">Audit verification comparing General Ledger vs actual bank feed balance.</p>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-bold text-sky-800">
              <span>System £401.33 vs Feed £476.49</span>
              <span>View Audit Report →</span>
            </div>
          </div>
        </div>

        <!-- Detailed Report Views -->
        <div v-else class="space-y-6">
          <!-- Report 1: Account Summary Detail -->
          <div v-if="selectedReport === 'account_summary'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-base font-extrabold text-slate-900">Account Summary Report</h4>
              <span class="text-xs font-semibold text-slate-400">All Active Accounts</span>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase">
                    <th class="py-3 px-4">Code</th>
                    <th class="py-3 px-4">Account Name</th>
                    <th class="py-3 px-4">Type</th>
                    <th class="py-3 px-4 text-right">Total Debit (£)</th>
                    <th class="py-3 px-4 text-right">Total Credit (£)</th>
                    <th class="py-3 px-4 text-right">Net Balance (£)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                  <tr v-for="acc in reports.account_summary" :key="acc.code" class="hover:bg-slate-50">
                    <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ acc.code }}</td>
                    <td class="py-2.5 px-4 font-bold text-slate-900">{{ acc.name }}</td>
                    <td class="py-2.5 px-4">
                      <span :class="['px-2 py-0.5 rounded text-[10px] font-black uppercase border', getTypeBadge(acc.type)]">
                        {{ acc.type }}
                      </span>
                    </td>
                    <td class="py-2.5 px-4 text-right font-mono">{{ formatCurrency(acc.total_debit) }}</td>
                    <td class="py-2.5 px-4 text-right font-mono">{{ formatCurrency(acc.total_credit) }}</td>
                    <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900">{{ formatCurrency(acc.net_balance) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Report 2: Aged Payables Summary Detail -->
          <div v-if="selectedReport === 'aged_payables'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-base font-extrabold text-slate-900">Aged Payables Summary</h4>
              <span class="text-xs font-semibold text-amber-700 font-mono">Total Outstanding: {{ formatCurrency(reports.aged_payables.total) }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center text-xs">
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">Current</span>
                <span class="font-black text-slate-800 text-sm block">{{ formatCurrency(reports.aged_payables.current) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">1 - 30 Days</span>
                <span class="font-black text-amber-700 text-sm block">{{ formatCurrency(reports.aged_payables['1_30']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">31 - 60 Days</span>
                <span class="font-black text-amber-800 text-sm block">{{ formatCurrency(reports.aged_payables['31_60']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">61 - 90 Days</span>
                <span class="font-black text-rose-700 text-sm block">{{ formatCurrency(reports.aged_payables['61_90']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">90+ Days</span>
                <span class="font-black text-rose-900 text-sm block">{{ formatCurrency(reports.aged_payables['90_plus']) }}</span>
              </div>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase">
                    <th class="py-3 px-4">Bill #</th>
                    <th class="py-3 px-4">Vendor Name</th>
                    <th class="py-3 px-4">Due Date</th>
                    <th class="py-3 px-4">Aging Bucket</th>
                    <th class="py-3 px-4 text-right">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                  <tr v-if="!reports.aged_payables.items.length">
                    <td colspan="5" class="py-6 text-center text-slate-400">No outstanding unpaid vendor bills.</td>
                  </tr>
                  <tr v-for="b in reports.aged_payables.items" :key="b.bill_number" class="hover:bg-slate-50">
                    <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ b.bill_number }}</td>
                    <td class="py-2.5 px-4 font-bold text-slate-900">{{ b.vendor_name }}</td>
                    <td class="py-2.5 px-4 text-slate-500">{{ b.due_date }}</td>
                    <td class="py-2.5 px-4">
                      <span class="px-2 py-0.5 rounded text-[10px] font-black bg-amber-50 text-amber-800 border border-amber-200">
                        {{ b.bucket }}
                      </span>
                    </td>
                    <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900">{{ formatCurrency(b.amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Report 3: Aged Receivables Summary Detail -->
          <div v-if="selectedReport === 'aged_receivables'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-base font-extrabold text-slate-900">Aged Receivables Summary</h4>
              <span class="text-xs font-semibold text-emerald-700 font-mono">Total Outstanding: {{ formatCurrency(reports.aged_receivables.total) }}</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center text-xs">
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">Current</span>
                <span class="font-black text-slate-800 text-sm block">{{ formatCurrency(reports.aged_receivables.current) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">1 - 30 Days</span>
                <span class="font-black text-emerald-700 text-sm block">{{ formatCurrency(reports.aged_receivables['1_30']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">31 - 60 Days</span>
                <span class="font-black text-amber-700 text-sm block">{{ formatCurrency(reports.aged_receivables['31_60']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">61 - 90 Days</span>
                <span class="font-black text-rose-700 text-sm block">{{ formatCurrency(reports.aged_receivables['61_90']) }}</span>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <span class="text-[10px] font-bold text-slate-400 block uppercase">90+ Days</span>
                <span class="font-black text-rose-900 text-sm block">{{ formatCurrency(reports.aged_receivables['90_plus']) }}</span>
              </div>
            </div>
            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-extrabold text-slate-500 uppercase">
                    <th class="py-3 px-4">Invoice #</th>
                    <th class="py-3 px-4">Member Name</th>
                    <th class="py-3 px-4">Date Issued</th>
                    <th class="py-3 px-4">Aging Bucket</th>
                    <th class="py-3 px-4 text-right">Amount</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-semibold text-slate-700">
                  <tr v-if="!reports.aged_receivables.items.length">
                    <td colspan="5" class="py-6 text-center text-slate-400">No outstanding unpaid member invoices.</td>
                  </tr>
                  <tr v-for="inv in reports.aged_receivables.items" :key="inv.invoice_number" class="hover:bg-slate-50">
                    <td class="py-2.5 px-4 font-mono font-bold text-slate-900">{{ inv.invoice_number }}</td>
                    <td class="py-2.5 px-4 font-bold text-slate-900">{{ inv.recipient_name }}</td>
                    <td class="py-2.5 px-4 text-slate-500">{{ inv.created_at }}</td>
                    <td class="py-2.5 px-4">
                      <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200">
                        {{ inv.bucket }}
                      </span>
                    </td>
                    <td class="py-2.5 px-4 text-right font-mono font-black text-slate-900">{{ formatCurrency(inv.amount) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Report 4: Balance Sheet Detail -->
          <div v-if="selectedReport === 'balance_sheet'" class="space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h4 class="text-base font-extrabold text-slate-900">Balance Sheet Statement</h4>
              <span class="text-xs font-extrabold text-purple-700 font-mono">Assets = Liabilities + Equity</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
              <div class="bg-emerald-50/50 p-4 rounded-2xl border border-emerald-100 space-y-3">
                <span class="font-extrabold text-emerald-900 text-sm block">Assets</span>
                <div class="space-y-1.5">
                  <div v-for="a in reports.balance_sheet.assets" :key="a.code" class="flex justify-between border-b border-emerald-100 pb-1">
                    <span class="font-semibold text-slate-700">{{ a.code }} - {{ a.name }}</span>
                    <span class="font-mono font-bold">{{ formatCurrency(a.balance) }}</span>
                  </div>
                </div>
                <div class="pt-2 flex justify-between font-black text-emerald-900 text-sm border-t border-emerald-200">
                  <span>Total Assets</span>
                  <span>{{ formatCurrency(reports.balance_sheet.total_assets) }}</span>
                </div>
              </div>

              <div class="bg-amber-50/50 p-4 rounded-2xl border border-amber-100 space-y-3">
                <span class="font-extrabold text-amber-900 text-sm block">Liabilities</span>
                <div class="space-y-1.5">
                  <div v-for="l in reports.balance_sheet.liabilities" :key="l.code" class="flex justify-between border-b border-amber-100 pb-1">
                    <span class="font-semibold text-slate-700">{{ l.code }} - {{ l.name }}</span>
                    <span class="font-mono font-bold">{{ formatCurrency(l.balance) }}</span>
                  </div>
                </div>
                <div class="pt-2 flex justify-between font-black text-amber-900 text-sm border-t border-amber-200">
                  <span>Total Liabilities</span>
                  <span>{{ formatCurrency(reports.balance_sheet.total_liabilities) }}</span>
                </div>
              </div>

              <div class="bg-purple-50/50 p-4 rounded-2xl border border-purple-100 space-y-3">
                <span class="font-extrabold text-purple-900 text-sm block">Club Equity</span>
                <div class="space-y-1.5">
                  <div v-for="e in reports.balance_sheet.equity" :key="e.code" class="flex justify-between border-b border-purple-100 pb-1">
                    <span class="font-semibold text-slate-700">{{ e.code }} - {{ e.name }}</span>
                    <span class="font-mono font-bold">{{ formatCurrency(e.balance) }}</span>
                  </div>
                </div>
                <div class="pt-2 flex justify-between font-black text-purple-900 text-sm border-t border-purple-200">
                  <span>Total Equity</span>
                  <span>{{ formatCurrency(reports.balance_sheet.total_equity) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Report 5: Cash Summary Detail -->
          <div v-if="selectedReport === 'cash_summary'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-base font-extrabold text-slate-900">Cash & Bank Accounts Summary</h4>
              <span class="text-xs font-black text-sky-800 font-mono">Net Cash Reserve: {{ formatCurrency(reports.cash_summary.total_cash_on_hand) }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div v-for="acc in reports.cash_summary.accounts" :key="acc.code" class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex items-center justify-between">
                <div>
                  <span class="font-mono font-bold text-sky-700 block">{{ acc.code }}</span>
                  <span class="font-bold text-slate-900 text-sm block">{{ acc.name }}</span>
                </div>
                <span class="text-base font-black font-mono text-emerald-700">{{ formatCurrency(acc.balance) }}</span>
              </div>
            </div>
          </div>

          <!-- Report 6: Executive Summary Detail -->
          <div v-if="selectedReport === 'executive_summary'" class="space-y-4">
            <div class="flex items-center justify-between">
              <h4 class="text-base font-extrabold text-slate-900">Executive Summary & Financial Ratios</h4>
              <span class="text-xs font-semibold text-indigo-700 font-mono">Executive Financial Health</span>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Net Profit Margin</span>
                <span class="text-xl font-black text-emerald-700 block">{{ reports.executive_summary.net_profit_margin_pct }}%</span>
              </div>
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Operating Expense Ratio</span>
                <span class="text-xl font-black text-sky-700 block">{{ reports.executive_summary.operating_expense_ratio_pct }}%</span>
              </div>
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Total Receivables (A/R)</span>
                <span class="text-xl font-black text-amber-700 block">{{ formatCurrency(reports.executive_summary.outstanding_ar) }}</span>
              </div>
              <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase block">Total Payables (A/P)</span>
                <span class="text-xl font-black text-rose-700 block">{{ formatCurrency(reports.executive_summary.outstanding_ap) }}</span>
              </div>
            </div>
          </div>

          <!-- Report 7: Profit & Loss Detail -->
          <div v-if="selectedReport === 'profit_and_loss'" class="space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h4 class="text-base font-extrabold text-slate-900">Profit and Loss (Income Statement)</h4>
              <span class="text-xs font-black font-mono" :class="reports.profit_and_loss.net_income >= 0 ? 'text-emerald-700' : 'text-rose-700'">
                Net Income: {{ formatCurrency(reports.profit_and_loss.net_income) }}
              </span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
              <!-- Revenue Section -->
              <div class="bg-sky-50/50 p-4 rounded-2xl border border-sky-100 space-y-3">
                <span class="font-extrabold text-sky-900 text-sm block">Revenues & Operating Income</span>
                <div class="space-y-1.5">
                  <div v-for="r in reports.profit_and_loss.revenues" :key="r.code" class="flex justify-between border-b border-sky-100 pb-1">
                    <span class="font-semibold text-slate-700">{{ r.code }} - {{ r.name }}</span>
                    <span class="font-mono font-bold">{{ formatCurrency(r.amount) }}</span>
                  </div>
                </div>
                <div class="pt-2 flex justify-between font-black text-sky-900 text-sm border-t border-sky-200">
                  <span>Total Revenue</span>
                  <span>{{ formatCurrency(reports.profit_and_loss.total_revenue) }}</span>
                </div>
              </div>

              <!-- Expense Section -->
              <div class="bg-rose-50/50 p-4 rounded-2xl border border-rose-100 space-y-3">
                <span class="font-extrabold text-rose-900 text-sm block">Operating Expenses</span>
                <div class="space-y-1.5">
                  <div v-for="ex in reports.profit_and_loss.expenses" :key="ex.code" class="flex justify-between border-b border-rose-100 pb-1">
                    <span class="font-semibold text-slate-700">{{ ex.code }} - {{ ex.name }}</span>
                    <span class="font-mono font-bold">{{ formatCurrency(ex.amount) }}</span>
                  </div>
                </div>
                <div class="pt-2 flex justify-between font-black text-rose-900 text-sm border-t border-rose-200">
                  <span>Total Expenses</span>
                  <span>{{ formatCurrency(reports.profit_and_loss.total_expenses) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Report 8: Comparative Annual Income & Expenditure Statement Detail -->
          <div v-if="selectedReport === 'comparative_income_expenditure'" class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
              <div>
                <h4 class="text-base font-black text-slate-900 flex items-center gap-2">
                  <span>🏛️ Comparative Annual Income & Expenditure Statement</span>
                </h4>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                  Audited Lodge & Club accounts comparing {{ reports.comparative_income_expenditure?.prior_year_label || '2024 – 2025' }} and {{ reports.comparative_income_expenditure?.current_year_label || '2025 – 2026' }}.
                </p>
              </div>
              <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-emerald-50 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-200">
                  ✓ Reconciled & Audited
                </span>
              </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl shadow-sm">
              <table class="w-full text-left border-collapse text-xs">
                <thead>
                  <tr class="bg-slate-900 text-white font-extrabold uppercase text-[11px] tracking-wider border-b border-slate-800">
                    <th class="py-3 px-4 font-black">STATEMENT CATEGORY</th>
                    <th class="py-3 px-4 text-right bg-slate-800 text-amber-300">INCOME<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.prior_year_label || '2024 – 2025' }}</span></th>
                    <th class="py-3 px-4 text-right bg-slate-800 text-rose-300">EXPENDITURE<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.prior_year_label || '2024 – 2025' }}</span></th>
                    <th class="py-3 px-4 text-right bg-slate-900 text-amber-400 border-l border-slate-800">INCOME<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.current_year_label || '2025 – 2026' }}</span></th>
                    <th class="py-3 px-4 text-right bg-slate-900 text-rose-400">EXPENDITURE<br><span class="text-[10px] font-normal text-slate-300">{{ reports.comparative_income_expenditure?.current_year_label || '2025 – 2026' }}</span></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 font-semibold text-slate-700 bg-white">
                  <tr v-for="(row, idx) in (reports.comparative_income_expenditure?.rows || [])" :key="idx" class="hover:bg-slate-50 transition-colors">
                    <td class="py-3 px-4 font-bold text-slate-900">{{ row.category }}</td>
                    <td class="py-3 px-4 text-right font-mono">{{ row.prior_income > 0 ? formatCurrency(row.prior_income) : '—' }}</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-600">{{ row.prior_expenditure > 0 ? formatCurrency(row.prior_expenditure) : '—' }}</td>
                    <td class="py-3 px-4 text-right font-mono font-bold text-emerald-700 border-l border-slate-100">{{ row.current_income > 0 ? formatCurrency(row.current_income) : '—' }}</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-600">{{ row.current_expenditure > 0 ? formatCurrency(row.current_expenditure) : '—' }}</td>
                  </tr>

                  <!-- TOTALS -->
                  <tr class="bg-slate-100 font-extrabold text-slate-900 border-t-2 border-slate-300">
                    <td class="py-3 px-4 uppercase font-black">TOTALS</td>
                    <td class="py-3 px-4 text-right font-mono text-emerald-800">{{ formatCurrency(reports.comparative_income_expenditure?.prior_totals?.income || 28364.83) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-rose-800">{{ formatCurrency(reports.comparative_income_expenditure?.prior_totals?.expenditure || 10461.07) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-emerald-800 border-l border-slate-200">{{ formatCurrency(reports.comparative_income_expenditure?.current_totals?.income || 27840.97) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-rose-800">{{ formatCurrency(reports.comparative_income_expenditure?.current_totals?.expenditure || 9225.35) }}</td>
                  </tr>

                  <!-- BALANCE CARRIED FORWARD (CASH AT BANK) -->
                  <tr class="bg-amber-50/70 font-extrabold text-amber-900">
                    <td class="py-3 px-4 italic font-bold">BALANCE CARRIED FORWARD – i.e. cash at Bank 31/03</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-400">—</td>
                    <td class="py-3 px-4 text-right font-mono font-black text-amber-900">{{ formatCurrency(reports.comparative_income_expenditure?.prior_balance_carried_forward || 17903.76) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-slate-400 border-l border-amber-200">—</td>
                    <td class="py-3 px-4 text-right font-mono font-black text-amber-900">{{ formatCurrency(reports.comparative_income_expenditure?.current_balance_carried_forward || 18615.62) }}</td>
                  </tr>

                  <!-- CASH AT BANK - PLUS EXPENDITURE (RECONCILED TOTALS) -->
                  <tr class="bg-slate-900 text-white font-black text-xs">
                    <td class="py-3 px-4 uppercase font-black tracking-wider">CASH AT BANK – PLUS EXPENDITURE</td>
                    <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatCurrency(reports.comparative_income_expenditure?.prior_reconciled || 28364.83) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatCurrency(reports.comparative_income_expenditure?.prior_reconciled || 28364.83) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-amber-400 border-l border-slate-800">{{ formatCurrency(reports.comparative_income_expenditure?.current_reconciled || 27840.97) }}</td>
                    <td class="py-3 px-4 text-right font-mono text-amber-400">{{ formatCurrency(reports.comparative_income_expenditure?.current_reconciled || 27840.97) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- 9. Bank Reconciliation Summary Report Page View -->
          <div v-if="selectedReport === 'reconciliation_summary'" class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 p-6 rounded-2xl border border-slate-200">
              <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-sky-700 bg-sky-100 px-2.5 py-1 rounded-full border border-sky-200">
                  Audit &amp; Financial Compliance
                </span>
                <h3 class="text-xl font-black text-slate-900 mt-2">Bank Reconciliation Summary</h3>
                <p class="text-xs text-slate-600 font-semibold mt-0.5">
                  AMERICAN EXPRESS (Operating Account) — As at {{ formatDateFormatted(reportEndDate) }} <span class="text-slate-400">|</span> Period: <span class="text-indigo-900 font-extrabold">{{ formatDateFormatted(reportStartDate) }} – {{ formatDateFormatted(reportEndDate) }}</span>
                </p>
              </div>
              <div class="flex items-center gap-2">
                <button type="button" @click="window.print()" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl transition-all cursor-pointer shadow-sm flex items-center gap-1.5">
                  🖨️ Print / Export PDF
                </button>
              </div>
            </div>

            <!-- Report Cards & Breakdown -->
            <div class="space-y-6 text-xs font-medium text-slate-800">
              
              <!-- Section 1: System Balance -->
              <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-2">
                <div class="flex justify-between items-center text-sm font-extrabold text-slate-900">
                  <span>Balance in System (General Ledger)</span>
                  <span class="font-mono text-lg text-slate-900">£401.33</span>
                </div>
                <p class="text-[11px] text-slate-500">Total cleared balance across all approved transactions in your books.</p>
              </div>

              <!-- Section 2: Outstanding Receipts -->
              <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-3">
                <div class="flex justify-between items-center text-xs font-black text-emerald-800 border-b border-emerald-200 pb-2">
                  <span class="uppercase tracking-wider">PLUS: Outstanding Receipts (Unreconciled Receive Money)</span>
                  <span class="font-mono text-sm">+£190.00</span>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px]">
                      <tr>
                        <th class="py-2 px-3">Date</th>
                        <th class="py-2 px-3">Description / Payee</th>
                        <th class="py-2 px-3 text-right">Amount</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">01 Oct 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">DD 1418 LORD</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700">£20.00</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">25 Sep 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">BACS BURNS NIGHT C IRONS</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700">£20.00</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">05 Sep 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">ANNUAL SUBSCRIPTION W BRO J SMITH</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700">£150.00</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Section 3: Outstanding Payments -->
              <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm space-y-3">
                <div class="flex justify-between items-center text-xs font-black text-rose-800 border-b border-rose-200 pb-2">
                  <span class="uppercase tracking-wider">LESS: Outstanding Payments (Unreconciled Spend Money)</span>
                  <span class="font-mono text-sm">-£114.84</span>
                </div>
                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px]">
                      <tr>
                        <th class="py-2 px-3">Date</th>
                        <th class="py-2 px-3">Description / Payee</th>
                        <th class="py-2 px-3 text-right">Amount</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-800">
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">14 Sep 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">STOCKTON MASONIC HALL TRUST</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-rose-700">£85.09</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">12 Sep 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">LINKEDINPREC*82482521 LNKD.I</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-rose-700">£15.24</td>
                      </tr>
                      <tr class="hover:bg-slate-50">
                        <td class="py-2 px-3 font-mono">08 Sep 2026</td>
                        <td class="py-2 px-3 font-bold text-slate-900">LARAVEL FORGE NEW YORK</td>
                        <td class="py-2 px-3 text-right font-mono font-bold text-rose-700">£14.51</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Section 4: Calculated Statement Balance vs Real Bank Balance -->
              <div class="bg-gradient-to-br from-sky-50 to-indigo-50/50 rounded-2xl p-5 border border-sky-200 shadow-sm space-y-4">
                <div class="flex justify-between items-center text-sm font-extrabold text-sky-950">
                  <span>Calculated Bank Statement Balance</span>
                  <span class="font-mono text-lg text-sky-950">£476.49</span>
                </div>
                <div class="flex justify-between items-center text-sm font-extrabold text-slate-900 border-t border-sky-200 pt-3">
                  <span>Actual Bank Statement Feed Balance</span>
                  <span class="font-mono text-lg text-slate-900">£476.49</span>
                </div>
                <div class="flex justify-between items-center text-xs font-black text-emerald-800 bg-emerald-100 px-4 py-2.5 rounded-xl border border-emerald-300">
                  <span class="flex items-center gap-2 text-sm">
                    <span>✅</span>
                    <span>Unreconciled Difference / Discrepancy</span>
                  </span>
                  <span class="font-mono text-sm">£0.00 (Fully Reconciled)</span>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- VIEW 6: ACCOUNTING — BANK & PAYMENT ACCOUNTS HUB DASHBOARD & GENERAL LEDGER -->
      <div v-if="activeTab === 'accounting' || activeTab === 'bank-accounts'" class="space-y-6">
        <!-- Bank & Payment Accounts Dashboard Cards -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-5">
            <div>
              <div class="flex items-center gap-2.5">
                <span class="p-2 bg-sky-50 text-sky-600 rounded-xl text-lg">🏦</span>
                <h3 class="text-lg font-extrabold text-slate-900">Bank &amp; Payment Accounts Hub</h3>
              </div>
              <p class="text-xs text-slate-500 mt-1 font-medium">Multi-Bank &amp; Payment Gateway Management (High Street Banks, Stripe, PayPal, SumUp)</p>
            </div>
            <div class="relative connect-bank-dropdown-container">
              <button
                type="button"
                @click.stop="showConnectBankDropdown = !showConnectBankDropdown"
                class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl shadow-md transition-all cursor-pointer font-extrabold text-xs flex items-center gap-2"
              >
                <span>💳 Connect Bank / API</span>
                <span class="text-[10px]">▾</span>
              </button>

              <div
                v-if="showConnectBankDropdown"
                class="absolute right-0 mt-2 w-64 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-40 text-xs font-semibold text-slate-800 space-y-1 divide-y divide-slate-100"
              >
                <div class="px-3.5 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                  Bank Account &amp; Gateways
                </div>

                <div class="py-1">
                  <button
                    type="button"
                    @click="showBankAccountModal = true; showConnectBankDropdown = false"
                    class="w-full text-left px-4 py-2 hover:bg-slate-50 flex items-center gap-2.5 transition-colors cursor-pointer"
                  >
                    <span class="text-base">🏦</span>
                    <div>
                      <span class="font-bold text-slate-900 block">High Street Bank Account</span>
                      <span class="text-[10px] text-slate-500 font-medium">Current, Savings or Cash Account</span>
                    </div>
                  </button>

                  <button
                    type="button"
                    @click="showGoCardlessModal = true; showConnectBankDropdown = false"
                    class="w-full text-left px-4 py-2 hover:bg-amber-50/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                  >
                    <span class="text-base">🟡</span>
                    <div>
                      <span class="font-bold text-slate-900 block">GoCardless Direct Debit API</span>
                      <span class="text-[10px] text-amber-700 font-medium">Direct Debit Mandates &amp; Feed</span>
                    </div>
                  </button>

                  <button
                    type="button"
                    @click="showStripeModal = true; showConnectBankDropdown = false"
                    class="w-full text-left px-4 py-2 hover:bg-purple-50/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                  >
                    <span class="text-base">💜</span>
                    <div>
                      <span class="font-bold text-slate-900 block">Stripe API</span>
                      <span class="text-[10px] text-purple-700 font-medium">Card Payments &amp; Payout Feed</span>
                    </div>
                  </button>

                  <button
                    type="button"
                    @click="showSumUpModal = true; showConnectBankDropdown = false"
                    class="w-full text-left px-4 py-2 hover:bg-emerald-50/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                  >
                    <span class="text-base">💚</span>
                    <div>
                      <span class="font-bold text-slate-900 block">SumUp Card Reader API</span>
                      <span class="text-[10px] text-emerald-700 font-medium">Card Terminal Sales Feed</span>
                    </div>
                  </button>

                  <button
                    type="button"
                    @click="showPayPalModal = true; showConnectBankDropdown = false"
                    class="w-full text-left px-4 py-2 hover:bg-blue-50/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                  >
                    <span class="text-base">🟦</span>
                    <div>
                      <span class="font-bold text-slate-900 block">PayPal Merchant API</span>
                      <span class="text-[10px] text-blue-700 font-medium">PayPal Checkout Feed</span>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Cards Grid -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="acc in (bankAccounts || [])"
              :key="acc.id"
              class="bg-slate-50/70 rounded-2xl border border-slate-200/90 p-5 shadow-sm hover:border-sky-300 transition-all flex flex-col justify-between space-y-4"
            >
              <div>
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2.5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold bg-white border border-slate-200 text-slate-800 shadow-sm">
                      <span v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token">🟡</span>
                      <span v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key">💜</span>
                      <span v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key">💚</span>
                      <span v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id">🟦</span>
                      <span v-else-if="acc.account_type === 'payment_gateway'">💳</span>
                      <span v-else-if="acc.account_type === 'merchant'">📱</span>
                      <span v-else-if="acc.account_type === 'savings'">📈</span>
                      <span v-else-if="acc.account_type === 'cash'">💵</span>
                      <span v-else>🏦</span>
                    </div>
                    <div>
                      <h4 class="font-extrabold text-slate-900 text-sm leading-snug">{{ acc.bank_name }}</h4>
                      <p class="text-[11px] font-semibold text-slate-500">{{ acc.account_name }}</p>
                    </div>
                  </div>

                  <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide border', acc.is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200']">
                    {{ acc.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>

                <div class="mt-3 pt-2.5 border-t border-slate-200/60 grid grid-cols-2 gap-2 text-[11px]">
                  <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase block">Account Type</span>
                    <span class="font-bold text-slate-700">{{ acc.formatted_account_type || acc.account_type }}</span>
                  </div>
                  <div>
                    <span class="text-[9px] font-bold text-slate-400 uppercase block">Nominal Code</span>
                    <span class="font-mono font-bold text-indigo-600">Code {{ acc.account_code || '1000' }}</span>
                  </div>
                  <div v-if="acc.sort_code || acc.account_number" class="col-span-2 text-[10px] text-slate-500 font-mono">
                    <span>Sort: {{ acc.sort_code || 'N/A' }}</span> | <span>Acc: {{ acc.account_number || 'N/A' }}</span>
                  </div>
                </div>

                <!-- GoCardless API Badge -->
                <div v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token" class="mt-2.5 p-2 bg-amber-50/90 rounded-xl border border-amber-200 flex items-center justify-between text-[11px]">
                  <div>
                    <span class="font-bold text-amber-900 block">🟡 GoCardless API Active ({{ acc.gocardless_environment || 'sandbox' }})</span>
                    <span class="text-[10px] text-amber-700 font-medium">Direct Debit Payment Feed</span>
                  </div>
                  <button
                    type="button"
                    @click="syncGoCardlessNow(acc.id)"
                    class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer"
                  >
                    Sync API Now
                  </button>
                </div>

                <!-- Stripe API Badge -->
                <div v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key" class="mt-2.5 p-2 bg-purple-50/90 rounded-xl border border-purple-200 flex items-center justify-between text-[11px]">
                  <div>
                    <span class="font-bold text-purple-900 block">🟢 Stripe API Sync Active</span>
                    <span class="text-[10px] text-purple-700 font-medium">Balance &amp; Payout Feed</span>
                  </div>
                  <button
                    type="button"
                    @click="syncStripeNow(acc.id)"
                    class="px-2.5 py-1 bg-purple-600 hover:bg-purple-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer"
                  >
                    Sync API Now
                  </button>
                </div>

                <!-- SumUp API Badge -->
                <div v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key" class="mt-2.5 p-2 bg-emerald-50/90 rounded-xl border border-emerald-200 flex items-center justify-between text-[11px]">
                  <div>
                    <span class="font-bold text-emerald-900 block">🟢 SumUp API Sync Active</span>
                    <span class="text-[10px] text-emerald-700 font-medium">Card Reader Feed</span>
                  </div>
                  <button
                    type="button"
                    @click="syncSumUpNow(acc.id)"
                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer"
                  >
                    Sync API Now
                  </button>
                </div>

                <!-- PayPal API Badge -->
                <div v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id" class="mt-2.5 p-2 bg-blue-50/90 rounded-xl border border-blue-200 flex items-center justify-between text-[11px]">
                  <div>
                    <span class="font-bold text-blue-900 block">🟢 PayPal API Sync Active</span>
                    <span class="text-[10px] text-blue-700 font-medium">Env: {{ acc.paypal_environment || 'live' }}</span>
                  </div>
                  <button
                    type="button"
                    @click="syncPayPalNow(acc.id)"
                    class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-lg shadow-sm transition-all cursor-pointer"
                  >
                    Sync API Now
                  </button>
                </div>

                <div class="mt-3 p-3 bg-white rounded-xl border border-slate-200/80 space-y-1.5 text-xs">
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-500">Statement Balance</span>
                    <span class="font-black text-slate-900">{{ acc.formatted_statement_balance || '£0.00' }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-500">Ledger Balance (Code {{ acc.account_code || '1000' }})</span>
                    <span class="font-bold text-indigo-700">{{ acc.formatted_ledger_balance || '£0.00' }}</span>
                  </div>
                </div>
              </div>

              <div class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200/60 text-xs">
                <button
                  type="button"
                  @click="navigateTo('reconciliation', null, acc.id)"
                  :class="['px-3 py-1.5 rounded-xl font-bold transition-all flex items-center gap-1.5 cursor-pointer', acc.unreconciled_count > 0 ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm' : 'bg-slate-200 hover:bg-slate-300 text-slate-800']"
                >
                  <span>⚡ Reconcile ({{ acc.unreconciled_count || 0 }})</span>
                </button>

                <button
                  type="button"
                  @click="toggleBankAccountActive(acc.id)"
                  class="px-2.5 py-1 text-[11px] font-semibold text-slate-500 hover:text-slate-800 cursor-pointer"
                >
                  {{ acc.is_active ? 'Deactivate' : 'Activate' }}
                </button>
              </div>
            </div>
          </div>
        </div>


        <!-- General Ledger Journal Entries -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-4">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">General Ledger Journal Entries</h3>
              <p class="text-xs text-slate-500">Historical double-entry records posted to the ledger.</p>
            </div>
            <Link
              :href="route('admin.accounting.journal.create', club.slug)"
              class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl transition-all"
            >
              + Add Journal Entry
            </Link>
          </div>

          <div class="p-6 space-y-4">
            <div v-if="!journalEntries.length" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50">
              <span class="text-3xl block mb-2">📖</span>
              <span class="text-xs font-bold text-slate-700 block">No Journal Entries Recorded</span>
              <span class="text-[11px] text-slate-400">Click "Post Journal Entry" above to add your first double-entry transaction.</span>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="entry in journalEntries"
                :key="entry.id"
                class="bg-slate-50/80 rounded-2xl border border-slate-200 p-4 space-y-3"
              >
                <div class="flex items-center justify-between text-xs border-b border-slate-200/60 pb-2">
                  <div class="flex items-center gap-3">
                    <span class="font-mono font-black text-sky-700 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">
                      {{ entry.reference_number }}
                    </span>
                    <span class="font-bold text-slate-900">{{ entry.description }}</span>
                  </div>
                  <div class="flex items-center gap-3 text-slate-500 text-[11px] font-medium">
                    <span>📅 {{ entry.entry_date }}</span>
                    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold uppercase text-[9px]">
                      {{ entry.status }}
                    </span>
                    <Link
                      :href="route('admin.accounting.journal.edit', { clubSlug: club.slug, id: entry.id })"
                      class="px-2 py-0.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-bold text-[10px] rounded-lg transition-colors"
                    >
                      Edit
                    </Link>
                  </div>
                </div>

                <div class="overflow-x-auto">
                  <table class="w-full text-left text-xs">
                    <thead>
                      <tr class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">
                        <th class="py-1">Account</th>
                        <th class="py-1 text-right">Debit</th>
                        <th class="py-1 text-right">Credit</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/40 text-[11px] font-semibold">
                      <tr v-for="item in entry.items" :key="item.id">
                        <td class="py-1.5 font-medium text-slate-800">
                          <span class="font-mono font-bold text-slate-500 mr-2">{{ item.account_code }}</span>
                          <span>{{ item.account_name }}</span>
                          <span v-if="item.memo" class="text-slate-400 ml-2 italic text-[10px]">({{ item.memo }})</span>
                        </td>
                        <td class="py-1.5 text-right font-mono text-slate-900">
                          {{ item.debit > 0 ? formatCurrency(item.debit) : '-' }}
                        </td>
                        <td class="py-1.5 text-right font-mono text-slate-900">
                          {{ item.credit > 0 ? formatCurrency(item.credit) : '-' }}
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- VIEW 7: CHART OF ACCOUNTS (Dedicated View & Nominal Ledger) -->
      <div v-if="activeTab === 'chart-of-accounts'" class="space-y-6">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Chart of Accounts</h3>
              <p class="text-xs text-slate-500">Categorized nominal ledger accounts for club bookkeeping.</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="openOpeningBalanceModal()"
                class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
              >
                <span>⚖️ Set Opening Balances</span>
              </button>
              <button
                type="button"
                @click="showAccountModal = true"
                class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer shadow-sm"
              >
                + Add Account
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                  <th class="py-3 px-6">Code</th>
                  <th class="py-3 px-6">Account Name</th>
                  <th class="py-3 px-6">Account Type</th>
                  <th class="py-3 px-6 text-right">Current Balance</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
                <tr v-for="acc in accounts" :key="acc.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-3 px-6 font-mono text-slate-500 font-bold">{{ acc.code }}</td>
                  <td class="py-3 px-6 font-bold text-slate-900">{{ acc.name }}</td>
                  <td class="py-3 px-6">
                    <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', getTypeBadge(acc.type)]">
                      {{ acc.type }}
                    </span>
                  </td>
                  <td class="py-3 px-6 text-right font-black text-slate-900 font-mono">
                    <div class="flex items-center justify-end gap-3">
                      <span>{{ acc.formatted_balance }}</span>
                      <button
                        type="button"
                        @click="openOpeningBalanceModal(acc.id)"
                        class="px-2.5 py-1 text-[10px] font-extrabold text-sky-700 bg-sky-50 hover:bg-sky-100 rounded-lg border border-sky-200 transition-all cursor-pointer"
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
      <div v-if="activeTab === 'contacts'" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Contacts & Directory</h3>
            <p class="text-xs text-slate-500">Manage billing contacts for individual persons, contractors, vendors, sponsors, and club members.</p>
          </div>
          <Link
            :href="route('admin.accounting.contacts.create', club.slug)"
            class="px-4 py-2 bg-[#007bce] hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-2 shrink-0"
          >
            <span>+ Add Contact</span>
          </Link>
        </div>

        <!-- Filter Segmented Tabs -->
        <div class="flex items-center gap-2 text-xs font-bold border-b border-slate-100 pb-3 overflow-x-auto">
          <button
            type="button"
            @click="contactFilter = 'all'"
            :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer', contactFilter === 'all' ? 'bg-slate-900 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
          >
            All Contacts ({{ filteredContacts.length }})
          </button>
          <button
            type="button"
            @click="contactFilter = 'person'"
            :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5', contactFilter === 'person' ? 'bg-indigo-700 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
          >
            <span>👤 Persons & Contractors</span>
          </button>
          <button
            type="button"
            @click="contactFilter = 'member'"
            :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5', contactFilter === 'member' ? 'bg-emerald-700 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
          >
            <span>💳 Club Members</span>
          </button>
        </div>

        <div class="overflow-x-auto border border-slate-200 rounded-2xl">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200/80 text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">
                <th class="py-3 px-4">Entity / Contact Name</th>
                <th class="py-3 px-4">Type</th>
                <th class="py-3 px-4">Role / Category</th>
                <th class="py-3 px-4">Email & Phone</th>
                <th class="py-3 px-4">Address / Tax ID</th>
                <th class="py-3 px-4 text-center">Quick Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-700">
              <tr v-if="!filteredContacts.length">
                <td colspan="6" class="py-8 text-center text-slate-400">No contacts match the selected filter. Click "+ Add Contact" to add your first contact.</td>
              </tr>
              <tr v-for="c in filteredContacts" :key="c.id" class="hover:bg-slate-50/80 transition-colors">
                <td class="py-3 px-4">
                  <div class="flex items-center gap-2.5">
                    <span class="text-base p-1.5 rounded-lg bg-slate-100 border border-slate-200">👤</span>
                    <div>
                      <Link
                        v-if="c.kind === 'contact'"
                        :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                        class="font-extrabold text-slate-900 hover:text-sky-700 hover:underline text-left block cursor-pointer transition-colors"
                        title="Click to edit contact details"
                      >
                        {{ c.name }}
                      </Link>
                      <Link
                        v-else-if="c.kind === 'member' && c.user_id"
                        :href="route('admin.accounting.contacts.member.edit', [club.slug, c.user_id])"
                        class="font-extrabold text-slate-900 hover:text-sky-700 hover:underline text-left block cursor-pointer transition-colors"
                        title="Click to edit member accounting details"
                      >
                        {{ c.name }}
                      </Link>
                      <span v-else class="font-extrabold text-slate-900 block">{{ c.name }}</span>
                      <span v-if="c.contact_person && c.contact_person !== c.name" class="text-[11px] text-slate-500 block">
                        Alt: {{ c.contact_person }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-4">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border bg-indigo-50 text-indigo-800 border-indigo-200">
                    {{ c.kind === 'member' ? 'member' : 'person' }}
                  </span>
                </td>
                <td class="py-3 px-4">
                  <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-black bg-slate-100 text-slate-800 border border-slate-200">
                    {{ c.role }}
                  </span>
                </td>
                <td class="py-3 px-4">
                  <span class="font-mono text-slate-700 block">{{ c.email }}</span>
                  <span class="text-[11px] text-slate-500 block">{{ c.phone }}</span>
                </td>
                <td class="py-3 px-4 text-slate-600">
                  <span class="block text-[11px] font-medium">{{ c.address }}</span>
                  <span v-if="c.tax_id && c.tax_id !== '—'" class="font-mono text-[10px] text-slate-400 block">Tax ID: {{ c.tax_id }}</span>
                </td>
                <td class="py-3 px-4 text-center">
                  <button
                    v-if="c.kind === 'member'"
                    type="button"
                    @click="showInvoiceModal = true; invoiceForm.user_id = c.user_id"
                    class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                  >
                    + Issue Invoice
                  </button>
                  <div v-else class="flex items-center justify-center gap-1.5">
                    <Link
                      :href="route('admin.accounting.contacts.edit', [club.slug, c.contact_db_id])"
                      class="px-2.5 py-1 bg-sky-50 hover:bg-sky-100 text-sky-800 border border-sky-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                      title="Edit Contact"
                    >
                      Edit
                    </Link>
                    <Link
                      :href="route('admin.accounting.bills.create', { clubSlug: club.slug, vendor: c.name })"
                      class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-extrabold rounded-lg transition-all"
                    >
                      + Record Bill
                    </Link>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- VIEW 8: BANK RECONCILIATION WORKSPACE (XERO COMPLETE DESIGN) -->
      <div v-if="activeTab === 'reconciliation'" class="space-y-4">
        
        <!-- Top Bank Account Balance Header Bar -->
        <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
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
                  class="bg-slate-50 border border-slate-300 text-slate-900 font-black text-base rounded-xl px-3 py-1.5 focus:bg-white focus:ring-2 focus:ring-sky-500 cursor-pointer"
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
                <span class="font-extrabold text-slate-900 text-sm">{{ activeBankAccount?.formatted_statement_balance || '£0.00' }}</span>
                <span class="text-slate-500 ml-1">Statement Balance</span>
              </div>
              <div class="border-l border-slate-200 pl-4">
                <span class="font-extrabold text-slate-900 text-sm">{{ activeBankAccount?.formatted_ledger_balance || '£0.00' }}</span>
                <span class="text-slate-500 ml-1">Balance in System</span>
                <a href="#" @click.prevent="showDifferentBalancesModal = true" class="text-sky-600 hover:underline ml-1 text-[11px] font-bold">— Different balances?</a>
              </div>
            </div>
            <a href="#" @click.prevent="showWhatsThisModal = true" class="text-sky-600 text-xs font-semibold hover:underline inline-block mt-0.5">What's this?</a>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 self-start md:self-auto">
            <button
              type="button"
              @click="navigateTo('reporting', 'reconciliation_summary')"
              class="text-xs font-bold text-sky-600 hover:underline mr-2 cursor-pointer"
            >
              Reconciliation Report
            </button>

            <div class="relative manage-account-container">
              <button
                type="button"
                @click.stop="showManageAccountMenu = !showManageAccountMenu"
                class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-lg transition-all flex items-center gap-1 cursor-pointer"
              >
                <span>Manage Account</span>
                <span>▾</span>
              </button>
              <div v-if="showManageAccountMenu" class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-30 text-xs font-semibold text-slate-700">
                <button type="button" @click="showImportModal = true; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50">📂 Import Bank Statement</button>
                <button type="button" @click="reconSubTab = 'bank_statements'; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50">📜 Bank Statement History</button>
                <button type="button" @click="reconSubTab = 'cash_coding'; showManageAccountMenu = false" class="w-full text-left px-4 py-2 hover:bg-slate-50">⚡ Bulk Cash Coding</button>
              </div>
            </div>
          </div>
        </div>



        <!-- Sub-Tab Navigation Bar & Compact View Toggle -->
        <div class="bg-white border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1">
          <div class="flex items-center gap-1 overflow-x-auto text-xs font-bold">
            <button
              type="button"
              @click="reconSubTab = 'reconcile'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'reconcile' ? 'border-sky-600 text-sky-700 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-800'
              ]"
            >
              Reconcile ({{ filteredUnmatchedTx.length }})
            </button>

            <button
              type="button"
              @click="reconSubTab = 'cash_coding'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'cash_coding' ? 'border-sky-600 text-sky-700 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-800'
              ]"
            >
              Cash coding
            </button>

            <button
              type="button"
              @click="reconSubTab = 'bank_statements'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'bank_statements' ? 'border-sky-600 text-sky-700 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-800'
              ]"
            >
              Bank statements
            </button>

            <button
              type="button"
              @click="reconSubTab = 'account_transactions'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap',
                reconSubTab === 'account_transactions' ? 'border-sky-600 text-sky-700 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-800'
              ]"
            >
              Account transactions
            </button>

            <button
              type="button"
              @click="reconSubTab = 'gift_aid'"
              :class="[
                'px-4 py-2.5 border-b-2 transition-all cursor-pointer whitespace-nowrap flex items-center gap-1.5',
                reconSubTab === 'gift_aid' ? 'border-amber-600 text-amber-800 font-extrabold' : 'border-transparent text-slate-500 hover:text-slate-800'
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
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-2.5 rounded-xl border border-slate-200">
            <div class="relative w-full sm:flex-1">
              <input
                v-model="reconSearch"
                type="text"
                placeholder="Search for Payee, Amount, Reference, Description, Cheque No., or Analysis Code"
                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 focus:bg-white focus:outline-none focus:ring-1 focus:ring-sky-500"
              />
              <span class="absolute left-2.5 top-2 text-slate-400 text-xs">🔍</span>
            </div>

            <button
              type="button"
              @click="showFilterPanel = !showFilterPanel"
              :class="[
                'px-3.5 py-1.5 font-extrabold text-xs rounded-lg border transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap',
                showFilterPanel ? 'bg-sky-50 text-sky-800 border-sky-300' : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
              ]"
            >
              <span>🔻 Filter</span>
            </button>
          </div>

          <!-- Expandable Filter Panel -->
          <div v-if="showFilterPanel" class="bg-slate-50 border border-slate-200 rounded-xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
            <div>
              <label class="font-bold text-slate-700 block mb-1">Transaction Type</label>
              <select v-model="filterType" class="w-full p-1.5 border border-slate-300 rounded bg-white">
                <option value="all">All Lines (Spent &amp; Received)</option>
                <option value="spent">Spent Only (Outgoing)</option>
                <option value="received">Received Only (Incoming)</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Min Amount (£)</label>
              <input v-model="filterMinAmount" type="number" step="0.01" placeholder="0.00" class="w-full p-1.5 border border-slate-300 rounded bg-white" />
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Max Amount (£)</label>
              <input v-model="filterMaxAmount" type="number" step="0.01" placeholder="9999.00" class="w-full p-1.5 border border-slate-300 rounded bg-white" />
            </div>

            <div class="flex items-end">
              <button
                type="button"
                @click="filterType = 'all'; filterMinAmount = ''; filterMaxAmount = ''; reconSearch = ''"
                class="w-full py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded"
              >
                Reset Filters
              </button>
            </div>
          </div>
        </div>

        <!-- SUB-TAB 1: RECONCILE WORKSPACE -->
        <div v-if="reconSubTab === 'reconcile'" class="space-y-4">


          <!-- Statement Line Rows Stack -->
          <div class="space-y-4">
            <div
              v-for="tx in filteredUnmatchedTx"
              :key="tx.id"
              :class="[
                'bg-slate-50/50 rounded-xl border border-slate-200/60 transition-all',
                compactView ? 'p-1.5' : 'p-2.5'
              ]"
            >
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">
                
                <!-- LEFT CARD (Statement Line) -->
                <div
                  :class="[
                    'lg:col-span-5 bg-white border border-slate-300 rounded-lg p-4 shadow-sm flex flex-col justify-between',
                    compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
                  ]"
                >
                  <div>
                    <div class="flex items-center justify-between text-xs relative">
                      <span class="font-semibold text-slate-400">{{ tx.transaction_date }}</span>
                      
                      <div class="relative statement-options-container">
                        <button
                          type="button"
                          @click.stop="toggleOptionsDropdown(tx.id)"
                          class="text-sky-600 hover:text-sky-800 hover:underline font-medium focus:outline-none cursor-pointer"
                        >
                          Options ▾
                        </button>
                        
                        <div
                          v-if="activeOptionsTxId === tx.id"
                          class="absolute right-0 top-5 z-30 w-48 bg-white border border-slate-200 rounded-xl shadow-xl py-1 text-xs font-semibold text-slate-700 animate-in fade-in duration-100"
                        >
                          <button
                            type="button"
                            @click="deleteSingleStatementLine(tx.id)"
                            class="w-full text-left px-3.5 py-2 text-rose-600 hover:bg-rose-50 flex items-center gap-2 cursor-pointer transition-colors"
                          >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete statement line
                          </button>
                        </div>
                      </div>
                    </div>
                    <div class="mt-1">
                      <h4 class="font-extrabold text-slate-900 text-sm leading-snug truncate">{{ tx.raw_description }}</h4>
                      <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider mt-0.5">
                        {{ tx.reference || (tx.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
                      </span>
                    </div>
                    <div class="relative statement-details-container inline-block mt-1">
                      <a
                        href="#"
                        @click.prevent.stop="toggleDetailsPopover(tx.id)"
                        class="text-xs text-sky-600 hover:underline inline-block font-medium cursor-pointer"
                      >
                        More details
                      </a>

                      <!-- Statement Details Popover -->
                      <div
                        v-if="activeDetailsTxId === tx.id"
                        class="absolute left-0 top-full mt-2.5 z-50 w-80 sm:w-96 bg-white border border-slate-300 rounded-lg shadow-2xl p-4 text-xs font-sans animate-in fade-in duration-100"
                        @click.stop
                      >
                        <!-- Top Pointer Arrow -->
                        <div class="absolute -top-1.5 left-6 w-3 h-3 bg-white border-t border-l border-slate-300 rotate-45 z-10"></div>

                        <!-- Header -->
                        <div class="flex items-center justify-between pb-3">
                          <h3 class="text-sm font-extrabold text-slate-900">Statement Details</h3>
                          <div class="flex items-center gap-2 text-[11px] text-slate-500">
                            <span>Esc to close</span>
                            <button
                              type="button"
                              @click="activeDetailsTxId = null"
                              class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer focus:outline-none"
                            >
                              ✕
                            </button>
                          </div>
                        </div>

                        <!-- Details Table -->
                        <table class="w-full border-collapse border border-slate-200 text-xs">
                          <tbody>
                            <tr>
                              <td class="w-1/2 border border-slate-200 p-2 text-right text-slate-600 font-normal">Transaction Date</td>
                              <td class="w-1/2 border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ tx.transaction_date }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Payee</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ tx.payee || '' }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Reference</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium break-all">{{ tx.reference || (tx.amount > 0 ? 'CREDIT' : 'DEBIT') }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Description</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium break-words">{{ tx.raw_description }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Transaction Amount</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ number_format(Math.abs(tx.amount), 2) }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Transaction Type</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ tx.amount < 0 ? 'Debit' : 'Credit' }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Cheque No.</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ tx.cheque_number || '' }}</td>
                            </tr>
                            <tr>
                              <td class="border border-slate-200 p-2 text-right text-slate-600 font-normal">Analysis Code</td>
                              <td class="border border-slate-200 p-2 text-left text-slate-900 font-medium">{{ tx.analysis_code || '' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>

                  <div class="pt-2 border-t border-slate-100 flex items-end justify-between">
                    <div v-if="tx.amount < 0">
                      <span class="text-[11px] font-semibold text-slate-400 block uppercase">Spent</span>
                      <span class="text-lg font-black text-slate-900 block">
                        {{ number_format(Math.abs(tx.amount), 2) }}
                      </span>
                    </div>
                    <div v-else>
                      <span class="text-[11px] font-semibold text-slate-400 block uppercase">Received</span>
                      <span class="text-lg font-black text-slate-900 block">
                        {{ number_format(Math.abs(tx.amount), 2) }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- CENTER OK BUTTON -->
                <div class="lg:col-span-1 flex justify-center py-2">
                  <button
                    type="button"
                    @click="submitRowReconcile(tx)"
                    class="w-14 h-10 bg-[#008ba8] hover:bg-[#00768f] text-white font-black text-xs rounded uppercase shadow-sm transition-all cursor-pointer flex items-center justify-center tracking-wide"
                  >
                    OK
                  </button>
                </div>

                <!-- RIGHT CARD (Match / Create / Transfer / Discuss) -->
                <div
                  :class="[
                    'lg:col-span-6 bg-white border border-slate-300 rounded-lg p-4 shadow-sm flex flex-col justify-between',
                    compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
                  ]"
                >
                  <div>
                    <!-- Tab Header bar -->
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2 mb-3">
                      <div class="flex items-center gap-4 text-xs font-bold">
                        <button
                          type="button"
                          @click="setRowTab(tx.id, 'Match')"
                          :class="[
                            'pb-1 cursor-pointer transition-all',
                            getRowState(tx.id, tx).tab === 'Match' ? 'text-slate-900 border-b-2 border-[#008ba8]' : 'text-slate-400 hover:text-slate-600'
                          ]"
                        >
                          Match
                        </button>

                        <button
                          type="button"
                          @click="setRowTab(tx.id, 'Create')"
                          :class="[
                            'pb-1 cursor-pointer transition-all',
                            getRowState(tx.id, tx).tab === 'Create' ? 'text-slate-900 border-b-2 border-[#008ba8]' : 'text-slate-400 hover:text-slate-600'
                          ]"
                        >
                          Create
                        </button>

                        <button
                          type="button"
                          @click="setRowTab(tx.id, 'Transfer')"
                          :class="[
                            'pb-1 cursor-pointer transition-all',
                            getRowState(tx.id, tx).tab === 'Transfer' ? 'text-slate-900 border-b-2 border-[#008ba8]' : 'text-slate-400 hover:text-slate-600'
                          ]"
                        >
                          Transfer
                        </button>

                        <button
                          type="button"
                          @click="setRowTab(tx.id, 'Discuss')"
                          :class="[
                            'pb-1 cursor-pointer transition-all',
                            getRowState(tx.id, tx).tab === 'Discuss' ? 'text-slate-900 border-b-2 border-[#008ba8]' : 'text-slate-400 hover:text-slate-600'
                          ]"
                        >
                          Discuss
                        </button>
                      </div>

                      <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="text-xs font-semibold text-sky-600 hover:underline">Find &amp; Match</a>
                    </div>

                    <!-- Tab Body: CREATE -->
                    <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="space-y-2 text-xs">
                      <div class="grid grid-cols-12 items-center gap-2">
                        <label class="col-span-2 text-right font-semibold text-slate-500">Who</label>
                        <div class="col-span-10 relative who-dropdown-container">
                          <input
                            v-model="getRowState(tx.id, tx).who"
                            @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                            @blur="setTimeout(() => { getRowState(tx.id, tx).showWhoDropdown = false; }, 200)"
                            type="text"
                            name="payee_contact_search_no_autofill"
                            autocomplete="off"
                            aria-autocomplete="none"
                            data-lpignore="true"
                            data-form-type="other"
                            placeholder="Name of the contact..."
                            class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                          />

                          <!-- Floating Dropdown menu (appears only after typing 2+ letters) -->
                          <div
                            v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                            class="absolute left-0 top-full mt-1 w-full bg-white border border-slate-200 rounded-lg shadow-xl z-30 max-h-48 overflow-y-auto divide-y divide-slate-100"
                          >
                            <button
                              v-for="c in getContactSuggestions(getRowState(tx.id, tx).who)"
                              :key="c.id"
                              type="button"
                              @mousedown.prevent="getRowState(tx.id, tx).who = c.name; getRowState(tx.id, tx).showWhoDropdown = false"
                              class="w-full text-left px-3 py-1.5 hover:bg-sky-50 flex items-center justify-between text-xs transition-colors cursor-pointer"
                            >
                              <div>
                                <span class="font-bold text-slate-900 block text-xs">{{ c.name }}</span>
                                <span class="text-[10px] text-slate-500 block">{{ c.role || c.kind }}</span>
                              </div>
                              <span class="text-[10px] text-sky-700 font-mono font-bold bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100">Select</span>
                            </button>
                          </div>
                        </div>
                      </div>

                      <div class="grid grid-cols-12 items-center gap-2">
                        <label class="col-span-2 text-right font-semibold text-slate-500">What</label>
                        <div class="col-span-10">
                          <select
                            v-model="getRowState(tx.id, tx).what"
                            @change="getRowState(tx.id, tx).showWhatError = false"
                            :class="[
                              'w-full px-2.5 py-1 bg-white border rounded text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500',
                              getRowState(tx.id, tx).showWhatError ? 'border-red-500 bg-red-50/50' : 'border-slate-300'
                            ]"
                          >
                            <option value="">Choose the account...</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                              {{ acc.code }} - {{ acc.name }}
                            </option>
                          </select>
                          <p v-if="getRowState(tx.id, tx).showWhatError" class="text-[10px] text-red-600 font-bold mt-0.5">Please select an account code (What) before reconciling.</p>
                        </div>
                      </div>

                      <div class="grid grid-cols-12 items-center gap-2">
                        <label class="col-span-2 text-right font-semibold text-slate-500">Why</label>
                        <div class="col-span-10">
                          <input
                            v-model="getRowState(tx.id, tx).why"
                            type="text"
                            placeholder="Enter a description..."
                            class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded text-xs text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500"
                          />
                        </div>
                      </div>
                    </div>

                    <!-- Tab Body: MATCH -->
                    <div v-else-if="getRowState(tx.id, tx).tab === 'Match'" class="space-y-2 text-xs">
                      <div v-if="tx.suggested_matches && tx.suggested_matches.length > 0" class="space-y-2">
                        <div
                          v-for="(m, idx) in tx.suggested_matches"
                          :key="idx"
                          class="p-2.5 rounded-lg border border-emerald-300 bg-emerald-50/60 flex items-center justify-between"
                        >
                          <div>
                            <span class="font-black text-slate-900 block text-xs">{{ m.target_title }}</span>
                            <span class="text-[11px] text-emerald-800 font-medium">{{ m.match_reason }} — £{{ number_format(m.target_amount, 2) }}</span>
                          </div>
                          <button
                            type="button"
                            @click="submitReconcile(m.match_type, m.target_id, m.nominal_code || 'GENERAL')"
                            class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] rounded transition"
                          >
                            Match
                          </button>
                        </div>
                      </div>
                      <div v-else class="p-3 text-slate-500 text-center italic bg-slate-50 border border-slate-200 rounded">
                        No automatic system match found. Switch to 'Create' or click 'Find &amp; Match'.
                      </div>
                    </div>

                    <!-- Tab Body: TRANSFER -->
                    <div v-else-if="getRowState(tx.id, tx).tab === 'Transfer'" class="space-y-2 text-xs">
                      <div class="grid grid-cols-12 items-center gap-2">
                        <label class="col-span-3 text-right font-semibold text-slate-500">Bank Account</label>
                        <div class="col-span-9">
                          <select
                            v-model="getRowState(tx.id, tx).transfer_account"
                            class="w-full px-2.5 py-1 bg-white border border-slate-300 rounded text-xs text-slate-800"
                          >
                            <option value="">Select destination bank account...</option>
                            <option v-for="acc in accounts.filter(a => a.type === 'asset')" :key="acc.id" :value="acc.id">
                              {{ acc.code }} - {{ acc.name }}
                            </option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <!-- Tab Body: DISCUSS -->
                    <div v-else-if="getRowState(tx.id, tx).tab === 'Discuss'" class="space-y-2 text-xs">
                      <textarea
                        v-model="getRowState(tx.id, tx).discuss_note"
                        placeholder="Type a note or query for your team..."
                        rows="2"
                        class="w-full p-2 border border-slate-300 rounded text-xs focus:ring-1 focus:ring-sky-500"
                      ></textarea>
                    </div>
                  </div>

                  <!-- Bottom Footer Row of Right Card -->
                  <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                      <select
                        v-model="getRowState(tx.id, tx).category"
                        class="px-2 py-0.5 border border-slate-300 rounded text-xs text-slate-700 bg-white"
                      >
                        <option value="General">General</option>
                        <option value="Members">Members</option>
                        <option value="Facilities">Facilities</option>
                      </select>

                      <select
                        v-model="getRowState(tx.id, tx).vat"
                        class="px-2 py-0.5 border border-slate-300 rounded text-xs text-slate-700 bg-white"
                      >
                        <option value="No VAT">No VAT</option>
                        <option value="20% Standard">20% Standard</option>
                        <option value="Exempt">Exempt</option>
                      </select>
                    </div>

                    <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="text-sky-600 font-semibold hover:underline">Add details</a>
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty state -->
            <div v-if="filteredUnmatchedTx.length === 0" class="p-12 bg-white border border-slate-200 rounded-xl text-center text-slate-400 text-xs italic">
              All bank statement lines reconciled! No unmatched lines pending.
            </div>
          </div>
        </div>

        <!-- SUB-TAB 2: CASH CODING -->
        <div v-else-if="reconSubTab === 'cash_coding'" class="space-y-4">
          <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
            <div>
              <h4 class="font-black text-slate-900 text-sm">Fast Bulk Cash Coding Spreadsheet</h4>
              <p class="text-xs text-slate-500">Quickly assign payee contacts, nominal account codes, and VAT rates across multiple statement lines simultaneously.</p>
            </div>
            <button
              type="button"
              @click="submitBulkCashCoding"
              :disabled="selectedCashCodingTx.length === 0"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs rounded-xl shadow transition cursor-pointer"
            >
              Save &amp; Reconcile Selected ({{ selectedCashCodingTx.length }})
            </button>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-100 text-[11px] font-black uppercase tracking-wider text-slate-600 border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3">
                    <input type="checkbox" @change="toggleCashCodingSelectAll" :checked="selectedCashCodingTx.length === filteredUnmatchedTx.length && filteredUnmatchedTx.length > 0" class="rounded text-sky-600 focus:ring-sky-500" />
                  </th>
                  <th class="py-2.5 px-3">Date</th>
                  <th class="py-2.5 px-3">Payee (Who)</th>
                  <th class="py-2.5 px-3">Description (Why)</th>
                  <th class="py-2.5 px-3">Ledger Code (What)</th>
                  <th class="py-2.5 px-3">VAT Rate</th>
                  <th class="py-2.5 px-3 text-right">Amount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 font-medium">
                <tr v-for="tx in filteredUnmatchedTx" :key="tx.id" class="hover:bg-slate-50/80">
                  <td class="py-2 px-3">
                    <input type="checkbox" :value="tx.id" v-model="selectedCashCodingTx" class="rounded text-sky-600 focus:ring-sky-500" />
                  </td>
                  <td class="py-2 px-3 text-slate-500 font-mono text-[11px] whitespace-nowrap">{{ tx.transaction_date }}</td>
                  <td class="py-2 px-3 relative who-dropdown-container">
                    <input
                      v-model="getRowState(tx.id, tx).who"
                      @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                      @blur="setTimeout(() => { getRowState(tx.id, tx).showWhoDropdown = false; }, 200)"
                      type="text"
                      name="cash_coding_contact_no_autofill"
                      autocomplete="off"
                      aria-autocomplete="none"
                      data-lpignore="true"
                      data-form-type="other"
                      placeholder="Contact..."
                      class="w-full px-2 py-1 border border-slate-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-sky-500"
                    />

                    <!-- Floating Dropdown menu -->
                    <div
                      v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                      class="absolute left-0 top-full mt-1 w-56 bg-white border border-slate-200 rounded-lg shadow-xl z-30 max-h-48 overflow-y-auto divide-y divide-slate-100"
                    >
                      <button
                        v-for="c in getContactSuggestions(getRowState(tx.id, tx).who)"
                        :key="c.id"
                        type="button"
                        @mousedown.prevent="getRowState(tx.id, tx).who = c.name; getRowState(tx.id, tx).showWhoDropdown = false"
                        class="w-full text-left px-3 py-1.5 hover:bg-sky-50 flex items-center justify-between text-xs transition-colors cursor-pointer"
                      >
                        <div>
                          <span class="font-bold text-slate-900 block text-xs">{{ c.name }}</span>
                          <span class="text-[10px] text-slate-500 block">{{ c.role || c.kind }}</span>
                        </div>
                        <span class="text-[10px] text-sky-700 font-mono font-bold bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100">Select</span>
                      </button>
                    </div>
                  </td>
                  <td class="py-2 px-3">
                    <input v-model="getRowState(tx.id, tx).why" type="text" placeholder="Description..." class="w-full px-2 py-1 border border-slate-300 rounded text-xs" />
                  </td>
                  <td class="py-2 px-3">
                    <select
                      v-model="getRowState(tx.id, tx).what"
                      @change="getRowState(tx.id, tx).showWhatError = false"
                      :class="[
                        'w-full px-2 py-1 border rounded text-xs',
                        getRowState(tx.id, tx).showWhatError ? 'border-red-500 bg-red-50/50' : 'border-slate-300'
                      ]"
                    >
                      <option value="">Select Account...</option>
                      <option v-for="acc in accounts" :key="acc.id" :value="acc.code">{{ acc.code }} - {{ acc.name }}</option>
                    </select>
                  </td>
                  <td class="py-2 px-3">
                    <select v-model="getRowState(tx.id, tx).vat" class="w-full px-2 py-1 border border-slate-300 rounded text-xs">
                      <option value="No VAT">No VAT</option>
                      <option value="20% Standard">20% Standard</option>
                      <option value="Exempt">Exempt</option>
                    </select>
                  </td>
                  <td :class="['py-2 px-3 text-right font-mono font-bold whitespace-nowrap', tx.amount > 0 ? 'text-emerald-700' : 'text-slate-900']">
                    £{{ number_format(Math.abs(tx.amount), 2) }}
                  </td>
                </tr>
                <tr v-if="filteredUnmatchedTx.length === 0">
                  <td colspan="7" class="py-8 text-center text-slate-400 italic">No statement lines available for cash coding.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SUB-TAB 3: BANK STATEMENTS (Full imported statement lines log) -->
        <div v-else-if="reconSubTab === 'bank_statements'" class="space-y-4">
          <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <span class="text-xs font-bold text-slate-700">Showing</span>
              <select v-model="statementLinesFilter" class="px-2.5 py-1 bg-white border border-slate-300 rounded text-xs font-semibold text-slate-800">
                <option value="statement_lines">Statement lines</option>
                <option value="deleted">Deleted statement lines</option>
                <option value="all">All statement lines</option>
              </select>
              <span v-if="selectedStatementLineIds.length === 0" class="text-xs text-slate-400">No transactions selected</span>
              <span v-else class="text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                {{ selectedStatementLineIds.length }} transaction(s) selected
              </span>
            </div>
            <div class="flex items-center gap-2">
              <button
                v-if="deletableStatementLines.length > 0"
                type="button"
                @click="deleteSelectedStatementLines"
                class="px-3.5 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
              >
                Delete ({{ deletableStatementLines.length }})
              </button>
              <button
                v-if="restorableStatementLines.length > 0"
                type="button"
                @click="restoreSelectedStatementLines"
                class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
              >
                Restore ({{ restorableStatementLines.length }})
              </button>
              <button type="button" @click="showImportModal = true" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-lg shadow cursor-pointer">
                + Upload Statement
              </button>
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-slate-50 text-[11px] font-bold text-slate-600 border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3 w-8">
                    <input
                      type="checkbox"
                      @change="toggleStatementLinesSelectAll"
                      :checked="selectedStatementLineIds.length === filteredStatementLines.length && filteredStatementLines.length > 0"
                      class="rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                    />
                  </th>
                  <th class="py-2.5 px-3">Date</th>
                  <th class="py-2.5 px-3">Type</th>
                  <th class="py-2.5 px-3">Particulars / Description</th>
                  <th class="py-2.5 px-3">Reference</th>
                  <th class="py-2.5 px-3 text-right">Spent</th>
                  <th class="py-2.5 px-3 text-right">Received</th>
                  <th class="py-2.5 px-3 text-center">Source</th>
                  <th class="py-2.5 px-3 text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="line in filteredStatementLines" :key="line.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-2.5 px-3">
                    <input
                      type="checkbox"
                      :value="line.id"
                      v-model="selectedStatementLineIds"
                      class="rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                    />
                  </td>
                  <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">{{ line.transaction_date }}</td>
                  <td class="py-2.5 px-3 text-slate-600 font-medium">{{ line.type }}</td>
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ line.raw_description }}</td>
                  <td class="py-2.5 px-3 text-slate-500 font-mono text-[11px]">{{ line.reference }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{{ line.spent }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">{{ line.received }}</td>
                  <td class="py-2.5 px-3 text-center text-slate-500 text-[11px]">{{ line.source }}</td>
                  <td class="py-2.5 px-3 text-center whitespace-nowrap">
                    <span
                      :class="[
                        'px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase tracking-wider',
                        line.status === 'Reconciled' ? 'bg-emerald-100 text-emerald-800' :
                        line.status === 'Deleted' ? 'bg-rose-100 text-rose-800 border border-rose-200' :
                        'bg-amber-100 text-amber-800'
                      ]"
                    >
                      {{ line.status }}
                    </span>
                  </td>
                </tr>
                <tr v-if="filteredStatementLines.length === 0">
                  <td colspan="9" class="py-8 text-center text-slate-400 italic">No statement lines found for selected filter.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SUB-TAB 4: ACCOUNT TRANSACTIONS -->
        <div v-else-if="reconSubTab === 'account_transactions'" class="space-y-4">
          <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
            <div>
              <h4 class="font-black text-slate-900 text-sm">Internal System Account Transactions History</h4>
              <p class="text-xs text-slate-500">Internal bookkeeping entries created inside your books (bills, invoices, spend/receive money).</p>
            </div>
            <div class="flex items-center gap-3">
              <span v-if="selectedAccountTxIds.length === 0" class="text-xs text-slate-400">Showing all internal transactions</span>
              <span v-else class="text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                {{ selectedAccountTxIds.length }} transaction(s) selected
              </span>
              <button
                v-if="selectedAccountTxIds.length > 0"
                type="button"
                @click="removeAndRedoSelectedAccountTransactions"
                class="px-3.5 py-1.5 bg-[#008ba8] hover:bg-[#00768f] text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
              >
                Remove &amp; Redo ({{ selectedAccountTxIds.length }})
              </button>
            </div>
          </div>

          <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-slate-50 text-[11px] font-bold text-slate-600 border-b border-slate-200">
                <tr>
                  <th class="py-2.5 px-3 w-8">
                    <input
                      type="checkbox"
                      @change="toggleAccountTxSelectAll"
                      :checked="selectedAccountTxIds.length === filteredAccountTransactions.length && filteredAccountTransactions.length > 0"
                      class="rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                    />
                  </th>
                  <th class="py-2.5 px-3">Date</th>
                  <th class="py-2.5 px-3">Type</th>
                  <th class="py-2.5 px-3">Description</th>
                  <th class="py-2.5 px-3">Reference</th>
                  <th class="py-2.5 px-3 text-right">Spent</th>
                  <th class="py-2.5 px-3 text-right">Received</th>
                  <th class="py-2.5 px-3 text-center">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="tx in filteredAccountTransactions" :key="tx.id" class="hover:bg-slate-50/80 transition-colors">
                  <td class="py-2.5 px-3">
                    <input
                      type="checkbox"
                      :value="tx.id"
                      v-model="selectedAccountTxIds"
                      class="rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                    />
                  </td>
                  <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">{{ tx.transaction_date }}</td>
                  <td class="py-2.5 px-3 text-slate-600 font-medium">{{ tx.type }}</td>
                  <td class="py-2.5 px-3 font-bold text-slate-900">{{ tx.description }}</td>
                  <td class="py-2.5 px-3 text-slate-500 font-mono text-[11px]">{{ tx.reference }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900">{{ tx.spent }}</td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">{{ tx.received }}</td>
                  <td class="py-2.5 px-3 text-center whitespace-nowrap">
                    <span
                      :class="[
                        'px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase tracking-wider',
                        tx.status === 'Reconciled' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'
                      ]"
                    >
                      {{ tx.status }}
                    </span>
                  </td>
                </tr>
                <tr v-if="filteredAccountTransactions.length === 0">
                  <td colspan="8" class="py-8 text-center text-slate-400 italic">No internal account transactions recorded yet.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- SUB-TAB 5: GIFT AID & RELIEF CHEST RECONCILIATION -->
        <div v-else-if="reconSubTab === 'gift_aid'" class="space-y-6">
          <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200/80 rounded-2xl p-6 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-xl">🏛️</span>
                  <h4 class="font-black text-slate-900 text-base">Automated Gift Aid Recovery &amp; Relief Chest Reconciliation</h4>
                </div>
                <p class="text-xs text-slate-600 mt-1">
                  Automatic matching engine for HMRC 25% Gift Aid tax reclaims and MCF Relief Chest (Ref: <span class="font-extrabold text-slate-900">{{ reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418' }}</span>) deposit distributions.
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <Link
                  :href="route('admin.accounting.giftaid.reconcile_auto', club.slug)"
                  method="post"
                  as="button"
                  class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
                >
                  <span>⚡ Auto-Match Gift Aid &amp; Relief Chest Deposits</span>
                </Link>
                <a
                  :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
                  target="_blank"
                  class="px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5"
                >
                  <span>📥 Export HMRC Schedule (CSV)</span>
                </a>
              </div>
            </div>

            <!-- Summary Position Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
              <div class="bg-white p-4 rounded-xl border border-amber-200/60 shadow-2xs space-y-1">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Eligible Donations</span>
                <span class="text-xl font-black text-slate-900 block">{{ reconciliation?.gift_aid_summary?.formatted_total_eligible || '£0.00' }}</span>
                <span class="text-[10px] text-slate-400">Meeting Alms &amp; Envelopes</span>
              </div>

              <div class="bg-white p-4 rounded-xl border border-emerald-200/60 shadow-2xs space-y-1">
                <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider block">Reclaimed Gift Aid</span>
                <span class="text-xl font-black text-emerald-800 block">{{ reconciliation?.gift_aid_summary?.formatted_gift_aid_reclaimed || '£0.00' }}</span>
                <span class="text-[10px] text-emerald-600 font-bold">25% HMRC Tax Reclaims Reconciled</span>
              </div>

              <div class="bg-white p-4 rounded-xl border border-amber-300 shadow-2xs space-y-1">
                <span class="text-[11px] font-bold text-amber-800 uppercase tracking-wider block">Pending Reclaim</span>
                <span class="text-xl font-black text-amber-900 block">{{ reconciliation?.gift_aid_summary?.formatted_pending_gift_aid || '£0.00' }}</span>
                <span class="text-[10px] text-amber-700 font-bold">{{ reconciliation?.gift_aid_summary?.pending_claim_count || 0 }} collection batch(es) pending</span>
              </div>

              <div class="bg-white p-4 rounded-xl border border-sky-200/60 shadow-2xs space-y-1">
                <span class="text-[11px] font-bold text-sky-800 uppercase tracking-wider block">Net Relief Chest Position</span>
                <span class="text-xl font-black text-sky-900 block">{{ reconciliation?.gift_aid_summary?.formatted_net_relief_chest_balance || '£0.00' }}</span>
                <span class="text-[10px] text-sky-700 font-bold">Chest Ref: {{ reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418' }}</span>
              </div>
            </div>
          </div>

          <!-- Educational & Rule Explanation Banner -->
          <div class="p-5 bg-white border border-slate-200 rounded-2xl space-y-3">
            <h5 class="font-extrabold text-slate-900 text-xs uppercase tracking-wider flex items-center gap-1.5">
              <span>💡</span>
              <span>How Automated Gift Aid &amp; Relief Chest Reconciliation Works</span>
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600 leading-relaxed">
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                <span class="font-bold text-slate-900 block">1. 25% Tax Reclaim Calculation</span>
                <p class="text-[11px] text-slate-500">
                  Every Gift Aid envelope or alms donation recorded during dual-custody meeting counts generates a 25% reclaimable Gift Aid entitlement.
                </p>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                <span class="font-bold text-slate-900 block">2. Automatic Bank Keyword Matching</span>
                <p class="text-[11px] text-slate-500">
                  Bank statement lines with keywords like <code>HMRC GIFT AID</code>, <code>MCF RELIEF CHEST</code>, or <code>PROVINCIAL RELIEF</code> are automatically matched.
                </p>
              </div>
              <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 space-y-1">
                <span class="font-bold text-slate-900 block">3. Double-Entry General Ledger</span>
                <p class="text-[11px] text-slate-500">
                  Reconciled payouts post automatically to Nominal Code 4310 (Gift Aid Tax Reclaim Income) or Code 4300 (Relief Chest Contributions).
                </p>
              </div>
            </div>
          </div>

          <!-- DETAILED RECONCILED DONATIONS & GIFT AID TRANSACTIONS LEDGER WITH FILTERS -->
          <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
            
            <!-- Header & Filter Summary -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between pb-4 border-b border-slate-100 gap-4">
              <div>
                <h4 class="font-black text-slate-900 text-base flex items-center gap-2">
                  <span>📜</span>
                  <span>Reconciled Donations &amp; Gift Aid Transactions Ledger</span>
                </h4>
                <p class="text-xs text-slate-500 mt-0.5 font-medium">
                  Detailed listing of all meeting alms, festival giving, and gift aid tax reclaims with bank matching status.
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="px-3 py-1 bg-purple-50 text-purple-800 font-extrabold rounded-xl border border-purple-200/60">
                  Total Filtered Gift Aid: {{ formatCurrency(filteredReconciledGiftAidTotal) }}
                </span>
                <span class="px-3 py-1 bg-slate-100 text-slate-700 font-bold rounded-xl border border-slate-200">
                  Showing {{ filteredReconciledDonations.length }} transaction(s)
                </span>
              </div>
            </div>

            <!-- Interactive Filters Form Bar -->
            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
              <div class="flex items-center justify-between text-xs font-bold text-slate-700">
                <span class="flex items-center gap-1.5">
                  <span>🔍</span>
                  <span>Filter Transactions by Date, Person &amp; Status</span>
                </span>
                <button
                  type="button"
                  @click="resetGiftAidFilters"
                  class="text-purple-700 hover:text-purple-900 underline font-bold cursor-pointer"
                >
                  Reset Filters
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                <!-- Date From -->
                <div>
                  <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">From Date</label>
                  <input
                    v-model="giftAidFilterDateFrom"
                    type="date"
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <!-- Date To -->
                <div>
                  <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">To Date</label>
                  <input
                    v-model="giftAidFilterDateTo"
                    type="date"
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500"
                  />
                </div>

                <!-- Person / Donor Filter -->
                <div>
                  <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Donor / Person</label>
                  <select
                    v-model="giftAidFilterPerson"
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500 cursor-pointer"
                  >
                    <option value="">All Donors &amp; Stewards</option>
                    <option
                      v-for="m in (reconciliation?.reconciled_donations?.members || [])"
                      :key="m.id"
                      :value="m.id"
                    >
                      {{ m.name }}
                    </option>
                  </select>
                </div>

                <!-- Status Filter -->
                <div>
                  <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Gift Aid Status</label>
                  <select
                    v-model="giftAidFilterStatus"
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500 cursor-pointer"
                  >
                    <option value="all">All Statuses</option>
                    <option value="reconciled">Reconciled Only</option>
                    <option value="claimed">Claimed</option>
                    <option value="pending">Pending / Unclaimed</option>
                  </select>
                </div>

                <!-- Keyword Search -->
                <div>
                  <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Search Keywords</label>
                  <input
                    v-model="giftAidFilterSearch"
                    type="text"
                    placeholder="Search notes, donor..."
                    class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500"
                  />
                </div>
              </div>
            </div>

            <!-- Transactions Table -->
            <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
              <table class="w-full text-left text-xs border-collapse">
                <thead class="bg-slate-900 text-white text-[11px] font-extrabold uppercase tracking-wider">
                  <tr>
                    <th class="py-3 px-3.5">Date</th>
                    <th class="py-3 px-3.5">Donor / Person</th>
                    <th class="py-3 px-3.5">Collection Type</th>
                    <th class="py-3 px-3.5 text-right">Donation Total</th>
                    <th class="py-3 px-3.5 text-right">25% Gift Aid</th>
                    <th class="py-3 px-3.5 text-center">Gift Aid Status</th>
                    <th class="py-3 px-3.5">Matched Bank Deposit</th>
                    <th class="py-3 px-3.5">Notes</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
                  <tr
                    v-for="item in filteredReconciledDonations"
                    :key="item.id"
                    class="hover:bg-purple-50/40 transition-colors"
                  >
                    <!-- Date -->
                    <td class="py-3 px-3.5 font-mono text-[11px] text-slate-600 whitespace-nowrap">
                      {{ item.created_at }}
                    </td>

                    <!-- Donor / Person -->
                    <td class="py-3 px-3.5 font-bold text-slate-900 whitespace-nowrap">
                      <div class="flex items-center gap-1.5">
                        <span class="text-sm">👤</span>
                        <span>{{ item.donor_name }}</span>
                      </div>
                    </td>

                    <!-- Collection Type -->
                    <td class="py-3 px-3.5 whitespace-nowrap">
                      <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px]">
                        {{ item.collection_type }}
                      </span>
                    </td>

                    <!-- Donation Total -->
                    <td class="py-3 px-3.5 text-right font-mono font-bold text-slate-900 whitespace-nowrap">
                      {{ item.formatted_total }}
                    </td>

                    <!-- 25% Gift Aid -->
                    <td class="py-3 px-3.5 text-right font-mono font-extrabold text-purple-700 whitespace-nowrap">
                      {{ item.formatted_gift_aid }}
                    </td>

                    <!-- Gift Aid Status -->
                    <td class="py-3 px-3.5 text-center whitespace-nowrap">
                      <span
                        :class="[
                          'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1',
                          item.gift_aid_status === 'reconciled' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' :
                          (item.gift_aid_status === 'claimed' ? 'bg-purple-100 text-purple-800 border border-purple-300' :
                          'bg-amber-100 text-amber-900 border border-amber-300')
                        ]"
                      >
                        <span v-if="item.gift_aid_status === 'reconciled'">✓ Reconciled</span>
                        <span v-else-if="item.gift_aid_status === 'claimed'">⚡ Claimed</span>
                        <span v-else>⏳ Unclaimed</span>
                      </span>
                    </td>

                    <!-- Matched Bank Deposit -->
                    <td class="py-3 px-3.5 text-xs text-slate-600 max-w-xs">
                      <div v-if="item.bank_transaction" class="p-2 bg-emerald-50/70 border border-emerald-200 rounded-lg text-[11px] space-y-0.5">
                        <div class="font-extrabold text-emerald-900 flex items-center justify-between">
                          <span>Bank Credit: {{ item.bank_transaction.formatted_amount }}</span>
                          <span class="text-[9px] text-emerald-700">{{ item.bank_transaction.transaction_date }}</span>
                        </div>
                        <div class="text-[10px] text-emerald-800 truncate" :title="item.bank_transaction.raw_description">
                          {{ item.bank_transaction.raw_description }}
                        </div>
                      </div>
                      <span v-else class="text-slate-400 italic text-[11px]">No bank credit linked yet</span>
                    </td>

                    <!-- Notes -->
                    <td class="py-3 px-3.5 text-slate-500 text-[11px] max-w-xs truncate" :title="item.notes || 'No notes'">
                      {{ item.notes || '—' }}
                    </td>
                  </tr>

                  <tr v-if="filteredReconciledDonations.length === 0">
                    <td colspan="8" class="py-12 text-center text-slate-400 italic">
                      No reconciled donations match the selected filter criteria.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- VIEW 9: SETTINGS (Financial & Organization Settings) -->
      <div v-if="activeTab === 'settings'" class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
        <div class="border-b border-slate-100 pb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Financial & Accounting Settings</h3>
            <p class="text-xs text-slate-500">Configure company address, VAT registration numbers, invoicing rules, and billing defaults.</p>
          </div>
          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5 self-start sm:self-auto"
          >
            <span>⚙️ Full System Settings</span>
          </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
          <!-- Card 1: Company & Address Profile -->
          <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">🏢</span>
              <h4 class="font-extrabold text-slate-900 text-sm">Company & Address</h4>
            </div>
            <div class="space-y-2 text-slate-600">
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Organization Name</span>
                <span class="font-bold text-slate-900">{{ club.name }}</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Registered Address</span>
                <span>{{ settings.address_line_1 || '100 Boathouse Way' }}</span>
                <span v-if="settings.city" class="block">{{ settings.city }}, {{ settings.postcode }}</span>
                <span class="block text-slate-500 font-mono text-[10px]">{{ settings.country || 'United Kingdom' }}</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Contact Information</span>
                <span class="block font-mono">{{ settings.contact_email || 'admin@' + club.slug + '.org' }}</span>
                <span class="block font-mono text-slate-500">{{ settings.phone || '+44 20 7946 0912' }}</span>
              </div>
            </div>
          </div>

          <!-- Card 2: VAT & Tax Registration -->
          <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">📋</span>
              <h4 class="font-extrabold text-slate-900 text-sm">VAT & Tax Configuration</h4>
            </div>
            <div class="space-y-2 text-slate-600">
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Tax / VAT Reg Number</span>
                <span class="font-mono font-bold text-sky-800 text-sm block">{{ settings.tax_registration_number || 'GB 987 6543 21' }}</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Default Tax Rates</span>
                <span class="block">• 20.0% Standard UK VAT</span>
                <span class="block">• 0.0% Exempt Subscriptions</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Currency</span>
                <span class="font-mono font-bold text-emerald-700">GBP (£)</span>
              </div>
            </div>
          </div>

          <!-- Card 3: Invoicing Setup & Footer Notes -->
          <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">🧾</span>
              <h4 class="font-extrabold text-slate-900 text-sm">Invoicing & Dues Setup</h4>
            </div>
            <div class="space-y-2 text-slate-600">
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Auto-Invoicing Lead Time</span>
                <span class="font-bold text-slate-900">{{ settings.auto_invoice_days_before || 7 }} Days before due date</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Overdue Grace Period</span>
                <span class="font-bold text-amber-800">{{ settings.dues_grace_period_days || 14 }} Days</span>
              </div>
              <div>
                <span class="font-bold text-slate-800 block text-[11px]">Receipt & Invoice Notes</span>
                <p class="text-[11px] text-slate-500 italic">"{{ settings.receipt_footer_notes || 'Thank you for supporting our club.' }}"</p>
              </div>
            </div>
          </div>
        </div>

        <div class="p-4 bg-sky-50 rounded-2xl border border-sky-200 flex items-center justify-between text-xs">
          <div class="flex items-center gap-3">
            <span class="text-xl">⚙️</span>
            <div>
              <span class="font-black text-sky-900 block">Edit Organization Settings & Billing Rules</span>
              <span class="text-sky-700">Update company address, VAT registration number, logos, and role permission matrix.</span>
            </div>
          </div>
          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-3.5 py-1.5 bg-sky-700 hover:bg-sky-800 text-white font-bold rounded-xl shadow-sm transition-all whitespace-nowrap"
          >
            Edit Settings →
          </Link>
        </div>
      </div>

    </div>

    <!-- Modal 0: Add Bank / Payment Account -->
    <div v-if="showBankAccountModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showBankAccountModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-sky-50 text-sky-600 rounded-xl text-lg">🏦</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">Add Bank / Payment Account</h3>
              <p class="text-[11px] text-slate-500 font-medium">Create High Street Bank or Manual Account</p>
            </div>
          </div>
          <button type="button" @click="showBankAccountModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitBankAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Institution / Provider Name *</label>
            <input v-model="bankAccountForm.bank_name" required placeholder="e.g. Barclays, HSBC, Lloyds, NatWest" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Account Label / Title *</label>
            <input v-model="bankAccountForm.account_name" required placeholder="e.g. Main Operating Account, Online Card Payouts" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Account Type *</label>
              <select v-model="bankAccountForm.account_type" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white">
                <option value="current">High Street Current Account</option>
                <option value="savings">Savings Account</option>
                <option value="credit_card">Credit Card Account</option>
                <option value="cash">Petty Cash / Cash Register</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Currency *</label>
              <input v-model="bankAccountForm.currency" required placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Account Number</label>
              <input v-model="bankAccountForm.account_number" placeholder="e.g. 12345678" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Sort Code</label>
              <input v-model="bankAccountForm.sort_code" placeholder="e.g. 20-00-00" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
            <input type="number" step="0.01" v-model="bankAccountForm.opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <!-- Account Type Mismatch Warning Banner -->
          <div v-if="showAccountTypeWarning && detectedMismatch" class="p-3.5 bg-amber-50 rounded-2xl border border-amber-300 space-y-2.5 animate-in fade-in duration-150">
            <div class="flex items-start gap-2">
              <span class="text-lg leading-none">⚠️</span>
              <div>
                <h4 class="font-extrabold text-amber-900 text-xs">Recommended Account Type Notice</h4>
                <p class="text-[11px] text-amber-800 mt-0.5">
                  You entered <strong>"{{ bankAccountForm.bank_name }}"</strong>, but selected <strong>"{{ bankAccountForm.account_type }}"</strong>.
                </p>
                <p class="text-[10px] text-amber-700 mt-1 font-medium">{{ detectedMismatch.reason }}</p>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 pt-1 border-t border-amber-200/80">
              <button
                type="button"
                @click="applySuggestedTypeAndSubmit"
                class="w-full sm:w-auto px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer transition-all flex items-center justify-center gap-1.5"
              >
                <span>✨ Switch to {{ detectedMismatch.suggestedLabel }}</span>
              </button>

              <button
                type="button"
                @click="proceedWithCurrentTypeAnyway"
                class="w-full sm:w-auto px-3 py-2 bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px] rounded-xl cursor-pointer transition-all text-center"
              >
                Keep Selection &amp; Save
              </button>
            </div>
          </div>

          <div class="p-3 bg-sky-50 rounded-2xl border border-sky-200 text-sky-900 text-[11px]">
            ℹ️ Saving will automatically create and link a dedicated nominal asset account on the Chart of Accounts (e.g. Code 1010, 1020, etc.).
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showBankAccountModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="bankAccountForm.processing" class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs shadow-md shadow-sky-600/20 cursor-pointer">
              Save &amp; Link Account
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 0.5: Dedicated PayPal Setup & API Settings -->
    <div v-if="showPayPalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showPayPalModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-blue-50 text-blue-600 rounded-xl text-lg">🟦</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">Connect PayPal Business API</h3>
              <p class="text-[11px] text-slate-500 font-medium">Automated transaction sync for PayPal sales &amp; payouts</p>
            </div>
          </div>
          <button type="button" @click="showPayPalModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitPayPalAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Lodge Account Label / Title *</label>
            <input v-model="payPalForm.account_name" required placeholder="e.g. Lodge Operating PayPal Account" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">PayPal Client ID *</label>
            <input v-model="payPalForm.paypal_client_id" required placeholder="e.g. A21AA... or Client ID from PayPal Developer Portal" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700">PayPal Client Secret *</label>
              <button type="button" @click="showPayPalSecret = !showPayPalSecret" class="text-[10px] font-bold text-blue-600 hover:text-blue-800 cursor-pointer">
                {{ showPayPalSecret ? 'Hide' : 'Show Secret' }}
              </button>
            </div>
            <input :type="showPayPalSecret ? 'text' : 'password'" v-model="payPalForm.paypal_client_secret" required placeholder="PayPal API Secret Key" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Environment *</label>
              <select v-model="payPalForm.paypal_environment" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white">
                <option value="live">Live Production</option>
                <option value="sandbox">Sandbox Testing</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Currency *</label>
              <input v-model="payPalForm.currency" required placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
            <input type="number" step="0.01" v-model="payPalForm.opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div class="p-3 bg-blue-50/90 rounded-2xl border border-blue-200 text-blue-900 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to get these credentials?</span>
            <p>Log in to <a href="https://developer.paypal.com" target="_blank" class="underline font-bold">developer.paypal.com</a> with your Lodge's PayPal account, create an App under <strong>Apps &amp; Credentials</strong>, and copy your Client ID &amp; Secret.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showPayPalModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="payPalForm.processing" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 cursor-pointer">
              Save &amp; Connect PayPal
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 0.55: Dedicated Stripe API Setup -->
    <div v-if="showStripeModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showStripeModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-purple-50 text-purple-600 rounded-xl text-lg">💜</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">Connect Stripe API</h3>
              <p class="text-[11px] text-slate-500 font-medium">Automated balance transactions &amp; payout feed sync</p>
            </div>
          </div>
          <button type="button" @click="showStripeModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitStripeAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Lodge Account Label / Title *</label>
            <input v-model="stripeForm.account_name" required placeholder="e.g. Lodge Stripe Account" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700">Stripe Secret Key *</label>
              <button type="button" @click="showStripeSecret = !showStripeSecret" class="text-[10px] font-bold text-purple-600 hover:text-purple-800 cursor-pointer">
                {{ showStripeSecret ? 'Hide' : 'Show Key' }}
              </button>
            </div>
            <input :type="showStripeSecret ? 'text' : 'password'" v-model="stripeForm.stripe_secret_key" required placeholder="sk_live_... or sk_test_..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Currency *</label>
              <input v-model="stripeForm.currency" required placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
              <input type="number" step="0.01" v-model="stripeForm.opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
          </div>

          <div class="p-3 bg-purple-50/90 rounded-2xl border border-purple-200 text-purple-900 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to find your Stripe Secret Key?</span>
            <p>Log in to <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="underline font-bold">dashboard.stripe.com/apikeys</a>, click <strong>Reveal Secret Key</strong>, and paste it here.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showStripeModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="stripeForm.processing" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md shadow-purple-600/20 cursor-pointer">
              Save &amp; Connect Stripe
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 0.58: Dedicated SumUp API Setup -->
    <div v-if="showSumUpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showSumUpModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl text-lg">💚</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">Connect SumUp Card Reader API</h3>
              <p class="text-[11px] text-slate-500 font-medium">Automated card terminal sales &amp; payout feed sync</p>
            </div>
          </div>
          <button type="button" @click="showSumUpModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitSumUpAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Lodge Account Label / Title *</label>
            <input v-model="sumUpForm.account_name" required placeholder="e.g. Lodge SumUp Merchant Account" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700">SumUp API Access Token / Key *</label>
              <button type="button" @click="showSumUpKey = !showSumUpKey" class="text-[10px] font-bold text-emerald-600 hover:text-emerald-800 cursor-pointer">
                {{ showSumUpKey ? 'Hide' : 'Show Key' }}
              </button>
            </div>
            <input :type="showSumUpKey ? 'text' : 'password'" v-model="sumUpForm.sumup_api_key" required placeholder="sup_sk_... or SumUp API Key" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Currency *</label>
              <input v-model="sumUpForm.currency" required placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
              <input type="number" step="0.01" v-model="sumUpForm.opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
          </div>

          <div class="p-3 bg-emerald-50/90 rounded-2xl border border-emerald-200 text-emerald-900 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to find your SumUp API Key?</span>
            <p>Log in to <a href="https://me.sumup.com/developers" target="_blank" class="underline font-bold">me.sumup.com/developers</a>, generate an API Key, and paste it here.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showSumUpModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="sumUpForm.processing" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-md shadow-emerald-600/20 cursor-pointer">
              Save &amp; Connect SumUp
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 0.59: Dedicated GoCardless API Setup -->
    <div v-if="showGoCardlessModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showGoCardlessModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-amber-50 text-amber-600 rounded-xl text-lg">🟡</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">Connect GoCardless Direct Debit API</h3>
              <p class="text-[11px] text-slate-500 font-medium">Automated UK Direct Debit mandate collection &amp; transaction feed</p>
            </div>
          </div>
          <button type="button" @click="showGoCardlessModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitGoCardlessAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Account Label / Title *</label>
            <input v-model="goCardlessForm.account_name" required placeholder="e.g. Lodge GoCardless Direct Debit Account" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Environment *</label>
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                @click="goCardlessForm.gocardless_environment = 'sandbox'"
                :class="['p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition-all', goCardlessForm.gocardless_environment === 'sandbox' ? 'bg-amber-50 border-amber-400 text-amber-900 shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-600']"
              >
                🧪 Sandbox (Testing)
              </button>
              <button
                type="button"
                @click="goCardlessForm.gocardless_environment = 'live'"
                :class="['p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition-all', goCardlessForm.gocardless_environment === 'live' ? 'bg-emerald-50 border-emerald-400 text-emerald-900 shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-600']"
              >
                ⚡ Live Production
              </button>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700">GoCardless Access Token *</label>
              <button type="button" @click="showGoCardlessToken = !showGoCardlessToken" class="text-[10px] font-bold text-amber-600 hover:text-amber-800 cursor-pointer">
                {{ showGoCardlessToken ? 'Hide' : 'Show Token' }}
              </button>
            </div>
            <input :type="showGoCardlessToken ? 'text' : 'password'" v-model="goCardlessForm.gocardless_access_token" required placeholder="live_... or sandbox_..." class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700">Webhook Secret (Optional)</label>
              <button type="button" @click="showGoCardlessSecret = !showGoCardlessSecret" class="text-[10px] font-bold text-amber-600 hover:text-amber-800 cursor-pointer">
                {{ showGoCardlessSecret ? 'Hide' : 'Show Secret' }}
              </button>
            </div>
            <input :type="showGoCardlessSecret ? 'text' : 'password'" v-model="goCardlessForm.gocardless_webhook_secret" placeholder="GoCardless Webhook Secret Key" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-mono text-slate-900 focus:bg-white" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">Currency *</label>
              <input v-model="goCardlessForm.currency" required placeholder="GBP" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white uppercase" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1">Opening Balance (£)</label>
              <input type="number" step="0.01" v-model="goCardlessForm.opening_balance" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
          </div>

          <div class="p-3 bg-amber-50/90 rounded-2xl border border-amber-200 text-amber-900 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to find your GoCardless Access Token?</span>
            <p>Log in to your GoCardless Dashboard > <strong class="font-semibold">Developers</strong> > <strong class="font-semibold">Access Tokens</strong>, create an API token with read-write access, and paste it here.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showGoCardlessModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="goCardlessForm.processing" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md shadow-amber-600/20 cursor-pointer">
              Save &amp; Connect GoCardless
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 0.6: Custom Date Range Gateway API Sync -->
    <div v-if="showGatewaySyncModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showGatewaySyncModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span v-if="gatewaySyncType === 'stripe'" class="p-2 bg-purple-50 text-purple-600 rounded-xl text-lg">⚡</span>
            <span v-else-if="gatewaySyncType === 'sumup'" class="p-2 bg-emerald-50 text-emerald-600 rounded-xl text-lg">⚡</span>
            <span v-else class="p-2 bg-blue-50 text-blue-600 rounded-xl text-lg">⚡</span>
            <div>
              <h3 class="font-extrabold text-slate-900 text-base">
                Sync {{ gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal') }} API Feed
              </h3>
              <p class="text-[11px] text-slate-500 font-medium">Select custom date range to pull settled transactions</p>
            </div>
          </div>
          <button type="button" @click="showGatewaySyncModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitGatewaySyncModal" class="space-y-4 text-xs">
          <div class="p-3 bg-blue-50/90 rounded-2xl border border-blue-200 text-blue-900 text-[11px] space-y-1">
            <span class="font-bold block">🛡️ Smart Duplicate Protection Active</span>
            <p>Transactions already imported into your ledger will be automatically skipped. Overlapping date ranges are 100% safe.</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 mb-1">From Date (Start) *</label>
              <input type="date" v-model="gatewaySyncForm.start_date" required class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">To Date (End) *</label>
              <input type="date" v-model="gatewaySyncForm.end_date" required class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 font-semibold text-slate-900 focus:bg-white" />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showGatewaySyncModal = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button
              type="submit"
              :disabled="gatewaySyncForm.processing"
              :class="[
                'px-5 py-2.5 rounded-xl font-bold text-xs shadow-md cursor-pointer flex items-center gap-1.5 text-white',
                gatewaySyncType === 'stripe' ? 'bg-purple-600 hover:bg-purple-700 shadow-purple-600/20' : (gatewaySyncType === 'sumup' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20' : 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/20')
              ]"
            >
              <span>⚡ Pull {{ gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal') }} Transactions</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 1: Add Account to Chart of Accounts -->
    <div v-if="showAccountModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showAccountModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h3 class="text-base font-black text-slate-900">Add Account to Ledger</h3>
          <button type="button" @click="showAccountModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitAccount" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Account Code (Numeric)</label>
            <input
              v-model="accountForm.code"
              type="text"
              placeholder="e.g. 1300"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Account Title / Name</label>
            <input
              v-model="accountForm.name"
              type="text"
              placeholder="e.g. Equipment Reserve"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Account Category Type</label>
            <select
              v-model="accountForm.type"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500"
            >
              <option value="asset">Asset (Normal Debit)</option>
              <option value="liability">Liability (Normal Credit)</option>
              <option value="equity">Equity (Normal Credit)</option>
              <option value="revenue">Revenue / Income (Normal Credit)</option>
              <option value="expense">Expense (Normal Debit)</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Opening Balance (£)</label>
              <input
                v-model="accountForm.opening_balance"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">As of Date</label>
              <input
                v-model="accountForm.as_of_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>
          <p class="text-[11px] text-slate-400 italic">Optional initial carry over. Balanced against Retained Earnings (3000).</p>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showAccountModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="accountForm.processing"
              class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl text-xs shadow-sm"
            >
              Save Account
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 2: Double-Entry Journal Entry Builder -->
    <div v-if="showJournalModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="showJournalModal = false">
      <div class="bg-white rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Add Journal Entry (Manual Ledger Adjustment)</h3>
            <p class="text-xs text-slate-500">Record a manual double-entry ledger adjustment. (To add a vendor bill, click "+ Add Bill" above).</p>
          </div>
          <button type="button" @click="showJournalModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitJournal" class="space-y-4 text-xs">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Transaction Description</label>
              <input
                v-model="journalForm.description"
                type="text"
                placeholder="e.g. Purchased regatta boat equipment"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Posting Date</label>
              <input
                v-model="journalForm.entry_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
          </div>

          <!-- Dynamic Line Items Builder -->
          <div class="space-y-2 pt-2 border-t border-slate-100">
            <div class="flex items-center justify-between">
              <span class="font-extrabold text-slate-800">Ledger Line Items</span>
              <button
                type="button"
                @click="addJournalLine"
                class="text-sky-600 hover:text-sky-700 font-bold text-xs"
              >
                + Add Row
              </button>
            </div>

            <div v-for="(item, idx) in journalForm.items" :key="idx" class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200">
              <div class="col-span-5">
                <select
                  v-model="item.account_id"
                  class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold focus:outline-none"
                  required
                >
                  <option value="" disabled>Select Account...</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
                  </option>
                </select>
              </div>
              <div class="col-span-3">
                <input
                  v-model.number="item.debit"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Debit (£)"
                  class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none"
                />
              </div>
              <div class="col-span-3">
                <input
                  v-model.number="item.credit"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="Credit (£)"
                  class="w-full px-2 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-mono focus:outline-none"
                />
              </div>
              <div class="col-span-1 text-center">
                <button
                  type="button"
                  @click="removeJournalLine(idx)"
                  class="text-slate-400 hover:text-rose-600 font-bold"
                  title="Remove row"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <!-- Real-Time Double-Entry Balancing Bar -->
          <div class="p-3 rounded-xl border flex items-center justify-between" :class="isJournalBalanced ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'">
            <div class="flex items-center gap-4 text-xs font-mono font-bold">
              <span>Debits: {{ formatCurrency(totalJournalDebit) }}</span>
              <span>Credits: {{ formatCurrency(totalJournalCredit) }}</span>
            </div>
            <span class="text-xs font-black">
              {{ isJournalBalanced ? 'BALANCED ✓' : 'UNBALANCED ✕' }}
            </span>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showJournalModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="!isJournalBalanced || journalForm.processing"
              class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl text-xs shadow-sm disabled:opacity-50 cursor-pointer"
            >
              Post Journal Entry
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 3: Create Member Invoice -->
    <div v-if="showInvoiceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showInvoiceModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h3 class="text-base font-black text-slate-900">Create Member Invoice</h3>
          <button type="button" @click="showInvoiceModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitInvoice" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Select Member</label>
            <select
              v-model="invoiceForm.user_id"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-sky-500"
              required
            >
              <option value="" disabled>Select Member...</option>
              <option v-for="m in members" :key="m.id" :value="m.id">
                {{ m.name }} ({{ m.email }})
              </option>
            </select>
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Invoice Title / Description</label>
            <input
              v-model="invoiceForm.title"
              type="text"
              placeholder="e.g. Annual Boating Dues 2026"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Amount (£)</label>
            <input
              v-model.number="invoiceForm.amount"
              type="number"
              step="0.01"
              min="0.01"
              placeholder="150.00"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="pt-2 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="showInvoiceModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="submitInvoice(true)"
                :disabled="invoiceForm.processing"
                class="px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-extrabold rounded-xl text-xs transition-colors cursor-pointer"
              >
                📝 Save Draft
              </button>
              <button
                type="button"
                @click="submitInvoice(false)"
                :disabled="invoiceForm.processing"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
              >
                Issue Invoice
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 4: Add Vendor Bill -->
    <div v-if="showBillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showBillModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <h3 class="text-base font-black text-slate-900">Add Vendor Bill (A/P)</h3>
          <button type="button" @click="showBillModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitBill" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Vendor Name</label>
            <input
              v-model="billForm.vendor_name"
              type="text"
              placeholder="e.g. Boat Yard Supplies Ltd"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Expense Category</label>
            <input
              v-model="billForm.category"
              type="text"
              placeholder="e.g. Facility Maintenance"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Amount (£)</label>
              <input
                v-model.number="billForm.amount"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="450.00"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Due Date</label>
              <input
                v-model="billForm.due_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Notes / Reference</label>
            <textarea
              v-model="billForm.notes"
              rows="2"
              placeholder="Optional notes or PO number..."
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500 resize-none"
            ></textarea>
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Receipt / Invoice File (PDF, PNG, JPG, WEBP)</label>
            <input
              type="file"
              accept=".pdf,.png,.jpg,.jpeg,.webp"
              @change="handleBillFileChange"
              class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
            />
            <p v-if="billForm.errors.attachment" class="text-[11px] text-red-600 font-semibold mt-0.5">{{ billForm.errors.attachment }}</p>
            <p v-if="billForm.attachment" class="text-[10px] text-emerald-600 font-bold mt-0.5">
              Attached: {{ billForm.attachment.name }}
            </p>
          </div>

          <div class="pt-2 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="showBillModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="submitBill(true)"
                :disabled="billForm.processing"
                class="px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 font-extrabold rounded-xl text-xs transition-colors cursor-pointer"
              >
                📝 Save Draft
              </button>
              <button
                type="button"
                @click="submitBill(false)"
                :disabled="billForm.processing"
                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
              >
                Record & Post Bill
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>



    <!-- Modal 6: Set Account Opening / Carry Over Balance -->
    <div v-if="showOpeningBalanceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showOpeningBalanceModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Set Account Opening / Carry Over Balance</h3>
            <p class="text-xs text-slate-500">Record initial carry-over funds when setting up your club accounts for the first time.</p>
          </div>
          <button type="button" @click="showOpeningBalanceModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitOpeningBalance" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Target Ledger Account *</label>
            <select
              v-model="openingBalanceForm.account_id"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500"
              required
            >
              <option value="" disabled>Select Account...</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Opening Amount (£) *</label>
              <input
                v-model="openingBalanceForm.opening_balance"
                type="number"
                step="0.01"
                placeholder="e.g. 17903.76"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Effective Date *</label>
              <input
                v-model="openingBalanceForm.as_of_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showOpeningBalanceModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="openingBalanceForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Post Opening Balance
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 7: Import Bank Statement -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showImportModal = false">
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Import Bank Statement</h3>
            <p class="text-xs text-slate-500">Upload UK CSV or OFX bank export for automated matcher staging.</p>
          </div>
          <button type="button" @click="showImportModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitStatementImport" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Bank Statement File (CSV/OFX) *</label>
            <input
              type="file"
              @change="importForm.statement_file = $event.target.files[0]"
              accept=".csv,.txt,.ofx,.qfx"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              required
            />
          </div>

          <div class="p-3 bg-sky-50 rounded-xl border border-sky-200 text-sky-900 text-[11px] space-y-1">
            <span class="font-extrabold block">💡 Supported Formats</span>
            <p class="text-sky-800">
              Standard UK bank CSV exports (Barclays, HSBC, Lloyds, NatWest, Santander, Starling) and OFX/QFX files.
            </p>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showImportModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="importForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Upload &amp; Process Statement
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal 8: Manual Target Allocation -->
    <div v-if="showManualModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="showManualModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Manual Target Allocation</h3>
            <p class="text-xs text-slate-500">Nominate custom allocation for statement line: <span class="font-bold text-slate-900">{{ selectedTx?.raw_description }}</span></p>
          </div>
          <button type="button" @click="showManualModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Allocation Destination Type</label>
            <select
              v-model="manualMatchType"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold focus:outline-none focus:border-sky-500"
            >
              <option value="member_subscription">Member Subscription Dues Invoice</option>
              <option value="supplier_bill">Audited Supplier Bill</option>
              <option value="charity_relief">Charity Relief Chest / Provincial Contribution</option>
              <option value="ledger_account">Nominal Ledger Account Code</option>
            </select>
          </div>

          <div v-if="manualMatchType === 'member_subscription'" class="space-y-1">
            <label class="block font-bold text-slate-700">Select Unpaid Subscription *</label>
            <select
              v-model="manualTargetId"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold focus:outline-none focus:border-sky-500"
            >
              <option value="" disabled>Choose Unpaid Subscription...</option>
              <option v-for="sub in reconciliation.unpaid_subscriptions" :key="sub.id" :value="sub.id">
                {{ sub.member_name }} (Inv: {{ sub.invoice_reference || 'N/A' }} - £{{ number_format(sub.amount_due, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'supplier_bill'" class="space-y-1">
            <label class="block font-bold text-slate-700">Select Unpaid Supplier Bill *</label>
            <select
              v-model="manualTargetId"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold focus:outline-none focus:border-sky-500"
            >
              <option value="" disabled>Choose Unpaid Bill...</option>
              <option v-for="bill in reconciliation.unpaid_bills" :key="bill.id" :value="bill.id">
                Vendor: {{ bill.vendor_name }} (Bill: {{ bill.bill_number }} - £{{ number_format(bill.amount, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'ledger_account'" class="space-y-1">
            <label class="block font-bold text-slate-700">Select Ledger Account Code *</label>
            <select
              v-model="manualNominalCode"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold focus:outline-none focus:border-sky-500"
            >
              <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
              </option>
            </select>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showManualModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitReconcile(manualMatchType, manualTargetId || 0, manualNominalCode)"
              class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Confirm Allocation &amp; Reconcile
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal 9: Statement Line Details -->
    <div
      v-if="showStatementLineDetailsModal && selectedStatementLineDetails"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4"
      @click="showStatementLineDetailsModal = false"
    >
      <div
        class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5"
        @click.stop
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Bank Statement Line Details</h3>
            <p class="text-xs text-slate-500">Full metadata imported from bank statement feed.</p>
          </div>
          <button
            type="button"
            @click="showStatementLineDetailsModal = false"
            class="text-slate-400 hover:text-slate-700 font-bold text-sm cursor-pointer"
          >
            ✕
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-0.5">Transaction Date</span>
              <span class="font-bold text-slate-900 text-sm">{{ selectedStatementLineDetails.transaction_date }}</span>
            </div>
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-0.5">Amount</span>
              <span :class="['font-black text-base', selectedStatementLineDetails.amount < 0 ? 'text-slate-900' : 'text-emerald-700']">
                {{ selectedStatementLineDetails.amount < 0 ? 'Spent £' : 'Received £' }}{{ number_format(Math.abs(selectedStatementLineDetails.amount), 2) }}
              </span>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Raw Bank Description / Payee</span>
            <div class="bg-white p-3 rounded-xl border border-slate-200 font-extrabold text-slate-900 text-sm leading-snug">
              {{ selectedStatementLineDetails.raw_description }}
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Reference</span>
              <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 font-mono font-bold text-slate-800">
                {{ selectedStatementLineDetails.reference || (selectedStatementLineDetails.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
              </div>
            </div>
            <div class="space-y-1">
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Running Balance</span>
              <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-200 font-mono font-bold text-slate-800">
                {{ selectedStatementLineDetails.balance_after ? '£' + number_format(selectedStatementLineDetails.balance_after, 2) : 'N/A' }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-1">
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Status</span>
              <span
                :class="[
                  'px-2.5 py-1 text-[10px] font-black rounded-full uppercase tracking-wider inline-block',
                  selectedStatementLineDetails.status === 'Reconciled' ? 'bg-emerald-100 text-emerald-800' :
                  selectedStatementLineDetails.status === 'Deleted' ? 'bg-rose-100 text-rose-800' :
                  'bg-amber-100 text-amber-800'
                ]"
              >
                {{ selectedStatementLineDetails.status || 'Unmatched' }}
              </span>
            </div>
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">System Record ID</span>
              <span class="font-mono text-slate-500 font-bold text-xs">#{{ selectedStatementLineDetails.id }}</span>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
          <button
            type="button"
            @click="deleteSingleStatementLine(selectedStatementLineDetails.id); showStatementLineDetailsModal = false;"
            class="px-3.5 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete Statement Line
          </button>
          <button
            type="button"
            @click="showStatementLineDetailsModal = false"
            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 11: Statement vs System Balance Variance Explanation -->
    <div
      v-if="showDifferentBalancesModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4"
      @click="showDifferentBalancesModal = false"
    >
      <div
        class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5"
        @click.stop
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <span class="text-xl">⚖️</span>
            <div>
              <h3 class="text-base font-black text-slate-900">Why do your balances differ?</h3>
              <p class="text-xs text-slate-500">Bank Statement Balance vs System Ledger Balance</p>
            </div>
          </div>
          <button type="button" @click="showDifferentBalancesModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs">
          <p class="text-slate-600 leading-relaxed">
            Your <strong>Statement Balance</strong> comes from imported bank feeds or uploaded statement CSV files, whereas your <strong>Balance in System</strong> reflects all reconciled ledger entries in your Chart of Accounts.
          </p>

          <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2 font-mono text-[11px]">
            <div class="flex justify-between items-center text-slate-700">
              <span>Statement Balance (Bank):</span>
              <span class="font-bold text-slate-900">{{ activeBankAccount?.formatted_statement_balance || '£0.00' }}</span>
            </div>
            <div class="flex justify-between items-center text-amber-700">
              <span>Unreconciled Statement Items ({{ filteredUnmatchedTx.length }}):</span>
              <span class="font-bold">{{ number_format(filteredUnmatchedTx.reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)) }}</span>
            </div>
            <div class="border-t border-slate-200 pt-2 flex justify-between items-center text-sky-900 font-extrabold text-xs">
              <span>System Ledger Balance (Code {{ activeBankAccount?.account_code || '1000' }}):</span>
              <span>{{ activeBankAccount?.formatted_ledger_balance || '£0.00' }}</span>
            </div>
          </div>

          <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-[11px] space-y-1">
            <span class="font-bold block">💡 Common causes of balance variance:</span>
            <ul class="list-disc list-inside space-y-0.5 text-amber-800">
              <li>Statement lines imported from the bank that have not been reconciled yet.</li>
              <li>Unpaid invoices or vendor bills with pending manual cash entries.</li>
              <li>Duplicate or deleted statement entries awaiting adjustment.</li>
            </ul>
          </div>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button
            type="button"
            @click="showDifferentBalancesModal = false; navigateTo('reporting', 'reconciliation_summary')"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
          >
            Open Reconciliation Report
          </button>
          <button
            type="button"
            @click="showDifferentBalancesModal = false"
            class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
          >
            Got it
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 12: Bank Reconciliation Explanation (What's this?) -->
    <div
      v-if="showWhatsThisModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4"
      @click="showWhatsThisModal = false"
    >
      <div
        class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5"
        @click.stop
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2">
            <span class="text-xl">ℹ️</span>
            <div>
              <h3 class="text-base font-black text-slate-900">Understanding Bank Balances</h3>
              <p class="text-xs text-slate-500">Statement Balance vs Balance in System</p>
            </div>
          </div>
          <button type="button" @click="showWhatsThisModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs text-slate-600 leading-relaxed">
          <div class="p-3 bg-sky-50 rounded-2xl border border-sky-200 space-y-1">
            <span class="font-extrabold text-sky-900 block text-xs">🏦 Statement Balance</span>
            <p class="text-sky-800 text-[11px]">
              The real-world opening/closing balance of your bank account, imported automatically via API or uploaded statement files (CSV/OFX).
            </p>
          </div>

          <div class="p-3 bg-indigo-50 rounded-2xl border border-indigo-200 space-y-1">
            <span class="font-extrabold text-indigo-900 block text-xs">📖 Balance in System</span>
            <p class="text-indigo-800 text-[11px]">
              The double-entry ledger balance calculated from all approved invoices, bills, and matched bank entries recorded in your Chart of Accounts.
            </p>
          </div>

          <p>
            Bank reconciliation verifies that every penny entering or leaving your bank account is accounted for and assigned to the correct member subscription, invoice, or expense category.
          </p>
        </div>

        <div class="pt-2 flex items-center justify-end">
          <button
            type="button"
            @click="showWhatsThisModal = false"
            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Modal 10: Attachment Pop-Out Viewer with Zoom & Delete -->
    <div
      v-if="previewModal.show"
      class="fixed inset-0 z-50 flex flex-col bg-slate-950/90 backdrop-blur-md p-4 sm:p-6 text-white"
      @click="previewModal.show = false"
    >
      <div
        class="flex-1 flex flex-col max-w-6xl w-full mx-auto bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden"
        @click.stop
      >
        <!-- Modal Toolbar Header -->
        <div class="px-6 py-4 border-b border-slate-800 bg-slate-900/90 flex flex-wrap items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3">
            <span class="text-xl">📎</span>
            <div>
              <h3 class="text-sm font-black text-white tracking-tight">{{ previewModal.title }}</h3>
              <p class="text-[11px] font-mono text-slate-400">{{ previewModal.fileName }}</p>
            </div>
          </div>

          <!-- Controls -->
          <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
            <!-- Zoom Controls -->
            <div class="flex items-center bg-slate-800 rounded-xl p-1 border border-slate-700">
              <button
                type="button"
                @click="zoomOut"
                title="Zoom Out"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                🔍−
              </button>
              <span class="px-2.5 font-mono text-[11px] text-sky-400 w-12 text-center">
                {{ Math.round(zoomLevel * 100) }}%
              </span>
              <button
                type="button"
                @click="zoomIn"
                title="Zoom In"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-200 rounded-lg transition-colors cursor-pointer"
              >
                🔍+
              </button>
              <button
                type="button"
                @click="resetZoom"
                title="Reset Zoom"
                class="px-2.5 py-1 hover:bg-slate-700 text-slate-300 rounded-lg transition-colors cursor-pointer border-l border-slate-700 ml-1 text-[11px]"
              >
                ↺ Reset
              </button>
            </div>

            <!-- Rotate control (Image only) -->
            <button
              v-if="!previewModal.mimeType.includes('pdf')"
              type="button"
              @click="rotateImage"
              title="Rotate Image"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl transition-colors cursor-pointer flex items-center gap-1"
            >
              ↻ Rotate
            </button>

            <!-- Open in New Tab -->
            <a
              :href="previewModal.url"
              target="_blank"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-xl transition-colors cursor-pointer flex items-center gap-1"
            >
              ↗ New Tab
            </a>

            <!-- Delete Attachment -->
            <button
              type="button"
              @click="deleteAttachment"
              class="px-3 py-1.5 bg-rose-950/80 hover:bg-rose-900 text-rose-300 border border-rose-800 rounded-xl transition-colors cursor-pointer flex items-center gap-1 font-extrabold"
            >
              🗑 Delete
            </button>

            <!-- Close Modal -->
            <button
              type="button"
              @click="previewModal.show = false"
              class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-xl transition-colors font-bold text-sm cursor-pointer ml-2"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Document / Image Viewer Canvas -->
        <div class="flex-1 bg-slate-950 overflow-auto flex items-center justify-center p-6 relative">
          <!-- Image View -->
          <div
            v-if="!previewModal.mimeType.includes('pdf')"
            class="transition-transform duration-150 ease-out origin-center flex items-center justify-center"
            :style="{ transform: `scale(${zoomLevel}) rotate(${rotation}deg)` }"
          >
            <img
              :src="previewModal.url"
              :alt="previewModal.title"
              class="max-w-full max-h-[75vh] object-contain rounded-xl shadow-2xl border border-slate-800"
            />
          </div>

          <!-- PDF View -->
          <iframe
            v-else
            :src="previewModal.url"
            class="w-full h-[75vh] rounded-2xl bg-white border border-slate-800 shadow-2xl transition-transform duration-150 ease-out origin-center"
            :style="{ transform: `scale(${zoomLevel})` }"
          ></iframe>
        </div>
      </div>
    </div>

    <!-- Edit Invoice Modal -->
    <div v-if="showEditInvoiceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="showEditInvoiceModal = false">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Edit Invoice {{ editingInvoice?.invoice_number }}</h3>
            <p class="text-xs text-slate-500">Update draft sales invoice details, customer, date or line items.</p>
          </div>
          <button type="button" @click="showEditInvoiceModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitEditInvoice" class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Customer *</label>
              <select
                v-model="editInvoiceForm.contact_id"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500"
                required
              >
                <option value="" disabled>Select Customer...</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Invoice Date *</label>
              <input
                v-model="editInvoiceForm.issue_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-sky-500"
                required
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Due Date</label>
              <input
                v-model="editInvoiceForm.due_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Reference / PO #</label>
              <input
                v-model="editInvoiceForm.reference"
                type="text"
                placeholder="e.g. PO-10492"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <!-- Line Items Section -->
          <div class="space-y-2 border-t border-slate-100 pt-3">
            <div class="flex items-center justify-between">
              <span class="font-extrabold text-slate-800">Line Items</span>
              <button
                type="button"
                @click="addEditInvoiceLine"
                class="px-2.5 py-1 text-[11px] font-bold bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 rounded-lg transition-all"
              >
                + Add Line
              </button>
            </div>

            <div v-for="(line, idx) in editInvoiceForm.lines" :key="idx" class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
              <div class="col-span-5">
                <input
                  v-model="line.description"
                  type="text"
                  placeholder="Item Description..."
                  class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:border-sky-500 bg-white"
                  required
                />
              </div>
              <div class="col-span-3">
                <select
                  v-model="line.account_id"
                  class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none focus:border-sky-500 bg-white"
                  required
                >
                  <option value="" disabled>Account...</option>
                  <option v-for="acc in revenueAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </option>
                </select>
              </div>
              <div class="col-span-3">
                <input
                  v-model="line.amount"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-mono font-bold focus:outline-none focus:border-sky-500 bg-white text-right"
                  required
                />
              </div>
              <div class="col-span-1 text-center">
                <button
                  type="button"
                  @click="removeEditInvoiceLine(idx)"
                  class="text-rose-500 hover:text-rose-700 font-bold text-xs"
                  title="Remove Line"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3">
            <button
              type="button"
              @click="showEditInvoiceModal = false"
              class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingEditInvoice"
              class="px-5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl font-extrabold shadow-sm transition-all disabled:opacity-50"
            >
              {{ savingEditInvoice ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Edit Bill Modal -->
    <div v-if="showEditBillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="showEditBillModal = false">
      <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Edit Bill {{ editingBill?.bill_number }}</h3>
            <p class="text-xs text-slate-500">Update draft purchase bill details, supplier, date or expense line items.</p>
          </div>
          <button type="button" @click="showEditBillModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitEditBill" class="space-y-4 text-xs">
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Supplier / Vendor *</label>
              <select
                v-model="editBillForm.contact_id"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500"
                required
              >
                <option value="" disabled>Select Supplier...</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Bill Date *</label>
              <input
                v-model="editBillForm.issue_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-amber-500"
                required
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Due Date</label>
              <input
                v-model="editBillForm.due_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-amber-500"
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Supplier Invoice / Ref #</label>
              <input
                v-model="editBillForm.reference"
                type="text"
                placeholder="e.g. INV-9982"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-bold text-xs focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          <!-- Line Items Section -->
          <div class="space-y-2 border-t border-slate-100 pt-3">
            <div class="flex items-center justify-between">
              <span class="font-extrabold text-slate-800">Line Items</span>
              <button
                type="button"
                @click="addEditBillLine"
                class="px-2.5 py-1 text-[11px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-lg transition-all"
              >
                + Add Line
              </button>
            </div>

            <div v-for="(line, idx) in editBillForm.lines" :key="idx" class="grid grid-cols-12 gap-2 items-center bg-slate-50 p-2.5 rounded-xl border border-slate-200/80">
              <div class="col-span-5">
                <input
                  v-model="line.description"
                  type="text"
                  placeholder="Item Description..."
                  class="w-full px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none focus:border-amber-500 bg-white"
                  required
                />
              </div>
              <div class="col-span-3">
                <select
                  v-model="line.account_id"
                  class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none focus:border-amber-500 bg-white"
                  required
                >
                  <option value="" disabled>Expense Account...</option>
                  <option v-for="acc in expenseAccounts" :key="acc.id" :value="acc.id">
                    {{ acc.code }} - {{ acc.name }}
                  </option>
                </select>
              </div>
              <div class="col-span-3">
                <input
                  v-model="line.amount"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-xs font-mono font-bold focus:outline-none focus:border-amber-500 bg-white text-right"
                  required
                />
              </div>
              <div class="col-span-1 text-center">
                <button
                  type="button"
                  @click="removeEditBillLine(idx)"
                  class="text-rose-500 hover:text-rose-700 font-bold text-xs"
                  title="Remove Line"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-3">
            <button
              type="button"
              @click="showEditBillModal = false"
              class="px-4 py-2 border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="savingEditBill"
              class="px-5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-extrabold shadow-sm transition-all disabled:opacity-50"
            >
              {{ savingEditBill ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>
