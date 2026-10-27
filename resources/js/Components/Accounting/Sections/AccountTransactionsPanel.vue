<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

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
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
        <div>
          <h4 class="font-black text-slate-900 dark:text-white text-sm">Internal System Account Transactions History</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Internal bookkeeping entries created inside your books (bills, invoices, spend/receive money).</p>
        </div>
        <div class="flex items-center gap-3">
          <span v-if="selectedAccountTxIds.length === 0" class="text-xs text-slate-400">Showing all internal transactions</span>
          <span v-else class="text-xs font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded border border-blue-200 dark:border-blue-800/60">
            {{ selectedAccountTxIds.length }} transaction(s) selected
          </span>
          <button
            v-if="selectedAccountTxIds.length > 0"
            type="button"
            @click="removeAndRedoSelectedAccountTransactions"
            class="px-3.5 py-1.5 bg-[#008ba8] hover:bg-[#00768f] text-white font-extrabold text-xs rounded-lg shadow transition-all cursor-pointer flex items-center gap-1.5"
          >
            Remove &amp; Redo ({{ selectedAccountTxIds.length }})
          </button>
        </div>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="bg-slate-50 dark:bg-slate-800/50 text-[11px] font-bold text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800">
            <tr>
              <th class="py-2.5 px-3 w-8">
                <input
                  type="checkbox"
                  @change="toggleAccountTxSelectAll"
                  :checked="selectedAccountTxIds.length === filteredAccountTransactions.length && filteredAccountTransactions.length > 0"
                  class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                />
              </th>
              <th class="py-2.5 px-3">Date</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3">Description</th>
              <th class="py-2.5 px-3">Reference</th>
              <th class="py-2.5 px-3 text-right">Spent</th>
              <th class="py-2.5 px-3 text-right">Received</th>
              <th class="py-2.5 px-3 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
            <tr v-for="tx in filteredAccountTransactions" :key="tx.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80 transition-colors">
              <td class="py-2.5 px-3">
                <input
                  type="checkbox"
                  :value="tx.id"
                  v-model="selectedAccountTxIds"
                  class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500 cursor-pointer"
                />
              </td>
              <td class="py-2.5 px-3 font-mono text-[11px] text-slate-600 dark:text-slate-300 whitespace-nowrap">{{ tx.transaction_date }}</td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-300 font-medium">{{ tx.type }}</td>
              <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">{{ tx.description }}</td>
              <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">{{ tx.reference }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-900 dark:text-white">{{ tx.spent }}</td>
              <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-700 dark:text-emerald-300">{{ tx.received }}</td>
              <td class="py-2.5 px-3 text-center whitespace-nowrap">
                <span
                  :class="[
                    'px-2.5 py-0.5 text-[10px] font-black rounded-full uppercase tracking-wider',
                    tx.status === 'Reconciled' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200'
                  ]"
                >
                  {{ tx.status }}
                </span>
              </td>
            </tr>
            <tr v-if="filteredAccountTransactions.length === 0">
              <td colspan="8" class="py-8 text-center text-slate-400 italic">No internal account transactions recorded yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
