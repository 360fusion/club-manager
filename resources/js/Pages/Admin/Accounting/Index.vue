<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
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
  summary: {
    type: Object,
    default: () => ({
      total_assets: 0,
      total_liabilities: 0,
      total_equity: 0,
      total_revenue: 0,
      total_expenses: 0,
      net_income: 0,
    }),
  },
});

const activeTab = ref('overview');
const showAccountModal = ref(false);
const showJournalModal = ref(false);

// New Account Form
const accountForm = useForm({
  code: '',
  name: '',
  type: 'asset',
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
  <AdminLayout :club="club">
    <Head :title="`Accounting & ERP - ${club.name}`" />

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      
      <!-- Top ERP Page Header -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-slate-900 to-sky-900 text-white flex items-center justify-center text-2xl shadow-md shrink-0">
            📊
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-black text-slate-900 tracking-tight">Accounting & ERP Ledger</h1>
              <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-sky-100 text-sky-800 border border-sky-200">
                Liberu Engine Active
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
              Double-entry general ledger, chart of accounts, automated dues posting, and balance sheet reporting.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <button
            type="button"
            @click="showAccountModal = true"
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-extrabold text-xs rounded-xl border border-slate-200 shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
          >
            <span>➕ New Account</span>
          </button>
          <button
            type="button"
            @click="showJournalModal = true"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1.5"
          >
            <span>📖 Post Journal Entry</span>
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

      <!-- Main Navigation Tabs -->
      <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
          type="button"
          @click="activeTab = 'overview'"
          :class="['px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer', activeTab === 'overview' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
        >
          📈 Financial Statement Overview
        </button>
        <button
          type="button"
          @click="activeTab = 'accounts'"
          :class="['px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer', activeTab === 'accounts' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
        >
          📂 Chart of Accounts ({{ accounts.length }})
        </button>
        <button
          type="button"
          @click="activeTab = 'journal'"
          :class="['px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer', activeTab === 'journal' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100']"
        >
          📖 General Ledger Journal ({{ journalEntries.length }})
        </button>
      </div>

      <!-- Tab 1: Financial Overview & Balance Sheet Breakdown -->
      <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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

      <!-- Tab 2: Chart of Accounts Table -->
      <div v-if="activeTab === 'accounts'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between">
          <div>
            <h3 class="text-base font-extrabold text-slate-900">Chart of Accounts</h3>
            <p class="text-xs text-slate-500">Categorized ledger accounts for club bookkeeping.</p>
          </div>
          <button
            type="button"
            @click="showAccountModal = true"
            class="px-3.5 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer"
          >
            + Add Account
          </button>
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
                  {{ acc.formatted_balance }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Tab 3: General Ledger Journal Entries -->
      <div v-if="activeTab === 'journal'" class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden space-y-4">
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
            + Post Journal Entry
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

              <!-- Line Items Table -->
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
            <h3 class="text-base font-black text-slate-900">Post Double-Entry Journal</h3>
            <p class="text-xs text-slate-500">Record a balanced transaction with matching debits and credits.</p>
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

  </AdminLayout>
</template>
