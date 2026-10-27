<script setup>
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import { ref } from 'vue';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Modal from '@/Components/Ui/Modal.vue';

defineProps({
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
  <MembersLayout title="Member portal" :club="club" :member-role="memberRole" active-tab="dashboard">

    <!-- Pending Approval Banner -->
    <Alert v-if="isPending" variant="warning" title="Your membership is waiting for approval">
      Your registration for <strong>{{ club.name }}</strong> has been submitted. A club officer or captain will review your invite request shortly.
    </Alert>

    <!-- Top Big Stat Cards Grid (Matching Admin Design) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- 1. Member Standing Card -->
      <Card class="flex flex-col justify-between space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Member standing</span>
          <Badge variant="success">Active #{{ memberNumber }}</Badge>
        </div>
        <div class="text-3xl font-semibold text-slate-900 dark:text-white tracking-tight">
          {{ memberRole.toUpperCase() }}
        </div>
        <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400">
          Good standing · {{ club.name }}
        </div>
      </Card>

      <!-- 2. Attendance Standing Score Card -->
      <Card class="flex flex-col justify-between space-y-3">
        <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Attendance rate</div>
        <div class="text-3xl font-semibold text-slate-900 dark:text-white tracking-tight tabular-nums">
          {{ attendanceRate }}%
        </div>
        <div class="text-xs text-slate-500 dark:text-slate-400">
          {{ attendedCount }} of {{ totalRsvps }} sessions attended
        </div>
      </Card>

    </div>

    <!-- Main Content Dual Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

      <!-- Left Column: Invited Meetings & Upcoming Events (2 cols) -->
      <div id="events-section" class="lg:col-span-2 space-y-8">

        <!-- Invited Meetings & Summons Section -->
        <div id="meetings-section" class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Meetings and summonses</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ meetings.length }} meetings invited</span>
          </div>

          <div v-if="meetings.length" class="space-y-4">
            <Card v-for="m in meetings" :key="m.id">
              <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                <div class="space-y-1 flex-1">
                  <div class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wide">{{ club.name }}</div>
                  <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ m.title }}</h3>
                  <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="building" /> {{ m.venue }}</span>
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" /> Starts {{ m.starts_at }} <span v-if="m.rehearsal_starts_at">(rehearsal {{ m.rehearsal_starts_at }})</span></span>
                  </p>
                  <p v-if="m.rsvp_cutoff_at" class="inline-flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-300 font-medium"><UiIcon name="warning" /> Catering deadline: {{ m.rsvp_cutoff_at }}</p>
                </div>

                <div class="flex flex-col gap-2.5 w-full sm:w-44 items-stretch flex-shrink-0">
                  <div class="flex flex-wrap gap-1.5 sm:justify-end">
                    <Badge v-if="m.user_rsvp?.attendance_status === 'attending_dining'" variant="success">
                      <UiIcon name="check" /> Attending with dining
                    </Badge>
                    <Badge v-else-if="m.user_rsvp?.attendance_status === 'attending_meeting_only'" variant="info">
                      <UiIcon name="check" /> Attending meeting only
                    </Badge>
                    <Badge v-else-if="m.user_rsvp?.attendance_status === 'apologies'" variant="danger">
                      <UiIcon name="x" /> Apologies sent
                    </Badge>
                    <Badge v-else variant="warning">
                      <UiIcon name="warning" /> Reply needed
                    </Badge>

                    <Badge v-if="m.user_rsvp?.payment_status === 'paid'" variant="success">Paid</Badge>
                    <Badge v-else-if="m.user_rsvp?.payment_status === 'waived'" variant="info">Waived</Badge>
                    <Badge v-else-if="m.user_rsvp?.attendance_status === 'attending_dining'" variant="warning">Unpaid</Badge>

                    <Badge v-if="m.is_cutoff_passed">Replies closed</Badge>
                  </div>

                  <Button size="sm" block :variant="m.user_rsvp ? 'secondary' : 'primary'" :disabled="m.is_cutoff_passed" @click="openMeetingRsvpModal(m)">
                    <UiIcon name="pencil" /> {{ m.user_rsvp ? 'Edit response' : 'Respond' }}
                  </Button>

                  <Button variant="secondary" size="sm" block :href="route('member.meetings.summons', { slug: club.slug, id: m.id })">
                    <UiIcon name="document" /> View summons
                  </Button>
                </div>
              </div>
            </Card>
          </div>

          <Card v-else class="text-center">
            <p class="py-2 text-sm text-slate-500 dark:text-slate-400">No published meeting summons at this time.</p>
          </Card>
        </div>

        <!-- Upcoming Events Section -->
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Upcoming events and training</h2>
            <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ events.length }} events scheduled</span>
          </div>

          <div v-if="events.length" class="space-y-4">
            <Card v-for="event in events" :key="event.id">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="event.user_rsvp?.checked_in_at" variant="success">
                      <UiIcon name="check" /> Checked in at {{ event.user_rsvp.checked_in_at }}
                    </Badge>
                    <Badge v-else-if="event.user_rsvp?.attendance_status === 'attending'" variant="success">
                      <UiIcon name="check" /> RSVP confirmed
                    </Badge>
                    <Badge v-if="event.has_dining" variant="info">3-course dining</Badge>
                  </div>
                  <h3 class="text-lg font-semibold text-slate-900 dark:text-white mt-1">{{ event.title }}</h3>
                  <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="building" /> {{ event.location }}</span>
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" /> {{ event.starts_at }}</span>
                  </p>
                </div>

                <Button size="sm" :variant="event.user_rsvp ? 'secondary' : 'primary'" class="self-start sm:self-auto" @click="openRsvpModal(event)">
                  {{ event.user_rsvp ? 'Edit RSVP & dining' : 'RSVP & select menu' }}
                </Button>
              </div>
            </Card>
          </div>

          <Card v-else class="text-center">
            <p class="py-2 text-sm text-slate-500 dark:text-slate-400">No upcoming social events at this time.</p>
          </Card>
        </div>

      </div>

      <!-- Right Column: Newsletters Feed & Dues Sidebar (1 col) -->
      <div id="dues-section" class="space-y-6">

        <!-- Published Newsletters Feed Card -->
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2"><UiIcon name="mail" class="text-slate-400" /> Club broadcasts</h3>
              <Badge>Live feed</Badge>
            </div>
          </template>

          <div v-if="newsletters && newsletters.length" class="space-y-3">
            <div v-for="item in newsletters" :key="item.id" @click="selectedNewsletter = item" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1 cursor-pointer hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Sent {{ item.sent_at }}</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ item.subject }}</div>
              <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">{{ item.content }}</p>
            </div>
          </div>

          <div v-else class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">
            No published email broadcasts yet.
          </div>
        </Card>

        <!-- My Active Dues Card -->
        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2"><UiIcon name="cash" class="text-slate-400" /> Membership dues</h3>
          </template>
          <div class="space-y-4">
            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Active plan</div>
              <div class="text-sm font-semibold text-slate-900 dark:text-white">Senior Rower Tier</div>
              <div class="text-xs text-slate-600 dark:text-slate-300 font-medium">{{ $cs }}35.00 a month</div>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
              Status: <Badge variant="success">Good standing</Badge>
            </div>
          </div>
        </Card>

        <!-- Attendance Stats Detail Card -->
        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2"><UiIcon name="chart" class="text-slate-400" /> Attendance</h3>
          </template>
          <div class="space-y-2 text-xs">
            <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-300">Sessions tracked</span>
              <span class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ totalRsvps }}</span>
            </div>
            <div class="flex justify-between items-center p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-300">Checked in or attended</span>
              <span class="font-semibold text-slate-900 dark:text-white tabular-nums">{{ attendedCount }}</span>
            </div>
          </div>
        </Card>

      </div>

    </div>

    <!-- Read Newsletter Modal -->
    <Modal :open="Boolean(selectedNewsletter)" :title="selectedNewsletter?.subject ?? ''" :subtitle="selectedNewsletter ? `Sent ${selectedNewsletter.sent_at}` : ''" size="lg" @close="selectedNewsletter = null">
      <div class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed max-h-80 overflow-y-auto prose prose-sm dark:prose-invert" v-html="selectedNewsletter.content">
      </div>

      <template #footer>
        <Button variant="secondary" @click="selectedNewsletter = null">Close</Button>
      </template>
    </Modal>

    <!-- Social Event RSVP Modal -->
    <EventBookingModal v-if="showDinnerModal && selectedEvent" :key="selectedEvent.id" :event="selectedEvent" :club-slug="club.slug" @close="showDinnerModal = false" />

    <!-- Member Meeting Summons RSVP Modal -->
    <MeetingRsvpModal v-if="showMeetingRsvpModal && selectedMeeting" :key="selectedMeeting.id" :meeting="selectedMeeting" :club-slug="club.slug" :rsvp="selectedMeeting.user_rsvp" @close="showMeetingRsvpModal = false" />

  </MembersLayout>
</template>
