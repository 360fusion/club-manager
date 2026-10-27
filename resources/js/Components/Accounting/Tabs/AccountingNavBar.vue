<script setup>
import { Link } from '@inertiajs/vue3';
// The top bar of the accounting screen: the tabs, the Accounting menu, the + Add menu and the settings link. The page owns which tab is open
// and which menus are showing; this component only reads them and reports clicks.
defineProps({
    activeTab: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: Function, default: undefined },
    openOpeningBalanceModal: { type: Function, default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleAccountingDropdown: { type: Function, default: undefined }
});

const showAccountingDropdown = defineModel('showAccountingDropdown', { type: Boolean, default: false });
const showAddMenu = defineModel('showAddMenu', { type: Boolean, default: false });
</script>

<template>
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
            <span>Bank & payment accounts</span>
          </button>

          <button
            type="button"
            @click="showAccountingDropdown = false; navigateTo('chart-of-accounts')"
            class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
          >
            <span>📋</span>
            <span>Chart of accounts</span>
          </button>

          <button
            type="button"
            @click="showAccountingDropdown = false; navigateTo('accounting')"
            class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 border-t border-slate-800 mt-1 pt-2 cursor-pointer transition-colors"
          >
            <span>📖</span>
            <span>General ledger & journals</span>
          </button>

          <button
            v-if="canManageBilling"
            type="button"
            @click="showAccountingDropdown = false; openOpeningBalanceModal()"
            class="w-full text-left px-4 py-2.5 hover:bg-slate-800 dark:hover:bg-slate-600 hover:text-white flex items-center gap-2 cursor-pointer transition-colors"
          >
            <span>⚖️</span>
            <span>Set opening balances</span>
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
            <span>Create invoice</span>
          </Link>
          <Link
            :href="route('admin.accounting.bills.create', club.slug)"
            @click="showAddMenu = false"
            class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white"
          >
            <span>📄</span>
            <span>Add bill</span>
          </Link>
          <Link
            :href="route('admin.accounting.journal.create', club.slug)"
            @click="showAddMenu = false"
            class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors text-slate-700 dark:text-slate-200 hover:text-slate-900 dark:hover:text-white"
          >
            <span>📖</span>
            <span>Post journal</span>
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
</template>
