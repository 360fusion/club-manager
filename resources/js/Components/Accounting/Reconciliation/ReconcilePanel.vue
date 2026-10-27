<script setup>
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
            'bg-slate-50/50 dark:bg-slate-800/50 rounded-2xl border border-slate-200/60 dark:border-slate-800/60 transition-all',
            compactView ? 'p-1.5' : 'p-2.5'
          ]"
        >
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-center">

            <!-- LEFT CARD (Statement Line) -->
            <div
              :class="[
                'lg:col-span-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between',
                compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
              ]"
            >
              <div>
                <div class="flex items-center justify-between text-xs relative">
                  <span class="font-medium text-slate-500 dark:text-slate-400">{{ tx.transaction_date }}</span>

                  <div class="relative statement-options-container">
                    <button
                      type="button"
                      @click.stop="toggleOptionsDropdown(tx.id)"
                      aria-haspopup="menu"
                      :aria-expanded="activeOptionsTxId === tx.id"
                      class="inline-flex items-center gap-1 rounded-xl px-1.5 py-0.5 text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 hover:underline font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 cursor-pointer"
                    >
                      Options
                      <UiIcon name="chevron-down" :size="14" />
                    </button>

                    <div
                      v-if="activeOptionsTxId === tx.id"
                      role="menu"
                      class="absolute right-0 top-5 z-30 w-52 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg py-1 text-sm text-slate-700 dark:text-slate-200 animate-in fade-in duration-100"
                    >
                      <button
                        type="button"
                        role="menuitem"
                        @click="deleteSingleStatementLine(tx.id)"
                        class="w-full text-left px-3.5 py-2 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 flex items-center gap-2 cursor-pointer transition-colors"
                      >
                        <UiIcon name="trash" :size="14" />
                        Delete statement line
                      </button>
                    </div>
                  </div>
                </div>
                <div class="mt-1">
                  <h4 class="font-semibold text-slate-900 dark:text-white text-sm leading-snug truncate">{{ tx.raw_description }}</h4>
                  <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400 block uppercase tracking-wider mt-0.5">
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
                    class="absolute left-0 top-full mt-2.5 z-50 w-80 sm:w-96 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl p-4 text-xs font-sans animate-in fade-in duration-100"
                    @click.stop
                  >
                    <!-- Top Pointer Arrow -->
                    <div class="absolute -top-1.5 left-6 w-3 h-3 bg-white dark:bg-slate-900 border-t border-l border-slate-200 dark:border-slate-700 rotate-45 z-10"></div>

                    <!-- Header -->
                    <div class="flex items-center justify-between pb-3">
                      <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Statement details</h3>
                      <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
                        <span>Esc to close</span>
                        <button
                          type="button"
                          @click="$emit('close-details')"
                          aria-label="Close"
                          class="rounded-xl p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
                        >
                          <UiIcon name="x" :size="14" />
                        </button>
                      </div>
                    </div>

                    <!-- Details Table -->
                    <table class="w-full border-collapse border border-slate-200 dark:border-slate-800 text-xs">
                      <tbody>
                        <tr>
                          <td class="w-1/2 border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction date</td>
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
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction amount</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium tabular-nums">{{ numberFormat(Math.abs(tx.amount), 2) }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Transaction type</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.amount < 0 ? 'Debit' : 'Credit' }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Cheque no.</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.cheque_number || '' }}</td>
                        </tr>
                        <tr>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-right text-slate-600 dark:text-slate-300 font-normal">Analysis code</td>
                          <td class="border border-slate-200 dark:border-slate-800 p-2 text-left text-slate-900 dark:text-white font-medium">{{ tx.analysis_code || '' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-end justify-between">
                <div v-if="tx.amount < 0">
                  <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 block uppercase tracking-wider">Spent</span>
                  <span class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white block">
                    {{ numberFormat(Math.abs(tx.amount), 2) }}
                  </span>
                </div>
                <div v-else>
                  <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 block uppercase tracking-wider">Received</span>
                  <span class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white block">
                    {{ numberFormat(Math.abs(tx.amount), 2) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- CENTER OK BUTTON -->
            <div class="lg:col-span-1 flex justify-center py-2">
              <Button @click="submitRowReconcile(tx)">OK</Button>
            </div>

            <!-- RIGHT CARD (Match / Create / Transfer / Discuss) -->
            <div
              :class="[
                'lg:col-span-6 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 shadow-sm flex flex-col justify-between',
                compactView ? 'min-h-[110px] space-y-1 p-3' : 'min-h-[160px] space-y-3'
              ]"
            >
              <div>
                <!-- Tab Header bar -->
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 mb-3">
                  <div class="flex items-center gap-4 text-xs font-medium">
                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Match')"
                      :aria-pressed="getRowState(tx.id, tx).tab === 'Match'"
                      :class="[
                        'pb-2 -mb-px border-b-2 cursor-pointer transition-colors',
                        getRowState(tx.id, tx).tab === 'Match' ? 'text-blue-700 dark:text-blue-300 border-blue-600' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                      ]"
                    >
                      Match
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Create')"
                      :aria-pressed="getRowState(tx.id, tx).tab === 'Create'"
                      :class="[
                        'pb-2 -mb-px border-b-2 cursor-pointer transition-colors',
                        getRowState(tx.id, tx).tab === 'Create' ? 'text-blue-700 dark:text-blue-300 border-blue-600' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                      ]"
                    >
                      Create
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Transfer')"
                      :aria-pressed="getRowState(tx.id, tx).tab === 'Transfer'"
                      :class="[
                        'pb-2 -mb-px border-b-2 cursor-pointer transition-colors',
                        getRowState(tx.id, tx).tab === 'Transfer' ? 'text-blue-700 dark:text-blue-300 border-blue-600' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                      ]"
                    >
                      Transfer
                    </button>

                    <button
                      type="button"
                      @click="setRowTab(tx.id, 'Discuss')"
                      :aria-pressed="getRowState(tx.id, tx).tab === 'Discuss'"
                      :class="[
                        'pb-2 -mb-px border-b-2 cursor-pointer transition-colors',
                        getRowState(tx.id, tx).tab === 'Discuss' ? 'text-blue-700 dark:text-blue-300 border-blue-600' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                      ]"
                    >
                      Discuss
                    </button>
                  </div>

                  <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="pb-2 inline-flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline"><UiIcon name="search" :size="14" /> Find and match</a>
                </div>

                <!-- Tab Body: CREATE -->
                <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="space-y-2 text-xs">
                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right text-sm font-medium text-slate-600 dark:text-slate-400">Who</label>
                    <div class="col-span-10 relative who-dropdown-container">
                      <input
                        v-model="getRowState(tx.id, tx).who"
                        @focus="getRowState(tx.id, tx).showWhoDropdown = true"
                        @blur="closeWhoDropdownSoon(tx)"
                        type="text"
                        name="payee_contact_search_no_autofill"
                        autocomplete="off"
                        aria-autocomplete="none"
                        aria-label="Who"
                        data-lpignore="true"
                        data-form-type="other"
                        placeholder="Name of the contact..."
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-1.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      />

                      <!-- Floating Dropdown menu (appears only after typing 2+ letters) -->
                      <div
                        v-if="getRowState(tx.id, tx).showWhoDropdown && getContactSuggestions(getRowState(tx.id, tx).who).length > 0"
                        class="absolute left-0 top-full mt-1 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg z-30 max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800"
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
                    </div>
                  </div>

                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right text-sm font-medium text-slate-600 dark:text-slate-400">What</label>
                    <div class="col-span-10">
                      <select
                        v-model="getRowState(tx.id, tx).what"
                        @change="getRowState(tx.id, tx).showWhatError = false"
                        aria-label="What"
                        :aria-invalid="getRowState(tx.id, tx).showWhatError ? 'true' : undefined"
                        :class="[
                          'w-full rounded-xl border bg-white dark:bg-slate-950 px-3 py-1.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                          getRowState(tx.id, tx).showWhatError ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700'
                        ]"
                      >
                        <option value="">Choose the account...</option>
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.code">
                          {{ acc.code }} - {{ acc.name }}
                        </option>
                      </select>
                      <p v-if="getRowState(tx.id, tx).showWhatError" class="text-xs text-rose-600 dark:text-rose-400 mt-1">Please select an account code (What) before reconciling.</p>
                    </div>
                  </div>

                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-2 text-right text-sm font-medium text-slate-600 dark:text-slate-400">Why</label>
                    <div class="col-span-10">
                      <input
                        v-model="getRowState(tx.id, tx).why"
                        type="text"
                        aria-label="Why"
                        placeholder="Enter a description..."
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-1.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                      class="p-2.5 rounded-xl border border-emerald-200 dark:border-emerald-800/50 bg-emerald-50/60 dark:bg-emerald-950/40 flex items-center justify-between gap-3"
                    >
                      <div>
                        <span class="font-semibold text-slate-900 dark:text-white flex items-center gap-1.5 text-xs">
                          <UiIcon v-if="m.remembered" name="refresh" :size="14" class="text-slate-500 dark:text-slate-400" />
                          <span>{{ m.target_title }}</span>
                          <Badge v-if="m.remembered" variant="neutral">Remembered</Badge>
                        </span>
                        <span class="text-[11px] text-emerald-800 dark:text-emerald-200 font-medium">{{ m.match_reason }} — {{ $cs }}{{ numberFormat(m.target_amount, 2) }}</span>
                      </div>
                      <Button variant="secondary" size="sm" @click="submitReconcile(m.match_type, m.target_id, m.nominal_code || 'GENERAL')">
                        Match
                      </Button>
                    </div>
                  </div>
                  <div v-else class="p-3 text-slate-500 dark:text-slate-400 text-center bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl">
                    No automatic match found. Switch to 'Create' or use 'Find and match'.
                  </div>
                </div>

                <!-- Tab Body: TRANSFER -->
                <div v-else-if="getRowState(tx.id, tx).tab === 'Transfer'" class="space-y-2 text-xs">
                  <div class="grid grid-cols-12 items-center gap-2">
                    <label class="col-span-3 text-right text-sm font-medium text-slate-600 dark:text-slate-400">Bank account</label>
                    <div class="col-span-9">
                      <select
                        v-model="getRowState(tx.id, tx).transfer_account"
                        aria-label="Bank account"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-1.5 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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
                    aria-label="Note for your team"
                    placeholder="Type a note or query for your team..."
                    rows="2"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  ></textarea>
                </div>
              </div>

              <!-- Bottom Footer Row of Right Card -->
              <div v-if="getRowState(tx.id, tx).tab === 'Create'" class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                  <select
                    v-model="getRowState(tx.id, tx).category"
                    aria-label="Category"
                    class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="General">General</option>
                    <option value="Members">Members</option>
                    <option value="Facilities">Facilities</option>
                  </select>

                  <select
                    v-model="getRowState(tx.id, tx).vat"
                    aria-label="VAT"
                    class="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-2.5 py-1 text-xs text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="No VAT">No VAT</option>
                    <option value="20% Standard">20% standard</option>
                    <option value="Exempt">Exempt</option>
                  </select>
                </div>

                <a href="#" @click.prevent="openManualMatchModalForTx(tx)" class="text-blue-600 dark:text-blue-400 font-medium hover:underline">Add details</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="filteredUnmatchedTx.length === 0" class="p-12 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl text-center text-slate-500 dark:text-slate-400 text-sm">
          <UiIcon name="check" :size="20" class="mx-auto mb-2 block text-emerald-600 dark:text-emerald-400" />
          All bank statement lines are reconciled. Nothing is waiting to be matched.
        </div>
      </div>
    </div>
</template>
