<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

const props = defineProps({
    // { type: 'meeting' | 'event', id, slug, reply, closed, simple }
    item: { type: Object, required: true },
});

// The answer just tapped shows straight away; it goes back to the saved one if the save fails.
const chosen = ref(null);
const current = computed(() => chosen.value ?? props.item.reply);

const OPTIONS = {
    meeting: [
        { value: 'attending_dining', label: 'Going, dining' },
        { value: 'attending_meeting_only', label: 'Going, no dining' },
        { value: 'apologies', label: 'Apologies' },
    ],
    event: [
        { value: 'attending', label: 'Going' },
        { value: 'declined', label: "Can't go" },
    ],
};

// Changing a reply already given asks first (the same as on the summons).
const changingTo = ref(null);
const labelFor = (value) => OPTIONS[props.item.type].find((option) => option.value === value)?.label ?? '';
const choose = (status) => {
    if (status === current.value) return;
    // Only a real reply asks first: someone who hasn't replied yet may be stored as pending.
    if (labelFor(current.value)) {
        changingTo.value = status;
        return;
    }
    reply(status);
};
const diningNote = computed(() => (props.item.type === 'meeting' && current.value === 'attending_dining'
    ? ` Your dining place will be given up${props.item.closed ? ', and dining numbers have gone to the caterer so it can\'t be added back here' : ''}. If you have paid, it is refunded: by card or PayPal straight away, by bank transfer through the Treasurer.`
    : ''));
const confirmChange = () => {
    const status = changingTo.value;
    changingTo.value = null;
    reply(status);
};

const reply = (status) => {
    chosen.value = status;

    router.post(
        route(props.item.type === 'meeting' ? 'member.meetings.quick_rsvp' : 'member.events.quick_rsvp', { slug: props.item.slug, id: props.item.id }),
        { attendance_status: status },
        { preserveScroll: true, onFinish: () => { chosen.value = null; } },
    );
};

// An event with tickets or a menu is booked on its own page; once booked, say so instead of offering a fresh reply.
const booked = () => props.item.type === 'event' && !props.item.simple && ['attending', 'tentative'].includes(current.value);

// A meeting past its dining deadline still takes replies; only adding dining is closed (the caterer has the numbers).
const isDisabled = (value) => (props.item.type === 'meeting' ? props.item.closed && value === 'attending_dining' && current.value !== 'attending_dining' : props.item.closed);

const options = () => (booked() ? [] : OPTIONS[props.item.type].filter((option) => props.item.type === 'meeting' || props.item.simple || option.value === 'declined'));
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Reply">
        <Button
            v-for="option in options()"
            :key="option.value"
            size="sm"
            :variant="current === option.value ? 'success' : 'secondary'"
            :disabled="isDisabled(option.value)"
            :aria-pressed="current === option.value"
            @click="choose(option.value)"
        >
            {{ option.label }}
        </Button>
        <span v-if="booked()" class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Booked</span>
        <Link v-if="item.type === 'event' && !item.simple && (booked() || !item.closed)" :href="route('member.events', { slug: item.slug })" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ booked() ? 'View booking' : 'Choose options' }}
        </Link>
        <span v-if="item.closed" class="text-xs text-slate-500 dark:text-slate-400">{{ item.type === 'meeting' ? 'Dining closed' : 'Replies closed' }}</span>
        <ConfirmDialog :open="changingTo !== null" :title="`Change your reply to ${labelFor(changingTo)}?`" :confirm-label="`Yes, ${labelFor(changingTo).toLowerCase()}`" cancel-label="Keep my reply" variant="primary" @confirm="confirmChange" @close="changingTo = null">
            You replied {{ labelFor(current) }}.{{ diningNote }}
        </ConfirmDialog>
    </div>
</template>
