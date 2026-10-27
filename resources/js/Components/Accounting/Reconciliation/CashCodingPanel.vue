<script setup>


// A section of the accounting reconciliation screen, in its own file so the page stays readable.
const selectedCashCodingTx = defineModel('selectedCashCodingTx', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeWhoDropdownSoon: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    contacts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredUnmatchedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getContactSuggestions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getRowState: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitBulkCashCoding: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleCashCodingSelectAll: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 shadow-sm flex items-center justify-between gap-4">
        <div>
          <h4 class="font-black text-slate-900 dark:text-white text-sm">Fast Bulk Cash Coding Spreadsheet</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Quickly assign payee contacts, nominal account codes, and VAT rates across multiple statement lines simultaneously.</p>
        </div>
        <button
          type="button"
          @click="submitBulkCashCoding"
          :disabled="selectedCashCodingTx.length === 0"
          class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-extrabold text-xs rounded-xl shadow transition cursor-pointer"
        >
          Save &amp; Reconcile Selected ({{ selectedCashCodingTx.length }})
        </button>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-100 dark:bg-slate-800 text-[11px] font-black uppercase tracking-wider text-slate-600 dark:text-slate-300 border-b border-slate-200 dark:border-slate-800">
            <tr>
              <th class="py-2.5 px-3">
                <input type="checkbox" @change="toggleCashCodingSelectAll" :checked="selectedCashCodingTx.length === filteredUnmatchedTx.length && filteredUnmatchedTx.length > 0" class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              </th>
              <th class="py-2.5 px-3">Date</th>
              <th class="py-2.5 px-3">Payee (Who)</th>
              <th class="py-2.5 px-3">Description (Why)</th>
              <th class="py-2.5 px-3">Ledger Code (What)</th>
              <th class="py-2.5 px-3">VAT Rate</th>
              <th class="py-2.5 px-3 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-medium">
            <tr v-for="tx in filteredUnmatchedTx" :key="tx.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/80">
              <td class="py-2 px-3">
                <input type="checkbox" :value="tx.id" v-model="selectedCashCodingTx" class="rounded text-blue-600 dark:text-blue-400 focus:ring-blue-500" />
              </td>
              <td class="py-2 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">{{ tx.transaction_date }}</td>
              <td class="py-2 px-3 relative who-dropdown-container">
                <input
                  v-model="getRowState(tx.id, tx).who"
                  @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                  @blur="closeWhoDropdownSoon(tx)"
                  type="text"
                  name="cash_coding_contact_no_autofill"
                  autocomplete="off"
                  aria-autocomplete="none"
                  data-lpignore="true"
                  data-form-type="other"
                  placeholder="Contact..."
                  class="w-full px-2 py-1 border border-slate-300 dark:border-slate-700 rounded text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                />

                <!-- Floating Dropdown menu -->
                <div
                  v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                  class="absolute left-0 top-full mt-1 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xl z-30 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                >
                  <button
                    v-for="c in getContactSuggestions(getRowState(tx.id, tx).who)"
                    :key="c.id"
                    type="button"
                    @mousedown.prevent="getRowState(tx.id, tx).who = c.name; getRowState(tx.id, tx).showWhoDropdown = false"
                    class="w-full text-left px-3 py-1.5 hover:bg-blue-50 dark:hover:bg-blue-950/40 flex items-center justify-between text-xs transition-colors cursor-pointer"
                  >
                    <div>
                      <span class="font-bold text-slate-900 dark:text-white block text-xs">{{ c.name }}</span>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ c.role || c.kind }}</span>
                    </div>
                    <span class="text-[10px] text-blue-700 dark:text-blue-300 font-mono font-bold bg-blue-50 dark:bg-blue-950/40 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-900/40">Select</span>
                  </button>
                </div>
              </td>
              <td class="py-2 px-3">
                <input v-model="getRowState(tx.id, tx).why" type="text" placeholder="Description..." class="w-full px-2 py-1 border border-slate-300 dark:border-slate-700 rounded text-xs" />
              </td>
              <td class="py-2 px-3">
                <select
                  v-model="getRowState(tx.id, tx).what"
                  @change="getRowState(tx.id, tx).showWhatError = false"
                  :class="[
                    'w-full px-2 py-1 border rounded text-xs',
                    getRowState(tx.id, tx).showWhatError ? 'border-red-500 bg-red-50/50 dark:bg-red-950/50' : 'border-slate-300 dark:border-slate-700'
                  ]"
                >
                  <option value="">Select Account...</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.code">{{ acc.code }} - {{ acc.name }}</option>
                </select>
              </td>
              <td class="py-2 px-3">
                <select v-model="getRowState(tx.id, tx).vat" class="w-full px-2 py-1 border border-slate-300 dark:border-slate-700 rounded text-xs">
                  <option value="No VAT">No VAT</option>
                  <option value="20% Standard">20% Standard</option>
                  <option value="Exempt">Exempt</option>
                </select>
              </td>
              <td :class="['py-2 px-3 text-right font-mono font-bold whitespace-nowrap', tx.amount > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-900 dark:text-white']">
                {{ $cs }}{{ numberFormat(Math.abs(tx.amount), 2) }}
              </td>
            </tr>
            <tr v-if="filteredUnmatchedTx.length === 0">
              <td colspan="7" class="py-8 text-center text-slate-400 italic">No statement lines available for cash coding.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
