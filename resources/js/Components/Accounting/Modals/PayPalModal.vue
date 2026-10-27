<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
const showPayPalSecret = defineModel('showPayPalSecret', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    payPalForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitPayPalAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl text-lg">🟦</span>
            <div>
              <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Connect PayPal Business API</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Automated transaction sync for PayPal sales &amp; payouts</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitPayPalAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Lodge Account Label / Title *</label>
            <input v-model="payPalForm.account_name" required placeholder="e.g. Lodge Operating PayPal Account" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">PayPal Client ID *</label>
            <input v-model="payPalForm.paypal_client_id" required placeholder="e.g. A21AA... or Client ID from PayPal Developer Portal" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-mono text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700 dark:text-slate-200">PayPal Client Secret *</label>
              <button type="button" @click="showPayPalSecret = !showPayPalSecret" class="text-[10px] font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 cursor-pointer">
                {{ showPayPalSecret ? 'Hide' : 'Show secret' }}
              </button>
            </div>
            <input :type="showPayPalSecret ? 'text' : 'password'" v-model="payPalForm.paypal_client_secret" required placeholder="PayPal API Secret Key" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-mono text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Environment *</label>
              <select v-model="payPalForm.paypal_environment" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900">
                <option value="live">Live Production</option>
                <option value="sandbox">Sandbox Testing</option>
              </select>
            </div>
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Currency *</label>
              <input v-model="payPalForm.currency" readonly class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 uppercase" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Opening Balance ({{ $cs }})</label>
            <input type="number" step="0.01" v-model="payPalForm.opening_balance" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div class="p-3 bg-blue-50/90 dark:bg-blue-950/90 rounded-2xl border border-blue-200 dark:border-blue-800/60 text-blue-900 dark:text-blue-200 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to get these credentials?</span>
            <p>Log in to <a href="https://developer.paypal.com" target="_blank" class="underline font-bold">developer.paypal.com</a> with your Lodge's PayPal account, create an App under <strong>Apps &amp; Credentials</strong>, and copy your Client ID &amp; Secret.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="$emit('close')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="payPalForm.processing" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md shadow-blue-600/20 cursor-pointer">
              Save &amp; connect PayPal
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
