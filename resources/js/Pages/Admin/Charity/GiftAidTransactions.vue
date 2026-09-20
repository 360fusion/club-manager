<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CharitySubNav from '@/Components/CharitySubNav.vue';

const props = defineProps({
  club: Object,
  giftAidSummary: Object,
  reconciledDonations: Object,
  filters: Object,
});

const filterDateFrom = ref(props.filters?.date_from || '');
const filterDateTo = ref(props.filters?.date_to || '');
const filterPerson = ref(props.filters?.person_id || '');
const filterStatus = ref(props.filters?.status || 'all');
const filterSearch = ref(props.filters?.search || '');

const filteredCollections = computed(() => {
  const list = props.reconciledDonations?.collections || [];
  return list.filter(item => {
    if (filterDateFrom.value && item.raw_date < filterDateFrom.value) return false;
    if (filterDateTo.value && item.raw_date > filterDateTo.value) return false;
    if (filterPerson.value) {
      const p = filterPerson.value.toString().toLowerCase();
      const matchId = item.donor_id && item.donor_id.toString() === p;
      const matchName = item.donor_name && item.donor_name.toLowerCase().includes(p);
      const matchCounted = item.counted_by && item.counted_by.toLowerCase().includes(p);
      if (!matchId && !matchName && !matchCounted) return false;
    }
    if (filterStatus.value !== 'all') {
      if (filterStatus.value === 'reconciled' && item.gift_aid_status !== 'reconciled') return false;
      if (filterStatus.value === 'claimed' && item.gift_aid_status !== 'claimed') return false;
      if (filterStatus.value === 'pending' && item.gift_aid_status === 'reconciled') return false;
    }
    if (filterSearch.value) {
      const s = filterSearch.value.toLowerCase();
      const matchType = (item.collection_type || '').toLowerCase().includes(s);
      const matchDonor = (item.donor_name || '').toLowerCase().includes(s);
      const matchNotes = (item.notes || '').toLowerCase().includes(s);
      const matchBank = item.bank_transaction ? ((item.bank_transaction.raw_description || '') + ' ' + (item.bank_transaction.reference || '')).toLowerCase().includes(s) : false;
      if (!matchType && !matchDonor && !matchNotes && !matchBank) return false;
    }
    return true;
  });
});

const filteredTotalDonations = computed(() => {
  return filteredCollections.value.reduce((acc, item) => acc + (item.total_amount || 0), 0);
});

const filteredTotalGiftAid = computed(() => {
  return filteredCollections.value.reduce((acc, item) => acc + (item.gift_aid_amount || 0), 0);
});

