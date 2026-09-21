<script setup>
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import { formatMoney } from '@/Utils/currency';
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  events: { type: Array, default: () => [] },
  meetings: { type: Array, default: () => [] },
});

const activeFilter = ref('all'); // 'all', 'meetings', 'events'

// Social Event RSVP Modal State
const selectedEvent = ref(null);
const showDinnerModal = ref(false);

const openRsvpModal = (event) => {
  selectedEvent.value = event;
  showDinnerModal.value = true;
};


// Meeting RSVP Modal State
const selectedMeeting = ref(null);
const showMeetingRsvpModal = ref(false);

const meetingRsvpForm = useForm({
  attendance_status: 'attending_dining',
  dietary_requirements: '',
  apology_reason: '',
  guests: [],
});

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

const formatDate = (dateVal) => {
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
};

const totalScheduleCount = computed(() => props.meetings.length + props.events.length);
</script>

<template>
  <MembersLayout title="My Events & RSVPs" :club="club" :member-role="memberRole" active-tab="events">
    
    <div class="space-y-6">
      
      <!-- Top Action & Summary Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-3 flex-wrap">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings, Events & Training Schedule</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
              {{ totalScheduleCount }} Scheduled
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">View official lodge meeting summons, confirm attendance & dining RSVPs, select 3-course menus, and access summons PDFs.</p>
        </div>

        <!-- Filter Tab Pill Buttons -->
        <div class="flex items-center gap-1.5 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl self-start md:self-auto text-xs font-bold">
          <button
            @click="activeFilter = 'all'"
            :class="['px-3 py-1.5 rounded-lg transition-all cursor-pointer', activeFilter === 'all' ? 'bg-white dark:bg-slate-900 text-slate-900 dark:text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white']"
          >
            All ({{ totalScheduleCount }})
          </button>
          <button
            @click="activeFilter = 'meetings'"
            :class="['px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1', activeFilter === 'meetings' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white']"
          >
            📜 Meetings ({{ meetings.length }})
          </button>
          <button
            @click="activeFilter = 'events'"
            :class="['px-3 py-1.5 rounded-lg transition-all cursor-pointer flex items-center gap-1', activeFilter === 'events' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white']"
          >
            🎪 Social Events ({{ events.length }})
          </button>
        </div>
      </div>

      <!-- SECTION 1: Official Lodge Meetings & Summons -->
      <div v-if="(activeFilter === 'all' || activeFilter === 'meetings') && meetings.length > 0" class="space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <span>📜 Official Meetings & Summons</span>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">{{ meetings.length }}</span>
          </h3>
        </div>

        <div class="space-y-4">
          <div
            v-for="meeting in meetings"
            :key="meeting.id"
            class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all space-y-4"
          >
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                  <!-- Attendance Status Badge -->
                  <span v-if="meeting.user_rsvp?.attendance_status === 'attending_dining'" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                    🟢 Attending Dining
                  </span>
                  <span v-else-if="meeting.user_rsvp?.attendance_status === 'attending_meeting_only'" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                    🔵 Attending Meeting Only
                  </span>
                  <span v-else-if="meeting.user_rsvp?.attendance_status === 'apologies'" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60">
                    🔴 Apologies Sent
                  </span>
                  <span v-else class="px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                    ⚠️ Response Required
                  </span>

                  <!-- Payment Status Badge if dining -->
                  <span v-if="meeting.user_rsvp && meeting.user_rsvp.attendance_status === 'attending_dining'" class="px-2 py-0.5 rounded text-xs font-bold" :class="meeting.user_rsvp.payment_status === 'paid' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-200' : (meeting.user_rsvp.payment_status === 'waived' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-200')">
                    {{ meeting.user_rsvp.payment_status === 'paid' ? '✅ PAID' : (meeting.user_rsvp.payment_status === 'waived' ? '🎁 WAIVED' : '💳 UNPAID') }}
                  </span>

                  <!-- Dining Cutoff Badge -->
                  <span v-if="meeting.rsvp_cutoff_at" class="px-2 py-0.5 rounded text-xs font-semibold" :class="meeting.is_cutoff_passed ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'">
                    ⏰ Cutoff: {{ meeting.rsvp_cutoff_at }}
                  </span>
                </div>

                <h4 class="text-lg font-bold text-slate-900 dark:text-white">{{ meeting.title || 'Regular Meeting' }}</h4>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-600 dark:text-slate-300">
                  <span>📅 <strong>Date:</strong> {{ formatDate(meeting.raw_meeting_date || meeting.meeting_date) }}</span>
                  <span>🕒 <strong>Start:</strong> {{ meeting.starts_at }} <span v-if="meeting.rehearsal_starts_at" class="text-slate-400">(Rehearsal: {{ meeting.rehearsal_starts_at }})</span></span>
                  <span>📍 <strong>Venue:</strong> {{ meeting.venue }}</span>
                  <span v-if="meeting.dress_code">👔 <strong>Dress:</strong> {{ meeting.dress_code }}</span>
                </div>
              </div>

              <!-- Meeting Actions -->
              <div class="flex flex-wrap items-center gap-2 self-start lg:self-center">
                <Link
                  :href="route('member.meetings.summons', { slug: club.slug, id: meeting.id })"
                  class="px-3.5 py-2 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-xs rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all flex items-center gap-1.5"
                >
                  📜 View Summons
                </Link>

                <a
                  :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })"
                  target="_blank"
                  class="px-3.5 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition-all flex items-center gap-1.5"
                >
                  🖨️ PDF
                </a>

                <button
                  @click="openMeetingRsvpModal(meeting)"
                  class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-md shadow-blue-600/20 transition-all cursor-pointer flex items-center gap-1.5"
                >
                  ✍️ {{ meeting.user_rsvp ? 'Edit Response' : 'Respond to Summons' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 2: Social Events, Regattas & Activities -->
      <div v-if="(activeFilter === 'all' || activeFilter === 'events') && events.length > 0" class="space-y-4">
        <div class="flex items-center justify-between pt-2">
          <h3 class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <span>🎪 Social Events, Regattas & Activities</span>
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full">{{ events.length }}</span>
          </h3>
        </div>

        <div class="space-y-4">
          <div
            v-for="event in events"
            :key="event.id"
            class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-all space-y-4"
          >
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div class="space-y-1.5">
                <div class="flex items-center gap-2 flex-wrap">
                  <span v-if="event.user_rsvp?.checked_in_at" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                    ✓ CHECKED IN @ {{ event.user_rsvp.checked_in_at }}
                  </span>
                  <span v-else-if="event.user_rsvp?.attendance_status === 'attending'" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                    RSVP CONFIRMED
                  </span>
                  <span v-if="event.has_dining" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                    🍽️ 3-COURSE DINING
                  </span>
                  <span v-if="event.booking_cutoff_days" class="px-2.5 py-0.5 rounded text-[10px] font-bold" :class="event.is_booking_closed ? 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60' : 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60'">
                    {{ event.is_booking_closed ? '🔒 BOOKINGS CLOSED' : `⏰ Cutoff: ${event.booking_cutoff_days} days before` }}
                  </span>
                </div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ event.title }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">📍 {{ event.location }} • 🕒 {{ event.starts_at }}</p>
                <p v-if="event.requires_payment && event.advertised && Number(event.advertised.headline) > 0" class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                  {{ formatMoney(event.advertised.headline) }}
                  <span v-if="event.advertised.has_saving" class="font-normal text-emerald-600 dark:text-emerald-400">or {{ formatMoney(event.advertised.lowest) }} if you pay online</span>
                  <span v-if="event.places_left !== null" class="ml-2 font-normal text-slate-500 dark:text-slate-400">· {{ event.places_left > 0 ? `${event.places_left} places left` : 'full' }}</span>
                </p>
                <p v-if="event.description" class="text-xs text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">{{ event.description }}</p>
              </div>

              <button
                v-if="!event.is_booking_closed"
                @click="openRsvpModal(event)"
                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all self-start sm:self-auto cursor-pointer"
              >
                {{ event.user_rsvp ? 'Edit RSVP & Dining' : 'RSVP & Select Menu' }}
              </button>
              <span v-else class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-400 font-bold text-xs rounded-xl border border-slate-200 dark:border-slate-800 self-start sm:self-auto">
                Bookings Closed
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State if no meetings or events found for filter -->
      <div v-if="totalScheduleCount === 0" class="bg-white dark:bg-slate-900 rounded-2xl p-12 text-center border border-slate-200/80 dark:border-slate-800/80 text-slate-500 dark:text-slate-400 text-sm">
        No upcoming meetings, events, or regattas scheduled for {{ club.name }} at this time.
      </div>

    </div>

    <!-- Social Event RSVP & 3-Course Dining Modal -->
    <EventBookingModal v-if="showDinnerModal && selectedEvent" :key="selectedEvent.id" :event="selectedEvent" :club-slug="club.slug" @close="showDinnerModal = false" />

    <!-- Member Meeting Summons RSVP Modal -->
    <div v-if="showMeetingRsvpModal && selectedMeeting" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm overflow-y-auto">
      <div class="bg-white dark:bg-slate-900 rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 my-8">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
          <h3 class="text-base font-bold text-slate-900 dark:text-white">
            ✍️ Respond to Summons — {{ selectedMeeting.title || formatDate(selectedMeeting.raw_meeting_date || selectedMeeting.meeting_date) }}
          </h3>
          <button @click="showMeetingRsvpModal = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 text-lg font-bold cursor-pointer">&times;</button>
        </div>

        <form @submit.prevent="submitMeetingRsvp" class="space-y-4">
          <!-- Attendance Status -->
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Select Attendance</label>
            <div class="grid grid-cols-3 gap-2">
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'attending_dining' ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-500 text-emerald-800 dark:text-emerald-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="attending_dining" class="sr-only" />
                🟢 Dining & Meeting
              </label>
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'attending_meeting_only' ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 text-blue-800 dark:text-blue-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="attending_meeting_only" class="sr-only" />
                🔵 Meeting Only
              </label>
              <label :class="['flex items-center justify-center p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all', meetingRsvpForm.attendance_status === 'apologies' ? 'bg-rose-50 dark:bg-rose-950/40 border-rose-500 text-rose-800 dark:text-rose-200' : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300']">
                <input type="radio" v-model="meetingRsvpForm.attendance_status" value="apologies" class="sr-only" />
                🔴 Apologies
              </label>
            </div>
          </div>

          <!-- Dietary Requirements (if dining) -->
          <div v-if="meetingRsvpForm.attendance_status === 'attending_dining'">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Your Dietary Requirements</label>
            <input type="text" v-model="meetingRsvpForm.dietary_requirements" placeholder="e.g. Vegetarian, Gluten-free, Nut allergy" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5" />
          </div>

          <!-- Apology Reason (if apologies) -->
          <div v-if="meetingRsvpForm.attendance_status === 'apologies'">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1">Apology Reason / Notes</label>
            <textarea v-model="meetingRsvpForm.apology_reason" rows="2" placeholder="e.g. Away on business, unwell" class="w-full text-xs rounded-xl border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-blue-500 p-2.5"></textarea>
          </div>

          <!-- Guests Section (if dining) -->
          <div v-if="meetingRsvpForm.attendance_status === 'attending_dining'" class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <div class="flex items-center justify-between">
              <label class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">Accompanying Guests</label>
              <button type="button" @click="addMeetingGuestRow" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 bg-blue-50 dark:bg-blue-950/40 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60 cursor-pointer">
                + Add Guest
              </button>
            </div>

            <div v-for="(g, idx) in meetingRsvpForm.guests" :key="idx" class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Guest #{{ idx + 1 }}</span>
                <button type="button" @click="removeMeetingGuestRow(idx)" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-200 text-xs font-bold cursor-pointer">Remove</button>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input type="text" v-model="g.guest_name" placeholder="Guest Full Name" class="text-xs rounded-lg border-slate-300 dark:border-slate-700 p-2" required />
                <input type="text" v-model="g.dietary_requirements" placeholder="Dietary notes (optional)" class="text-xs rounded-lg border-slate-300 dark:border-slate-700 p-2" />
              </div>
            </div>
          </div>

          <!-- Submit buttons -->
          <div class="flex justify-end gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
            <button type="button" @click="showMeetingRsvpModal = false" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl cursor-pointer">Cancel</button>
            <button type="submit" :disabled="meetingRsvpForm.processing" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md cursor-pointer disabled:opacity-50">
              Save Response
            </button>
          </div>
        </form>
      </div>
    </div>

  </MembersLayout>
</template>
