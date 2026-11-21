<script setup>
import { ref, computed } from 'vue';
import { Link, Deferred, usePage } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChip from '@/Components/Ui/ClubChip.vue';
import DateTile from '@/Components/Ui/DateTile.vue';
import QuickReply from '@/Components/QuickReply.vue';
import InstallTip from '@/Components/InstallTip.vue';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
    clubs: { type: Array, default: () => [] },
    pendingClubs: { type: Array, default: () => [] },
    inbox: { type: Array, default: () => [] },
    upNext: { type: Array, default: () => [] },
    feed: { type: Array, default: undefined },
});

const page = usePage();
const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0]);

const greeting = computed(() => {
    const hour = new Date().getHours();

    return hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
});

const TYPES = {
    news: { label: 'News', variant: 'info' },
    event: { label: 'Event', variant: 'success' },
    update: { label: 'Update', variant: 'neutral' },
    newsletter: { label: 'Newsletter', variant: 'neutral' },
};

const REPLIES = {
    tentative: 'Maybe',
    declined: 'Not going',
    waitlisted: 'Waiting list',
    apologies: 'Apologies',
};

const ATTENDING = ['attending', 'attending_dining', 'attending_meeting_only'];

// The chip beside each coming-up item: what is owed first, then the member's reply, then an invitation to answer.
const status = (item) => {
    if (item.owes && item.said_paid) return { label: 'Payment sent', variant: 'info' };
    if (item.owes) return { label: 'Unpaid', variant: 'warning' };
    if (ATTENDING.includes(item.reply)) return { label: 'Attending', variant: 'success' };
    if (item.reply) return { label: REPLIES[item.reply] ?? item.reply, variant: 'neutral' };
    if (item.closed) return null;

    return { label: item.type === 'event' ? 'Book' : 'Reply', variant: 'info' };
};

// The soonest booking that still owes money, shown on its own above everything else.
// Not one they have said they paid by bank transfer: that waits for the Treasurer to see it in the bank.
const nextBooking = computed(() => props.upNext.find((item) => item.owes && !item.said_paid) ?? null);

// Its dining payment is already the highlighted card, so it is not listed again.
const attention = computed(() => props.inbox.filter((item) => !(item.kind === 'dining_payment' && item.url === nextBooking.value?.url)));

const subtitle = computed(() => (props.clubs.length === 1 ? props.clubs[0].name : props.clubs.length ? `You belong to ${props.clubs.length} lodges` : 'You are not a member of any lodge yet'));

const clubFilter = ref('all');
const typeFilter = ref('all');

const filteredFeed = computed(() => (props.feed ?? []).filter((item) => (
    (clubFilter.value === 'all' || item.club.slug === clubFilter.value)
    && (typeFilter.value === 'all' || item.type === typeFilter.value)
)));

const feedTypes = computed(() => [...new Set((props.feed ?? []).map((item) => item.type))]);

const rtf = new Intl.RelativeTimeFormat('en-GB', { numeric: 'auto' });

const ago = (iso) => {
    const minutes = Math.round((new Date(iso) - new Date()) / 60000);
    const abs = Math.abs(minutes);

    if (abs < 60) return rtf.format(minutes, 'minute');
    if (abs < 60 * 24) return rtf.format(Math.round(minutes / 60), 'hour');
    if (abs < 60 * 24 * 30) return rtf.format(Math.round(minutes / 60 / 24), 'day');

    return new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const dayParts = (iso) => {
    const date = new Date(iso);

    return {
        weekday: date.toLocaleDateString('en-GB', { weekday: 'long' }),
        long: date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }),
        time: date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
    };
};

const company = (guests) => (guests ? `you and ${guests} ${guests === 1 ? 'guest' : 'guests'}` : 'just you');

const KIND_LABELS = { meeting_rsvp: 'Summons', dining_payment: 'Payment', dues: 'Dues', event_rsvp: 'Event', approvals: 'Admin', signature: 'Sign' };
const severityVariant = (severity) => (severity >= 3 ? 'danger' : severity === 2 ? 'warning' : 'info');

