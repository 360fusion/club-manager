<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import PaymentChoice from '@/Components/Events/PaymentChoice.vue';
import PaymentSummary from '@/Components/Events/PaymentSummary.vue';
import { useEventQuote } from '@/Composables/useEventQuote';

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
    { key: 'main', column: 'main_item_id', label: 'Main course' },
    { key: 'dessert', column: 'dessert_item_id', label: 'Dessert' },
];

const blankPerson = (isGuest = false) => ({
    name: isGuest ? '' : myName.value,
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

const fieldClass = 'w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500';
const labelClass = 'block text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1';
const generalError = computed(() => form.errors.capacity || form.errors.registration || form.errors.booking_closed || form.errors.attendees);
</script>

<template>
    <Modal :open="true" size="lg" :title="going ? 'Book your place' : 'Your reply'" :subtitle="event.title" @close="emit('close')">
        <form class="space-y-5" @submit.prevent="submit">
            <div v-if="generalError" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-semibold text-rose-700 dark:border-rose-800/60 dark:bg-rose-950/40 dark:text-rose-300" role="alert">{{ generalError }}</div>

            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Your reply">
                <label v-for="option in [['attending', 'I\'m going'], ['tentative', 'Maybe'], ['declined', 'Can\'t go']]" :key="option[0]"
                    :class="['flex cursor-pointer items-center justify-center rounded-xl border p-3 text-xs font-bold transition-all', form.attendance_status === option[0] ? 'border-blue-500 bg-blue-50 text-blue-800 dark:bg-blue-950/40 dark:text-blue-200' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-800/50 dark:text-slate-300']">
                    <input v-model="form.attendance_status" type="radio" :value="option[0]" class="sr-only" />
                    {{ option[1] }}
                </label>
            </div>

            <template v-if="going">
                <div v-if="event.places_left !== null" class="text-[11px] text-slate-500 dark:text-slate-400">
                    <template v-if="event.places_left > 0">{{ event.places_left }} {{ event.places_left === 1 ? 'place' : 'places' }} left.</template>
                    <template v-else>This event is full.</template>
                </div>
                <div v-if="willWait" class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-200">
                    There aren't enough places for everyone. You'll be added to the waiting list and moved up if places free up.
                </div>
                <div v-if="blocked" class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                    There aren't enough places for this many people. Try fewer guests.
                </div>
                <div v-if="wasWaitlisted" class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800 dark:border-blue-800/60 dark:bg-blue-950/40 dark:text-blue-200">You are on the waiting list for this event.</div>

                <div v-for="(person, index) in form.attendees" :key="index" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/40">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex-1">
                            <label :class="labelClass">{{ person.is_guest ? `Guest ${index}` : 'You' }}</label>
                            <input v-model="person.name" type="text" maxlength="150" :readonly="!person.is_guest" :placeholder="person.is_guest ? 'Guest\'s full name' : ''" :class="[fieldClass, !person.is_guest ? 'opacity-70' : '']" />
                            <p v-if="errorFor(index, 'name')" class="mt-1 text-[11px] font-semibold text-rose-600">{{ errorFor(index, 'name') }}</p>
                        </div>
                        <button v-if="person.is_guest" type="button" class="self-end pb-2 text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400" @click="removeGuest(index)">Remove</button>
                    </div>

                    <div v-if="tiersFor(person).length > 1">
                        <label :class="labelClass">Ticket type</label>
                        <select v-model="person.ticket_tier_id" :class="fieldClass">
                            <option :value="null" disabled>Choose a ticket type</option>
                            <option v-for="tier in tiersFor(person)" :key="tier.id" :value="tier.id">{{ tier.name }} ({{ $cs }}{{ tier.price }})</option>
                        </select>
                        <p v-if="errorFor(index, 'ticket_tier_id')" class="mt-1 text-[11px] font-semibold text-rose-600">{{ errorFor(index, 'ticket_tier_id') }}</p>
                    </div>

                    <template v-if="hasMenu">
                        <label class="flex items-center gap-2 text-xs font-bold text-slate-900 dark:text-white">
                            <input type="checkbox" :checked="person.attending_dining" class="h-4 w-4 rounded border-slate-300 text-blue-600 dark:border-slate-700" @change="setDining(person, $event.target.checked)" />
                            {{ person.is_guest ? 'Having the meal' : 'I\'m having the meal' }}
                            <span v-if="Number(event.dining_price) > 0" class="font-normal text-slate-500">({{ $cs }}{{ event.dining_price }})</span>
                        </label>

                        <div v-if="person.attending_dining" class="grid gap-3 sm:grid-cols-3">
                            <div v-for="course in COURSES.filter((c) => (event.menu?.[c.key] ?? []).length)" :key="course.key">
                                <label :class="labelClass">{{ course.label }}</label>
                                <select v-model="person[course.column]" :class="fieldClass">
                                    <option :value="null" disabled>Choose</option>
                                    <option v-for="dish in event.menu[course.key]" :key="dish.id" :value="dish.id">{{ dish.name }}</option>
                                </select>
                                <p v-if="person[course.column] && dishNote(event.menu[course.key].find((d) => d.id === person[course.column]) ?? {})" class="mt-1 text-[10px] text-slate-500 dark:text-slate-400">{{ dishNote(event.menu[course.key].find((d) => d.id === person[course.column])) }}</p>
                                <p v-if="errorFor(index, course.column)" class="mt-1 text-[11px] font-semibold text-rose-600">{{ errorFor(index, course.column) }}</p>
                            </div>
                        </div>
                    </template>

                    <div>
                        <label :class="labelClass">Dietary requirements or allergies</label>
                        <input v-model="person.dietary_requirements" type="text" maxlength="1000" placeholder="e.g. gluten free, nut allergy" :class="fieldClass" />
                    </div>
                </div>

                <PaymentSummary v-if="event.user_rsvp?.payment && Number(event.user_rsvp.payment.total) > 0" :payment="event.user_rsvp.payment" />

                <button v-if="event.max_guests > 0" type="button" :disabled="!canAddGuest" class="text-xs font-semibold text-blue-600 hover:underline disabled:cursor-not-allowed disabled:opacity-50 dark:text-blue-400" @click="addGuest">
                    + Add a guest <span class="font-normal text-slate-500">({{ guestCount }} of {{ event.max_guests }})</span>
                </button>

                <div v-if="event.requires_payment" class="space-y-3">
                    <div>
                        <button v-if="!showPromo" type="button" class="text-xs font-semibold text-blue-600 hover:underline dark:text-blue-400" @click="showPromo = true">Have a promo code?</button>
                        <div v-else class="max-w-xs">
                            <label :class="labelClass">Promo code</label>
                            <input v-model="form.promo_code" type="text" maxlength="40" :class="[fieldClass, 'font-mono uppercase']" />
                            <p v-if="form.errors.promo_code" class="mt-1 text-[11px] font-semibold text-rose-600">{{ form.errors.promo_code }}</p>
                        </div>
                    </div>

                    <PaymentChoice v-model="form.payment_method" :quote="quote" :error="quoteError" />
                    <p v-if="form.errors.payment_method" class="text-[11px] font-semibold text-rose-600">{{ form.errors.payment_method }}</p>
                </div>
            </template>

            <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                <button type="submit" :disabled="form.processing || blocked" class="flex-1 rounded-xl bg-blue-600 py-3 text-xs font-bold text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 disabled:opacity-50">
                    {{ !going ? 'Save reply' : (willWait ? 'Join the waiting list' : (event.user_rsvp ? 'Update booking' : 'Confirm booking')) }}
                </button>
                <button type="button" class="rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-xs font-bold text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-200" @click="emit('close')">Close</button>
            </div>
        </form>
    </Modal>
</template>
