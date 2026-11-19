<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChip from '@/Components/Ui/ClubChip.vue';
import ClubChips from '@/Components/ClubChips.vue';
import DateTile from '@/Components/Ui/DateTile.vue';
import QuickReply from '@/Components/QuickReply.vue';
import BookingCard from '@/Components/Events/Member/BookingCard.vue';

const props = defineProps({
    // Upcoming events; `booking` is the member's own booking when they hold or wait for a place.
    events: { type: Array, default: () => [] },
    // The member's bookings for events in the last year, newest first.
    past: { type: Array, default: () => [] },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const show = ref('upcoming');
const booked = computed(() => props.events.filter((event) => event.booking));
const others = computed(() => props.events.filter((event) => !event.booking));
const manyClubs = computed(() => !props.scopeClub && props.clubOptions.length > 1);

const when = (iso) => new Date(iso).toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const bookUrl = (event) => `${route('member.events', { slug: event.club.slug })}?book=${event.id}`;
const segmentClass = (active) => ['min-h-11 rounded-xl text-sm transition-colors sm:min-h-9', active ? 'bg-blue-600 font-semibold text-white' : 'font-medium text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'];
</script>

<template>
    <MembersLayout title="My bookings" active-tab="events">
        <div class="space-y-4">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">My bookings</h2>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.events" />

            <div class="grid grid-cols-2 gap-1 rounded-xl border border-slate-200 bg-white p-1 sm:max-w-xs dark:border-slate-800 dark:bg-slate-900" role="group" aria-label="Show">
                <button type="button" :class="segmentClass(show === 'upcoming')" :aria-pressed="show === 'upcoming'" @click="show = 'upcoming'">Upcoming</button>
                <button type="button" :class="segmentClass(show === 'past')" :aria-pressed="show === 'past'" @click="show = 'past'">Past</button>
            </div>

            <template v-if="show === 'upcoming'">
                <div v-if="booked.length" class="grid gap-3 lg:grid-cols-2">
                    <BookingCard v-for="event in booked" :key="event.key" :item="event" :show-club="manyClubs" />
                </div>
                <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">You have no upcoming bookings.</p></Card>

                <section v-if="others.length" class="space-y-3 pt-2">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">Other events coming up</h3>
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
                <div v-if="past.length" class="grid gap-3 lg:grid-cols-2">
                    <BookingCard v-for="event in past" :key="event.key" :item="event" past :show-club="manyClubs" />
                </div>
                <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No bookings in the last year.</p></Card>
            </template>
        </div>
    </MembersLayout>
</template>
