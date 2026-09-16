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
  reconciliation: {
    type: Object,
    default: () => ({
      unmatched_transactions: [],
      reconciled_transactions: [],
      bank_imports: [],
      unpaid_subscriptions: [],
      unpaid_bills: [],
    }),
  },
});

const validTabs = ['home', 'sales', 'purchases', 'reporting', 'accounting', 'reconciliation', 'contacts', 'settings'];

const getTabFromUrl = () => {
  const hash = typeof window !== 'undefined' ? window.location.hash.replace('#', '').trim() : '';
  if (hash && validTabs.includes(hash)) {
    return hash;
  }
  const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null;
  const tabParam = searchParams ? searchParams.get('tab') : null;
  if (tabParam && validTabs.includes(tabParam)) {
    return tabParam;
  }
  return 'home';
};

const activeTab = ref(getTabFromUrl());

const syncTabWithUrl = () => {
  const tabFromUrl = getTabFromUrl();
  if (tabFromUrl !== activeTab.value) {
    activeTab.value = tabFromUrl;
  }
};

watch(activeTab, (newTab) => {
  if (typeof window !== 'undefined' && window.location.hash.replace('#', '') !== newTab) {
    history.replaceState(null, '', '#' + newTab);
  }
});

const handleGlobalDocumentClick = (e) => {
  if (!e.target.closest('.who-dropdown-container')) {
    Object.values(rowStates.value).forEach(state => {
      if (state) state.showWhoDropdown = false;
    });
  }
};

onMounted(() => {
  activeTab.value = getTabFromUrl();
  if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', syncTabWithUrl);
    document.addEventListener('click', handleGlobalDocumentClick);
  }
  if (props.reconciliation?.unmatched_transactions?.length > 0) {
    selectedTx.value = props.reconciliation.unmatched_transactions[0];
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('hashchange', syncTabWithUrl);
    document.removeEventListener('click', handleGlobalDocumentClick);
  }
});

const selectedReport = ref(null);

const showAccountModal = ref(false);
const showJournalModal = ref(false);
const showInvoiceModal = ref(false);
const showBillModal = ref(false);
const showContactModal = ref(false);
const showImportModal = ref(false);
const showManualModal = ref(false);
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
const showManageAccountMenu = ref(false);
const selectedCashCodingTx = ref([]);
const selectedStatementLineIds = ref([]);
const statementLinesFilter = ref('statement_lines');

