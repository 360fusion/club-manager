<script setup>
import { computed, ref, useId, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import PaymentChoice from '@/Components/Events/PaymentChoice.vue';
import PaymentSummary from '@/Components/Events/PaymentSummary.vue';
import { useEventQuote } from '@/Composables/useEventQuote';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    // An event payload from EventPayload::forMember, with the member's own booking in user_rsvp.
    event: { type: Object, required: true },
    clubSlug: { type: String, required: true },
});

const emit = defineEmits(['close']);

const page = usePage();
const myName = computed(() => page.props.auth?.user?.name ?? '');

const COURSES = [
    { key: 'starter', column: 'starter_item_id', label: 'Starter' },
    { key: 'main', column: 'main_item_id', label: 'Main' },
    { key: 'dessert', column: 'dessert_item_id', label: 'Dessert' },
];

const blankPerson = (isGuest = false) => ({
    name: isGuest ? '' : myName.value,
    email: '',
    is_guest: isGuest,
    ticket_tier_id: null,
    attending_dining: false,
    starter_item_id: null,
    main_item_id: null,
    dessert_item_id: null,
    dietary_requirements: '',
});

const saved = props.event.user_rsvp?.attendees ?? [];

const form = useForm({
    payment_method: props.event.user_rsvp?.payment?.selected_option_id ?? null,
    promo_code: props.event.user_rsvp?.payment?.promo_code ?? '',
    attendance_status: props.event.user_rsvp?.attendance_status && props.event.user_rsvp.attendance_status !== 'cancelled' && props.event.user_rsvp.attendance_status !== 'waitlisted'
        ? props.event.user_rsvp.attendance_status
        : 'attending',
    attendees: saved.length
        ? saved.map((a) => ({
            name: a.name,
            email: a.email ?? '',
            is_guest: a.is_guest,
            ticket_tier_id: a.ticket_tier_id,
            attending_dining: a.attending_dining,
            starter_item_id: a.starter_item_id,
            main_item_id: a.main_item_id,
            dessert_item_id: a.dessert_item_id,
            dietary_requirements: a.dietary_requirements ?? '',
        }))
        : [blankPerson()],
});

const going = computed(() => form.attendance_status !== 'declined');
const guestCount = computed(() => form.attendees.filter((a) => a.is_guest).length);
const canAddGuest = computed(() => guestCount.value < (props.event.max_guests ?? 0));
const hasMenu = computed(() => props.event.has_dining && COURSES.some((c) => (props.event.menu?.[c.key] ?? []).length > 0));

// Tickets a person may pick: members see "everyone" and "member" types, guests see "everyone" and "guest".
const tiersFor = (person) => (props.event.ticket_tiers ?? []).filter((tier) => ['all', person.is_guest ? 'guest' : 'member'].includes(tier.audience));

const errorFor = (index, field) => form.errors[`attendees.${index}.${field}`];

const wasWaitlisted = computed(() => props.event.user_rsvp?.attendance_status === 'waitlisted');

// Places the booking needs, and whether that leaves anyone waiting.
const peopleGoing = computed(() => form.attendees.length);
const overCapacity = computed(() => going.value && props.event.places_left !== null && peopleGoing.value > props.event.places_left + (wasHolding.value ? saved.length : 0));
const wasHolding = computed(() => ['attending', 'tentative'].includes(props.event.user_rsvp?.attendance_status));
const willWait = computed(() => overCapacity.value && props.event.waitlist_enabled);
const blocked = computed(() => overCapacity.value && !props.event.waitlist_enabled);

// Live price from the server, so what is shown is what is charged.
const showPromo = ref(!!form.promo_code);
const { quote, error: quoteError, refresh } = useEventQuote(
    route('member.events.quote', { slug: props.clubSlug, id: props.event.id }),
    () => ({
        promo_code: form.promo_code || null,
        attendees: form.attendees.map((a) => ({ is_guest: a.is_guest, ticket_tier_id: a.ticket_tier_id, attending_dining: a.attending_dining })),
    }),
);

watch(() => [form.attendees, form.promo_code], () => { if (going.value && props.event.requires_payment) refresh(); }, { deep: true, immediate: true });