const formatCurrency = (amount) => {
  return '£' + Number(amount || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const resetFilters = () => {
  filterDateFrom.value = '';
  filterDateTo.value = '';
  filterPerson.value = '';
  filterStatus.value = 'all';
  filterSearch.value = '';
};
</script>

<template>
  <Head :title="`Gift Aid Page — ${club.name}`" />

  <AdminLayout :club="club" currentTab="charity">
    <div class="max-w-7xl mx-auto space-y-6 pb-12">
      
      <!-- Top Horizontal Sub-Navigation Bar -->
      <CharitySubNav :clubSlug="club.slug" activeTab="gift-aid" />

      <!-- Action Header Banner -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900 text-white p-6 rounded-3xl shadow-xl">
        <div class="space-y-1">
          <h1 class="text-2xl font-black tracking-tight flex items-center gap-2">
            <span>🏛️ Charity Gift Aid Transactions Page</span>
          </h1>
          <p class="text-xs text-slate-400 font-medium">
            Dedicated audit ledger for meeting alms, festival donations, 25% tax reclaims, and bank credit reconciliations.
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <Link
            :href="route('admin.accounting.giftaid.reconcile_auto', club.slug)"
            method="post"
            as="button"
            class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-md transition cursor-pointer flex items-center gap-1.5"
          >
            <span>⚡ Auto-Match Deposits</span>
          </Link>

          <a
            :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
            target="_blank"
            class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 shadow-md transition flex items-center gap-1.5"
          >
            <span>📥 Export HMRC CSV</span>
          </a>
        </div>
      </div>

      <!-- KPI Summary Position Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block">Eligible Donations Total</span>
          <span class="text-xl font-black text-slate-900 block">{{ giftAidSummary?.formatted_total_eligible || '£0.00' }}</span>
          <span class="text-[10px] text-slate-500 block">Meeting collections &amp; envelope gifts</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-purple-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-purple-600 uppercase tracking-wider block">25% Gift Aid Reclaimed</span>
          <span class="text-xl font-black text-purple-700 block">{{ giftAidSummary?.formatted_gift_aid_reclaimed || '£0.00' }}</span>
          <span class="text-[10px] text-purple-600/80 block">Reconciled to bank deposits</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-amber-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-amber-700 uppercase tracking-wider block">Unclaimed Tax Relief</span>
          <span class="text-xl font-black text-amber-800 block">{{ giftAidSummary?.formatted_pending_gift_aid || '£0.00' }}</span>
          <span class="text-[10px] text-amber-700/80 block">{{ giftAidSummary?.pending_claim_count || 0 }} collection batch(es) pending</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-sky-200 shadow-sm space-y-1">
          <span class="text-[10px] font-extrabold text-sky-700 uppercase tracking-wider block">Net Relief Chest Position</span>
          <span class="text-xl font-black text-sky-800 block">{{ giftAidSummary?.formatted_net_relief_chest_balance || '£0.00' }}</span>
          <span class="text-[10px] text-sky-700/80 block">Relief Chest Ref: {{ giftAidSummary?.relief_chest_ref || 'E1418' }}</span>
        </div>
      </div>

      <!-- Main Filter & Transactions Table Card -->
      <div class="bg-white border border-slate-200 rounded-3xl p-6 shadow-sm space-y-6">
        
        <div class="flex flex-col lg:flex-row lg:items-center justify-between pb-4 border-b border-slate-100 gap-4">
          <div>
            <h2 class="text-base font-extrabold text-slate-900">Charity Gift Aid Transactions Listing</h2>
            <p class="text-xs text-slate-500 font-medium">Filter transactions by collection date, donor person, Gift Aid status, or search terms.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="px-3.5 py-1.5 bg-purple-50 text-purple-800 font-extrabold rounded-xl border border-purple-200/80">
              Filtered Donations: {{ formatCurrency(filteredTotalDonations) }}
            </span>
            <span class="px-3.5 py-1.5 bg-emerald-50 text-emerald-800 font-extrabold rounded-xl border border-emerald-200/80">
              Filtered Gift Aid: {{ formatCurrency(filteredTotalGiftAid) }}
            </span>
            <span class="px-3.5 py-1.5 bg-slate-100 text-slate-700 font-bold rounded-xl border border-slate-200">
              Showing {{ filteredCollections.length }} of {{ reconciledDonations?.totals?.total_count || 0 }} records
            </span>
          </div>
        </div>

        <!-- Interactive Filters Toolbar -->
        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200/80 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-slate-700">
            <span class="flex items-center gap-1.5">
              <span>🔍</span>
              <span>Filter Options</span>
            </span>
            <button
              type="button"
              @click="resetFilters"
              class="text-purple-700 hover:text-purple-900 underline font-bold cursor-pointer"
            >
              Reset All Filters
            </button>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">From Date</label>
              <input v-model="filterDateFrom" type="date" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500" />
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">To Date</label>
              <input v-model="filterDateTo" type="date" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500" />
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Donor / Person</label>
              <select v-model="filterPerson" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500 cursor-pointer">
                <option value="">All Donors &amp; Brethren</option>
                <option v-for="m in (reconciledDonations?.members || [])" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Gift Aid Status</label>
              <select v-model="filterStatus" class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500 cursor-pointer">
                <option value="all">All Statuses</option>
                <option value="reconciled">Reconciled Only</option>
                <option value="claimed">Claimed</option>
                <option value="pending">Pending / Unclaimed</option>
              </select>
            </div>

            <div>
              <label class="block text-[10px] font-extrabold text-slate-500 uppercase mb-1">Search Keywords</label>
              <input v-model="filterSearch" type="text" placeholder="Search notes, donor..." class="w-full px-3 py-1.5 bg-white border border-slate-300 rounded-xl text-xs text-slate-800 focus:ring-2 focus:ring-purple-500" />
            </div>
          </div>
        </div>

        <!-- Transactions Table -->
        <div class="overflow-x-auto rounded-2xl border border-slate-200 shadow-2xs">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="bg-slate-900 text-white text-[11px] font-extrabold uppercase tracking-wider">
              <tr>
                <th class="py-3.5 px-4">Date</th>
                <th class="py-3.5 px-4">Donor / Person</th>
                <th class="py-3.5 px-4">Collection Type</th>
                <th class="py-3.5 px-4 text-right">Donation Total</th>
                <th class="py-3.5 px-4 text-right">25% Gift Aid</th>
                <th class="py-3.5 px-4 text-center">Gift Aid Status</th>
                <th class="py-3.5 px-4">Matched Bank Credit</th>
                <th class="py-3.5 px-4">Notes</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-800">
              <tr v-for="item in filteredCollections" :key="item.id" class="hover:bg-purple-50/40 transition-colors">
                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-600 whitespace-nowrap">{{ item.created_at }}</td>
                <td class="py-3.5 px-4 font-bold text-slate-900 whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <span class="text-sm">👤</span>
                    <span>{{ item.donor_name }}</span>
                  </div>
                </td>
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <span class="px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px]">{{ item.collection_type }}</span>
                </td>
                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 whitespace-nowrap">{{ item.formatted_total }}</td>
                <td class="py-3.5 px-4 text-right font-mono font-extrabold text-purple-700 whitespace-nowrap">{{ item.formatted_gift_aid }}</td>
                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                  <span :class="['px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-flex items-center gap-1', item.gift_aid_status === 'reconciled' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : (item.gift_aid_status === 'claimed' ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-amber-100 text-amber-900 border border-amber-300')]">
                    <span v-if="item.gift_aid_status === 'reconciled'">✓ Reconciled</span>
                    <span v-else-if="item.gift_aid_status === 'claimed'">⚡ Claimed</span>
                    <span v-else>⏳ Unclaimed</span>
                  </span>
                </td>
                <td class="py-3.5 px-4 text-xs text-slate-600 max-w-xs">
                  <div v-if="item.bank_transaction" class="p-2 bg-emerald-50/70 border border-emerald-200 rounded-lg text-[11px] space-y-0.5">
                    <div class="font-extrabold text-emerald-900 flex items-center justify-between">
                      <span>Bank Credit: {{ item.bank_transaction.formatted_amount }}</span>
                      <span class="text-[9px] text-emerald-700">{{ item.bank_transaction.transaction_date }}</span>
                    </div>
                    <div class="text-[10px] text-emerald-800 truncate" :title="item.bank_transaction.raw_description">{{ item.bank_transaction.raw_description }}</div>
                  </div>
                  <span v-else class="text-slate-400 italic text-[11px]">Unlinked bank credit</span>
                </td>
                <td class="py-3.5 px-4 text-slate-500 text-[11px] max-w-xs truncate" :title="item.notes || 'No notes'">{{ item.notes || '—' }}</td>
              </tr>
              <tr v-if="filteredCollections.length === 0">
                <td colspan="8" class="py-12 text-center text-slate-400 italic">No charity gift aid transactions match the selected filter criteria.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
