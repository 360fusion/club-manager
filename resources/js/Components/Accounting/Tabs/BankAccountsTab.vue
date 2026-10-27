<script setup>
import { formatMoney } from '@/Utils/currency';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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

const MENU_ITEM = 'flex w-full flex-col items-start px-4 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800';
</script>

<template>
    <div class="space-y-6">
      <!-- Bank and payment accounts -->
      <Card>
        <div class="space-y-6">
          <div class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center dark:border-slate-800">
            <div>
              <h3 class="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white">
                <UiIcon name="bank" :size="20" class="text-slate-500 dark:text-slate-400" />
                Bank and payment accounts
              </h3>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">High street bank accounts and payment providers (Stripe, PayPal, SumUp, GoCardless).</p>
            </div>
            <div v-if="canManageBilling" class="relative connect-bank-dropdown-container">
              <Button :aria-expanded="Boolean(showConnectBankDropdown)" aria-haspopup="menu" @click.stop="showConnectBankDropdown = !showConnectBankDropdown">
                <template #icon><UiIcon name="plus" /></template>
                Add an account
                <UiIcon name="chevron-down" />
              </Button>

              <div
                v-if="showConnectBankDropdown"
                role="menu"
                class="absolute right-0 z-40 mt-1 w-72 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 text-sm shadow-lg dark:border-slate-700 dark:bg-slate-900"
              >
                <div class="px-4 py-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
                  Bank account and payment providers
                </div>

                <button type="button" role="menuitem" :class="MENU_ITEM" @click="showBankAccountModal = true; showConnectBankDropdown = false">
                  <span class="font-medium text-slate-900 dark:text-white">Bank account</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">Current, savings or cash account</span>
                </button>

                <button type="button" role="menuitem" :class="MENU_ITEM" @click="showGoCardlessModal = true; showConnectBankDropdown = false">
                  <span class="font-medium text-slate-900 dark:text-white">GoCardless</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">Direct debit mandates and collections</span>
                </button>

                <button type="button" role="menuitem" :class="MENU_ITEM" @click="showStripeModal = true; showConnectBankDropdown = false">
                  <span class="font-medium text-slate-900 dark:text-white">Stripe</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">Card payments and payouts</span>
                </button>

                <button type="button" role="menuitem" :class="MENU_ITEM" @click="showSumUpModal = true; showConnectBankDropdown = false">
                  <span class="font-medium text-slate-900 dark:text-white">SumUp card reader</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">Card terminal sales</span>
                </button>

                <button type="button" role="menuitem" :class="MENU_ITEM" @click="showPayPalModal = true; showConnectBankDropdown = false">
                  <span class="font-medium text-slate-900 dark:text-white">PayPal</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">PayPal checkout payments</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Account cards -->
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
              v-for="acc in (bankAccounts || [])"
              :key="acc.id"
              class="flex flex-col justify-between space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 transition-colors hover:border-blue-300 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-blue-700/60"
            >
              <div>
                <div class="flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2.5">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                      <UiIcon v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token" name="link" :size="20" />
                      <UiIcon v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key" name="link" :size="20" />
                      <UiIcon v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key" name="link" :size="20" />
                      <UiIcon v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id" name="link" :size="20" />
                      <UiIcon v-else-if="acc.account_type === 'payment_gateway'" name="link" :size="20" />
                      <UiIcon v-else-if="acc.account_type === 'merchant'" name="receipt" :size="20" />
                      <UiIcon v-else-if="acc.account_type === 'savings'" name="chart" :size="20" />
                      <UiIcon v-else-if="acc.account_type === 'cash'" name="cash" :size="20" />
                      <UiIcon v-else name="bank" :size="20" />
                    </div>
                    <div>
                      <h4 class="text-sm font-semibold leading-snug text-slate-900 dark:text-white">{{ acc.bank_name }}</h4>
                      <p class="text-xs text-slate-500 dark:text-slate-400">{{ acc.account_name }}</p>
                    </div>
                  </div>

                  <Badge :variant="acc.is_active ? 'success' : 'neutral'">{{ acc.is_active ? 'Active' : 'Inactive' }}</Badge>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-200 pt-2.5 text-xs dark:border-slate-800">
                  <div>
                    <span class="block text-slate-500 dark:text-slate-400">Account type</span>
                    <span class="font-medium text-slate-700 dark:text-slate-200">{{ acc.formatted_account_type || acc.account_type }}</span>
                  </div>
                  <div>
                    <span class="block text-slate-500 dark:text-slate-400">Nominal code</span>
                    <span class="font-medium tabular-nums text-slate-700 dark:text-slate-200">Code {{ acc.account_code || '1000' }}</span>
                  </div>
                  <div v-if="acc.sort_code || acc.account_number" class="col-span-2 tabular-nums text-slate-500 dark:text-slate-400">
                    <span>Sort code: {{ acc.sort_code || 'N/A' }}</span> · <span>Account: {{ acc.account_number || 'N/A' }}</span>
                  </div>
                </div>

                <!-- GoCardless feed -->
                <div v-if="acc.bank_name === 'GoCardless' || acc.gocardless_access_token" class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                  <div class="space-y-0.5">
                    <span class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">GoCardless <Badge variant="success">Connected</Badge></span>
                    <span class="block text-slate-500 dark:text-slate-400">Environment: {{ acc.gocardless_environment || 'sandbox' }}</span>
                  </div>
                  <Button variant="secondary" size="sm" @click="syncGoCardlessNow(acc.id)">Sync now</Button>
                </div>

                <!-- Stripe feed -->
                <div v-else-if="acc.bank_name === 'Stripe' || acc.stripe_secret_key" class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                  <div class="space-y-0.5">
                    <span class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">Stripe <Badge variant="success">Connected</Badge></span>
                    <span class="block text-slate-500 dark:text-slate-400">Balance and payout feed</span>
                  </div>
                  <Button variant="secondary" size="sm" @click="syncStripeNow(acc.id)">Sync now</Button>
                </div>

                <!-- SumUp feed -->
                <div v-else-if="acc.bank_name === 'SumUp' || acc.sumup_api_key" class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                  <div class="space-y-0.5">
                    <span class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">SumUp <Badge variant="success">Connected</Badge></span>
                    <span class="block text-slate-500 dark:text-slate-400">Card reader feed</span>
                  </div>
                  <Button variant="secondary" size="sm" @click="syncSumUpNow(acc.id)">Sync now</Button>
                </div>

                <!-- PayPal feed -->
                <div v-else-if="acc.bank_name === 'PayPal' || acc.paypal_client_id" class="mt-3 flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900">
                  <div class="space-y-0.5">
                    <span class="flex items-center gap-1.5 font-medium text-slate-900 dark:text-white">PayPal <Badge variant="success">Connected</Badge></span>
                    <span class="block text-slate-500 dark:text-slate-400">Environment: {{ acc.paypal_environment || 'live' }}</span>
                  </div>
                  <Button variant="secondary" size="sm" @click="syncPayPalNow(acc.id)">Sync now</Button>
                </div>

                <div class="mt-3 space-y-1.5 rounded-xl border border-slate-200 bg-white p-3 text-xs dark:border-slate-800 dark:bg-slate-900">
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Statement balance</span>
                    <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ acc.formatted_statement_balance || ($cs + '0.00') }}</span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-slate-500 dark:text-slate-400">Ledger balance (code {{ acc.account_code || '1000' }})</span>
                    <span class="font-medium tabular-nums text-slate-700 dark:text-slate-200">{{ acc.formatted_ledger_balance || ($cs + '0.00') }}</span>
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between gap-2 border-t border-slate-200 pt-3 dark:border-slate-800">
                <Button variant="secondary" size="sm" @click="navigateTo('reconciliation', null, acc.id)">
                  Reconcile
                  <Badge :variant="acc.unreconciled_count > 0 ? 'warning' : 'neutral'">{{ acc.unreconciled_count || 0 }} to match</Badge>
                </Button>

                <Button variant="ghost" size="sm" @click="toggleBankAccountActive(acc.id)">
                  {{ acc.is_active ? 'Deactivate' : 'Activate' }}
                </Button>
              </div>
            </div>
          </div>
        </div>
      </Card>

      <!-- General ledger journal entries -->
      <Card padding="none" class="overflow-hidden">
        <div class="flex items-center justify-between gap-4 border-b border-slate-200 p-6 dark:border-slate-800">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">General ledger journal entries</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Double-entry records posted to the ledger.</p>
          </div>
          <Button variant="secondary" size="sm" :href="route('admin.accounting.journal.create', club.slug)">
            <template #icon><UiIcon name="plus" /></template>
            Add journal entry
          </Button>
        </div>

        <div class="space-y-4 p-6">
          <div v-if="!journalEntries.length" class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50 py-12 text-center dark:border-slate-800 dark:bg-slate-800/50">
            <UiIcon name="document" :size="20" class="mx-auto mb-2 text-slate-400" />
            <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">No journal entries yet</span>
            <span class="text-xs text-slate-500 dark:text-slate-400">Use "Add journal entry" above to post your first double-entry transaction.</span>
          </div>

          <div v-else class="space-y-4">
            <div
              v-for="entry in journalEntries"
              :key="entry.id"
              class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
            >
              <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-2 text-xs dark:border-slate-800">
                <div class="flex items-center gap-3">
                  <span class="font-semibold tabular-nums text-slate-700 dark:text-slate-200">
                    {{ entry.reference_number }}
                  </span>
                  <span class="font-medium text-slate-900 dark:text-white">{{ entry.description }}</span>
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400">
                  <span class="flex items-center gap-1"><UiIcon name="calendar" /> {{ entry.entry_date }}</span>
                  <Badge variant="success">{{ entry.status }}</Badge>
                  <Button variant="secondary" size="sm" :href="route('admin.accounting.journal.edit', { clubSlug: club.slug, id: entry.id })">
                    Edit
                  </Button>
                </div>
              </div>

              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                  <thead class="text-slate-500 dark:text-slate-400">
                    <tr>
                      <th scope="col" class="py-1 font-medium">Account</th>
                      <th scope="col" class="py-1 text-right font-medium">Debit</th>
                      <th scope="col" class="py-1 text-right font-medium">Credit</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="item in entry.items" :key="item.id">
                      <td class="py-1.5 text-slate-800 dark:text-slate-100">
                        <span class="mr-2 tabular-nums text-slate-500 dark:text-slate-400">{{ item.account_code }}</span>
                        <span>{{ item.account_name }}</span>
                        <span v-if="item.memo" class="ml-2 italic text-slate-400">({{ item.memo }})</span>
                      </td>
                      <td class="py-1.5 text-right tabular-nums text-slate-900 dark:text-white">
                        {{ item.debit > 0 ? formatMoney(item.debit) : '-' }}
                      </td>
                      <td class="py-1.5 text-right tabular-nums text-slate-900 dark:text-white">
                        {{ item.credit > 0 ? formatMoney(item.credit) : '-' }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </Card>
    </div>
</template>
