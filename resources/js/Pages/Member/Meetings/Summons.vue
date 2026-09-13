<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  userRsvp: { type: Object, default: null },
  secretaryUser: { type: Object, default: null },
  worshipfulMaster: { type: Object, default: null },
  memberRole: { type: String, default: 'member' },
  isCutoffPassed: { type: Boolean, default: false },
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
</script>

<template>
  <MemberLayout :title="`Summons — ${meeting.title || meeting.meeting_date}`" :club="club" :member-role="memberRole" active-tab="dashboard">
    
    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Sticky Top Action Bar -->
      <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 flex flex-wrap items-center justify-between gap-4">
        <Link :href="route('member.dashboard', { slug: club.slug })" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
          ← Back to Dashboard
        </Link>

        <div class="flex items-center gap-3">
          <button @click="openRsvpModal" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition-all flex items-center gap-1.5 cursor-pointer">
            ✍️ {{ userRsvp ? 'Edit Your Response' : 'Respond to Summons' }}
          </button>
          
          <a :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })" target="_blank" class="px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition-all flex items-center gap-1.5">
            🖨️ Download PDF Summons
          </a>
        </div>
      </div>

      <!-- Main Summons Sheet Container (Vertical Web View) -->
      <div class="bg-white rounded-3xl p-8 shadow-lg border border-slate-200/80 space-y-8">
        
        <!-- Header Banner -->
        <div class="text-center border-b border-slate-200 pb-8 space-y-3">
          <div class="w-16 h-16 bg-slate-900 text-amber-400 rounded-2xl flex items-center justify-center font-black text-2xl mx-auto shadow-md">
            {{ club.name.substring(0, 2).toUpperCase() }}
          </div>

          <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ club.name }}</h1>
          <p v-if="club.lodge_number" class="text-xs font-bold text-slate-500 uppercase tracking-widest">No. {{ club.lodge_number }}</p>
          <div class="inline-block px-4 py-1 bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-extrabold rounded-full uppercase tracking-wider">
            Official Meeting Summons
          </div>
          <h2 class="text-lg font-bold text-slate-800 pt-2">{{ meeting.title || 'Regular Meeting' }}</h2>
        </div>

        <!-- Current Member Response Status Bar -->
        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
          <div>
            <span class="text-slate-500 font-medium">Your Attendance Status: </span>
            <span v-if="userRsvp?.attendance_status === 'attending_dining'" class="font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200 ml-1">
              🟢 Attending Dining
            </span>
            <span v-else-if="userRsvp?.attendance_status === 'attending_meeting_only'" class="font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-lg border border-sky-200 ml-1">
              🔵 Attending Meeting Only
            </span>
            <span v-else-if="userRsvp?.attendance_status === 'apologies'" class="font-bold text-rose-700 bg-rose-50 px-2.5 py-1 rounded-lg border border-rose-200 ml-1">
              🔴 Apologies Sent
            </span>
            <span v-else class="font-bold text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200 ml-1">
              ⚠️ Awaiting Response
            </span>
          </div>

          <button @click="openRsvpModal" class="text-indigo-600 font-bold hover:underline">
            {{ userRsvp ? 'Change RSVP' : 'Submit RSVP' }} →
          </button>
        </div>

        <!-- Schedule & Venue Details Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 p-6 bg-slate-900 text-white rounded-2xl">
          <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">📅 Date</div>
            <div class="text-sm font-extrabold text-white mt-1">{{ meeting.meeting_date }}</div>
          </div>
          <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">🕒 Timings</div>
            <div class="text-sm font-extrabold text-white mt-1">Start: {{ meeting.starts_at }} <span v-if="meeting.rehearsal_starts_at" class="text-xs font-normal text-slate-300">(Rehearsal: {{ meeting.rehearsal_starts_at }})</span></div>
          </div>
          <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">📍 Location</div>
            <div class="text-sm font-extrabold text-white mt-1">{{ meeting.venue }}</div>
          </div>
          <div>
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">👔 Dress Code</div>
            <div class="text-sm font-extrabold text-white mt-1">{{ meeting.dress_code }}</div>
          </div>
        </div>

        <!-- Formal Salutation Call -->
        <div class="p-6 bg-amber-50/60 border border-amber-200/80 rounded-2xl text-xs text-amber-900 leading-relaxed space-y-2">
          <p class="font-bold text-sm text-amber-950">{{ meeting.salutation || 'Dear Sir and Brother,' }}</p>
          <p>
            You are hereby requested and summoned to attend the proceedings of <strong>{{ club.name }}</strong> at the date, time, and venue specified above.
          </p>
          <div class="flex items-center justify-between pt-2 text-[11px] font-semibold text-amber-800">
            <span>By Command of the Worshipful Master: <strong>{{ worshipfulMaster?.name || 'Worshipful Master' }}</strong></span>
            <span>Secretary: <strong>{{ secretaryUser?.name || 'Secretary' }}</strong></span>
          </div>
        </div>

        <!-- Agenda & Business Items -->
        <div class="space-y-4 pt-2">
          <h3 class="text-base font-extrabold text-slate-900 border-b border-slate-200 pb-2 flex items-center justify-between">
            <span>📜 Agenda & Business to be Transacted</span>
            <span v-if="meeting.agenda_items?.length" class="text-xs font-semibold text-slate-500">{{ meeting.agenda_items.length }} Items</span>
          </h3>

          <div v-if="meeting.agenda_items?.length" class="space-y-3">
            <div v-for="(item, idx) in meeting.agenda_items" :key="item.id || idx" class="p-4 bg-slate-50 rounded-2xl border border-slate-200/80 space-y-1">
              <div class="flex items-start gap-3">
                <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center flex-shrink-0 mt-0.5">
                  {{ item.item_number || (idx + 1) }}
                </span>
                <div>
                  <h4 class="text-xs font-bold text-slate-900">{{ item.title }}</h4>
                  <p v-if="item.description" class="text-xs text-slate-600 leading-relaxed mt-1 whitespace-pre-line">{{ item.description }}</p>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="p-6 text-center text-slate-400 bg-slate-50 rounded-2xl text-xs">
            Standard business and agenda items will be transacted.
          </div>
        </div>

        <!-- Officers Roster -->
        <div v-if="meeting.officer_assignments?.length" class="space-y-4 pt-2">
          <h3 class="text-base font-extrabold text-slate-900 border-b border-slate-200 pb-2">
            🏛️ Officers Roster ({{ meeting.officers_year_label || 'Officers' }})
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
            <div v-for="off in meeting.officer_assignments" :key="off.id" class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-xs flex items-center justify-between">
              <span class="font-bold text-slate-600">{{ off.officer_role?.name || 'Officer' }}</span>
              <span class="font-extrabold text-slate-900">{{ off.custom_name || off.user?.name || '-' }}</span>
            </div>
          </div>
        </div>

        <!-- Festive Board & Dining Details -->
        <div class="space-y-4 pt-2">
          <h3 class="text-base font-extrabold text-slate-900 border-b border-slate-200 pb-2">
            🍽️ Festive Board & Dining Information
          </h3>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-xs space-y-1">
              <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Member Dining Fee</div>
              <div class="text-xl font-black text-emerald-900">£{{ numberFormat(meeting.dining_cost_member) }}</div>
            </div>

            <div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl text-xs space-y-1">
              <div class="text-[10px] font-bold uppercase tracking-wider text-purple-700">Guest Dining Fee</div>
              <div class="text-xl font-black text-purple-900">£{{ numberFormat(meeting.dining_cost_guest) }}</div>
            </div>

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-xs space-y-1">
              <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Dining Cutoff Deadline</div>
              <div class="text-sm font-extrabold text-amber-900">{{ meeting.rsvp_cutoff_at || '5 Days Before Meeting' }}</div>
            </div>
          </div>

          <!-- Bank Details & Payment Reference -->
          <div v-if="meeting.bank_sort_code || meeting.bank_account_number" class="p-4 bg-slate-50 border border-slate-200 rounded-2xl text-xs space-y-2">
            <div class="font-bold text-slate-900">🏦 Direct Bank Transfer Payment Details</div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-slate-700 font-mono text-[11px]">
              <div>Sort Code: <strong class="text-slate-900">{{ meeting.bank_sort_code }}</strong></div>
              <div>Account No: <strong class="text-slate-900">{{ meeting.bank_account_number }}</strong></div>
              <div>Bank Ref Prefix: <strong class="text-indigo-600">{{ meeting.payment_reference_prefix || 'SUMMONS' }}</strong></div>
            </div>
          </div>
        </div>

      </div>

      <!-- Member RSVP Modal -->
      <div v-if="showRsvpModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">
              ✍️ Respond to Summons — {{ meeting.title || meeting.meeting_date }}
            </h3>
            <button @click="showRsvpModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
          </div>

          <form @submit.prevent="submitRsvp" class="space-y-4">
            <!-- Attendance Status -->
            <div>
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Attendance</label>
              <div class="grid grid-cols-3 gap-2">
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'attending_dining' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="attending_dining" class="sr-only" />
                  🟢 Dining & Meeting
                </label>
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'attending_meeting_only' ? 'bg-sky-50 border-sky-500 text-sky-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="attending_meeting_only" class="sr-only" />
                  🔵 Meeting Only
                </label>
                <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', rsvpForm.attendance_status === 'apologies' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                  <input type="radio" v-model="rsvpForm.attendance_status" value="apologies" class="sr-only" />
                  🔴 Apologies
                </label>
              </div>
            </div>

            <!-- Dietary Requirements (if dining) -->
            <div v-if="rsvpForm.attendance_status === 'attending_dining'">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Your Dietary Requirements</label>
              <input type="text" v-model="rsvpForm.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5" />
            </div>

            <!-- Apology Reason (if apologies) -->
            <div v-if="rsvpForm.attendance_status === 'apologies'">
              <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
              <textarea v-model="rsvpForm.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5"></textarea>
            </div>

            <!-- Guests Section (if dining) -->
            <div v-if="rsvpForm.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100">
              <div class="flex items-center justify-between">
                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Accompanying Guests</label>
                <button type="button" @click="addGuestRow" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200">
                  + Add Guest
                </button>
              </div>

              <div v-for="(g, idx) in rsvpForm.guests" :key="idx" class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
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
              <button type="button" @click="showRsvpModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
              <button type="submit" :disabled="rsvpForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
                Save Response
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </MemberLayout>
</template>
