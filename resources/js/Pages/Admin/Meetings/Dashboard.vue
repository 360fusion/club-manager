<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  meeting: Object,
  rsvps: Array,
  visitorsList: Array,
  allClubUsers: Array,
  stats: Object,
});

const activeTab = ref('all');
const showApologiesModal = ref(false);
const showManualRsvpModal = ref(false);
const selectedUserId = ref(null);

const manualRsvpForm = useForm({
  user_id: '',
  attendance_status: 'attending_dining',
  dietary_requirements: '',
  apology_reason: '',
  payment_status: 'unpaid',
  guests: [],
});

const openManualRsvpModal = (userId = null) => {
  if (userId) {
    selectedUserId.value = userId;
    manualRsvpForm.user_id = userId;
    const existingRsvp = (props.rsvps || []).find(r => r.user_id === userId);
    if (existingRsvp) {
      manualRsvpForm.attendance_status = existingRsvp.attendance_status || 'attending_dining';
      manualRsvpForm.dietary_requirements = existingRsvp.dietary_requirements || '';
      manualRsvpForm.apology_reason = existingRsvp.apology_reason || '';
      manualRsvpForm.payment_status = existingRsvp.payment_status || 'unpaid';
      manualRsvpForm.guests = (existingRsvp.guests || []).map(g => ({
        guest_name: g.guest_name,
        dietary_requirements: g.dietary_requirements || '',
        attending_dining: g.attending_dining ?? true,
      }));
    } else {
      manualRsvpForm.attendance_status = 'attending_dining';
      manualRsvpForm.dietary_requirements = '';
      manualRsvpForm.apology_reason = '';
      manualRsvpForm.payment_status = 'unpaid';
      manualRsvpForm.guests = [];
    }
  } else {
    selectedUserId.value = null;
    manualRsvpForm.reset();
    manualRsvpForm.attendance_status = 'attending_dining';
    manualRsvpForm.payment_status = 'unpaid';
    manualRsvpForm.user_id = (props.allClubUsers || [])[0]?.id || '';
    manualRsvpForm.guests = [];
  }
  showManualRsvpModal.value = true;
};

const setPaymentStatus = (userId, status) => {
  router.post(route('admin.meetings.rsvp.payment_status', { clubSlug: props.club.slug, id: props.meeting.id }), {
    user_id: userId,
    payment_status: status,
  }, {
    preserveScroll: true,
  });
};

const addGuestRow = () => {
  manualRsvpForm.guests.push({
    guest_name: '',
    dietary_requirements: '',
    attending_dining: true,
  });
};

const removeGuestRow = (index) => {
  manualRsvpForm.guests.splice(index, 1);
};

const submitManualRsvp = () => {
  manualRsvpForm.post(route('admin.meetings.rsvp.update', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showManualRsvpModal.value = false;
    },
  });
};

const memberRsvps = computed(() => (props.rsvps || []).filter(r => !r.is_visitor));

const filteredRsvps = computed(() => {
  if (activeTab.value === 'all') return memberRsvps.value;
  return memberRsvps.value.filter(r => r.attendance_status === activeTab.value);
});

const publishSummons = () => {
  if (confirm(`Publish summons and issue passwordless RSVP tokens to all ${props.stats.total_members} members?`)) {
    router.post(route('admin.meetings.publish', { clubSlug: props.club.slug, id: props.meeting.id }));
  }
};

const apologiesFormattedText = computed(() => {
  const list = memberRsvps.value.filter(r => r.attendance_status === 'apologies');
  if (!list.length) return 'No apologies have been received for this meeting.';
  
  const names = list.map(r => r.user ? r.user.name : 'Member').join(', ');
  return `APOLOGIES FOR ABSENCE:\nApologies were received and recorded from: ${names}.`;
});

const formattedMeetingDate = computed(() => {
  if (!props.meeting?.meeting_date) return '';
  const parts = String(props.meeting.meeting_date).split('T')[0].split('-');
  if (parts.length === 3) {
    const d = new Date(parts[0], parts[1] - 1, parts[2]);
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
  }
  return props.meeting.meeting_date;
});
const showFinancialReturnModal = ref(false);

