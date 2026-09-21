<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import { useEventQuote } from '@/Composables/useEventQuote';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    open: { type: Boolean, default: false },
    // The event as sent to the registrations page: tiers, menu (by course), payment_options, capacity and so on.
    event: { type: Object, required: true },
    clubSlug: { type: String, required: true },
    canMarkPaid: { type: Boolean, default: false },
    // The booking being changed, or null to add a new one.
    registrationId: { type: Number, default: null },
});

const emit = defineEmits(['close', 'edit']);

const COURSES = [
    { key: 'starter', column: 'starter_item_id', label: 'Starter' },
    { key: 'main', column: 'main_item_id', label: 'Main course' },
    { key: 'dessert', column: 'dessert_item_id', label: 'Dessert' },
];

const field = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white';
const label = 'mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const errorText = 'mt-1 text-[11px] font-semibold text-rose-600';

const blankPerson = (isGuest = false) => ({
    name: '',
    email: '',
    organisation: '',
    is_guest: isGuest,
    ticket_tier_id: null,
    attending_dining: false,
    starter_item_id: null,
    main_item_id: null,
    dessert_item_id: null,
    dietary_requirements: '',
});

const today = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    member_id: null,
    contact_email: '',
    contact_phone: '',
    internal_note: '',
    status: 'attending',
    over_capacity: false,
    payment_method: null,
    promo_code: '',
    send_confirmation: true,
    mark_paid: { enabled: false, amount: '', received_on: today(), comment: '' },
    attendees: [blankPerson()],
});

const editing = computed(() => props.registrationId !== null);
const loading = ref(false);
const loadError = ref('');
const booking = ref(null); // the booking being edited, as sent by the server

// ---- who is booking -------------------------------------------------------------------------------------------
const who = ref('member'); // 'member' or 'visitor'
const member = ref(null);
const search = ref('');
const results = ref([]);
const searching = ref(false);
let searchTimer = null;
let searchRun = 0;

