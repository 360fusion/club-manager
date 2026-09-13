<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  isPending: { type: Boolean, default: false },
  memberRole: { type: String, default: 'member' },
  memberNumber: { type: String, default: 'MEM-1001' },
  attendanceRate: { type: Number, default: 100 },
  attendedCount: { type: Number, default: 0 },
  totalRsvps: { type: Number, default: 0 },
  meetings: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  newsletters: { type: Array, default: () => [] },
});

const selectedEvent = ref(null);
const showDinnerModal = ref(false);
const selectedNewsletter = ref(null);

const selectedMeeting = ref(null);
const showMeetingRsvpModal = ref(false);

const rsvpForm = useForm({
  attendance_status: 'attending',
  attending_dining: false,
  menu_selections: { starter: '', main: '', dessert: '' },
  dietary_requirements: '',
});

const meetingRsvpForm = useForm({
  attendance_status: 'attending_dining',
  dietary_requirements: '',
  apology_reason: '',
  guests: [],
});

const openRsvpModal = (event) => {
  selectedEvent.value = event;
  rsvpForm.attendance_status = event.user_rsvp?.attendance_status || 'attending';
  rsvpForm.attending_dining = event.user_rsvp?.attending_dining || false;
  rsvpForm.menu_selections = event.user_rsvp?.menu_selections || { starter: '', main: '', dessert: '' };
  rsvpForm.dietary_requirements = event.user_rsvp?.dietary_requirements || '';
  showDinnerModal.value = true;
};

const submitRsvp = () => {
  if (!selectedEvent.value) return;
  rsvpForm.post(route('member.rsvp', { slug: props.club.slug, id: selectedEvent.value.id }), {
    onSuccess: () => {
      showDinnerModal.value = false;
    }
  });
};

const openMeetingRsvpModal = (meeting) => {
  selectedMeeting.value = meeting;
  if (meeting.user_rsvp) {
    meetingRsvpForm.attendance_status = meeting.user_rsvp.attendance_status || 'attending_dining';
    meetingRsvpForm.dietary_requirements = meeting.user_rsvp.dietary_requirements || '';
    meetingRsvpForm.apology_reason = meeting.user_rsvp.apology_reason || '';
    meetingRsvpForm.guests = (meeting.user_rsvp.guests || []).map(g => ({
      guest_name: g.guest_name,
      dietary_requirements: g.dietary_requirements || '',
      attending_dining: g.attending_dining ?? true,
    }));
  } else {
    meetingRsvpForm.attendance_status = 'attending_dining';
    meetingRsvpForm.dietary_requirements = '';
    meetingRsvpForm.apology_reason = '';
    meetingRsvpForm.guests = [];
  }
  showMeetingRsvpModal.value = true;
};

const addMeetingGuestRow = () => {
  meetingRsvpForm.guests.push({
    guest_name: '',
    dietary_requirements: '',
    attending_dining: true,
  });
};

const removeMeetingGuestRow = (index) => {
  meetingRsvpForm.guests.splice(index, 1);
};

const submitMeetingRsvp = () => {
  if (!selectedMeeting.value) return;
  meetingRsvpForm.post(route('member.meetings.rsvp', { slug: props.club.slug, id: selectedMeeting.value.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showMeetingRsvpModal.value = false;
    }
  });
};
</script>

