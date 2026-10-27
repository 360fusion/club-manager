<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openingBalanceForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitOpeningBalance: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Set Account Opening / Carry Over Balance</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Record initial carry-over funds when setting up your club accounts for the first time.</p>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <form @submit.prevent="submitOpeningBalance" class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Target Ledger Account *</label>
            <select
              v-model="openingBalanceForm.account_id"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs font-bold focus:outline-none focus:border-blue-500"
              required
            >
              <option value="" disabled>Select Account...</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Opening Amount ({{ $cs }}) *</label>
              <input
                v-model="openingBalanceForm.opening_balance"
                type="number"
                step="0.01"
                placeholder="e.g. 17903.76"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-mono text-xs focus:outline-none focus:border-blue-500"
                required
              />
            </div>
            <div class="space-y-1">
              <label class="block font-bold text-slate-700 dark:text-slate-200">Effective Date *</label>
              <input
                v-model="openingBalanceForm.as_of_date"
                type="date"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl text-xs focus:outline-none focus:border-blue-500"
                required
              />
            </div>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="openingBalanceForm.processing"
              class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Post Opening Balance
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
