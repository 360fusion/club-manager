<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    accountForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <h3 class="text-base font-black text-slate-900 dark:text-white">Add Account to Ledger</h3>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitAccount" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Account Code (Numeric)</label>
            <input
              v-model="accountForm.code"
              type="text"
              placeholder="e.g. 1300"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-mono text-xs focus:outline-none focus:border-blue-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Account Title / Name</label>
            <input
              v-model="accountForm.name"
              type="text"
              placeholder="e.g. Equipment Reserve"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:outline-none focus:border-blue-500"
              required
            />
          </div>

          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Account Category Type</label>
            <select
              v-model="accountForm.type"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold focus:outline-none focus:border-blue-500"
            >
              <option value="asset">Asset (Normal Debit)</option>
              <option value="liability">Liability (Normal Credit)</option>
              <option value="equity">Equity (Normal Credit)</option>
              <option value="revenue">Revenue / Income (Normal Credit)</option>
              <option value="expense">Expense (Normal Debit)</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Opening Balance ({{ $cs }})</label>
              <input
                v-model="accountForm.opening_balance"
                type="number"
                step="0.01"
                placeholder="0.00"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-mono text-xs focus:outline-none focus:border-blue-500"
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">As of Date</label>
              <input
                v-model="accountForm.as_of_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
          <p class="text-[11px] text-slate-400 italic">Optional initial carry over. Balanced against Retained Earnings (3000).</p>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="accountForm.processing"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm"
            >
              Save Account
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