<template>
  <MemberLayout title="Member Portal" :club="club" :member-role="memberRole" active-tab="dashboard">
    
    <!-- Pending Approval Banner -->
    <div v-if="isPending" class="p-6 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl space-y-1">
      <div class="flex items-center gap-2 font-bold text-xs uppercase tracking-wider text-amber-700">
        <span>⏳ Membership Pending Admin Approval</span>
      </div>
      <p class="text-xs leading-relaxed">
        Your registration for <strong>{{ club.name }}</strong> has been submitted. A club officer or captain will review your invite request shortly.
      </p>
    </div>

    <!-- Top Big Stat Cards Grid (Matching Admin Design) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- 1. Member Standing Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Member Standing</span>
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
            Active #{{ memberNumber }}
          </span>
        </div>
        <div class="text-3xl font-black text-slate-900 tracking-tight">
          {{ memberRole.toUpperCase() }}
        </div>
        <div class="text-xs font-semibold text-emerald-600">
          Good Standing • {{ club.name }}
        </div>
      </div>

      <!-- 2. Attendance Standing Score Card -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Attendance Rate Score</div>
        <div class="text-3xl font-black text-slate-900 tracking-tight">
          {{ attendanceRate }}%
        </div>
        <div class="text-xs font-semibold text-slate-500">
          {{ attendedCount }} of {{ totalRsvps }} sessions attended
        </div>
      </div>

    </div>

    <!-- Main Content Dual Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Left Column: Invited Meetings & Upcoming Events (2 cols) -->
      <div id="events-section" class="lg:col-span-2 space-y-8">
        
        <!-- Invited Meetings & Summons Section -->
        <div id="meetings-section" class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">📜 Invited Meetings & Summons</h2>
            <span class="text-xs text-slate-500 font-medium">{{ meetings.length }} meetings invited</span>
          </div>

          <div v-if="meetings.length" class="space-y-4">
            <div v-for="m in meetings" :key="m.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span v-if="m.user_rsvp?.attendance_status === 'attending_dining'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      🟢 ATTENDING DINING
                    </span>
                    <span v-else-if="m.user_rsvp?.attendance_status === 'attending_meeting_only'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                      🔵 ATTENDING MEETING ONLY
                    </span>
                    <span v-else-if="m.user_rsvp?.attendance_status === 'apologies'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                      🔴 APOLOGIES SENT
                    </span>
                    <span v-else class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                      ⚠️ RSVP AWAITING
                    </span>
                    
                    <span v-if="m.is_cutoff_passed" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                      CUTOFF PASSED
                    </span>
                  </div>

                  <h3 class="text-lg font-bold text-slate-900 mt-1">{{ m.title }}</h3>
                  <p class="text-xs text-slate-500">📍 {{ m.venue }} • 🕒 Start: {{ m.starts_at }} <span v-if="m.rehearsal_starts_at">(Rehearsal: {{ m.rehearsal_starts_at }})</span></p>
                  <p v-if="m.rsvp_cutoff_at" class="text-[11px] text-amber-700 font-semibold">⏰ Catering Deadline: {{ m.rsvp_cutoff_at }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                  <button @click="openMeetingRsvpModal(m)" :disabled="m.is_cutoff_passed" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all disabled:opacity-50 cursor-pointer">
                    ✍️ {{ m.user_rsvp ? 'Edit Response' : 'Respond' }}
                  </button>

                  <Link :href="route('member.meetings.summons', { slug: club.slug, id: m.id })" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 transition-all flex items-center gap-1">
                    📄 View Summons
                  </Link>

                  <a :href="route('member.meetings.pdf', { slug: club.slug, id: m.id })" target="_blank" class="px-3 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs rounded-xl border border-emerald-200 transition-all flex items-center gap-1">
                    🖨️ PDF
                  </a>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 text-slate-400 text-xs">
            No published meeting summons at this time.
          </div>
        </div>

        <!-- Upcoming Events Section -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900">📅 Upcoming Social Events & Training</h2>
            <span class="text-xs text-slate-500 font-medium">{{ events.length }} events scheduled</span>
          </div>

          <div v-if="events.length" class="space-y-4">
            <div v-for="event in events" :key="event.id" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 hover:border-slate-300 transition-all space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span v-if="event.user_rsvp?.checked_in_at" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      ✓ CHECKED IN @ {{ event.user_rsvp.checked_in_at }}
                    </span>
                    <span v-else-if="event.user_rsvp?.attendance_status === 'attending'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                      RSVP CONFIRMED
                    </span>
                    <span v-if="event.has_dining" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-sky-50 text-sky-700 border border-sky-200">
                      🍽️ 3-COURSE DINING
                    </span>
                  </div>
                  <h3 class="text-lg font-bold text-slate-900 mt-1">{{ event.title }}</h3>
                  <p class="text-xs text-slate-500">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
                </div>

                <button @click="openRsvpModal(event)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all self-start sm:self-auto cursor-pointer">
                  {{ event.user_rsvp ? 'Edit RSVP & Dining' : 'RSVP & Select Menu' }}
                </button>
              </div>
            </div>
          </div>

          <div v-else class="bg-white rounded-2xl p-8 text-center border border-slate-200/80 text-slate-500 text-xs">
            No upcoming social events at this time.
          </div>
        </div>

      </div>

      <!-- Right Column: Newsletters Feed & Dues Sidebar (1 col) -->
      <div id="dues-section" class="space-y-6">
        
        <!-- Published Newsletters Feed Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-bold text-slate-900">📧 Club Broadcasts</h3>
            <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 uppercase">Live Feed</span>
          </div>

          <div v-if="newsletters && newsletters.length" class="space-y-3">
            <div v-for="item in newsletters" :key="item.id" @click="selectedNewsletter = item" class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-1 cursor-pointer hover:border-slate-300 transition-all">
              <div class="text-[10px] font-semibold text-slate-400">Sent {{ item.sent_at }}</div>
              <div class="text-xs font-bold text-slate-900">{{ item.subject }}</div>
              <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">{{ item.content }}</p>
            </div>
          </div>

          <div v-else class="text-xs text-slate-400 text-center py-4">
            No published email broadcasts yet.
          </div>
        </div>

        <!-- My Active Dues Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">💳 Membership Dues</h3>
          <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-1">
            <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Active Plan</div>
            <div class="text-sm font-bold text-slate-900">Senior Rower Tier</div>
            <div class="text-xs text-emerald-700 font-bold">£35.00 / Monthly Dues</div>
          </div>
          <div class="text-xs text-slate-500 pt-1">
            Status: <strong class="text-emerald-700 uppercase font-mono">Good Standing</strong>
          </div>
        </div>

        <!-- Attendance Stats Detail Card -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 border-b border-slate-100 pb-3">📊 Attendance Log</h3>
          <div class="space-y-2 text-xs">
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-xl border border-slate-200">
              <span class="text-slate-600">Total Tracked Sessions</span>
              <span class="font-bold text-slate-900 font-mono">{{ totalRsvps }}</span>
            </div>
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-xl border border-slate-200">
              <span class="text-slate-600">Checked In / Attended</span>
              <span class="font-bold text-emerald-700 font-mono">{{ attendedCount }}</span>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- Read Newsletter Modal -->
    <div v-if="selectedNewsletter" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-4 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <span class="text-[10px] font-semibold text-slate-400">Sent {{ selectedNewsletter.sent_at }}</span>
            <h3 class="text-lg font-bold text-slate-900 mt-0.5">{{ selectedNewsletter.subject }}</h3>
          </div>
          <button @click="selectedNewsletter = null" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>

        <div class="text-xs text-slate-700 leading-relaxed max-h-80 overflow-y-auto prose prose-sm" v-html="selectedNewsletter.content">
        </div>

        <div class="pt-2 text-right">
          <button @click="selectedNewsletter = null" class="px-5 py-2 bg-slate-900 text-white font-bold text-xs rounded-xl">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Social Event RSVP Modal -->
    <div v-if="showDinnerModal && selectedEvent" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-6 shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
          <div>
            <h3 class="text-lg font-bold text-slate-900">RSVP & Meal Selection</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ selectedEvent.title }}</p>
          </div>
          <button @click="showDinnerModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-lg">&times;</button>
        </div>

        <form @submit.prevent="submitRsvp" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Attendance Status</label>
            <select v-model="rsvpForm.attendance_status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500">
              <option value="attending">I will attend</option>
              <option value="declined">Unable to attend</option>
              <option value="tentative">Tentative</option>
            </select>
          </div>

          <!-- Dining options if event has dining -->
          <div v-if="selectedEvent.has_dining" class="space-y-3 pt-2 border-t border-slate-100">
            <div class="flex items-center gap-3">
              <input v-model="rsvpForm.attending_dining" type="checkbox" id="attending_dining" class="w-4 h-4 rounded text-indigo-600 border-slate-300" />
              <label for="attending_dining" class="font-bold text-slate-900 text-xs">Attending Formal 3-Course Dinner</label>
            </div>

            <div v-if="rsvpForm.attending_dining" class="space-y-3 pl-2">
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Starter Choice</label>
                <input v-model="rsvpForm.menu_selections.starter" type="text" placeholder="e.g. Smoked Salmon" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Main Course Choice</label>
                <input v-model="rsvpForm.menu_selections.main" type="text" placeholder="e.g. Roast Beef / Vegetarian" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
              <div>
                <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Dessert Choice</label>
                <input v-model="rsvpForm.menu_selections.dessert" type="text" placeholder="e.g. Dark Chocolate Fondant" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs text-slate-900" />
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Dietary Requirements</label>
            <textarea v-model="rsvpForm.dietary_requirements" rows="2" placeholder="Gluten-free, Nut allergy, Vegetarian..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"></textarea>
          </div>

          <div class="flex items-center gap-3 pt-4">
            <button type="submit" :disabled="rsvpForm.processing" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all">
              Confirm RSVP
            </button>
            <button type="button" @click="showDinnerModal = false" class="px-4 py-3 bg-slate-100 text-slate-700 font-bold text-xs rounded-xl border border-slate-200">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Member Meeting Summons RSVP Modal -->
    <div v-if="showMeetingRsvpModal && selectedMeeting" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">
            ✍️ Respond to Summons — {{ selectedMeeting.title || selectedMeeting.meeting_date }}
          </h3>
          <button @click="showMeetingRsvpModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">✕</button>
        </div>

        <form @submit.prevent="submitMeetingRsvp" class="space-y-4">
          <!-- Attendance Status -->
          <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Select Attendance</label>
            <div class="grid grid-cols-3 gap-2">
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'attending_dining' ? 'bg-emerald-50 border-emerald-500 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="attending_dining" class="sr-only" />
                🟢 Dining & Meeting
              </label>
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'attending_meeting_only' ? 'bg-sky-50 border-sky-500 text-sky-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="attending_meeting_only" class="sr-only" />
                🔵 Meeting Only
              </label>
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'apologies' ? 'bg-rose-50 border-rose-500 text-rose-800' : 'bg-slate-50 border-slate-200 text-slate-600']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="apologies" class="sr-only" />
                🔴 Apologies
              </label>
            </div>
          </div>

          <!-- Dietary Requirements (if dining) -->
          <div v-if="meetingRsvpForm.attendance_status === 'attending_dining'">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Your Dietary Requirements</label>
            <input type="text" v-model="meetingRsvpForm.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5" />
          </div>

          <!-- Apology Reason (if apologies) -->
          <div v-if="meetingRsvpForm.attendance_status === 'apologies'">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
            <textarea v-model="meetingRsvpForm.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-xs rounded-xl border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 p-2.5"></textarea>
          </div>

          <!-- Guests Section (if dining) -->
          <div v-if="meetingRsvpForm.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Accompanying Guests</label>
              <button type="button" @click="addMeetingGuestRow" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer">
                + Add Guest
              </button>
            </div>

            <div v-for="(g, idx) in meetingRsvpForm.guests" :key="idx" class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700">Guest #{{ idx + 1 }}</span>
                <button type="button" @click="removeMeetingGuestRow(idx)" class="text-rose-600 hover:text-rose-800 text-xs font-bold cursor-pointer">Remove</button>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input type="text" v-model="g.guest_name" placeholder="Guest Full Name" class="text-xs rounded-lg border-slate-300 p-2" required />
                <input type="text" v-model="g.dietary_requirements" placeholder="Dietary notes (optional)" class="text-xs rounded-lg border-slate-300 p-2" />
              </div>
            </div>
          </div>

          <!-- Submit buttons -->
          <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
            <button type="button" @click="showMeetingRsvpModal = false" class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
            <button type="submit" :disabled="meetingRsvpForm.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
              Save Response
            </button>
          </div>
        </form>
      </div>
    </div>

  </MemberLayout>
</template>
