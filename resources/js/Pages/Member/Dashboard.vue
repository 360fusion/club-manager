<script setup>
import EventBookingModal from '@/Components/Events/EventBookingModal.vue';
import { formatLocal } from '@/Utils/localDateTime';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import { computed, ref } from 'vue';
import { formatMoney } from '@/Utils/currency';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Modal from '@/Components/Ui/Modal.vue';
import SanitizedHtml from '@/Components/Ui/SanitizedHtml.vue';

const props = defineProps({
  club: { type: Object, required: true },
  isPending: { type: Boolean, default: false },
  memberRole: { type: String, default: 'member' },
  duesOwed: { type: Number, default: 0 },
  subscription: { type: Object, default: null },
  organiserTools: { type: Object, default: null },
  officers: { type: Array, default: () => [] },
  documents: { type: Array, default: () => [] },
  meetings: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  newsletters: { type: Array, default: () => [] },
});

const toolIcons = { event_night: 'check', catering: 'clipboard', collections: 'heart', accounts: 'chart', add_booking: 'user-add', send_notice: 'mail' };

const SUBSCRIPTION_BADGES = {
  paid: { variant: 'success', label: 'Paid' },
  waived: { variant: 'neutral', label: 'Waived' },
  partially_paid: { variant: 'info', label: 'Part paid' },
  arrears_warning: { variant: 'danger', label: 'In arrears' },
};
const subscriptionBadge = computed(() => SUBSCRIPTION_BADGES[props.subscription?.status] ?? { variant: 'warning', label: 'Unpaid' });

const subtitle = computed(() => [props.club.lodge_number ? `No. ${props.club.lodge_number}` : null, props.club.meets].filter(Boolean).join(' · '));

