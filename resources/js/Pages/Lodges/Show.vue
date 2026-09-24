<script setup>
import { computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ReferenceLinks from '@/Components/ReferenceLinks.vue';
import { formatMeetingDate, describePattern } from '@/Utils/lodgeDates';

const props = defineProps({
    lodge: { type: Object, required: true },
    hall: { type: Object, default: null },
    sharing: { type: Array, default: () => [] },
});

const signedIn = computed(() => Boolean(usePage().props.auth?.user));
const webcalUrl = computed(() => props.lodge.ics_url.replace(/^https?:/, 'webcal:'));

function follow() {
    router.post(route('lodges.follow', props.lodge.slug), {}, { preserveScroll: true });
}

function unfollow() {
    router.delete(route('lodges.unfollow', props.lodge.slug), { preserveScroll: true });
}

const request = reactive({ home_lodge_name: '', home_lodge_number: '', rank: '', message: '' });

function requestAccess() {
    router.post(route('lodges.visitor_access', props.lodge.slug), { ...request }, { preserveScroll: true });
}

function withdrawRequest() {
    router.delete(route('lodges.visitor_access.withdraw', props.lodge.slug), { preserveScroll: true });
}

function setNotify(event) {
    router.patch(route('lodges.follow.update', props.lodge.slug), { notify_summons: event.target.checked }, { preserveScroll: true });
}

function subscribe(bulletin) {
    router.post(route('directory.subscribe', { clubSlug: props.lodge.club_slug, typeId: bulletin.id }), {}, { preserveScroll: true });
}

function setCalendar(event) {
    router.patch(route('lodges.follow.update', props.lodge.slug), { in_calendar: event.target.checked }, { preserveScroll: true });
}
</script>

<template>
    <PublicLayout :title="lodge.name" :description="`${lodge.name}${lodge.number ? ' No. ' + lodge.number : ''}: where and when it meets.`">
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div>
                    <Link :href="route('lodges.index')" class="text-sm text-blue-600 hover:underline dark:text-blue-400">← All lodges</Link>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold tracking-tight">{{ lodge.name }}</h1>
                        <Badge v-if="lodge.is_managed" variant="success">On ClubManager</Badge>
                    </div>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ lodge.order }}<span v-if="lodge.number"> · No. {{ lodge.number }}</span><span v-if="lodge.province"> · {{ lodge.province }}</span>
                    </p>
                    <p v-if="lodge.description" class="mt-3 text-sm">{{ lodge.description }}</p>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <template v-if="signedIn">
                            <button v-if="!lodge.following" type="button" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500" @click="follow">Follow this lodge</button>
                            <template v-else>
                                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" @click="unfollow">Following ✓ Unfollow</button>
                                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" :checked="lodge.in_calendar" @change="setCalendar" /> Show in my calendar
                                </label>
                                <label v-if="lodge.is_managed" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" :checked="lodge.notify_summons" @change="setNotify" /> Tell me when they publish a summons
                                </label>
                            </template>
                        </template>
                        <Link v-else :href="route('login')" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">Sign in to follow this lodge</Link>
                    </div>
                </div>

                <section class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">When it meets</h2>

                    <ul v-if="lodge.schedules.length" class="mt-3 space-y-1 text-sm">
                        <li v-for="(schedule, index) in lodge.schedules" :key="index" class="font-medium">
                            {{ describePattern(schedule) }}<span v-if="schedule.start_time" class="font-normal text-slate-500 dark:text-slate-400"> · from {{ schedule.start_time }}</span>
                        </li>
                    </ul>

                    <p v-if="lodge.meets_text" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700 dark:bg-slate-800/60 dark:text-slate-300">
                        <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">As listed by the province</span><br />
                        {{ lodge.meets_text }}
                    </p>
                    <p v-if="lodge.installation_month" class="mt-2 text-sm text-slate-600 dark:text-slate-400">Installation meeting: the usual meeting in {{ lodge.installation_month }}.</p>
                    <p v-if="!lodge.meets_text && !lodge.schedules.length" class="mt-3 text-sm text-slate-500">Meeting times have not been listed yet.</p>

                    <div v-if="lodge.confirmed.length" class="mt-5">
                        <h3 class="text-sm font-medium">Confirmed meetings</h3>
                        <ul class="mt-2 space-y-3">
                            <li v-for="meeting in lodge.confirmed" :key="meeting.date" class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-3 text-sm dark:border-emerald-900/50 dark:bg-emerald-950/20">
                                <p class="flex flex-wrap items-center gap-2 font-semibold">
                                    {{ formatMeetingDate(meeting.date, meeting.time) }}
                                    <Badge v-if="meeting.installation" variant="warning">Installation</Badge>
                                    <Badge variant="success">Published by the lodge</Badge>
                                </p>
                                <p v-if="meeting.title" class="text-slate-600 dark:text-slate-300">{{ meeting.title }}</p>
                                <dl class="mt-1 grid gap-x-4 gap-y-0.5 text-xs text-slate-600 dark:text-slate-400 sm:grid-cols-2">
                                    <div v-if="meeting.venue"><dt class="inline font-medium">Venue: </dt><dd class="inline">{{ meeting.venue }}</dd></div>
                                    <div v-if="meeting.dress_code"><dt class="inline font-medium">Dress: </dt><dd class="inline">{{ meeting.dress_code }}</dd></div>
                                    <div v-if="meeting.festive_board.theme"><dt class="inline font-medium">Festive board: </dt><dd class="inline">{{ meeting.festive_board.theme }}</dd></div>
                                    <div v-if="meeting.festive_board.guest_cost"><dt class="inline font-medium">Visitor's dining cost: </dt><dd class="inline">{{ meeting.festive_board.guest_cost }}</dd></div>
                                </dl>
                                <p v-if="meeting.festive_board.menu" class="mt-1 whitespace-pre-wrap text-xs text-slate-500 dark:text-slate-400">{{ meeting.festive_board.menu }}</p>
                            </li>
                        </ul>
                    </div>

                    <div v-if="lodge.visitor && !lodge.visitor.can_see" class="mt-5 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800/60">
                        <p><strong>This lodge shares its summonses with: {{ lodge.visitor.label }}.</strong></p>
                        <template v-if="lodge.visitor.visibility === 'approved_visitors'">
                            <p v-if="lodge.visitor.request_status === 'pending'" class="mt-1">
                                Your request is waiting for the lodge.
                                <button type="button" class="text-blue-600 hover:underline dark:text-blue-400" @click="withdrawRequest">Withdraw it</button>
                            </p>
                            <p v-else-if="lodge.visitor.request_status === 'declined' || lodge.visitor.request_status === 'revoked'" class="mt-1 text-slate-500">The lodge did not approve your last request.</p>
                            <form v-if="lodge.visitor.can_request" class="mt-3 space-y-2" @submit.prevent="requestAccess">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tell the lodge who you are. The secretary decides.</p>
                                <input v-model="request.home_lodge_name" required placeholder="Your lodge" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                                <div class="grid grid-cols-2 gap-2">
                                    <input v-model="request.home_lodge_number" placeholder="Its number" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                                    <input v-model="request.rank" placeholder="Your rank" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                                </div>
                                <textarea v-model="request.message" rows="2" placeholder="Anything else (optional)" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" />
                                <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-500">Ask to receive their summonses</button>
                            </form>
                            <p v-else-if="!signedIn" class="mt-1"><Link :href="route('login')" class="text-blue-600 hover:underline dark:text-blue-400">Sign in</Link> to ask to receive their summonses.</p>
                        </template>
                    </div>

                    <div v-if="lodge.upcoming.length" class="mt-5">
                        <h3 class="text-sm font-medium">{{ lodge.confirmed.length ? 'Further expected meetings' : 'Next expected meetings' }}</h3>
                        <ul class="mt-2 divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <li v-for="meeting in lodge.upcoming" :key="meeting.date" class="flex items-center gap-2 py-1.5">
                                {{ formatMeetingDate(meeting.date, meeting.time) }}
                                <Badge v-if="meeting.installation" variant="warning">Installation</Badge>
                            </li>
                        </ul>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Worked out from the pattern above, so a date may move. Confirm with the lodge before you visit.</p>
                        <p class="mt-2 text-sm">
                            <a :href="webcalUrl" class="text-blue-600 hover:underline dark:text-blue-400">Subscribe in your calendar app</a>
                            <span class="text-slate-400"> · </span>
                            <a :href="lodge.ics_url" class="text-blue-600 hover:underline dark:text-blue-400">Download .ics</a>
                        </p>
                    </div>
                </section>

                <section v-if="lodge.is_managed && (lodge.news.length || lodge.bulletins.length)" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">News from the lodge</h2>
                        <a v-if="lodge.feed_url" :href="lodge.feed_url" class="text-xs text-blue-600 hover:underline dark:text-blue-400">RSS feed</a>
                    </div>
                    <ul v-if="lodge.news.length" class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                        <li v-for="post in lodge.news" :key="post.id" class="py-2.5">
                            <a :href="post.url" class="font-medium hover:underline">{{ post.title }}</a>
                            <span class="text-xs text-slate-500 dark:text-slate-400"> · {{ post.date }}</span>
                            <p v-if="post.excerpt" class="text-sm text-slate-600 dark:text-slate-400">{{ post.excerpt }}</p>
                        </li>
                    </ul>
                    <p v-if="lodge.news.length && !signedIn" class="mt-2 text-xs text-slate-500">Sign in to read the articles.</p>

                    <div v-if="lodge.bulletins.length" class="mt-4 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <h3 class="text-sm font-medium">Bulletins you can subscribe to</h3>
                        <ul class="mt-2 space-y-2">
                            <li v-for="bulletin in lodge.bulletins" :key="bulletin.id" class="flex items-center justify-between gap-3 text-sm">
                                <span><strong>{{ bulletin.name }}</strong><span v-if="bulletin.description" class="text-slate-500 dark:text-slate-400"> · {{ bulletin.description }}</span></span>
                                <button v-if="signedIn" type="button" class="shrink-0 rounded-lg border border-blue-600 px-3 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40" @click="subscribe(bulletin)">{{ bulletin.needs_approval ? 'Ask to subscribe' : 'Subscribe' }}</button>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-if="sharing.length" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Also meeting at {{ hall.name }}</h2>
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        <li v-for="other in sharing" :key="other.slug" class="text-sm">
                            <Link :href="route('lodges.show', other.slug)" class="font-medium hover:underline">{{ other.name }}</Link>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ other.order }}<span v-if="other.number"> · No. {{ other.number }}</span></span>
                        </li>
                    </ul>
                </section>
            </div>

            <aside class="space-y-4">
                <section v-if="hall" class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Where</h2>
                    <Link :href="route('lodges.hall', hall.slug)" class="mt-3 block text-base font-semibold hover:underline">{{ hall.name }}</Link>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ hall.kind }}</p>
                    <p class="mt-2 text-sm">{{ hall.address }}</p>
                    <a v-if="hall.map_url" :href="hall.map_url" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400">Open in maps ↗</a>
                </section>

                <section v-if="lodge.can_claim" class="rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Is this your lodge?</h2>
                    <p v-if="lodge.claim && ['pending', 'more_info'].includes(lodge.claim.status)" class="mt-2">
                        Your claim is <strong>{{ lodge.claim.status === 'more_info' ? 'waiting for your reply' : 'being checked' }}</strong>.
                        <Link :href="route('members.lodges')" class="text-blue-600 hover:underline dark:text-blue-400">See where it stands</Link>
                    </p>
                    <template v-else>
                        <p class="mt-2 text-slate-600 dark:text-slate-400">Officers can ask to manage this listing, keep the details right, and run the lodge's meetings and members here.</p>
                        <Link v-if="signedIn" :href="route('lodges.claim', lodge.slug)" class="mt-3 inline-block rounded-lg border border-blue-600 px-3 py-1.5 font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">Claim this lodge</Link>
                        <Link v-else :href="route('login')" class="mt-3 inline-block rounded-lg border border-blue-600 px-3 py-1.5 font-medium text-blue-600 hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-950/40">Sign in to claim this lodge</Link>
                    </template>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 text-sm dark:border-slate-800 dark:bg-slate-900">
                    <a v-if="lodge.website_url" :href="lodge.website_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">Lodge website ↗</a>
                </section>

                <ReferenceLinks :groups="lodge.references" :checked-on="lodge.last_verified_at" />
            </aside>
        </div>
    </PublicLayout>
</template>