watch(search, (term) => {
    clearTimeout(searchTimer);

    if (term.trim().length < 2) {
        results.value = [];
        searching.value = false;
        return;
    }

    searching.value = true;
    searchTimer = setTimeout(async () => {
        const run = ++searchRun;
        try {
            const response = await fetch(route('admin.events.member_search', { clubSlug: props.clubSlug, id: props.event.id, q: term.trim() }), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (run === searchRun) results.value = data.members ?? [];
        } catch {
            if (run === searchRun) results.value = [];
        } finally {
            if (run === searchRun) searching.value = false;
        }
    }, 250);
});

const pickMember = (found) => {
    if (found.registration_id) return;
    member.value = found;
    form.member_id = found.id;
    form.contact_email = found.email ?? '';
    form.contact_phone = found.phone ?? '';
    Object.assign(form.attendees[0], { name: found.name, organisation: found.lodge ?? '', dietary_requirements: found.dietary_notes ?? '' });
    search.value = '';
    results.value = [];
};

const clearMember = () => {
    member.value = null;
    form.member_id = null;
    Object.assign(form.attendees[0], { name: '', organisation: '', dietary_requirements: '' });
    form.contact_email = '';
    form.contact_phone = '';
};

watch(who, (value) => {
    if (value === 'visitor' && member.value) clearMember();
});

// ---- people ---------------------------------------------------------------------------------------------------
const hasMenu = computed(() => props.event.has_dining && COURSES.some((c) => props.event.menu?.[c.key]?.length));
const isMember = computed(() => form.member_id !== null || (editing.value && booking.value?.member));

// Ticket types a person may be given, matching what the server allows.
const tiersFor = (person) => {
    const audiences = person.is_guest ? ['all', 'guest', 'public'] : (isMember.value ? ['all', 'member'] : ['all', 'public', 'guest']);
    return (props.event.tiers ?? []).filter((tier) => audiences.includes(tier.audience));
};

const setDining = (person, on) => {
    person.attending_dining = on;
    if (!on) Object.assign(person, { starter_item_id: null, main_item_id: null, dessert_item_id: null });
};

const addGuest = () => form.attendees.push(blankPerson(true));
const removeGuest = (index) => form.attendees.splice(index, 1);
const errorFor = (index, column) => form.errors[`attendees.${index}.${column}`];

// ---- price ----------------------------------------------------------------------------------------------------
const quoteUrl = computed(() => route('admin.events.quote', { clubSlug: props.clubSlug, id: props.event.id }));
const { quote, error: quoteError, refresh: refreshQuote } = useEventQuote(quoteUrl.value, () => ({
    promo_code: form.promo_code || null,
    attendees: form.attendees.map((person, index) => ({ is_guest: person.is_guest || index > 0, ticket_tier_id: person.ticket_tier_id, attending_dining: person.attending_dining })),
}));

const allowedIds = computed(() => (props.event.payment_options ?? []).map((option) => option.id));
const optionQuotes = computed(() => (quote.value?.options ?? []).filter((option) => allowedIds.value.includes(option.id)));
const chosenQuote = computed(() => optionQuotes.value.find((option) => option.id === form.payment_method)?.quote ?? quote.value?.quote ?? optionQuotes.value[0]?.quote ?? null);
const charged = computed(() => !!quote.value?.charged);

// ---- opening and closing --------------------------------------------------------------------------------------
const reset = () => {
    form.reset();
    form.clearErrors();
    form.attendees = [blankPerson()];
    form.mark_paid = { enabled: false, amount: '', received_on: today(), comment: '' };
    who.value = 'member';
    member.value = null;
    search.value = '';
    results.value = [];
    booking.value = null;
    loadError.value = '';
};

watch(() => [props.open, props.registrationId], async ([open]) => {
    if (!open) return;

    reset();

    if (props.registrationId !== null) {
        loading.value = true;
        try {
            const response = await fetch(route('admin.events.registrations.show', { clubSlug: props.clubSlug, id: props.event.id, registrationId: props.registrationId }), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = (await response.json()).booking;
            booking.value = data;
            who.value = data.member ? 'member' : 'visitor';
            form.member_id = data.member?.id ?? null;
            form.contact_email = data.contact_email ?? '';
            form.contact_phone = data.contact_phone ?? '';
            form.internal_note = data.internal_note ?? '';
            form.status = data.status === 'waitlisted' ? 'waitlisted' : 'attending';
            form.payment_method = data.payment_method;
            form.promo_code = data.promo_code ?? '';
            form.send_confirmation = false;
            form.attendees = data.attendees.map((a) => ({ ...blankPerson(a.is_guest), ...a }));
        } catch {
            loadError.value = 'Could not load this booking.';
        } finally {
            loading.value = false;
        }
    } else if (props.event.payment_options?.length === 1) {
        form.payment_method = props.event.payment_options[0].id;
    }

    refreshQuote();
});

watch(() => [form.attendees, form.promo_code], refreshQuote, { deep: true });

// ---- rules of the form ----------------------------------------------------------------------------------------
const placesAfter = computed(() => (props.event.places_taken ?? 0) + form.attendees.length - (editing.value && booking.value && ['attending', 'tentative'].includes(booking.value.status) ? booking.value.attendees.length : 0));
const wouldBeFull = computed(() => props.event.capacity !== null && props.event.capacity !== undefined && placesAfter.value > props.event.capacity && form.status === 'attending');
const showOverride = computed(() => wouldBeFull.value || !!form.errors.capacity);
const canRecordPayment = computed(() => props.canMarkPaid && form.status === 'attending' && charged.value && !(editing.value && booking.value?.locked));
const hasEmail = computed(() => !!form.contact_email);
const paidAlready = computed(() => editing.value && Number(booking.value?.payment?.amount_paid) > 0);

const topErrors = computed(() => ['registration', 'capacity', 'booking_closed', 'member_id', 'mark_paid', 'payment_method'].map((key) => form.errors[key]).filter(Boolean));

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => emit('close') };
    form.transform((data) => ({
        ...data,
        mark_paid: data.mark_paid.enabled ? data.mark_paid : null,
        send_confirmation: data.send_confirmation && !!data.contact_email,
        member_id: editing.value ? undefined : data.member_id,
    }));

    if (editing.value) {
        form.put(route('admin.events.registrations.update', { clubSlug: props.clubSlug, id: props.event.id, registrationId: props.registrationId }), options);
    } else {
        form.post(route('admin.events.registrations.store', { clubSlug: props.clubSlug, id: props.event.id }), options);
    }
};

