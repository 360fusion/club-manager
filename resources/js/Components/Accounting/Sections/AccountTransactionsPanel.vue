<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';

// The account_transactions section of Index.vue, in its own file so the page stays readable. State only this section uses lives here.
const props = defineProps({
    bills: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredAccountTransactions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    invoices: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const selectedAccountTxIds = ref([]);

const toggleAccountTxSelectAll = () => {
  const allTx = props.reconciliation?.account_transactions || [];
  if (selectedAccountTxIds.value.length === allTx.length) {
    selectedAccountTxIds.value = [];
  } else {
    selectedAccountTxIds.value = allTx.map(t => t.id);
  }
};

const removeAndRedoSelectedAccountTransactions = () => {
  if (selectedAccountTxIds.value.length === 0) return;
  router.post(route('admin.accounting.account_transactions.remove_redo', props.club.slug), {
    transaction_ids: selectedAccountTxIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedAccountTxIds.value = [];
    }
  });
};
</script>

<template>
    <div class="space-y-4">
      <Card padding="sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Internal account transactions</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400">Entries created inside your books (bills, invoices, spend and receive money).</p>
          </div>
          <div class="flex items-center gap-3">
            <span v-if="selectedAccountTxIds.length === 0" class="text-xs text-slate-500 dark:text-slate-400">Showing all internal transactions</span>
            <Badge v-else variant="info">{{ selectedAccountTxIds.length }} transaction(s) selected</Badge>
            <Button
              v-if="selectedAccountTxIds.length > 0"
              size="sm"
              @click="removeAndRedoSelectedAccountTransactions"
            >
              Remove and redo ({{ selectedAccountTxIds.length }})
            </Button>
          </div>
        </div>
      </Card>

      <Card padding="none" class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="w-8 p-3">
                <input
                  type="checkbox"
                  aria-label="Select all"
                  @change="toggleAccountTxSelectAll"
                  :checked="selectedAccountTxIds.length === filteredAccountTransactions.length && filteredAccountTransactions.length > 0"
                  class="cursor-pointer rounded border-slate-300 text-blue-600 accent-blue-600 focus:ring-blue-500 dark:border-slate-700"
                />
              </th>
              <th scope="col" class="p-3 font-medium">Date</th>
              <th scope="col" class="p-3 font-medium">Type</th>
              <th scope="col" class="p-3 font-medium">Description</th>
              <th scope="col" class="p-3 font-medium">Reference</th>
              <th scope="col" class="p-3 text-right font-medium">Spent</th>
              <th scope="col" class="p-3 text-right font-medium">Received</th>
              <th scope="col" class="p-3 text-center font-medium">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
            <tr v-for="tx in filteredAccountTransactions" :key="tx.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
              <td class="p-3">
                <input
                  type="checkbox"
                  :value="tx.id"
                  v-model="selectedAccountTxIds"
                  :aria-label="`Select ${tx.description}`"
                  class="cursor-pointer rounded border-slate-300 text-blue-600 accent-blue-600 focus:ring-blue-500 dark:border-slate-700"
                />
              </td>
              <td class="whitespace-nowrap p-3 tabular-nums text-slate-600 dark:text-slate-300">{{ tx.transaction_date }}</td>
              <td class="p-3 text-slate-600 dark:text-slate-300">{{ tx.type }}</td>
              <td class="p-3 font-medium text-slate-900 dark:text-white">{{ tx.description }}</td>
              <td class="p-3 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ tx.reference }}</td>
              <td class="p-3 text-right font-medium tabular-nums text-slate-900 dark:text-white">{{ tx.spent }}</td>
              <td class="p-3 text-right font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{{ tx.received }}</td>
              <td class="whitespace-nowrap p-3 text-center">
                <Badge :variant="tx.status === 'Reconciled' ? 'success' : 'warning'">{{ tx.status }}</Badge>
              </td>
            </tr>
            <tr v-if="filteredAccountTransactions.length === 0">
              <td colspan="8" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No internal account transactions recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </Card>
    </div>
</template>
