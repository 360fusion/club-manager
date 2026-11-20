<script setup>
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import { formatMeetingDate } from '@/Utils/lodgeDates';

const props = defineProps({
    visits: { type: Array, default: () => [] },
    claims: { type: Array, default: () => [] },
    corrections: { type: Array, default: () => [] },
    lodges: { type: Array, default: () => [] },
    limit: { type: Number, default: 100 },
    province: { type: Object, default: null },
    own: { type: Array, default: () => [] },
    suggested: { type: Array, default: () => [] },
});

const following = reactive({});
function follow(lodge) {
    following[lodge.slug] = true;
    router.post(route('lodges.follow', lodge.slug), {}, { preserveScroll: true, onFinish: () => { following[lodge.slug] = false; } });
}

const replies = reactive({});
const STATUS = {
    pending: { label: 'Being checked', variant: 'info' },
    more_info: { label: 'We need a reply', variant: 'warning' },
    approved: { label: 'Approved', variant: 'success' },
    rejected: { label: 'Not approved', variant: 'danger' },
    withdrawn: { label: 'Withdrawn', variant: 'neutral' },
    superseded: { label: 'Closed', variant: 'neutral' },
};

const CORRECTION_STATUS = {
    pending: { label: 'Being checked', variant: 'info' },
    applied: { label: 'Applied', variant: 'success' },
    rejected: { label: 'Not applied', variant: 'danger' },
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

// A visit is to a lodge they follow (linked to its directory page) or one they type in.
const today = new Date().toISOString().slice(0, 10);
const visit = useForm({ lodge_slug: '', lodge_name: '', visited_on: today, note: '' });
function recordVisit() {
    visit.post(route('members.visits.store'), { preserveScroll: true, onSuccess: () => visit.reset() });
}

function removeVisit(id) {
    router.delete(route('members.visits.destroy', id), { preserveScroll: true });
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
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">My lodges</h2>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Your own lodges and the ones you follow. Only you can see who you follow, and the lodges are not told.</p>
                </div>
                <Link :href="route('lodges.index')" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Find more lodges</Link>
            </div>

            <Card v-if="province">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">You look after the listings for {{ province.names.join(', ') }}. <strong>{{ province.waiting }}</strong> suggested {{ province.waiting === 1 ? 'fix is' : 'fixes are' }} waiting.</p>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('members.lodges.fixes.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Review suggested fixes</Link>
                        <Link :href="route('members.lodges.directory.index')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit lodge listings</Link>
                    </div>
                </div>
            </Card>

            <section v-if="own.length" class="space-y-2" aria-labelledby="own-heading">
                <h2 id="own-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Your lodges</h2>
                <ul class="grid gap-3 md:grid-cols-2">
                    <li v-for="club in own" :key="club.slug" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-white">{{ club.name }}</p>
                            <p class="text-xs capitalize text-slate-500 dark:text-slate-400">{{ club.role }}<template v-if="club.hall"> · <span class="normal-case">{{ club.hall.name }}<template v-if="club.hall.town">, {{ club.hall.town }}</template></span></template></p>
                        </div>
                        <Button size="sm" variant="secondary" :href="route('member.dashboard', { slug: club.slug })">Open</Button>
                    </li>
                </ul>
            </section>

            <section v-if="suggested.length" class="space-y-2" aria-labelledby="suggested-heading">
                <div>
                    <h2 id="suggested-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Lodges you might follow</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">From your hall and your lodges' sister and visiting lists. Following puts their meeting dates in your calendar.</p>
                </div>
                <p v-if="$page.props.errors?.follow" class="text-sm text-rose-600 dark:text-rose-400">{{ $page.props.errors.follow }}</p>
                <ul class="grid gap-3 md:grid-cols-2">
                    <li v-for="lodge in suggested" :key="lodge.slug" class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="min-w-0">
                            <Link :href="route('lodges.show', lodge.slug)" class="block font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ lodge.name }}</Link>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ lodge.reason }}<template v-if="lodge.order"> · {{ lodge.order }}</template></p>
                            <p v-if="lodge.meets_text" class="mt-1 line-clamp-2 text-xs text-slate-600 dark:text-slate-400">{{ lodge.meets_text }}</p>
                        </div>
                        <Button size="sm" :disabled="following[lodge.slug]" @click="follow(lodge)">Follow</Button>
                    </li>
                </ul>
            </section>

            <h2 v-if="own.length || suggested.length" class="text-lg font-semibold text-slate-900 dark:text-white">Following</h2>

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

            <section class="space-y-3" aria-labelledby="visits-heading">
                <div>
                    <h2 id="visits-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Lodges I have visited</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Your own record of visits. The Secretaries of your lodges can see where and when, but not your note.</p>
                </div>
                <Card>
                    <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="recordVisit">
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Lodge</span>
                            <select v-model="visit.lodge_slug" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                <option value="">Another lodge (type its name)</option>
                                <option v-for="lodge in props.lodges" :key="lodge.slug" :value="lodge.slug">{{ lodge.name }}</option>
                            </select>
                        </label>
                        <label v-if="!visit.lodge_slug" class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Lodge name and number</span>
                            <input v-model="visit.lodge_name" type="text" maxlength="200" required placeholder="St John's Lodge No 1234" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                        </label>
                        <label class="block text-sm">
                            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Date</span>
                            <input v-model="visit.visited_on" type="date" :max="today" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                        </label>
                        <label class="block text-sm sm:col-span-2">
                            <span class="mb-1 block font-medium text-slate-700 dark:text-slate-200">Note (only you see this)</span>
                            <input v-model="visit.note" type="text" maxlength="500" placeholder="Second degree, dined" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                        </label>
                        <p v-for="(error, field) in visit.errors" :key="field" class="text-xs text-rose-600 dark:text-rose-400 sm:col-span-2">{{ error }}</p>
                        <div class="sm:col-span-2">
                            <button type="submit" :disabled="visit.processing" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500 disabled:opacity-60">Record visit</button>
                        </div>
                    </form>
                </Card>
                <ul v-if="visits.length" class="divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white text-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                    <li v-for="item in visits" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                        <span class="min-w-0">
                            <Link v-if="item.lodge_slug" :href="route('lodges.show', item.lodge_slug)" class="font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ item.lodge }}</Link>
                            <span v-else class="font-medium text-slate-900 dark:text-white">{{ item.lodge }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ item.visited_on }}<template v-if="item.note"> · {{ item.note }}</template></span>
                        </span>
                        <button type="button" class="text-xs text-slate-500 hover:text-rose-600 dark:text-slate-400" :aria-label="`Remove your visit to ${item.lodge} on ${item.visited_on}`" @click="removeVisit(item.id)">Remove</button>
                    </li>
                </ul>
            </section>

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
                            <button type="button" class="rounded-xl bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500" @click="reply(claim)">Send reply</button>
                        </div>

                        <button v-if="claim.open" type="button" class="mt-3 text-sm text-slate-500 hover:text-rose-600 dark:text-slate-400" @click="withdraw(claim)">Withdraw this claim</button>
                    </li>
                </ul>
            </section>

            <section v-if="corrections.length" class="space-y-3" aria-labelledby="corrections-heading">
                <h2 id="corrections-heading" class="text-lg font-semibold text-slate-900 dark:text-white">Your suggested fixes</h2>
                <ul class="space-y-3">
                    <li v-for="correction in corrections" :key="correction.id" class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <Link v-if="correction.lodge.slug" :href="route('lodges.show', correction.lodge.slug)" class="font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ correction.lodge.name }}</Link>
                            <span v-else class="font-semibold text-slate-900 dark:text-white">{{ correction.lodge.name }}</span>
                            <Badge :variant="CORRECTION_STATUS[correction.status]?.variant ?? 'neutral'">{{ CORRECTION_STATUS[correction.status]?.label ?? correction.status }}</Badge>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sent {{ correction.created_at }}<span v-if="correction.what.length"> · {{ correction.what.join(', ') }}</span></p>
                        <p v-if="correction.decision_note" class="mt-2 text-sm text-slate-700 dark:text-slate-300">{{ correction.decision_note }}</p>
                    </li>
                </ul>
            </section>

            <p v-if="lodges.length" class="text-xs text-slate-500 dark:text-slate-400">{{ lodges.length }} of {{ limit }} lodges followed.</p>
        </div>
    </MembersLayout>
</template>
