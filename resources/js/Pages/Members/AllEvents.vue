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
import QuickReply from '@/Components/QuickReply.vue';
import BookingCard from '@/Components/Events/Member/BookingCard.vue';
import MeetingRsvpModal from '@/Components/MeetingRsvpModal.vue';

// Meetings and bookings: the lodge meetings a member is summoned to and the events they book, in one place.
const props = defineProps({
    // Upcoming events; `booking` is the member's own booking when they hold or wait for a place.
    events: { type: Array, default: () => [] },
    // The member's bookings for events in the last year, newest first.
    past: { type: Array, default: () => [] },
    // Upcoming meetings with the member's reply (`rsvp`), and the last ten with theirs.
    meetings: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    show: { type: String, default: 'all' },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const SHOW = [{ key: 'all', label: 'All' }, { key: 'meetings', label: 'Meetings' }, { key: 'events', label: 'Events' }, { key: 'past', label: 'Past' }];
const showing = ref(props.show);
const shows = (key) => showing.value === 'all' || showing.value === key;

const booked = computed(() => props.events.filter((event) => event.booking));
const others = computed(() => props.events.filter((event) => !event.booking));
const manyClubs = computed(() => !props.scopeClub && props.clubOptions.length > 1);
const selectedMeeting = ref(null);

const STATUS = {
    attending_dining: { label: 'Going, dining', variant: 'success' },
    attending_meeting_only: { label: 'Going', variant: 'info' },
    apologies: { label: 'Apologies', variant: 'danger' },
};

const when = (iso) => new Date(iso).toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const day = (date) => new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
const bookUrl = (event) => `${route('member.events', { slug: event.club.slug })}?book=${event.id}`;
const segmentClass = (active) => ['min-h-11 rounded-xl text-sm transition-colors sm:min-h-9', active ? 'bg-blue-600 font-semibold text-white' : 'font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'];
const headingClass = 'text-base font-semibold text-slate-900 dark:text-white';
</script>

<template>
    <MembersLayout title="Meetings and bookings" active-tab="events">
        <div class="space-y-4">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Meetings and bookings</h2>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.events" />

            <div class="grid grid-cols-4 gap-1 rounded-xl border border-slate-200 bg-white p-1 sm:max-w-md dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Show">
                <button v-for="option in SHOW" :key="option.key" type="button" :class="segmentClass(showing === option.key)" :aria-pressed="showing === option.key" @click="showing = option.key">{{ option.label }}</button>
            </div>

            <template v-if="showing !== 'past'">
                <section v-if="shows('meetings')" class="space-y-3" aria-labelledby="meetings-heading">
                    <h3 id="meetings-heading" :class="headingClass">Meetings</h3>
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
                                                {{ meeting.rsvp ? 'Change reply' : 'Reply' }}
                                            </Button>
                                            <span v-if="meeting.closed" class="text-xs text-slate-500 dark:text-slate-400">Replies closed</span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </Card>
                    <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No upcoming meetings.</p></Card>
                </section>

                <section v-if="shows('events')" class="space-y-3" aria-labelledby="bookings-heading">
                    <h3 id="bookings-heading" :class="headingClass">Your bookings</h3>
                    <div v-if="booked.length" class="grid gap-3 lg:grid-cols-2">
                        <BookingCard v-for="event in booked" :key="event.key" :item="event" :show-club="manyClubs" />
                    </div>
                    <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">You have no upcoming bookings.</p></Card>
                </section>

                <section v-if="shows('events') && others.length" class="space-y-3" aria-labelledby="others-heading">
                    <h3 id="others-heading" :class="headingClass">Other events coming up</h3>
                    <Card padding="none">
                        <ul>
                            <li v-for="event in others" :key="event.key" class="flex gap-4 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                                <DateTile :date="event.start" :colour="event.club.colour" size="sm" />
                                <div class="min-w-0 flex-1 space-y-2">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                        <Badge v-if="event.clash" variant="warning">Clashes with another item</Badge>
                                        <ClubChip :name="event.club.name" :colour="event.club.colour" />
                                        <span aria-hidden="true">·</span>
                                        <span>{{ when(event.start) }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <Link :href="bookUrl(event)" class="text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ event.title }}</Link>
                                            <p v-if="event.where" class="text-xs text-slate-500 dark:text-slate-400">{{ event.where }}</p>
                                        </div>
                                        <QuickReply :item="{ type: 'event', id: event.id, slug: event.club.slug, reply: event.reply, closed: event.closed, simple: event.simple }" />
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </Card>
                </section>
            </template>

            <template v-else>
                <section class="space-y-3" aria-labelledby="past-bookings-heading">
                    <h3 id="past-bookings-heading" :class="headingClass">Past bookings</h3>
                    <div v-if="past.length" class="grid gap-3 lg:grid-cols-2">
                        <BookingCard v-for="event in past" :key="event.key" :item="event" past :show-club="manyClubs" />
                    </div>
                    <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No bookings in the last year.</p></Card>
                </section>

                <section v-if="recent.length" class="space-y-3" aria-labelledby="recent-heading">
                    <h3 id="recent-heading" :class="headingClass">Recent meetings</h3>
                    <Card padding="none">
                        <ul>
                            <li v-for="meeting in recent" :key="meeting.id" class="flex items-center justify-between gap-3 border-b border-slate-200 p-3 text-sm last:border-b-0 dark:border-slate-800">
                                <Link :href="route('member.meetings.summons', { slug: meeting.club.slug, id: meeting.id })" class="min-w-0 truncate font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ meeting.title }}</Link>
                                <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ meeting.club.name }} · {{ day(meeting.date) }}<template v-if="meeting.reply"> · {{ STATUS[meeting.reply]?.label ?? meeting.reply }}</template></span>
                            </li>
                        </ul>
                    </Card>
                </section>
            </template>
        </div>

        <MeetingRsvpModal v-if="selectedMeeting" :key="selectedMeeting.key" :meeting="selectedMeeting" :club-slug="selectedMeeting.club.slug" :rsvp="selectedMeeting.rsvp" @close="selectedMeeting = null" />
    </MembersLayout>
</template>
