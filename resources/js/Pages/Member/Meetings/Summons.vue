<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  userRsvp: { type: Object, default: null },
  secretaryUser: { type: Object, default: null },
  worshipfulMaster: { type: Object, default: null },
  memberRole: { type: String, default: 'member' },
  isCutoffPassed: { type: Boolean, default: false },
  charityGrants: { type: Array, default: () => [] },
});

const showRsvpModal = ref(false);

const rsvpForm = useForm({
  attendance_status: props.userRsvp?.attendance_status || 'attending_dining',
  dietary_requirements: props.userRsvp?.dietary_requirements || '',
  apology_reason: props.userRsvp?.apology_reason || '',
  guests: (props.userRsvp?.guests || []).map(g => ({
    guest_name: g.guest_name,
    dietary_requirements: g.dietary_requirements || '',
    attending_dining: g.attending_dining ?? true,
  })),
});

const openRsvpModal = () => {
  if (props.userRsvp) {
    rsvpForm.attendance_status = props.userRsvp.attendance_status || 'attending_dining';
    rsvpForm.dietary_requirements = props.userRsvp.dietary_requirements || '';
    rsvpForm.apology_reason = props.userRsvp.apology_reason || '';
    rsvpForm.guests = (props.userRsvp.guests || []).map(g => ({
      guest_name: g.guest_name,
      dietary_requirements: g.dietary_requirements || '',
      attending_dining: g.attending_dining ?? true,
    }));
  } else {
    rsvpForm.attendance_status = 'attending_dining';
    rsvpForm.dietary_requirements = '';
    rsvpForm.apology_reason = '';
    rsvpForm.guests = [];
  }
  showRsvpModal.value = true;
};

const addGuestRow = () => {
  rsvpForm.guests.push({
    guest_name: '',
    dietary_requirements: '',
    attending_dining: true,
  });
};

const removeGuestRow = (index) => {
  rsvpForm.guests.splice(index, 1);
};

const submitRsvp = () => {
  rsvpForm.post(route('member.meetings.rsvp', { slug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showRsvpModal.value = false;
    },
  });
};

function numberFormat(val) {
  if (!val) return '0.00';
  return parseFloat(val).toFixed(2);
}