// Choose the first option automatically, and re-choose if the one picked is no longer offered.
watch(quote, (value) => {
    const options = value?.options ?? [];

    if (options.length && !options.some((o) => o.id === form.payment_method)) {
        form.payment_method = options[0].id;
    }
});

const paying = ref(false);
const payNow = (option = null) => {
    paying.value = true;
    router.post(route('member.events.pay', { slug: props.clubSlug, id: props.event.id }), { option }, { onFinish: () => { paying.value = false; } });
};

const saidPaid = () => router.post(route('member.events.said_paid', { slug: props.clubSlug, id: props.event.id }), {}, { preserveScroll: true });

const addGuest = () => {
    if (canAddGuest.value) {
        form.attendees.push(blankPerson(true));
    }
};

const removeGuest = (index) => {
    form.attendees.splice(index, 1);
};

const setDining = (person, value) => {
    person.attending_dining = value;

    if (!value) {
        person.starter_item_id = person.main_item_id = person.dessert_item_id = null;
    }
};

const dishNote = (dish) => [dish.is_vegan ? 'Vegan' : (dish.is_vegetarian ? 'Vegetarian' : null), dish.is_gf ? 'Gluten free' : null, dish.allergens ? `Contains ${dish.allergens}` : null].filter(Boolean).join(' · ');