const subscriptionHint = computed(() => {
  const subscription = props.subscription;
  if (!subscription) {
    return 'Your lodge has not recorded a subscription for you yet.';
  }
  const due = subscription.due_date ? ` Due ${subscription.due_date}.` : '';
  const earlier = props.duesOwed > subscription.balance ? ` You owe ${formatMoney(props.duesOwed)} in all.` : '';
  if (['paid', 'waived'].includes(subscription.status)) {
    return `Nothing to pay for this year.${earlier}`;
  }
  return `${formatMoney(subscription.balance)} still to pay.${due}${earlier}`;
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
  <MembersLayout :title="club.name" :club="club" :member-role="memberRole" active-tab="dashboard" own-heading>

    <header class="flex items-center gap-3 rounded-2xl bg-blue-900 px-4 py-4 text-white dark:bg-blue-950 sm:px-6">
      <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-white/20 bg-white/10 text-gold-400">
        <UiIcon name="building" :size="24" />
      </div>
      <div class="min-w-0">
        <h1 class="text-[22px] font-semibold leading-7">{{ club.name }}</h1>
        <p v-if="subtitle" class="text-xs text-blue-100">{{ subtitle }}</p>
      </div>
    </header>

    <!-- Pending Approval Banner -->
    <Alert v-if="isPending" variant="warning" title="Your membership is waiting for approval">
      Your registration for <strong>{{ club.name }}</strong> has been submitted. A club officer or captain will review your invite request shortly.
    </Alert>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2 lg:gap-6">
      <section v-if="organiserTools" class="space-y-3 rounded-2xl border border-gold-500 bg-gold-50 p-4 shadow-sm dark:border-gold-400/60 dark:bg-gold-950">
        <div class="flex items-center gap-3">
          <h2 class="flex-1 text-xs font-bold uppercase tracking-wide text-gold-700 dark:text-gold-300">Organiser tools</h2>
          <span v-if="organiserTools.office" class="text-xs text-slate-500 dark:text-slate-400">You're {{ organiserTools.office }}</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <Link v-for="tool in organiserTools.links" :key="tool.key" :href="tool.href" class="flex min-h-[52px] items-center gap-2.5 rounded-xl border border-slate-200 bg-slate-50 px-3 text-[13px] font-semibold text-slate-900 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:hover:border-slate-600">
            <UiIcon :name="toolIcons[tool.key] ?? 'link'" :size="18" class="text-gold-700 dark:text-gold-300" />
            {{ tool.label }}
          </Link>
        </div>
      </section>

      <Card id="dues-section" padding="sm" class="space-y-2.5">
        <div class="flex items-center gap-3">
          <div class="min-w-0 flex-1">
            <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ subscription ? `Subscription ${subscription.year}` : 'Subscription' }}</h2>
            <p v-if="subscription" class="mt-1 font-semibold text-slate-900 tabular-nums dark:text-white">{{ $money(subscription.amount_due) }}</p>
          </div>
          <Badge v-if="subscription" :variant="subscriptionBadge.variant">{{ subscriptionBadge.label }}</Badge>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ subscriptionHint }}</p>
        <div class="flex flex-wrap gap-x-5">
          <Link :href="route('members.dues', { club: club.slug })" class="inline-flex min-h-11 items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">See your dues</Link>
          <Link :href="route('member.record', { slug: club.slug })" class="inline-flex min-h-11 items-center text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">My Masonic record</Link>
        </div>
      </Card>

      <Card v-if="officers.length" padding="sm" class="space-y-2.5">
        <h2 class="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Officers</h2>
        <dl class="grid grid-cols-[110px_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-[13px] leading-[18px]">
          <template v-for="(officer, index) in officers" :key="index">
            <dt class="text-slate-500 dark:text-slate-400">{{ officer.role }}</dt>
            <dd class="font-medium text-slate-900 dark:text-white">{{ officer.name }}</dd>
          </template>
        </dl>
      </Card>

      <Card v-if="documents.length" padding="sm">
        <h2 class="mb-1.5 text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Documents</h2>
        <a v-for="doc in documents" :key="doc.href" :href="doc.href" target="_blank" rel="noopener" class="flex min-h-11 items-center gap-2.5 border-t border-slate-200 text-slate-900 first-of-type:border-t-0 hover:text-blue-600 dark:border-slate-800 dark:text-white dark:hover:text-blue-400">
          <UiIcon name="document" :size="18" class="text-slate-500 dark:text-slate-400" />
          <span class="min-w-0 flex-1 truncate">{{ doc.title }}</span>
          <span v-if="doc.type" class="text-xs text-slate-500 dark:text-slate-400">{{ doc.type }}</span>
        </a>
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
            <span v-if="meetings.length" class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ meetings.length }} {{ meetings.length === 1 ? 'meeting' : 'meetings' }} invited</span>
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
            <span v-if="events.length" class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ events.length }} {{ events.length === 1 ? 'event' : 'events' }} scheduled</span>
          </div>

          <div v-if="events.length" class="space-y-4">
            <Card v-for="event in events" :key="event.id">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <Badge v-if="event.user_rsvp?.checked_in_at" variant="success">
                      <UiIcon name="check" /> Checked in {{ formatLocal(event.user_rsvp.checked_in_at) }}
                    </Badge>
                    <Badge v-else-if="event.user_rsvp?.attendance_status === 'attending'" variant="success">
                      <UiIcon name="check" /> RSVP confirmed
                    </Badge>
                    <Badge v-if="event.has_dining" variant="info">Dinner</Badge>
                  </div>
                  <h3 class="text-lg font-semibold text-slate-900 dark:text-white mt-1">{{ event.title }}</h3>
                  <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="building" /> {{ event.location }}</span>
                    <span class="inline-flex items-center gap-1.5"><UiIcon name="clock" /> {{ event.starts_at }}</span>
                  </p>
                </div>

                <Button size="sm" :variant="event.user_rsvp ? 'secondary' : 'primary'" class="self-start sm:self-auto" @click="openRsvpModal(event)">
                  {{ event.user_rsvp ? 'Change booking' : 'Book a place' }}
                </Button>
              </div>
            </Card>
          </div>

          <Card v-else class="text-center">
            <p class="py-2 text-sm text-slate-500 dark:text-slate-400">No upcoming social events at this time.</p>
          </Card>
        </div>

      </div>

      <!-- Right Column: Newsletters Feed (1 col) -->
      <div class="space-y-6">

        <!-- Published Newsletters Feed Card -->
        <Card>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2"><UiIcon name="mail" class="text-slate-500 dark:text-slate-400" /> Lodge broadcasts</h3>
              <Badge>Live feed</Badge>
            </div>
          </template>

          <div v-if="newsletters && newsletters.length" class="space-y-3">
            <div v-for="item in newsletters" :key="item.id" @click="selectedNewsletter = item" class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl space-y-1 cursor-pointer hover:border-slate-300 dark:hover:border-slate-700 transition-colors">
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Sent {{ item.sent_at }}</div>
              <div class="text-sm font-medium text-slate-900 dark:text-white">{{ item.subject }}</div>
              <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">{{ item.preview }}</p>
            </div>
          </div>

          <div v-else class="text-sm text-slate-500 dark:text-slate-400 text-center py-4">
            No published email broadcasts yet.
          </div>
        </Card>

      </div>

    </div>

    <!-- Read Newsletter Modal -->
    <Modal :open="Boolean(selectedNewsletter)" :title="selectedNewsletter?.subject ?? ''" :subtitle="selectedNewsletter ? `Sent ${selectedNewsletter.sent_at}` : ''" size="lg" @close="selectedNewsletter = null">
      <SanitizedHtml class="text-sm text-slate-700 dark:text-slate-200 leading-relaxed max-h-80 overflow-y-auto prose prose-sm dark:prose-invert" :html="selectedNewsletter.content" />

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
