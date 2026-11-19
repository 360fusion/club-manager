<script setup>
import { formatEventTime } from '@/Utils/localDateTime';
import { formatMeetingDate } from '@/Utils/lodgeDates';
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
  club: { type: Object, required: true },
  meeting: { type: Object, required: true },
  members: { type: Array, default: () => [] },
  userRsvp: { type: Object, default: null },
  secretaryUser: { type: Object, default: null },
  worshipfulMaster: { type: Object, default: null },
  memberRole: { type: String, default: 'member' },
  isCutoffPassed: { type: Boolean, default: false },
  owed: { type: String, default: null },
  payWith: { type: Array, default: () => [] },
  takesBank: { type: Boolean, default: false },
  canRecordCollections: { type: Boolean, default: false },
  saidPaidAt: { type: String, default: null },
  charityGrants: { type: Array, default: () => [] },
});

const overline = 'text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400';

const showRsvpModal = ref(false);
// The modal starts on this answer ("Book dinner" opens it on dining); the saved reply is otherwise kept.
const modalRsvp = ref(null);
const openRsvpModal = (status = null) => {
  modalRsvp.value = status ? { ...(props.userRsvp ?? {}), attendance_status: status } : props.userRsvp;
  showRsvpModal.value = true;
};

const paying = ref(null);
const pay = (provider) => router.post(route('member.meetings.pay', { slug: props.club.slug, id: props.meeting.id }), { provider }, {
  onStart: () => { paying.value = provider; },
  onFinish: () => { paying.value = null; },
});
const paymentOutcome = new URLSearchParams(window.location.search).get('payment');
const saidPaid = () => router.post(route('member.meetings.said_paid', { slug: props.club.slug, id: props.meeting.id }), {}, { preserveScroll: true });

const ANSWERS = [
  { value: 'attending_dining', label: 'Going, dining' },
  { value: 'attending_meeting_only', label: 'Going, no dining' },
  { value: 'apologies', label: 'Apologies' },
];
const STATUS = {
  attending_dining: { variant: 'success', label: "You're coming and dining" },
  attending_meeting_only: { variant: 'success', label: "You're coming, no dining" },
  apologies: { variant: 'danger', label: 'Apologies sent' },
};

const answer = computed(() => props.userRsvp?.attendance_status ?? null);
const status = computed(() => STATUS[answer.value] ?? { variant: 'warning', label: 'Not replied yet' });
const isDining = computed(() => answer.value === 'attending_dining');
const diningGuests = computed(() => (props.userRsvp?.guests ?? []).filter((guest) => guest.attending_dining).length);
// After the cut-off dining can be dropped but not added (MeetingReplies::guardDining refuses it on the server too).
const isAnswerDisabled = (value) => props.isCutoffPassed && value === 'attending_dining' && !isDining.value;

const replying = ref(null);
// One tap saves the answer through the quick reply route, which keeps guests and dietary notes already given.
const reply = (value) => {
  if (value === answer.value || isAnswerDisabled(value)) return;

  router.post(route('member.meetings.quick_rsvp', { slug: props.club.slug, id: props.meeting.id }), { attendance_status: value }, {
    preserveScroll: true,
    onStart: () => { replying.value = value; },
    onFinish: () => { replying.value = null; },
  });
};

