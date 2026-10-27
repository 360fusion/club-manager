<script setup>
import GiftAidPanel from '@/Components/Accounting/Reconciliation/GiftAidPanel.vue';
import BankStatementsPanel from '@/Components/Accounting/Reconciliation/BankStatementsPanel.vue';
import CashCodingPanel from '@/Components/Accounting/Reconciliation/CashCodingPanel.vue';
import ReconcilePanel from '@/Components/Accounting/Reconciliation/ReconcilePanel.vue';
import AccountTransactionsPanel from '@/Components/Accounting/Sections/AccountTransactionsPanel.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const transactionTypeOptions = [
    { value: 'all', label: 'All lines (spent and received)' },
    { value: 'spent', label: 'Spent only (outgoing)' },
    { value: 'received', label: 'Received only (incoming)' },
];

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
    numberFormat: { type: Function, default: undefined },
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
    <details v-if="(reconciliation?.match_rules || []).length" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm text-xs">
      <summary class="cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-200"><span class="inline-flex items-center gap-1.5 align-middle"><UiIcon name="refresh" /> Remembered matches ({{ reconciliation.match_rules.length }})</span></summary>
      <p class="mt-2 text-slate-500 dark:text-slate-400">Recurring payees are suggested first once you have matched them. Forget one if it was a mistake.</p>
      <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
        <li v-for="rule in reconciliation.match_rules" :key="rule.id" class="py-2 flex items-center justify-between gap-3">
          <span class="min-w-0">
            <span class="font-mono font-medium text-slate-900 dark:text-white block truncate">{{ rule.pattern }}</span>
            <span class="text-slate-500 dark:text-slate-400">{{ rule.match_type === 'charity_relief' ? 'Charity relief' : `Ledger ${rule.nominal_code}` }} · used {{ rule.hit_count }}×</span>
          </span>
          <Button variant="secondary" size="sm" @click="forgetMatchRule(rule.id)">Forget</Button>
        </li>
      </ul>
    </details>

    <!-- Top Bank Account Balance Header Bar -->
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="space-y-2">
        <div class="flex items-center gap-3">
          <UiIcon name="bank" :size="20" class="text-slate-500 dark:text-slate-400" />

          <!-- Bank Account Selector Dropdown -->
          <div class="relative">
            <select
              v-model="selectedBankAccountId"
              aria-label="Bank account"
              class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 text-slate-900 dark:text-white text-sm font-semibold px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
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

        <div class="flex flex-wrap items-center gap-4 text-xs">
          <div>
            <span class="font-semibold tabular-nums text-slate-900 dark:text-white text-sm">{{ activeBankAccount?.formatted_statement_balance || ($cs + '0.00') }}</span>
            <span class="text-slate-500 dark:text-slate-400 ml-1">Statement balance</span>
          </div>
          <div class="border-l border-slate-200 dark:border-slate-800 pl-4">
            <span class="font-semibold tabular-nums text-slate-900 dark:text-white text-sm">{{ activeBankAccount?.formatted_ledger_balance || ($cs + '0.00') }}</span>
            <span class="text-slate-500 dark:text-slate-400 ml-1">Balance in system</span>
            <a href="#" @click.prevent="showDifferentBalancesModal = true" class="text-blue-600 dark:text-blue-400 hover:underline ml-1 font-medium">Different balances?</a>
          </div>
        </div>
        <a href="#" @click.prevent="showWhatsThisModal = true" class="text-blue-600 dark:text-blue-400 text-xs font-medium hover:underline inline-block mt-0.5">What's this?</a>
      </div>

      <div class="flex flex-wrap items-center gap-2 self-start md:self-auto">
        <Button variant="ghost" size="sm" @click="navigateTo('reporting', 'reconciliation_summary')">
          <template #icon><UiIcon name="chart" /></template>
          Reconciliation report
        </Button>

        <div class="relative manage-account-container">
          <Button variant="secondary" size="sm" :aria-expanded="showManageAccountMenu" aria-haspopup="menu" @click.stop="showManageAccountMenu = !showManageAccountMenu">
            Manage account
            <UiIcon name="chevron-down" :size="14" />
          </Button>
          <div v-if="showManageAccountMenu" role="menu" class="absolute right-0 mt-1 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg py-1 z-30 text-sm text-slate-700 dark:text-slate-200">
            <button type="button" role="menuitem" @click="showImportModal = true; showManageAccountMenu = false" class="w-full flex items-center gap-2 text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800"><UiIcon name="upload" /> Import bank statement</button>
            <button type="button" role="menuitem" @click="reconSubTab = 'bank_statements'; showManageAccountMenu = false" class="w-full flex items-center gap-2 text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800"><UiIcon name="document" /> Bank statement history</button>
            <button type="button" role="menuitem" @click="reconSubTab = 'cash_coding'; showManageAccountMenu = false" class="w-full flex items-center gap-2 text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800"><UiIcon name="lightning" /> Bulk cash coding</button>
          </div>
        </div>
      </div>
    </div>


    <!-- Sub-Tab Navigation Bar & Compact View Toggle -->
    <div class="border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2 px-1">
      <div class="flex items-center gap-1 overflow-x-auto text-sm font-medium">
        <button
          type="button"
          @click="reconSubTab = 'reconcile'"
          :aria-current="reconSubTab === 'reconcile' ? 'page' : undefined"
          :class="[
            'px-4 py-2.5 -mb-px border-b-2 transition-colors cursor-pointer whitespace-nowrap',
            reconSubTab === 'reconcile' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
        >
          Reconcile ({{ filteredUnmatchedTx.length }})
        </button>

        <button
          type="button"
          @click="reconSubTab = 'cash_coding'"
          :aria-current="reconSubTab === 'cash_coding' ? 'page' : undefined"
          :class="[
            'px-4 py-2.5 -mb-px border-b-2 transition-colors cursor-pointer whitespace-nowrap',
            reconSubTab === 'cash_coding' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
        >
          Cash coding
        </button>

        <button
          type="button"
          @click="reconSubTab = 'bank_statements'"
          :aria-current="reconSubTab === 'bank_statements' ? 'page' : undefined"
          :class="[
            'px-4 py-2.5 -mb-px border-b-2 transition-colors cursor-pointer whitespace-nowrap',
            reconSubTab === 'bank_statements' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
        >
          Bank statements
        </button>

        <button
          type="button"
          @click="reconSubTab = 'account_transactions'"
          :aria-current="reconSubTab === 'account_transactions' ? 'page' : undefined"
          :class="[
            'px-4 py-2.5 -mb-px border-b-2 transition-colors cursor-pointer whitespace-nowrap',
            reconSubTab === 'account_transactions' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
        >
          Account transactions
        </button>

        <button
          type="button"
          @click="reconSubTab = 'gift_aid'"
          :aria-current="reconSubTab === 'gift_aid' ? 'page' : undefined"
          :class="[
            'px-4 py-2.5 -mb-px border-b-2 transition-colors cursor-pointer whitespace-nowrap flex items-center gap-1.5',
            reconSubTab === 'gift_aid' ? 'border-blue-600 text-blue-700 dark:text-blue-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100'
          ]"
        >
          <span>Gift Aid and Relief Chest</span>
          <Badge v-if="reconciliation?.gift_aid_summary?.pending_claim_count > 0" variant="warning">
            {{ reconciliation.gift_aid_summary.pending_claim_count }} to claim
          </Badge>
        </button>
      </div>
    </div>

    <!-- Search & Filter Toolbar -->
    <div class="space-y-2">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-slate-900 p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800">
        <div class="relative w-full sm:flex-1">
          <input
            v-model="reconSearch"
            type="text"
            aria-label="Search bank lines"
            placeholder="Search by payee, amount, reference, description, cheque number or analysis code"
            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 pl-9 pr-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <UiIcon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
        </div>

        <button
          type="button"
          @click="showFilterPanel = !showFilterPanel"
          :aria-pressed="showFilterPanel"
          :class="[
            'px-3 py-1.5 text-xs font-medium rounded-xl border transition-colors cursor-pointer inline-flex items-center gap-1.5 whitespace-nowrap',
            showFilterPanel ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-700/60' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'
          ]"
        >
          <UiIcon name="filter" />
          <span>Filter</span>
        </button>
      </div>

      <!-- Expandable Filter Panel -->
      <div v-if="showFilterPanel" class="bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3">
        <Select v-model="filterType" label="Transaction type" :options="transactionTypeOptions" />

        <Input v-model="filterMinAmount" :label="`Min amount (${$cs})`" type="number" step="0.01" placeholder="0.00" />

        <Input v-model="filterMaxAmount" :label="`Max amount (${$cs})`" type="number" step="0.01" placeholder="9999.00" />

        <div class="flex items-end">
          <Button variant="secondary" size="sm" block @click="filterType = 'all'; filterMinAmount = ''; filterMaxAmount = ''; reconSearch = ''">
            Reset filters
          </Button>
        </div>
      </div>
    </div>

    <!-- SUB-TAB 1: RECONCILE WORKSPACE -->
    <ReconcilePanel v-if="reconSubTab === 'reconcile'" :accounts="accounts" :active-details-tx-id="activeDetailsTxId" :active-options-tx-id="activeOptionsTxId" :close-who-dropdown-soon="closeWhoDropdownSoon" :compact-view="compactView" :delete-single-statement-line="deleteSingleStatementLine" :filtered-unmatched-tx="filteredUnmatchedTx" :get-contact-suggestions="getContactSuggestions" :get-row-state="getRowState" :number-format="numberFormat" :open-manual-match-modal-for-tx="openManualMatchModalForTx" :set-row-tab="setRowTab" :submit-reconcile="submitReconcile" :submit-row-reconcile="submitRowReconcile" :toggle-details-popover="toggleDetailsPopover" :toggle-options-dropdown="toggleOptionsDropdown" @close-details="activeDetailsTxId = null" />

    <!-- SUB-TAB 2: CASH CODING -->
    <CashCodingPanel v-else-if="reconSubTab === 'cash_coding'" :accounts="accounts" :close-who-dropdown-soon="closeWhoDropdownSoon" :contacts="contacts" :filtered-unmatched-tx="filteredUnmatchedTx" :get-contact-suggestions="getContactSuggestions" :get-row-state="getRowState" :number-format="numberFormat" v-model:selected-cash-coding-tx="selectedCashCodingTx" :submit-bulk-cash-coding="submitBulkCashCoding" :toggle-cash-coding-select-all="toggleCashCodingSelectAll" />

    <!-- SUB-TAB 3: BANK STATEMENTS (Full imported statement lines log) -->
    <BankStatementsPanel v-else-if="reconSubTab === 'bank_statements'" :deletable-statement-lines="deletableStatementLines" :delete-selected-statement-lines="deleteSelectedStatementLines" :filtered-statement-lines="filteredStatementLines" :restorable-statement-lines="restorableStatementLines" :restore-selected-statement-lines="restoreSelectedStatementLines" v-model:selected-statement-line-ids="selectedStatementLineIds" :show-import-modal="showImportModal" v-model:statement-lines-filter="statementLinesFilter" :toggle-statement-lines-select-all="toggleStatementLinesSelectAll" @open-import="showImportModal = true" />

    <!-- SUB-TAB 4: ACCOUNT TRANSACTIONS -->
    <AccountTransactionsPanel v-else-if="reconSubTab === 'account_transactions'" :bills="bills" :club="club" :filtered-account-transactions="filteredAccountTransactions" :invoices="invoices" :reconciliation="reconciliation" />

    <!-- SUB-TAB 5: GIFT AID & RELIEF CHEST RECONCILIATION -->
    <GiftAidPanel v-else-if="reconSubTab === 'gift_aid'" :club="club" :filtered-reconciled-donations="filteredReconciledDonations" :filtered-reconciled-gift-aid-total="filteredReconciledGiftAidTotal" v-model:gift-aid-filter-date-from="giftAidFilterDateFrom" v-model:gift-aid-filter-date-to="giftAidFilterDateTo" v-model:gift-aid-filter-person="giftAidFilterPerson" v-model:gift-aid-filter-search="giftAidFilterSearch" v-model:gift-aid-filter-status="giftAidFilterStatus" :reconciliation="reconciliation" :reset-gift-aid-filters="resetGiftAidFilters" />

  </div>
</template>
