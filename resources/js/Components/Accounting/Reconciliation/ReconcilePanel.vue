<script setup>


// A section of the accounting reconciliation screen, in its own file so the page stays readable.
defineEmits(['close-details']);

defineProps({
    accounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    activeDetailsTxId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    activeOptionsTxId: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    closeWhoDropdownSoon: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    compactView: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    deleteSingleStatementLine: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredUnmatchedTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getContactSuggestions: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    getRowState: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    numberFormat: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    openManualMatchModalForTx: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    setRowTab: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitReconcile: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    submitRowReconcile: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleDetailsPopover: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleOptionsDropdown: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-4">


      <!-- Statement Line Rows Stack -->
      <div class="space-y-4">
        <div
          v-for="tx in filteredUnmatchedTx"
          :key="tx.id"
          :class="[
            'bg-slate-50/50 dark:bg-slate-800/50 rounded-xl border border-slate-200/60 dark:border-slate-800/60 transition-all',
            compactView ? 'p-1.5' : 'p-2.5'
          ]"
        >
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">
            
            <!-- LEFT CARD (Statement Line) -->
            <div
              :class="[
                'lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg p-4 shadow-sm flex flex-col justify-between',
                compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
              ]"
            >
              <div>
                <div class="flex items-center justify-between text-xs relative">
                  <span class="font-semibold text-slate-400">{{ tx.transaction_date }}</span>
                  
                  <div class="relative statement-options-container">
                    <button
                      type="button"
                      @click.stop="toggleOptionsDropdown(tx.id)"
                      class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 hover:underline font-medium focus:outline-none cursor-pointer"
                    >
                      Options ▾
                    </button>
                    
                    <div
                      v-if="activeOptionsTxId === tx.id"
                      class="absolute right-0 top-5 z-30 w-48 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-xl py-1 text-xs font-semibold text-slate-700 dark:text-slate-200 animate-in fade-in duration-100"
                    >
                      <button
                        type="button"
                        @click="deleteSingleStatementLine(tx.id)"
                        class="w-full text-left px-3.5 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2 cursor-pointer transition-colors"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Delete statement line
                      </button>
                    </div>
                  </div>
                </div>
                <div class="mt-1">
                  <h4 class="font-extrabold text-slate-900 dark:text-white text-sm leading-snug truncate">{{ tx.raw_description }}</h4>
                  <span class="text-[10px] font-bold text-slate-400 block uppercase tracking-wider mt-0.5">
                    {{ tx.reference || (tx.amount > 0 ? 'FASTER PAYMENT' : 'DIRECT DEBIT') }}
                  </span>
                </div>
                <div class="relative statement-details-container inline-block mt-1">
                  <a
                    href="#"
                    @click.prevent.stop="toggleDetailsPopover(tx.id)"
                    class="text-xs text-blue-600 dark:text-blue-400 hover:underline inline-block font-medium cursor-pointer"
                  >
                    More details
                  </a>

                  <!-- Statement Details Popover -->
                  <div
                    v-if="activeDetailsTxId === tx.id"
                    class="absolute left-0 top-full mt-2.5 z-50 w-80 sm:w-96 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg shadow-2xl p-4 text-xs font-sans animate-in fade-in duration-100"
                    @click.stop
                  >
                    <!-- Top Pointer Arrow -->
                    <div class="absolute -top-1.5 left-6 w-3 h-3 bg-white dark:bg-slate-900 border-t border-l border-slate-300 dark:border-slate-700 rotate-45 z-10"></div>

                    <!-- Header -->
                    <div class="flex items-center justify-between pb-3">
                      <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">Statement Details</h3>
                      <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Esc to close</span>
                        <button
                          type="button"
                          @click="$emit('close-details')"
                          class="text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold text-sm cursor-pointer focus:outline-none"
                        >
                          ✕
                        </button>
                      </div>
                    </div>

                    <!-- Details Table -->
                    <table class="w-full border-collapse border border-slate-200 dark:border-slate-800 text-xs">
                      <tbody>
                        <tr>
                          <td class="w-1/2 border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction Date</td>
                          <td class="w-1/2 border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.transaction_date }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Payee</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.payee || '' }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Reference</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium break-all">{{ tx.reference || (tx.amount > 0 ? 'CREDIT' : 'DEBIT') }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Description</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium break-words">{{ tx.raw_description }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction Amount</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ numberFormat(Math.abs(tx.amount), 2) }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction Type</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.amount < 0 ? 'Debit' : 'Credit' }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Cheque No.</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.cheque_number || '' }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Analysis Code</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.analysis_code || '' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-end justify-between">
                <div v-if="tx.amount < 0">
                  <span class="text-[11px] font-semibold text-slate-400 block uppercase">Spent</span>
                  <span class="text-lg font-black text-slate-900 dark:text-white block">
                    {{ numberFormat(Math.abs(tx.amount), 2) }}
                  </span>
                </div>
                <div v-else>
                  <span class="text-[11px] font-semibold text-slate-400 block uppercase">Received</span>
                  <span class="text-lg font-black text-slate-900 dark:text-white block">
                    {{ numberFormat(Math.abs(tx.amount), 2) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- CENTER OK BUTTON -->
            <div class="lg:col-span-1 flex justify-center py-2">
              <button
                type="button"
                @click="submitRowReconcile(tx)"
                class="w-14 h-10 bg-blue-600 hover:bg-blue-700 text-white font-black text-xs rounded uppercase shadow-sm transition-all cursor-pointer flex items-center justify-center tracking-wide"
              >
                OK
              </button>
            </div>

            <!-- RIGHT CARD (Match / Create / Transfer / Discuss) -->
            <div
              :class="[
                'lg:col-span-6 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg p-4 shadow-sm flex flex-col justify-between',
                compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
              ]"
            >
              <div>
                <!-- Tab Header bar -->
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-2 mb-3">
                  <div class="flex items-center gap-4 text-xs font-bold">
                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Match')"
                      :class="[
                        'pb-1 cursor-pointer transition-all',
                        getRowState(tx.id, tx).tab === 'Match' ? 'text-slate-900 dark:text-white border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                      ]"
                    >
                      Match
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Create')"
                      :class="[
                        'pb-1 cursor-pointer transition-all',
                        getRowState(tx.id, tx).tab === 'Create' ? 'text-slate-900 dark:text-white border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                      ]"
                    >
                      Create
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Transfer')"
                      :class="[
                        'pb-1 cursor-pointer transition-all',
                        getRowState(tx.id, tx).tab === 'Transfer' ? 'text-slate-900 dark:text-white border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                      ]"
                    >
                      Transfer
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Discuss')"
                      :class="[
                        'pb-1 cursor-pointer transition-all',
                        getRowState(tx.id, tx).tab === 'Discuss' ? 'text-slate-900 dark:text-white border-b-2 border-blue-600' : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                      ]"
                    >
                      Discuss
                    </button>
                  </div>

                  <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">Find &amp; match</a>
                </div>

                <!-- Tab Body: CREATE -->
                <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="space-y-2 text-xs">
                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right font-semibold text-slate-500 dark:text-slate-400">Who</label>
                    <div class="col-span-10 relative who-dropdown-container">
                      <input
                        v-model="getRowState(tx.id, tx).who"
                        @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                        @blur="closeWhoDropdownSoon(tx)"
                        type="text"
                        name="payee_contact_search_no_autofill"
                        autocomplete="off"
                        aria-autocomplete="none"
                        data-lpignore="true"
                        data-form-type="other"
                        placeholder="Name of the contact..."
                        class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                      />

                      <!-- Floating Dropdown menu (appears only after typing 2+ letters) -->
                      <div
                        v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                        class="absolute left-0 top-full mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg shadow-xl z-30 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
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
                    </div>
                  </div>

                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right font-semibold text-slate-500 dark:text-slate-400">What</label>
                    <div class="col-span-10">
                      <select
                        v-model="getRowState(tx.id, tx).what"
                        @change="getRowState(tx.id, tx).showWhatError = false"
                        :class="[
                          'w-full px-2.5 py-1 bg-white dark:bg-slate-900 border rounded text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-blue-500',
                          getRowState(tx.id, tx).showWhatError ? 'border-rose-500 bg-rose-50/50 dark:bg-rose-950/50' : 'border-slate-300 dark:border-slate-700'
                        ]"
                      >
                        <option value="">Choose the account...</option>
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                          {{ acc.code }} - {{ acc.name }}
                        </option>
                      </select>
                      <p v-if="getRowState(tx.id, tx).showWhatError" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-0.5">Please select an account code (What) before reconciling.</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right font-semibold text-slate-500 dark:text-slate-400">Why</label>
                    <div class="col-span-10">
                      <input
                        v-model="getRowState(tx.id, tx).why"
                        type="text"
                        placeholder="Enter a description..."
                        class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-blue-500"
                      />
                    </div>
                  </div>
                </div>

                <!-- Tab Body: MATCH -->
                <div v-else-if="getRowState(tx.id, tx).tab === 'Match'" class="space-y-2 text-xs">
                  <div v-if="tx.suggested_matches && tx.suggested_matches.length > 0" class="space-y-2">
                    <div
                      v-for="(m, idx) in tx.suggested_matches"
                      :key="idx"
                      class="p-2.5 rounded-lg border border-emerald-300 dark:border-emerald-700/60 bg-emerald-50/60 dark:bg-emerald-950/60 flex items-center justify-between"
                    >
                      <div>
                        <span class="font-black text-slate-900 dark:text-white block text-xs">{{ m.remembered ? '🔁 ' : '' }}{{ m.target_title }}</span>
                        <span class="text-[11px] text-emerald-800 dark:text-emerald-200 font-medium">{{ m.match_reason }} — {{ $cs }}{{ numberFormat(m.target_amount, 2) }}</span>
                      </div>
                      <button
                        type="button"
                        @click="submitReconcile(m.match_type, m.target_id, m.nominal_code || 'GENERAL')"
                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] rounded transition"
                      >
                        Match
                      </button>
                    </div>
                  </div>
                  <div v-else class="p-3 text-slate-500 dark:text-slate-400 text-center italic bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded">
                    No automatic system match found. Switch to 'Create' or click 'Find &amp; Match'.
                  </div>
                </div>

                <!-- Tab Body: TRANSFER -->
                <div v-else-if="getRowState(tx.id, tx).tab === 'Transfer'" class="space-y-2 text-xs">
                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-3 text-right font-semibold text-slate-500 dark:text-slate-400">Bank Account</label>
                    <div class="col-span-9">
                      <select
                        v-model="getRowState(tx.id, tx).transfer_account"
                        class="w-full px-2.5 py-1 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded text-xs text-slate-800 dark:text-slate-100"
                      >
                        <option value="">Select destination bank account...</option>
                        <option v-for="acc in accounts.filter(a => a.type === 'asset')" :key="acc.id" :value="acc.id">
                          {{ acc.code }} - {{ acc.name }}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Tab Body: DISCUSS -->
                <div v-else-if="getRowState(tx.id, tx).tab === 'Discuss'" class="space-y-2 text-xs">
                  <textarea
                    v-model="getRowState(tx.id, tx).discuss_note"
                    placeholder="Type a note or query for your team..."
                    rows="2"
                    class="w-full p-2 border border-slate-300 dark:border-slate-700 rounded text-xs focus:ring-1 focus:ring-blue-500"
                  ></textarea>
                </div>
              </div>

              <!-- Bottom Footer Row of Right Card -->
              <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                  <select
                    v-model="getRowState(tx.id, tx).category"
                    class="px-2 py-0.5 border border-slate-300 dark:border-slate-700 rounded text-xs text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900"
                  >
                    <option value="General">General</option>
                    <option value="Members">Members</option>
                    <option value="Facilities">Facilities</option>
                  </select>

                  <select
                    v-model="getRowState(tx.id, tx).vat"
                    class="px-2 py-0.5 border border-slate-300 dark:border-slate-700 rounded text-xs text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-900"
                  >
                    <option value="No VAT">No VAT</option>
                    <option value="20% Standard">20% Standard</option>
                    <option value="Exempt">Exempt</option>
                  </select>
                </div>

                <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="text-blue-600 dark:text-blue-400 font-semibold hover:underline">Add details</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="filteredUnmatchedTx.length === 0" class="p-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl text-center text-slate-400 text-xs italic">
          All bank statement lines reconciled! No unmatched lines pending.
        </div>
      </div>
    </div>
</template>
