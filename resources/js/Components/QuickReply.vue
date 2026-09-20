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

const options = () => OPTIONS[props.item.type].filter((option) => props.item.type === 'meeting' || props.item.simple || option.value === 'declined');
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Reply">
        <Button
            v-for="option in options()"
            :key="option.value"
            size="sm"
            :variant="item.reply === option.value ? 'primary' : 'secondary'"
            :loading="busy === option.value"
            :disabled="item.closed"
            :aria-pressed="item.reply === option.value"
            @click="reply(option.value)"
        >
            {{ option.label }}
        </Button>
        <Link v-if="item.type === 'event' && !item.simple && !item.closed" :href="route('member.events', { slug: item.slug })" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">
            Choose options
        </Link>
        <span v-if="item.closed" class="text-xs text-slate-500 dark:text-slate-400">Replies closed</span>
    </div>
</template>
