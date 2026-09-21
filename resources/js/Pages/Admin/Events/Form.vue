<script setup>
import { computed, reactive, watch } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { currencySymbol } from '@/Utils/currency';
import Select from '@/Components/Ui/Select.vue';
import Card from '@/Components/Ui/Card.vue';

const props = defineProps({
  club: Object,
  event: Object,
  visibilityOptions: { type: Array, default: () => [] },
  tiersInUse: { type: Array, default: () => [] },
  dishesInUse: { type: Array, default: () => [] },
  registrationCount: { type: Number, default: 0 },
  clubPaymentMethods: { type: Array, default: () => [] },
  enabledMethods: { type: Array, default: () => [] },
  pricePreview: { type: Object, default: null },
});

// The server sends dates as ISO strings; date-time inputs want YYYY-MM-DDTHH:mm.
const localInput = (value) => (value ? String(value).slice(0, 16) : '');

const COURSES = [
  { key: 'starter', label: 'Starters', single: 'starter' },
  { key: 'main', label: 'Mains', single: 'main course' },
  { key: 'dessert', label: 'Desserts', single: 'dessert' },
];

const blankDish = () => ({ id: null, name: '', description: '', allergens: '', is_vegetarian: false, is_vegan: false, is_gf: false });

// Three dishes per course to start with; the organiser fills them in and can add or remove.
const menu = reactive(Object.fromEntries(COURSES.map(({ key }) => {
  const saved = (props.event.menu_items || [])
    .filter((item) => item.category === key)
    .sort((x, y) => (x.sort_order ?? 0) - (y.sort_order ?? 0) || x.id - y.id)
    .map((item) => ({ ...blankDish(), ...item }));

  return [key, saved.length ? saved : [blankDish(), blankDish(), blankDish()]];
})));

const form = useForm({
  id: props.event.id || null,
  title: props.event.title || '',
  slug: props.event.slug || '',
  description: props.event.description || '',
  cancellation_policy: props.event.cancellation_policy || '',
  location: props.event.location || '',
  address_line_1: props.event.address_line_1 || '',
  address_line_2: props.event.address_line_2 || '',
  city: props.event.city || '',
  county: props.event.county || '',
  postcode: props.event.postcode || '',
  starts_at: localInput(props.event.starts_at),
  ends_at: localInput(props.event.ends_at),
  registration_opens_at: localInput(props.event.registration_opens_at),
  rsvp_deadline: localInput(props.event.rsvp_deadline),
  booking_cutoff_days: props.event.booking_cutoff_days ?? 7,
  capacity: props.event.capacity ?? '',
  waitlist_enabled: props.event.waitlist_enabled ?? false,
  max_guests_per_booking: props.event.max_guests_per_booking ?? '',
  allow_public_registration: props.event.allow_public_registration ?? false,
  requires_payment: props.event.requires_payment ?? true,
  price: props.event.price || 0,
  has_dining: props.event.has_dining ?? false,
  dining_price: props.event.dining_price || 0,
  status: props.event.status || 'draft',
  visibility: props.event.visibility || 'club',
  rsvp_audience: props.event.rsvp_audience || 'club',
  booking_fee_type: props.event.booking_fee_type || 'none',
  booking_fee_amount: props.event.booking_fee_amount || 0,
  booking_fee_scope: props.event.booking_fee_scope || 'per_booking',
  booking_fee_label: props.event.booking_fee_label || '',
  price_display: props.event.price_display || 'standard',
  // One row per payment option the club has set up: switched on or off, with this event's own discount or fee.
  payment_methods: props.clubPaymentMethods.map((method) => {
    const saved = props.enabledMethods.find((row) => row.payment_method_id === method.id);

    return {
      payment_method_id: method.id,
      is_enabled: saved ? !!saved.is_enabled : false,
      adjustment_kind: saved?.adjustment_kind ?? null, // null = use the option's own default
      adjustment_mode: saved?.adjustment_mode ?? method.default_adjustment_mode ?? 'fixed',
      adjustment_amount: saved?.adjustment_amount ?? method.default_adjustment_amount ?? 0,
      adjustment_scope: saved?.adjustment_scope ?? method.default_adjustment_scope ?? 'per_person',
      due_days: saved?.due_days ?? null,
      due_basis: saved?.due_basis ?? null,
    };
  }),
  ticket_tiers: (props.event.ticket_tiers || []).map((tier) => ({ ...tier, audience: tier.audience || 'all' })),
  promos: props.event.promos || [],
});

