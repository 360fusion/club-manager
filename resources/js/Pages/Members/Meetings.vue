<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChip from '@/Components/Ui/ClubChip.vue';
import ClubChips from '@/Components/ClubChips.vue';
import DateTile from '@/Components/Ui/DateTile.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';

const props = defineProps({
    meetings: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const urlScoped = computed(() => Boolean(route().params.slug));
const chipsRoute = 'members.meetings';
const when = (iso) => new Date(iso).toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const day = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const selectedMeeting = ref(null);
const STATUS = {
    attending_dining: { label: 'Attending dining', variant: 'success' },
    attending_meeting_only: { label: 'Attending meeting', variant: 'info' },
    apologies: { label: 'Apologies', variant: 'danger' },
};
const REPLIES = { attending_dining: 'Going, dining', attending_meeting_only: 'Going', apologies: 'Apologies' };
</script>

<template>
    <MembersLayout title="Meetings" :club="urlScoped ? scopeClub : null" active-tab="meetings">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Meetings and summonses</h1>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" :route-name="chipsRoute" />

            <Card v-if="meetings.length" padding="none">
                <ul>
                    <li v-for="meeting in meetings" :key="meeting.key" class="flex gap-4 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <DateTile :date="meeting.start" :colour="meeting.club.colour" size="sm" />
                        <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <Badge v-if="meeting.clash" variant="warning">Clashes with another item</Badge>
                            <ClubChip :name="meeting.club.name" :colour="meeting.club.colour" />
                            <span aria-hidden="true">·</span>
                            <span>{{ when(meeting.start) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <Link :href="meeting.url" class="text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ meeting.title }}</Link>
                                <p v-if="meeting.where" class="text-xs text-slate-500 dark:text-slate-400">{{ meeting.where }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge v-if="meeting.rsvp" :variant="STATUS[meeting.rsvp.attendance_status]?.variant ?? 'neutral'">{{ STATUS[meeting.rsvp.attendance_status]?.label ?? meeting.rsvp.attendance_status }}</Badge>
                                <Badge v-if="meeting.rsvp?.attendance_status === 'attending_dining' && meeting.rsvp.payment_status === 'paid'" variant="success">Paid</Badge>
                                <Button size="sm" :variant="meeting.rsvp ? 'secondary' : 'primary'" :disabled="meeting.closed" @click="selectedMeeting = meeting">
                                    {{ meeting.rsvp ? 'Edit Response' : 'Respond' }}
                                </Button>
                                <span v-if="meeting.closed" class="text-xs text-slate-500 dark:text-slate-400">Replies closed</span>
                            </div>
                        </div>
                        </div>
                    </li>
                </ul>
            </Card>
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No upcoming meetings.</p></Card>

            <section v-if="recent.length" aria-labelledby="recent-heading" class="space-y-2">
                <h2 id="recent-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Recent meetings</h2>
                <Card padding="none">
                    <ul>
                        <li v-for="meeting in recent" :key="meeting.id" class="flex items-center justify-between gap-3 border-b border-slate-200 p-3 text-sm last:border-b-0 dark:border-slate-800">
                            <Link :href="route('member.meetings.summons', { slug: meeting.club.slug, id: meeting.id })" class="min-w-0 truncate font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ meeting.title }}</Link>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ meeting.club.name }} · {{ day(meeting.date) }}<template v-if="meeting.reply"> · {{ REPLIES[meeting.reply] ?? meeting.reply }}</template></span>
                        </li>
                    </ul>
                </Card>
            </section>
        </div>

        <MeetingRsvpModal v-if="selectedMeeting" :key="selectedMeeting.key" :meeting="selectedMeeting" :club-slug="selectedMeeting.club.slug" :rsvp="selectedMeeting.rsvp" @close="selectedMeeting = null" />
    </MembersLayout>
</template>
