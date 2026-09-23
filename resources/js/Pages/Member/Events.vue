<script setup>
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import { formatMoney } from '@/Utils/currency';
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  memberRole: { type: String, default: 'member' },
  events: { type: Array, default: () => [] },
  meetings: { type: Array, default: () => [] },
});

const activeFilter = ref('all'); // 'all', 'meetings', 'events'

// Stripe and PayPal send people back here with ?payment=success or ?payment=cancelled.
const returned = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('payment') : null;

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

const openMeetingRsvpModal = (meeting) => {
  selectedMeeting.value = meeting;
  showMeetingRsvpModal.value = true;
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
      <div v-if="returned === 'success'" class="rounded-xl border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/30 dark:text-emerald-200" role="status">Thank you. Your payment is being confirmed and will show on your booking in a moment.</div>
      <div v-else-if="returned === 'cancelled'" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-200" role="status">Payment cancelled. You have not been charged, and you can pay any time from your booking.</div>

      
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
    <MeetingRsvpModal v-if="showMeetingRsvpModal && selectedMeeting" :key="selectedMeeting.id" :meeting="selectedMeeting" :club-slug="club.slug" :rsvp="selectedMeeting.user_rsvp" @close="showMeetingRsvpModal = false" />

  </MembersLayout>
</template>