// Options run narrowest to widest, so an option's position is its breadth.
const breadth = (value) => props.visibilityOptions.findIndex((o) => o.value === value);

const rsvpOptions = computed(() => props.visibilityOptions.filter((o) => breadth(o.value) <= breadth(form.visibility)));

const hintFor = (value) => props.visibilityOptions.find((o) => o.value === value)?.description ?? '';

const canAllowPublic = computed(() => form.visibility === 'public');

watch(() => form.visibility, () => {
  if (breadth(form.rsvp_audience) > breadth(form.visibility)) {
    form.rsvp_audience = form.visibility;
  }

  if (!canAllowPublic.value) {
    form.allow_public_registration = false;
  }
});

const methodInfo = (id) => props.clubPaymentMethods.find((m) => m.id === id) ?? {};
const canFee = (row) => ['pay_later', 'cash_on_door'].includes(methodInfo(row.payment_method_id).type);

// "" = use the option's default; the rest is this event's own adjustment.
const adjustmentChoice = (row) => row.adjustment_kind ?? '';
const setAdjustmentChoice = (row, value) => {
  row.adjustment_kind = value === '' ? null : value;
};

const defaultSummary = (method) => {
  if (!method || method.default_adjustment_kind === 'none' || !Number(method.default_adjustment_amount)) return 'no discount or fee';
  const amount = method.default_adjustment_mode === 'percent' ? `${Number(method.default_adjustment_amount)}%` : Number(method.default_adjustment_amount).toFixed(2);
  const scope = method.default_adjustment_mode === 'percent' ? '' : (method.default_adjustment_scope === 'per_person' ? ' per person' : ' per booking');

  return method.default_adjustment_kind === 'discount' ? `${amount} off${scope}` : `${amount} fee${scope}`;
};

const money = (value) => `${currencySymbol()}${Number(value).toFixed(2)}`;

const addTier = () => {
  form.ticket_tiers.push({ id: null, name: '', price: 0, max_quantity: 0, audience: 'all' });
};

const removeTier = (index) => {
  form.ticket_tiers.splice(index, 1);
};

const addPromo = () => {
  form.promos.push({ code: '', discount_amount: 10, max_uses: 50 });
};

const removePromo = (index) => {
  form.promos.splice(index, 1);
};

const addDish = (course) => {
  menu[course].push(blankDish());
};

const dishLocked = (dish) => dish.id && props.dishesInUse.includes(dish.id);
const tierLocked = (tier) => tier.id && props.tiersInUse.includes(tier.id);

const removeDish = (course, index) => {
  if (!dishLocked(menu[course][index])) {
    menu[course].splice(index, 1);
  }
};

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      menu_items: data.has_dining
        ? COURSES.flatMap(({ key }) => menu[key].map((dish) => ({ ...dish, category: key })))
        : [],
    }))
    .post(route('admin.events.store', { clubSlug: props.club.slug }));
};

const duplicate = () => router.post(route('admin.events.duplicate', { clubSlug: props.club.slug, id: props.event.id }));

const cancelEvent = () => {
  if (confirm(`Cancel "${props.event.title}"? Existing bookings are kept, but nobody can book any more.`)) {
    router.post(route('admin.events.cancel', { clubSlug: props.club.slug, id: props.event.id }));
  }
};

const inputClass = 'w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500';
const smallInput = 'px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-800 dark:text-slate-100';
const labelClass = 'block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5';
const cardClass = 'bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4';
const cardTitle = 'text-base font-bold text-slate-900 dark:text-white border-b border-slate-100 dark:border-slate-800 pb-3';
</script>