const mapsUrl = computed(() => (props.meeting.venue ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.meeting.venue)}` : null));

const grantStatusLabel = (grantStatus) => {
  const label = (grantStatus || 'proposed').replace('_', ' ');

  return label.charAt(0).toUpperCase() + label.slice(1);
};
</script>

<template>
  <MembersLayout :title="`Summons — ${meeting.title || formatMeetingDate(meeting.meeting_date)}`" :club="club" :member-role="memberRole" active-tab="dashboard" own-heading>

    <div class="mx-auto max-w-2xl space-y-3 sm:space-y-4">
      <Link :href="route('member.calendar', { slug: club.slug })" class="-ml-2 inline-flex min-h-11 items-center gap-1 rounded-xl px-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        Back to calendar
      </Link>

      <!-- Meeting -->
      <section class="space-y-2.5 px-1">
        <div class="flex flex-wrap items-center gap-2">
          <Badge variant="info">{{ club.name }}<template v-if="club.lodge_number"> No. {{ club.lodge_number }}</template></Badge>
          <Badge :variant="status.variant">{{ status.label }}</Badge>
        </div>
        <h1 class="text-[26px] font-semibold leading-8 text-slate-900 dark:text-white">{{ meeting.title || 'Regular meeting' }}</h1>
        <div class="h-[3px] w-10 rounded-full bg-gold-500 dark:bg-gold-400" aria-hidden="true" />
        <div class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300">
          <svg class="mt-0.5 h-[18px] w-[18px] shrink-0 text-slate-500 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
          <div>
            {{ formatMeetingDate(meeting.meeting_date) }}<br>
            <span v-if="meeting.starts_at">Lodge opens {{ meeting.starts_at }}</span><span v-if="meeting.starts_at && meeting.dress_code">, </span><span v-if="meeting.dress_code">dress: {{ meeting.dress_code }}</span>
            <span v-if="meeting.rehearsal_starts_at" class="block text-slate-500 dark:text-slate-400">Rehearsal {{ meeting.rehearsal_starts_at }}</span>
          </div>
        </div>
        <div v-if="meeting.venue" class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300">
          <svg class="mt-0.5 h-[18px] w-[18px] shrink-0 text-slate-500 dark:text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
          <div>
            {{ meeting.venue }}<br>
            <a :href="mapsUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 sm:min-h-0">Open in maps</a>
          </div>
        </div>
      </section>

      <!-- Your reply -->
      <Card padding="sm" class="space-y-3">
        <h2 :class="overline">Your reply</h2>
        <div class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-800 dark:bg-slate-800/60" role="group" aria-label="Your reply">
          <button
            v-for="option in ANSWERS"
            :key="option.value"
            type="button"
            :aria-pressed="answer === option.value"
            :disabled="isAnswerDisabled(option.value) || replying !== null"
            :class="['min-h-11 rounded-xl px-1.5 text-[13px] leading-tight transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-not-allowed', answer === option.value ? 'bg-white font-semibold text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white' : 'font-medium text-slate-600 hover:text-slate-900 disabled:opacity-50 dark:text-slate-300 dark:hover:text-white', replying === option.value ? 'animate-pulse' : '']"
            @click="reply(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
        <p v-if="isCutoffPassed && !isDining" class="text-xs text-slate-500 dark:text-slate-400">Dining numbers have gone to the caterer, so dining can no longer be added here. To dine, please ask the Secretary.</p>
        <button v-if="answer === 'attending_dining' || answer === 'apologies'" type="button" class="-my-2 inline-flex min-h-11 items-center text-sm font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 sm:min-h-0 sm:my-0" @click="openRsvpModal()">
          {{ answer === 'apologies' ? 'Add a reason' : 'Guests and dietary needs' }}
        </button>
      </Card>

      <!-- Summons -->
      <Card padding="sm" class="space-y-3">
        <div class="flex items-center gap-3">
          <h2 :class="[overline, 'flex-1']">Summons</h2>
          <a :href="route('member.meetings.pdf', { slug: club.slug, id: meeting.id })" target="_blank" class="inline-flex min-h-11 items-center text-[13px] font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 sm:min-h-0">Download PDF</a>
        </div>

        <p class="text-sm leading-relaxed text-slate-700 dark:text-slate-300">
          {{ meeting.salutation || 'Dear Sir and Brother,' }} you are requested and summoned to attend <strong class="font-semibold text-slate-900 dark:text-white">{{ club.name }}</strong> at the date, time and place above.
        </p>

        <ol v-if="meeting.agenda_items?.length" class="list-decimal space-y-1.5 pl-5 text-sm text-slate-800 marker:text-slate-500 dark:text-slate-200">
          <li v-for="(item, idx) in meeting.agenda_items" :key="item.id || idx">
            {{ item.title }}
            <p v-if="item.description" class="mt-0.5 whitespace-pre-line text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ item.description }}</p>
          </li>
        </ol>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">Standard business will be transacted.</p>

        <div class="flex flex-wrap justify-between gap-x-4 gap-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
          <span>By command of the Worshipful Master: <strong class="font-semibold text-slate-700 dark:text-slate-200">{{ worshipfulMaster?.name || 'Worshipful Master' }}</strong></span>
          <span>Secretary: <strong class="font-semibold text-slate-700 dark:text-slate-200">{{ secretaryUser?.name || 'Secretary' }}</strong></span>
        </div>
      </Card>

      <!-- Festive board and dining -->
      <Card padding="sm" class="space-y-1">
        <h2 :class="[overline, 'mb-1']">Festive board after the meeting</h2>
        <p v-if="meeting.festive_board_theme" class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ meeting.festive_board_theme }}</p>
        <dl class="divide-y divide-slate-200 text-sm tabular-nums dark:divide-slate-800">
          <div class="flex justify-between py-2"><dt>Member</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $money(meeting.dining_cost_member) }}</dd></div>
          <div class="flex justify-between py-2"><dt>Guest</dt><dd class="font-semibold text-slate-900 dark:text-white">{{ $money(meeting.dining_cost_guest) }}</dd></div>
        </dl>
        <p v-if="meeting.festive_board_menu" class="whitespace-pre-line text-xs leading-relaxed text-slate-600 dark:text-slate-300">{{ meeting.festive_board_menu }}</p>
        <p v-if="meeting.rsvp_cutoff_at" class="text-xs text-slate-500 dark:text-slate-400">
          {{ isCutoffPassed ? 'Booking closed' : 'Booking closes' }} {{ formatEventTime(meeting.rsvp_cutoff_at) }}.
        </p>

        <div class="mt-3 flex items-center gap-3 border-t border-slate-200 pt-3 dark:border-slate-800">
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ isDining ? 'Dining booked' : 'Dining not booked' }}</p>
            <p v-if="isDining && diningGuests" class="text-xs text-slate-500 dark:text-slate-400">You and {{ diningGuests }} {{ diningGuests === 1 ? 'guest' : 'guests' }}</p>
          </div>
          <Button v-if="!isDining && !isCutoffPassed" class="px-6" @click="openRsvpModal('attending_dining')">Book dinner</Button>
          <Button v-else-if="isDining" variant="secondary" @click="openRsvpModal()">Change</Button>
        </div>
      </Card>

      <Alert v-if="paymentOutcome === 'success'" variant="success" title="Thank you">Your card payment has gone through. It can take a minute to show as paid.</Alert>
      <Alert v-else-if="paymentOutcome === 'cancelled'" variant="info">The card payment was cancelled, and nothing was taken.</Alert>
      <Alert v-if="$page.props.errors?.payment" variant="danger" role="alert">{{ $page.props.errors.payment }}</Alert>

      <div v-if="owed" class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900 dark:bg-amber-950/40">
        <p class="text-amber-900 dark:text-amber-200">Dining still to pay: <strong>{{ owed }}</strong></p>
        <div class="flex flex-wrap gap-2">
          <Button v-if="payWith.includes('card')" size="sm" :loading="paying === 'card'" @click="pay('card')">Pay by card</Button>
          <Button v-if="payWith.includes('paypal')" size="sm" variant="secondary" :loading="paying === 'paypal'" @click="pay('paypal')">Pay with PayPal</Button>
          <Button v-if="takesBank && !saidPaidAt" size="sm" variant="secondary" @click="saidPaid">I've paid by bank transfer</Button>
        </div>
        <p v-if="saidPaidAt" class="text-amber-900 dark:text-amber-200">You told us you paid by bank on {{ saidPaidAt }}. It will show as paid once the Treasurer sees it in the bank.</p>
      </div>

      <!-- Bank details and payment reference -->
      <Card v-if="(meeting.bank_sort_code || meeting.bank_account_number) && (isDining || userRsvp?.guests?.some(g => g.attending_dining))" padding="sm" class="space-y-2 text-sm">
        <div class="flex items-center justify-between gap-2">
          <h2 :class="overline">Bank transfer details</h2>
          <Badge v-if="userRsvp?.payment_status === 'paid'" variant="success">Paid</Badge>
          <Badge v-else-if="userRsvp?.payment_status === 'waived'" variant="info">Waived</Badge>
          <Badge v-else variant="warning">Unpaid</Badge>
        </div>
        <div class="grid grid-cols-1 gap-2 font-mono text-xs text-slate-700 dark:text-slate-200 sm:grid-cols-3">
          <div>Sort code: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_sort_code }}</strong></div>
          <div>Account no: <strong class="text-slate-900 dark:text-white">{{ meeting.bank_account_number }}</strong></div>
          <div>Reference prefix: <strong class="text-blue-600 dark:text-blue-400">{{ meeting.payment_reference_prefix || 'SUMMONS' }}</strong></div>
        </div>
      </Card>

      <!-- For the Charity Steward, Almoner, Treasurer or Secretary: the counted money on the night -->
      <Card v-if="canRecordCollections" padding="sm" class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 :class="overline">Alms and raffle</h2>
          <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Enter what was collected tonight.</p>
        </div>
        <Link :href="route('member.meetings.collections', { slug: club.slug, id: meeting.id })" class="inline-flex min-h-11 items-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white hover:bg-blue-500">Enter amounts</Link>
      </Card>

      <!-- Charitable donation proposals -->
      <Card v-if="charityGrants?.length" padding="sm" class="space-y-3">
        <h2 :class="overline">Charitable donations to be voted on</h2>
        <div v-for="grant in charityGrants" :key="grant.id" class="space-y-1 border-t border-slate-200 pt-3 text-sm first-of-type:border-t-0 first-of-type:pt-0 dark:border-slate-800">
          <div class="flex items-start justify-between gap-2">
            <p class="font-semibold text-slate-900 dark:text-white">{{ $money(grant.amount) }}, {{ grant.recipient_name }}</p>
            <Badge variant="warning">{{ grantStatusLabel(grant.approval_status) }}</Badge>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-300">{{ grant.purpose }}</p>
          <p class="flex flex-wrap gap-x-4 text-xs text-slate-500 dark:text-slate-400">
            <span v-if="grant.proposer">Proposed by {{ grant.proposer.first_name }} {{ grant.proposer.last_name }}</span>
            <span v-if="grant.seconder">Seconded by {{ grant.seconder.first_name }} {{ grant.seconder.last_name }}</span>
          </p>
        </div>
      </Card>

      <!-- Officers -->
      <Card v-if="meeting.officer_assignments?.length" padding="sm" class="space-y-2">
        <h2 :class="overline">Officers {{ meeting.officers_year_label || '' }}</h2>
        <dl class="grid grid-cols-1 gap-x-6 text-sm sm:grid-cols-2">
          <div v-for="off in meeting.officer_assignments" :key="off.id" class="flex justify-between gap-2 border-b border-slate-200 py-2 dark:border-slate-800">
            <dt class="text-slate-600 dark:text-slate-300">{{ off.officer_role?.name || 'Officer' }}</dt>
            <dd class="text-right font-semibold text-slate-900 dark:text-white">{{ off.custom_name || off.user?.name || '-' }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Provincial leadership -->
      <Card v-if="meeting.provincial_grand_master || meeting.deputy_provincial_grand_master || meeting.assistant_provincial_grand_masters" padding="sm" class="space-y-2">
        <h2 :class="overline">{{ meeting.front_page_title || 'Provincial Grand Lodge' }}</h2>
        <dl class="space-y-2 text-sm">
          <div v-if="meeting.provincial_grand_master">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Provincial Grand Master</dt>
            <dd class="font-semibold text-slate-900 dark:text-white">{{ meeting.provincial_grand_master }}</dd>
          </div>
          <div v-if="meeting.deputy_provincial_grand_master">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Deputy Provincial Grand Master</dt>
            <dd class="font-semibold text-slate-900 dark:text-white">{{ meeting.deputy_provincial_grand_master }}</dd>
          </div>
          <div v-if="meeting.assistant_provincial_grand_masters">
            <dt class="text-xs text-slate-500 dark:text-slate-400">Assistant Provincial Grand Masters</dt>
            <dd class="whitespace-pre-line text-slate-800 dark:text-slate-100">{{ meeting.assistant_provincial_grand_masters }}</dd>
          </div>
        </dl>
      </Card>

      <!-- Fraternal visits -->
      <Card v-if="meeting.fraternal_visits?.length || meeting.fraternal_visits_text" padding="sm" class="space-y-2">
        <h2 :class="overline">Fraternal visits</h2>
        <p v-if="meeting.fraternal_visits_text" class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ meeting.fraternal_visits_text }}</p>
        <div v-for="visit in meeting.fraternal_visits" :key="visit.id" class="border-t border-slate-200 pt-2 text-sm dark:border-slate-800">
          <p class="font-semibold text-slate-900 dark:text-white">{{ visit.visiting_club_name || visit.lodge_name }} <span v-if="visit.visiting_club_number || visit.lodge_number">No. {{ visit.visiting_club_number || visit.lodge_number }}</span></p>
          <p v-if="visit.leader_name" class="text-slate-600 dark:text-slate-300">Led by {{ visit.leader_name }}</p>
          <p v-if="visit.notes" class="text-xs text-slate-500 dark:text-slate-400">{{ visit.notes }}</p>
        </div>
      </Card>

      <!-- Honorary members and welfare notices -->
      <Alert v-if="meeting.honorary_members_text" variant="info" title="Honorary members" class="whitespace-pre-line">{{ meeting.honorary_members_text }}</Alert>
      <Alert v-if="meeting.sick_distressed_notes || meeting.almoner_notice" variant="warning" title="Almoner, sick and distressed notice" class="whitespace-pre-line">{{ meeting.sick_distressed_notes || meeting.almoner_notice }}</Alert>

      <!-- Secretary contact -->
      <Card padding="sm" class="space-y-1 text-sm">
        <h2 :class="overline">Lodge Secretary</h2>
        <p class="font-semibold text-slate-900 dark:text-white">{{ secretaryUser?.name || 'Secretary' }}</p>
        <p v-if="secretaryUser?.email || club.email" class="break-all text-slate-600 dark:text-slate-300">{{ secretaryUser?.email || club.email }}</p>
        <p v-if="club.settings?.phone" class="text-slate-600 dark:text-slate-300">{{ club.settings.phone }}</p>
      </Card>

      <MeetingRsvpModal v-if="showRsvpModal" :meeting="meeting" :club-slug="club.slug" :rsvp="modalRsvp" @close="showRsvpModal = false" />
    </div>
  </MembersLayout>
</template>
