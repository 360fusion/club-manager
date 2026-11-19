<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Ui/Badge.vue';

// One person on the event night list: a big tick to mark them arrived, what they are eating, and where their booking's
// payment stands. Money can only be taken on the booker's row, since the booking is paid as a whole.
const props = defineProps({
    person: { type: Object, required: true },
    hasDining: { type: Boolean, default: false },
    canTakePayment: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle', 'take']);

const arrived = computed(() => !!props.person.checked_in_at);
const owes = computed(() => Number(props.person.balance) > 0 && ['unpaid', 'part_paid'].includes(props.person.payment_status));

const details = computed(() => {
    const p = props.person;
    const meal = [p.meal?.starter, p.meal?.main, p.meal?.dessert].filter(Boolean).join(' · ');

    return [
        arrived.value ? p.checked_in_at.slice(0, 5) : null,
        p.is_guest ? `Guest of ${p.booked_by}` : (p.is_visitor ? ['Visitor', p.organisation].filter(Boolean).join(', ') : null),
        p.attending_dining ? (meal || 'Dining') : (props.hasDining ? 'Not dining' : null),
    ].filter(Boolean).join(' · ');
});

const chip = computed(() => {
    const status = props.person.payment_status;
    if (owes.value) return props.person.pays_at_door ? ['info', 'Pay on night'] : ['warning', 'To pay'];
    if (status === 'paid') return ['success', 'Paid'];
    if (status === 'waived') return ['neutral', 'Waived'];
    if (status === 'refunded') return ['danger', 'Refunded'];
    return null;
});
</script>

<template>
    <li class="flex items-center gap-3 px-4 py-3">
        <button
            type="button"
            :class="['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950', arrived ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-transparent hover:text-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:text-slate-600']"
            :aria-label="arrived ? `Undo arrival for ${person.name}` : `Mark ${person.name} as arrived`"
            :aria-pressed="arrived"
            @click="emit('toggle', person)"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
        </button>

        <div class="min-w-0 flex-1">
            <div class="font-semibold text-slate-900 dark:text-white">{{ person.name }}</div>
            <div v-if="details" class="text-xs text-slate-500 dark:text-slate-400">{{ details }}</div>
            <div v-if="person.dietary_requirements" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ person.dietary_requirements }}</div>
        </div>

        <button
            v-if="owes && canTakePayment && person.is_booker"
            type="button"
            class="min-h-11 shrink-0 whitespace-nowrap rounded-xl border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-900 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800"
            @click="emit('take', person)"
        >
            Take {{ $money(person.balance) }}
        </button>
        <Badge v-else-if="chip" :variant="chip[0]">{{ chip[1] }}</Badge>
    </li>
</template>
