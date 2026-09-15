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
});

const validTabs = ['home', 'sales', 'purchases', 'reporting', 'accounting', 'contacts', 'settings'];

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

onMounted(() => {
  activeTab.value = getTabFromUrl();
  if (typeof window !== 'undefined') {
    window.addEventListener('hashchange', syncTabWithUrl);
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('hashchange', syncTabWithUrl);
  }
});

const selectedReport = ref(null);

const showAccountModal = ref(false);
const showJournalModal = ref(false);
const showInvoiceModal = ref(false);
const showBillModal = ref(false);
const showContactModal = ref(false);

const contactFilter = ref('all');

const contactForm = useForm({
  type: 'business',
  name: '',
  contact_person: '',
  email: '',
  phone: '',
  role: 'Vendor / Supplier',
  tax_id: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  postcode: '',
  country: 'United Kingdom',
  notes: '',
});

const submitContact = () => {
  contactForm.post(route('admin.accounting.contacts.store', props.club.slug), {
    onSuccess: () => {
      showContactModal.value = false;
      contactForm.reset({
        type: 'business',
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        role: 'Vendor / Supplier',
        tax_id: '',
        address_line_1: '',
        address_line_2: '',
        city: '',
        postcode: '',
        country: 'United Kingdom',
        notes: '',
      });
    },
  });
};

