<script setup>
import { ref, computed } from 'vue';
import { Link, Deferred, usePage } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import QuickReply from '@/Components/QuickReply.vue';

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
    attending: 'Going',
    tentative: 'Maybe',
    declined: 'Not going',
    attending_dining: 'Going, dining',
    attending_meeting_only: 'Going',
    apologies: 'Apologies',
};

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
        month: date.toLocaleDateString('en-GB', { month: 'short' }),
        day: date.getDate(),
        time: date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }),
    };
};

const KIND_LABELS = { meeting_rsvp: 'Summons', dining_payment: 'Payment', dues: 'Dues', event_rsvp: 'Event', approvals: 'Admin' };
const severityVariant = (severity) => (severity >= 3 ? 'danger' : severity === 2 ? 'warning' : 'info');

const roleLabel = (role) => role.charAt(0).toUpperCase() + role.slice(1);
const chip = (active) => ['rounded-full border px-3 py-1 text-xs font-medium transition-colors', active ? 'border-blue-300 bg-blue-50 text-blue-700 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300' : 'border-slate-300 text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'];
</script>

<template>
    <MembersLayout title="Dashboard" active-tab="dashboard">
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ greeting }}, {{ firstName }}</h1>
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                        <template v-if="clubs.length">You belong to {{ clubs.length }} {{ clubs.length === 1 ? 'club' : 'clubs' }}.</template>
                        <template v-else>You are not a member of any club yet.</template>
                    </p>
                </div>
            </div>

            <Card v-if="inbox.length" padding="none" aria-labelledby="inbox-heading">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                    <h2 id="inbox-heading" class="text-sm font-semibold text-slate-900 dark:text-white">Needs your attention ({{ inbox.length }})</h2>
                </div>
                <ul>
                    <li v-for="item in inbox" :key="`${item.kind}-${item.club.slug}-${item.title}`" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <Badge :variant="severityVariant(item.severity)">{{ KIND_LABELS[item.kind] ?? 'Action' }}</Badge>
                            <span>{{ item.club.name }}</span>
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
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Find your club</h2>
                    <p class="mx-auto mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">Browse the directory to see public meetings and events, and ask to join a club.</p>
                    <Button class="mt-4" :href="route('directory.index')">Open the directory</Button>
                </div>
            </Card>

            <div v-else class="grid gap-6 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)]">
                <section aria-labelledby="feed-heading" class="min-w-0 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 id="feed-heading" class="mr-2 text-sm font-medium text-slate-500 dark:text-slate-400">From your clubs</h2>
                        <button v-for="option in [{ slug: 'all', name: 'All clubs' }, ...clubs]" :key="option.slug" type="button" :class="chip(clubFilter === option.slug)" @click="clubFilter = option.slug">{{ option.name }}</button>
                    </div>

                    <div v-if="feedTypes.length > 1" class="flex flex-wrap gap-2">
                        <button v-for="type in ['all', ...feedTypes]" :key="type" type="button" :class="chip(typeFilter === type)" @click="typeFilter = type">{{ type === 'all' ? 'Everything' : TYPES[type]?.label }}</button>
                    </div>

                    <Deferred data="feed">
                        <template #fallback>
                            <Card padding="none">
                                <div v-for="n in 3" :key="n" class="flex animate-pulse gap-3 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                                    <div class="h-8 w-8 shrink-0 rounded-lg bg-slate-200 dark:bg-slate-800" />
                                    <div class="flex-1 space-y-2"><div class="h-3 w-1/3 rounded bg-slate-200 dark:bg-slate-800" /><div class="h-4 w-2/3 rounded bg-slate-200 dark:bg-slate-800" /><div class="h-3 w-full rounded bg-slate-200 dark:bg-slate-800" /></div>
                                </div>
                            </Card>
                        </template>

                        <Card v-if="filteredFeed.length" padding="none">
                            <ul>
                                <li v-for="item in filteredFeed" :key="item.key" class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                        <Badge v-if="item.important" variant="danger">Important</Badge>
                                        <Badge :variant="TYPES[item.type]?.variant ?? 'neutral'">{{ TYPES[item.type]?.label ?? item.type }}</Badge>
                                        <span>{{ item.club.name }}</span>
                                        <span aria-hidden="true">·</span>
                                        <time :datetime="item.at">{{ ago(item.at) }}</time>
                                    </div>
                                    <Link :href="route(item.link.name, item.link.params)" class="mt-1.5 block text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ item.title }}</Link>
                                    <p v-if="item.excerpt" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ item.excerpt }}</p>
                                </li>
                            </ul>
                        </Card>

                        <Card v-else>
                            <p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">{{ (feed ?? []).length ? 'Nothing matches those filters.' : 'Nothing new yet. News and events from your clubs will show up here.' }}</p>
                        </Card>
                    </Deferred>
                </section>

                <aside class="min-w-0 space-y-6">
                    <section aria-labelledby="next-heading" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h2 id="next-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Up next</h2>
                            <Link :href="route('members.calendar')" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Open calendar</Link>
                        </div>
                        <Card v-if="upNext.length" padding="none">
                            <ul>
                                <li v-for="item in upNext" :key="`${item.type}-${item.club_slug}-${item.title}-${item.at}`" class="flex gap-3 border-b border-slate-200 p-3 last:border-b-0 dark:border-slate-800">
                                    <div class="w-10 shrink-0 text-center leading-tight">
                                        <div class="text-[11px] uppercase text-slate-500 dark:text-slate-400">{{ dayParts(item.at).month }}</div>
                                        <div class="text-lg font-semibold text-slate-900 dark:text-white">{{ dayParts(item.at).day }}</div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ item.title }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ item.club_name }} · {{ dayParts(item.at).time }}<template v-if="item.where"> · {{ item.where }}</template></p>
                                        <Badge v-if="item.reply" class="mt-1.5" variant="success">{{ REPLIES[item.reply] ?? item.reply }}</Badge>
                                    </div>
                                </li>
                            </ul>
                        </Card>
                        <Card v-else><p class="text-sm text-slate-500 dark:text-slate-400">No meetings or events coming up.</p></Card>
                    </section>

                    <section aria-labelledby="clubs-heading" class="space-y-2">
                        <h2 id="clubs-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Your clubs</h2>
                        <Card padding="none">
                            <ul>
                                <li v-for="club in clubs" :key="club.id" class="space-y-2 border-b border-slate-200 p-3 last:border-b-0 dark:border-slate-800">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ club.name }}</p>
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
                </aside>
            </div>
        </div>
    </MembersLayout>
</template>
