<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PaymentChoice from '@/Components/Events/PaymentChoice.vue';
import { useEventQuote } from '@/Composables/useEventQuote';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
  club: Object,
  event: Object,
  canBookAsGuest: Boolean,
  isSignedIn: Boolean,
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

const COURSES = [
  { key: 'starter', column: 'starter_item_id', label: 'Starter' },
  { key: 'main', column: 'main_item_id', label: 'Main course' },
  { key: 'dessert', column: 'dessert_item_id', label: 'Dessert' },
];

const blankPerson = () => ({ name: '', ticket_tier_id: null, attending_dining: false, starter_item_id: null, main_item_id: null, dessert_item_id: null, dietary_requirements: '' });

const form = useForm({
  website: '',
  contact_name: '',
  contact_email: '',
  contact_phone: '',
  payment_method: null,
  promo_code: '',
  attendees: [blankPerson()],
});

// Live price from the server, so what is shown is what is charged.
const showPromo = ref(false);
const { quote, error: quoteError, refresh } = useEventQuote(
  route('public.event.quote', { clubSlug: props.club.slug, eventSlug: props.event.slug }),
  () => ({
    promo_code: form.promo_code || null,
    attendees: form.attendees.map((a, index) => ({ is_guest: index > 0, ticket_tier_id: a.ticket_tier_id, attending_dining: a.attending_dining })),
  }),
);

watch(() => [form.attendees, form.promo_code], () => { if (props.canBookAsGuest && props.event.requires_payment) refresh(); }, { deep: true, immediate: true });

watch(quote, (value) => {
  const options = value?.options ?? [];
  if (options.length && !options.some((o) => o.id === form.payment_method)) form.payment_method = options[0].id;
});

const hasMenu = computed(() => props.event.has_dining && COURSES.some((c) => (props.event.menu?.[c.key] ?? []).length));
const tiers = computed(() => (props.event.ticket_tiers ?? []).filter((t) => ['all', 'public', 'guest'].includes(t.audience)));
const full = computed(() => props.event.places_left === 0);
const bookingOpen = computed(() => props.canBookAsGuest && !props.event.is_booking_closed && (!full.value || props.event.waitlist_enabled));
const canAddGuest = computed(() => form.attendees.length - 1 < (props.event.max_guests ?? 0));

const addGuest = () => { if (canAddGuest.value) form.attendees.push(blankPerson()); };
const removePerson = (index) => form.attendees.splice(index, 1);

const setDining = (person, value) => {
  person.attending_dining = value;
  if (!value) person.starter_item_id = person.main_item_id = person.dessert_item_id = null;
};

const err = (index, field) => form.errors[`attendees.${index}.${field}`];
const dishNote = (dish) => [dish.is_vegan ? 'Vegan' : (dish.is_vegetarian ? 'Vegetarian' : null), dish.is_gf ? 'Gluten free' : null, dish.allergens ? `Contains ${dish.allergens}` : null].filter(Boolean).join(' · ');

