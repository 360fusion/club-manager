<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
const showStripeSecret = defineModel('showStripeSecret', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    stripeForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitStripeAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <Modal :open="true" title="Connect Stripe" subtitle="Brings in balance transactions and payouts automatically" size="lg" @close="$emit('close')">
      <form id="stripe-account-form" class="space-y-4" @submit.prevent="submitStripeAccount">
        <Input v-model="stripeForm.account_name" label="Account name" required placeholder="For example Lodge Stripe account" />

        <div class="relative">
          <Input v-model="stripeForm.stripe_secret_key" :type="showStripeSecret ? 'text' : 'password'" label="Stripe secret key" required placeholder="sk_live_... or sk_test_..." />
          <button type="button" class="absolute right-0 top-0 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300" @click="showStripeSecret = !showStripeSecret">
            {{ showStripeSecret ? 'Hide' : 'Show key' }}
          </button>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="stripeForm.currency" label="Currency" readonly />
          <Input v-model="stripeForm.opening_balance" type="number" step="0.01" label="Opening balance" :prefix="$cs" />
        </div>

        <Alert variant="info" title="Where to find your Stripe secret key">
          Sign in to <a href="https://dashboard.stripe.com/apikeys" target="_blank" class="font-medium underline">dashboard.stripe.com/apikeys</a>, choose <strong>Reveal secret key</strong> and paste it here.
        </Alert>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="stripe-account-form" :loading="stripeForm.processing">Save and connect Stripe</Button>
      </template>
    </Modal>
</template>
