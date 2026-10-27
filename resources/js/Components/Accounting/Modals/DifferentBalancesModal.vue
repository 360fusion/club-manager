<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    activeBankAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredUnmatchedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <div
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4"
      @click="$emit('close')"
    >
      <div
        class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5"
        @click.stop
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2">
            <span class="text-xl">⚖️</span>
            <div>
              <h3 class="text-base font-black text-slate-900 dark:text-white">Why do your balances differ?</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Bank Statement Balance vs System Ledger Balance</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs">
          <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
            Your <strong>Statement Balance</strong> comes from imported bank feeds or uploaded statement CSV files, whereas your <strong>Balance in System</strong> reflects all reconciled ledger entries in your Chart of Accounts.
          </p>

          <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2 font-mono text-[11px]">
            <div class="flex justify-between items-center text-slate-700 dark:text-slate-200">
              <span>Statement Balance (Bank):</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ activeBankAccount?.formatted_statement_balance || ($cs + '0.00') }}</span>
            </div>
            <div class="flex justify-between items-center text-amber-700 dark:text-amber-300">
              <span>Unreconciled Statement Items ({{ filteredUnmatchedTx.length }}):</span>
              <span class="font-bold">{{ numberFormat(filteredUnmatchedTx.reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)) }}</span>
            </div>
            <div class="border-t border-slate-200 dark:border-slate-800 pt-2 flex justify-between items-center text-blue-900 dark:text-blue-200 font-extrabold text-xs">
              <span>System Ledger Balance (Code {{ activeBankAccount?.account_code || '1000' }}):</span>
              <span>{{ activeBankAccount?.formatted_ledger_balance || ($cs + '0.00') }}</span>
            </div>
          </div>

          <div class="p-3 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-[11px] space-y-1">
            <span class="font-bold block">💡 Common causes of balance variance:</span>
            <ul class="list-disc list-inside space-y-0.5 text-amber-800 dark:text-amber-200">
              <li>Statement lines imported from the bank that have not been reconciled yet.</li>
              <li>Unpaid invoices or vendor bills with pending manual cash entries.</li>
              <li>Duplicate or deleted statement entries awaiting adjustment.</li>
            </ul>
          </div>
        </div>

        <div class="pt-2 flex items-center justify-end gap-2">
          <button
            type="button"
            @click="$emit('close'); navigateTo('reporting', 'reconciliation_summary')"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
          >
            Open Reconciliation Report
          </button>
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer"
          >
            Got it
          </button>
        </div>
      </div>
    </div>
</template>
