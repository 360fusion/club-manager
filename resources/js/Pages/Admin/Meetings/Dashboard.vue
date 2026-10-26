<script setup>
import { localDate } from '@/Utils/localDateTime';
import { formatDate } from '@/Utils/lodgeDates';
import { ref, computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { useClipboard } from '@/Composables/useClipboard';

const props = defineProps({
  club: Object,
  meeting: Object,
  rsvps: Array,
  visitorsList: Array,
  allClubUsers: Array,
  stats: Object,
});

const activeTab = ref('all');
const showApologiesModal = ref(false);
const showManualRsvpModal = ref(false);
const selectedUserId = ref(null);

const manualRsvpForm = useForm({
  user_id: '',
  attendance_status: 'attending_dining',
  dietary_requirements: '',
  apology_reason: '',
  payment_status: 'unpaid',
  guests: [],
});

const openManualRsvpModal = (userId = null) => {
  if (userId) {
    selectedUserId.value = userId;
    manualRsvpForm.user_id = userId;
    const existingRsvp = (props.rsvps || []).find(r => r.user_id === userId);
    if (existingRsvp) {
      manualRsvpForm.attendance_status = existingRsvp.attendance_status || 'attending_dining';
      manualRsvpForm.dietary_requirements = existingRsvp.dietary_requirements || '';
      manualRsvpForm.apology_reason = existingRsvp.apology_reason || '';
      manualRsvpForm.payment_status = existingRsvp.payment_status || 'unpaid';
      manualRsvpForm.guests = (existingRsvp.guests || []).map(g => ({
        guest_name: g.guest_name,
        dietary_requirements: g.dietary_requirements || '',
        attending_dining: g.attending_dining ?? true,
      }));
    } else {
      manualRsvpForm.attendance_status = 'attending_dining';
      manualRsvpForm.dietary_requirements = '';
      manualRsvpForm.apology_reason = '';
      manualRsvpForm.payment_status = 'unpaid';
      manualRsvpForm.guests = [];
    }
  } else {
    selectedUserId.value = null;
    manualRsvpForm.reset();
    manualRsvpForm.attendance_status = 'attending_dining';
    manualRsvpForm.payment_status = 'unpaid';
    manualRsvpForm.user_id = (props.allClubUsers || [])[0]?.id || '';
    manualRsvpForm.guests = [];
  }
  showManualRsvpModal.value = true;
};

const setPaymentStatus = (userId, status) => {
  router.post(route('admin.meetings.rsvp.payment_status', { clubSlug: props.club.slug, id: props.meeting.id }), {
    user_id: userId,
    payment_status: status,
  }, {
    preserveScroll: true,
  });
};

const addGuestRow = () => {
  manualRsvpForm.guests.push({
    guest_name: '',
    dietary_requirements: '',
    attending_dining: true,
  });
};

const removeGuestRow = (index) => {
  manualRsvpForm.guests.splice(index, 1);
};

const submitManualRsvp = () => {
  manualRsvpForm.post(route('admin.meetings.rsvp.update', { clubSlug: props.club.slug, id: props.meeting.id }), {
    preserveScroll: true,
    onSuccess: () => {
      showManualRsvpModal.value = false;
    },
  });
};

const memberRsvps = computed(() => (props.rsvps || []).filter(r => !r.is_visitor));

const filteredRsvps = computed(() => {
  if (activeTab.value === 'all') return memberRsvps.value;
  return memberRsvps.value.filter(r => r.attendance_status === activeTab.value);
});

const confirmingPublish = ref(false);
const publishing = ref(false);

const publishSummons = () => {
  publishing.value = true;
  router.post(route('admin.meetings.publish', { clubSlug: props.club.slug, id: props.meeting.id }), {}, {
    onFinish: () => {
      publishing.value = false;
      confirmingPublish.value = false;
    },
  });
};

const { copied, copy } = useClipboard();
const copyApologiesText = () => copy(apologiesFormattedText.value);

// The kit's small secondary button look, for the PDF link that has to open in a new tab (Button renders an Inertia Link).
const secondaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus-visible:ring-offset-slate-950';
const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const choiceClass = (selected) => ['flex cursor-pointer items-center justify-center rounded-xl border p-2.5 text-center text-xs font-medium transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500', selected ? 'border-blue-500 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'];

const PAYMENT_BADGES = { paid: ['success', 'Paid'], waived: ['info', 'Waived'], refunded: ['danger', 'Refunded'] };
const paymentBadge = (status) => PAYMENT_BADGES[status] ?? ['warning', 'Unpaid'];
const ATTENDANCE_LABELS = { attending_dining: 'Attending dining', attending_meeting_only: 'Meeting only', apologies: 'Apologies' };
const attendanceVariant = (status) => ({ attending_dining: 'success', apologies: 'danger' }[status] ?? 'info');
const ATTENDANCE_CHOICES = [['attending_dining', 'Dining and meeting'], ['attending_meeting_only', 'Meeting only'], ['apologies', 'Apologies']];
const PAYMENT_CHOICES = [['unpaid', 'Unpaid'], ['paid', 'Paid'], ['waived', 'Waived'], ['refunded', 'Refunded']];

const userOptions = computed(() => [
  { value: '', label: 'Choose a member or visitor' },
  ...(props.allClubUsers || []).map(u => ({ value: u.id, label: `${u.name} (${u.email})${u.is_visitor ? ' · Visitor' : ''}` })),
]);

const filters = computed(() => [
  ['all', 'All responses', memberRsvps.value.length],
  ['attending_dining', 'Attending dining', props.stats.attending_dining],
  ['attending_meeting_only', 'Meeting only', props.stats.attending_meeting_only],
  ['apologies', 'Apologies', props.stats.apologies],
  ['visitors', 'Visitors', props.stats.visiting_count || 0],
]);

const apologiesFormattedText = computed(() => {
  const list = memberRsvps.value.filter(r => r.attendance_status === 'apologies');
  if (!list.length) return 'No apologies have been received for this meeting.';
  
  const names = list.map(r => r.user ? r.user.name : 'Member').join(', ');
  return `APOLOGIES FOR ABSENCE:\nApologies were received and recorded from: ${names}.`;
});

const formattedMeetingDate = computed(() => formatDate(props.meeting?.meeting_date, { month: 'long' }));

const todayStr = localDate();

const isPastMeeting = computed(() => {
  if (!props.meeting?.meeting_date) return false;
  const meetingDateStr = String(props.meeting.meeting_date).split('T')[0];
  return meetingDateStr <= todayStr;
});
</script>

<template>
  <AdminLayout title="Secretary meeting dashboard" :club="club" active-tab="meetings">
    <div class="space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">
                {{ meeting.title && !meeting.title.includes('Regular Meeting No.') ? meeting.title : 'Meeting - ' + formattedMeetingDate }} at {{ meeting.starts_at ? meeting.starts_at.substring(0, 5) : '18:30' }}
              </h2>
              <Badge :variant="meeting.status === 'published' ? 'success' : 'warning'" class="capitalize">{{ meeting.status }}</Badge>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              {{ formattedMeetingDate }} · {{ meeting.venue }} · Rehearsal: {{ meeting.rehearsal_starts_at ? meeting.rehearsal_starts_at.substring(0, 5) : '-' }}
            </p>
          </div>

          <div class="flex shrink-0 flex-col items-start gap-2 sm:items-end">
            <div class="flex flex-wrap gap-2 sm:justify-end">
              <Button v-if="isPastMeeting || meeting.financial_return" variant="secondary" size="sm" :href="route('admin.meetings.financial_return.show', { clubSlug: club.slug, id: meeting.id })">Financial return</Button>
              <a :href="route('admin.meetings.pdf', { clubSlug: club.slug, id: meeting.id })" target="_blank" :class="secondaryButton">Summons PDF</a>
              <Button variant="secondary" size="sm" :href="route('admin.meetings.edit', { clubSlug: club.slug, id: meeting.id })">Edit summons</Button>
              <Button variant="secondary" size="sm" @click="openManualRsvpModal(null)">Record RSVP</Button>
              <Button variant="secondary" size="sm" @click="showApologiesModal = true">Copy apologies for the minutes</Button>
            </div>
            <Button v-if="meeting.status !== 'published'" size="sm" @click="confirmingPublish = true">Publish and send summons</Button>
          </div>
        </div>
      </Card>

      <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        <StatCard label="Total attending" :value="stats.total_attending ?? (stats.attending_dining + stats.attending_meeting_only + stats.visiting_attending + stats.guest_meals)" caption="Meeting attendees" />
        <StatCard label="Total dining" :value="stats.total_dining ?? stats.total_caterer_headcount" :caption="`${stats.dining_members_and_visitors ?? (stats.attending_dining + stats.visiting_dining)} dining + ${stats.guest_meals} guests`" />
        <StatCard label="Meeting only" :value="stats.attending_meeting_only" caption="Attending without dining" />
        <StatCard label="Apologies received" :value="stats.apologies" caption="Formally recorded absence" />
        <StatCard label="Visitors attending" :value="stats.visiting_attending" caption="Visiting brethren and guests" />
        <StatCard label="Awaiting RSVP" :value="stats.awaiting" :caption="meeting.status === 'published' ? 'Emailed members pending' : 'Invites not sent yet'" />
      </div>

      <Card>
        <div class="space-y-4">
          <div class="flex items-center gap-1 overflow-x-auto border-b border-slate-100 pb-4 dark:border-slate-800" role="group" aria-label="Show">
            <button
              v-for="[key, label, count] in filters"
              :key="key"
              type="button"
              :aria-pressed="activeTab === key"
              :class="['whitespace-nowrap rounded-full px-3 py-1.5 text-xs font-medium transition-colors', activeTab === key ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
              @click="activeTab = key"
            >
              {{ label }} ({{ count }})
            </button>
          </div>

          <div class="overflow-x-auto">
            <table v-if="activeTab === 'visitors'" class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="p-3 font-medium">Visitor name and rank</th>
                  <th scope="col" class="p-3 font-medium">Home lodge or club</th>
                  <th scope="col" class="p-3 font-medium">Email</th>
                  <th scope="col" class="p-3 font-medium">Summons and attendance</th>
                  <th scope="col" class="p-3 font-medium">Dietary notes</th>
                  <th scope="col" class="p-3 font-medium">Payment and reference</th>
                  <th scope="col" class="p-3 text-right font-medium"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="v in visitorsList" :key="v.id">
                  <td class="p-3 font-medium text-slate-900 dark:text-white">
                    <span v-if="v.rank" class="mr-1 font-normal text-slate-500 dark:text-slate-400">{{ v.rank }}</span>
                    {{ v.name }}
                  </td>
                  <td class="p-3">{{ v.home_club_info || 'Visitor' }}</td>
                  <td class="p-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ v.email }}</td>
                  <td class="p-3">
                    <Badge v-if="v.attendance_status === 'attending_dining'" variant="success">Attending (dining)</Badge>
                    <Badge v-else-if="v.attendance_status === 'attending_meeting_only'" variant="info">Attending (meeting only)</Badge>
                    <Badge v-else-if="v.attendance_status === 'apologies'" variant="danger">Apologies received</Badge>
                    <Badge v-else-if="v.summons_sent" variant="info">Summons sent (awaiting response)</Badge>
                    <Badge v-else>Registered visitor</Badge>
                  </td>
                  <td class="p-3 text-slate-600 dark:text-slate-300">{{ v.dietary_notes || 'Standard' }}</td>
                  <td class="p-3">
                    <div v-if="v.attendance_status === 'attending_dining'">
                      <button type="button" class="cursor-pointer rounded-full" title="Click to toggle paid/unpaid status" @click="setPaymentStatus(v.id, v.payment_status === 'paid' ? 'unpaid' : 'paid')">
                        <Badge :variant="paymentBadge(v.payment_status)[0]">{{ paymentBadge(v.payment_status)[1] }}</Badge>
                      </button>
                      <div class="mt-0.5 font-mono text-xs text-slate-400">{{ v.payment_reference || '-' }}</div>
                    </div>
                    <span v-else class="text-xs text-slate-400">No payment due</span>
                  </td>
                  <td class="p-3 text-right">
                    <Button variant="secondary" size="sm" @click="openManualRsvpModal(v.id)">Edit</Button>
                  </td>
                </tr>
                <tr v-if="!visitorsList?.length">
                  <td colspan="7" class="p-6 text-center text-slate-500 dark:text-slate-400">No visitors have registered for this club yet.</td>
                </tr>
              </tbody>
            </table>

            <table v-else class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="p-3 font-medium">Member name</th>
                  <th scope="col" class="p-3 font-medium">Attendance</th>
                  <th scope="col" class="p-3 font-medium">Dietary requirements</th>
                  <th scope="col" class="p-3 font-medium">Registered visitors and guests</th>
                  <th scope="col" class="p-3 font-medium">Payment and reference</th>
                  <th scope="col" class="p-3 text-right font-medium"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="r in filteredRsvps" :key="r.id">
                  <td class="p-3 font-medium text-slate-900 dark:text-white">{{ r.user ? r.user.name : 'Member' }}</td>
                  <td class="p-3">
                    <Badge :variant="attendanceVariant(r.attendance_status)">{{ ATTENDANCE_LABELS[r.attendance_status] || (r.attendance_status ? r.attendance_status.replace('_', ' ') : 'Awaiting') }}</Badge>
                  </td>
                  <td class="p-3 text-slate-600 dark:text-slate-300">{{ r.dietary_requirements || 'Standard' }}</td>
                  <td class="p-3">
                    <div v-if="r.guests?.length" class="space-y-1 text-xs">
                      <div v-for="g in r.guests" :key="g.id" class="text-slate-800 dark:text-slate-100">
                        {{ g.guest_name }} <span class="text-slate-500 dark:text-slate-400">({{ g.home_club_lodge || 'Visitor' }})</span>
                      </div>
                    </div>
                    <span v-else class="text-slate-400">-</span>
                  </td>
                  <td class="p-3">
                    <div v-if="r.attendance_status === 'attending_dining' || r.guests?.some(g => g.attending_dining)">
                      <button type="button" class="cursor-pointer rounded-full" title="Click to toggle paid/unpaid status" @click="setPaymentStatus(r.user_id, r.payment_status === 'paid' ? 'unpaid' : 'paid')">
                        <Badge :variant="paymentBadge(r.payment_status)[0]">{{ paymentBadge(r.payment_status)[1] }}</Badge>
                      </button>
                      <div class="mt-0.5 font-mono text-xs text-slate-400">{{ r.payment_reference || '-' }}</div>
                    </div>
                    <span v-else class="text-xs text-slate-400">No payment due</span>
                  </td>
                  <td class="p-3 text-right">
                    <Button variant="secondary" size="sm" @click="openManualRsvpModal(r.user_id)">Edit</Button>
                  </td>
                </tr>
                <tr v-if="!filteredRsvps?.length">
                  <td colspan="6" class="p-6 text-center text-slate-500 dark:text-slate-400">No member responses in this filter.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Card>

      <Card v-if="stats.dietary_constraints?.length">
        <template #header>
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Itemised caterer dietary breakdown</h2>
            <Badge variant="warning">{{ stats.dietary_constraints.length }} requests</Badge>
          </div>
        </template>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          <div v-for="(d, idx) in stats.dietary_constraints" :key="idx" class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800">
            <div class="font-medium text-slate-900 dark:text-white">{{ d.person }}</div>
            <div class="mt-0.5 text-slate-600 dark:text-slate-300">{{ d.requirement }}</div>
          </div>
        </div>
      </Card>
    </div>

    <ConfirmDialog :open="confirmingPublish" title="Publish the summons?" confirm-label="Publish summons" variant="primary" :processing="publishing" @confirm="publishSummons" @close="confirmingPublish = false">
      This publishes the summons and issues passwordless RSVP links to all {{ stats.total_members }} members.
    </ConfirmDialog>

    <Modal :open="showApologiesModal" title="Apologies for the minutes" @close="showApologiesModal = false">
      <Textarea :model-value="apologiesFormattedText" readonly :rows="5" aria-label="Apologies text for the minutes" class="font-mono" />
      <template #footer>
        <Button variant="secondary" @click="showApologiesModal = false">Close</Button>
        <Button @click="copyApologiesText">{{ copied ? 'Copied' : 'Copy to clipboard' }}</Button>
      </template>
    </Modal>

    <Modal :open="showManualRsvpModal" :title="selectedUserId ? 'Edit attendance and dining RSVP' : 'Record an RSVP'" size="lg" @close="showManualRsvpModal = false">
      <form id="manual-rsvp-form" class="space-y-4" @submit.prevent="submitManualRsvp">
        <Select v-model.number="manualRsvpForm.user_id" label="Member or visitor" :options="userOptions" :disabled="!!selectedUserId" :error="manualRsvpForm.errors.user_id" />

        <fieldset>
          <legend class="mb-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">Attendance</legend>
          <div class="grid grid-cols-3 gap-2">
            <label v-for="[value, label] in ATTENDANCE_CHOICES" :key="value" :class="choiceClass(manualRsvpForm.attendance_status === value)">
              <input v-model="manualRsvpForm.attendance_status" type="radio" :value="value" class="sr-only" />
              {{ label }}
            </label>
          </div>
        </fieldset>

        <fieldset v-if="manualRsvpForm.attendance_status === 'attending_dining' || manualRsvpForm.guests?.some(g => g.attending_dining)">
          <legend class="mb-1.5 text-xs font-medium text-slate-700 dark:text-slate-300">Dining payment</legend>
          <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <label v-for="[value, label] in PAYMENT_CHOICES" :key="value" :class="choiceClass(manualRsvpForm.payment_status === value)">
              <input v-model="manualRsvpForm.payment_status" type="radio" :value="value" class="sr-only" />
              {{ label }}
            </label>
          </div>
        </fieldset>
        <Alert v-else>Payment is only required for dining RSVPs. No payment is due for meeting-only attendance or apologies.</Alert>

        <Input v-if="manualRsvpForm.attendance_status === 'attending_dining'" v-model="manualRsvpForm.dietary_requirements" label="Member dietary requirements" placeholder="e.g. Vegetarian, gluten-free, nut allergy" :error="manualRsvpForm.errors.dietary_requirements" />

        <Textarea v-if="manualRsvpForm.attendance_status === 'apologies'" v-model="manualRsvpForm.apology_reason" label="Apology reason or notes" :rows="2" placeholder="e.g. Away on business, unwell" :error="manualRsvpForm.errors.apology_reason" />

        <div v-if="manualRsvpForm.attendance_status === 'attending_dining'" class="space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Accompanying guests</h3>
            <Button variant="secondary" size="sm" @click="addGuestRow">Add guest</Button>
          </div>

          <div v-for="(g, idx) in manualRsvpForm.guests" :key="idx" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50">
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-slate-700 dark:text-slate-200">Guest {{ idx + 1 }}</span>
              <Button variant="ghost" size="sm" :aria-label="`Remove guest ${idx + 1}`" @click="removeGuestRow(idx)">Remove</Button>
            </div>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <input v-model="g.guest_name" type="text" placeholder="Guest full name" :aria-label="`Guest ${idx + 1} full name`" :class="fieldClass" required />
              <input v-model="g.dietary_requirements" type="text" placeholder="Dietary notes (optional)" :aria-label="`Guest ${idx + 1} dietary notes`" :class="fieldClass" />
            </div>
          </div>
        </div>
      </form>
      <template #footer>
        <Button variant="secondary" @click="showManualRsvpModal = false">Cancel</Button>
        <Button type="submit" form="manual-rsvp-form" :loading="manualRsvpForm.processing">Save RSVP</Button>
      </template>
    </Modal>
  </AdminLayout>
</template>
