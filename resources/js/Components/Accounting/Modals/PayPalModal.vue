<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
const showPayPalSecret = defineModel('showPayPalSecret', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    payPalForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitPayPalAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const ENVIRONMENTS = [
    { value: 'live', label: 'Live' },
    { value: 'sandbox', label: 'Sandbox (testing)' },
];
</script>

<template>
    <Modal :open="true" title="Connect PayPal Business" subtitle="Brings in PayPal sales and payouts automatically" size="lg" @close="$emit('close')">
      <form id="paypal-account-form" class="space-y-4" @submit.prevent="submitPayPalAccount">
        <Input v-model="payPalForm.account_name" label="Account name" required placeholder="For example Lodge PayPal account" />

        <Input v-model="payPalForm.paypal_client_id" label="PayPal client ID" required placeholder="The client ID from the PayPal developer site" />

        <div class="relative">
          <Input v-model="payPalForm.paypal_client_secret" :type="showPayPalSecret ? 'text' : 'password'" label="PayPal client secret" required placeholder="PayPal client secret" />
          <button type="button" class="absolute right-0 top-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="showPayPalSecret = !showPayPalSecret">
            {{ showPayPalSecret ? 'Hide' : 'Show secret' }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Select v-model="payPalForm.paypal_environment" label="Environment" :options="ENVIRONMENTS" />
          <Input v-model="payPalForm.currency" label="Currency" readonly />
        </div>

        <Input v-model="payPalForm.opening_balance" type="number" step="0.01" label="Opening balance" :prefix="$cs" />

        <Alert variant="info" title="Where to get these details">
          Sign in to <a href="https://developer.paypal.com" target="_blank" class="font-medium underline">developer.paypal.com</a> with your lodge's PayPal account, create an app under <strong>Apps &amp; Credentials</strong>, and copy its client ID and secret.
        </Alert>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="paypal-account-form" :loading="payPalForm.processing">Save and connect PayPal</Button>
      </template>
    </Modal>
</template>