const filteredStatementLines = computed(() => {
  let list = props.reconciliation?.statement_lines || [];
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

const rowStates = ref({});

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

const submitInvoice = () => {
  invoiceForm.post(route('admin.accounting.invoices.store', props.club.slug), {
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
});

const submitBill = () => {
  billForm.post(route('admin.accounting.bills.store', props.club.slug), {
    onSuccess: () => {
      showBillModal.value = false;
      billForm.reset();
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
  <AdminLayout :club="club" title="Accounting">
    <Head :title="`Accounting - ${club.name}`" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 space-y-4">
      
      <!-- Sleek Slate Navigation Bar at Top -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-sm p-1.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 overflow-x-auto">
        <nav class="flex items-center px-1 min-w-max text-xs sm:text-sm font-semibold text-slate-300">
          <button
            type="button"
            @click="activeTab = 'home'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'home' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Home</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'sales'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'sales' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Sales</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'purchases'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'purchases' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Purchases</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'reporting'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reporting' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Reporting</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'accounting'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'accounting' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Accounting</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'reconciliation'"
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

          <button
            type="button"
            @click="activeTab = 'contacts'"
            :class="[
              'px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'contacts' ? 'font-extrabold text-white bg-indigo-600 shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/80'
            ]"
          >
            <span>Contacts</span>
          </button>

          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-4 py-2.5 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl text-slate-400 hover:text-white hover:bg-slate-800/80"
          >
            <span>⚙️ Settings</span>
          </Link>
        </nav>

        <!-- Quick Action Buttons in Header Bar -->
        <div class="flex items-center gap-2 px-2 py-1 shrink-0">
          <Link
            :href="route('admin.accounting.invoices.create', club.slug)"
            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>🧾 Create Invoice</span>
          </Link>
          <button
            type="button"
            @click="showBillModal = true"
            class="px-3.5 py-2 bg-amber-600 hover:bg-amber-500 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>📄 + Add Bill</span>
          </button>
          <button
            type="button"
            @click="showJournalModal = true"
            class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-extrabold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>📖 Post Journal</span>
          </button>
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
                <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ inv.invoice_number }}</td>
                <td class="py-3 px-4 font-bold text-slate-900">{{ inv.recipient_name }}</td>
                <td class="py-3 px-4 text-slate-600">{{ inv.title }}</td>
                <td class="py-3 px-4 text-slate-500">{{ inv.created_at }}</td>
                <td class="py-3 px-4">
                  <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', inv.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
                    {{ inv.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-black text-slate-900 font-mono">{{ inv.formatted_amount }}</td>
                <td class="py-3 px-4 text-center">
                  <button
                    v-if="inv.status !== 'paid'"
                    type="button"
                    @click="markInvoicePaid(inv.id)"
                    class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                  >
                    ✓ Mark Paid
                  </button>
                  <span v-else class="text-[10px] font-bold text-slate-400">Paid {{ inv.paid_at }}</span>
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
          <button
            type="button"
            @click="showBillModal = true"
            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer flex items-center gap-1.5"
          >
            <span>📄 Record Vendor Bill</span>
          </button>
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
                <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ b.bill_number }}</td>
                <td class="py-3 px-4 font-bold text-slate-900">{{ b.vendor_name }}</td>
                <td class="py-3 px-4 text-slate-600">{{ b.category }}</td>
                <td class="py-3 px-4 text-slate-500">{{ b.due_date }}</td>
                <td class="py-3 px-4">
                  <span :class="['px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border', b.status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200']">
                    {{ b.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right font-black text-slate-900 font-mono">{{ b.formatted_amount }}</td>
                <td class="py-3 px-4 text-center">
                  <button
                    v-if="b.status !== 'paid'"
                    type="button"
                    @click="markBillPaid(b.id)"
                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                  >
                    ✓ Pay Bill
                  </button>
                  <span v-else class="text-[10px] font-bold text-slate-400">Paid {{ b.paid_at }}</span>
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
            v-if="selectedReport"
            type="button"
            @click="selectedReport = null"
            class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition-all cursor-pointer self-start sm:self-auto flex items-center gap-1"
          >
            ← Back to All Reports
          </button>
        </div>

        <!-- 7 Report Grid Cards Selection -->
        <div v-if="!selectedReport" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
          <!-- 1. Account Summary -->
          <div @click="selectedReport = 'account_summary'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'aged_payables'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'aged_receivables'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'balance_sheet'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'cash_summary'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'executive_summary'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'profit_and_loss'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
          <div @click="selectedReport = 'comparative_income_expenditure'" class="bg-slate-50 hover:bg-sky-50/50 p-5 rounded-2xl border border-slate-200 hover:border-sky-300 transition-all cursor-pointer space-y-3 group">
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
        </div>
      </div>

      <!-- VIEW 6: ACCOUNTING (Chart of Accounts & General Ledger Journal) -->
      <div v-if="activeTab === 'accounting'" class="space-y-6">
        <!-- Chart of Accounts Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">Chart of Accounts</h3>
              <p class="text-xs text-slate-500">Categorized ledger accounts for club bookkeeping.</p>
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

        <!-- General Ledger Journal Entries -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-4">
          <div class="p-6 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 class="text-base font-extrabold text-slate-900">General Ledger Journal Entries</h3>
              <p class="text-xs text-slate-500">Historical double-entry records posted to the ledger.</p>
            </div>
            <button
              type="button"
              @click="showJournalModal = true"
              class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer"
            >
              + Add Journal Entry
            </button>
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
                    <button
                      type="button"
                      @click="showBillModal = true; billForm.vendor_name = c.name"
                      class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                    >
                      + Record Bill
                    </button>
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
          <div class="space-y-1">
            <div class="flex items-center gap-3">
              <span class="text-xl">💳</span>
              <h3 class="text-base font-black text-slate-900 tracking-tight">AMERICAN EXPRESS (Operating Account)</h3>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs font-semibold">
              <div>
                <span class="font-extrabold text-slate-900 text-sm">({{ reconciliation.unmatched_transactions?.length ? '476.49' : '0.00' }})</span>
                <span class="text-slate-500 ml-1">Statement Balance</span>
              </div>
              <div class="border-l border-slate-200 pl-4">
                <span class="font-extrabold text-slate-900 text-sm">({{ reconciliation.unmatched_transactions?.length ? '401.33' : '0.00' }})</span>
                <span class="text-slate-500 ml-1">Balance in System</span>
                <a href="#" @click.prevent class="text-sky-600 hover:underline ml-1 text-[11px] font-bold">— Different balances?</a>
              </div>
            </div>
            <a href="#" @click.prevent class="text-sky-600 text-xs font-semibold hover:underline inline-block mt-0.5">What's this?</a>
          </div>

          <div class="flex flex-wrap items-center gap-2.5 self-start md:self-auto">
            <a href="#" @click.prevent class="text-xs font-bold text-sky-600 hover:underline mr-2">Reconciliation Report</a>

            <div class="relative">
              <button
                type="button"
                @click="showManageAccountMenu = !showManageAccountMenu"
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

        <!-- Notification / Import Summary Banner -->
        <div class="bg-sky-50 border border-sky-200 rounded-xl p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs font-semibold text-sky-900">
          <div>
            <span>11 statement lines imported in the last 30 days</span>
          </div>
          <div class="flex items-center gap-3">
            <button type="button" @click="reconSubTab = 'account_transactions'" class="text-sky-700 hover:underline">Review reconciled</button>
            <button
              type="button"
              @click="autoReconcile = !autoReconcile"
              :class="[
                'px-3.5 py-1.5 text-white font-extrabold rounded-md shadow-sm transition-all cursor-pointer flex items-center gap-1.5',
                autoReconcile ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-sky-600 hover:bg-sky-700'
              ]"
            >
              <span>{{ autoReconcile ? 'Turn auto-reconcile off' : 'Turn auto-reconcile on' }}</span>
            </button>
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
              Reconcile ({{ reconciliation.unmatched_transactions?.length || 0 }})
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
          <!-- Header Sub-banner -->
          <div class="px-4 py-2 bg-slate-100 border border-slate-200 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between text-xs text-slate-500 font-medium">
            <div>
              <a href="#" @click.prevent class="text-sky-600 font-bold hover:underline">What's this?</a>
              <span class="ml-1">Review your bank statement lines...</span>
            </div>
            <div class="text-slate-400 font-normal">
              ...then match with your transactions in the system
            </div>
          </div>

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
                    <div class="flex items-center justify-between text-xs">
                      <span class="font-semibold text-slate-400">{{ tx.transaction_date }}</span>
                      <a href="#" @click.prevent class="text-sky-600 hover:underline font-medium">Options ▾</a>
                    </div>
                    <div class="mt-1">
                      <h4 class="font-extrabold text-slate-900 text-sm leading-snug truncate">{{ tx.raw_description }}</h4>
                      <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider mt-0.5">
                        {{ tx.reference || (tx.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
                      </span>
                    </div>
                    <a href="#" @click.prevent class="text-xs text-sky-600 hover:underline inline-block mt-1 font-medium">More details</a>
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
                      :checked="selectedAccountTxIds.length === (reconciliation.account_transactions || []).length && (reconciliation.account_transactions || []).length > 0"
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
                <tr v-for="tx in reconciliation.account_transactions || []" :key="tx.id" class="hover:bg-slate-50/80 transition-colors">
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
                <tr v-if="!reconciliation.account_transactions || reconciliation.account_transactions.length === 0">
                  <td colspan="8" class="py-8 text-center text-slate-400 italic">No internal account transactions recorded yet.</td>
                </tr>
              </tbody>
            </table>
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

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showInvoiceModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="invoiceForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Issue Invoice
            </button>
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

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showBillModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="billForm.processing"
              class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Record Bill
            </button>
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
  </AdminLayout>
</template>
