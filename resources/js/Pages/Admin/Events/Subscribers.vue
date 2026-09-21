<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PaymentPanel from '@/Components/Events/PaymentPanel.vue';
import OrganiserBookingModal from '@/Components/Events/OrganiserBookingModal.vue';
import DropdownMenu from '@/Components/Ui/DropdownMenu.vue';
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

const buttonBase = 'inline-flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-xs font-bold transition-all';
const secondaryButton = `${buttonBase} border border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700`;
const menuItem = 'block w-full px-3.5 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800';

const cancelBooking = (sub) => {
  if (confirm(`Cancel ${sub.is_guest ? sub.booked_by + "'s" : sub.name + "'s"} booking (${sub.is_guest ? 'including guests' : 'and any guests'})?`)) {
    router.post(route('admin.events.registrations.cancel', { clubSlug: props.club.slug, id: props.event.id, registrationId: sub.registration_id }), {}, { preserveScroll: true });
  }
};

const promoteBooking = (sub) => {
  router.post(route('admin.events.registrations.promote', { clubSlug: props.club.slug, id: props.event.id, registrationId: sub.registration_id }), {}, { preserveScroll: true });
};

const panel = ref(null); // the booking whose payment panel is open
const selected = ref([]); // booking ids ticked for a bulk "mark as paid"

const toggleSelected = (id) => {
  selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id];
};

const bulkPaid = () => {
  const comment = window.prompt(`Mark ${selected.value.length} booking(s) as paid. Add a comment for the history (optional):`, '');
  if (comment === null) return;
  router.post(route('admin.events.payment.bulk_paid', { clubSlug: props.club.slug, id: props.event.id }), { registration_ids: selected.value, comment: comment || null }, {
    preserveScroll: true,
    onSuccess: () => { selected.value = []; },
  });
};

const reload = () => router.reload({ only: ['subscribers'], preserveScroll: true });
</script>

