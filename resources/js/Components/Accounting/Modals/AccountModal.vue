<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    accountForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const accountTypeOptions = [
    { value: 'asset', label: 'Asset (normal debit)' },
    { value: 'liability', label: 'Liability (normal credit)' },
    { value: 'equity', label: 'Equity (normal credit)' },
    { value: 'revenue', label: 'Revenue or income (normal credit)' },
    { value: 'expense', label: 'Expense (normal debit)' },
];
</script>

<template>
    <Modal :open="true" title="Add an account to the ledger" @close="$emit('close')">
      <form id="account-modal-form" @submit.prevent="submitAccount" class="space-y-4">
        <Input v-model="accountForm.code" label="Account code (numeric)" placeholder="e.g. 1300" required />

        <Input v-model="accountForm.name" label="Account name" placeholder="e.g. Equipment reserve" required />

        <Select v-model="accountForm.type" label="Account type" :options="accountTypeOptions" />

        <div class="grid grid-cols-2 gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
          <Input v-model="accountForm.opening_balance" type="number" step="0.01" :label="`Opening balance (${$cs})`" placeholder="0.00" />
          <Input v-model="accountForm.as_of_date" type="date" label="As of date" />
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">Optional carry-over from before. It is balanced against Retained Earnings (3000).</p>
      </form>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Cancel</Button>
        <Button type="submit" form="account-modal-form" :disabled="accountForm.processing">Save account</Button>
      </template>
    </Modal>
</template>