const financialReturnForm = useForm({
  return_date: props.meeting?.meeting_date ? String(props.meeting.meeting_date).split('T')[0] : '',
  dining_fee_per_head: props.meeting?.dining_cost_member || 35.00,
  paid_diners_count: props.stats?.attending_dining || 0,
  waived_diners_count: 0,
  waived_reason: 'Official Guests / Visiting Speakers',
  kitchen_cost_per_head: 28.00,
  kitchen_vendor_name: 'Masonic Hall Catering Ltd',
  raffle_amount: 0.00,
  alms_amount: 0.00,
  donations_amount: 0.00,
  bequest_amount: 0.00,
  notes: '',
});

const openFinancialReturnModal = () => {
  const existing = props.meeting?.financial_return;
  if (existing) {
    financialReturnForm.return_date = existing.return_date ? String(existing.return_date).split('T')[0] : '';
    financialReturnForm.dining_fee_per_head = Number(existing.dining_fee_per_head) || 35.00;
    financialReturnForm.paid_diners_count = Number(existing.paid_diners_count) || 0;
    financialReturnForm.waived_diners_count = Number(existing.waived_diners_count) || 0;
    financialReturnForm.waived_reason = existing.waived_reason || '';
    financialReturnForm.kitchen_cost_per_head = Number(existing.kitchen_cost_per_head) || 28.00;
    financialReturnForm.kitchen_vendor_name = existing.kitchen_vendor_name || '';
    financialReturnForm.raffle_amount = Number(existing.raffle_amount) || 0.00;
    financialReturnForm.alms_amount = Number(existing.alms_amount) || 0.00;
    financialReturnForm.donations_amount = Number(existing.donations_amount) || 0.00;
    financialReturnForm.bequest_amount = Number(existing.bequest_amount) || 0.00;
    financialReturnForm.notes = existing.notes || '';
  } else {
    financialReturnForm.paid_diners_count = props.stats?.attending_dining || 0;
  }
  showFinancialReturnModal.value = true;
};

const calcTotalMeals = computed(() => Number(financialReturnForm.paid_diners_count || 0) + Number(financialReturnForm.waived_diners_count || 0));
const calcDiningRevenue = computed(() => Number(financialReturnForm.paid_diners_count || 0) * Number(financialReturnForm.dining_fee_per_head || 0));
const calcKitchenBill = computed(() => calcTotalMeals.value * Number(financialReturnForm.kitchen_cost_per_head || 0));
const calcDiningSurplus = computed(() => calcDiningRevenue.value - calcKitchenBill.value);
const calcTotalCharity = computed(() => 
  Number(financialReturnForm.raffle_amount || 0) + 
  Number(financialReturnForm.alms_amount || 0) + 
  Number(financialReturnForm.donations_amount || 0) + 
  Number(financialReturnForm.bequest_amount || 0)
);
const calcBankDeposit = computed(() => calcDiningRevenue.value + calcTotalCharity.value);

const submitFinancialReturn = () => {
  financialReturnForm.post(route('admin.meetings.financial_return.store', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showFinancialReturnModal.value = false;
    },
  });
};
</script>

