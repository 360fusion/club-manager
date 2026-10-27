<script setup>
import { Link } from '@inertiajs/vue3';
import { formatMoney } from '@/Utils/currency';

// A tab of the accounting screen, in its own file so the page stays readable.
const showConnectBankDropdown = defineModel('showConnectBankDropdown', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showBankAccountModal = defineModel('showBankAccountModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showGoCardlessModal = defineModel('showGoCardlessModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showStripeModal = defineModel('showStripeModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showSumUpModal = defineModel('showSumUpModal', { type: [String, Number, Boolean, Array, Object], default: undefined });
const showPayPalModal = defineModel('showPayPalModal', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    bankAccounts: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    canManageBilling: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    journalEntries: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    navigateTo: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    syncGoCardlessNow: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    syncPayPalNow: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    syncStripeNow: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    syncSumUpNow: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    toggleBankAccountActive: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="space-y-6">
      <!-- Bank & Payment Accounts Dashboard Cards -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
          <div>
            <div class="flex items-center gap-2.5">
              <span class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-xl text-lg">🏦</span>
              <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">Bank &amp; Payment Accounts Hub</h3>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">Multi-Bank &amp; Payment Gateway Management (High Street Banks, Stripe, PayPal, SumUp)</p>
          </div>
          <div v-if="canManageBilling" class="relative connect-bank-dropdown-container">
            <button
              type="button"
              @click.stop="showConnectBankDropdown = !showConnectBankDropdown"
              class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md transition-all cursor-pointer font-extrabold text-xs flex items-center gap-2"
            >
              <span>💳 Connect bank / API</span>
              <span class="text-[10px]">▾</span>
            </button>

            <div
              v-if="showConnectBankDropdown"
              class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl py-2 z-40 text-xs font-semibold text-slate-800 dark:text-slate-100 space-y-1 divide-y divide-slate-100 dark:divide-slate-800"
            >
              <div class="px-3.5 py-1.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                Bank Account &amp; Gateways
              </div>

              <div class="py-1">
                <button
                  type="button"
                  @click="showBankAccountModal = true; showConnectBankDropdown = false"
                  class="w-full text-left px-4 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/50 flex items-center gap-2.5 transition-colors cursor-pointer"
                >
                  <span class="text-base">🏦</span>
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">High Street Bank Account</span>
                    <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">Current, Savings or Cash Account</span>
                  </div>
                </button>

                <button
                  type="button"
                  @click="showGoCardlessModal = true; showConnectBankDropdown = false"
                  class="w-full text-left px-4 py-2 hover:bg-amber-50/70 dark:hover:bg-amber-950/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                >
                  <span class="text-base">🟡</span>
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">GoCardless Direct Debit API</span>
                    <span class="text-[10px] text-amber-700 dark:text-amber-300 font-medium">Direct Debit Mandates &amp; Feed</span>
                  </div>
                </button>

                <button
                  type="button"
                  @click="showStripeModal = true; showConnectBankDropdown = false"
                  class="w-full text-left px-4 py-2 hover:bg-blue-50/70 dark:hover:bg-blue-950/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                >
                  <span class="text-base">💜</span>
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">Stripe API</span>
                    <span class="text-[10px] text-blue-700 dark:text-blue-300 font-medium">Card Payments &amp; Payout Feed</span>
                  </div>
                </button>

                <button
                  type="button"
                  @click="showSumUpModal = true; showConnectBankDropdown = false"
                  class="w-full text-left px-4 py-2 hover:bg-emerald-50/70 dark:hover:bg-emerald-950/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                >
                  <span class="text-base">💚</span>
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">SumUp Card Reader API</span>
                    <span class="text-[10px] text-emerald-700 dark:text-emerald-300 font-medium">Card Terminal Sales Feed</span>
                  </div>
                </button>

                <button
                  type="button"
                  @click="showPayPalModal = true; showConnectBankDropdown = false"
                  class="w-full text-left px-4 py-2 hover:bg-blue-50/70 dark:hover:bg-blue-950/70 flex items-center gap-2.5 transition-colors cursor-pointer"
                >
                  <span class="text-base">🟦</span>
                  <div>
                    <span class="font-bold text-slate-900 dark:text-white block">PayPal Merchant API</span>
                    <span class="text-[10px] text-blue-700 dark:text-blue-300 font-medium">PayPal Checkout Feed</span>
                  </div>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div
            v-for="acc in (bankAccounts || [])"
            :key="acc.id"
            class="bg-slate-50/70 dark:bg-slate-800/70 rounded-2xl border border-slate-200/90 dark:border-slate-800/90 p-5 shadow-sm hover:border-blue-300 dark:hover:border-blue-700/60 transition-all flex flex-col justify-between space-y-4"
          >
            <div>
              <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2.5">
                  <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg font-bold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 shadow-sm">
                    <span v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token">🟡</span>
                    <span v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key">💜</span>
                    <span v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key">💚</span>
                    <span v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id">🟦</span>
                    <span v-else-if="acc.account_type === 'payment_gateway'">💳</span>
                    <span v-else-if="acc.account_type === 'merchant'">📱</span>
                    <span v-else-if="acc.account_type === 'savings'">📈</span>
                    <span v-else-if="acc.account_type === 'cash'">💵</span>
                    <span v-else>🏦</span>
                  </div>
                  <div>
                    <h4 class="font-extrabold text-slate-900 dark:text-white text-sm leading-snug">{{ acc.bank_name }}</h4>
                    <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ acc.account_name }}</p>
                  </div>
                </div>

                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide border', acc.is_active ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-800']">
                  {{ acc.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>

              <div class="mt-3 pt-2.5 border-t border-slate-200/60 dark:border-slate-800/60 grid grid-cols-2 gap-2 text-[11px]">
                <div>
                  <span class="text-[9px] font-bold text-slate-400 uppercase block">Account Type</span>
                  <span class="font-bold text-slate-700 dark:text-slate-200">{{ acc.formatted_account_type || acc.account_type }}</span>
                </div>
                <div>
                  <span class="text-[9px] font-bold text-slate-400 uppercase block">Nominal Code</span>
                  <span class="font-mono font-bold text-blue-600 dark:text-blue-400">Code {{ acc.account_code || '1000' }}</span>
                </div>
                <div v-if="acc.sort_code || acc.account_number" class="col-span-2 text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                  <span>Sort: {{ acc.sort_code || 'N/A' }}</span> | <span>Acc: {{ acc.account_number || 'N/A' }}</span>
                </div>
              </div>

              <!-- GoCardless API Badge -->
              <div v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token" class="mt-2.5 p-2 bg-amber-50/90 dark:bg-amber-950/90 rounded-xl border border-amber-200 dark:border-amber-800/60 flex items-center justify-between text-[11px]">
                <div>
                  <span class="font-bold text-amber-900 dark:text-amber-200 block">🟡 GoCardless API Active ({{ acc.gocardless_environment || 'sandbox' }})</span>
                  <span class="text-[10px] text-amber-700 dark:text-amber-300 font-medium">Direct Debit Payment Feed</span>
                </div>
                <button
                  type="button"
                  @click="syncGoCardlessNow(acc.id)"
                  class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white font-bold text-[10px] rounded-xl shadow-sm transition-all cursor-pointer"
                >
                  Sync API now
                </button>
              </div>

              <!-- Stripe API Badge -->
              <div v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key" class="mt-2.5 p-2 bg-blue-50/90 dark:bg-blue-950/90 rounded-xl border border-blue-200 dark:border-blue-800/60 flex items-center justify-between text-[11px]">
                <div>
                  <span class="font-bold text-blue-900 dark:text-blue-200 block">🟢 Stripe API Sync Active</span>
                  <span class="text-[10px] text-blue-700 dark:text-blue-300 font-medium">Balance &amp; Payout Feed</span>
                </div>
                <button
                  type="button"
                  @click="syncStripeNow(acc.id)"
                  class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-xl shadow-sm transition-all cursor-pointer"
                >
                  Sync API now
                </button>
              </div>

              <!-- SumUp API Badge -->
              <div v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key" class="mt-2.5 p-2 bg-emerald-50/90 dark:bg-emerald-950/90 rounded-xl border border-emerald-200 dark:border-emerald-800/60 flex items-center justify-between text-[11px]">
                <div>
                  <span class="font-bold text-emerald-900 dark:text-emerald-200 block">🟢 SumUp API Sync Active</span>
                  <span class="text-[10px] text-emerald-700 dark:text-emerald-300 font-medium">Card Reader Feed</span>
                </div>
                <button
                  type="button"
                  @click="syncSumUpNow(acc.id)"
                  class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] rounded-xl shadow-sm transition-all cursor-pointer"
                >
                  Sync API now
                </button>
              </div>

              <!-- PayPal API Badge -->
              <div v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id" class="mt-2.5 p-2 bg-blue-50/90 dark:bg-blue-950/90 rounded-xl border border-blue-200 dark:border-blue-800/60 flex items-center justify-between text-[11px]">
                <div>
                  <span class="font-bold text-blue-900 dark:text-blue-200 block">🟢 PayPal API Sync Active</span>
                  <span class="text-[10px] text-blue-700 dark:text-blue-300 font-medium">Env: {{ acc.paypal_environment || 'live' }}</span>
                </div>
                <button
                  type="button"
                  @click="syncPayPalNow(acc.id)"
                  class="px-2.5 py-1 bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] rounded-xl shadow-sm transition-all cursor-pointer"
                >
                  Sync API now
                </button>
              </div>

              <div class="mt-3 p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200/80 dark:border-slate-800/80 space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                  <span class="font-bold text-slate-500 dark:text-slate-400">Statement Balance</span>
                  <span class="font-black text-slate-900 dark:text-white">{{ acc.formatted_statement_balance || ($cs + '0.00') }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="font-bold text-slate-500 dark:text-slate-400">Ledger Balance (Code {{ acc.account_code || '1000' }})</span>
                  <span class="font-bold text-blue-700 dark:text-blue-300">{{ acc.formatted_ledger_balance || ($cs + '0.00') }}</span>
                </div>
              </div>
            </div>

            <div class="pt-2 flex items-center justify-between gap-2 border-t border-slate-200/60 dark:border-slate-800/60 text-xs">
              <button
                type="button"
                @click="navigateTo('reconciliation', null, acc.id)"
                :class="['px-3 py-1.5 rounded-xl font-bold transition-all flex items-center gap-1.5 cursor-pointer', acc.unreconciled_count > 0 ? 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm' : 'bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-800 dark:text-slate-100']"
              >
                <span>⚡ Reconcile ({{ acc.unreconciled_count || 0 }})</span>
              </button>

              <button
                type="button"
                @click="toggleBankAccountActive(acc.id)"
                class="px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-100 cursor-pointer"
              >
                {{ acc.is_active ? 'Deactivate' : 'Activate' }}
              </button>
            </div>
          </div>
        </div>
      </div>


      <!-- General Ledger Journal Entries -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden space-y-4">
        <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
          <div>
            <h3 class="text-base font-extrabold text-slate-900 dark:text-white">General Ledger Journal Entries</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Historical double-entry records posted to the ledger.</p>
          </div>
          <Link
            :href="route('admin.accounting.journal.create', club.slug)"
            class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl transition-all"
          >
            + Add journal entry
          </Link>
        </div>

        <div class="p-6 space-y-4">
          <div v-if="!journalEntries.length" class="text-center py-12 border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl bg-slate-50 dark:bg-slate-800/50">
            <span class="text-3xl block mb-2">📖</span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 block">No Journal Entries Recorded</span>
            <span class="text-[11px] text-slate-400">Click "Post Journal Entry" above to add your first double-entry transaction.</span>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="entry in journalEntries"
              :key="entry.id"
              class="bg-slate-50/80 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-800 p-4 space-y-3"
            >
              <div class="flex items-center justify-between text-xs border-b border-slate-200/60 dark:border-slate-800/60 pb-2">
                <div class="flex items-center gap-3">
                  <span class="font-mono font-black text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 px-2 py-0.5 rounded-md border border-blue-200 dark:border-blue-800/60">
                    {{ entry.reference_number }}
                  </span>
                  <span class="font-bold text-slate-900 dark:text-white">{{ entry.description }}</span>
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 text-[11px] font-medium">
                  <span>📅 {{ entry.entry_date }}</span>
                  <span class="px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 font-bold uppercase text-[9px]">
                    {{ entry.status }}
                  </span>
                  <Link
                    :href="route('admin.accounting.journal.edit', { clubSlug: club.slug, id: entry.id })"
                    class="px-2 py-0.5 bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 font-bold text-[10px] rounded-xl transition-colors"
                  >
                    Edit
                  </Link>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead>
                    <tr class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">
                      <th class="py-1">Account</th>
                      <th class="py-1 text-right">Debit</th>
                      <th class="py-1 text-right">Credit</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200/40 dark:divide-slate-800/40 text-[11px] font-semibold">
                    <tr v-for="item in entry.items" :key="item.id">
                      <td class="py-1.5 font-medium text-slate-800 dark:text-slate-100">
                        <span class="font-mono font-bold text-slate-500 dark:text-slate-400 mr-2">{{ item.account_code }}</span>
                        <span>{{ item.account_name }}</span>
                        <span v-if="item.memo" class="text-slate-400 ml-2 italic text-[10px]">({{ item.memo }})</span>
                      </td>
                      <td class="py-1.5 text-right font-mono text-slate-900 dark:text-white">
                        {{ item.debit > 0 ? formatMoney(item.debit) : '-' }}
                      </td>
                      <td class="py-1.5 text-right font-mono text-slate-900 dark:text-white">
                        {{ item.credit > 0 ? formatMoney(item.credit) : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>
