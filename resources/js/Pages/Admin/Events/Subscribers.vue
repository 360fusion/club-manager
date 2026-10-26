<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaymentPanel from '@/Components/Events/PaymentPanel.vue';
import OrganiserBookingModal from '@/Components/Events/OrganiserBookingModal.vue';
import DropdownMenu from '@/Components/Ui/DropdownMenu.vue';
import Card from '@/Components/Ui/Card.vue';
import StatCard from '@/Components/Ui/StatCard.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { formatMoney } from '@/Utils/currency';

// Editing one person's meal
const mealFor = ref(null);
const mealForm = useForm({ attending_dining: true, starter_item_id: null, main_item_id: null, dessert_item_id: null, dietary_requirements: '' });
const COURSES = [['starter', 'starter_item_id', 'Starter'], ['main', 'main_item_id', 'Main'], ['dessert', 'dessert_item_id', 'Dessert']];
const canEditMeal = (sub) => props.event.has_dining && !['cancelled', 'declined'].includes(sub.attendance_status);
const openMeal = (sub) => {
  mealForm.clearErrors();
  mealForm.attending_dining = !!sub.attending_dining;
  mealForm.starter_item_id = sub.starter_item_id;
  mealForm.main_item_id = sub.main_item_id;
  mealForm.dessert_item_id = sub.dessert_item_id;
  mealForm.dietary_requirements = sub.dietary_requirements || '';
  mealFor.value = sub;
};
const saveMeal = () => {
  mealForm.put(route('admin.events.attendees.meal', { clubSlug: props.club.slug, id: props.event.id, attendeeId: mealFor.value.attendee_id }), {
    preserveScroll: true,
    onSuccess: () => { mealFor.value = null; },
  });
};
const mealError = (column) => mealForm.errors[`attendees.0.${column}`];

const props = defineProps({
  club: Object,
  event: Object,
  subscribers: Array,
  canManagePayments: { type: Boolean, default: false },
});

const activeTab = ref('all'); // all, paid, unpaid, dining
const searchQuery = ref('');

const filteredSubscribers = computed(() => {
  return (props.subscribers || []).filter(sub => {
    const query = searchQuery.value.toLowerCase().trim();
    const matchesQuery = !query || 
      (sub.name && sub.name.toLowerCase().includes(query)) ||
      (sub.email && sub.email.toLowerCase().includes(query)) ||
      (sub.home_club_lodge && sub.home_club_lodge.toLowerCase().includes(query)) ||
      (sub.rank && sub.rank.toLowerCase().includes(query));

    if (!matchesQuery) return false;

    if (activeTab.value === 'paid') return sub.payment_status === 'paid';
    if (activeTab.value === 'unpaid') return sub.payment_status === 'unpaid' || sub.payment_status === 'pending';
    if (activeTab.value === 'dining') return sub.has_food_choice;

    return true;
  });
});

const stats = computed(() => {
  const total = (props.subscribers || []).length;
  const paidCount = (props.subscribers || []).filter(s => s.payment_status === 'paid').length;
  const unpaidCount = (props.subscribers || []).filter(s => s.payment_status === 'unpaid' || s.payment_status === 'pending').length;
  const diningCount = (props.subscribers || []).filter(s => s.has_food_choice).length;

  const waitingCount = (props.subscribers || []).filter(s => s.attendance_status === 'waitlisted').length;

  return { total, paidCount, unpaidCount, diningCount, waitingCount };
});

// The add / edit booking popup
const bookingOpen = ref(false);
const editingId = ref(null);
const openAdd = () => { editingId.value = null; bookingOpen.value = true; };
const openEdit = (registrationId) => { editingId.value = registrationId; bookingOpen.value = true; };

const resendConfirmation = (sub) => {
  router.post(route('admin.events.registrations.resend', { clubSlug: props.club.slug, id: props.event.id, registrationId: sub.registration_id }), {}, { preserveScroll: true });
};

// DropdownMenu draws its own trigger <button>, so it gets the kit's small secondary button look as classes.
const secondaryButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
const menuItem = 'block w-full px-3.5 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800';
const FILTERS = [['all', 'All'], ['paid', 'Paid'], ['unpaid', 'Unpaid'], ['dining', 'Dining']];
const PAYMENT = { paid: 'success', part_paid: 'warning', waived: 'info', refunded: 'danger' };

const bookingToCancel = ref(null);
const cancelBooking = () => {
  router.post(route('admin.events.registrations.cancel', { clubSlug: props.club.slug, id: props.event.id, registrationId: bookingToCancel.value.registration_id }), {}, {
    preserveScroll: true,
    onFinish: () => { bookingToCancel.value = null; },
  });
};

