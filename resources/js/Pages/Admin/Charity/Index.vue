<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import CharitySubNav from '@/Components/CharitySubNav.vue';

const props = defineProps({
  club: Object,
  target: Object,
  totalRaisedForFestival: Number,
  formattedTotalRaised: String,
  targetPercentage: Number,
  currentHonorTier: String,
  giftAidSummary: Object,
  reconciledDonations: Object,
  collections: Array,
  totalCollectionsAmount: Number,
  formattedTotalCollections: String,
  grants: Array,
  upcomingMeetings: {
    type: Array,
    default: () => [],
  },
  totalGrantsDisbursed: Number,
  formattedTotalGrants: String,
  activeMembers: Array,
  jewelHoldersCount: Number,
});

const showCollectionModal = ref(false);
const showGrantModal = ref(false);

const collectionForm = useForm({
  collection_type: 'alms_plate',
  cash_amount: '0.00',
  cheque_amount: '0.00',
  counted_by_member_id: null,
  witnessed_by_member_id: null,
  donor_member_id: null,
  donor_name: '',
  notes: '',
});

const grantForm = useForm({
  recipient_name: '',
  purpose: '',
  amount: '0.00',
  relief_chest_number: '',
  proposer_member_id: null,
  seconder_member_id: null,
  meeting_id: null,
});

const submitCollection = () => {
  collectionForm.post(route('admin.charity.collections.store', props.club.slug), {
    onSuccess: () => {
      showCollectionModal.value = false;
      collectionForm.reset();
    },
  });
};

const submitGrant = () => {
  grantForm.post(route('admin.charity.grants.store', props.club.slug), {
    onSuccess: () => {
      showGrantModal.value = false;
      grantForm.reset();
    },
  });
};

const updateGrantStatus = (grantId, status) => {
  useForm({ approval_status: status }).post(route('admin.charity.grants.update_status', { clubSlug: props.club.slug, grantId }));
};
</script>

