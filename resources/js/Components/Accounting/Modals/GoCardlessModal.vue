<script setup>


// A dialog of the accounting screen, in its own file so the page stays readable.
const showGoCardlessSecret = defineModel('showGoCardlessSecret', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showGoCardlessToken = defineModel('showGoCardlessToken', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    goCardlessForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitGoCardlessAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <span class="p-2 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-xl text-lg">🟡</span>
            <div>
              <h3 class="font-extrabold text-slate-900 dark:text-white text-base">Connect GoCardless Direct Debit API</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Automated UK Direct Debit mandate collection &amp; transaction feed</p>
            </div>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="submitGoCardlessAccount" class="space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Account Label / Title *</label>
            <input v-model="goCardlessForm.account_name" required placeholder="e.g. Lodge GoCardless Direct Debit Account" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Environment *</label>
            <div class="grid grid-cols-2 gap-3">
              <button
                type="button"
                @click="goCardlessForm.gocardless_environment = 'sandbox'"
                :class="['p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition-all', goCardlessForm.gocardless_environment === 'sandbox' ? 'bg-amber-50 dark:bg-amber-950/40 border-amber-400 text-amber-900 dark:text-amber-200 shadow-sm' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']"
              >
                🧪 Sandbox (testing)
              </button>
              <button
                type="button"
                @click="goCardlessForm.gocardless_environment = 'live'"
                :class="['p-2.5 rounded-xl border text-center font-bold text-xs cursor-pointer transition-all', goCardlessForm.gocardless_environment === 'live' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-400 text-emerald-900 dark:text-emerald-200 shadow-sm' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']"
              >
                ⚡ Live production
              </button>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700 dark:text-slate-200">GoCardless Access Token *</label>
              <button type="button" @click="showGoCardlessToken = !showGoCardlessToken" class="text-[10px] font-bold text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200 cursor-pointer">
                {{ showGoCardlessToken ? 'Hide' : 'Show token' }}
              </button>
            </div>
            <input :type="showGoCardlessToken ? 'text' : 'password'" v-model="goCardlessForm.gocardless_access_token" required placeholder="live_... or sandbox_..." class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-mono text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="font-bold text-slate-700 dark:text-slate-200">Webhook Secret (Optional)</label>
              <button type="button" @click="showGoCardlessSecret = !showGoCardlessSecret" class="text-[10px] font-bold text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200 cursor-pointer">
                {{ showGoCardlessSecret ? 'Hide' : 'Show secret' }}
              </button>
            </div>
            <input :type="showGoCardlessSecret ? 'text' : 'password'" v-model="goCardlessForm.gocardless_webhook_secret" placeholder="GoCardless Webhook Secret Key" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-mono text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Currency *</label>
              <input v-model="goCardlessForm.currency" readonly class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900 uppercase" />
            </div>
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Opening Balance ({{ $cs }})</label>
              <input type="number" step="0.01" v-model="goCardlessForm.opening_balance" class="w-full bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl p-2.5 font-semibold text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-900" />
            </div>
          </div>

          <div class="p-3 bg-amber-50/90 dark:bg-amber-950/90 rounded-2xl border border-amber-200 dark:border-amber-800/60 text-amber-900 dark:text-amber-200 text-[11px] space-y-1">
            <span class="font-bold block">💡 Where to find your GoCardless Access Token?</span>
            <p>Log in to your GoCardless Dashboard > <strong class="font-semibold">Developers</strong> > <strong class="font-semibold">Access Tokens</strong>, create an API token with read-write access, and paste it here.</p>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="$emit('close')" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs cursor-pointer">
              Cancel
            </button>
            <button type="submit" :disabled="goCardlessForm.processing" class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs shadow-md shadow-amber-600/20 cursor-pointer">
              Save &amp; connect GoCardless
            </button>
          </div>
        </form>
      </div>
    </div>
</template>
