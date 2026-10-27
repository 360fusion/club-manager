<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Alert from '@/Components/Ui/Alert.vue';

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

const ACCOUNT_TYPES = [
    { value: 'current', label: 'High street current account' },
    { value: 'savings', label: 'Savings account' },
    { value: 'credit_card', label: 'Credit card account' },
    { value: 'cash', label: 'Petty cash or cash box' },
];
</script>

<template>
    <Modal :open="true" title="Add a bank or payment account" subtitle="A high street bank account or one you keep by hand" size="lg" @close="$emit('close')">
      <form id="bank-account-form" class="space-y-4" @submit.prevent="submitBankAccount">
        <Input v-model="bankAccountForm.bank_name" label="Bank or provider name" required placeholder="For example Barclays, HSBC, Lloyds, NatWest" />

        <Input v-model="bankAccountForm.account_name" label="Account name" required placeholder="For example Main account, Card payouts" />

        <div class="grid grid-cols-2 gap-3">
          <Select v-model="bankAccountForm.account_type" label="Account type" :options="ACCOUNT_TYPES" />
          <Input v-model="bankAccountForm.currency" label="Currency" readonly />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <Input v-model="bankAccountForm.account_number" label="Account number" placeholder="For example 12345678" />
          <Input v-model="bankAccountForm.sort_code" label="Sort code" placeholder="For example 20-00-00" />
        </div>

        <Input v-model="bankAccountForm.opening_balance" type="number" step="0.01" label="Opening balance" :prefix="$cs" />

        <Alert v-if="showAccountTypeWarning && detectedMismatch" variant="warning" title="Check the account type">
          <p>
            You entered <strong>"{{ bankAccountForm.bank_name }}"</strong>, but chose <strong>"{{ bankAccountForm.account_type }}"</strong>.
          </p>
          <p class="mt-1">{{ detectedMismatch.reason }}</p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <Button variant="secondary" size="sm" @click="applySuggestedTypeAndSubmit">Switch to {{ detectedMismatch.suggestedLabel }}</Button>
            <Button variant="ghost" size="sm" @click="proceedWithCurrentTypeAnyway">Keep my choice and save</Button>
          </div>
        </Alert>

        <Alert variant="info">
          Saving also creates a nominal asset account for it in the chart of accounts (for example code 1010, 1020).
        </Alert>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="bank-account-form" :loading="bankAccountForm.processing">Save and link account</Button>
      </template>
    </Modal>
</template>
