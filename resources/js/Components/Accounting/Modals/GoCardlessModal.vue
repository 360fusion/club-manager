<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
const showGoCardlessSecret = defineModel('showGoCardlessSecret', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showGoCardlessToken = defineModel('showGoCardlessToken', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    goCardlessForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitGoCardlessAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const CHOICE = 'rounded-xl border px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500';
const CHOSEN = 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-500 dark:bg-blue-950/40 dark:text-blue-300';
const NOT_CHOSEN = 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:hover:bg-slate-800';
</script>

<template>
    <Modal :open="true" title="Connect GoCardless" subtitle="Brings in UK direct debit collections automatically" size="lg" @close="$emit('close')">
      <form id="gocardless-account-form" class="space-y-4" @submit.prevent="submitGoCardlessAccount">
        <Input v-model="goCardlessForm.account_name" label="Account name" required placeholder="For example Lodge GoCardless account" />

        <div class="space-y-1.5">
          <span id="gocardless-environment-label" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Environment</span>
          <div class="grid grid-cols-2 gap-3" role="group" aria-labelledby="gocardless-environment-label">
            <button
              type="button"
              :aria-pressed="goCardlessForm.gocardless_environment === 'sandbox'"
              :class="[CHOICE, goCardlessForm.gocardless_environment === 'sandbox' ? CHOSEN : NOT_CHOSEN]"
              @click="goCardlessForm.gocardless_environment = 'sandbox'"
            >
              Sandbox (testing)
            </button>
            <button
              type="button"
              :aria-pressed="goCardlessForm.gocardless_environment === 'live'"
              :class="[CHOICE, goCardlessForm.gocardless_environment === 'live' ? CHOSEN : NOT_CHOSEN]"
              @click="goCardlessForm.gocardless_environment = 'live'"
            >
              Live
            </button>
          </div>
        </div>

        <div class="relative">
          <Input v-model="goCardlessForm.gocardless_access_token" :type="showGoCardlessToken ? 'text' : 'password'" label="GoCardless access token" required placeholder="live_... or sandbox_..." />
          <button type="button" class="absolute right-0 top-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="showGoCardlessToken = !showGoCardlessToken">
            {{ showGoCardlessToken ? 'Hide' : 'Show token' }}
          </button>
        </div>

        <div class="relative">
          <Input v-model="goCardlessForm.gocardless_webhook_secret" :type="showGoCardlessSecret ? 'text' : 'password'" label="Webhook secret (optional)" placeholder="GoCardless webhook secret" />
          <button type="button" class="absolute right-0 top-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="showGoCardlessSecret = !showGoCardlessSecret">
            {{ showGoCardlessSecret ? 'Hide' : 'Show secret' }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="goCardlessForm.currency" label="Currency" readonly />
          <Input v-model="goCardlessForm.opening_balance" type="number" step="0.01" label="Opening balance" :prefix="$cs" />
        </div>

        <Alert variant="info" title="Where to find your GoCardless access token">
          In your GoCardless dashboard go to <strong>Developers</strong> &gt; <strong>Access tokens</strong>, create a token with read-write access and paste it here.
        </Alert>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="gocardless-account-form" :loading="goCardlessForm.processing">Save and connect GoCardless</Button>
      </template>
    </Modal>
</template>