const canSubmit = computed(() => !form.processing && !loading.value && !(editing.value && booking.value?.locked) && form.attendees[0].name.trim() !== '' && (who.value === 'visitor' || !!form.member_id || editing.value));
</script>

<template>
    <Modal :open="open" size="xl" :title="editing ? `Edit booking${booking ? ' for ' + booking.attendees[0]?.name : ''}` : 'Add a booking'" :subtitle="event.title" @close="emit('close')">
        <p v-if="loading" class="py-8 text-center text-xs text-slate-500">Loading booking...</p>
        <p v-else-if="loadError" class="rounded-lg bg-rose-500/10 p-3 text-xs font-semibold text-rose-600" role="alert">{{ loadError }}</p>

        <form v-else class="space-y-6 text-xs" @submit.prevent="submit">
            <div v-if="topErrors.length" class="space-y-1 rounded-lg bg-rose-500/10 p-3 font-semibold text-rose-600" role="alert">
                <p v-for="message in topErrors" :key="message">{{ message }}</p>
            </div>
            <p v-if="editing && booking?.locked" class="rounded-lg bg-amber-500/10 p-3 font-semibold text-amber-700 dark:text-amber-300">Someone on this booking has been checked in, so it can no longer be changed.</p>

            <!-- 1. Who is booking -->
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">1. Who is booking?</h3>

                <div v-if="!editing" class="inline-flex rounded-lg border border-slate-300 p-0.5 dark:border-slate-700" role="tablist">
                    <button type="button" role="tab" :aria-selected="who === 'member'" :class="['rounded-md px-3 py-1.5 font-bold', who === 'member' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300']" @click="who = 'member'">A member</button>
                    <button type="button" role="tab" :aria-selected="who === 'visitor'" :class="['rounded-md px-3 py-1.5 font-bold', who === 'visitor' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300']" @click="who = 'visitor'">Visitor or non-member</button>
                </div>

                <template v-if="who === 'member'">
                    <div v-if="member || (editing && booking?.member)" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-800/60 dark:bg-blue-950/30">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">{{ (member ?? booking.member).name }}</div>
                            <div class="text-slate-500 dark:text-slate-400">
                                <span v-if="(member ?? booking.member).rank">{{ (member ?? booking.member).rank }} · </span>
                                <span v-if="(member ?? booking.member).lodge">{{ (member ?? booking.member).lodge }} · </span>
                                <span v-if="(member ?? booking.member).member_number">Member #{{ (member ?? booking.member).member_number }}</span>
                            </div>
                        </div>
                        <button v-if="!editing" type="button" class="font-bold text-blue-600 hover:underline dark:text-blue-400" @click="clearMember">Change</button>
                    </div>

                    <div v-else class="relative">
                        <label :class="label" for="member-search">Search members by name, email or member number</label>
                        <input id="member-search" v-model="search" type="search" autocomplete="off" maxlength="100" placeholder="Start typing a name..." :class="field" />
                        <p v-if="form.errors.member_id" :class="errorText">{{ form.errors.member_id }}</p>
                        <ul v-if="search.trim().length >= 2" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900" role="listbox">
                            <li v-if="searching" class="px-3 py-2 text-slate-500">Searching...</li>
                            <li v-else-if="!results.length" class="px-3 py-2 text-slate-500">No members found. Try "A visitor or non-member" instead.</li>
                            <li v-for="found in results" :key="found.id" role="option">
                                <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800" @click="pickMember(found)">
                                    <span>
                                        <span class="font-bold text-slate-900 dark:text-white">{{ found.name }}</span>
                                        <span class="block text-slate-500 dark:text-slate-400"><template v-if="found.rank">{{ found.rank }} · </template><template v-if="found.lodge">{{ found.lodge }} · </template>{{ found.email }}</span>
                                    </span>
                                    <span v-if="found.registration_id" class="shrink-0 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">
                                        Already booked
                                        <span class="ml-1 cursor-pointer underline" role="link" tabindex="0" @click.stop="emit('edit', found.registration_id)" @keydown.enter.stop="emit('edit', found.registration_id)">Edit</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                    </div>
                </template>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-if="who === 'visitor' || editing"><label :class="label">Name</label><input v-model="form.attendees[0].name" type="text" maxlength="150" :readonly="isMember" :class="field" /><p v-if="errorFor(0, 'name')" :class="errorText">{{ errorFor(0, 'name') }}</p></div>
                    <div><label :class="label">Lodge / organisation</label><input v-model="form.attendees[0].organisation" type="text" maxlength="150" placeholder="e.g. Fraternity Lodge No 1418" :class="field" /></div>
                    <div><label :class="label">Email</label><input v-model="form.contact_email" type="email" maxlength="255" placeholder="For their confirmation" :class="field" /><p v-if="form.errors.contact_email" :class="errorText">{{ form.errors.contact_email }}</p></div>
                    <div><label :class="label">Phone</label><input v-model="form.contact_phone" type="text" maxlength="50" :class="field" /></div>
                </div>
            </section>

            <!-- 2. People -->
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">2. Who is coming, and what are they having?</h3>

                <div v-for="(person, index) in form.attendees" :key="index" class="space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold text-slate-900 dark:text-white">{{ index === 0 ? (form.attendees[0].name || 'The booker') : `Guest ${index}` }}</span>
                        <button v-if="index > 0" type="button" class="font-bold text-rose-600 hover:underline" @click="removeGuest(index)">Remove</button>
                    </div>

                    <div v-if="index > 0" class="grid gap-3 sm:grid-cols-3">
                        <div><label :class="label">Guest's name</label><input v-model="person.name" type="text" maxlength="150" :class="field" /><p v-if="errorFor(index, 'name')" :class="errorText">{{ errorFor(index, 'name') }}</p></div>
                        <div><label :class="label">Lodge / organisation</label><input v-model="person.organisation" type="text" maxlength="150" :class="field" /></div>
                        <div><label :class="label">Guest's email (optional)</label><input v-model="person.email" type="email" maxlength="255" :class="field" /><p v-if="errorFor(index, 'email')" :class="errorText">{{ errorFor(index, 'email') }}</p></div>
                    </div>

                    <div v-if="tiersFor(person).length > 1">
                        <label :class="label">Ticket type</label>
                        <select v-model="person.ticket_tier_id" :class="field">
                            <option :value="null" disabled>Choose a ticket type</option>
                            <option v-for="tier in tiersFor(person)" :key="tier.id" :value="tier.id">{{ tier.name }} ({{ $cs }}{{ tier.price }})</option>
                        </select>
                        <p v-if="errorFor(index, 'ticket_tier_id')" :class="errorText">{{ errorFor(index, 'ticket_tier_id') }}</p>
                    </div>

                    <template v-if="hasMenu">
                        <label class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
                            <input type="checkbox" :checked="person.attending_dining" class="h-4 w-4 rounded" @change="setDining(person, $event.target.checked)" />
                            Having dinner
                            <span v-if="Number(event.dining_price) > 0" class="font-normal text-slate-500">({{ $cs }}{{ event.dining_price }})</span>
                        </label>
                        <div v-if="person.attending_dining" class="grid gap-3 sm:grid-cols-3">
                            <div v-for="course in COURSES.filter((c) => event.menu?.[c.key]?.length)" :key="course.key">
                                <label :class="label">{{ course.label }}</label>
                                <select v-model="person[course.column]" :class="field">
                                    <option :value="null">Choose...</option>
                                    <option v-for="item in event.menu[course.key]" :key="item.id" :value="item.id">{{ item.name }}</option>
                                </select>
                                <p v-if="errorFor(index, course.column)" :class="errorText">{{ errorFor(index, course.column) }}</p>
                            </div>
                        </div>
                    </template>

                    <div><label :class="label">Dietary needs</label><input v-model="person.dietary_requirements" type="text" maxlength="1000" placeholder="Allergies, vegetarian..." :class="field" /></div>
                </div>

                <button type="button" class="rounded-lg border border-dashed border-slate-300 px-3 py-2 font-bold text-blue-600 hover:bg-slate-50 dark:border-slate-700 dark:text-blue-400 dark:hover:bg-slate-800" @click="addGuest">+ Add another person</button>
            </section>

            <!-- 3. Status and payment -->
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">3. Place and payment</h3>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex rounded-lg border border-slate-300 p-0.5 dark:border-slate-700">
                        <button type="button" :class="['rounded-md px-3 py-1.5 font-bold', form.status === 'attending' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300']" @click="form.status = 'attending'">Attending</button>
                        <button type="button" :class="['rounded-md px-3 py-1.5 font-bold', form.status === 'waitlisted' ? 'bg-blue-600 text-white' : 'text-slate-600 dark:text-slate-300']" @click="form.status = 'waitlisted'">Waiting list</button>
                    </div>
                    <span v-if="event.capacity" class="text-slate-500 dark:text-slate-400">{{ event.places_taken }} of {{ event.capacity }} places taken</span>
                </div>
                <label v-if="showOverride" class="flex items-start gap-2 rounded-lg bg-amber-500/10 p-3 font-semibold text-amber-800 dark:text-amber-200">
                    <input v-model="form.over_capacity" type="checkbox" class="mt-0.5 rounded" />
                    <span>The event is full. Add them anyway (over capacity)? Otherwise they go on the waiting list, or the booking is refused if there isn't one.</span>
                </label>

                <template v-if="charged">
                    <div v-if="event.payment_options?.length" class="space-y-2" role="radiogroup" aria-label="How will they pay?">
                        <label :class="label">How will they pay?</label>
                        <label v-for="option in event.payment_options" :key="option.id" :class="['flex cursor-pointer items-center justify-between gap-2 rounded-xl border p-3', form.payment_method === option.id ? 'border-blue-500 ring-1 ring-blue-500/40' : 'border-slate-200 dark:border-slate-800']">
                            <span class="flex items-center gap-2"><input v-model="form.payment_method" type="radio" :value="option.id" name="organiser-payment-option" /><span class="font-bold text-slate-900 dark:text-white">{{ option.label }}</span></span>
                            <span class="font-mono">{{ formatMoney(optionQuotes.find((o) => o.id === option.id)?.total ?? 0) }}</span>
                        </label>
                        <p class="text-slate-500 dark:text-slate-400">Online payment is left to the person paying: choose pay later and they can pay online from their booking link.</p>
                    </div>

                    <div v-if="chosenQuote" class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                        <div class="flex justify-between"><span class="text-slate-500">Tickets{{ chosenQuote.people.some((p) => p.dining > 0) ? ' and dinner' : '' }}</span><span class="font-mono">{{ formatMoney(chosenQuote.subtotal) }}</span></div>
                        <div v-if="chosenQuote.promo_discount > 0" class="flex justify-between"><span class="text-slate-500">Promo {{ chosenQuote.promo_code }}</span><span class="font-mono text-emerald-600">-{{ formatMoney(chosenQuote.promo_discount) }}</span></div>
                        <div v-if="chosenQuote.method_adjustment !== 0" class="flex justify-between"><span class="text-slate-500">{{ chosenQuote.method_adjustment < 0 ? 'Discount' : 'Fee' }} for this way of paying</span><span class="font-mono">{{ chosenQuote.method_adjustment < 0 ? '-' : '+' }}{{ formatMoney(Math.abs(chosenQuote.method_adjustment)) }}</span></div>
                        <div v-if="chosenQuote.booking_fee > 0" class="flex justify-between"><span class="text-slate-500">{{ chosenQuote.booking_fee_label }}</span><span class="font-mono">{{ formatMoney(chosenQuote.booking_fee) }}</span></div>
                        <div class="flex justify-between border-t border-slate-200 pt-1.5 text-sm font-bold text-slate-900 dark:border-slate-700 dark:text-white"><span>Total</span><span class="font-mono">{{ formatMoney(chosenQuote.total) }}</span></div>
                    </div>
                    <p v-if="quoteError" :class="errorText">{{ quoteError }}</p>
                    <p v-if="paidAlready" class="rounded-lg bg-blue-500/10 p-3 font-semibold text-blue-700 dark:text-blue-300">{{ formatMoney(booking.payment.amount_paid) }} has already been paid on this booking, so a change that alters the price will be refused.</p>

                    <div><label :class="label">Promo code (optional)</label><input v-model="form.promo_code" type="text" maxlength="40" :class="[field, 'sm:max-w-xs']" /></div>

                    <div v-if="canRecordPayment" class="space-y-2 rounded-xl border border-emerald-300 p-3 dark:border-emerald-800/60">
                        <label class="flex items-center gap-2 font-bold text-slate-900 dark:text-white"><input v-model="form.mark_paid.enabled" type="checkbox" class="rounded" /> They have already paid</label>
                        <div v-if="form.mark_paid.enabled" class="grid gap-3 sm:grid-cols-3">
                            <div><label :class="label">Amount received</label><input v-model="form.mark_paid.amount" type="number" step="0.01" min="0.01" :placeholder="chosenQuote ? String(chosenQuote.total) : ''" :class="field" /><p v-if="form.errors['mark_paid.amount']" :class="errorText">{{ form.errors['mark_paid.amount'] }}</p></div>
                            <div><label :class="label">Date received</label><input v-model="form.mark_paid.received_on" type="date" :class="field" /></div>
                            <div><label :class="label">Comment</label><input v-model="form.mark_paid.comment" type="text" maxlength="500" placeholder="e.g. cash at the door" :class="field" /></div>
                        </div>
                        <p v-if="form.mark_paid.enabled" class="text-slate-500 dark:text-slate-400">Leave the amount blank for the full total. It is recorded in the payment history under your name.</p>
                    </div>
                </template>
            </section>

            <!-- 4. Notes and confirmation -->
            <section class="space-y-3">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">4. Notes and confirmation</h3>
                <div><label :class="label">Internal note (only organisers see this)</label><textarea v-model="form.internal_note" rows="2" maxlength="2000" placeholder="e.g. seat with the Master, phoned in on Tuesday" :class="field"></textarea></div>
                <label class="flex items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
                    <input v-model="form.send_confirmation" type="checkbox" :disabled="!hasEmail" class="rounded" />
                    Email them a confirmation <span v-if="!hasEmail" class="font-normal text-slate-400">(add an email address first)</span>
                    <span v-else class="font-normal text-slate-500">(guests with their own email are told too)</span>
                </label>
            </section>

            <div class="sticky bottom-0 -mx-6 -mb-6 flex items-center justify-between gap-3 border-t border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
                <span class="font-mono text-sm font-bold text-slate-900 dark:text-white">{{ charged && chosenQuote ? `Total ${formatMoney(chosenQuote.total)}` : '' }}</span>
                <div class="flex gap-2">
                    <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="emit('close')">Cancel</button>
                    <button type="submit" :disabled="!canSubmit" class="rounded-lg bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-500 disabled:opacity-50">{{ form.processing ? 'Saving...' : editing ? 'Save changes' : 'Add booking' }}</button>
                </div>
            </div>
        </form>
    </Modal>
</template>
