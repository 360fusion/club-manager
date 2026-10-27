<script setup>

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    applySuggestedTypeAndSubmit: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bankAccountForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    detectedMismatch: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    proceedWithCurrentTypeAnyway: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    showAccountTypeWarning: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitBankAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl text-lg">🏦</span>
            <div>
              <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Add Bank / Payment Account</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Create High Street Bank or Manual Account</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitBankAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Institution / Provider Name *</label>
            <input v-model="bankAccountForm.bank_name" required placeholder="e.g. Barclays, HSBC, Lloyds, NatWest" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Account Label / Title *</label>
            <input v-model="bankAccountForm.account_name" required placeholder="e.g. Main Operating Account, Online Card Payouts" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Account Type *</label>
              <select v-model="bankAccountForm.account_type" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900">
                <option value="current">High Street Current Account</option>
                <option value="savings">Savings Account</option>
                <option value="credit_card">Credit Card Account</option>
                <option value="cash">Petty Cash / Cash Register</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Currency *</label>
              <input v-model="bankAccountForm.currency" readonly class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 uppercase" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Account Number</label>
              <input v-model="bankAccountForm.account_number" placeholder="e.g. 12345678" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Sort Code</label>
              <input v-model="bankAccountForm.sort_code" placeholder="e.g. 20-00-00" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Opening Balance ({{ $cs }})</label>
            <input type="number" step="0.01" v-model="bankAccountForm.opening_balance" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <!-- Account Type Mismatch Warning Banner -->
          <div v-if="showAccountTypeWarning && detectedMismatch" class="p-3.5 bg-amber-50 dark:bg-amber-950/40 rounded-2xl border border-amber-300 dark:border-amber-700/60 space-y-2.5 animate-in fade-in duration-150">
            <div class="flex items-start gap-2">
              <span class="text-lg leading-none">⚠️</span>
              <div>
                <h4 class="font-extrabold text-amber-900 dark:text-amber-200 text-xs">Recommended Account Type Notice</h4>
                <p class="text-[11px] text-amber-800 dark:text-amber-200 mt-0.5">
                  You entered <strong>"{{ bankAccountForm.bank_name }}"</strong>, but selected <strong>"{{ bankAccountForm.account_type }}"</strong>.
                </p>
                <p class="text-[10px] text-amber-700 dark:text-amber-300 mt-1 font-medium">{{ detectedMismatch.reason }}</p>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row items-center gap-2 pt-1 border-t border-amber-200/80 dark:border-amber-800/80">
              <button
                type="button"
                @click="applySuggestedTypeAndSubmit"
                class="w-full sm:w-auto px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-sm cursor-pointer transition-all flex items-center justify-center gap-1.5"
              >
                <span>✨ Switch to {{ detectedMismatch.suggestedLabel }}</span>
              </button>

              <button
                type="button"
                @click="proceedWithCurrentTypeAnyway"
                class="w-full sm:w-auto px-3 py-2 bg-amber-100 dark:bg-amber-900/40 hover:bg-amber-200 dark:hover:bg-amber-900/60 text-amber-900 dark:text-amber-200 font-bold text-[11px] rounded-xl cursor-pointer transition-all text-center"
              >
                Keep Selection &amp; Save
              </button>
            </div>
          </div>

          <div class="p-3 bg-blue-50 dark:bg-blue-950/40 rounded-2xl border border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200 text-[11px]">
            ℹ️ Saving will automatically create and link a dedicated nominal asset account on the Chart of Accounts (e.g. Code 1010, 1020, etc.).
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="$emit('close')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="bankAccountForm.processing" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 cursor-pointer">
              Save &amp; Link Account
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