const promoteBooking = (sub) => {
  router.post(route('admin.events.registrations.promote', { clubSlug: props.club.slug, id: props.event.id, registrationId: sub.registration_id }), {}, { preserveScroll: true });
};

const panel = ref(null); // the booking whose payment panel is open
const selected = ref([]); // booking ids ticked for a bulk "mark as paid"

const toggleSelected = (id) => {
  selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
};

const bulkPaidOpen = ref(false);
const bulkPaidComment = ref('');
const bulkPaying = ref(false);
const bulkPaid = () => {
  bulkPaying.value = true;
  router.post(route('admin.events.payment.bulk_paid', { clubSlug: props.club.slug, id: props.event.id }), { registration_ids: selected.value, comment: bulkPaidComment.value || null }, {
    preserveScroll: true,
    onSuccess: () => { selected.value = []; bulkPaidOpen.value = false; bulkPaidComment.value = ''; },
    onFinish: () => { bulkPaying.value = false; },
  });
};

const reload = () => router.reload({ only: ['subscribers'], preserveScroll: true });
</script>

<template>
  <AdminLayout title="Bookings" :club="club" active-tab="events">
    <Head :title="`Bookings - ${event.title}`" />

    <div class="space-y-6">
      <Card>
        <div class="space-y-4">
          <Button variant="ghost" size="sm" :href="route('admin.events.index', club.slug)">Back to events</Button>

          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ event.title }}</h2>
              <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-slate-500 dark:text-slate-400">
                <span>{{ event.starts_at || 'Date to be set' }}</span>
                <span v-if="event.location">{{ event.location }}</span>
                <Badge v-if="event.has_dining" variant="info">Dining {{ $money(event.dining_price) }}</Badge>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 lg:justify-end">
              <Button size="sm" @click="openAdd">Add a booking</Button>
              <Button variant="secondary" size="sm" :href="route('admin.events.checkin', { clubSlug: club.slug, id: event.id })">Check-in</Button>
              <DropdownMenu label="Print and export" :button-class="secondaryButton">
                <a :href="route('admin.events.guest_list', { clubSlug: club.slug, id: event.id })" target="_blank" :class="menuItem" role="menuitem">Guest list</a>
                <a v-if="event.has_dining" :href="route('admin.events.catering', { clubSlug: club.slug, id: event.id })" target="_blank" :class="menuItem" role="menuitem">Catering summary</a>
                <a :href="route('admin.events.export', { clubSlug: club.slug, id: event.id })" :class="menuItem" role="menuitem">Download CSV</a>
              </DropdownMenu>
              <Button variant="secondary" size="sm" :href="route('admin.events.edit', { clubSlug: club.slug, id: event.id })">Edit event</Button>
            </div>
          </div>
        </div>
      </Card>

      <div v-if="selected.length" class="flex flex-wrap items-center gap-3 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm dark:border-blue-800/60 dark:bg-blue-950/40">
        <span class="font-medium text-blue-900 dark:text-blue-200">{{ selected.length }} selected</span>
        <Button size="sm" @click="bulkPaidOpen = true">Mark as paid</Button>
        <Button variant="ghost" size="sm" @click="selected = []">Clear</Button>
      </div>

      <Modal :open="Boolean(mealFor)" :title="`Meal for ${mealFor?.name ?? ''}`" @close="mealFor = null">
        <form v-if="mealFor" id="meal-form" class="space-y-4" @submit.prevent="saveMeal">
          <p v-if="mealForm.errors.meal || mealForm.errors.attending_dining" class="text-sm text-rose-600 dark:text-rose-400" role="alert">{{ mealForm.errors.meal || mealForm.errors.attending_dining }}</p>
          <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input v-model="mealForm.attending_dining" type="checkbox" class="rounded accent-blue-600" /> Having dinner</label>
          <template v-if="mealForm.attending_dining">
            <template v-for="[course, column, title] in COURSES" :key="column">
              <Select
                v-if="event.menu?.[course]?.length"
                v-model="mealForm[column]"
                :label="title"
                :options="[{ value: '', label: 'Choose…' }, ...event.menu[course].map((item) => ({ value: item.id, label: item.name }))]"
                :error="mealError(column)"
              />
            </template>
          </template>
          <Input v-model="mealForm.dietary_requirements" label="Dietary needs" maxlength="1000" />
        </form>
        <template #footer>
          <Button variant="secondary" @click="mealFor = null">Cancel</Button>
          <Button type="submit" form="meal-form" :loading="mealForm.processing">Save meal</Button>
        </template>
      </Modal>

      <Modal :open="bulkPaidOpen" :title="`Mark ${selected.length} booking${selected.length === 1 ? '' : 's'} as paid`" @close="bulkPaidOpen = false">
        <form id="bulk-paid-form" @submit.prevent="bulkPaid">
          <Input v-model="bulkPaidComment" label="Comment for the payment history" hint="Optional, for example: paid in cash on the night." />
        </form>
        <template #footer>
          <Button variant="secondary" @click="bulkPaidOpen = false">Cancel</Button>
          <Button type="submit" form="bulk-paid-form" :loading="bulkPaying">Mark as paid</Button>
        </template>
      </Modal>

      <ConfirmDialog :open="Boolean(bookingToCancel)" title="Cancel this booking?" confirm-label="Cancel booking" cancel-label="Keep it" @confirm="cancelBooking" @close="bookingToCancel = null">
        {{ bookingToCancel?.name }}'s booking, and any guests on it, will be cancelled.
      </ConfirmDialog>

      <PaymentPanel v-if="panel" :key="panel.id" :club-slug="club.slug" :event-id="event.id" :registration-id="panel.id" :name="panel.name" @close="panel = null" @changed="reload" />

      <OrganiserBookingModal :open="bookingOpen" :event="event" :club-slug="club.slug" :club-name="club.name" :can-mark-paid="canManagePayments" :registration-id="editingId" @close="bookingOpen = false" @edit="openEdit" />

      <div class="grid grid-cols-2 gap-4 md:grid-cols-5">
        <StatCard label="Booked" :value="stats.total" caption="People attending" />
        <StatCard label="Dining" :value="stats.diningCount" caption="With a meal choice" />
        <StatCard label="Paid" :value="stats.paidCount" />
        <StatCard label="Awaiting payment" :value="stats.unpaidCount" />
        <StatCard label="Waiting list" :value="stats.waitingCount" />
      </div>

      <Card>
        <div class="space-y-4">
          <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 sm:flex-row sm:items-center">
            <div class="flex items-center gap-1 overflow-x-auto" role="group" aria-label="Show">
              <button
                v-for="[key, label] in FILTERS"
                :key="key"
                type="button"
                :aria-pressed="activeTab === key"
                :class="['rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors', activeTab === key ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
                @click="activeTab = key"
              >
                {{ label }} ({{ { all: subscribers.length, paid: stats.paidCount, unpaid: stats.unpaidCount, dining: stats.diningCount }[key] }})
              </button>
            </div>
            <Input v-model="searchQuery" class="w-full sm:w-72" type="search" aria-label="Search bookings" placeholder="Search name, email or lodge" />
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
              <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                  <th scope="col" class="p-3 font-medium">Name</th>
                  <th scope="col" class="p-3 font-medium">Home lodge or club</th>
                  <th scope="col" class="p-3 font-medium">Email</th>
                  <th scope="col" class="p-3 font-medium">Ticket</th>
                  <th scope="col" class="p-3 font-medium">Meal</th>
                  <th scope="col" class="p-3 font-medium">Dietary needs</th>
                  <th scope="col" class="p-3 font-medium">Payment</th>
                  <th scope="col" class="p-3 text-right font-medium"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="sub in filteredSubscribers" :key="sub.attendee_id">
                  <td class="p-3">
                    <div class="flex flex-wrap items-center gap-1 font-medium text-slate-900 dark:text-white">
                      <span v-if="sub.rank" class="font-normal text-slate-500 dark:text-slate-400">{{ sub.rank }}</span>
                      {{ sub.name }}
                      <Badge v-if="sub.is_guest" variant="warning">Guest</Badge>
                      <Badge v-if="sub.attendance_status === 'waitlisted'" variant="info">Waiting list</Badge>
                      <Badge v-else-if="['cancelled', 'declined'].includes(sub.attendance_status)">{{ sub.attendance_status === 'declined' ? 'Declined' : 'Cancelled' }}</Badge>
                    </div>
                    <div v-if="sub.booked_by" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Guest of {{ sub.booked_by }}</div>
                    <div v-else-if="sub.added_by_organiser" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Added by an organiser</div>
                    <div v-if="sub.internal_note && !sub.is_guest" class="mt-0.5 text-xs text-amber-700 dark:text-amber-300" :title="sub.internal_note">Note: {{ sub.internal_note }}</div>
                    <div v-if="sub.member_number" class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">Member {{ sub.member_number }}</div>
                  </td>

                  <td class="p-3">
                    <span v-if="sub.home_club_lodge">{{ sub.home_club_lodge }}</span>
                    <span v-else class="text-slate-400">Not given</span>
                  </td>

                  <td class="p-3 text-xs text-slate-600 dark:text-slate-300">{{ sub.email }}</td>

                  <td class="p-3">
                    <Badge v-if="sub.ticket_tier" variant="info">{{ sub.ticket_tier.name }} ({{ $money(sub.ticket_tier.price) }})</Badge>
                    <span v-else class="text-slate-500 dark:text-slate-400">Standard</span>
                  </td>

                  <td class="p-3 text-xs">
                    <div v-if="sub.has_food_choice" class="space-y-0.5">
                      <div v-if="sub.menu_selections.starter"><span class="text-slate-500 dark:text-slate-400">Starter:</span> {{ sub.menu_selections.starter }}</div>
                      <div v-if="sub.menu_selections.main"><span class="text-slate-500 dark:text-slate-400">Main:</span> {{ sub.menu_selections.main }}</div>
                      <div v-if="sub.menu_selections.dessert"><span class="text-slate-500 dark:text-slate-400">Dessert:</span> {{ sub.menu_selections.dessert }}</div>
                    </div>
                    <span v-else class="text-slate-400">No dining</span>
                    <button v-if="canEditMeal(sub)" type="button" class="mt-1 block text-xs font-medium text-blue-600 hover:underline dark:text-blue-400" @click="openMeal(sub)">Edit meal</button>
                  </td>

                  <td class="p-3">
                    <Badge v-if="sub.dietary_requirements" variant="warning">{{ sub.dietary_requirements }}</Badge>
                    <span v-else class="text-slate-400">None</span>
                  </td>

                  <!-- Payment: click for who marked it and when, and to change it -->
                  <td class="p-3">
                    <div class="flex items-center gap-2">
                      <input v-if="canManagePayments && !sub.is_guest && Number(sub.balance) > 0" type="checkbox" :checked="selected.includes(sub.registration_id)" class="h-3.5 w-3.5 rounded accent-blue-600" :aria-label="`Select ${sub.name}`" @change="toggleSelected(sub.registration_id)" />
                      <span v-if="sub.is_guest" class="text-xs text-slate-500 dark:text-slate-400">With the booker</span>
                      <span v-else-if="Number(sub.total) <= 0" class="text-xs text-slate-500 dark:text-slate-400">Free</span>
                      <component
                        :is="canManagePayments ? 'button' : 'span'"
                        v-else
                        :type="canManagePayments ? 'button' : undefined"
                        :class="canManagePayments ? 'cursor-pointer rounded-full' : ''"
                        :title="canManagePayments ? 'See who marked this and when, or change it' : undefined"
                        @click="canManagePayments && (panel = { id: sub.registration_id, name: sub.is_guest ? sub.booked_by : sub.name })"
                      >
                        <Badge :variant="PAYMENT[sub.payment_status] ?? 'neutral'">
                          <template v-if="sub.payment_status === 'paid'">Paid {{ formatMoney(sub.total) }}</template>
                          <template v-else-if="sub.payment_status === 'part_paid'">{{ formatMoney(sub.amount_paid) }} of {{ formatMoney(sub.total) }}</template>
                          <template v-else-if="sub.payment_status === 'waived'">Waived</template>
                          <template v-else-if="sub.payment_status === 'refunded'">Refunded</template>
                          <template v-else>Owes {{ formatMoney(sub.balance) }}</template>
                        </Badge>
                      </component>
                    </div>
                  </td>

                  <!-- Booking actions act on the whole booking, guests included -->
                  <td class="whitespace-nowrap p-3 text-right">
                    <DropdownMenu v-if="!sub.is_guest" :aria-label="`Actions for ${sub.name}`" :button-class="secondaryButton">
                      <template #trigger>Actions</template>
                      <button v-if="!['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="menuItem" role="menuitem" @click="openEdit(sub.registration_id)">Edit booking</button>
                      <button v-if="canManagePayments && Number(sub.total) > 0" type="button" :class="menuItem" role="menuitem" @click="panel = { id: sub.registration_id, name: sub.name }">Payment and history</button>
                      <button v-if="sub.email && !['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="menuItem" role="menuitem" @click="resendConfirmation(sub)">Resend confirmation</button>
                      <button v-if="sub.attendance_status === 'waitlisted'" type="button" :class="menuItem" role="menuitem" @click="promoteBooking(sub)">Give them a place</button>
                      <button v-if="!['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="`${menuItem} !text-rose-600 dark:!text-rose-400`" role="menuitem" @click="bookingToCancel = sub">Cancel booking</button>
                    </DropdownMenu>
                  </td>
                </tr>

                <tr v-if="filteredSubscribers.length === 0">
                  <td colspan="8" class="p-8 text-center text-slate-500 dark:text-slate-400">No bookings match.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </Card>
    </div>
  </AdminLayout>
</template>
