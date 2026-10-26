<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatMoney } from '@/Utils/currency';
import Select from '@/Components/Ui/Select.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Button from '@/Components/Ui/Button.vue';
import Alert from '@/Components/Ui/Alert.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import { useDraft } from '@/Composables/useDraft';

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
      // A new event offers every option the lodge uses; an event that already exists keeps its own choice.
      is_enabled: saved ? !!saved.is_enabled : !props.event.id,
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

// Everything typed here is kept on this device until the event is saved, so opening the payment options (or a refresh) loses nothing.
const draft = useDraft({
  key: `event-draft:${props.club.slug}:${props.event.id ?? 'new'}`,
  version: String(props.event.updated_at ?? 'new'),
  snapshot: () => ({ form: form.data(), menu: JSON.parse(JSON.stringify(menu)) }),
  restore: (saved) => {
    Object.assign(form, saved.form);
    Object.keys(saved.menu || {}).forEach((course) => { menu[course] = saved.menu[course]; });
  },
});

// Payment options are edited once for the whole lodge; open them in a new tab and come back to this page afterwards.
const paymentOptionsUrl = computed(() => `${route('admin.payment_options.index', { clubSlug: props.club.slug })}?return=${encodeURIComponent(typeof window !== 'undefined' ? window.location.pathname : '')}`);

const submit = () => {
  form
    .transform((data) => ({
      ...data,
      menu_items: data.has_dining
        ? COURSES.flatMap(({ key }) => menu[key].map((dish) => ({ ...dish, category: key })))
        : [],
    }))
    .post(route('admin.events.store', { clubSlug: props.club.slug }), { onSuccess: () => draft.clear() });
};

const duplicate = () => router.post(route('admin.events.duplicate', { clubSlug: props.club.slug, id: props.event.id }));

const confirmingCancel = ref(false);
const cancelEvent = () => router.post(route('admin.events.cancel', { clubSlug: props.club.slug, id: props.event.id }), {}, {
  onFinish: () => { confirmingCancel.value = false; },
});