<template>
  <AdminLayout title="Secretary Meeting Dashboard" :club="club" active-tab="meetings">
    
    <div class="space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">
            {{ meeting.title && !meeting.title.includes('Regular Meeting No.') ? meeting.title : 'Meeting - ' + formattedMeetingDate }} at {{ meeting.starts_at ? meeting.starts_at.substring(0, 5) : '18:30' }}
          </h2>
          <div class="flex items-center gap-3 text-xs mt-1.5 flex-wrap">
            <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', meeting.status === 'published' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200']">
              {{ meeting.status }}
            </span>
            <span class="text-slate-500 font-medium">📅 {{ formattedMeetingDate }} • 📍 {{ meeting.venue }} • Rehearsal: {{ meeting.rehearsal_starts_at ? meeting.rehearsal_starts_at.substring(0, 5) : '-' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
          <div class="relative group">
            <button @click="openFinancialReturnModal" title="Enter Financial Return & Dining Calculator" aria-label="Enter Financial Return & Dining Calculator" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1">
              💰 Financial Return
            </button>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Post Dining Fees, Charity Collections & Kitchen Bill
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>

          <div class="relative group">
            <a :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" title="Download or Print Summons PDF" aria-label="Download or Print Summons PDF" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition-all flex items-center gap-1">
              🖨️ PDF
            </a>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Download / Print PDF
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>

          <div class="relative group">
            <Link :href="route('admin.meetings.edit', { clubSlug: club.slug, id: meeting.id })" title="Edit Summons Details" aria-label="Edit Summons Details" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all flex items-center gap-1">
              📜 Edit Summons
            </Link>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Edit Summons Details
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>

          <div class="relative group">
            <button @click="openManualRsvpModal(null)" title="Record or Update Member RSVP" aria-label="Record or Update Member RSVP" class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all cursor-pointer flex items-center gap-1">
              ✍️ Record RSVP
            </button>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Record / Edit Member RSVP
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>

          <div class="relative group">
            <button @click="showApologiesModal = true" title="Copy Apologies for Minutes" aria-label="Copy Apologies for Minutes" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all cursor-pointer flex items-center gap-1">
              📋 Copy Minutes Apologies
            </button>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Copy Minutes Apologies
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>

          <div v-if="meeting.status !== 'published'" class="relative group">
            <button @click="publishSummons" title="Publish & Dispatch Passwordless RSVPs" aria-label="Publish & Dispatch Passwordless RSVPs" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1">
              ✉️ Publish & Dispatch Summons
            </button>
            <div class="absolute bottom-full mb-2 left-1/2 -translate-x-1/2 opacity-0 group-hover:opacity-100 pointer-events-none transition-all duration-150 transform group-hover:-translate-y-1 z-30 whitespace-nowrap bg-slate-900 text-white text-[11px] font-semibold py-1 px-2.5 rounded-lg shadow-xl border border-slate-800">
              Publish & Dispatch Summons
              <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1 border-4 border-transparent border-t-slate-900"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Live KPI Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Attending</div>
          <div class="text-2xl font-black text-indigo-600">{{ stats.total_attending ?? (stats.attending_dining + stats.attending_meeting_only + stats.visiting_attending + stats.guest_meals) }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Meeting attendees</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Dining</div>
          <div class="text-2xl font-black text-emerald-600">{{ stats.total_dining ?? stats.total_caterer_headcount }}</div>
          <div class="text-[11px] text-slate-500 font-medium">{{ (stats.dining_members_and_visitors ?? (stats.attending_dining + stats.visiting_dining)) }} Dining + {{ stats.guest_meals }} Guests</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Meeting Only</div>
          <div class="text-2xl font-black text-sky-600">{{ stats.attending_meeting_only }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Attending without dining</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Apologies Received</div>
          <div class="text-2xl font-black text-rose-600">{{ stats.apologies }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Formally recorded absence</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Visitors Attending</div>
          <div class="text-2xl font-black text-purple-600">{{ stats.visiting_attending }}</div>
          <div class="text-[11px] text-slate-500 font-medium">Visiting Brethren & Guests</div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Awaiting RSVP</div>
          <div class="text-2xl font-black text-amber-600">{{ stats.awaiting }}</div>
          <div class="text-[11px] text-slate-500 font-medium">{{ meeting.status === 'published' ? 'Emailed members pending' : 'Invites not sent yet' }}</div>
        </div>
      </div>

      <!-- Filter Tabs & RSVP Grid -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex items-center gap-2 border-b border-slate-100 pb-3 overflow-x-auto">
          <button @click="activeTab = 'all'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'all' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            All Responses ({{ memberRsvps.length }})
          </button>
          <button @click="activeTab = 'attending_dining'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'attending_dining' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Attending Dining ({{ stats.attending_dining }})
          </button>
          <button @click="activeTab = 'attending_meeting_only'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'attending_meeting_only' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Meeting Only ({{ stats.attending_meeting_only }})
          </button>
          <button @click="activeTab = 'apologies'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'apologies' ? 'bg-rose-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Apologies ({{ stats.apologies }})
          </button>
          <button @click="activeTab = 'visitors'" :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all', activeTab === 'visitors' ? 'bg-purple-600 text-white' : 'text-slate-600 hover:bg-slate-100']">
            Visitors ({{ stats.visiting_count || 0 }})
          </button>
        </div>

        <div class="overflow-x-auto">
          <!-- Invited Visitors Roster Table -->
          <table v-if="activeTab === 'visitors'" class="w-full text-xs text-left text-slate-700">
            <thead class="bg-purple-50/80 text-purple-900 uppercase font-bold text-[10px] tracking-wider">
              <tr>
                <th class="p-3">Visitor Name & Rank</th>
                <th class="p-3">Home Lodge / Club</th>
                <th class="p-3">Email Address</th>
                <th class="p-3">Summons & Attendance Status</th>
                <th class="p-3">Dietary Notes</th>
                <th class="p-3">Payment Status & Ref</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="v in visitorsList" :key="v.id" class="hover:bg-purple-50/30 transition-all">
                <td class="p-3 font-bold text-slate-900">
                  <span v-if="v.rank" class="text-slate-500 font-normal mr-1">{{ v.rank }}</span>
                  {{ v.name }}
                </td>
                <td class="p-3 font-semibold text-purple-900">
                  🏛️ {{ v.home_club_info || 'Visitor' }}
                </td>
                <td class="p-3 text-slate-600 font-mono text-[11px]">{{ v.email }}</td>
                <td class="p-3">
                  <span v-if="v.attendance_status === 'attending_dining'" class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    🟢 Attending (Dining)
                  </span>
                  <span v-else-if="v.attendance_status === 'attending_meeting_only'" class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-sky-50 text-sky-700 border border-sky-200">
                    🔵 Attending (Meeting Only)
                  </span>
                  <span v-else-if="v.attendance_status === 'apologies'" class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                    🔴 Apologies Received
                  </span>
                  <span v-else-if="v.summons_sent" class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200">
                    ✉️ Summons Sent (Awaiting Response)
                  </span>
                  <span v-else class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                    Registered Visitor
                  </span>
                </td>
                <td class="p-3 text-slate-600">{{ v.dietary_notes || 'Standard' }}</td>
                <td class="p-3">
                  <div v-if="v.attendance_status === 'attending_dining'">
                    <button 
                      @click="setPaymentStatus(v.id, v.payment_status === 'paid' ? 'unpaid' : 'paid')"
                      :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border cursor-pointer transition-all flex items-center gap-1 w-fit', 
                        v.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' :
                        v.payment_status === 'waived' ? 'bg-purple-50 text-purple-700 border-purple-300 hover:bg-purple-100' :
                        v.payment_status === 'refunded' ? 'bg-rose-50 text-rose-700 border-rose-300 hover:bg-rose-100' :
                        'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100']"
                      :title="'Click to toggle paid/unpaid status'"
                    >
                      <span v-if="v.payment_status === 'paid'">✅ Paid</span>
                      <span v-else-if="v.payment_status === 'waived'">🎁 Waived</span>
                      <span v-else-if="v.payment_status === 'refunded'">↩️ Refunded</span>
                      <span v-else>💳 Unpaid</span>
                    </button>
                    <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ v.payment_reference || '-' }}</div>
                  </div>
                  <div v-else class="text-slate-400 text-[11px] italic">
                    No Payment Due
                  </div>
                </td>
                <td class="p-3 text-right">
                  <button @click="openManualRsvpModal(v.id)" class="px-2.5 py-1 text-[11px] font-bold text-purple-700 bg-purple-50 hover:bg-purple-100 rounded-lg border border-purple-200 transition-all cursor-pointer">
                    ✏️ Edit
                  </button>
                </td>
              </tr>
              <tr v-if="!visitorsList?.length">
                <td colspan="7" class="p-6 text-center text-slate-400">No visitors have registered for this club yet.</td>
              </tr>
            </tbody>
          </table>

          <!-- Standard Responses Table -->
          <table v-else class="w-full text-xs text-left text-slate-700">
            <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider">
              <tr>
                <th class="p-3">Member Name</th>
                <th class="p-3">Attendance</th>
                <th class="p-3">Dietary Requirements</th>
                <th class="p-3">Registered Visitors / Guests</th>
                <th class="p-3">Payment Status & Ref</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="r in filteredRsvps" :key="r.id" class="hover:bg-slate-50/80 transition-all">
                <td class="p-3 font-bold text-slate-900">
                  {{ r.user ? r.user.name : 'Member' }}
                </td>
                <td class="p-3">
                  <span :class="['px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border', r.attendance_status === 'attending_dining' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : r.attendance_status === 'apologies' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-sky-50 text-sky-700 border-sky-200']">
                    {{ r.attendance_status ? r.attendance_status.replace('_', ' ') : 'awaiting' }}
                  </span>
                </td>
                <td class="p-3 text-slate-600">{{ r.dietary_requirements || 'Standard' }}</td>
                <td class="p-3">
                  <div v-if="r.guests?.length" class="space-y-1">
                    <div v-for="g in r.guests" :key="g.id" class="text-[11px] font-medium text-slate-800">
                      👤 {{ g.guest_name }} <span class="text-slate-400">({{ g.home_club_lodge || 'Visitor' }})</span>
                    </div>
                  </div>
                  <span v-else class="text-slate-400">-</span>
                </td>
                <td class="p-3">
                  <div v-if="r.attendance_status === 'attending_dining' || r.guests?.some(g => g.attending_dining)">
                    <button 
                      @click="setPaymentStatus(r.user_id, r.payment_status === 'paid' ? 'unpaid' : 'paid')"
                      :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border cursor-pointer transition-all flex items-center gap-1 w-fit', 
                        r.payment_status === 'paid' ? 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' :
                        r.payment_status === 'waived' ? 'bg-purple-50 text-purple-700 border-purple-300 hover:bg-purple-100' :
                        r.payment_status === 'refunded' ? 'bg-rose-50 text-rose-700 border-rose-300 hover:bg-rose-100' :
                        'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100']"
                      :title="'Click to toggle paid/unpaid status'"
                    >
                      <span v-if="r.payment_status === 'paid'">✅ Paid</span>
                      <span v-else-if="r.payment_status === 'waived'">🎁 Waived</span>
                      <span v-else-if="r.payment_status === 'refunded'">↩️ Refunded</span>
                      <span v-else>💳 Unpaid</span>
                    </button>
                    <div class="font-mono text-[10px] text-slate-400 mt-0.5">{{ r.payment_reference || '-' }}</div>
                  </div>
                  <div v-else class="text-slate-400 text-[11px] italic">
                    No Payment Due
                  </div>
                </td>
                <td class="p-3 text-right">
                  <button @click="openManualRsvpModal(r.user_id)" class="px-2.5 py-1 text-[11px] font-bold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition-all cursor-pointer">
                    ✏️ Edit
                  </button>
                </td>
              </tr>
              <tr v-if="!filteredRsvps?.length">
                <td colspan="6" class="p-6 text-center text-slate-400">No member responses in this filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Caterer Breakdown Drawer -->
      <div v-if="stats.dietary_constraints?.length" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 space-y-3">
        <h3 class="text-sm font-bold text-amber-900 flex items-center gap-2">
          <span>🍽️ Itemized Caterer Dietary Breakdown</span>
          <span class="px-2 py-0.5 bg-amber-200/80 text-amber-800 text-[10px] font-bold rounded-full">{{ stats.dietary_constraints.length }} Requests</span>
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <div v-for="(d, idx) in stats.dietary_constraints" :key="idx" class="bg-white p-3 rounded-xl border border-amber-200 text-xs">
            <div class="font-bold text-slate-900">{{ d.person }}</div>
            <div class="text-amber-700 font-semibold mt-0.5">⚠️ {{ d.requirement }}</div>
          </div>
        </div>
      </div>

      <!-- Apologies Modal for Minutes -->
      <div v-if="showApologiesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">Formatted Minutes Apologies Export</h3>
            <button @click="showApologiesModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <textarea :value="apologiesFormattedText" readonly rows="5" class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-800 focus:outline-none"></textarea>

          <div class="flex justify-end gap-3 pt-2">
            <button @click="showApologiesModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Close</button>
            <button @click="copyApologiesText" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md">
              📋 Copy to Clipboard
            </button>
          </div>
        </div>
      </div>

      <!-- Manual Admin RSVP Modal -->
      <div v-if="showManualRsvpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">
              {{ selectedUserId ? '✏️ Edit Attendance & Dining RSVP' : '✍️ Record Manual RSVP' }}
            </h3>
            <button @click="showManualRsvpModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <form @submit.prevent="submitManualRsvp" class="space-y-4">
            <!-- Member / Visitor Select -->
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Member / Visitor</label>
              <select v-model="manualRsvpForm.user_id" :disabled="!!selectedUserId" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50 p-2.5 font-medium">
                <option value="" disabled>-- Select a member or visitor --</option>
                <option v-for="u in allClubUsers" :key="u.id" :value="u.id">
                  {{ u.name }} ({{ u.email }}) {{ u.is_visitor ? '• Visitor' : '' }}
                </option>
              </select>
              <div v-if="manualRsvpForm.errors.user_id" class="text-rose-600 text-[11px] mt-1">{{ manualRsvpForm.errors.user_id }}</div>
            </div>

            <!-- Attendance Status -->
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Attendance Status</label>
              <div class="grid grid-cols-3 gap-2">
                <label :class="['flex items-center justify-center p-2.5 rounded-xl border text-xs font-bold cursor-pointer transition-all', manualRsvpForm.attendance_status === 'attending_dining' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.attendance_status" value="attending_dining" class="sr-only" />
                  🟢 Dining & Meeting
                </label>
                <label :class="['flex items-center justify-center p-2.5 rounded-xl border text-xs font-bold cursor-pointer transition-all', manualRsvpForm.attendance_status === 'attending_meeting_only' ? 'bg-sky-50 border-sky-500 text-sky-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.attendance_status" value="attending_meeting_only" class="sr-only" />
                  🔵 Meeting Only
                </label>
                <label :class="['flex items-center justify-center p-2.5 rounded-xl border text-xs font-bold cursor-pointer transition-all', manualRsvpForm.attendance_status === 'apologies' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.attendance_status" value="apologies" class="sr-only" />
                  🔴 Apologies
                </label>
              </div>
            </div>

            <!-- Payment Status (Only shown for Dining) -->
            <div v-if="manualRsvpForm.attendance_status === 'attending_dining' || manualRsvpForm.guests?.some(g => g.attending_dining)">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Dining Payment Status</label>
              <div class="grid grid-cols-4 gap-2">
                <label :class="['flex items-center justify-center p-2 rounded-xl border text-[11px] font-bold cursor-pointer transition-all', manualRsvpForm.payment_status === 'unpaid' ? 'bg-amber-50 border-amber-500 text-amber-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.payment_status" value="unpaid" class="sr-only" />
                  💳 Unpaid
                </label>
                <label :class="['flex items-center justify-center p-2 rounded-xl border text-[11px] font-bold cursor-pointer transition-all', manualRsvpForm.payment_status === 'paid' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.payment_status" value="paid" class="sr-only" />
                  ✅ Paid
                </label>
                <label :class="['flex items-center justify-center p-2 rounded-xl border text-[11px] font-bold cursor-pointer transition-all', manualRsvpForm.payment_status === 'waived' ? 'bg-purple-50 border-purple-500 text-purple-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.payment_status" value="waived" class="sr-only" />
                  🎁 Waived
                </label>
                <label :class="['flex items-center justify-center p-2 rounded-xl border text-[11px] font-bold cursor-pointer transition-all', manualRsvpForm.payment_status === 'refunded' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="manualRsvpForm.payment_status" value="refunded" class="sr-only" />
                  ↩️ Refunded
                </label>
              </div>
            </div>
            <div v-else class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-500 font-medium">
              ℹ️ Payment is only required for Dining RSVPs. No payment due for Meeting-Only attendance or Apologies.
            </div>

            <!-- Dietary Requirements (if dining) -->
            <div v-if="manualRsvpForm.attendance_status === 'attending_dining'">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Member Dietary Requirements</label>
              <input type="text" v-model="manualRsvpForm.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5" />
            </div>

            <!-- Apology Reason (if apologies) -->
            <div v-if="manualRsvpForm.attendance_status === 'apologies'">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
              <textarea v-model="manualRsvpForm.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5"></textarea>
            </div>

            <!-- Guests Section (if dining) -->
            <div v-if="manualRsvpForm.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Accompanying Guests</label>
                <button type="button" @click="addGuestRow" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                  + Add Guest
                </button>
              </div>

              <div v-for="(g, idx) in manualRsvpForm.guests" :key="idx" class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-slate-700">Guest #{{ idx + 1 }}</span>
                  <button type="button" @click="removeGuestRow(idx)" class="text-rose-600 hover:text-rose-800 text-xs font-bold">Remove</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <input type="text" v-model="g.guest_name" placeholder="Guest Full Name" class="text-xs rounded-lg border-slate-300 p-2" required />
                  <input type="text" v-model="g.dietary_requirements" placeholder="Dietary notes (optional)" class="text-xs rounded-lg border-slate-300 p-2" />
                </div>
              </div>
            </div>

            <!-- Submit buttons -->
            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
              <button type="button" @click="showManualRsvpModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
              <button type="submit" :disabled="manualRsvpForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
                Save RSVP
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Financial Return & Dining Calculator Modal -->
      <div v-if="showFinancialReturnModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-6 my-8 border border-slate-200">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                <span>💰 Meeting Financial Return & Dining Calculator</span>
              </h3>
              <p class="text-xs text-slate-500 font-medium mt-0.5">
                Automatically posts dining income, charity collections, and kitchen bills to Accounts Payable & Ledger.
              </p>
            </div>
            <button @click="showFinancialReturnModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <form @submit.prevent="submitFinancialReturn" class="space-y-6">
            
            <!-- Date & Basic Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/80 p-4 rounded-xl border border-slate-200">
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Accounting Return Date</label>
                <input type="date" v-model="financialReturnForm.return_date" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-semibold" required />
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Kitchen Vendor (Accounts Payable)</label>
                <input type="text" v-model="financialReturnForm.kitchen_vendor_name" placeholder="e.g. Masonic Hall Catering Ltd" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-semibold" />
              </div>
            </div>

            <!-- Section 1: Dining Fees & Kitchen Expense -->
            <div class="space-y-3">
              <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1.5 border-b border-amber-100 pb-1.5">
                <span>🍽️ 1. Dining Fees & Kitchen Caterer Calculator</span>
              </h4>

              <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Paid Diners Count</label>
                  <input type="number" min="0" v-model.number="financialReturnForm.paid_diners_count" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-bold" required />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Dining Fee / Head (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.dining_fee_per_head" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-bold" required />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Kitchen Cost / Head (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.kitchen_cost_per_head" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-bold" required />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Waived Diners (Guests / Speakers)</label>
                  <input type="number" min="0" v-model.number="financialReturnForm.waived_diners_count" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5 font-semibold" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Reason for Waived Fee</label>
                  <input type="text" v-model="financialReturnForm.waived_reason" placeholder="Official guests, visiting speakers" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5" />
                </div>
              </div>

              <!-- Dining Calculation Preview Card -->
              <div class="bg-gradient-to-r from-amber-500/10 to-orange-500/10 p-4 rounded-xl border border-amber-200/80 space-y-2">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center text-xs">
                  <div>
                    <div class="text-[10px] uppercase font-bold text-slate-500">Total Meals</div>
                    <div class="text-base font-black text-slate-800">{{ calcTotalMeals }} plates</div>
                  </div>
                  <div>
                    <div class="text-[10px] uppercase font-bold text-slate-500">Gross Dining Income</div>
                    <div class="text-base font-black text-emerald-700">£{{ calcDiningRevenue.toFixed(2) }}</div>
                  </div>
                  <div>
                    <div class="text-[10px] uppercase font-bold text-slate-500">Kitchen Caterer Bill</div>
                    <div class="text-base font-black text-rose-700">£{{ calcKitchenBill.toFixed(2) }}</div>
                  </div>
                  <div>
                    <div class="text-[10px] uppercase font-bold text-slate-500">Net Dining Surplus</div>
                    <div :class="['text-base font-black', calcDiningSurplus >= 0 ? 'text-indigo-700' : 'text-rose-600']">
                      £{{ calcDiningSurplus.toFixed(2) }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section 2: Charity Collections & Contributions -->
            <div class="space-y-3">
              <h4 class="text-xs font-bold text-purple-900 uppercase tracking-wider flex items-center gap-1.5 border-b border-purple-100 pb-1.5">
                <span>🎗️ 2. Meeting Charity & Almoner Collections</span>
              </h4>

              <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Raffle Collection (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.raffle_amount" class="w-full text-xs rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500 p-2.5 font-bold" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Alms Box Collection (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.alms_amount" class="w-full text-xs rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500 p-2.5 font-bold" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Donations (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.donations_amount" class="w-full text-xs rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500 p-2.5 font-bold" />
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-600 mb-1">Bequests (£)</label>
                  <input type="number" step="0.01" min="0" v-model.number="financialReturnForm.bequest_amount" class="w-full text-xs rounded-xl border-slate-300 focus:border-purple-500 focus:ring-purple-500 p-2.5 font-bold" />
                </div>
              </div>
            </div>

            <!-- Notes & Summary Banner -->
            <div>
              <label class="block text-[11px] font-bold text-slate-600 mb-1">Treasury Notes / Remarks</label>
              <textarea v-model="financialReturnForm.notes" rows="2" placeholder="e.g. Raffle receipts to be remitted to Masonic Charity Foundation" class="w-full text-xs rounded-xl border-slate-300 focus:border-amber-500 focus:ring-amber-500 p-2.5"></textarea>
            </div>

            <!-- Total Deposit Summary Bar -->
            <div class="bg-slate-900 text-white p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Net Bank Deposit Expected</div>
                <div class="text-xl font-black text-amber-400">£{{ calcBankDeposit.toFixed(2) }}</div>
              </div>
              <div class="text-right">
                <div class="text-[10px] text-slate-400 font-medium">Kitchen AP Bill Created: <span class="font-bold text-rose-300">£{{ calcKitchenBill.toFixed(2) }}</span></div>
                <div class="text-[10px] text-slate-400 font-medium">Total Charity Collected: <span class="font-bold text-purple-300">£{{ calcTotalCharity.toFixed(2) }}</span></div>
              </div>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex justify-end gap-3 pt-2 border-t border-slate-100">
              <button type="button" @click="showFinancialReturnModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
              <button type="submit" :disabled="financialReturnForm.processing" class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-xl shadow-lg transition-all cursor-pointer disabled:opacity-50 flex items-center gap-1.5">
                <span>⚡ Post Financial Return & Ledger Entry</span>
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
