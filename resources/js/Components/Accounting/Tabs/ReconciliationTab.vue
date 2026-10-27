<script setup>
import GiftAidPanel from '@/Components/Accounting/Reconciliation/GiftAidPanel.vue';
import BankStatementsPanel from '@/Components/Accounting/Reconciliation/BankStatementsPanel.vue';
import CashCodingPanel from '@/Components/Accounting/Reconciliation/CashCodingPanel.vue';
import ReconcilePanel from '@/Components/Accounting/Reconciliation/ReconcilePanel.vue';
import AccountTransactionsPanel from '@/Components/Accounting/Sections/AccountTransactionsPanel.vue';

// The bank reconciliation workspace of the accounting screen: balance header, sub-tabs, filters and the five panels. It reads the page's figures
// as props and hands back the state it changes (the open sub-tab, filters, menus and selections) through v-model.
defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    activeBankAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    activeOptionsTxId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bankAccounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeWhoDropdownSoon: { type: Function, default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    compactView: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    contacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deletableStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteSelectedStatementLines: { type: Function, default: undefined },
    deleteSingleStatementLine: { type: Function, default: undefined },
    filteredAccountTransactions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredReconciledDonations: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredReconciledGiftAidTotal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredUnmatchedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    forgetMatchRule: { type: Function, default: undefined },
    getContactSuggestions: { type: Function, default: undefined },
    getRowState: { type: Function, default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: Function, default: undefined },
    number_format: { type: Function, default: undefined },
    openManualMatchModalForTx: { type: Function, default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resetGiftAidFilters: { type: Function, default: undefined },
    restorableStatementLines: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    restoreSelectedStatementLines: { type: Function, default: undefined },
    setRowTab: { type: Function, default: undefined },
    submitBulkCashCoding: { type: Function, default: undefined },
    submitReconcile: { type: Function, default: undefined },
    submitRowReconcile: { type: Function, default: undefined },
    summary: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleCashCodingSelectAll: { type: Function, default: undefined },
    toggleDetailsPopover: { type: Function, default: undefined },
    toggleOptionsDropdown: { type: Function, default: undefined },
    toggleStatementLinesSelectAll: { type: Function, default: undefined }
});

const activeDetailsTxId = defineModel('activeDetailsTxId', { type: [String, Number, Boolean, Array, Object], default: undefined });
const filterMaxAmount = defineModel('filterMaxAmount', { type: [String, Number, Boolean, Array, Object], default: undefined });
const filterMinAmount = defineModel('filterMinAmount', { type: [String, Number, Boolean, Array, Object], default: undefined });
const filterType = defineModel('filterType', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterDateFrom = defineModel('giftAidFilterDateFrom', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterDateTo = defineModel('giftAidFilterDateTo', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterPerson = defineModel('giftAidFilterPerson', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterSearch = defineModel('giftAidFilterSearch', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterStatus = defineModel('giftAidFilterStatus', { type: [String, Number, Boolean, Array, Object], default: undefined });
const reconSearch = defineModel('reconSearch', { type: [String, Number, Boolean, Array, Object], default: undefined });
const reconSubTab = defineModel('reconSubTab', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedBankAccountId = defineModel('selectedBankAccountId', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedCashCodingTx = defineModel('selectedCashCodingTx', { type: [String, Number, Boolean, Array, Object], default: undefined });
const selectedStatementLineIds = defineModel('selectedStatementLineIds', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showDifferentBalancesModal = defineModel('showDifferentBalancesModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showFilterPanel = defineModel('showFilterPanel', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showImportModal = defineModel('showImportModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showManageAccountMenu = defineModel('showManageAccountMenu', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showWhatsThisModal = defineModel('showWhatsThisModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const statementLinesFilter = defineModel('statementLinesFilter', { type: [String, Number, Boolean, Array, Object], default: undefined });
</script>

<template>
  <div class="space-y-4">
    
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
</template>