// The row editors (ticket types, promo codes, dishes, payment options) use compact fields with the kit's look.
const cell = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const cellLabel = 'mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300';
const checkbox = 'mt-0.5 h-4 w-4 rounded accent-blue-600';
const rowBox = 'rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50';
const STATUS_OPTIONS = [
  { value: 'draft', label: 'Draft (only organisers can see it)' },
  { value: 'upcoming', label: 'Published (open for booking)' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
];
const PRICE_DISPLAY_OPTIONS = [
  { value: 'standard', label: 'The ticket price (extras shown when people choose how to pay)' },
  { value: 'all_in', label: 'All-in: the highest price, with cheaper options shown as savings' },
];
</script>

<template>
  <AdminLayout :title="event.id ? 'Edit event' : 'Create an event'" :club="club" active-tab="events">
    <Head :title="event.id ? 'Edit event' : 'Create an event'" />

    <div class="mx-auto max-w-4xl space-y-6">
      <Card>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ event.id ? 'Edit the event' : 'Create an event' }}</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Details, places, ticket types, dishes and who can book. A new event starts as a draft that only organisers can see.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <Button v-if="event.id" variant="secondary" size="sm" @click="duplicate">Duplicate</Button>
            <Button v-if="event.id && event.status !== 'cancelled'" variant="secondary" size="sm" @click="confirmingCancel = true">Cancel event</Button>
            <Button variant="ghost" size="sm" :href="route('admin.events.index', { clubSlug: club.slug })">Back to events</Button>
          </div>
        </div>
      </Card>

      <Alert v-if="draft.restored.value" variant="warning">
        Restored the changes you had not saved yet.
        <button type="button" class="ml-1 font-medium underline" @click="draft.discard">Discard them</button>
      </Alert>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Status</h3></template>
          <div class="grid items-start gap-5 sm:grid-cols-2">
            <Select v-model="form.status" label="Event status" :options="STATUS_OPTIONS" :error="form.errors.status" />
            <p class="text-sm text-slate-500 dark:text-slate-400 sm:pt-6">Members are told about the event once, when it is first published. {{ registrationCount }} {{ registrationCount === 1 ? 'booking' : 'bookings' }} so far.</p>
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Who can see this event and book</h3></template>
          <div class="space-y-4">
            <div class="grid gap-5 sm:grid-cols-2">
              <Select v-model="form.visibility" label="Who can see this event" :options="visibilityOptions" :hint="hintFor(form.visibility)" :error="form.errors.visibility" />
              <Select v-model="form.rsvp_audience" label="Who can book" :options="rsvpOptions" :hint="hintFor(form.rsvp_audience)" :error="form.errors.rsvp_audience" />
            </div>
            <label class="flex items-start gap-3 text-sm" :class="canAllowPublic ? '' : 'opacity-60'">
              <input v-model="form.allow_public_registration" type="checkbox" :disabled="!canAllowPublic" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Let outside guests book with just an email</span>
                <span class="block text-slate-500 dark:text-slate-400">They don't need an account. Only available when everyone can see the event.</span>
              </span>
            </label>
            <p v-if="form.errors.allow_public_registration" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.allow_public_registration }}</p>
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Event details</h3></template>
          <div class="space-y-4">
            <Input v-model="form.title" label="Title" required maxlength="255" placeholder="Annual dinner" :error="form.errors.title" />
            <Textarea v-model="form.description" label="Description" :rows="3" maxlength="10000" placeholder="What the event is, dress code, parking, anything people should know" :error="form.errors.description" />
            <Textarea v-model="form.cancellation_policy" label="Cancellation policy" hint="Optional." :rows="2" maxlength="5000" placeholder="For example: cancel up to 7 days before for a full refund." :error="form.errors.cancellation_policy" />
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Venue</h3></template>
          <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Input v-model="form.address_line_1" label="Venue or building" maxlength="255" placeholder="Freemasons' Hall" :error="form.errors.address_line_1" />
              <Input v-model="form.address_line_2" label="Street" maxlength="255" placeholder="Great Queen Street" :error="form.errors.address_line_2" />
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Input v-model="form.city" label="Town or city" maxlength="255" :error="form.errors.city" />
              <Input v-model="form.county" label="County or region" maxlength="255" :error="form.errors.county" />
              <Input v-model="form.postcode" label="Postcode" maxlength="50" :error="form.errors.postcode" />
            </div>
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">When</h3></template>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <Input v-model="form.starts_at" type="datetime-local" label="Starts" required :error="form.errors.starts_at" />
            <Input v-model="form.ends_at" type="datetime-local" label="Ends" hint="Optional." :error="form.errors.ends_at" />
            <Input v-model="form.registration_opens_at" type="datetime-local" label="Booking opens" hint="Optional." :error="form.errors.registration_opens_at" />
            <Input v-model="form.rsvp_deadline" type="datetime-local" label="Booking closes" hint="Optional." :error="form.errors.rsvp_deadline" />
            <Input v-model="form.booking_cutoff_days" class="md:col-span-2" type="number" min="0" max="1000" label="Or close bookings this many days before" placeholder="7" hint="If you set an exact closing time above, that is used instead." :error="form.errors.booking_cutoff_days" />
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Places and guests</h3></template>
          <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
            <Input v-model="form.capacity" type="number" min="1" max="100000" label="Total places" placeholder="No limit" hint="Counts every person, guests included. Leave empty for no limit." :error="form.errors.capacity" />
            <Input v-model="form.max_guests_per_booking" type="number" min="0" max="50" label="Guests per booking" placeholder="Club default" :error="form.errors.max_guests_per_booking" />
            <label class="flex items-start gap-3 text-sm md:pt-6">
              <input v-model="form.waitlist_enabled" type="checkbox" :class="checkbox" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Keep a waiting list</span>
                <span class="block text-slate-500 dark:text-slate-400">When it is full, new bookings wait and move up as places free up.</span>
              </span>
            </label>
          </div>
        </Card>

        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Price</h3></template>
          <div class="grid grid-cols-1 items-start gap-4 md:grid-cols-3">
            <label class="flex items-start gap-3 text-sm md:pt-6">
              <input v-model="form.requires_payment" type="checkbox" :class="checkbox" />
              <span class="font-medium text-slate-900 dark:text-white">This event has a charge</span>
            </label>
            <Input v-model="form.price" type="number" step="0.01" min="0" label="Ticket price" :prefix="$cs" hint="Used when there are no ticket types below." :error="form.errors.price" />
            <Input v-if="form.has_dining" v-model="form.dining_price" type="number" step="0.01" min="0" label="Dinner price" :prefix="$cs" :error="form.errors.dining_price" />
          </div>
        </Card>

        <Card>
          <template #header>
            <div class="flex items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Ticket types</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Optional. Charge members and guests differently: people pick a type when they book, and if only one applies to them it is chosen for them.</p>
              </div>
              <Button variant="secondary" size="sm" @click="addTier">Add a ticket type</Button>
            </div>
          </template>
          <div class="space-y-3">
            <div v-for="(tier, index) in form.ticket_tiers" :key="index" :class="[rowBox, 'grid grid-cols-1 items-end gap-3 sm:grid-cols-5']">
              <div><label :for="`tier-name-${index}`" :class="cellLabel">Name</label><input :id="`tier-name-${index}`" v-model="tier.name" type="text" maxlength="150" placeholder="Member" :class="cell" /></div>
              <div><label :for="`tier-price-${index}`" :class="cellLabel">Price ({{ $cs }})</label><input :id="`tier-price-${index}`" v-model="tier.price" type="number" step="0.01" min="0" :class="cell" /></div>
              <div><label :for="`tier-limit-${index}`" :class="cellLabel">Limit (0 for none)</label><input :id="`tier-limit-${index}`" v-model="tier.max_quantity" type="number" min="0" :class="cell" /></div>
              <div>
                <label :for="`tier-audience-${index}`" :class="cellLabel">For</label>
                <select :id="`tier-audience-${index}`" v-model="tier.audience" :class="cell">
                  <option value="all">Everyone</option>
                  <option value="member">Members</option>
                  <option value="guest">Guests</option>
                  <option value="public">Outside guests</option>
                </select>
              </div>
              <div class="pb-1.5">
                <Button v-if="!tierLocked(tier)" variant="ghost" size="sm" @click="removeTier(index)">Remove</Button>
                <span v-else class="text-xs text-slate-500 dark:text-slate-400" title="People have booked this ticket type">In use</span>
              </div>
            </div>
            <p v-if="form.errors.ticket_tiers" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.ticket_tiers }}</p>
          </div>
        </Card>

        <Card>
          <template #header>
            <div class="flex items-center justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Promo codes</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Optional.</p>
              </div>
              <Button variant="secondary" size="sm" @click="addPromo">Add a promo code</Button>
            </div>
          </template>
          <div class="space-y-3">
            <div v-for="(promo, index) in form.promos" :key="index" :class="[rowBox, 'grid grid-cols-1 items-end gap-3 sm:grid-cols-4']">
              <div><label :for="`promo-code-${index}`" :class="cellLabel">Code</label><input :id="`promo-code-${index}`" v-model="promo.code" type="text" maxlength="40" placeholder="CODE" :class="[cell, 'font-mono uppercase']" /></div>
              <div><label :for="`promo-discount-${index}`" :class="cellLabel">Discount (%)</label><input :id="`promo-discount-${index}`" v-model="promo.discount_amount" type="number" min="0" max="100" :class="cell" /></div>
              <div><label :for="`promo-uses-${index}`" :class="cellLabel">Most uses</label><input :id="`promo-uses-${index}`" v-model="promo.max_uses" type="number" min="0" :class="cell" /></div>
              <div class="pb-1.5"><Button variant="ghost" size="sm" @click="removePromo(index)">Remove</Button></div>
            </div>
          </div>
        </Card>

        <Card v-if="form.requires_payment">
          <template #header>
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Discounts and fees by payment method</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Every payment option your lodge uses is offered on this event. Untick one to hide it here, or add a discount to encourage early payment or a fee for paying later.</p>
              </div>
              <a :href="paymentOptionsUrl" target="_blank" rel="noopener" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Edit payment options (opens a new tab)</a>
            </div>
          </template>

          <div class="space-y-4">
            <p v-if="!form.payment_methods.length" class="text-sm text-slate-500 dark:text-slate-400">No payment options are switched on yet. <a :href="paymentOptionsUrl" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Switch some on</a> in a new tab, then refresh this page. What you have typed here is kept.</p>

            <div v-for="row in form.payment_methods" :key="row.payment_method_id" class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
              <label class="flex flex-wrap items-center gap-3 text-sm">
                <input v-model="row.is_enabled" type="checkbox" :class="checkbox" />
                <span class="font-medium text-slate-900 dark:text-white">{{ methodInfo(row.payment_method_id).label }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">Lodge default: {{ defaultSummary(methodInfo(row.payment_method_id)) }}</span>
                <span v-if="methodInfo(row.payment_method_id).complete === false" class="text-xs font-medium text-amber-700 dark:text-amber-300">Its details are not complete, so it is not offered yet.</span>
              </label>

              <div v-if="row.is_enabled" class="grid items-end gap-3 sm:grid-cols-4">
                <div>
                  <label :for="`adj-${row.payment_method_id}`" :class="cellLabel">For this event</label>
                  <select :id="`adj-${row.payment_method_id}`" :value="adjustmentChoice(row)" :class="cell" @change="setAdjustmentChoice(row, $event.target.value)">
                    <option value="">Use the default</option>
                    <option value="none">No discount or fee</option>
                    <option value="discount">Discount</option>
                    <option v-if="canFee(row)" value="fee">Fee</option>
                  </select>
                </div>
                <template v-if="row.adjustment_kind === 'discount' || row.adjustment_kind === 'fee'">
                  <div>
                    <label :for="`adj-mode-${row.payment_method_id}`" :class="cellLabel">How</label>
                    <select :id="`adj-mode-${row.payment_method_id}`" v-model="row.adjustment_mode" :class="cell"><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select>
                  </div>
                  <div>
                    <label :for="`adj-amount-${row.payment_method_id}`" :class="cellLabel">{{ row.adjustment_mode === 'percent' ? 'Percent' : 'Amount' }}</label>
                    <input :id="`adj-amount-${row.payment_method_id}`" v-model="row.adjustment_amount" type="number" step="0.01" min="0" :class="cell" />
                  </div>
                  <div v-if="row.adjustment_mode === 'fixed'">
                    <label :for="`adj-scope-${row.payment_method_id}`" :class="cellLabel">Applies</label>
                    <select :id="`adj-scope-${row.payment_method_id}`" v-model="row.adjustment_scope" :class="cell"><option value="per_person">per person</option><option value="per_booking">per booking</option></select>
                  </div>
                </template>
              </div>

              <div v-if="row.is_enabled && methodInfo(row.payment_method_id).type === 'pay_later'" class="grid items-end gap-3 sm:grid-cols-4">
                <div>
                  <label :for="`due-days-${row.payment_method_id}`" :class="cellLabel">Payment due (days)</label>
                  <input :id="`due-days-${row.payment_method_id}`" v-model="row.due_days" type="number" min="0" max="365" :placeholder="methodInfo(row.payment_method_id).due_days ?? ''" :class="cell" />
                </div>
                <div>
                  <label :for="`due-basis-${row.payment_method_id}`" :class="cellLabel">Counted from</label>
                  <select :id="`due-basis-${row.payment_method_id}`" v-model="row.due_basis" :class="cell"><option :value="null">Lodge default</option><option value="before_event">before the event</option><option value="after_booking">after booking</option></select>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 sm:col-span-2">Leave empty to use the lodge's default due date for this event.</p>
              </div>
            </div>
            <p v-if="form.errors.payment_methods" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.payment_methods }}</p>

            <div class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
              <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Booking fee (goes to the lodge)</h4>
              <div class="grid items-end gap-3 sm:grid-cols-4">
                <div>
                  <label for="fee-type" :class="cellLabel">Fee</label>
                  <select id="fee-type" v-model="form.booking_fee_type" :class="cell"><option value="none">None</option><option value="fixed">Fixed amount</option><option value="percent">Percentage</option></select>
                </div>
                <template v-if="form.booking_fee_type !== 'none'">
                  <div>
                    <label for="fee-amount" :class="cellLabel">{{ form.booking_fee_type === 'percent' ? 'Percent' : 'Amount' }}</label>
                    <input id="fee-amount" v-model="form.booking_fee_amount" type="number" step="0.01" min="0" :class="cell" />
                  </div>
                  <div v-if="form.booking_fee_type === 'fixed'">
                    <label for="fee-scope" :class="cellLabel">Charged</label>
                    <select id="fee-scope" v-model="form.booking_fee_scope" :class="cell"><option value="per_booking">once per booking</option><option value="per_ticket">per person</option></select>
                  </div>
                  <div>
                    <label for="fee-label" :class="cellLabel">Called</label>
                    <input id="fee-label" v-model="form.booking_fee_label" type="text" maxlength="60" placeholder="Booking fee" :class="cell" />
                  </div>
                </template>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400">The same for every payment option, and always shown in the price before anyone pays.</p>
            </div>

            <Select v-model="form.price_display" label="Advertise the price as" :options="PRICE_DISPLAY_OPTIONS" hint="All-in is the safest choice when any option carries a fee: nothing is added late. Under UK rules, fees that can't be avoided must be included in the price you advertise." :error="form.errors.price_display" />

            <div v-if="pricePreview && pricePreview.scenarios.some((s) => s.options.length)" class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/40">
              <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">What each option costs</h4>
                <span class="text-xs text-slate-500 dark:text-slate-400">Advertised as {{ formatMoney(pricePreview.advertised.headline) }}<template v-if="pricePreview.advertised.has_saving"> (from {{ formatMoney(pricePreview.advertised.lowest) }})</template>. Updates when you save.</span>
              </div>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead><tr class="text-left text-xs text-slate-500 dark:text-slate-400"><th scope="col" class="py-1 pr-3 font-medium">Booking</th><th v-for="option in pricePreview.scenarios[0].options" :key="option.label" scope="col" class="py-1 pr-3 font-medium">{{ option.label }}</th></tr></thead>
                  <tbody>
                    <tr v-for="scenario in pricePreview.scenarios" :key="scenario.label" class="border-t border-slate-200 dark:border-slate-700">
                      <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-300">{{ scenario.label }}</td>
                      <td v-for="option in scenario.options" :key="option.label" class="py-1.5 pr-3 font-mono text-slate-900 dark:text-white">{{ formatMoney(option.total) }}<span v-if="option.saving > 0" class="ml-1 font-sans text-xs text-emerald-700 dark:text-emerald-400">save {{ formatMoney(option.saving) }}</span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <template #header>
            <label class="flex items-center gap-3">
              <input v-model="form.has_dining" type="checkbox" :class="checkbox" />
              <span class="text-base font-semibold text-slate-900 dark:text-white">There is a meal with a choice of dishes</span>
            </label>
          </template>

          <p v-if="!form.has_dining" class="text-sm text-slate-500 dark:text-slate-400">Tick this to add starters, mains and desserts for people to choose from.</p>
          <div v-else class="space-y-5">
            <p class="text-sm text-slate-500 dark:text-slate-400">Enter the dishes people can choose from (three of each course to start with; leave empty any you don't need). Whoever books picks one starter, main and dessert for themselves and for each guest.</p>

            <div v-for="course in COURSES" :key="course.key" class="space-y-2">
              <div class="flex items-center justify-between">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ course.label }}</h4>
                <Button variant="ghost" size="sm" @click="addDish(course.key)">Add a {{ course.single }}</Button>
              </div>

              <div v-for="(dish, index) in menu[course.key]" :key="index" :class="[rowBox, 'space-y-2']">
                <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-[1fr_1.4fr_auto]">
                  <input v-model="dish.name" type="text" maxlength="150" :aria-label="`${course.single} ${index + 1} name`" :placeholder="`${course.single[0].toUpperCase()}${course.single.slice(1)} ${index + 1}`" :class="cell" />
                  <input v-model="dish.description" type="text" maxlength="500" :aria-label="`${course.single} ${index + 1} description`" placeholder="Description (optional)" :class="cell" />
                  <Button v-if="!dishLocked(dish)" variant="ghost" size="sm" @click="removeDish(course.key, index)">Remove</Button>
                  <span v-else class="text-xs text-slate-500 dark:text-slate-400" title="Guests have chosen this dish">In use</span>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-600 dark:text-slate-300">
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_vegetarian" type="checkbox" class="h-3.5 w-3.5 rounded accent-blue-600" /> Vegetarian</label>
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_vegan" type="checkbox" class="h-3.5 w-3.5 rounded accent-blue-600" /> Vegan</label>
                  <label class="flex items-center gap-1.5"><input v-model="dish.is_gf" type="checkbox" class="h-3.5 w-3.5 rounded accent-blue-600" /> Gluten free</label>
                  <input v-model="dish.allergens" type="text" maxlength="255" :aria-label="`${course.single} ${index + 1} allergens`" placeholder="Allergens (for example nuts, dairy)" :class="[cell, 'min-w-[10rem] flex-1']" />
                </div>
              </div>
            </div>
            <p v-if="form.errors.menu_items" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.menu_items }}</p>
          </div>
        </Card>

        <Button type="submit" block size="lg" :loading="form.processing">{{ form.status === 'draft' ? 'Save draft' : 'Save event' }}</Button>
      </form>
    </div>

    <ConfirmDialog :open="confirmingCancel" :title="`Cancel ${event.title}?`" confirm-label="Cancel event" cancel-label="Keep it" @confirm="cancelEvent" @close="confirmingCancel = false">
      Existing bookings are kept, but nobody can book any more.
    </ConfirmDialog>
  </AdminLayout>
</template>
