<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    gatewaySyncForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    gatewaySyncType: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitGatewaySyncModal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <span v-if="gatewaySyncType === 'stripe'" class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl text-lg">⚡</span>
            <span v-else-if="gatewaySyncType === 'sumup'" class="p-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl text-lg">⚡</span>
            <span v-else class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl text-lg">⚡</span>
            <div>
              <h3 class="font-extrabold text-slate-900 dark:text-white text-base">
                Sync {{ gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal') }} API Feed
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Select custom date range to pull settled transactions</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitGatewaySyncModal" class="space-y-4 text-xs">
          <div class="p-3 bg-blue-50/90 dark:bg-blue-950/90 rounded-2xl border border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200 text-[11px] space-y-1">
            <span class="font-bold block">🛡️ Smart Duplicate Protection Active</span>
            <p>Transactions already imported into your ledger will be automatically skipped. Overlapping date ranges are 100% safe.</p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">From Date (Start) *</label>
              <input type="date" v-model="gatewaySyncForm.start_date" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">To Date (End) *</label>
              <input type="date" v-model="gatewaySyncForm.end_date" required class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="$emit('close')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button
              type="submit"
              :disabled="gatewaySyncForm.processing"
              :class="[
                'px-5 py-2.5 rounded-xl font-bold text-xs shadow-md cursor-pointer flex items-center gap-1.5 text-white',
                gatewaySyncType === 'stripe' ? 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/20' : (gatewaySyncType === 'sumup' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20' : 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/20')
              ]"
            >
              <span>⚡ Pull {{ gatewaySyncType === 'stripe' ? 'Stripe' : (gatewaySyncType === 'sumup' ? 'SumUp' : 'PayPal') }} Transactions</span>
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
