<script setup>


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
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/75 backdrop-blur-sm p-4" @click="$emit('close')">
      <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-800 space-y-5" @click.stop>
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Manual Target Allocation</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Nominate custom allocation for statement line: <span class="font-bold text-slate-900 dark:text-white">{{ selectedTx?.raw_description }}</span></p>
          </div>
          <button type="button" @click="$emit('close')" class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm">✕</button>
        </div>

        <div class="space-y-4 text-xs">
          <div class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Allocation Destination Type</label>
            <select
              v-model="manualMatchType"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-bold focus:outline-none focus:border-blue-500"
            >
              <option value="member_subscription">Member Subscription Dues Invoice</option>
              <option value="supplier_bill">Audited Supplier Bill</option>
              <option value="charity_relief">Charity Relief Chest / Provincial Contribution</option>
              <option value="ledger_account">Nominal Ledger Account Code</option>
            </select>
          </div>

          <div v-if="manualMatchType === 'member_subscription'" class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Select Unpaid Subscription *</label>
            <select
              v-model="manualTargetId"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-bold focus:outline-none focus:border-blue-500"
            >
              <option value="" disabled>Choose Unpaid Subscription...</option>
              <option v-for="sub in reconciliation.unpaid_subscriptions" :key="sub.id" :value="sub.id">
                {{ sub.member_name }} (Inv: {{ sub.invoice_reference || 'N/A' }} - {{ $cs }}{{ numberFormat(sub.amount_due, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'supplier_bill'" class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Select Unpaid Supplier Bill *</label>
            <select
              v-model="manualTargetId"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-bold focus:outline-none focus:border-blue-500"
            >
              <option value="" disabled>Choose Unpaid Bill...</option>
              <option v-for="bill in reconciliation.unpaid_bills" :key="bill.id" :value="bill.id">
                Vendor: {{ bill.vendor_name }} (Bill: {{ bill.bill_number }} - {{ $cs }}{{ numberFormat(bill.amount, 2) }})
              </option>
            </select>
          </div>

          <div v-if="manualMatchType === 'ledger_account'" class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Select Ledger Account Code *</label>
            <select
              v-model="manualNominalCode"
              class="w-full px-3 py-2 border border-slate-200 dark:border-slate-800 rounded-xl font-bold focus:outline-none focus:border-blue-500"
            >
              <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
              </option>
            </select>
          </div>

          <div class="pt-2 flex items-center justify-end gap-2">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="button"
              @click="submitReconcile(manualMatchType, manualTargetId || 0, manualNominalCode)"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-sm cursor-pointer"
            >
              Confirm allocation &amp; reconcile
            </button>
          </div>
        </div>
      </div>
    </div>
</template>
