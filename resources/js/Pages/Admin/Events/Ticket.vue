<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import { formatMoney } from '@/Utils/currency';
import { formatEventTime } from '@/Utils/localDateTime';

// The booking behind a scanned ticket: who it covers, what they eat, whether it is paid, and one tap to check them in.
const props = defineProps({
    club: { type: Object, required: true },
    event: { type: Object, required: true },
    booking: { type: Object, required: true },
});

const waiting = computed(() => props.booking.attendees.filter((attendee) => !attendee.checked_in_at));
const meal = (attendee) => [attendee.meal?.starter, attendee.meal?.main, attendee.meal?.dessert].filter(Boolean).join(' · ');

const post = (data) => router.post(route('admin.events.checkin.store', { clubSlug: props.club.slug, id: props.event.id }), data, { preserveScroll: true });
const checkInEveryone = () => post({ registration_id: props.booking.id, action: 'checkin' });
const toggle = (attendee) => post({ attendee_id: attendee.id, action: attendee.checked_in_at ? 'undo' : 'checkin' });
</script>

<template>
    <AdminLayout title="Ticket" :club="club" active-tab="events">
        <div class="mx-auto max-w-xl space-y-4">
            <Card>
                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ event.title }} · {{ formatEventTime(event.starts_at) }}</p>
                <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-white">{{ booking.name }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <Badge v-if="!booking.holds_place" variant="danger">This booking is {{ booking.status }}</Badge>
                    <Badge v-if="booking.payment_status === 'paid' || booking.amount_due <= 0" variant="success">Paid</Badge>
                    <Badge v-else variant="warning">{{ formatMoney(booking.amount_due) }} still to pay</Badge>
                    <Badge>{{ booking.attendees.length === 1 ? '1 person' : `${booking.attendees.length} people` }}</Badge>
                </div>

                <Button v-if="waiting.length" class="mt-4 w-full" @click="checkInEveryone">
                    {{ booking.attendees.length === 1 ? 'Check in' : `Check in everyone (${waiting.length})` }}
                </Button>
                <p v-else class="mt-4 text-sm font-medium text-emerald-700 dark:text-emerald-400">Everyone in this booking is checked in.</p>
            </Card>

            <Card>
                <ul class="divide-y divide-slate-200 dark:divide-slate-800">
                    <li v-for="attendee in booking.attendees" :key="attendee.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                {{ attendee.name }}
                                <span v-if="attendee.is_guest" class="font-normal text-slate-500 dark:text-slate-400">(guest)</span>
                            </p>
                            <p v-if="meal(attendee)" class="text-xs text-slate-500 dark:text-slate-400">{{ meal(attendee) }}</p>
                            <p v-if="attendee.dietary_requirements" class="text-xs text-amber-700 dark:text-amber-400">{{ attendee.dietary_requirements }}</p>
                        </div>
                        <Button size="sm" :variant="attendee.checked_in_at ? 'ghost' : 'secondary'" @click="toggle(attendee)">
                            {{ attendee.checked_in_at ? 'Undo' : 'Check in' }}
                        </Button>
                    </li>
                </ul>
            </Card>

            <div class="flex justify-center">
                <Button variant="secondary" :href="route('admin.events.checkin', { clubSlug: club.slug, id: event.id })">Open the guest list</Button>
            </div>
        </div>
    </AdminLayout>
</template>
