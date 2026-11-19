<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    // { type: 'meeting' | 'event', id, slug, reply, closed, simple }
    item: { type: Object, required: true },
});

const busy = ref(null);

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

const reply = (status) => {
    busy.value = status;

    router.post(
        route(props.item.type === 'meeting' ? 'member.meetings.quick_rsvp' : 'member.events.quick_rsvp', { slug: props.item.slug, id: props.item.id }),
        { attendance_status: status },
        { preserveScroll: true, onFinish: () => { busy.value = null; } },
    );
};

// An event with tickets or a menu is booked on its own page; once booked, say so instead of offering a fresh reply.
const booked = () => props.item.type === 'event' && !props.item.simple && ['attending', 'tentative'].includes(props.item.reply);

// A meeting past its dining deadline still takes replies; only adding dining is closed (the caterer has the numbers).
const isDisabled = (value) => (props.item.type === 'meeting' ? props.item.closed && value === 'attending_dining' && props.item.reply !== 'attending_dining' : props.item.closed);

const options = () => (booked() ? [] : OPTIONS[props.item.type].filter((option) => props.item.type === 'meeting' || props.item.simple || option.value === 'declined'));
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Reply">
        <Button
            v-for="option in options()"
            :key="option.value"
            size="sm"
            :variant="item.reply === option.value ? 'primary' : 'secondary'"
            :loading="busy === option.value"
            :disabled="isDisabled(option.value)"
            :aria-pressed="item.reply === option.value"
            @click="reply(option.value)"
        >
            {{ option.label }}
        </Button>
        <span v-if="booked()" class="text-xs font-semibold text-emerald-700 dark:text-emerald-400">Booked</span>
        <Link v-if="item.type === 'event' && !item.simple && (booked() || !item.closed)" :href="route('member.events', { slug: item.slug })" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
            {{ booked() ? 'View booking' : 'Choose options' }}
        </Link>
        <span v-if="item.closed" class="text-xs text-slate-500 dark:text-slate-400">{{ item.type === 'meeting' ? 'Dining closed' : 'Replies closed' }}</span>
    </div>
</template>
