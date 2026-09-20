<script setup>
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChips from '@/Components/ClubChips.vue';
import QuickReply from '@/Components/QuickReply.vue';

defineProps({
    events: { type: Array, default: () => [] },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const when = (iso) => new Date(iso).toLocaleString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <MembersLayout title="Events" active-tab="events">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Events</h1>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.events" />

            <Card v-if="events.length" padding="none">
                <ul>
                    <li v-for="event in events" :key="event.key" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <Badge v-if="event.clash" variant="warning">Clashes with another item</Badge>
                            <span>{{ event.club.name }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ when(event.start) }}</span>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <Link :href="route('member.events', { slug: event.club.slug })" class="text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ event.title }}</Link>
                                <p v-if="event.where" class="text-xs text-slate-500 dark:text-slate-400">{{ event.where }}</p>
                            </div>
                            <QuickReply :item="{ type: 'event', id: event.id, slug: event.club.slug, reply: event.reply, closed: event.closed, simple: event.simple }" />
                        </div>
                    </li>
                </ul>
            </Card>
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">No upcoming events across your clubs.</p></Card>
        </div>
    </MembersLayout>
</template>
