<script setup>
// The Chart of Accounts tab of the accounting screen: the nominal ledger with each account's balance and its opening-balance shortcut.
defineProps({
    accounts: { type: Array, default: () => [] },
    getTypeBadge: { type: Function, required: true },
    openOpeningBalanceModal: { type: Function, required: true }
});

const showAccountModal = defineModel('showAccountModal', { type: Boolean, default: false });
</script>

<template>
  <div class="space-y-6">
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
            <span>⚖️ Set opening balances</span>
          </button>
          <button
            type="button"
            @click="showAccountModal = true"
            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition-all cursor-pointer shadow-sm"
          >
            + Add account
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
                    class="px-2.5 py-1 text-[10px] font-extrabold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all cursor-pointer"
                    title="Set opening / carry-over balance for this account"
                  >
                    ⚖️ Carry over
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
