<script setup>
// "Mark paid" for an invoice or bill: the day the money moved and the bank or cash account it went through, so the
// ledger entry lands in the right account and financial year. Leaving the account empty uses the lodge's main account.
import { computed } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});

const dialog = defineModel({ type: Object, required: true });
const emit = defineEmits(['submit']);

// Bank and cash accounts: assets below 1200, where receivables start (the same rule as AccountingService::moneyAccounts).
const options = computed(() => [
    { value: '', label: "The lodge's main bank account" },
    ...props.accounts
        .filter((account) => account.type === 'asset' && account.is_active && String(account.code) < '1200')
        .map((account) => ({ value: account.id, label: `${account.code} ${account.name}` })),
]);

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <Modal :open="dialog.show" :title="dialog.kind === 'bill' ? 'Mark bill paid' : 'Mark invoice paid'" size="sm" @close="dialog.show = false">
        <div class="space-y-4">
            <Input v-model="dialog.paid_on" type="date" :max="today" :label="dialog.kind === 'bill' ? 'Date paid' : 'Date received'" required :error="errors.paid_on" />
            <Select v-model.number="dialog.bank_account_id" :options="options" :label="dialog.kind === 'bill' ? 'Paid from' : 'Paid into'" :error="errors.bank_account_id" />
        </div>

        <template #footer>
            <Button variant="secondary" :disabled="dialog.processing" @click="dialog.show = false">Cancel</Button>
            <Button :loading="dialog.processing" @click="emit('submit')">Mark paid</Button>
        </template>
    </Modal>
</template>