const submit = () => {
  form.post(route('public.event.register', { clubSlug: props.club.slug, eventSlug: props.event.slug }), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
};

const field = 'w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500';
const label = 'block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1';
</script>

<template>
  <Head :title="`${event.title} - ${club.name}`" />

  <div class="min-h-screen bg-slate-950 text-slate-200 font-sans">
    <div class="mx-auto max-w-3xl px-4 py-8 space-y-6">
      <Link :href="`/site/${club.slug}`" class="text-xs font-semibold text-slate-400 hover:text-white">&larr; {{ club.name }}</Link>

      <div v-if="flash" class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200" role="status">{{ flash }}</div>

      <!-- Event details -->
      <div class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-blue-400">{{ club.name }}</p>
          <h1 class="text-2xl font-black text-white mt-1">{{ event.title }}</h1>
          <span v-if="event.status === 'cancelled'" class="mt-2 inline-block rounded bg-rose-500/20 px-2 py-0.5 text-xs font-bold text-rose-300">Cancelled</span>
        </div>

        <div class="grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
          <div>📅 {{ event.starts_at }}<span v-if="event.ends_at"> to {{ event.ends_at }}</span></div>
          <div v-if="event.location">📍 {{ event.location }}</div>
          <div v-if="event.requires_payment && (Number(event.price) > 0 || tiers.length)">🎟️
            <template v-if="event.advertised && event.advertised.mode === 'all_in' && !tiers.length">
              {{ formatMoney(event.advertised.headline) }}<span v-if="event.advertised.has_saving" class="text-emerald-400"> (or {{ formatMoney(event.advertised.lowest) }} if you pay online)</span>
            </template>
            <template v-else-if="tiers.length">{{ tiers.map((t) => `${t.name} ${$cs}${t.price}`).join(' · ') }}</template>
            <template v-else>{{ $cs }}{{ event.price }}</template>
          </div>
          <div v-if="event.places_left !== null">👥 {{ event.places_left > 0 ? `${event.places_left} places left` : 'Fully booked' }}</div>
        </div>

        <p v-if="event.description" class="whitespace-pre-line text-sm text-slate-300">{{ event.description }}</p>

        <div v-if="hasMenu" class="space-y-3 border-t border-slate-800 pt-4">
          <h2 class="text-sm font-bold text-white">The meal</h2>
          <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="course in COURSES.filter((c) => (event.menu?.[c.key] ?? []).length)" :key="course.key">
              <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ course.label }}s</h3>
              <ul class="mt-1 space-y-1.5 text-sm">
                <li v-for="dish in event.menu[course.key]" :key="dish.id">
                  <span class="font-semibold text-white">{{ dish.name }}</span>
                  <span v-if="dishNote(dish)" class="block text-[11px] text-slate-400">{{ dishNote(dish) }}</span>
                  <span v-if="dish.description" class="block text-[11px] text-slate-500">{{ dish.description }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <p v-if="event.cancellation_policy" class="border-t border-slate-800 pt-4 text-xs text-slate-400">{{ event.cancellation_policy }}</p>
      </div>

      <!-- Booking -->
      <form v-if="bookingOpen" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 space-y-5" @submit.prevent="submit">
        <h2 class="text-lg font-bold text-white">Book your place</h2>
        <p v-if="full" class="rounded-lg bg-blue-500/10 p-3 text-xs text-blue-200">This event is full, but you can join the waiting list. We'll email you if a place opens up.</p>

        <div v-if="form.errors.capacity || form.errors.registration || form.errors.booking_closed || form.errors.attendees" class="rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs font-semibold text-rose-300" role="alert">
          {{ form.errors.capacity || form.errors.registration || form.errors.booking_closed || form.errors.attendees }}
        </div>

        <!-- Hidden from people; bots tend to fill it in -->
        <div class="absolute -left-[9999px]" aria-hidden="true">
          <label>Leave this empty <input v-model="form.website" type="text" tabindex="-1" autocomplete="off" /></label>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
          <div class="sm:col-span-1">
            <label :class="label">Your name</label>
            <input v-model="form.contact_name" type="text" required maxlength="150" :class="field" />
            <p v-if="form.errors.contact_name" class="mt-1 text-xs text-rose-400">{{ form.errors.contact_name }}</p>
          </div>
          <div>
            <label :class="label">Email</label>
            <input v-model="form.contact_email" type="email" required maxlength="255" :class="field" />
            <p v-if="form.errors.contact_email" class="mt-1 text-xs text-rose-400">{{ form.errors.contact_email }}</p>
          </div>
          <div>
            <label :class="label">Phone (optional)</label>
            <input v-model="form.contact_phone" type="tel" maxlength="50" :class="field" />
          </div>
        </div>

        <div v-for="(person, index) in form.attendees" :key="index" class="space-y-3 rounded-xl border border-slate-800 bg-slate-950/60 p-4">
          <div class="flex items-end gap-3">
            <div class="flex-1">
              <label :class="label">{{ index === 0 ? 'You (if different from above)' : `Guest ${index}` }}</label>
              <input v-model="person.name" type="text" maxlength="150" :placeholder="index === 0 ? form.contact_name : 'Guest\'s full name'" :class="field" />
              <p v-if="err(index, 'name')" class="mt-1 text-xs text-rose-400">{{ err(index, 'name') }}</p>
            </div>
            <button v-if="index > 0" type="button" class="pb-2 text-xs font-semibold text-rose-400" @click="removePerson(index)">Remove</button>
          </div>

          <div v-if="tiers.length > 1">
            <label :class="label">Ticket type</label>
            <select v-model="person.ticket_tier_id" :class="field">
              <option :value="null" disabled>Choose a ticket type</option>
              <option v-for="tier in tiers" :key="tier.id" :value="tier.id">{{ tier.name }} ({{ $cs }}{{ tier.price }})</option>
            </select>
            <p v-if="err(index, 'ticket_tier_id')" class="mt-1 text-xs text-rose-400">{{ err(index, 'ticket_tier_id') }}</p>
          </div>

          <template v-if="hasMenu">
            <label class="flex items-center gap-2 text-sm font-semibold text-white">
              <input type="checkbox" :checked="person.attending_dining" class="h-4 w-4 rounded" @change="setDining(person, $event.target.checked)" />
              {{ index === 0 ? 'I\'m having the meal' : 'Having the meal' }}
            </label>
            <div v-if="person.attending_dining" class="grid gap-3 sm:grid-cols-3">
              <div v-for="course in COURSES.filter((c) => (event.menu?.[c.key] ?? []).length)" :key="course.key">
                <label :class="label">{{ course.label }}</label>
                <select v-model="person[course.column]" :class="field">
                  <option :value="null" disabled>Choose</option>
                  <option v-for="dish in event.menu[course.key]" :key="dish.id" :value="dish.id">{{ dish.name }}</option>
                </select>
                <p v-if="err(index, course.column)" class="mt-1 text-xs text-rose-400">{{ err(index, course.column) }}</p>
              </div>
            </div>
          </template>

          <div>
            <label :class="label">Dietary requirements or allergies</label>
            <input v-model="person.dietary_requirements" type="text" maxlength="1000" :class="field" />
          </div>
        </div>

        <button v-if="event.max_guests > 0" type="button" :disabled="!canAddGuest" class="text-sm font-semibold text-blue-400 hover:underline disabled:opacity-50" @click="addGuest">
          + Add a guest <span class="font-normal text-slate-500">({{ form.attendees.length - 1 }} of {{ event.max_guests }})</span>
        </button>

        <div v-if="event.requires_payment" class="space-y-3">
          <div>
            <button v-if="!showPromo" type="button" class="text-sm font-semibold text-blue-400 hover:underline" @click="showPromo = true">Have a promo code?</button>
            <div v-else class="max-w-xs">
              <label :class="label">Promo code</label>
              <input v-model="form.promo_code" type="text" maxlength="40" :class="[field, 'font-mono uppercase']" />
              <p v-if="form.errors.promo_code" class="mt-1 text-xs text-rose-400">{{ form.errors.promo_code }}</p>
            </div>
          </div>
          <PaymentChoice v-model="form.payment_method" :quote="quote" :error="quoteError" tone="dark" />
          <p v-if="form.errors.payment_method" class="text-xs text-rose-400">{{ form.errors.payment_method }}</p>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white hover:bg-blue-500 disabled:opacity-60">
          {{ full ? 'Join the waiting list' : 'Book now' }}
        </button>
        <p class="text-[11px] text-slate-500">We'll email you a private link to view or cancel your booking. Your details are shared only with {{ club.name }}.</p>
      </form>

      <div v-else class="rounded-2xl border border-slate-800 bg-slate-900/70 p-6 text-sm text-slate-300 space-y-2">
        <template v-if="event.status === 'cancelled'">This event has been cancelled.</template>
        <template v-else-if="event.is_booking_closed">Booking for this event has closed.</template>
        <template v-else-if="canBookAsGuest && full">This event is fully booked.</template>
        <template v-else-if="isSignedIn">
          <p>Members can book from the members area.</p>
          <Link :href="route('members.events')" class="font-bold text-blue-400 hover:underline">Go to my events &rarr;</Link>
        </template>
        <template v-else>
          <p>Booking for this event is open to members of {{ club.name }}.</p>
          <Link href="/login" class="font-bold text-blue-400 hover:underline">Log in to book &rarr;</Link>
        </template>
      </div>
    </div>
  </div>
</template>
