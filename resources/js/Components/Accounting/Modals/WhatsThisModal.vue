<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <Modal :open="true" title="Understanding bank balances" subtitle="Statement balance and balance in the system" size="lg" @close="$emit('close')">
      <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
        <Alert title="Statement balance">
          The real-world opening and closing balance of your bank account, imported automatically through the bank connection or from an uploaded statement file (CSV or OFX).
        </Alert>

        <Alert title="Balance in the system">
          The double-entry ledger balance worked out from every approved invoice, bill and matched bank entry recorded in your chart of accounts.
        </Alert>

        <p>
          Bank reconciliation checks that every penny entering or leaving your bank account is accounted for and assigned to the right member subscription, invoice or expense category.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" @click="$emit('close')">Close</Button>
      </template>
    </Modal>
</template>
