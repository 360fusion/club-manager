<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    deleteSingleStatementLine: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    selectedStatementLineDetails: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
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
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Bank Statement Line Details</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Full metadata imported from bank statement feed.</p>
          </div>
          <button
            type="button"
            @click="$emit('close')"
            class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer"
          >
            ✕
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800">
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-0.5">Transaction Date</span>
              <span class="font-bold text-slate-900 dark:text-white text-sm">{{ selectedStatementLineDetails.transaction_date }}</span>
            </div>
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-0.5">Amount</span>
              <span :class="['font-black text-base', selectedStatementLineDetails.amount < 0 ? 'text-slate-900 dark:text-white' : 'text-emerald-700 dark:text-emerald-300']">
                {{ selectedStatementLineDetails.amount < 0 ? 'Spent ' + $cs : 'Received ' + $cs }}{{ numberFormat(Math.abs(selectedStatementLineDetails.amount), 2) }}
              </span>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Raw Bank Description / Payee</span>
            <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 font-extrabold text-slate-900 dark:text-white text-sm leading-snug">
              {{ selectedStatementLineDetails.raw_description }}
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Reference</span>
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 font-mono font-bold text-slate-800 dark:text-slate-100">
                {{ selectedStatementLineDetails.reference || (selectedStatementLineDetails.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
              </div>
            </div>
            <div class="space-y-1">
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block">Running Balance</span>
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 font-mono font-bold text-slate-800 dark:text-slate-100">
                {{ selectedStatementLineDetails.balance_after ? $cs + numberFormat(selectedStatementLineDetails.balance_after, 2) : 'N/A' }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-1">
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">Status</span>
              <span
                :class="[
                  'px-2.5 py-1 text-[10px] font-black rounded-full uppercase tracking-wider inline-block',
                  selectedStatementLineDetails.status === 'Reconciled' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' :
                  selectedStatementLineDetails.status === 'Deleted' ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-800 dark:text-rose-200' :
                  'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200'
                ]"
              >
                {{ selectedStatementLineDetails.status || 'Unmatched' }}
              </span>
            </div>
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 block mb-1">System Record ID</span>
              <span class="font-mono text-slate-500 dark:text-slate-400 font-bold text-xs">#{{ selectedStatementLineDetails.id }}</span>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <button
            type="button"
            @click="deleteSingleStatementLine(selectedStatementLineDetails.id); $emit('close');"
            class="px-3.5 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-bold text-xs rounded-xl transition-all cursor-pointer flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete Statement Line
          </button>
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>
</template>