<template>
  <Head :title="`Charity Dashboard — ${club.name}`" />

  <AdminLayout :club="club" currentTab="charity">
    <div class="max-w-7xl mx-auto space-y-6 pb-12">
      
      <!-- Top Horizontal Sub-Navigation Bar -->
      <CharitySubNav :clubSlug="club.slug" activeTab="dashboard" />

      <!-- Header Banner -->
      <div class="p-6 bg-slate-900 rounded-3xl text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <span class="p-2.5 bg-purple-500/20 text-purple-400 rounded-2xl border border-purple-500/30">🤝</span>
            <div>
              <h1 class="text-2xl font-black tracking-tight">Charity Dashboard</h1>
              <p class="text-xs text-slate-400 mt-1 font-medium">Dual-Custody Meeting Collections, Grant Voting &amp; Relief Chest Integration</p>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="showCollectionModal = true"
            class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
          >
            <span>📥</span>
            <span>Record Collection</span>
          </button>

          <button
            type="button"
            @click="showGrantModal = true"
            class="px-3.5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center gap-2 cursor-pointer"
          >
            <span>🎁</span>
            <span>Propose Grant</span>
          </button>

          <a
            :href="route('admin.accounting.giftaid.export_schedule', club.slug)"
            target="_blank"
            class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center gap-2"
          >
            <span>📄</span>
            <span>MCF Relief Chest CSV</span>
          </a>
        </div>
      </div>

      <!-- KPI Summary Cards Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-1">
          <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Raised to Date</span>
          <div class="text-2xl font-black text-slate-900">{{ formattedTotalRaised }}</div>
          <span class="text-[10px] text-slate-500 block">Collections &amp; Member Giving</span>
        </div>

        <div class="p-5 bg-amber-50/60 border border-amber-200/80 rounded-2xl shadow-sm space-y-1">
          <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Meeting Alms &amp; Raffles</span>
          <div class="text-2xl font-black text-amber-950">{{ formattedTotalCollections }}</div>
          <span class="text-[10px] text-amber-700 block">Dual-custody verified takings</span>
        </div>

        <div class="p-5 bg-purple-50/60 border border-purple-200/80 rounded-2xl shadow-sm space-y-1">
          <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Grants Disbursed</span>
          <div class="text-2xl font-black text-purple-950">{{ formattedTotalGrants }}</div>
          <span class="text-[10px] text-purple-700 block">Approved relief grants paid</span>
        </div>

        <div class="p-5 bg-indigo-50/60 border border-indigo-200/80 rounded-2xl shadow-sm space-y-1">
          <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-800">Festival Jewel Holders</span>
          <div class="text-2xl font-black text-indigo-950">{{ jewelHoldersCount }}</div>
          <span class="text-[10px] text-indigo-700 block">Brethren qualifying for Festival Jewel</span>
        </div>
      </div>

      <!-- Automated Gift Aid Reconciliation Position Banner -->
      <div class="p-5 bg-gradient-to-r from-amber-500/10 via-purple-500/10 to-indigo-500/10 border border-amber-300/80 rounded-3xl shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="p-3 bg-amber-500/20 text-amber-800 rounded-2xl text-xl">🏛️</span>
          <div>
            <h3 class="font-black text-slate-900 text-sm">Automated Gift Aid &amp; Relief Chest Reconciliation Position</h3>
            <p class="text-xs text-slate-600 mt-0.5">
              Pending Gift Aid 25% Tax Reclaim: <strong class="text-amber-900 font-black">{{ giftAidSummary?.formatted_pending_gift_aid || '£0.00' }}</strong>
              ({{ giftAidSummary?.pending_claim_count || 0 }} collection batches pending)
              • Net Relief Chest Balance: <strong class="text-indigo-900 font-black">{{ giftAidSummary?.formatted_net_relief_chest_balance || '£0.00' }}</strong>
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <Link
            :href="route('admin.charity.giftaid.transactions_page', club.slug)"
            class="px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5"
          >
            <span>📜 View Gift Aid Transactions Ledger</span>
          </Link>
          <Link
            :href="route('admin.accounting.index', { clubSlug: club.slug, tab: 'reconciliation' })"
            class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center gap-1.5"
          >
            <span>⚡ Open Reconciliation Workspace</span>
          </Link>
        </div>
      </div>

      <!-- Two-Column Layout: Recent Collections vs Grants Disbursed -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Column 1: Dual-Custody Collections -->
        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">📥</span>
              <h3 class="font-black text-slate-900 text-sm">Recent Dual-Custody Meeting Collections</h3>
            </div>
            <button type="button" @click="showCollectionModal = true" class="text-xs text-purple-700 font-bold hover:underline">
              + New Collection
            </button>
          </div>

          <div class="space-y-3">
            <div
              v-for="col in collections"
              :key="col.id"
              class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center justify-between gap-3 text-xs"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-black text-slate-900">{{ col.collection_type }}</span>
                  <span class="text-[10px] text-slate-400 font-mono">{{ col.created_at }}</span>
                </div>
                <div class="text-[11px] text-slate-500">
                  <span>Counted by: <strong>{{ col.counted_by }}</strong></span> • <span>Witnessed by: <strong>{{ col.witnessed_by }}</strong></span>
                </div>
              </div>
              <div class="text-right whitespace-nowrap">
                <div class="font-black text-amber-950 text-sm">{{ col.formatted_total }}</div>
                <span class="text-[10px] text-slate-400 block font-mono">Cash: £{{ col.cash_amount }} | Cheque: £{{ col.cheque_amount }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Column 2: Charity Grants -->
        <div class="bg-white border border-slate-200/80 rounded-3xl p-6 shadow-sm space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
              <span class="text-lg">🎁</span>
              <h3 class="font-black text-slate-900 text-sm">Charity Grants &amp; Relief Voting</h3>
            </div>
            <button type="button" @click="showGrantModal = true" class="text-xs text-purple-700 font-bold hover:underline">
              + Propose Grant
            </button>
          </div>

          <div class="space-y-3">
            <div
              v-for="grant in grants"
              :key="grant.id"
              class="p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl flex items-center justify-between gap-3 text-xs"
            >
              <div class="space-y-1">
                <div class="flex items-center gap-2">
                  <span class="font-black text-slate-900">{{ grant.recipient_name }}</span>
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-purple-800">
                    {{ grant.status_label }}
                  </span>
                  <span v-if="grant.meeting_title" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-100 text-amber-900 border border-amber-300/70">
                    📜 {{ grant.meeting_title }}
                  </span>
                </div>
                <p class="text-[11px] text-slate-500 line-clamp-1">{{ grant.purpose }}</p>
              </div>
              <div class="text-right whitespace-nowrap space-y-1">
                <div class="font-black text-purple-950 text-sm">{{ grant.formatted_amount }}</div>
                <button
                  v-if="grant.approval_status === 'proposed'"
                  type="button"
                  @click="updateGrantStatus(grant.id, 'committee_approved')"
                  class="px-2 py-0.5 bg-blue-600 text-white font-bold text-[10px] rounded cursor-pointer"
                >
                  Approve Committee
                </button>
                <button
                  v-else-if="grant.approval_status === 'committee_approved'"
                  type="button"
                  @click="updateGrantStatus(grant.id, 'lodge_voted')"
                  class="px-2 py-0.5 bg-amber-600 text-white font-bold text-[10px] rounded cursor-pointer"
                >
                  Lodge Voted
                </button>
                <button
                  v-else-if="grant.approval_status === 'lodge_voted'"
                  type="button"
                  @click="updateGrantStatus(grant.id, 'disbursed')"
                  class="px-2 py-0.5 bg-emerald-600 text-white font-bold text-[10px] rounded cursor-pointer"
                >
                  Disburse Payment
                </button>
                <span v-else class="text-[10px] text-emerald-700 font-bold block">Disbursed ✓</span>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Record Collection Modal -->
      <div v-if="showCollectionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <span class="text-xl">📥</span>
              <h3 class="font-black text-slate-900 text-base">Record Meeting Collection</h3>
            </div>
            <button type="button" @click="showCollectionModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <form @submit.prevent="submitCollection" class="space-y-4 text-xs">
            <div>
              <label class="font-bold text-slate-700 block mb-1">Collection Type *</label>
              <select v-model="collectionForm.collection_type" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                <option value="alms_plate">Alms Plate Collection</option>
                <option value="raffle">Meeting Charity Raffle</option>
                <option value="ladies_night">Festive Board Alms</option>
                <option value="individual_envelope">Gift Aid Envelope</option>
                <option value="festival_donation">Festival Direct Donation</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Cash Amount (£) *</label>
                <input type="number" step="0.01" v-model="collectionForm.cash_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500" required />
              </div>
              <div>
                <label class="font-bold text-slate-700 block mb-1">Cheque Amount (£)</label>
                <input type="number" step="0.01" v-model="collectionForm.cheque_amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500" />
              </div>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Counter (e.g. Steward) *</label>
              <select v-model="collectionForm.counted_by_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                <option :value="null">-- Select Counter --</option>
                <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Witness *</label>
              <select v-model="collectionForm.witnessed_by_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                <option :value="null">-- Select Witness --</option>
                <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Notes / Donor Details</label>
              <textarea v-model="collectionForm.notes" rows="2" placeholder="e.g. Gift Aid Envelope #12 Bro. Smith..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500"></textarea>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
              <button type="button" @click="showCollectionModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
              <button type="submit" class="px-5 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-black rounded-xl shadow-md transition">Record Collection</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Propose Grant Modal -->
      <div v-if="showGrantModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-sm">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 space-y-5 border border-slate-100">
          <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
              <span class="text-xl">🎁</span>
              <h3 class="font-black text-slate-900 text-base">Propose Charity Grant</h3>
            </div>
            <button type="button" @click="showGrantModal = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
          </div>

          <form @submit.prevent="submitGrant" class="space-y-4 text-xs">
            <div>
              <label class="font-bold text-slate-700 block mb-1">Recipient Name / Cause *</label>
              <input type="text" v-model="grantForm.recipient_name" placeholder="e.g. Local Children's Hospice" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500" required />
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Grant Amount (£) *</label>
              <input type="number" step="0.01" v-model="grantForm.amount" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500" required />
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Purpose / Details *</label>
              <textarea v-model="grantForm.purpose" rows="2" placeholder="Describe the cause and committee recommendation..." class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500" required></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="font-bold text-slate-700 block mb-1">Proposer</label>
                <select v-model="grantForm.proposer_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                  <option :value="null">-- Select Proposer --</option>
                  <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </div>
              <div>
                <label class="font-bold text-slate-700 block mb-1">Seconder</label>
                <select v-model="grantForm.seconder_member_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                  <option :value="null">-- Select Seconder --</option>
                  <option v-for="m in activeMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="font-bold text-slate-700 block mb-1">Attach to Meeting / Summons Agenda (Optional)</label>
              <select v-model="grantForm.meeting_id" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-purple-500">
                <option :value="null">-- Do Not Attach to Summons --</option>
                <option v-for="m in upcomingMeetings" :key="m.id" :value="m.id">{{ m.label }}</option>
              </select>
              <p class="text-[10px] text-slate-400 mt-1 font-medium">Attaching will automatically insert a formal Charity Ballot Proposition item on the Meeting Summons.</p>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
              <button type="button" @click="showGrantModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition">Cancel</button>
              <button type="submit" class="px-5 py-2 bg-purple-700 hover:bg-purple-800 text-white font-black rounded-xl shadow-md transition">Save Grant Proposal</button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