const roleLabel = (role) => role.charAt(0).toUpperCase() + role.slice(1);
const chip = (active) => ['inline-flex min-h-11 shrink-0 items-center rounded-full border px-3.5 text-xs font-medium transition-colors sm:min-h-8', active ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'];
</script>

<template>
    <MembersLayout title="Home" active-tab="dashboard">
        <div class="space-y-4 sm:space-y-6">
            <div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
                <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ greeting }}, {{ firstName }}</h2>
            </div>

            <InstallTip />

            <section v-if="nextBooking" aria-labelledby="next-booking-heading" class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-800/40 dark:bg-amber-950/30 lg:max-w-xl">
                <div class="flex items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <h3 id="next-booking-heading" class="text-sm font-semibold text-slate-900 dark:text-white">{{ nextBooking.title }}</h3>
                        <p class="text-xs text-slate-700 dark:text-slate-300">{{ dayParts(nextBooking.at).long }} · {{ company(nextBooking.guests) }}<template v-if="clubs.length > 1"> · {{ nextBooking.club_name }}</template></p>
                    </div>
                    <Badge variant="warning">Payment due</Badge>
                </div>
                <Button block :href="nextBooking.url">Pay {{ nextBooking.owes }}</Button>
            </section>

            <Card v-if="attention.length" padding="none" aria-labelledby="inbox-heading">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h3 id="inbox-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Needs your attention ({{ attention.length }})</h3>
                </div>
                <ul>
                    <li v-for="item in attention" :key="`${item.kind}-${item.club.slug}-${item.title}`" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <Badge :variant="severityVariant(item.severity)">{{ KIND_LABELS[item.kind] ?? 'Action' }}</Badge>
                            <ClubChip :name="item.club.name" :colour="item.club.colour" />
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.detail }}</p>
                            </div>
                            <QuickReply v-if="item.reply" :item="{ ...item.reply, reply: null, simple: true }" />
                            <Button v-else size="sm" variant="secondary" :href="item.url">Open</Button>
                        </div>
                    </li>
                </ul>
            </Card>

            <Alert v-if="pendingClubs.length" variant="info" title="Waiting for approval">
                Your membership of {{ pendingClubs.map((club) => club.name).join(', ') }} is waiting for a club admin to approve it.
            </Alert>

            <Card v-if="!clubs.length && !pendingClubs.length">
                <div class="py-6 text-center">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Find your lodge</h3>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">Browse the directory to see public meetings and events, and ask to join a lodge.</p>
                    <Button class="mt-4" :href="route('lodges.index')">Open the directory</Button>
                </div>
            </Card>

            <!-- Phones read top to bottom: coming up, club news, clubs. Wide screens put the news on the left. -->
            <div v-else class="grid gap-4 sm:gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] lg:grid-rows-[auto_1fr] lg:items-start">
                <section aria-labelledby="next-heading" class="min-w-0 space-y-2 lg:col-start-2 lg:row-start-1">
                    <h3 id="next-heading" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Coming up</h3>
                    <Card padding="sm">
                        <ul v-if="upNext.length" class="-mt-3">
                            <li v-for="item in upNext" :key="`${item.type}-${item.id}`" class="border-t border-slate-200 first:border-t-0 dark:border-slate-800">
                                <Link :href="item.url" class="flex min-h-11 items-center gap-3 py-3">
                                    <DateTile :date="item.at" :colour="item.club_colour" size="sm" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ item.title }}</span>
                                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ dayParts(item.at).weekday }}, {{ dayParts(item.at).time }}<template v-if="item.where"> · {{ item.where }}</template><template v-if="clubs.length > 1"> · {{ item.club_name }}</template></span>
                                    </span>
                                    <Badge v-if="status(item)" :variant="status(item).variant">{{ status(item).label }}</Badge>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No meetings or events coming up.</p>
                        <Link :href="route('members.calendar')" class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400">See the full calendar</Link>
                    </Card>
                </section>

                <section aria-labelledby="feed-heading" class="min-w-0 space-y-2 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                    <h3 id="feed-heading" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">From your lodges</h3>

                    <div v-if="clubs.length > 1" class="flex flex-wrap gap-2">
                        <button v-for="option in [{ slug: 'all', name: 'All lodges' }, ...clubs]" :key="option.slug" type="button" :class="chip(clubFilter === option.slug)" :aria-pressed="clubFilter === option.slug" @click="clubFilter = option.slug">{{ option.name }}</button>
                    </div>

                    <div v-if="feedTypes.length > 1" class="flex flex-wrap gap-2">
                        <button v-for="type in ['all', ...feedTypes]" :key="type" type="button" :class="chip(typeFilter === type)" :aria-pressed="typeFilter === type" @click="typeFilter = type">{{ type === 'all' ? 'Everything' : TYPES[type]?.label }}</button>
                    </div>

                    <Deferred data="feed">
                        <template #fallback>
                            <Card padding="none">
                                <div v-for="n in 3" :key="n" class="flex animate-pulse gap-3 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                                    <div class="flex-1 space-y-2"><div class="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-800" /><div class="h-3 w-1/3 rounded bg-slate-200 dark:bg-slate-800" /></div>
                                </div>
                            </Card>
                        </template>

                        <!-- Each item is a card of its own, like the club page's broadcasts. -->
                        <ul v-if="filteredFeed.length" class="space-y-3">
                            <li v-for="item in filteredFeed" :key="item.key">
                                <Link :href="route(item.link.name, item.link.params)" class="block min-h-11 space-y-1 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition-colors hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700">
                                    <span class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                        <span :class="['h-2 w-2 shrink-0 rounded-full', orderColour(item.club.colour).dot]" aria-hidden="true" />
                                        <span class="min-w-0 flex-1 truncate">{{ TYPES[item.type]?.label ?? item.type }} · {{ item.club.name }} · <time :datetime="item.at">{{ ago(item.at) }}</time></span>
                                        <Badge v-if="item.important" variant="danger">Important</Badge>
                                    </span>
                                    <span class="block text-[15px] font-semibold text-slate-900 dark:text-white">{{ item.title }}</span>
                                    <span v-if="item.excerpt" class="line-clamp-2 block text-sm text-slate-600 dark:text-slate-300">{{ item.excerpt }}</span>
                                </Link>
                            </li>
                        </ul>

                        <Card v-else padding="sm">
                            <p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">{{ (feed ?? []).length ? 'Nothing matches those filters.' : 'Nothing new yet. News and events from your lodges will show up here.' }}</p>
                        </Card>
                    </Deferred>
                </section>

                <section aria-labelledby="clubs-heading" class="min-w-0 space-y-2 lg:col-start-2 lg:row-start-2">
                    <h3 id="clubs-heading" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Your lodges</h3>
                    <Card padding="none">
                        <ul>
                            <li v-for="club in clubs" :key="club.id" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 truncate text-sm font-semibold text-slate-900 dark:text-white"><span :class="['h-2 w-2 shrink-0 rounded-full', orderColour(club.colour).dot]" aria-hidden="true" />{{ club.name }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400"><template v-if="club.type_name">{{ club.type_name }}</template><template v-if="club.member_number"> · {{ club.member_number }}</template></p>
                                    </div>
                                    <Badge :variant="club.is_staff ? 'info' : 'neutral'">{{ roleLabel(club.role) }}</Badge>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Button size="sm" variant="secondary" :href="route('member.dashboard', { slug: club.slug })">Open</Button>
                                    <Button v-if="club.is_staff" size="sm" variant="secondary" :href="route('admin.analytics', { slug: club.slug })">Admin</Button>
                                    <Button size="sm" variant="ghost" :href="route('public.site', { clubSlug: club.slug })">Website</Button>
                                </div>
                            </li>
                        </ul>
                    </Card>
                </section>
            </div>
        </div>
    </MembersLayout>
</template>
