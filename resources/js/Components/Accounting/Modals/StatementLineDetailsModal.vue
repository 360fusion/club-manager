<script setup>
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
defineEmits(['close']);

defineProps({
    deleteSingleStatementLine: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    selectedStatementLineDetails: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
});
</script>

<template>
    <Modal :open="true" title="Bank statement line details" subtitle="Full metadata imported from the bank statement feed." @close="$emit('close')">
        <div class="space-y-3 text-xs">
          <div class="grid grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/50 p-3.5 rounded-xl border border-slate-200 dark:border-slate-800">
            <div>
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-0.5">Transaction date</span>
              <span class="font-medium text-slate-900 dark:text-white text-sm">{{ selectedStatementLineDetails.transaction_date }}</span>
            </div>
            <div>
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-0.5">Amount</span>
              <span :class="['font-semibold tabular-nums text-base', selectedStatementLineDetails.amount < 0 ? 'text-slate-900 dark:text-white' : 'text-emerald-700 dark:text-emerald-300']">
                {{ selectedStatementLineDetails.amount < 0 ? 'Spent ' + $cs : 'Received ' + $cs }}{{ numberFormat(Math.abs(selectedStatementLineDetails.amount), 2) }}
              </span>
            </div>
          </div>

          <div class="space-y-1">
            <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Raw bank description / payee</span>
            <div class="bg-white dark:bg-slate-900 p-3 rounded-xl border border-slate-200 dark:border-slate-800 font-semibold text-slate-900 dark:text-white text-sm leading-snug">
              {{ selectedStatementLineDetails.raw_description }}
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Reference</span>
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 font-mono font-medium text-slate-800 dark:text-slate-100">
                {{ selectedStatementLineDetails.reference || (selectedStatementLineDetails.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
              </div>
            </div>
            <div class="space-y-1">
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Running balance</span>
              <div class="bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 tabular-nums font-medium text-slate-800 dark:text-slate-100">
                {{ selectedStatementLineDetails.balance_after ? $cs + numberFormat(selectedStatementLineDetails.balance_after, 2) : 'N/A' }}
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-1">
            <div>
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">Status</span>
              <Badge :variant="selectedStatementLineDetails.status === 'Reconciled' ? 'success' : selectedStatementLineDetails.status === 'Deleted' ? 'danger' : 'warning'">
                {{ selectedStatementLineDetails.status || 'Unmatched' }}
              </Badge>
            </div>
            <div>
              <span class="text-[10px] font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400 block mb-1">System record ID</span>
              <span class="font-mono text-slate-500 dark:text-slate-400 font-medium text-xs">#{{ selectedStatementLineDetails.id }}</span>
            </div>
          </div>
        </div>

        <template #footer>
          <div class="flex w-full items-center justify-between gap-2">
            <Button variant="danger" @click="deleteSingleStatementLine(selectedStatementLineDetails.id); $emit('close');">
              <template #icon><UiIcon name="trash" :size="14" /></template>
              Delete statement line
            </Button>
            <Button variant="secondary" @click="$emit('close')">Close</Button>
          </div>
        </template>
    </Modal>
</template>
