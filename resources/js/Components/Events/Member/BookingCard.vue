<script setup>
// One of the member's own event bookings on the Bookings page: when, who is going, each person's meal and price, where
// the payment stands, and what they can do next. Amounts are the server's, shown in the booking's club currency.
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import DateTile from '@/Components/Ui/DateTile.vue';

const props = defineProps({
    // An event from MemberListController::events, with `booking` from EventPayload::registration.
    item: { type: Object, required: true },
    past: { type: Boolean, default: false },
    showClub: { type: Boolean, default: false },
});

const booking = computed(() => props.item.booking);
const payment = computed(() => booking.value.payment ?? {});
const waiting = computed(() => booking.value.attendance_status === 'waitlisted');
const charged = computed(() => Number(payment.value.total) > 0);
const owes = computed(() => charged.value && Number(payment.value.balance) > 0 && !['waived', 'refunded'].includes(payment.value.status));

const money = (amount) => (props.item.club.currency_symbol ?? '') + (Number(String(amount ?? 0).replace(/,/g, '')) || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const when = computed(() => {
    const date = new Date(props.item.start);
    const sameYear = date.getFullYear() === new Date().getFullYear();

    return date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: sameYear ? undefined : 'numeric' });
});

const guests = computed(() => booking.value.attendees.filter((a) => a.is_guest).length);
const who = computed(() => (guests.value === 0 ? 'Just you' : `You and ${guests.value} ${guests.value === 1 ? 'guest' : 'guests'}`));

const ordinal = (n) => {
    const tens = n % 100;
    const suffix = tens >= 11 && tens <= 13 ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th');

    return `${n}${suffix}`;
};

const chip = computed(() => {
    if (waiting.value) {
        return { variant: 'info', label: booking.value.waitlist_position ? `Waiting list, ${ordinal(booking.value.waitlist_position)}` : 'Waiting list' };
    }

    if (booking.value.checked_in_at) return { variant: 'success', label: 'Checked in' };
    if (!charged.value) return { variant: booking.value.attendance_status === 'tentative' ? 'neutral' : 'success', label: booking.value.attendance_status === 'tentative' ? 'Maybe' : 'Booked' };

    return {
        paid: { variant: 'success', label: 'Paid' },
        waived: { variant: 'info', label: 'No charge' },
        refunded: { variant: 'neutral', label: 'Refunded' },
        part_paid: { variant: 'warning', label: 'Part paid' },
    }[payment.value.status] ?? { variant: 'warning', label: payment.value.said_paid_at ? 'Checking your payment' : 'Payment due' };
});

const personLabel = (person) => (person.is_guest ? person.name || 'Guest' : 'You');
const mealOf = (person) => {
    const dishes = Object.values(person.meal ?? {}).filter(Boolean);

    if (dishes.length) return dishes.join(', ');

    return person.attending_dining ? 'Dining' : '';
};

// The cheapest way to pay online, the one the total on the button is for.
const offer = computed(() => {
    const options = payment.value.online?.can_pay ? payment.value.online.options : [];

    return [...options].sort((a, b) => a.total - b.total)[0] ?? null;
});

const changeUrl = computed(() => `${route('member.events', { slug: props.item.club.slug })}?book=${props.item.id}`);

const paying = ref(false);
const pay = () => {
    paying.value = true;
    router.post(route('member.events.pay', { slug: props.item.club.slug, id: props.item.id }), { option: offer.value.id }, { onFinish: () => { paying.value = false; } });
};
</script>

<template>
    <article class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start gap-3">
            <DateTile :date="item.start" :colour="item.club.colour" size="sm" />
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-slate-900 dark:text-white">{{ item.title }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ when }} · {{ who }}</p>
                <p v-if="showClub || item.where" class="text-xs text-slate-500 dark:text-slate-400">{{ [showClub ? item.club.name : null, item.where].filter(Boolean).join(' · ') }}</p>
            </div>
            <Badge :variant="chip.variant">{{ chip.label }}</Badge>
        </div>

        <p v-if="waiting" class="text-xs text-slate-500 dark:text-slate-400">We'll tell you as soon as a place comes free.<template v-if="charged"> You won't pay until then.</template></p>

        <ul v-else class="space-y-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm dark:border-slate-800 dark:bg-slate-800/40">
            <li v-for="person in booking.attendees" :key="person.id" class="flex justify-between gap-2 tabular-nums">
                <span class="min-w-0 text-slate-700 dark:text-slate-300">
                    <span class="font-medium text-slate-900 dark:text-white">{{ personLabel(person) }}</span><template v-if="mealOf(person)"> · {{ mealOf(person) }}</template>
                </span>
                <span v-if="charged" class="shrink-0 text-slate-700 dark:text-slate-300">{{ money(person.price) }}</span>
            </li>
            <li v-if="owes && Number(payment.amount_paid) > 0" class="flex justify-between gap-2 border-t border-slate-200 pt-1 text-xs tabular-nums text-slate-500 dark:border-slate-700 dark:text-slate-400">
                <span>Paid {{ money(payment.amount_paid) }} of {{ money(payment.total) }}</span><span>{{ money(payment.balance) }} to pay</span>
            </li>
        </ul>

        <div v-if="!past" class="flex flex-wrap gap-2 [&>*]:flex-1">
            <Button v-if="!item.closed" variant="secondary" :href="changeUrl">{{ booking.attendees.some((a) => a.attending_dining) ? 'Change meals' : 'Change booking' }}</Button>
            <Button v-if="!waiting" variant="secondary" :href="route('member.events.ticket', { slug: item.club.slug, id: item.id })">Ticket</Button>
            <Button v-if="owes && !waiting && offer" :loading="paying" @click="pay">Pay {{ money(offer.total) }}</Button>
            <Button v-else-if="owes && !waiting && !payment.said_paid_at" :href="changeUrl">How to pay</Button>
        </div>
    </article>
</template>
