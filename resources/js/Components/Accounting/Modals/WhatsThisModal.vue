<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
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
            <span class="text-xl">ℹ️</span>
            <div>
              <h3 class="text-base font-black text-slate-900 dark:text-white">Understanding Bank Balances</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Statement Balance vs Balance in System</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
          <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-2xl border border-blue-200 dark:border-blue-800/60 space-y-1">
            <span class="font-extrabold text-blue-900 dark:text-blue-200 block text-xs">🏦 Statement Balance</span>
            <p class="text-blue-800 dark:text-blue-200 text-[11px]">
              The real-world opening/closing balance of your bank account, imported automatically via API or uploaded statement files (CSV/OFX).
            </p>
          </div>

          <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-2xl border border-blue-200 dark:border-blue-800/60 space-y-1">
            <span class="font-extrabold text-blue-900 dark:text-blue-200 block text-xs">📖 Balance in System</span>
            <p class="text-blue-800 dark:text-blue-200 text-[11px]">
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
            @click="$emit('close')"
            class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold rounded-xl text-xs cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>
</template>
