<script setup>
import { Link } from '@inertiajs/vue3';
import { formatMoney } from '@/Utils/currency';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

// A section of the accounting reconciliation screen, in its own file so the page stays readable.
const giftAidFilterDateFrom = defineModel('giftAidFilterDateFrom', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterDateTo = defineModel('giftAidFilterDateTo', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterPerson = defineModel('giftAidFilterPerson', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterStatus = defineModel('giftAidFilterStatus', { type: [String, Number, Boolean, Array, Object], default: undefined });
const giftAidFilterSearch = defineModel('giftAidFilterSearch', { type: [String, Number, Boolean, Array, Object], default: undefined });

defineProps({
    club: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredReconciledDonations: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    filteredReconciledGiftAidTotal: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    reconciliation: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    resetGiftAidFilters: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});

const giftAidStatusOptions = [
    { value: 'all', label: 'All statuses' },
    { value: 'reconciled', label: 'Reconciled only' },
    { value: 'claimed', label: 'Claimed' },
    { value: 'pending', label: 'Pending / unclaimed' },
];

const primaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950';
const secondaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950';
</script>

<template>
    <div class="space-y-6">
      <Card>
        <div class="space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <h4 class="font-semibold text-slate-900 dark:text-white text-base">Gift Aid recovery and Relief Chest reconciliation</h4>
              <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                Automatic matching for HMRC 25% Gift Aid tax reclaims and MCF Relief Chest (ref: <span class="font-semibold text-slate-900 dark:text-white">{{ reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418' }}</span>) deposit distributions.
              </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Link
                :href="route('admin.accounting.giftaid.reconcile_auto', club.slug)"
                method="post"
                as="button"
                :class="primaryButton"
              >
                <UiIcon name="lightning" />
                Auto-match Gift Aid and Relief Chest deposits
              </Link>
              <a
                :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
                target="_blank"
                :class="secondaryButton"
              >
                <UiIcon name="download" />
                Export HMRC schedule (CSV)
              </a>
            </div>
          </div>

          <!-- Summary Position Cards Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
            <StatCard label="Eligible donations" :value="reconciliation?.gift_aid_summary?.formatted_total_eligible || ($cs + '0.00')" caption="Meeting alms and envelopes" />
            <StatCard label="Reclaimed Gift Aid" :value="reconciliation?.gift_aid_summary?.formatted_gift_aid_reclaimed || ($cs + '0.00')" caption="25% HMRC tax reclaims reconciled" />
            <StatCard label="Pending reclaim" :value="reconciliation?.gift_aid_summary?.formatted_pending_gift_aid || ($cs + '0.00')" :caption="`${reconciliation?.gift_aid_summary?.pending_claim_count || 0} collection batch(es) pending`" />
            <StatCard label="Net Relief Chest position" :value="reconciliation?.gift_aid_summary?.formatted_net_relief_chest_balance || ($cs + '0.00')" :caption="`Relief Chest ref: ${reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418'}`" />
          </div>
        </div>
      </Card>

      <!-- Educational & Rule Explanation Banner -->
      <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
        <h5 class="font-semibold text-slate-900 dark:text-white text-sm flex items-center gap-1.5">
          <UiIcon name="info" class="text-slate-500 dark:text-slate-400" />
          <span>How Gift Aid and Relief Chest reconciliation works</span>
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-medium text-slate-900 dark:text-white block">1. 25% tax reclaim calculation</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Every Gift Aid envelope or alms donation recorded during dual-custody meeting counts generates a 25% reclaimable Gift Aid entitlement.
            </p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-medium text-slate-900 dark:text-white block">2. Automatic bank keyword matching</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Bank statement lines with keywords like <code>HMRC GIFT AID</code>, <code>MCF RELIEF CHEST</code>, or <code>PROVINCIAL RELIEF</code> are automatically matched.
            </p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-medium text-slate-900 dark:text-white block">3. Double-entry general ledger</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Reconciled payouts post automatically to nominal code 4310 (Gift Aid tax reclaim income) or code 4300 (Relief Chest contributions).
            </p>
          </div>
        </div>
      </div>

      <!-- DETAILED RECONCILED DONATIONS & GIFT AID TRANSACTIONS LEDGER WITH FILTERS -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">

        <!-- Header & Filter Summary -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
          <div>
            <h4 class="font-semibold text-slate-900 dark:text-white text-base">
              Reconciled donations and Gift Aid transactions
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
              Every meeting alms collection, Festival gift and Gift Aid tax reclaim, with its bank matching status.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Badge variant="info">
              Filtered Gift Aid: {{ formatMoney(filteredReconciledGiftAidTotal) }}
            </Badge>
            <Badge>
              Showing {{ filteredReconciledDonations.length }} transaction(s)
            </Badge>
          </div>
        </div>

        <!-- Interactive Filters Form Bar -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3">
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
              <UiIcon name="filter" />
              <span>Filter by date, person and status</span>
            </span>
            <Button variant="ghost" size="sm" @click="resetGiftAidFilters">
              Reset filters
            </Button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <Input v-model="giftAidFilterDateFrom" type="date" label="From date" />

            <Input v-model="giftAidFilterDateTo" type="date" label="To date" />

            <!-- Person / Donor Filter (member ids, so a native select) -->
            <div class="space-y-1.5">
              <label for="gift-aid-filter-person" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Donor / person</label>
              <select
                id="gift-aid-filter-person"
                v-model="giftAidFilterPerson"
                class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-950 px-3 py-2 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 cursor-pointer"
              >
                <option value="">All donors and Stewards</option>
                <option
                  v-for="m in (reconciliation?.reconciled_donations?.members || [])"
                  :key="m.id"
                  :value="m.id"
                >
                  {{ m.name }}
                </option>
              </select>
            </div>

            <Select v-model="giftAidFilterStatus" label="Gift Aid status" :options="giftAidStatusOptions" />

            <Input v-model="giftAidFilterSearch" label="Search keywords" placeholder="Search notes, donor..." />
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
              <tr>
                <th scope="col" class="py-3 px-3.5 font-medium">Date</th>
                <th scope="col" class="py-3 px-3.5 font-medium">Donor / person</th>
                <th scope="col" class="py-3 px-3.5 font-medium">Collection type</th>
                <th scope="col" class="py-3 px-3.5 font-medium text-right">Donation total</th>
                <th scope="col" class="py-3 px-3.5 font-medium text-right">25% Gift Aid</th>
                <th scope="col" class="py-3 px-3.5 font-medium text-center">Gift Aid status</th>
                <th scope="col" class="py-3 px-3.5 font-medium">Matched bank deposit</th>
                <th scope="col" class="py-3 px-3.5 font-medium">Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-800 dark:text-slate-100">
              <tr
                v-for="item in filteredReconciledDonations"
                :key="item.id"
                class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors"
              >
                <!-- Date -->
                <td class="py-3 px-3.5 tabular-nums text-slate-600 dark:text-slate-300 whitespace-nowrap">
                  {{ item.created_at }}
                </td>

                <!-- Donor / Person -->
                <td class="py-3 px-3.5 font-medium text-slate-900 dark:text-white whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <UiIcon name="user" :size="14" class="text-slate-400" />
                    <span>{{ item.donor_name }}</span>
                  </div>
                </td>

                <!-- Collection Type -->
                <td class="py-3 px-3.5 whitespace-nowrap">
                  <Badge>{{ item.collection_type }}</Badge>
                </td>

                <!-- Donation Total -->
                <td class="py-3 px-3.5 text-right tabular-nums font-medium text-slate-900 dark:text-white whitespace-nowrap">
                  {{ item.formatted_total }}
                </td>

                <!-- 25% Gift Aid -->
                <td class="py-3 px-3.5 text-right tabular-nums font-semibold text-blue-700 dark:text-blue-300 whitespace-nowrap">
                  {{ item.formatted_gift_aid }}
                </td>

                <!-- Gift Aid Status -->
                <td class="py-3 px-3.5 text-center whitespace-nowrap">
                  <Badge v-if="item.gift_aid_status === 'reconciled'" variant="success"><UiIcon name="check" :size="12" /> Reconciled</Badge>
                  <Badge v-else-if="item.gift_aid_status === 'claimed'" variant="info">Claimed</Badge>
                  <Badge v-else variant="warning">Unclaimed</Badge>
                </td>

                <!-- Matched Bank Deposit -->
                <td class="py-3 px-3.5 text-xs text-slate-600 dark:text-slate-300 max-w-xs">
                  <div v-if="item.bank_transaction" class="p-2 bg-emerald-50/70 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 rounded-xl text-[11px] space-y-0.5">
                    <div class="font-semibold text-emerald-900 dark:text-emerald-200 flex items-center justify-between gap-2">
                      <span class="tabular-nums">Bank credit: {{ item.bank_transaction.formatted_amount }}</span>
                      <span class="text-[10px] font-normal text-emerald-700 dark:text-emerald-300">{{ item.bank_transaction.transaction_date }}</span>
                    </div>
                    <div class="text-[10px] text-emerald-800 dark:text-emerald-200 truncate" :title="item.bank_transaction.raw_description">
                      {{ item.bank_transaction.raw_description }}
                    </div>
                  </div>
                  <span v-else class="text-slate-500 dark:text-slate-400 text-[11px]">No bank credit linked yet</span>
                </td>

                <!-- Notes -->
                <td class="py-3 px-3.5 text-slate-500 dark:text-slate-400 text-[11px] max-w-xs truncate" :title="item.notes || 'No notes'">
                  {{ item.notes || '—' }}
                </td>
              </tr>

              <tr v-if="filteredReconciledDonations.length === 0">
                <td colspan="8" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                  No reconciled donations match the selected filters.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
</template>
