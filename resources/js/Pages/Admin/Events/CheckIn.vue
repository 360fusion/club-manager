<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import OrganiserBookingModal from '@/Components/Events/OrganiserBookingModal.vue';
import NightPersonRow from '@/Components/Events/Organiser/NightPersonRow.vue';
import { formatEventTime } from '@/Utils/localDateTime';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
  club: Object,
  event: Object,
  attendees: Array,
  canManagePayments: { type: Boolean, default: false },
});

const searchQuery = ref('');

const filteredAttendees = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  if (!q) return props.attendees;
  return props.attendees.filter((a) => [a.name, a.booked_by, a.organisation, a.email].some((text) => (text || '').toLowerCase().includes(q)));
});

const waiting = computed(() => filteredAttendees.value.filter((a) => !a.checked_in_at));
const arrived = computed(() => filteredAttendees.value.filter((a) => a.checked_in_at));

const arrivedCount = computed(() => props.attendees.filter((a) => a.checked_in_at).length);
// Bookings, not people: a booking is paid as a whole.
const toPayCount = computed(() => new Set(props.attendees.filter((a) => Number(a.balance) > 0 && ['unpaid', 'part_paid'].includes(a.payment_status)).map((a) => a.registration_id)).size);

const walkInName = ref('');

const addWalkIn = () => {
  if (!walkInName.value.trim()) return;
  router.post(route('admin.events.checkin.walk_in', { clubSlug: props.club.slug, id: props.event.id }), { name: walkInName.value }, {
    preserveScroll: true,
    onSuccess: () => { walkInName.value = ''; },
  });
};

const toggleCheckIn = (person) => {
  router.post(route('admin.events.checkin.store', { clubSlug: props.club.slug, id: props.event.id }), {
    attendee_id: person.id,
    action: person.checked_in_at ? 'undo' : 'checkin',
  }, {
    preserveScroll: true,
  });
};

// Taking the rest of a booking's money on the door, recorded through the payment history like any other payment.
const taking = ref(null);
const takingBusy = ref(false);
const payError = ref('');
const takePayment = () => {
  takingBusy.value = true;
  payError.value = '';
  router.post(route('admin.events.payment.paid', { clubSlug: props.club.slug, id: props.event.id, registrationId: taking.value.registration_id }), { comment: 'Taken at the door' }, {
    preserveScroll: true,
    onError: (errors) => { payError.value = errors.payment || errors.amount || 'The payment could not be recorded.'; },
    onFinish: () => { takingBusy.value = false; taking.value = null; },
  });
};

const bookingOpen = ref(false);
const editingId = ref(null);
const openAdd = () => { editingId.value = null; bookingOpen.value = true; };
</script>

<template>
  <AdminLayout title="Event night" :club="club" active-tab="events">
    <Head :title="`Event night - ${event.title}`" />

    <div class="mx-auto max-w-3xl space-y-3 pb-28 sm:pb-6">
      <div class="flex items-center gap-2">
        <Button variant="ghost" size="sm" :href="route('admin.events.subscribers', { clubSlug: club.slug, id: event.id })" aria-label="Back to bookings">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
        </Button>
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-semibold text-slate-900 dark:text-white">Event night</h1>
          <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ [event.title, event.location, formatEventTime(event.starts_at)].filter(Boolean).join(' · ') }}</p>
        </div>
        <Button v-if="event.has_dining" variant="secondary" size="sm" :href="route('admin.events.dining', { clubSlug: club.slug, id: event.id })">Catering</Button>
      </div>

      <div class="grid grid-cols-3 gap-2">
        <div v-for="[value, label] in [[attendees.length, 'Booked'], [arrivedCount, 'Arrived'], [toPayCount, 'To pay']]" :key="label" class="rounded-2xl border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-800 dark:bg-slate-900">
          <div class="text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ value }}</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">{{ label }}</div>
        </div>
      </div>

      <Input v-model="searchQuery" type="search" aria-label="Find a name" placeholder="Find a name" />

      <p v-if="payError" class="rounded-xl bg-rose-500/10 p-3 text-sm font-semibold text-rose-600" role="alert">{{ payError }}</p>

      <template v-for="[list, heading] in [[waiting, 'Not arrived yet'], [arrived, 'Arrived']]" :key="heading">
        <template v-if="list.length">
          <h2 class="px-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ heading }}</h2>
          <Card padding="none">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
              <NightPersonRow v-for="person in list" :key="person.id" :person="person" :has-dining="event.has_dining" :can-take-payment="canManagePayments" @toggle="toggleCheckIn" @take="taking = $event" />
            </ul>
          </Card>
        </template>
      </template>

      <Card v-if="!filteredAttendees.length" padding="sm">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ attendees.length ? 'No one by that name.' : 'No bookings yet.' }}</p>
      </Card>

      <Card padding="sm">
        <form class="flex items-end gap-2" @submit.prevent="addWalkIn">
          <Input v-model="walkInName" class="min-w-0 flex-1" label="Just a name? Add a walk-in" maxlength="150" placeholder="Name of someone without a booking" />
          <Button type="submit" variant="secondary">Add</Button>
        </form>
      </Card>

      <div class="fixed inset-x-0 bottom-0 z-30 flex gap-2 border-t border-slate-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 dark:border-slate-800 dark:bg-slate-900 sm:dark:bg-transparent">
        <Button variant="secondary" class="flex-1 sm:flex-none" @click="openAdd">Add at the door</Button>
        <Button class="flex-1 sm:flex-none" :href="route('admin.notices.index', { clubSlug: club.slug })">Message diners</Button>
      </div>
    </div>

    <OrganiserBookingModal :open="bookingOpen" :event="event" :club-slug="club.slug" :club-name="club.name" :can-mark-paid="canManagePayments" :registration-id="editingId" at-the-door @close="bookingOpen = false" @edit="editingId = $event" />

    <ConfirmDialog :open="!!taking" title="Take payment" variant="primary" :confirm-label="taking ? `Record ${formatMoney(taking.balance)}` : 'Record'" :processing="takingBusy" @confirm="takePayment" @close="taking = null">
      <template v-if="taking">{{ formatMoney(taking.balance) }} from {{ taking.name }}, taken at the door. It goes in the payment history under your name.</template>
    </ConfirmDialog>
  </AdminLayout>
</template>