function formatDate(dateVal) {
  if (!dateVal) return '';
  const cleanStr = String(dateVal).split('T')[0];
  const parts = cleanStr.split('-');
  if (parts.length === 3) {
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const day = parseInt(parts[2], 10);
    const d = new Date(year, month, day);
    if (!isNaN(d.getTime())) {
      return d.toLocaleDateString('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      });
    }
  }
  return dateVal;
}

function formatDateTime(dateTimeVal) {
  if (!dateTimeVal) return '';
  const d = new Date(dateTimeVal);
  if (isNaN(d.getTime())) return dateTimeVal;
  return d.toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}
</script>

<template>
  <MembersLayout :title="`Summons — ${meeting.title || formatDate(meeting.meeting_date)}`" :club="club" :member-role="memberRole" active-tab="dashboard">
    
    <div class="max-w-4xl mx-auto space-y-6">      <!-- Sticky Top Action Bar -->
      <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-wrap items-center justify-between gap-4">
        <Link :href="route('member.dashboard', { slug: club.slug })" class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-sm font-bold rounded-xl transition-all flex items-center gap-1.5">
          ← Back to Dashboard
        </Link>

        <div class="flex items-center gap-3">
          <button @click="openRsvpModal" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer">
            ✍️ {{ userRsvp ? 'Edit Your Response' : 'Respond to Summons' }}
          </button>
          
          <a :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })" target="_blank" class="px-4 py-2 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-sm font-bold rounded-xl border border-emerald-200 dark:border-emerald-800/60 transition-all flex items-center gap-1.5">
            🖨️ Download PDF Summons
          </a>
        </div>
      </div>

      <!-- Main Summons Sheet Container (Vertical Web View) -->
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-8 shadow-lg border border-slate-200/80 dark:border-slate-800/80 space-y-8">
        
        <!-- Header Banner -->
        <div class="text-center border-b border-slate-200 dark:border-slate-800 pb-8 space-y-3">
          <div class="w-16 h-16 bg-slate-900 dark:bg-slate-700 text-amber-400 rounded-2xl flex items-center justify-center font-black text-2xl mx-auto shadow-md">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>

          <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">{{ club.name }}</h1>
          <p v-if="club.lodge_number" class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-widest">No. {{ club.lodge_number }}</p>
          <div class="inline-block px-4 py-1.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-blue-800 dark:text-blue-200 text-sm font-extrabold rounded-full uppercase tracking-wider">
            Official Meeting Summons
          </div>
          <h2 class="text-xl font-bold text-slate-800 dark:text-slate-100 pt-2">{{ meeting.title || 'Regular Meeting' }}</h2>
        </div>

        <!-- Current Member Response Status Bar -->
        <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm">
          <div>
            <span class="text-slate-500 dark:text-slate-400 font-medium">Your Attendance Status: </span>
            <span v-if="userRsvp?.attendance_status === 'attending_dining'" class="font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2.5 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800/60 ml-1">
              🟢 Attending Dining
            </span>
            <span v-else-if="userRsvp?.attendance_status === 'attending_meeting_only'" class="font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60 ml-1">
              🔵 Attending Meeting Only
            </span>
            <span v-else-if="userRsvp?.attendance_status === 'apologies'" class="font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 px-2.5 py-1 rounded-lg border border-rose-200 dark:border-rose-800/60 ml-1">
              🔴 Apologies Sent
            </span>
            <span v-else class="font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2.5 py-1 rounded-lg border border-amber-200 dark:border-amber-800/60 ml-1">
              ⚠️ Awaiting Response
            </span>
          </div>

          <button @click="openRsvpModal" class="text-blue-600 dark:text-blue-400 font-bold hover:underline">
            {{ userRsvp ? 'Change RSVP' : 'Submit RSVP' }} →
          </button>
        </div>

        <!-- Schedule & Venue Details Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-6 bg-slate-900 dark:bg-slate-700 text-white rounded-2xl">
          <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">📅 Date</div>
            <div class="text-base font-extrabold text-white mt-1">{{ formatDate(meeting.meeting_date) }}</div>
          </div>
          <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">🕒 Timings</div>
            <div class="text-base font-extrabold text-white mt-1">Start: {{ meeting.starts_at }} <span v-if="meeting.rehearsal_starts_at" class="text-sm font-normal text-slate-300">(Rehearsal: {{ meeting.rehearsal_starts_at }})</span></div>
          </div>
          <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">📍 Location</div>
            <div class="text-base font-extrabold text-white mt-1">{{ meeting.venue }}</div>
          </div>
          <div>
            <div class="text-xs font-bold uppercase tracking-wider text-slate-400">👔 Dress Code</div>
            <div class="text-base font-extrabold text-white mt-1">{{ meeting.dress_code }}</div>
          </div>
        </div>

        <!-- Formal Salutation Call -->
        <div class="p-6 bg-amber-50/60 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl text-sm text-amber-900 dark:text-amber-200 leading-relaxed space-y-2">
          <p class="font-bold text-base text-amber-950 dark:text-amber-100">{{ meeting.salutation || 'Dear Sir and Brother,' }}</p>
          <p>
            You are hereby requested and summoned to attend the proceedings of <strong>{{ club.name }}</strong> at the date, time, and venue specified above.
          </p>
          <div class="flex items-center justify-between pt-2 text-xs font-semibold text-amber-800 dark:text-amber-200">
            <span>By Command of the Worshipful Master: <strong>{{ worshipfulMaster?.name || 'Worshipful Master' }}</strong></span>
            <span>Secretary: <strong>{{ secretaryUser?.name || 'Secretary' }}</strong></span>
          </div>
        </div>

        <!-- Agenda & Business Items -->
        <div class="space-y-4 pt-2">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center justify-between">
            <span>📜 Agenda & Business to be Transacted</span>
            <span v-if="meeting.agenda_items?.length" class="text-sm font-semibold text-slate-500 dark:text-slate-400">{{ meeting.agenda_items.length }} Items</span>
          </h3>

          <div v-if="meeting.agenda_items?.length" class="space-y-3">
            <div v-for="(item, idx) in meeting.agenda_items" :key="item.id || idx" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 space-y-1">
              <div class="flex items-start gap-3">
                <span class="w-7 h-7 rounded-full bg-blue-600 text-white font-bold text-sm flex items-center justify-center flex-shrink-0 mt-0.5">
                  {{ item.item_number || (idx + 1) }}
                </span>
                <div>
                  <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ item.title }}</h4>
                  <p v-if="item.description" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed mt-1 whitespace-pre-line">{{ item.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="p-6 text-center text-slate-400 bg-slate-50 dark:bg-slate-800/50 rounded-2xl text-sm">
            Standard business and agenda items will be transacted.
          </div>
        </div>

        <!-- Charitable Donation Proposals & Alms Voting -->
        <div v-if="charityGrants?.length" class="space-y-4 pt-2">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2 flex items-center justify-between">
            <span class="flex items-center gap-2"><span>❤️</span> Charitable Donation Proposals & Alms Voting</span>
            <span class="text-xs font-bold text-amber-800 dark:text-amber-200 bg-amber-100 dark:bg-amber-900/40 px-2.5 py-1 rounded-lg border border-amber-300 dark:border-amber-700/60">
              {{ charityGrants.length }} Grant Proposal(s)
            </span>
          </h3>

          <div class="space-y-3">
            <div v-for="grant in charityGrants" :key="grant.id" class="p-4 bg-amber-50/70 dark:bg-amber-950/70 rounded-2xl border border-amber-200/90 dark:border-amber-800/90 space-y-2">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-200/60 dark:border-amber-800/60 pb-2">
                <div>
                  <h4 class="text-base font-black text-slate-900 dark:text-white">
                    £{{ numberFormat(grant.amount) }} — {{ grant.recipient_name }}
                  </h4>
                  <p class="text-xs text-amber-900 dark:text-amber-200 font-semibold mt-0.5">{{ grant.purpose }}</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold capitalize bg-amber-200/80 dark:bg-amber-900/80 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60 self-start sm:self-center">
                  {{ (grant.approval_status || 'proposed').replace('_', ' ') }}
                </span>
              </div>
              <div class="flex flex-wrap items-center gap-x-4 text-xs text-slate-600 dark:text-slate-300 font-medium">
                <span v-if="grant.proposer">Proposed by: <strong class="text-slate-900 dark:text-white">{{ grant.proposer.first_name }} {{ grant.proposer.last_name }}</strong></span>
                <span v-if="grant.seconder">Seconded by: <strong class="text-slate-900 dark:text-white">{{ grant.seconder.first_name }} {{ grant.seconder.last_name }}</strong></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Officers Roster -->
        <div v-if="meeting.officer_assignments?.length" class="space-y-4 pt-2">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            🏛️ Officers Roster ({{ meeting.officers_year_label || 'Officers' }})
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="off in meeting.officer_assignments" :key="off.id" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 text-sm flex items-center justify-between">
              <span class="font-bold text-slate-600 dark:text-slate-300">{{ off.officer_role?.name || 'Officer' }}</span>
              <span class="font-extrabold text-slate-900 dark:text-white">{{ off.custom_name || off.user?.name || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Festive Board & Dining Details -->
        <div class="space-y-4 pt-2">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            🍽️ Festive Board & Dining Information
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Member Dining Fee</div>
              <div class="text-2xl font-black text-emerald-900 dark:text-emerald-200">£{{ numberFormat(meeting.dining_cost_member) }}</div>
            </div>

            <div class="p-4 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">Guest Dining Fee</div>
              <div class="text-2xl font-black text-blue-900 dark:text-blue-200">£{{ numberFormat(meeting.dining_cost_guest) }}</div>
            </div>

            <div class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Dining Cutoff Deadline</div>
              <div class="text-base font-extrabold text-amber-900 dark:text-amber-200">{{ meeting.rsvp_cutoff_at ? formatDateTime(meeting.rsvp_cutoff_at) : '5 Days Before Meeting' }}</div>
            </div>
          </div>

          <!-- Bank Details & Payment Reference -->
          <div v-if="(meeting.bank_sort_code || meeting.bank_account_number) && (user_rsvp?.attendance_status === 'attending_dining' || user_rsvp?.guests?.some(g => g.attending_dining))" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-2">
            <div class="font-bold text-slate-900 dark:text-white flex items-center justify-between">
              <span>🏦 Direct Bank Transfer Payment Details</span>
              <span v-if="user_rsvp?.payment_status === 'paid'" class="px-2.5 py-0.5 rounded text-xs font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60">✅ PAID</span>
              <span v-else-if="user_rsvp?.payment_status === 'waived'" class="px-2.5 py-0.5 rounded text-xs font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60">🎁 WAIVED</span>
              <span v-else class="px-2.5 py-0.5 rounded text-xs font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60">💳 UNPAID</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-slate-700 dark:text-slate-200 font-mono text-xs">
              <div>Sort Code: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_sort_code }}</strong></div>
              <div>Account No: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_account_number }}</strong></div>
              <div>Bank Ref Prefix: <strong class="text-blue-600 dark:text-blue-400">{{ meeting.payment_reference_prefix || 'SUMMONS' }}</strong></div>
            </div>
          </div>
          <div v-else-if="user_rsvp && user_rsvp.attendance_status !== 'attending_dining'" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-2xl text-sm text-slate-500 dark:text-slate-400 font-medium">
            ℹ️ No dining fee required for Meeting-Only attendance or Apologies.
          </div>
        </div>

        <!-- Provincial Executive Leadership & Grand Officers -->
        <div v-if="meeting.provincial_grand_master || meeting.deputy_provincial_grand_master || meeting.assistant_provincial_grand_masters" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            👑 {{ meeting.front_page_title || 'Provincial Grand Lodge Leadership' }}
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-if="meeting.provincial_grand_master" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Provincial Grand Master</div>
              <div class="text-base font-extrabold text-slate-900 dark:text-white">{{ meeting.provincial_grand_master }}</div>
            </div>

            <div v-if="meeting.deputy_provincial_grand_master" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deputy Provincial Grand Master</div>
              <div class="text-base font-extrabold text-slate-900 dark:text-white">{{ meeting.deputy_provincial_grand_master }}</div>
            </div>

            <div v-if="meeting.assistant_provincial_grand_masters" class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-2xl text-sm space-y-1">
              <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Assistant Provincial Grand Masters</div>
              <div class="text-sm font-semibold text-slate-800 dark:text-slate-100 whitespace-pre-line leading-relaxed">{{ meeting.assistant_provincial_grand_masters }}</div>
            </div>
          </div>
        </div>

        <!-- Fraternal Visits & Delegations -->
        <div v-if="meeting.fraternal_visits?.length || meeting.fraternal_visits_text" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 pb-2">
            🤝 Fraternal Visits & Official Delegations
          </h3>

          <div v-if="meeting.fraternal_visits_text" class="p-4 bg-blue-50/60 dark:bg-blue-950/60 border border-blue-200/80 dark:border-blue-800/80 rounded-2xl text-sm text-blue-900 dark:text-blue-200 leading-relaxed whitespace-pre-line">
            {{ meeting.fraternal_visits_text }}
          </div>

          <div v-if="meeting.fraternal_visits?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div v-for="visit in meeting.fraternal_visits" :key="visit.id" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 text-sm space-y-1">
              <div class="font-bold text-slate-900 dark:text-white">🏛️ {{ visit.visiting_club_name || visit.lodge_name }} <span v-if="visit.visiting_club_number || visit.lodge_number">No. {{ visit.visiting_club_number || visit.lodge_number }}</span></div>
              <div v-if="visit.leader_name" class="text-slate-600 dark:text-slate-300 font-medium">Delegation Leader: {{ visit.leader_name }}</div>
              <div v-if="visit.notes" class="text-slate-500 dark:text-slate-400 italic text-xs">{{ visit.notes }}</div>
            </div>
          </div>
        </div>

        <!-- Honorary Members & Welfare Notices -->
        <div v-if="meeting.honorary_members_text || meeting.sick_distressed_notes || meeting.almoner_notice" class="space-y-4 pt-2 border-t border-slate-100 dark:border-slate-800">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="meeting.honorary_members_text" class="p-4 bg-amber-50/60 dark:bg-amber-950/60 border border-amber-200/80 dark:border-amber-800/80 rounded-2xl text-sm text-amber-900 dark:text-amber-200 space-y-1">
              <div class="font-bold text-amber-950 dark:text-amber-100 uppercase tracking-wider text-xs">🎖️ Honorary Members</div>
              <p class="whitespace-pre-line leading-relaxed">{{ meeting.honorary_members_text }}</p>
            </div>

            <div v-if="meeting.sick_distressed_notes || meeting.almoner_notice" class="p-4 bg-rose-50/60 dark:bg-rose-950/60 border border-rose-200/80 dark:border-rose-800/80 rounded-2xl text-sm text-rose-900 dark:text-rose-200 space-y-1">
              <div class="font-bold text-rose-950 dark:text-rose-100 uppercase tracking-wider text-xs">❤️ Almoner & Sick/Distressed Notice</div>
              <p class="whitespace-pre-line leading-relaxed">{{ meeting.sick_distressed_notes || meeting.almoner_notice }}</p>
            </div>
          </div>
        </div>

        <!-- Secretary Contact Footer -->
        <div class="p-4 bg-slate-900 dark:bg-slate-700 text-white rounded-2xl text-sm flex flex-wrap items-center justify-between gap-3">
          <div>
            <span class="text-slate-400 font-medium">Lodge Secretary: </span>
            <strong class="text-white">{{ secretaryUser?.name || 'Secretary' }}</strong>
          </div>
          <div class="flex items-center gap-4 text-slate-300 font-mono text-xs">
            <span>📧 {{ secretaryUser?.email || club.email }}</span>
            <span v-if="club.settings?.phone">📞 {{ club.settings.phone }}</span>
          </div>
        </div>

      </div>

      <!-- Member RSVP Modal -->
      <div v-if="showRsvpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">
              ✍️ Respond to Summons — {{ meeting.title || formatDate(meeting.meeting_date) }}
            </h3>
            <button @click="showRsvpModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold">✕</button>
          </div>

          <form @submit.prevent="submitRsvp" class="space-y-4">
            <!-- Attendance Status -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Select Attendance</label>
              <div class="grid grid-cols-3 gap-2">
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-sm font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'attending_dining' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-800 dark:text-emerald-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="attending_dining" class="sr-only" />
                  🟢 Dining & Meeting
                </label>
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-sm font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'attending_meeting_only' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-800 dark:text-blue-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="attending_meeting_only" class="sr-only" />
                  🔵 Meeting Only
                </label>
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-sm font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'apologies' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-500 text-rose-800 dark:text-rose-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="apologies" class="sr-only" />
                  🔴 Apologies
                </label>
              </div>
            </div>

            <!-- Dietary Requirements (if dining) -->
            <div v-if="rsvpForm.attendance_status === 'attending_dining'">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Your Dietary Requirements</label>
              <input type="text" v-model="rsvpForm.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5" />
            </div>

            <!-- Apology Reason (if apologies) -->
            <div v-if="rsvpForm.attendance_status === 'apologies'">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
              <textarea v-model="rsvpForm.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-sm rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5"></textarea>
            </div>

            <!-- Guests Section (if dining) -->
            <div v-if="rsvpForm.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Accompanying Guests</label>
                <button type="button" @click="addGuestRow" class="text-xs font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60">
                  + Add Guest
                </button>
              </div>

              <div v-for="(g, idx) in rsvpForm.guests" :key="idx" class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                  <span class="text-sm font-bold text-slate-700 dark:text-slate-200">Guest #{{ idx + 1 }}</span>
                  <button type="button" @click="removeGuestRow(idx)" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-200 text-xs font-bold">Remove</button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <input type="text" v-model="g.guest_name" placeholder="Guest Full Name" class="text-sm rounded-lg border-slate-300 dark:border-slate-700 p-2" required />
                  <input type="text" v-model="g.dietary_requirements" placeholder="Dietary notes (optional)" class="text-sm rounded-lg border-slate-300 dark:border-slate-700 p-2" />
                </div>
              </div>
            </div>

            <!-- Submit buttons -->
            <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
              <button type="button" @click="showRsvpModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl">Cancel</button>
              <button type="submit" :disabled="rsvpForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
                Save Response
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MembersLayout>
</template>
