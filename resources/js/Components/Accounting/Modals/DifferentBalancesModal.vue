<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    activeBankAccount: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredUnmatchedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <Modal :open="true" title="Why do your balances differ?" subtitle="Bank statement balance and system ledger balance" @close="$emit('close')">
        <div class="space-y-4 text-xs">
          <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
            Your <strong>statement balance</strong> comes from imported bank feeds or uploaded statement CSV files, whereas your <strong>balance in system</strong> reflects all reconciled ledger entries in your chart of accounts.
          </p>

          <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex justify-between items-center text-slate-700 dark:text-slate-200">
              <span>Statement balance (bank):</span>
              <span class="font-medium tabular-nums text-slate-900 dark:text-white">{{ activeBankAccount?.formatted_statement_balance || ($cs + '0.00') }}</span>
            </div>
            <div class="flex justify-between items-center text-amber-700 dark:text-amber-300">
              <span>Unreconciled statement items ({{ filteredUnmatchedTx.length }}):</span>
              <span class="font-medium tabular-nums">{{ numberFormat(filteredUnmatchedTx.reduce((sum, t) => sum + (parseFloat(t.amount) || 0), 0)) }}</span>
            </div>
            <div class="border-t border-slate-200 dark:border-slate-800 pt-2 flex justify-between items-center text-slate-900 dark:text-white font-semibold">
              <span>System ledger balance (code {{ activeBankAccount?.account_code || '1000' }}):</span>
              <span class="tabular-nums">{{ activeBankAccount?.formatted_ledger_balance || ($cs + '0.00') }}</span>
            </div>
          </div>

          <Alert variant="warning" title="Common causes of a difference">
            <ul class="list-disc list-inside space-y-0.5">
              <li>Statement lines imported from the bank that have not been reconciled yet.</li>
              <li>Unpaid invoices or vendor bills with pending manual cash entries.</li>
              <li>Duplicate or deleted statement entries awaiting adjustment.</li>
            </ul>
          </Alert>
        </div>

        <template #footer>
          <Button variant="secondary" @click="$emit('close')">Got it</Button>
          <Button @click="$emit('close'); navigateTo('reporting', 'reconciliation_summary')">Open reconciliation report</Button>
        </template>
    </Modal>
</template>
