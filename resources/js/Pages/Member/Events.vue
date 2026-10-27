<script setup>
import { formatMeetingDate } from '@/Utils/lodgeDates';
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import { formatMoney } from '@/Utils/currency';
import { ref, computed } from 'vue';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';

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


const totalScheduleCount = computed(() => props.meetings.length + props.events.length);

const filterClass = (isActive) => [
  'inline-flex items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-1.5 font-medium transition-colors',
  isActive
    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white'
    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white',
];
</script>

<template>
  <MembersLayout title="My events and RSVPs" :club="club" :member-role="memberRole" active-tab="events">

    <div class="space-y-6">
      <Alert v-if="returned === 'success'" variant="success">Thank you. Your payment is being confirmed and will show on your booking in a moment.</Alert>
      <Alert v-else-if="returned === 'cancelled'" variant="warning">Payment cancelled. You have not been charged, and you can pay any time from your booking.</Alert>

      <!-- Top Action & Summary Header -->
      <Card>
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 flex-wrap">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings, events and training</h2>
              <Badge variant="info">{{ totalScheduleCount }} scheduled</Badge>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">See lodge meeting summonses, reply for the meeting and dining, choose your menu and download summons PDFs.</p>
          </div>

          <!-- Filter Tab Pill Buttons -->
          <div class="flex items-center gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl self-start md:self-auto text-xs">
            <button type="button" :class="filterClass(activeFilter === 'all')" @click="activeFilter = 'all'">
              All ({{ totalScheduleCount }})
            </button>
            <button type="button" :class="filterClass(activeFilter === 'meetings')" @click="activeFilter = 'meetings'">
              <UiIcon name="document" /> Meetings ({{ meetings.length }})
            </button>
            <button type="button" :class="filterClass(activeFilter === 'events')" @click="activeFilter = 'events'">
              <UiIcon name="sparkles" /> Social events ({{ events.length }})
            </button>
          </div>
        </div>
      </Card>

      <!-- SECTION 1: Official Lodge Meetings & Summons -->
      <section v-if="(activeFilter === 'all' || activeFilter === 'meetings') && meetings.length > 0" class="space-y-4">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <UiIcon name="document" class="text-slate-400" /> Meetings and summonses
          <Badge>{{ meetings.length }}</Badge>
        </h3>

        <div class="space-y-4">
          <Card v-for="meeting in meetings" :key="meeting.id">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              <div class="space-y-2">
                <div class="flex items-center gap-2 flex-wrap">
                  <!-- Attendance Status Badge -->
                  <Badge v-if="meeting.user_rsvp?.attendance_status === 'attending_dining'" variant="success">
                    <UiIcon name="check" /> Attending with dining
                  </Badge>
                  <Badge v-else-if="meeting.user_rsvp?.attendance_status === 'attending_meeting_only'" variant="info">
                    <UiIcon name="check" /> Attending meeting only
                  </Badge>
                  <Badge v-else-if="meeting.user_rsvp?.attendance_status === 'apologies'" variant="danger">
                    <UiIcon name="x" /> Apologies sent
                  </Badge>
                  <Badge v-else variant="warning">
                    <UiIcon name="warning" /> Reply needed
                  </Badge>

                  <!-- Payment Status Badge if dining -->
                  <Badge v-if="meeting.user_rsvp && meeting.user_rsvp.attendance_status === 'attending_dining'" :variant="meeting.user_rsvp.payment_status === 'paid' ? 'success' : (meeting.user_rsvp.payment_status === 'waived' ? 'info' : 'warning')">
                    {{ meeting.user_rsvp.payment_status === 'paid' ? 'Paid' : (meeting.user_rsvp.payment_status === 'waived' ? 'Waived' : 'Unpaid') }}
                  </Badge>

                  <!-- Dining Cutoff Badge -->
                  <Badge v-if="meeting.rsvp_cutoff_at" :variant="meeting.is_cutoff_passed ? 'danger' : 'neutral'">
                    <UiIcon name="clock" /> Replies close {{ meeting.rsvp_cutoff_at }}
                  </Badge>
                </div>

                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">{{ meeting.title || 'Regular meeting' }}</h4>
                <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-600 dark:text-slate-300">
                  <span class="inline-flex items-center gap-1.5"><UiIcon name="calendar" class="text-slate-400" /> {{ formatMeetingDate(meeting.raw_meeting_date || meeting.meeting_date) }}</span>
                  <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" class="text-slate-400" /> Starts {{ meeting.starts_at }} <span v-if="meeting.rehearsal_starts_at" class="text-slate-400">(rehearsal {{ meeting.rehearsal_starts_at }})</span></span>
                  <span class="inline-flex items-center gap-1.5"><UiIcon name="building" class="text-slate-400" /> {{ meeting.venue }}</span>
                  <span v-if="meeting.dress_code" class="inline-flex items-center gap-1.5"><UiIcon name="user" class="text-slate-400" /> Dress: {{ meeting.dress_code }}</span>
                </div>
              </div>

              <!-- Meeting Actions -->
              <div class="flex flex-wrap items-center gap-2 self-start lg:self-center">
                <Button variant="secondary" size="sm" :href="route('member.meetings.summons', { slug: club.slug, id: meeting.id })">
                  <UiIcon name="document" /> View summons
                </Button>

                <a
                  :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })"
                  target="_blank"
                  class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                  <UiIcon name="printer" /> PDF
                </a>

                <Button size="sm" :variant="meeting.user_rsvp ? 'secondary' : 'primary'" @click="openMeetingRsvpModal(meeting)">
                  <UiIcon name="pencil" /> {{ meeting.user_rsvp ? 'Edit response' : 'Respond to summons' }}
                </Button>
              </div>
            </div>
          </Card>
        </div>
      </section>

      <!-- SECTION 2: Social Events, Regattas & Activities -->
      <section v-if="(activeFilter === 'all' || activeFilter === 'events') && events.length > 0" class="space-y-4">
        <h3 class="pt-2 text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <UiIcon name="sparkles" class="text-slate-400" /> Social events and activities
          <Badge>{{ events.length }}</Badge>
        </h3>

        <div class="space-y-4">
          <Card v-for="event in events" :key="event.id">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div class="space-y-1.5">
                <div class="flex items-center gap-2 flex-wrap">
                  <Badge v-if="event.user_rsvp?.checked_in_at" variant="success">
                    <UiIcon name="check" /> Checked in at {{ event.user_rsvp.checked_in_at }}
                  </Badge>
                  <Badge v-else-if="event.user_rsvp?.attendance_status === 'attending'" variant="success">
                    <UiIcon name="check" /> RSVP confirmed
                  </Badge>
                  <Badge v-if="event.has_dining" variant="info">3-course dining</Badge>
                  <Badge v-if="event.booking_cutoff_days" :variant="event.is_booking_closed ? 'danger' : 'warning'">
                    <UiIcon :name="event.is_booking_closed ? 'lock' : 'clock'" />
                    {{ event.is_booking_closed ? 'Bookings closed' : `Bookings close ${event.booking_cutoff_days} days before` }}
                  </Badge>
                </div>
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ event.title }}</h3>
                <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                  <span class="inline-flex items-center gap-1.5"><UiIcon name="building" /> {{ event.location }}</span>
                  <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" /> {{ event.starts_at }}</span>
                </p>
                <p v-if="event.requires_payment && event.advertised && Number(event.advertised.headline) > 0" class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                  {{ formatMoney(event.advertised.headline) }}
                  <span v-if="event.advertised.has_saving" class="font-normal text-emerald-600 dark:text-emerald-400">or {{ formatMoney(event.advertised.lowest) }} if you pay online</span>
                  <span v-if="event.places_left !== null" class="ml-2 font-normal text-slate-500 dark:text-slate-400">· {{ event.places_left > 0 ? `${event.places_left} places left` : 'full' }}</span>
                </p>
                <p v-if="event.description" class="text-xs text-slate-600 dark:text-slate-300 mt-1 leading-relaxed">{{ event.description }}</p>
              </div>

              <Button
                v-if="!event.is_booking_closed"
                size="sm"
                :variant="event.user_rsvp ? 'secondary' : 'primary'"
                class="self-start sm:self-auto"
                @click="openRsvpModal(event)"
              >
                {{ event.user_rsvp ? 'Edit RSVP & dining' : 'RSVP & select menu' }}
              </Button>
              <Badge v-else class="self-start sm:self-auto">Bookings closed</Badge>
            </div>
          </Card>
        </div>
      </section>

      <!-- Empty State if no meetings or events found for filter -->
      <Card v-if="totalScheduleCount === 0" class="text-center">
        <p class="py-6 text-sm text-slate-500 dark:text-slate-400">No upcoming meetings, events, or regattas scheduled for {{ club.name }} at this time.</p>
      </Card>

    </div>

    <!-- Social Event RSVP & 3-Course Dining Modal -->
    <EventBookingModal v-if="showDinnerModal && selectedEvent" :key="selectedEvent.id" :event="selectedEvent" :club-slug="club.slug" @close="showDinnerModal = false" />

    <!-- Member Meeting Summons RSVP Modal -->
    <MeetingRsvpModal v-if="showMeetingRsvpModal && selectedMeeting" :key="selectedMeeting.id" :meeting="selectedMeeting" :club-slug="club.slug" :rsvp="selectedMeeting.user_rsvp" @close="showMeetingRsvpModal = false" />

  </MembersLayout>
</template>
