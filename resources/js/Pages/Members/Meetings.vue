<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChips from '@/Components/ClubChips.vue';
import QuickReply from '@/Components/QuickReply.vue';

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
const REPLIES = { attending_dining: 'Going, dining', attending_meeting_only: 'Going', apologies: 'Apologies' };
</script>

<template>
    <MembersLayout title="Meetings" :club="urlScoped ? scopeClub : null" active-tab="meetings">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Meetings and summonses</h1>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" :route-name="chipsRoute" />

            <Card v-if="meetings.length" padding="none">
                <ul>
                    <li v-for="meeting in meetings" :key="meeting.key" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <Badge v-if="meeting.clash" variant="warning">Clashes with another item</Badge>
                            <span>{{ meeting.club.name }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ when(meeting.start) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <Link :href="meeting.url" class="text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ meeting.title }}</Link>
                                <p v-if="meeting.where" class="text-xs text-slate-500 dark:text-slate-400">{{ meeting.where }}</p>
                            </div>
                            <QuickReply :item="{ type: 'meeting', id: meeting.id, slug: meeting.club.slug, reply: meeting.reply, closed: meeting.closed, simple: true }" />
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
    </MembersLayout>
</template>
