<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';

defineProps({
    claims: { type: Array, default: () => [] },
    lodges: { type: Array, default: () => [] },
    limit: { type: Number, default: 100 },
});

const replies = reactive({});
const STATUS = {
    pending: { label: 'Being checked', variant: 'info' },
    more_info: { label: 'We need a reply', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    rejected: { label: 'Not approved', variant: 'danger' },
    withdrawn: { label: 'Withdrawn', variant: 'neutral' },
    superseded: { label: 'Closed', variant: 'neutral' },
};

function reply(claim) {
    router.post(route('lodges.claim.respond', claim.id), { reply: replies[claim.id] ?? '' }, { preserveScroll: true, onSuccess: () => { replies[claim.id] = ''; } });
}

function withdraw(claim) {
    if (confirm(`Withdraw your claim for ${claim.lodge.name}?`)) {
        router.delete(route('lodges.claim.withdraw', claim.id), { preserveScroll: true });
    }
}

function setCalendar(lodge, event) {
    router.patch(route('lodges.follow.update', lodge.slug), { in_calendar: event.target.checked }, { preserveScroll: true });
}

function setNotify(lodge, event) {
    router.patch(route('lodges.follow.update', lodge.slug), { notify_summons: event.target.checked }, { preserveScroll: true });
}

function unfollow(lodge) {
    router.delete(route('lodges.unfollow', lodge.slug), { preserveScroll: true });
}
</script>

<template>
    <MembersLayout title="My lodges" active-tab="lodges">
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">My lodges</h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Lodges you follow. Only you can see this list, and the lodges are not told.</p>
                </div>
                <Link :href="route('lodges.index')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Find more lodges</Link>
            </div>

            <Card v-if="!lodges.length">
                <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
                    You are not following any lodges yet. <Link :href="route('lodges.index')" class="text-blue-600 hover:underline dark:text-blue-400">Find a lodge</Link> and press Follow to see its meetings in your calendar.
                </p>
            </Card>

            <ul v-else class="grid gap-3 md:grid-cols-2">
                <li v-for="lodge in lodges" :key="lodge.slug" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <Link :href="route('lodges.show', lodge.slug)" class="block truncate font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ lodge.name }}</Link>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span><span v-if="lodge.province"> · {{ lodge.province }}</span></p>
                        </div>
                        <Badge v-if="lodge.status !== 'active'" variant="warning">{{ lodge.status }}</Badge>
                    </div>

                    <p v-if="lodge.hall" class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ lodge.hall.name }}<span v-if="lodge.hall.town">, {{ lodge.hall.town }}</span></p>
                    <p v-if="lodge.next.length" class="mt-1 text-sm"><span class="text-slate-500 dark:text-slate-400">Next expected: </span><span class="font-medium">{{ formatMeetingDate(lodge.next[0].date, lodge.next[0].time) }}</span><Badge v-if="lodge.next[0].installation" variant="warning" class="ml-1">Installation</Badge></p>
                    <p v-else-if="lodge.meets_text" class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">{{ lodge.meets_text }}</p>

                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                        <label class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <input type="checkbox" :checked="lodge.in_calendar" @change="setCalendar(lodge, $event)" /> In my calendar
                        </label>
                        <label v-if="lodge.is_managed" class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                            <input type="checkbox" :checked="lodge.notify_summons" @change="setNotify(lodge, $event)" /> Tell me about summonses
                        </label>
                        <button type="button" class="text-slate-500 hover:text-rose-600 dark:text-slate-400" @click="unfollow(lodge)">Unfollow</button>
                    </div>
                </li>
            </ul>

            <section v-if="claims.length" class="space-y-3" aria-labelledby="claims-heading">
                <h2 id="claims-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Your claims</h2>
                <ul class="space-y-3">
                    <li v-for="claim in claims" :key="claim.id" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Link :href="route('lodges.show', claim.lodge.slug)" class="font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ claim.lodge.name }}</Link>
                            <Badge :variant="STATUS[claim.status]?.variant ?? 'neutral'">{{ STATUS[claim.status]?.label ?? claim.status }}</Badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sent {{ claim.created_at }}</p>

                        <p v-if="claim.status === 'approved' && claim.club" class="mt-2 text-sm">
                            You can now manage it: <Link :href="route('member.dashboard', claim.club.slug)" class="text-blue-600 hover:underline dark:text-blue-400">open {{ claim.club.name }}</Link>.
                        </p>
                        <p v-if="claim.status === 'rejected' && claim.decision_note" class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ claim.decision_note }}</p>

                        <div v-if="claim.status === 'more_info'" class="mt-3 space-y-2">
                            <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ claim.question }}</p>
                            <textarea v-model="replies[claim.id]" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" aria-label="Your reply" />
                            <button type="button" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500" @click="reply(claim)">Send reply</button>
                        </div>

                        <button v-if="claim.open" type="button" class="mt-3 text-sm text-slate-500 hover:text-rose-600 dark:text-slate-400" @click="withdraw(claim)">Withdraw this claim</button>
                    </li>
                </ul>
            </section>

            <p v-if="lodges.length" class="text-xs text-slate-500 dark:text-slate-400">{{ lodges.length }} of {{ limit }} lodges followed.</p>
        </div>
    </MembersLayout>
</template>
