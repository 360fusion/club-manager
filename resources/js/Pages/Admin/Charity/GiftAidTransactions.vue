<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CharitySubNav from '@/Components/CharitySubNav.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

import { formatMoney } from '@/Utils/currency';
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

const resetFilters = () => {
  filterDateFrom.value = '';
  filterDateTo.value = '';
  filterPerson.value = '';
  filterStatus.value = 'all';
  filterSearch.value = '';
};

const primaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-blue-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950';
const secondaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950';
</script>

<template>
  <Head :title="`Gift Aid transactions — ${club.name}`" />

  <AdminLayout :club="club" title="Gift Aid transactions" active-tab="charity">
    <div class="max-w-7xl mx-auto space-y-6 pb-12">
      <CharitySubNav :clubSlug="club.slug" activeTab="gift-aid" />

      <PageHeader title="Gift Aid transactions" description="An audit ledger of meeting alms, Festival donations, 25% Gift Aid reclaims and the bank credits they were matched to.">
        <template #actions>
          <div class="flex flex-wrap items-center gap-2">
            <Link
              :href="route('admin.accounting.giftaid.reconcile_auto', club.slug)"
              method="post"
              as="button"
              :class="primaryButton"
            >
              <UiIcon name="lightning" />
              Auto-match deposits
            </Link>

            <a
              :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
              target="_blank"
              :class="secondaryButton"
            >
              <UiIcon name="download" />
              Export HMRC CSV
            </a>
          </div>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard label="Eligible donations" :value="giftAidSummary?.formatted_total_eligible || ($cs + '0.00')" caption="Meeting collections and envelope gifts" />
        <StatCard label="Gift Aid reclaimed (25%)" :value="giftAidSummary?.formatted_gift_aid_reclaimed || ($cs + '0.00')" caption="Reconciled to bank deposits" />
        <StatCard label="Unclaimed tax relief" :value="giftAidSummary?.formatted_pending_gift_aid || ($cs + '0.00')" :caption="`${giftAidSummary?.pending_claim_count || 0} collection batch(es) pending`" />
        <StatCard label="Net Relief Chest position" :value="giftAidSummary?.formatted_net_relief_chest_balance || ($cs + '0.00')" :caption="`Relief Chest ref: ${giftAidSummary?.relief_chest_ref || 'E1418'}`" />
      </div>

      <Card>
        <template #header>
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
              <h2 class="text-base font-semibold text-slate-900 dark:text-white">Gift Aid transactions</h2>
              <p class="text-sm text-slate-500 dark:text-slate-400">Filter by collection date, donor, Gift Aid status or search terms.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
              <Badge>Filtered donations: {{ formatMoney(filteredTotalDonations) }}</Badge>
              <Badge>Filtered Gift Aid: {{ formatMoney(filteredTotalGiftAid) }}</Badge>
              <Badge>Showing {{ filteredCollections.length }} of {{ reconciledDonations?.totals?.total_count || 0 }} records</Badge>
            </div>
          </div>
        </template>

        <div class="space-y-6">
          <div class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200 dark:border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-1.5 text-sm font-medium text-slate-700 dark:text-slate-200">
                <UiIcon name="filter" />
                Filters
              </span>
              <Button variant="ghost" size="sm" @click="resetFilters">Reset all filters</Button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
              <Input v-model="filterDateFrom" type="date" label="From date" />

              <Input v-model="filterDateTo" type="date" label="To date" />

              <Select
                v-model="filterPerson"
                label="Donor"
                :options="[{ value: '', label: 'All donors and brethren' }, ...(reconciledDonations?.members || []).map(m => ({ value: m.id, label: m.name }))]"
              />

              <Select
                v-model="filterStatus"
                label="Gift Aid status"
                :options="[
                  { value: 'all', label: 'All statuses' },
                  { value: 'reconciled', label: 'Reconciled only' },
                  { value: 'claimed', label: 'Claimed' },
                  { value: 'pending', label: 'Pending or unclaimed' },
                ]"
              />

              <Input v-model="filterSearch" label="Search" placeholder="Search notes, donor…" />
            </div>
          </div>

          <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="py-3 px-4 font-medium">Date</th>
                  <th scope="col" class="py-3 px-4 font-medium">Donor</th>
                  <th scope="col" class="py-3 px-4 font-medium">Collection type</th>
                  <th scope="col" class="py-3 px-4 font-medium text-right">Donation total</th>
                  <th scope="col" class="py-3 px-4 font-medium text-right">Gift Aid (25%)</th>
                  <th scope="col" class="py-3 px-4 font-medium text-center">Gift Aid status</th>
                  <th scope="col" class="py-3 px-4 font-medium">Matched bank credit</th>
                  <th scope="col" class="py-3 px-4 font-medium">Notes</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="item in filteredCollections" :key="item.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                  <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap tabular-nums">{{ item.created_at }}</td>
                  <td class="py-3 px-4 font-medium text-slate-900 dark:text-white whitespace-nowrap">{{ item.donor_name }}</td>
                  <td class="py-3 px-4 whitespace-nowrap">
                    <Badge>{{ item.collection_type }}</Badge>
                  </td>
                  <td class="py-3 px-4 text-right font-medium text-slate-900 dark:text-white whitespace-nowrap tabular-nums">{{ item.formatted_total }}</td>
                  <td class="py-3 px-4 text-right font-medium text-blue-700 dark:text-blue-300 whitespace-nowrap tabular-nums">{{ item.formatted_gift_aid }}</td>
                  <td class="py-3 px-4 text-center whitespace-nowrap">
                    <Badge v-if="item.gift_aid_status === 'reconciled'" variant="success">
                      <UiIcon name="check" :size="12" />
                      Reconciled
                    </Badge>
                    <Badge v-else-if="item.gift_aid_status === 'claimed'" variant="info">Claimed</Badge>
                    <Badge v-else variant="warning">Unclaimed</Badge>
                  </td>
                  <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-300 max-w-xs">
                    <div v-if="item.bank_transaction" class="p-2 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 rounded-xl text-[11px] space-y-0.5">
                      <div class="font-medium text-emerald-900 dark:text-emerald-200 flex items-center justify-between gap-2">
                        <span>Bank credit: {{ item.bank_transaction.formatted_amount }}</span>
                        <span class="text-[11px] text-emerald-700 dark:text-emerald-300 tabular-nums">{{ item.bank_transaction.transaction_date }}</span>
                      </div>
                      <div class="text-emerald-800 dark:text-emerald-200 truncate" :title="item.bank_transaction.raw_description">{{ item.bank_transaction.raw_description }}</div>
                    </div>
                    <span v-else class="text-slate-500 dark:text-slate-400">Not matched to a bank credit</span>
                  </td>
                  <td class="py-3 px-4 text-slate-500 dark:text-slate-400 text-xs max-w-xs truncate" :title="item.notes || 'No notes'">{{ item.notes || '—' }}</td>
                </tr>
                <tr v-if="filteredCollections.length === 0">
                  <td colspan="8" class="py-12 text-center text-sm text-slate-500 dark:text-slate-400">No Gift Aid transactions match these filters.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
