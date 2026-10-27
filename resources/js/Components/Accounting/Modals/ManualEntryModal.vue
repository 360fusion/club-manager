<script setup>
import { useId } from 'vue';
import Modal from '@/Components/Ui/Modal.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';

// A dialog of the accounting screen, in its own file so the page stays readable.
const manualMatchType = defineModel('manualMatchType', { type: [String, Number, Boolean, Array, Object], default: undefined });
const manualNominalCode = defineModel('manualNominalCode', { type: [String, Number, Boolean, Array, Object], default: undefined });
const manualTargetId = defineModel('manualTargetId', { type: [String, Number, Boolean, Array, Object], default: undefined });
defineEmits(['close']);

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    selectedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitReconcile: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const matchTypeOptions = [
    { value: 'member_subscription', label: 'Member subscription dues invoice' },
    { value: 'supplier_bill', label: 'Audited supplier bill' },
    { value: 'charity_relief', label: 'Charity Relief Chest / Provincial contribution' },
    { value: 'ledger_account', label: 'Nominal ledger account code' },
];

const targetSelectId = useId();
const fieldClass = 'w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
const labelClass = 'block text-xs font-medium text-slate-700 dark:text-slate-300';
</script>

<template>
    <Modal :open="true" title="Manual allocation" :subtitle="`Choose where this statement line goes: ${selectedTx?.raw_description ?? ''}`" size="lg" @close="$emit('close')">
        <div class="space-y-4">
          <Select v-model="manualMatchType" label="Allocate to" :options="matchTypeOptions" />

          <div v-if="manualMatchType === 'member_subscription'" class="space-y-1.5">
            <label :for="targetSelectId" :class="labelClass">Unpaid subscription <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></label>
            <select
              :id="targetSelectId"
              v-model="manualTargetId"
              :class="fieldClass"
            >
              <option value="" disabled>Choose an unpaid subscription...</option>
              <option v-for="sub in reconciliation.unpaid_subscriptions" :key="sub.id" :value="sub.id">
                {{ sub.member_name }} (Inv: {{ sub.invoice_reference || 'N/A' }} - {{ $cs }}{{ numberFormat(sub.amount_due, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'supplier_bill'" class="space-y-1.5">
            <label :for="targetSelectId" :class="labelClass">Unpaid supplier bill <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></label>
            <select
              :id="targetSelectId"
              v-model="manualTargetId"
              :class="fieldClass"
            >
              <option value="" disabled>Choose an unpaid bill...</option>
              <option v-for="bill in reconciliation.unpaid_bills" :key="bill.id" :value="bill.id">
                Vendor: {{ bill.vendor_name }} (Bill: {{ bill.bill_number }} - {{ $cs }}{{ numberFormat(bill.amount, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'ledger_account'" class="space-y-1.5">
            <label :for="targetSelectId" :class="labelClass">Ledger account code <span class="text-rose-600 dark:text-rose-400" aria-hidden="true">*</span></label>
            <select
              :id="targetSelectId"
              v-model="manualNominalCode"
              :class="fieldClass"
            >
              <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
              </option>
            </select>
          </div>
        </div>

        <template #footer>
          <Button variant="secondary" @click="$emit('close')">Cancel</Button>
          <Button @click="submitReconcile(manualMatchType, manualTargetId || 0, manualNominalCode)">Confirm allocation and reconcile</Button>
        </template>
    </Modal>
</template>