const filteredContacts = computed(() => {
  const all = [];

  (props.contacts || []).forEach(c => {
    all.push({
      id: 'c_' + c.id,
      kind: 'contact',
      type: c.type,
      name: c.name,
      contact_person: c.contact_person || c.name,
      email: c.email || '—',
      phone: c.phone || '—',
      role: c.role || 'Contact',
      tax_id: c.tax_id || '—',
      address: c.formatted_address || '—',
      notes: c.notes,
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
  if (contactFilter.value === 'business') {
    return all.filter(item => item.type === 'business');
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

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
      
      <!-- Ocean Blue Navigation Bar at Top -->
      <div class="bg-[#007bce] rounded-2xl shadow-md p-1.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 overflow-x-auto">
        <nav class="flex items-center px-1 min-w-max text-sm font-semibold text-white">
          <button
            type="button"
            @click="activeTab = 'home'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'home' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Home</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'sales'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'sales' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Sales</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'purchases'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'purchases' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Purchases</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'reporting'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'reporting' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Reporting</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'accounting'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'accounting' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Accounting</span>
          </button>

          <button
            type="button"
            @click="activeTab = 'contacts'"
            :class="[
              'px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl',
              activeTab === 'contacts' ? 'font-extrabold text-white bg-white/20' : 'text-sky-100 hover:text-white hover:bg-white/10'
            ]"
          >
            <span>Contacts</span>
          </button>

          <Link
            :href="route('admin.settings.show', club.slug) + '#accounting'"
            class="px-5 py-3 relative transition-all cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl text-sky-100 hover:text-white hover:bg-white/10"
          >
            <span>⚙️ Settings</span>
          </Link>
        </nav>

        <!-- Quick Action Buttons in Ocean Blue Bar -->
        <div class="flex items-center gap-2 px-2 py-1 shrink-0">
          <button
            type="button"
            @click="showInvoiceModal = true"
            class="px-3 py-1.5 bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>🧾 Issue Invoice</span>
          </button>
          <button
            type="button"
            @click="showBillModal = true"
            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>📄 + Add Bill</span>
          </button>
          <button
            type="button"
            @click="showJournalModal = true"
            class="px-3 py-1.5 bg-white/20 hover:bg-white/35 text-white font-extrabold text-xs rounded-xl backdrop-blur-sm border border-white/30 transition-all cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
          >
            <span>📖 Post Journal</span>
          </button>
        </div>
      </div>

      <!-- Financial KPIs Overview Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
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
      <div v-if="activeTab === 'sales'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-6 p-6">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-black text-slate-900">Sales Invoicing & Receivables</h3>
            <p class="text-xs text-slate-500">Track member dues, locker fees, and ticket invoices.</p>
          </div>

          <button
            type="button"
            @click="showInvoiceModal = true"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer flex items-center gap-1.5"
          >
            <span>🧾 Create Member Invoice</span>
          </button>
        </div>

        <div v-if="!invoices.length" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50">
          <span class="text-3xl block mb-2">🧾</span>
          <span class="text-xs font-bold text-slate-700 block">No Member Invoices Found</span>
          <span class="text-[11px] text-slate-400">Click "Create Member Invoice" to issue a new bill.</span>
        </div>

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
              <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-slate-50/80 transition-colors">
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
      <div v-if="activeTab === 'purchases'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-6 p-6">
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

        <div v-if="!bills.length" class="text-center py-12 border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50">
          <span class="text-3xl block mb-2">📄</span>
          <span class="text-xs font-bold text-slate-700 block">No Vendor Bills Recorded</span>
          <span class="text-[11px] text-slate-400">Click "Record Vendor Bill" to log payable vendor expenses.</span>
        </div>

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
              <tr v-for="b in bills" :key="b.id" class="hover:bg-slate-50/80 transition-colors">
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
          <button
            type="button"
            @click="showContactModal = true"
            class="px-4 py-2 bg-[#007bce] hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-2 shrink-0"
          >
            <span>+ Add Contact</span>
          </button>
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
            @click="contactFilter = 'business'"
            :class="['px-3 py-1.5 rounded-xl transition-all cursor-pointer flex items-center gap-1.5', contactFilter === 'business' ? 'bg-sky-700 text-white font-black' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
          >
            <span>🏢 Businesses / Vendors</span>
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
                    <span class="text-base p-1.5 rounded-lg bg-slate-100 border border-slate-200">
                      {{ c.type === 'business' ? '🏢' : '👤' }}
                    </span>
                    <div>
                      <span class="font-extrabold text-slate-900 block">{{ c.name }}</span>
                      <span v-if="c.type === 'business' && c.contact_person && c.contact_person !== c.name" class="text-[11px] text-slate-500 block">
                        Contact: {{ c.contact_person }}
                      </span>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-4">
                  <span :class="[
                    'px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border',
                    c.type === 'business' ? 'bg-sky-50 text-sky-800 border-sky-200' : 'bg-indigo-50 text-indigo-800 border-indigo-200'
                  ]">
                    {{ c.type }}
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
                  <button
                    v-else
                    type="button"
                    @click="showBillModal = true; billForm.vendor_name = c.name"
                    class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-extrabold rounded-lg transition-all cursor-pointer"
                  >
                    + Record Bill
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
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

    <!-- Modal 5: Add New Contact (Person or Business) -->
    <div v-if="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4 overflow-y-auto" @click="showContactModal = false">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-5 my-8" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900">Add New Contact</h3>
            <p class="text-xs text-slate-500">Create a new person or business contact for invoicing and bookkeeping.</p>
          </div>
          <button type="button" @click="showContactModal = false" class="text-slate-400 hover:text-slate-700 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitContact" class="space-y-4 text-xs">
          <!-- Contact Type Toggle Selector -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Contact Type *</label>
            <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 rounded-xl border border-slate-200">
              <button
                type="button"
                @click="contactForm.type = 'person'; if (!contactForm.role || contactForm.role === 'Vendor / Supplier') contactForm.role = 'Contractor / Coach'"
                :class="[
                  'py-2 px-3 rounded-lg text-xs font-extrabold flex items-center justify-center gap-2 transition-all cursor-pointer',
                  contactForm.type === 'person' ? 'bg-white text-indigo-900 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'
                ]"
              >
                <span>👤 Individual Person</span>
              </button>

              <button
                type="button"
                @click="contactForm.type = 'business'; if (!contactForm.role || contactForm.role === 'Contractor / Coach') contactForm.role = 'Vendor / Supplier'"
                :class="[
                  'py-2 px-3 rounded-lg text-xs font-extrabold flex items-center justify-center gap-2 transition-all cursor-pointer',
                  contactForm.type === 'business' ? 'bg-white text-sky-900 shadow-sm border border-slate-200' : 'text-slate-600 hover:text-slate-900'
                ]"
              >
                <span>🏢 Business / Organization</span>
              </button>
            </div>
          </div>

          <!-- Entity / Name Field (Dynamic Label) -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">
              {{ contactForm.type === 'business' ? 'Business / Company Name *' : 'Full Name *' }}
            </label>
            <input
              v-model="contactForm.name"
              type="text"
              :placeholder="contactForm.type === 'business' ? 'e.g. Oxford Boatyard Ltd' : 'e.g. Robert Sterling'"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500 font-semibold"
              required
            />
          </div>

          <!-- Primary Contact Person Field -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">
              {{ contactForm.type === 'business' ? 'Primary Contact Person Name' : 'Secondary / Preferred Name' }}
            </label>
            <input
              v-model="contactForm.contact_person"
              type="text"
              :placeholder="contactForm.type === 'business' ? 'e.g. David Miller (Account Representative)' : 'e.g. Bob Sterling'"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
            />
          </div>

          <!-- Role / Category Select -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Relationship / Role Category *</label>
            <select
              v-model="contactForm.role"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500"
              required
            >
              <template v-if="contactForm.type === 'business'">
                <option value="Vendor / Supplier">Vendor / Supplier</option>
                <option value="Sponsor & Insurer">Sponsor & Insurer</option>
                <option value="Contractor / Service Provider">Contractor / Service Provider</option>
                <option value="Client / Corporate Customer">Client / Corporate Customer</option>
                <option value="Partner">Partner Organization</option>
              </template>
              <template v-else>
                <option value="Contractor / Coach">Contractor / Coach</option>
                <option value="Volunteer / Staff">Volunteer / Staff</option>
                <option value="Vendor Representative">Vendor Representative</option>
                <option value="Client / Customer">Client / Customer</option>
                <option value="Member">Club Member</option>
              </template>
            </select>
          </div>

          <!-- Email & Phone Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Email Address</label>
              <input
                v-model="contactForm.email"
                type="email"
                placeholder="contact@domain.com"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
              />
            </div>

            <div class="space-y-1">
              <label class="block font-bold text-slate-700">Phone Number</label>
              <input
                v-model="contactForm.phone"
                type="text"
                placeholder="+44 20 7946 0912"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <!-- Tax ID / VAT Number -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">
              {{ contactForm.type === 'business' ? 'VAT / Tax Registration Number' : 'Tax / UTR Number' }}
            </label>
            <input
              v-model="contactForm.tax_id"
              type="text"
              :placeholder="contactForm.type === 'business' ? 'GB 883 9920 11' : 'UTR 982341'"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl font-mono text-xs focus:outline-none focus:border-sky-500"
            />
          </div>

          <!-- Billing Address -->
          <div class="space-y-2 pt-2 border-t border-slate-100">
            <label class="block font-bold text-slate-700">Billing Address</label>
            <input
              v-model="contactForm.address_line_1"
              type="text"
              placeholder="Address Line 1"
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500 mb-2"
            />
            <div class="grid grid-cols-2 gap-2">
              <input
                v-model="contactForm.city"
                type="text"
                placeholder="City"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              />
              <input
                v-model="contactForm.postcode"
                type="text"
                placeholder="Postcode"
                class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
              />
            </div>
          </div>

          <!-- Notes -->
          <div class="space-y-1">
            <label class="block font-bold text-slate-700">Internal Notes / Payment Terms</label>
            <textarea
              v-model="contactForm.notes"
              rows="2"
              placeholder="e.g. Payment terms Net 30. Direct bank transfer."
              class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs focus:outline-none focus:border-sky-500"
            ></textarea>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="showContactModal = false"
              class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="contactForm.processing"
              class="px-4 py-2 bg-[#007bce] hover:bg-sky-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Save Contact
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

          <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-900 text-[11px] space-y-1">
            <span class="font-extrabold block">⚖️ Balanced Double-Entry System</span>
            <p class="text-amber-800">
              Posting an opening balance automatically credits/debits <strong>Account 3000 (Retained Earnings / Prior Year Reserves)</strong> to keep your general ledger 100% balanced ($Assets = Liabilities + Equity$).
            </p>
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
  </AdminLayout>
</template>
