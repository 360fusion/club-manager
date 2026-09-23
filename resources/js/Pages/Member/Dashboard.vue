<script setup>
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';

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

const openRsvpModal = (event) => {
  selectedEvent.value = event;
  showDinnerModal.value = true;
};

const openMeetingRsvpModal = (meeting) => {
  selectedMeeting.value = meeting;
  showMeetingRsvpModal.value = true;
};

</script>

<template>
  <MembersLayout title="Member Portal" :club="club" :member-role="memberRole" active-tab="dashboard">
    
    <!-- Pending Approval Banner -->
    <div v-if="isPending" class="p-6 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-200 rounded-2xl space-y-1">
      <div class="flex items-center gap-2 font-bold text-xs uppercase tracking-wider text-amber-700 dark:text-amber-300">
        <span>⏳ Membership Pending Admin Approval</span>
      </div>
      <p class="text-xs leading-relaxed">
        Your registration for <strong>{{ club.name }}</strong> has been submitted. A club officer or captain will review your invite request shortly.
      </p>
    </div>

    <!-- Top Big Stat Cards Grid (Matching Admin Design) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      
      <!-- 1. Member Standing Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col justify-between space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Member Standing</span>
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
            Active #{{ memberNumber }}
          </span>
        </div>
        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
          {{ memberRole.toUpperCase() }}
        </div>
        <div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
          Good Standing • {{ club.name }}
        </div>
      </div>

      <!-- 2. Attendance Standing Score Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 flex flex-col justify-between space-y-3">
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Attendance Rate Score</div>
        <div class="text-3xl font-black text-slate-900 dark:text-white tracking-tight">
          {{ attendanceRate }}%
        </div>
        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
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
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">📜 Invited Meetings & Summons</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ meetings.length }} meetings invited</span>
          </div>

          <div v-if="meetings.length" class="space-y-4">
            <div v-for="m in meetings" :key="m.id" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div class="space-y-1 flex-1">
                  <div class="text-xs sm:text-sm font-black text-blue-600 dark:text-blue-400 uppercase tracking-wide">{{ club.name }}</div>
                  <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ m.title }}</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">📍 {{ m.venue }} • 🕒 Start: {{ m.starts_at }} <span v-if="m.rehearsal_starts_at">(Rehearsal: {{ m.rehearsal_starts_at }})</span></p>
                  <p v-if="m.rsvp_cutoff_at" class="text-[11px] text-amber-700 dark:text-amber-300 font-semibold">⏰ Catering Deadline: {{ m.rsvp_cutoff_at }}</p>
                </div>

                <div class="flex flex-col gap-2.5 w-full sm:w-44 items-stretch flex-shrink-0">
                  <div class="w-full flex flex-col gap-1 items-stretch">
                    <span v-if="m.user_rsvp?.attendance_status === 'attending_dining'" class="w-full text-center justify-center px-3 py-1.5 rounded-xl text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 block truncate">
                      🟢 ATTENDING DINING
                    </span>
                    <span v-else-if="m.user_rsvp?.attendance_status === 'attending_meeting_only'" class="w-full text-center justify-center px-3 py-1.5 rounded-xl text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 block truncate">
                      🔵 ATTENDING MEETING ONLY
                    </span>
                    <span v-else-if="m.user_rsvp?.attendance_status === 'apologies'" class="w-full text-center justify-center px-3 py-1.5 rounded-xl text-[10px] font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 block truncate">
                      🔴 APOLOGIES SENT
                    </span>
                    <span v-else class="w-full text-center justify-center px-3 py-1.5 rounded-xl text-[10px] font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 block truncate">
                      ⚠️ RSVP AWAITING
                    </span>

                    <span v-if="m.user_rsvp?.payment_status === 'paid'" class="w-full text-center justify-center px-3 py-1 rounded-xl text-[10px] font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200 border border-emerald-300 dark:border-emerald-700/60 block truncate">
                      ✅ PAID
                    </span>
                    <span v-else-if="m.user_rsvp?.payment_status === 'waived'" class="w-full text-center justify-center px-3 py-1 rounded-xl text-[10px] font-bold bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60 block truncate">
                      🎁 WAIVED
                    </span>
                    <span v-else-if="m.user_rsvp?.attendance_status === 'attending_dining'" class="w-full text-center justify-center px-3 py-1 rounded-xl text-[10px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700/60 block truncate">
                      💳 UNPAID
                    </span>
                    
                    <span v-if="m.is_cutoff_passed" class="w-full text-center justify-center px-3 py-1 rounded-xl text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 block truncate">
                      CUTOFF PASSED
                    </span>
                  </div>

                  <button @click="openMeetingRsvpModal(m)" :disabled="m.is_cutoff_passed" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md transition-all disabled:opacity-50 cursor-pointer text-center justify-center flex items-center gap-1">
                    ✍️ {{ m.user_rsvp ? 'Edit Response' : 'Respond' }}
                  </button>

                  <Link :href="route('member.meetings.summons', { slug: club.slug, id: m.id })" class="w-full px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl border border-slate-200 dark:border-slate-800 transition-all flex items-center justify-center gap-1 text-center">
                    📄 View Summons
                  </Link>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="bg-white dark:bg-slate-900 rounded-2xl p-8 text-center border border-slate-200/80 dark:border-slate-800/80 text-slate-400 text-xs">
            No published meeting summons at this time.
          </div>
        </div>

        <!-- Upcoming Events Section -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">📅 Upcoming Social Events & Training</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ events.length }} events scheduled</span>
          </div>

          <div v-if="events.length" class="space-y-4">
            <div v-for="event in events" :key="event.id" class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all space-y-4">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span v-if="event.user_rsvp?.checked_in_at" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                      ✓ CHECKED IN @ {{ event.user_rsvp.checked_in_at }}
                    </span>
                    <span v-else-if="event.user_rsvp?.attendance_status === 'attending'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                      RSVP CONFIRMED
                    </span>
                    <span v-if="event.has_dining" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                      🍽️ 3-COURSE DINING
                    </span>
                  </div>
                  <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-1">{{ event.title }}</h3>
                  <p class="text-xs text-slate-500 dark:text-slate-400">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
                </div>

                <button @click="openRsvpModal(event)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all self-start sm:self-auto cursor-pointer">
                  {{ event.user_rsvp ? 'Edit RSVP & Dining' : 'RSVP & Select Menu' }}
                </button>
              </div>
            </div>
          </div>

          <div v-else class="bg-white dark:bg-slate-900 rounded-2xl p-8 text-center border border-slate-200/80 dark:border-slate-800/80 text-slate-500 dark:text-slate-400 text-xs">
            No upcoming social events at this time.
          </div>
        </div>

      </div>

      <!-- Right Column: Newsletters Feed & Dues Sidebar (1 col) -->
      <div id="dues-section" class="space-y-6">
        
        <!-- Published Newsletters Feed Card -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">📧 Club Broadcasts</h3>
            <span class="text-[10px] font-bold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800/60 uppercase">Live Feed</span>
          </div>

          <div v-if="newsletters && newsletters.length" class="space-y-3">
            <div v-for="item in newsletters" :key="item.id" @click="selectedNewsletter = item" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1 cursor-pointer hover:border-slate-300 dark:hover:border-slate-700 transition-all">
              <div class="text-[10px] font-semibold text-slate-400">Sent {{ item.sent_at }}</div>
              <div class="text-xs font-bold text-slate-900 dark:text-white">{{ item.subject }}</div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">{{ item.content }}</p>
            </div>
          </div>

          <div v-else class="text-xs text-slate-400 text-center py-4">
            No published email broadcasts yet.
          </div>
        </div>

        <!-- My Active Dues Card -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">💳 Membership Dues</h3>
          <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
            <div class="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Plan</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white">Senior Rower Tier</div>
            <div class="text-xs text-emerald-700 dark:text-emerald-300 font-bold">{{ $cs }}35.00 / Monthly Dues</div>
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400 pt-1">
            Status: <strong class="text-emerald-700 dark:text-emerald-300 uppercase font-mono">Good Standing</strong>
          </div>
        </div>

        <!-- Attendance Stats Detail Card -->
        <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
          <h3 class="text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3">📊 Attendance Log</h3>
          <div class="space-y-2 text-xs">
            <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-300">Total Tracked Sessions</span>
              <span class="font-bold text-slate-900 dark:text-white font-mono">{{ totalRsvps }}</span>
            </div>
            <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-300">Checked In / Attended</span>
              <span class="font-bold text-emerald-700 dark:text-emerald-300 font-mono">{{ attendedCount }}</span>
            </div>
          </div>
        </div>

      </div>

    </div>

    <!-- Read Newsletter Modal -->
    <div v-if="selectedNewsletter" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 sm:p-8 max-w-lg w-full space-y-4 shadow-2xl border border-slate-200 dark:border-slate-800">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <div>
            <span class="text-[10px] font-semibold text-slate-400">Sent {{ selectedNewsletter.sent_at }}</span>
            <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-0.5">{{ selectedNewsletter.subject }}</h3>
          </div>
          <button @click="selectedNewsletter = null" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 font-bold text-lg">&times;</button>
        </div>

        <div class="text-xs text-slate-700 dark:text-slate-200 leading-relaxed max-h-80 overflow-y-auto prose prose-sm" v-html="selectedNewsletter.content">
        </div>

        <div class="pt-2 text-right">
          <button @click="selectedNewsletter = null" class="px-5 py-2 bg-slate-900 dark:bg-slate-700 text-white font-bold text-xs rounded-xl">
            Close
          </button>
        </div>
      </div>
    </div>

    <!-- Social Event RSVP Modal -->
    <EventBookingModal v-if="showDinnerModal && selectedEvent" :key="selectedEvent.id" :event="selectedEvent" :club-slug="club.slug" @close="showDinnerModal = false" />

    <!-- Member Meeting Summons RSVP Modal -->
    <MeetingRsvpModal v-if="showMeetingRsvpModal && selectedMeeting" :key="selectedMeeting.id" :meeting="selectedMeeting" :club-slug="club.slug" :rsvp="selectedMeeting.user_rsvp" @close="showMeetingRsvpModal = false" />

  </MembersLayout>
</template>
