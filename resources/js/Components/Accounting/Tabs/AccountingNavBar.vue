<script setup>
import { Link } from '@inertiajs/vue3';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
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

const tabBase = 'px-4 py-2.5 transition-colors cursor-pointer flex items-center gap-2 whitespace-nowrap rounded-xl font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400';
const tabActive = 'text-white bg-blue-600 shadow-sm';
const tabIdle = 'text-slate-300 hover:text-white hover:bg-slate-800 dark:hover:bg-slate-700';
const menuItem = 'w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white flex items-center gap-2.5 cursor-pointer transition-colors';
</script>

<template>
  <div class="bg-slate-900 dark:bg-slate-900 border border-slate-800 rounded-2xl shadow-sm p-1.5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 relative z-30">
    <nav class="flex items-center px-1 text-sm overflow-visible flex-wrap gap-1">
      <button type="button" @click="navigateTo('home')" :class="[tabBase, activeTab === 'home' ? tabActive : tabIdle]">
        Home
      </button>

      <button type="button" @click="navigateTo('sales')" :class="[tabBase, activeTab === 'sales' ? tabActive : tabIdle]">
        Sales
      </button>

      <button type="button" @click="navigateTo('purchases')" :class="[tabBase, activeTab === 'purchases' ? tabActive : tabIdle]">
        Purchases
      </button>

      <button type="button" @click="navigateTo('reporting')" :class="[tabBase, activeTab === 'reporting' ? tabActive : tabIdle]">
        Reporting
      </button>

      <!-- Accounting dropdown menu -->
      <div class="relative accounting-dropdown-container">
        <button
          type="button"
          @click="toggleAccountingDropdown"
          :class="[tabBase, ['accounting', 'bank-accounts', 'chart-of-accounts'].includes(activeTab) ? tabActive : tabIdle]"
          aria-haspopup="menu"
          :aria-expanded="showAccountingDropdown"
        >
          Accounting
          <span class="transition-transform duration-200" :class="{ 'rotate-180': showAccountingDropdown }"><UiIcon name="chevron-down" /></span>
        </button>

        <div
          v-if="showAccountingDropdown"
          role="menu"
          class="absolute left-0 top-full mt-2 w-60 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg py-1 z-50"
        >
          <button type="button" role="menuitem" @click="showAccountingDropdown = false; navigateTo('accounting')" :class="menuItem">
            <UiIcon name="bank" class="text-slate-400" />
            Bank and payment accounts
          </button>

          <button type="button" role="menuitem" @click="showAccountingDropdown = false; navigateTo('chart-of-accounts')" :class="menuItem">
            <UiIcon name="clipboard" class="text-slate-400" />
            Chart of accounts
          </button>

          <button type="button" role="menuitem" @click="showAccountingDropdown = false; navigateTo('accounting')" :class="[menuItem, 'border-t border-slate-200 dark:border-slate-800 mt-1 pt-2']">
            <UiIcon name="document" class="text-slate-400" />
            General ledger and journals
          </button>

          <button
            v-if="canManageBilling"
            type="button"
            role="menuitem"
            @click="showAccountingDropdown = false; openOpeningBalanceModal()"
            :class="menuItem"
          >
            <UiIcon name="cash" class="text-slate-400" />
            Set opening balances
          </button>
        </div>
      </div>

      <button type="button" @click="navigateTo('contacts')" :class="[tabBase, activeTab === 'contacts' ? tabActive : tabIdle]">
        Contacts
      </button>

      <button
        v-if="canManageBilling"
        type="button"
        @click="navigateTo('reconciliation')"
        :class="[tabBase, activeTab === 'reconciliation' ? tabActive : tabIdle]"
      >
        Reconciliation
        <Badge v-if="reconciliation?.unmatched_transactions?.length > 0" variant="warning">
          {{ reconciliation.unmatched_transactions.length }} to match
        </Badge>
      </button>

    </nav>

    <!-- Quick action menu and settings link -->
    <div class="flex items-center gap-2 px-2 py-1 shrink-0">
      <!-- + Add menu -->
      <div v-if="canManageBilling" class="relative add-menu-container">
        <Button size="sm" aria-haspopup="menu" :aria-expanded="showAddMenu" @click.stop="showAddMenu = !showAddMenu">
          <template #icon><UiIcon name="plus" /></template>
          Add
          <UiIcon name="chevron-down" />
        </Button>

        <div
          v-if="showAddMenu"
          role="menu"
          class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg py-1 z-50"
        >
          <Link
            :href="route('admin.accounting.invoices.create', club.slug)"
            role="menuitem"
            @click="showAddMenu = false"
            :class="menuItem"
          >
            <UiIcon name="receipt" class="text-slate-400" />
            Create invoice
          </Link>
          <Link
            :href="route('admin.accounting.bills.create', club.slug)"
            role="menuitem"
            @click="showAddMenu = false"
            :class="menuItem"
          >
            <UiIcon name="document" class="text-slate-400" />
            Add bill
          </Link>
          <Link
            :href="route('admin.accounting.journal.create', club.slug)"
            role="menuitem"
            @click="showAddMenu = false"
            :class="menuItem"
          >
            <UiIcon name="pencil" class="text-slate-400" />
            Post journal
          </Link>
        </div>
      </div>

      <!-- Settings link (far right) -->
      <Link
        :href="route('admin.settings.show', club.slug) + '#accounting'"
        class="px-3 py-2 text-slate-300 hover:text-white hover:bg-slate-800 dark:hover:bg-slate-700 font-medium text-sm rounded-xl transition-colors cursor-pointer flex items-center gap-1.5 whitespace-nowrap"
      >
        <UiIcon name="settings" />
        Settings
      </Link>
    </div>
  </div>
</template>