<template>
  <AdminLayout :title="`${event.id ? 'Edit' : 'Create'} Event`" :club="club" active-tab="events">
    <Head :title="`${event.id ? 'Edit' : 'Create'} Event`" />

    <div class="max-w-4xl mx-auto space-y-6">

      <!-- Top Action Bar -->
      <div class="flex flex-wrap items-center justify-between gap-3 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ event.id ? 'Edit Event Details' : 'Create New Event' }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Details, places, ticket types, dishes and who can book. New events start as a draft that only organisers can see.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button v-if="event.id" type="button" @click="duplicate" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all">Duplicate</button>
          <button v-if="event.id && event.status !== 'cancelled'" type="button" @click="cancelEvent" class="px-4 py-2 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 border border-rose-200 dark:border-rose-800/60 text-rose-700 dark:text-rose-300 text-xs font-semibold rounded-xl transition-all">Cancel event</button>
          <Link :href="route('admin.events.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all">
            &larr; Back to Events
          </Link>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-6">

        <!-- Status -->
        <Card>
          <template #header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Status</h3>
          </template>
          <div class="grid sm:grid-cols-2 gap-5 items-start">
            <div>
              <label :class="labelClass">Event status</label>
              <select v-model="form.status" :class="inputClass">
                <option value="draft">Draft (only organisers can see it)</option>
                <option value="upcoming">Published (open for booking)</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
              <p v-if="form.errors.status" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.status }}</p>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 sm:pt-6">Members are told about the event once, when it is first published. {{ registrationCount }} {{ registrationCount === 1 ? 'booking' : 'bookings' }} so far.</p>
          </div>
        </Card>

        <!-- Audience -->
        <Card>
          <template #header>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Who can see this event and book</h3>
          </template>
          <div class="grid sm:grid-cols-2 gap-5">
            <Select v-model="form.visibility" label="Who can see this event" :options="visibilityOptions" :hint="hintFor(form.visibility)" :error="form.errors.visibility" />
            <Select v-model="form.rsvp_audience" label="Who can book" :options="rsvpOptions" :hint="hintFor(form.rsvp_audience)" :error="form.errors.rsvp_audience" />
          </div>
          <label class="flex items-start gap-3 text-xs" :class="canAllowPublic ? '' : 'opacity-60'">
            <input v-model="form.allow_public_registration" type="checkbox" :disabled="!canAllowPublic" class="mt-0.5 w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700" />
            <span>
              <span class="font-bold text-slate-900 dark:text-white">Let outside guests book with just an email</span>
              <span class="block text-slate-500 dark:text-slate-400">They don't need an account. Only available when everyone can see the event.</span>
            </span>
          </label>
          <p v-if="form.errors.allow_public_registration" class="text-[11px] font-semibold text-rose-600">{{ form.errors.allow_public_registration }}</p>
        </Card>

        <!-- General Details -->
        <div :class="cardClass">
          <h3 :class="cardTitle">Event Details</h3>

          <div>
            <label :class="labelClass">Event Title</label>
            <input v-model="form.title" type="text" required maxlength="255" placeholder="Annual Dinner" :class="inputClass" />
            <p v-if="form.errors.title" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.title }}</p>
          </div>

          <div>
            <label :class="labelClass">Description</label>
            <textarea v-model="form.description" rows="3" maxlength="10000" placeholder="What is the event, dress code, parking, anything people should know..." :class="inputClass"></textarea>
          </div>

          <div>
            <label :class="labelClass">Cancellation policy (optional)</label>
            <textarea v-model="form.cancellation_policy" rows="2" maxlength="5000" placeholder="e.g. Cancel up to 7 days before for a full refund." :class="inputClass"></textarea>
          </div>
        </div>

        <!-- Venue Address Section -->
        <div :class="cardClass">
          <h3 :class="cardTitle">📍 Venue</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label :class="labelClass">Venue / Building Name</label>
              <input v-model="form.address_line_1" type="text" maxlength="255" placeholder="e.g. Freemasons' Hall" :class="inputClass" />
            </div>
            <div>
              <label :class="labelClass">Street Address</label>
              <input v-model="form.address_line_2" type="text" maxlength="255" placeholder="e.g. Great Queen Street" :class="inputClass" />
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label :class="labelClass">City / Town</label>
              <input v-model="form.city" type="text" maxlength="255" :class="inputClass" />
            </div>
            <div>
              <label :class="labelClass">County / Region</label>
              <input v-model="form.county" type="text" maxlength="255" :class="inputClass" />
            </div>
            <div>
              <label :class="labelClass">Postcode</label>
              <input v-model="form.postcode" type="text" maxlength="50" :class="inputClass" />
            </div>
          </div>
        </div>

        <!-- Schedule -->
        <div :class="cardClass">
          <h3 :class="cardTitle">⏰ When</h3>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label :class="labelClass">Starts</label>
              <input v-model="form.starts_at" type="datetime-local" required :class="inputClass" />
              <p v-if="form.errors.starts_at" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.starts_at }}</p>
            </div>
            <div>
              <label :class="labelClass">Ends (optional)</label>
              <input v-model="form.ends_at" type="datetime-local" :class="inputClass" />
              <p v-if="form.errors.ends_at" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.ends_at }}</p>
            </div>
            <div>
              <label :class="labelClass">Booking opens (optional)</label>
              <input v-model="form.registration_opens_at" type="datetime-local" :class="inputClass" />
            </div>
            <div>
              <label :class="labelClass">Booking closes on (optional)</label>
              <input v-model="form.rsvp_deadline" type="datetime-local" :class="inputClass" />
            </div>
            <div class="md:col-span-2">
              <label :class="labelClass">Or close bookings this many days before the event</label>
              <input v-model="form.booking_cutoff_days" type="number" min="0" max="1000" placeholder="e.g. 7" :class="inputClass" />
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">If you set an exact closing date above, that date is used instead.</p>
            </div>
          </div>
        </div>

        <!-- Places -->
        <div :class="cardClass">
          <h3 :class="cardTitle">👥 Places and guests</h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <div>
              <label :class="labelClass">Total places</label>
              <input v-model="form.capacity" type="number" min="1" max="100000" placeholder="No limit" :class="inputClass" />
              <p v-if="form.errors.capacity" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.capacity }}</p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Counts every person, guests included. Leave blank for no limit.</p>
            </div>
            <div>
              <label :class="labelClass">Guests per booking</label>
              <input v-model="form.max_guests_per_booking" type="number" min="0" max="50" :placeholder="'Club default'" :class="inputClass" />
            </div>
            <label class="flex items-start gap-3 text-xs md:pt-6">
              <input v-model="form.waitlist_enabled" type="checkbox" class="mt-0.5 w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700" />
              <span>
                <span class="font-bold text-slate-900 dark:text-white">Keep a waiting list</span>
                <span class="block text-slate-500 dark:text-slate-400">When full, new bookings wait and move up as places free up.</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Price -->
        <div :class="cardClass">
          <h3 :class="cardTitle">💷 Price</h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
            <label class="flex items-start gap-3 text-xs md:pt-6">
              <input v-model="form.requires_payment" type="checkbox" class="mt-0.5 w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700" />
              <span class="font-bold text-slate-900 dark:text-white">This event has a charge</span>
            </label>
            <div>
              <label :class="labelClass">Ticket price ({{ $cs }})</label>
              <input v-model="form.price" type="number" step="0.01" min="0" :class="inputClass" />
              <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Used when you have no ticket types below.</p>
            </div>
            <div v-if="form.has_dining">
              <label :class="labelClass">Dinner price ({{ $cs }})</label>
              <input v-model="form.dining_price" type="number" step="0.01" min="0" :class="inputClass" />
            </div>
          </div>
        </div>

        <!-- Ticket Types -->
        <div :class="cardClass">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">🎟️ Ticket types (optional)</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Charge members and guests differently. People pick a type when they book; if only one applies to them it is chosen for them.</p>
            </div>
            <button type="button" @click="addTier" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 text-xs font-semibold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-all">+ Add ticket type</button>
          </div>

          <div v-for="(tier, index) in form.ticket_tiers" :key="index" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-5 gap-3 items-center">
            <input v-model="tier.name" type="text" maxlength="150" placeholder="Name (e.g. Member)" :class="smallInput" />
            <input v-model="tier.price" type="number" step="0.01" min="0" :placeholder="'Price (' + $cs + ')'" :class="smallInput" />
            <input v-model="tier.max_quantity" type="number" min="0" placeholder="Limit (0 = none)" :class="smallInput" />
            <select v-model="tier.audience" :class="smallInput">
              <option value="all">Everyone</option>
              <option value="member">Members</option>
              <option value="guest">Guests</option>
              <option value="public">Outside guests</option>
            </select>
            <button v-if="!tierLocked(tier)" type="button" @click="removeTier(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 text-xs font-semibold">Remove</button>
            <span v-else class="text-[11px] text-slate-400" title="People have booked this ticket type">In use</span>
          </div>
          <p v-if="form.errors.ticket_tiers" class="text-[11px] font-semibold text-rose-600">{{ form.errors.ticket_tiers }}</p>
        </div>

        <!-- Promos -->
        <div :class="cardClass">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">🏷️ Promo codes (optional)</h3>
            <button type="button" @click="addPromo" class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60 text-xs font-semibold rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-all">+ Add promo</button>
          </div>

          <div v-for="(promo, index) in form.promos" :key="index" class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 grid grid-cols-1 sm:grid-cols-4 gap-3 items-center">
            <input v-model="promo.code" type="text" maxlength="40" placeholder="CODE" :class="[smallInput, 'font-mono uppercase']" />
            <input v-model="promo.discount_amount" type="number" min="0" max="100" placeholder="Discount %" :class="smallInput" />
            <input v-model="promo.max_uses" type="number" min="0" placeholder="Max uses" :class="smallInput" />
            <button type="button" @click="removePromo(index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 text-xs font-semibold">Remove</button>
          </div>
        </div>

        <!-- Payment options, discounts and fees -->
        <div v-if="form.requires_payment" :class="cardClass">
          <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">💳 How people can pay</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Switch on the options for this event. Encourage early payment with a discount online or a fee later.</p>
            </div>
            <Link :href="route('admin.payment_options.index', { clubSlug: club.slug })" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">Manage options</Link>
          </div>

          <p v-if="!form.payment_methods.length" class="text-xs text-slate-500 dark:text-slate-400">You haven't set up any payment options yet. <Link :href="route('admin.payment_options.index', { clubSlug: club.slug })" class="font-semibold text-blue-600 hover:underline">Set them up</Link> first, then come back to switch them on.</p>

          <div v-for="row in form.payment_methods" :key="row.payment_method_id" class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 space-y-3">
            <label class="flex items-center gap-3 text-sm">
              <input v-model="row.is_enabled" type="checkbox" class="h-4 w-4 rounded text-blue-600" />
              <span class="font-bold text-slate-900 dark:text-white">{{ methodInfo(row.payment_method_id).label }}</span>
              <span class="text-[11px] text-slate-500 dark:text-slate-400">Default: {{ defaultSummary(methodInfo(row.payment_method_id)) }}</span>
            </label>

            <div v-if="row.is_enabled" class="grid gap-3 sm:grid-cols-4 items-end">
              <div>
                <label :class="labelClass">For this event</label>
                <select :value="adjustmentChoice(row)" :class="smallInput + ' w-full'" @change="setAdjustmentChoice(row, $event.target.value)">
                  <option value="">Use the default</option>
                  <option value="none">No discount or fee</option>
                  <option value="discount">Discount</option>
                  <option v-if="canFee(row)" value="fee">Fee</option>
                </select>
              </div>
              <template v-if="row.adjustment_kind === 'discount' || row.adjustment_kind === 'fee'">
                <div>
                  <label :class="labelClass">How</label>
                  <select v-model="row.adjustment_mode" :class="smallInput + ' w-full'"><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select>
                </div>
                <div>
                  <label :class="labelClass">{{ row.adjustment_mode === 'percent' ? 'Percent' : 'Amount' }}</label>
                  <input v-model="row.adjustment_amount" type="number" step="0.01" min="0" :class="smallInput + ' w-full'" />
                </div>
                <div v-if="row.adjustment_mode === 'fixed'">
                  <label :class="labelClass">Applies</label>
                  <select v-model="row.adjustment_scope" :class="smallInput + ' w-full'"><option value="per_person">per person</option><option value="per_booking">per booking</option></select>
                </div>
              </template>
            </div>
          </div>
          <p v-if="form.errors.payment_methods" class="text-[11px] font-semibold text-rose-600">{{ form.errors.payment_methods }}</p>

          <!-- Booking fee -->
          <div class="rounded-xl border border-slate-200 dark:border-slate-800 p-4 space-y-3">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Booking fee (goes to the lodge)</h4>
            <div class="grid gap-3 sm:grid-cols-4 items-end">
              <div>
                <label :class="labelClass">Fee</label>
                <select v-model="form.booking_fee_type" :class="smallInput + ' w-full'"><option value="none">None</option><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select>
              </div>
              <template v-if="form.booking_fee_type !== 'none'">
                <div>
                  <label :class="labelClass">{{ form.booking_fee_type === 'percent' ? 'Percent' : 'Amount' }}</label>
                  <input v-model="form.booking_fee_amount" type="number" step="0.01" min="0" :class="smallInput + ' w-full'" />
                </div>
                <div v-if="form.booking_fee_type === 'fixed'">
                  <label :class="labelClass">Charged</label>
                  <select v-model="form.booking_fee_scope" :class="smallInput + ' w-full'"><option value="per_booking">once per booking</option><option value="per_ticket">per person</option></select>
                </div>
                <div>
                  <label :class="labelClass">Called</label>
                  <input v-model="form.booking_fee_label" type="text" maxlength="60" placeholder="Booking fee" :class="smallInput + ' w-full'" />
                </div>
              </template>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">The same for every payment option, and always shown in the price before anyone pays.</p>
          </div>

          <!-- Advertised price -->
          <div>
            <label :class="labelClass">Advertise the price as</label>
            <select v-model="form.price_display" :class="inputClass">
              <option value="standard">The ticket price (extras shown when people choose how to pay)</option>
              <option value="all_in">All-in: the highest price, with cheaper options shown as savings</option>
            </select>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">All-in is the safest choice when any option carries a fee: nothing is added late. Under UK rules, fees that can't be avoided must be included in the price you advertise.</p>
          </div>

          <!-- Preview from the pricing engine -->
          <div v-if="pricePreview && pricePreview.scenarios.some((s) => s.options.length)" class="rounded-xl bg-slate-50 dark:bg-slate-800/40 p-4 space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
              <h4 class="text-sm font-bold text-slate-900 dark:text-white">What each option costs</h4>
              <span class="text-xs text-slate-500 dark:text-slate-400">Advertised as {{ money(pricePreview.advertised.headline) }}<template v-if="pricePreview.advertised.has_saving"> (from {{ money(pricePreview.advertised.lowest) }})</template>. Updates when you save.</span>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead><tr class="text-left text-slate-500 dark:text-slate-400"><th class="py-1 pr-3 font-semibold">Booking</th><th v-for="option in pricePreview.scenarios[0].options" :key="option.label" class="py-1 pr-3 font-semibold">{{ option.label }}</th></tr></thead>
                <tbody>
                  <tr v-for="scenario in pricePreview.scenarios" :key="scenario.label" class="border-t border-slate-200 dark:border-slate-700">
                    <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-300">{{ scenario.label }}</td>
                    <td v-for="option in scenario.options" :key="option.label" class="py-1.5 pr-3 font-mono font-semibold text-slate-900 dark:text-white">{{ money(option.total) }}<span v-if="option.saving > 0" class="ml-1 font-sans text-[10px] text-emerald-600">save {{ money(option.saving) }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Dinner and dishes -->
        <div :class="cardClass">
          <div class="flex items-center gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <input v-model="form.has_dining" type="checkbox" id="has_dining" class="w-4 h-4 rounded text-blue-600 border-slate-300 dark:border-slate-700" />
            <label for="has_dining" class="font-bold text-slate-900 dark:text-white text-sm">There is a meal with a choice of dishes</label>
          </div>

          <div v-if="form.has_dining" class="space-y-5">
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Enter the dishes people can choose from (three of each course to start with; leave blank any you don't need). Whoever books picks one starter, main and dessert for themselves and for each guest.</p>

            <div v-for="course in COURSES" :key="course.key" class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white">{{ course.label }}</h4>
                <button type="button" @click="addDish(course.key)" class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:underline">+ Add {{ course.single }}</button>
              </div>

              <div v-for="(dish, index) in menu[course.key]" :key="index" class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200/80 dark:border-slate-800/80 space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_1.4fr_auto] gap-2 items-center">
                  <input v-model="dish.name" type="text" maxlength="150" :placeholder="`${course.single[0].toUpperCase()}${course.single.slice(1)} ${index + 1}`" :class="smallInput" />
                  <input v-model="dish.description" type="text" maxlength="500" placeholder="Description (optional)" :class="smallInput" />
                  <button v-if="!dishLocked(dish)" type="button" @click="removeDish(course.key, index)" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 text-xs font-semibold">Remove</button>
                  <span v-else class="text-[11px] text-slate-400" title="Guests have chosen this dish">In use</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-slate-600 dark:text-slate-300">
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_vegetarian" type="checkbox" class="w-3.5 h-3.5 rounded" /> Vegetarian</label>
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_vegan" type="checkbox" class="w-3.5 h-3.5 rounded" /> Vegan</label>
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_gf" type="checkbox" class="w-3.5 h-3.5 rounded" /> Gluten free</label>
                  <input v-model="dish.allergens" type="text" maxlength="255" placeholder="Allergens (e.g. nuts, dairy)" :class="[smallInput, 'flex-1 min-w-[10rem]']" />
                </div>
              </div>
            </div>
            <p v-if="form.errors.menu_items" class="text-[11px] font-semibold text-rose-600">{{ form.errors.menu_items }}</p>
          </div>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-blue-600/20">
          {{ form.processing ? 'Saving...' : (form.status === 'draft' ? 'Save draft' : 'Save event') }}
        </button>

      </form>

    </div>

  </AdminLayout>
</template>
