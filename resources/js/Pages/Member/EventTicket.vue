<script setup>
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import { formatMoney } from '@/Utils/currency';
import { formatEventTime } from '@/Utils/localDateTime';
import SanitizedHtml from '@/Components/Ui/SanitizedHtml.vue';

defineProps({
    club: { type: Object, required: true },
    event: { type: Object, required: true },
    booking: { type: Object, required: true },
    // Drawn on the server from our own signed link, so it is safe to place as HTML.
    qrSvg: { type: String, required: true },
});

const meal = (attendee) => [attendee.meal?.starter, attendee.meal?.main, attendee.meal?.dessert].filter(Boolean).join(' · ');
</script>

<template>
    <MembersLayout title="Your ticket" :club="club" active-tab="events">
        <div class="mx-auto max-w-md space-y-4">
            <Card>
                <div class="space-y-1 text-center">
                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ club.name }}</p>
                    <h2 class="text-xl font-semibold text-slate-900 dark:text-white">{{ event.title }}</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ [formatEventTime(event.starts_at), event.location].filter(Boolean).join(' · ') }}</p>
                </div>

                <!-- Always dark on white, so a camera can read it in dark mode too. -->
                <SanitizedHtml class="mx-auto my-5 w-64 max-w-full rounded-xl bg-white p-3 [&_svg]:h-auto [&_svg]:w-full" :html="qrSvg" />
                <p class="text-center text-sm text-slate-600 dark:text-slate-300">Show this at the door. The organiser scans it to check your party in.</p>

                <div class="mt-4 flex justify-center">
                    <Badge v-if="booking.payment_status === 'paid' || booking.amount_due <= 0" variant="success">Paid</Badge>
                    <Badge v-else variant="warning">{{ formatMoney(booking.amount_due) }} to pay</Badge>
                </div>
            </Card>

            <Card>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">In this booking</h3>
                <ul class="mt-3 divide-y divide-slate-200 dark:divide-slate-800">
                    <li v-for="attendee in booking.attendees" :key="attendee.id" class="py-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-slate-900 dark:text-white">{{ attendee.name }}</span>
                            <Badge v-if="attendee.checked_in_at" variant="success">Checked in</Badge>
                            <Badge v-else-if="attendee.is_guest">Guest</Badge>
                        </div>
                        <p v-if="meal(attendee)" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ meal(attendee) }}</p>
                    </li>
                </ul>
            </Card>

            <div class="flex justify-center">
                <Button variant="secondary" :href="route('member.events', { slug: club.slug })">Back to events</Button>
            </div>
        </div>
    </MembersLayout>
</template>
