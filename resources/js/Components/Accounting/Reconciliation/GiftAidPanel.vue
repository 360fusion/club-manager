<script setup>
import { Link } from '@inertiajs/vue3';
import { formatMoney } from '@/Utils/currency';

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
</script>

<template>
    <div class="space-y-6">
      <div class="bg-gradient-to-br from-amber-50 dark:from-amber-950/40 to-orange-50 dark:to-orange-950/40 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl">🏛️</span>
              <h4 class="font-black text-slate-900 dark:text-white text-base">Automated Gift Aid Recovery &amp; Relief Chest Reconciliation</h4>
            </div>
            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
              Automatic matching engine for HMRC 25% Gift Aid tax reclaims and MCF Relief Chest (Ref: <span class="font-extrabold text-slate-900 dark:text-white">{{ reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418' }}</span>) deposit distributions.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Link
              :href="route('admin.accounting.giftaid.reconcile_auto', club.slug)"
              method="post"
              as="button"
              class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-extrabold text-xs rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
            >
              <span>⚡ Auto-Match Gift Aid &amp; Relief Chest Deposits</span>
            </Link>
            <a
              :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
              target="_blank"
              class="px-3.5 py-2 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 font-extrabold text-xs rounded-xl shadow-sm transition-all flex items-center gap-1.5"
            >
              <span>📥 Export HMRC Schedule (CSV)</span>
            </a>
          </div>
        </div>

        <!-- Summary Position Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
          <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-amber-200/60 dark:border-amber-800/60 shadow-2xs space-y-1">
            <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Eligible Donations</span>
            <span class="text-xl font-black text-slate-900 dark:text-white block">{{ reconciliation?.gift_aid_summary?.formatted_total_eligible || ($cs + '0.00') }}</span>
            <span class="text-[10px] text-slate-400">Meeting Alms &amp; Envelopes</span>
          </div>

          <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-emerald-200/60 dark:border-emerald-800/60 shadow-2xs space-y-1">
            <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 uppercase tracking-wider block">Reclaimed Gift Aid</span>
            <span class="text-xl font-black text-emerald-800 dark:text-emerald-200 block">{{ reconciliation?.gift_aid_summary?.formatted_gift_aid_reclaimed || ($cs + '0.00') }}</span>
            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">25% HMRC Tax Reclaims Reconciled</span>
          </div>

          <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-amber-300 dark:border-amber-700/60 shadow-2xs space-y-1">
            <span class="text-[11px] font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wider block">Pending Reclaim</span>
            <span class="text-xl font-black text-amber-900 dark:text-amber-200 block">{{ reconciliation?.gift_aid_summary?.formatted_pending_gift_aid || ($cs + '0.00') }}</span>
            <span class="text-[10px] text-amber-700 dark:text-amber-300 font-bold">{{ reconciliation?.gift_aid_summary?.pending_claim_count || 0 }} collection batch(es) pending</span>
          </div>

          <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-blue-200/60 dark:border-blue-800/60 shadow-2xs space-y-1">
            <span class="text-[11px] font-bold text-blue-800 dark:text-blue-200 uppercase tracking-wider block">Net Relief Chest Position</span>
            <span class="text-xl font-black text-blue-900 dark:text-blue-200 block">{{ reconciliation?.gift_aid_summary?.formatted_net_relief_chest_balance || ($cs + '0.00') }}</span>
            <span class="text-[10px] text-blue-700 dark:text-blue-300 font-bold">Chest Ref: {{ reconciliation?.gift_aid_summary?.relief_chest_ref || 'E1418' }}</span>
          </div>
        </div>
      </div>

      <!-- Educational & Rule Explanation Banner -->
      <div class="p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-3">
        <h5 class="font-extrabold text-slate-900 dark:text-white text-xs uppercase tracking-wider flex items-center gap-1.5">
          <span>💡</span>
          <span>How Automated Gift Aid &amp; Relief Chest Reconciliation Works</span>
        </h5>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-bold text-slate-900 dark:text-white block">1. 25% Tax Reclaim Calculation</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Every Gift Aid envelope or alms donation recorded during dual-custody meeting counts generates a 25% reclaimable Gift Aid entitlement.
            </p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-bold text-slate-900 dark:text-white block">2. Automatic Bank Keyword Matching</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Bank statement lines with keywords like <code>HMRC GIFT AID</code>, <code>MCF RELIEF CHEST</code>, or <code>PROVINCIAL RELIEF</code> are automatically matched.
            </p>
          </div>
          <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1">
            <span class="font-bold text-slate-900 dark:text-white block">3. Double-Entry General Ledger</span>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              Reconciled payouts post automatically to Nominal Code 4310 (Gift Aid Tax Reclaim Income) or Code 4300 (Relief Chest Contributions).
            </p>
          </div>
        </div>
      </div>

      <!-- DETAILED RECONCILED DONATIONS & GIFT AID TRANSACTIONS LEDGER WITH FILTERS -->
      <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm space-y-5">
        
        <!-- Header & Filter Summary -->
        <div class="flex flex-col lg:flex-row lg:items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800 gap-4">
          <div>
            <h4 class="font-black text-slate-900 dark:text-white text-base flex items-center gap-2">
              <span>📜</span>
              <span>Reconciled Donations &amp; Gift Aid Transactions Ledger</span>
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-medium">
              Detailed listing of all meeting alms, festival giving, and gift aid tax reclaims with bank matching status.
            </p>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="px-3 py-1 bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-200 font-extrabold rounded-xl border border-blue-200/60 dark:border-blue-800/60">
              Total Filtered Gift Aid: {{ formatMoney(filteredReconciledGiftAidTotal) }}
            </span>
            <span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold rounded-xl border border-slate-200 dark:border-slate-800">
              Showing {{ filteredReconciledDonations.length }} transaction(s)
            </span>
          </div>
        </div>

        <!-- Interactive Filters Form Bar -->
        <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-200">
            <span class="flex items-center gap-1.5">
              <span>🔍</span>
              <span>Filter Transactions by Date, Person &amp; Status</span>
            </span>
            <button
              type="button"
              @click="resetGiftAidFilters"
              class="text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-200 underline font-bold cursor-pointer"
            >
              Reset Filters
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <!-- Date From -->
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase mb-1">From Date</label>
              <input
                v-model="giftAidFilterDateFrom"
                type="date"
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Date To -->
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase mb-1">To Date</label>
              <input
                v-model="giftAidFilterDateTo"
                type="date"
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <!-- Person / Donor Filter -->
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase mb-1">Donor / Person</label>
              <select
                v-model="giftAidFilterPerson"
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 cursor-pointer"
              >
                <option value="">All Donors &amp; Stewards</option>
                <option
                  v-for="m in (reconciliation?.reconciled_donations?.members || [])"
                  :key="m.id"
                  :value="m.id"
                >
                  {{ m.name }}
                </option>
              </select>
            </div>

            <!-- Status Filter -->
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase mb-1">Gift Aid Status</label>
              <select
                v-model="giftAidFilterStatus"
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500 cursor-pointer"
              >
                <option value="all">All Statuses</option>
                <option value="reconciled">Reconciled Only</option>
                <option value="claimed">Claimed</option>
                <option value="pending">Pending / Unclaimed</option>
              </select>
            </div>

            <!-- Keyword Search -->
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 dark:text-slate-400 uppercase mb-1">Search Keywords</label>
              <input
                v-model="giftAidFilterSearch"
                type="text"
                placeholder="Search notes, donor..."
                class="w-full px-3 py-1.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-extrabold uppercase tracking-wider">
              <tr>
                <th class="py-3 px-3.5">Date</th>
                <th class="py-3 px-3.5">Donor / Person</th>
                <th class="py-3 px-3.5">Collection Type</th>
                <th class="py-3 px-3.5 text-right">Donation Total</th>
                <th class="py-3 px-3.5 text-right">25% Gift Aid</th>
                <th class="py-3 px-3.5 text-center">Gift Aid Status</th>
                <th class="py-3 px-3.5">Matched Bank Deposit</th>
                <th class="py-3 px-3.5">Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-800 dark:text-slate-100">
              <tr
                v-for="item in filteredReconciledDonations"
                :key="item.id"
                class="hover:bg-blue-50/40 dark:hover:bg-blue-950/40 transition-colors"
              >
                <!-- Date -->
                <td class="py-3 px-3.5 font-mono text-[11px] text-slate-600 dark:text-slate-300 whitespace-nowrap">
                  {{ item.created_at }}
                </td>

                <!-- Donor / Person -->
                <td class="py-3 px-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <span class="text-sm">👤</span>
                    <span>{{ item.donor_name }}</span>
                  </div>
                </td>

                <!-- Collection Type -->
                <td class="py-3 px-3.5 whitespace-nowrap">
                  <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-[10px]">
                    {{ item.collection_type }}
                  </span>
                </td>

                <!-- Donation Total -->
                <td class="py-3 px-3.5 text-right font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                  {{ item.formatted_total }}
                </td>

                <!-- 25% Gift Aid -->
                <td class="py-3 px-3.5 text-right font-mono font-extrabold text-blue-700 dark:text-blue-300 whitespace-nowrap">
                  {{ item.formatted_gift_aid }}
                </td>

                <!-- Gift Aid Status -->
                <td class="py-3 px-3.5 text-center whitespace-nowrap">
                  <span
                    :class="[
                      'px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1',
                      item.gift_aid_status === 'reconciled' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60' :
                      (item.gift_aid_status === 'claimed' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60' :
                      'bg-amber-100 dark:bg-amber-900/40 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60')
                    ]"
                  >
                    <span v-if="item.gift_aid_status === 'reconciled'">✓ Reconciled</span>
                    <span v-else-if="item.gift_aid_status === 'claimed'">⚡ Claimed</span>
                    <span v-else>⏳ Unclaimed</span>
                  </span>
                </td>

                <!-- Matched Bank Deposit -->
                <td class="py-3 px-3.5 text-xs text-slate-600 dark:text-slate-300 max-w-xs">
                  <div v-if="item.bank_transaction" class="p-2 bg-emerald-50/70 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800/60 rounded-lg text-[11px] space-y-0.5">
                    <div class="font-extrabold text-emerald-900 dark:text-emerald-200 flex items-center justify-between">
                      <span>Bank Credit: {{ item.bank_transaction.formatted_amount }}</span>
                      <span class="text-[9px] text-emerald-700 dark:text-emerald-300">{{ item.bank_transaction.transaction_date }}</span>
                    </div>
                    <div class="text-[10px] text-emerald-800 dark:text-emerald-200 truncate" :title="item.bank_transaction.raw_description">
                      {{ item.bank_transaction.raw_description }}
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-[11px]">No bank credit linked yet</span>
                </td>

                <!-- Notes -->
                <td class="py-3 px-3.5 text-slate-500 dark:text-slate-400 text-[11px] max-w-xs truncate" :title="item.notes || 'No notes'">
                  {{ item.notes || '—' }}
                </td>
              </tr>

              <tr v-if="filteredReconciledDonations.length === 0">
                <td colspan="8" class="py-12 text-center text-slate-400 italic">
                  No reconciled donations match the selected filter criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
</template>
