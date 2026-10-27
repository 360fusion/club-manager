<script setup>
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';

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
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div>
          <h4 class="font-semibold text-slate-900 dark:text-white text-base">Bulk cash coding</h4>
          <p class="text-xs text-slate-500 dark:text-slate-400">Quickly assign payee contacts, nominal account codes and VAT rates across several statement lines at once.</p>
        </div>
        <Button
          size="sm"
          @click="submitBulkCashCoding"
          :disabled="selectedCashCodingTx.length === 0"
        >
          Save and reconcile selected ({{ selectedCashCodingTx.length }})
        </Button>
      </div>

      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
            <tr>
              <th scope="col" class="py-2.5 px-3">
                <input type="checkbox" aria-label="Select all statement lines" @change="toggleCashCodingSelectAll" :checked="selectedCashCodingTx.length === filteredUnmatchedTx.length && filteredUnmatchedTx.length > 0" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900" />
              </th>
              <th scope="col" class="py-2.5 px-3 font-medium">Date</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Payee (who)</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Description (why)</th>
              <th scope="col" class="py-2.5 px-3 font-medium">Ledger code (what)</th>
              <th scope="col" class="py-2.5 px-3 font-medium">VAT rate</th>
              <th scope="col" class="py-2.5 px-3 font-medium text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="tx in filteredUnmatchedTx" :key="tx.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
              <td class="py-2 px-3">
                <input type="checkbox" :value="tx.id" v-model="selectedCashCodingTx" :aria-label="`Select line dated ${tx.transaction_date}`" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-900" />
              </td>
              <td class="py-2 px-3 text-slate-500 dark:text-slate-400 tabular-nums whitespace-nowrap">{{ tx.transaction_date }}</td>
              <td class="py-2 px-3 relative who-dropdown-container">
                <input
                  v-model="getRowState(tx.id, tx).who"
                  @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                  @blur="closeWhoDropdownSoon(tx)"
                  type="text"
                  name="cash_coding_contact_no_autofill"
                  autocomplete="off"
                  aria-autocomplete="none"
                  aria-label="Payee"
                  data-lpignore="true"
                  data-form-type="other"
                  placeholder="Contact..."
                  class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                />

                <!-- Floating Dropdown menu -->
                <div
                  v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                  class="absolute left-0 top-full mt-1 w-56 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg z-30 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
                >
                  <button
                    v-for="c in getContactSuggestions(getRowState(tx.id, tx).who)"
                    :key="c.id"
                    type="button"
                    @mousedown.prevent="getRowState(tx.id, tx).who = c.name; getRowState(tx.id, tx).showWhoDropdown = false"
                    class="w-full text-left px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-between text-xs transition-colors cursor-pointer"
                  >
                    <div>
                      <span class="font-medium text-slate-900 dark:text-white block text-xs">{{ c.name }}</span>
                      <span class="text-[10px] text-slate-500 dark:text-slate-400 block">{{ c.role || c.kind }}</span>
                    </div>
                    <Badge variant="info">Select</Badge>
                  </button>
                </div>
              </td>
              <td class="py-2 px-3">
                <input v-model="getRowState(tx.id, tx).why" type="text" aria-label="Description" placeholder="Description..." class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
              </td>
              <td class="py-2 px-3">
                <select
                  v-model="getRowState(tx.id, tx).what"
                  @change="getRowState(tx.id, tx).showWhatError = false"
                  aria-label="Ledger code"
                  :aria-invalid="getRowState(tx.id, tx).showWhatError ? 'true' : undefined"
                  :class="[
                    'w-full rounded-xl border bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                    getRowState(tx.id, tx).showWhatError ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700'
                  ]"
                >
                  <option value="">Select account...</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.code">{{ acc.code }} - {{ acc.name }}</option>
                </select>
              </td>
              <td class="py-2 px-3">
                <select v-model="getRowState(tx.id, tx).vat" aria-label="VAT rate" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                  <option value="No VAT">No VAT</option>
                  <option value="20% Standard">20% standard</option>
                  <option value="Exempt">Exempt</option>
                </select>
              </td>
              <td :class="['py-2 px-3 text-right tabular-nums font-medium whitespace-nowrap', tx.amount > 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-900 dark:text-white']">
                {{ $cs }}{{ numberFormat(Math.abs(tx.amount), 2) }}
              </td>
            </tr>
            <tr v-if="filteredUnmatchedTx.length === 0">
              <td colspan="7" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No statement lines available for cash coding.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</template>