const submit = () => {
    form.post(route('member.rsvp', { slug: props.clubSlug, id: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
};

// The price of each person and the total, as the server worked them out for the chosen way of paying.
const breakdown = computed(() => (quote.value?.charged ? (quote.value.options?.find((o) => o.id === form.payment_method)?.quote ?? quote.value.quote ?? null) : null));
const personPrice = (index) => Number(breakdown.value?.people?.[index]?.price ?? 0);
const total = computed(() => (going.value && breakdown.value ? breakdown.value.total : null));
const who = computed(() => (guestCount.value === 0 ? 'Just you' : `You and ${guestCount.value} ${guestCount.value === 1 ? 'guest' : 'guests'}`));

const personHint = (person, index) => {
    const tier = tiersFor(person).length > 1 ? tiersFor(person).find((t) => t.id === person.ticket_tier_id)?.name : null;

    return [tier ?? (person.is_guest ? 'Your guest' : 'Member'), personPrice(index) > 0 ? formatMoney(personPrice(index)) : null].filter(Boolean).join(' · ');
};

const initials = (person) => {
    const words = (person.is_guest ? person.name : myName.value).trim().split(/\s+/).filter(Boolean);

    return person.is_guest && !words.length ? 'G' : words.slice(0, 1).concat(words.length > 1 ? words.slice(-1) : []).map((w) => w[0].toUpperCase()).join('');
};

const titleId = useId();

const fieldClass = 'min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 sm:min-h-10 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const cardClass = 'space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900';
const labelClass = 'mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300';
const generalError = computed(() => form.errors.capacity || form.errors.registration || form.errors.booking_closed || form.errors.attendees);
</script>

<template>
    <!-- A full-screen sheet on phones, a dialog from sm up. Escape and a click on the backdrop close it. -->
    <div class="fixed inset-0 z-50 flex bg-slate-900/60 backdrop-blur-sm sm:items-center sm:justify-center sm:p-4" @mousedown.self="emit('close')">
        <div
            v-focus-trap="() => emit('close')"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            class="flex h-full w-full flex-col overflow-hidden bg-slate-100 outline-none sm:h-auto sm:max-h-[calc(100dvh-2rem)] sm:max-w-2xl sm:rounded-2xl sm:border sm:border-slate-200 sm:shadow-2xl dark:bg-slate-950 sm:dark:border-slate-800"
        >
            <header class="flex items-center gap-1 bg-slate-800 px-3 pt-[max(0.625rem,env(safe-area-inset-top))] pb-2.5 text-white sm:border-b sm:border-slate-200 sm:bg-white sm:px-5 sm:py-3 sm:text-slate-900 sm:dark:border-slate-800 sm:dark:bg-slate-900 sm:dark:text-white">
                <button type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl hover:bg-white/10 sm:order-last sm:text-slate-500 sm:hover:bg-slate-100 sm:dark:text-slate-400 sm:dark:hover:bg-slate-800" aria-label="Close" @click="emit('close')">
                    <svg class="h-6 w-6 sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <svg class="hidden h-5 w-5 sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <h2 :id="titleId" class="truncate text-[17px] leading-snug font-semibold sm:text-base">{{ going ? 'Book your place' : 'Your reply' }}</h2>
                    <p class="truncate text-xs text-slate-300 sm:text-slate-500 sm:dark:text-slate-400">{{ event.title }} · {{ event.starts_at }}</p>
                </div>
                <span class="w-11 shrink-0 sm:hidden" aria-hidden="true"></span>
            </header>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                    <Alert v-if="generalError" variant="danger" role="alert">{{ generalError }}</Alert>

                    <div class="grid grid-cols-3 gap-1 rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900" role="radiogroup" aria-label="Your reply">
                        <label v-for="option in [['attending', 'I\'m going'], ['tentative', 'Maybe'], ['declined', 'Can\'t go']]" :key="option[0]"
                            :class="['flex min-h-11 cursor-pointer items-center justify-center rounded-xl text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-blue-500 sm:min-h-9', form.attendance_status === option[0] ? 'bg-blue-600 font-semibold text-white' : 'font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white']">
                            <input v-model="form.attendance_status" type="radio" :value="option[0]" class="sr-only" />
                            {{ option[1] }}
                        </label>
                    </div>

                    <PaymentSummary v-if="event.user_rsvp?.payment && Number(event.user_rsvp.payment.total) > 0" :payment="event.user_rsvp.payment" :paying="paying" @pay="payNow" @said-paid="saidPaid" />

                    <template v-if="going">
                        <p v-if="event.places_left !== null" class="px-1 text-xs text-slate-500 dark:text-slate-400">
                            <template v-if="event.places_left > 0">{{ event.places_left }} {{ event.places_left === 1 ? 'place' : 'places' }} left.</template>
                            <template v-else>This event is full.</template>
                        </p>
                        <Alert v-if="willWait" variant="info">There aren't enough places for everyone. You'll be added to the waiting list and moved up if places free up.</Alert>
                        <Alert v-if="blocked" variant="warning">There aren't enough places for this many people. Try fewer guests.</Alert>
                        <Alert v-if="wasWaitlisted" variant="info">You are on the waiting list for this event.</Alert>

                        <section v-for="(person, index) in form.attendees" :key="index" :class="cardClass" :aria-label="person.is_guest ? `Guest ${index}` : 'You'">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-blue-200 bg-blue-50 text-xs font-semibold text-blue-700 dark:border-blue-800/60 dark:bg-blue-950/60 dark:text-blue-300" aria-hidden="true">{{ initials(person) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-slate-900 dark:text-white">{{ person.is_guest ? (person.name || `Guest ${index}`) : 'You' }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ personHint(person, index) }}</p>
                                </div>
                                <button v-if="person.is_guest" type="button" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white" :aria-label="`Remove ${person.name || 'this guest'}`" @click="removeGuest(index)">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                </button>
                            </div>

                            <div v-if="person.is_guest" class="grid gap-3 sm:grid-cols-2">
                                <label class="block">
                                    <span :class="labelClass">Guest's full name</span>
                                    <input v-model="person.name" type="text" maxlength="150" autocomplete="off" :class="fieldClass" />
                                    <p v-if="errorFor(index, 'name')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errorFor(index, 'name') }}</p>
                                </label>
                                <label class="block">
                                    <span :class="labelClass">Guest's email (optional)</span>
                                    <input v-model="person.email" type="email" maxlength="255" placeholder="For a confirmation of their place" :class="fieldClass" />
                                    <p v-if="errorFor(index, 'email')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errorFor(index, 'email') }}</p>
                                </label>
                            </div>

                            <label v-if="tiersFor(person).length > 1" class="block">
                                <span :class="labelClass">Ticket type</span>
                                <select v-model="person.ticket_tier_id" :class="fieldClass">
                                    <option :value="null" disabled>Choose a ticket type</option>
                                    <option v-for="tier in tiersFor(person)" :key="tier.id" :value="tier.id">{{ tier.name }} ({{ $money(tier.price) }})</option>
                                </select>
                                <p v-if="errorFor(index, 'ticket_tier_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errorFor(index, 'ticket_tier_id') }}</p>
                            </label>

                            <template v-if="event.has_dining">
                                <label class="flex min-h-11 items-center gap-2 text-sm font-medium text-slate-900 dark:text-white">
                                    <input type="checkbox" :checked="person.attending_dining" class="h-5 w-5 rounded accent-blue-600" @change="setDining(person, $event.target.checked)" />
                                    {{ person.is_guest ? 'Dining' : 'I\'m dining' }}
                                    <span v-if="Number(event.dining_price) > 0" class="font-normal text-slate-500 dark:text-slate-400">({{ $money(event.dining_price) }})</span>
                                </label>

                                <div v-if="person.attending_dining && hasMenu" class="grid grid-cols-3 gap-2">
                                    <label v-for="course in COURSES.filter((c) => (event.menu?.[c.key] ?? []).length)" :key="course.key" class="block min-w-0">
                                        <span :class="labelClass">{{ course.label }}</span>
                                        <select v-model="person[course.column]" :class="[fieldClass, 'px-2']">
                                            <option :value="null" disabled>Choose</option>
                                            <option v-for="dish in event.menu[course.key]" :key="dish.id" :value="dish.id">{{ dish.name }}</option>
                                        </select>
                                        <p v-if="person[course.column] && dishNote(event.menu[course.key].find((d) => d.id === person[course.column]) ?? {})" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ dishNote(event.menu[course.key].find((d) => d.id === person[course.column])) }}</p>
                                        <p v-if="errorFor(index, course.column)" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ errorFor(index, course.column) }}</p>
                                    </label>
                                </div>
                            </template>

                            <label class="block">
                                <span :class="labelClass">Dietary requirements or allergies</span>
                                <input v-model="person.dietary_requirements" type="text" maxlength="1000" placeholder="Gluten free, nut allergy" :class="fieldClass" />
                            </label>
                        </section>

                        <button v-if="event.max_guests > 0" type="button" :disabled="!canAddGuest" class="flex min-h-11 items-center gap-1.5 px-1 text-sm font-semibold text-blue-600 hover:text-blue-500 disabled:cursor-not-allowed disabled:text-slate-400 dark:text-blue-400 dark:disabled:text-slate-500" @click="addGuest">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                            {{ guestCount ? 'Add another guest' : 'Add a guest' }} (up to {{ event.max_guests }})
                        </button>

                        <section v-if="event.requires_payment" :class="cardClass" aria-label="Paying">
                            <PaymentChoice v-model="form.payment_method" :quote="quote" :error="quoteError" />
                            <p v-if="form.errors.payment_method" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.payment_method }}</p>

                            <Button v-if="!showPromo" variant="ghost" size="sm" @click="showPromo = true">Have a promo code?</Button>
                            <label v-else class="block max-w-xs">
                                <span :class="labelClass">Promo code</span>
                                <input v-model="form.promo_code" type="text" maxlength="40" :class="[fieldClass, 'font-mono uppercase']" />
                                <p v-if="form.errors.promo_code" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.promo_code }}</p>
                            </label>
                        </section>
                    </template>
                </div>

                <div class="space-y-2.5 border-t border-slate-200 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5 dark:border-slate-800 dark:bg-slate-900">
                    <div v-if="total !== null" class="flex items-baseline justify-between tabular-nums">
                        <span class="text-[13px] text-slate-500 dark:text-slate-400">{{ who }}</span>
                        <span class="font-display text-[22px] font-semibold text-slate-900 dark:text-white">{{ $money(total) }}</span>
                    </div>
                    <div class="flex gap-3">
                        <Button type="submit" size="lg" class="flex-1" :disabled="blocked" :loading="form.processing">
                            {{ !going ? 'Save reply' : (willWait ? 'Join the waiting list' : (event.user_rsvp ? 'Update booking' : 'Confirm booking')) }}
                        </Button>
                        <div class="hidden sm:block"><Button variant="secondary" size="lg" @click="emit('close')">Close</Button></div>
                    </div>
                    <p v-if="going && event.booking_cutoff_at && !event.is_booking_closed" class="text-center text-xs text-slate-500 dark:text-slate-400">You can change your booking until {{ event.booking_cutoff_at }}.</p>
                </div>
            </form>
        </div>
    </div>
</template>