<template>
  <AdminLayout title="Event Subscriptions & RSVPs" :club="club" active-tab="events">
    <Head :title="`Event Subscriptions - ${event.title}`" />

    <div class="space-y-6">
      
      <!-- Header: title and details, then the actions -->
      <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <Link :href="route('admin.events.index', club.slug)" class="inline-flex items-center text-xs font-bold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
          ← Back to Events
        </Link>

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">👥 {{ event.title }}</h2>
            <div class="flex items-center gap-3 text-xs mt-1.5 flex-wrap text-slate-500 dark:text-slate-400 font-medium">
              <span>📅 {{ event.starts_at || 'Date TBD' }}</span>
              <span v-if="event.location">📍 {{ event.location }}</span>
              <span v-if="event.has_dining" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                🍽️ 3-Course Dining ({{ $cs }}{{ event.dining_price }})
              </span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 lg:justify-end">
            <button type="button" :class="`${buttonBase} bg-blue-600 text-white shadow-md shadow-blue-600/20 hover:bg-blue-700`" @click="openAdd">+ Add booking</button>
            <Link :href="route('admin.events.checkin', { clubSlug: club.slug, id: event.id })" :class="`${buttonBase} bg-emerald-600 text-white hover:bg-emerald-700`">✓ Check-in</Link>
            <DropdownMenu label="Print & export" :button-class="secondaryButton">
              <a :href="route('admin.events.guest_list', { clubSlug: club.slug, id: event.id })" target="_blank" :class="menuItem" role="menuitem">🖨️ Guest list</a>
              <a v-if="event.has_dining" :href="route('admin.events.catering', { clubSlug: club.slug, id: event.id })" target="_blank" :class="menuItem" role="menuitem">🍽️ Catering summary</a>
              <a :href="route('admin.events.export', { clubSlug: club.slug, id: event.id })" :class="menuItem" role="menuitem">⬇ Download CSV</a>
            </DropdownMenu>
            <Link :href="route('admin.events.edit', { clubSlug: club.slug, id: event.id })" :class="secondaryButton">✏️ Edit event</Link>
          </div>
        </div>
      </div>

      <!-- Bulk mark as paid -->
      <div v-if="selected.length" class="flex flex-wrap items-center gap-3 rounded-2xl border border-emerald-300 bg-emerald-50 p-3 text-xs dark:border-emerald-800/60 dark:bg-emerald-950/30">
        <span class="font-bold text-emerald-800 dark:text-emerald-200">{{ selected.length }} selected</span>
        <button type="button" class="rounded-lg bg-emerald-600 px-3.5 py-2 font-bold text-white hover:bg-emerald-700" @click="bulkPaid">Mark selected as paid</button>
        <button type="button" class="font-semibold text-slate-600 dark:text-slate-300" @click="selected = []">Clear</button>
      </div>


      <div v-if="mealFor" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" @click.self="mealFor = null">
        <form class="w-full max-w-md space-y-3 rounded-2xl bg-white p-5 text-xs shadow-xl dark:bg-slate-900" @submit.prevent="saveMeal">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Meal for {{ mealFor.name }}</h3>
          <p v-if="mealForm.errors.meal || mealForm.errors.attending_dining" class="rounded-lg bg-rose-500/10 p-2 font-semibold text-rose-600" role="alert">{{ mealForm.errors.meal || mealForm.errors.attending_dining }}</p>
          <label class="flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200"><input v-model="mealForm.attending_dining" type="checkbox" class="rounded" /> Having dinner</label>
          <template v-if="mealForm.attending_dining">
            <div v-for="[course, column, title] in COURSES" :key="column">
              <template v-if="event.menu?.[course]?.length">
                <label class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">{{ title }}</label>
                <select v-model="mealForm[column]" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white">
                  <option :value="null">Choose...</option>
                  <option v-for="item in event.menu[course]" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
                <p v-if="mealError(column)" class="mt-1 font-semibold text-rose-600">{{ mealError(column) }}</p>
              </template>
            </div>
          </template>
          <div>
            <label class="mb-1 block font-semibold text-slate-600 dark:text-slate-300">Dietary needs</label>
            <input v-model="mealForm.dietary_requirements" type="text" maxlength="1000" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white" />
          </div>
          <div class="flex justify-end gap-2 pt-1">
            <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="mealFor = null">Cancel</button>
            <button type="submit" :disabled="mealForm.processing" class="rounded-lg bg-blue-600 px-3 py-2 font-bold text-white hover:bg-blue-500 disabled:opacity-60">Save meal</button>
          </div>
        </form>
      </div>

      <PaymentPanel v-if="panel" :key="panel.id" :club-slug="club.slug" :event-id="event.id" :registration-id="panel.id" :name="panel.name" @close="panel = null" @changed="reload" />

      <OrganiserBookingModal :open="bookingOpen" :event="event" :club-slug="club.slug" :club-name="club.name" :can-mark-paid="canManagePayments" :registration-id="editingId" @close="bookingOpen = false" @edit="openEdit" />

      <!-- Live KPI Cards Grid -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Subscribed</div>
          <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ stats.total }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Registered Attendees</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dining Attendees</div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.diningCount }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">3-Course Meal Selections</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Payments Received</div>
          <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.paidCount }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Paid Subscriptions</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Awaiting Payment</div>
          <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.unpaidCount }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Unpaid Subscriptions</div>
        </div>

        <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-1">
          <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Waiting List</div>
          <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ stats.waitingCount }}</div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">People waiting for a place</div>
        </div>
      </div>

      <!-- Filter Tabs & Subscribers Table Card -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
          <!-- Filter Tabs -->
          <div class="flex items-center gap-2 overflow-x-auto">
            <button
              @click="activeTab = 'all'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'all' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              All Subscribers ({{ subscribers.length }})
            </button>
            <button
              @click="activeTab = 'paid'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'paid' ? 'bg-emerald-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              Paid ({{ stats.paidCount }})
            </button>
            <button
              @click="activeTab = 'unpaid'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'unpaid' ? 'bg-amber-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              Unpaid ({{ stats.unpaidCount }})
            </button>
            <button
              @click="activeTab = 'dining'"
              :class="['px-3 py-1.5 text-xs font-bold rounded-xl transition-all cursor-pointer', activeTab === 'dining' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800']"
            >
              Dining ({{ stats.diningCount }})
            </button>
          </div>

          <!-- Search Input -->
          <div class="relative w-full sm:w-72">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search subscriber, email, lodge..."
              class="w-full pl-3 pr-4 py-1.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
            />
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-xs text-left text-slate-700 dark:text-slate-200">
            <thead class="bg-blue-50/80 dark:bg-blue-950/80 text-blue-900 dark:text-blue-200 uppercase font-bold text-[10px] tracking-wider">
              <tr>
                <th class="p-3">Subscriber Name & Rank</th>
                <th class="p-3">Home Lodge / Club</th>
                <th class="p-3">Email Address</th>
                <th class="p-3">Ticket Tier</th>
                <th class="p-3">Food Choices & Menu</th>
                <th class="p-3">Dietary Notes</th>
                <th class="p-3">Payment Status</th>
                <th class="p-3 text-right">Booking</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
              <tr
                v-for="sub in filteredSubscribers"
                :key="sub.attendee_id"
                class="hover:bg-blue-50/30 dark:hover:bg-blue-950/30 transition-all"
              >
                <!-- Subscriber Name & Rank -->
                <td class="p-3">
                  <div class="font-bold text-slate-900 dark:text-white">
                    <span v-if="sub.rank" class="text-slate-500 dark:text-slate-400 font-normal mr-1">{{ sub.rank }}</span>
                    {{ sub.name }}
                    <span v-if="sub.is_guest" class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">Guest</span>
                    <span v-if="sub.attendance_status === 'waitlisted'" class="ml-1 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-200">Waitlist</span>
                    <span v-else-if="sub.attendance_status === 'cancelled' || sub.attendance_status === 'declined'" class="ml-1 rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ sub.attendance_status === 'declined' ? 'Declined' : 'Cancelled' }}</span>
                  </div>
                  <div v-if="sub.booked_by" class="text-[10px] text-slate-400 mt-0.5">Guest of {{ sub.booked_by }}</div>
                  <div v-else-if="sub.added_by_organiser" class="text-[10px] text-slate-400 mt-0.5">Added by an organiser</div>
                  <div v-if="sub.internal_note && !sub.is_guest" class="mt-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300" :title="sub.internal_note">📝 {{ sub.internal_note }}</div>
                  <div v-if="sub.member_number" class="text-[10px] text-slate-400 font-mono mt-0.5">
                    Mem #: {{ sub.member_number }}
                  </div>
                </td>

                <!-- Home Lodge / Club -->
                <td class="p-3 font-semibold text-slate-800 dark:text-slate-100">
                  <span v-if="sub.home_club_lodge">🏛️ {{ sub.home_club_lodge }}</span>
                  <span v-else class="text-slate-400 italic">Not specified</span>
                </td>

                <!-- Email Address -->
                <td class="p-3 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                  {{ sub.email }}
                </td>

                <!-- Ticket Tier -->
                <td class="p-3">
                  <span v-if="sub.ticket_tier" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                    🎟️ {{ sub.ticket_tier.name }} ({{ $cs }}{{ sub.ticket_tier.price }})
                  </span>
                  <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800">
                    Standard Admission
                  </span>
                </td>

                <!-- Food Choices -->
                <td class="p-3">
                  <div v-if="sub.has_food_choice" class="space-y-0.5 text-[11px]">
                    <div v-if="sub.menu_selections.starter" class="text-slate-700 dark:text-slate-200">
                      <span class="font-semibold text-slate-400">Starter:</span> {{ sub.menu_selections.starter }}
                    </div>
                    <div v-if="sub.menu_selections.main" class="text-slate-700 dark:text-slate-200">
                      <span class="font-semibold text-slate-400">Main:</span> {{ sub.menu_selections.main }}
                    </div>
                    <div v-if="sub.menu_selections.dessert" class="text-slate-700 dark:text-slate-200">
                      <span class="font-semibold text-slate-400">Dessert:</span> {{ sub.menu_selections.dessert }}
                    </div>
                  </div>
                  <span v-else class="text-slate-400 italic text-[11px]">No Dining</span>
                  <button v-if="canEditMeal(sub)" type="button" class="mt-1 block text-[11px] font-bold text-blue-600 hover:underline dark:text-blue-400" @click="openMeal(sub)">Edit meal</button>
                </td>

                <!-- Dietary Notes -->
                <td class="p-3">
                  <span v-if="sub.dietary_requirements" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-200 border border-amber-200 dark:border-amber-800/60">
                    ⚠️ {{ sub.dietary_requirements }}
                  </span>
                  <span v-else class="text-slate-400 italic text-[11px]">Standard</span>
                </td>

                <!-- Payment: green tick when paid; click for the history and to change it -->
                <td class="p-3">
                  <div class="flex items-center gap-2">
                    <input v-if="canManagePayments && !sub.is_guest && Number(sub.balance) > 0" type="checkbox" :checked="selected.includes(sub.registration_id)" class="h-3.5 w-3.5 rounded" :aria-label="`Select ${sub.name}`" @change="toggleSelected(sub.registration_id)" />
                    <span v-if="sub.is_guest" class="text-[10px] text-slate-400">with booker</span>
                    <span v-else-if="Number(sub.total) <= 0" class="text-[11px] font-semibold text-slate-400">Free</span>
                    <component :is="canManagePayments ? 'button' : 'span'" v-else type="button" :class="['flex items-center gap-1.5 rounded-lg border px-2 py-1 text-[11px] font-bold', canManagePayments ? 'cursor-pointer hover:brightness-95' : '',
                        sub.payment_status === 'paid' ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-700/60 dark:bg-emerald-950/40 dark:text-emerald-300' :
                        sub.payment_status === 'part_paid' ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700/60 dark:bg-amber-950/40 dark:text-amber-200' :
                        sub.payment_status === 'waived' ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-700/60 dark:bg-blue-950/40 dark:text-blue-300' :
                        sub.payment_status === 'refunded' ? 'border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-700/60 dark:bg-rose-950/40 dark:text-rose-300' :
                        'border-slate-300 bg-slate-50 text-slate-500 dark:border-slate-700 dark:bg-slate-800/50 dark:text-slate-400']"
                      :title="canManagePayments ? 'Click to see who marked this and when, or to change it' : 'Payment status'"
                      @click="canManagePayments && (panel = { id: sub.registration_id, name: sub.is_guest ? sub.booked_by : sub.name })">
                      <span v-if="sub.payment_status === 'paid'" class="text-base leading-none">✓</span>
                      <span v-else class="text-base leading-none">○</span>
                      <span v-if="sub.payment_status === 'paid'">Paid {{ formatMoney(sub.total) }}</span>
                      <span v-else-if="sub.payment_status === 'part_paid'">{{ formatMoney(sub.amount_paid) }} of {{ formatMoney(sub.total) }}</span>
                      <span v-else-if="sub.payment_status === 'waived'">Waived</span>
                      <span v-else-if="sub.payment_status === 'refunded'">Refunded</span>
                      <span v-else>Owes {{ formatMoney(sub.balance) }}</span>
                    </component>
                  </div>
                </td>

                <!-- Booking actions (act on the whole booking, guests included) -->
                <td class="p-3 text-right whitespace-nowrap">
                  <DropdownMenu v-if="!sub.is_guest" :aria-label="`Actions for ${sub.name}`" button-class="rounded-lg border border-slate-300 px-2.5 py-1 text-base leading-none text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    <template #trigger><span aria-hidden="true">⋯</span></template>
                    <button v-if="!['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="menuItem" role="menuitem" @click="openEdit(sub.registration_id)">✏️ Edit booking</button>
                    <button v-if="canManagePayments && Number(sub.total) > 0" type="button" :class="menuItem" role="menuitem" @click="panel = { id: sub.registration_id, name: sub.name }">💳 Payment and history</button>
                    <button v-if="sub.email && !['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="menuItem" role="menuitem" @click="resendConfirmation(sub)">✉️ Resend confirmation</button>
                    <button v-if="sub.attendance_status === 'waitlisted'" type="button" :class="menuItem" role="menuitem" @click="promoteBooking(sub)">⬆ Move up from waiting list</button>
                    <button v-if="!['cancelled', 'declined'].includes(sub.attendance_status)" type="button" :class="`${menuItem} !text-rose-600 dark:!text-rose-400`" role="menuitem" @click="cancelBooking(sub)">✕ Cancel booking</button>
                  </DropdownMenu>
                </td>
              </tr>

              <tr v-if="filteredSubscribers.length === 0">
                <td colspan="8" class="p-8 text-center text-slate-500 dark:text-slate-400 font-medium">
                  No subscribers found matching your filter criteria.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
