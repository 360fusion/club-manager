<script setup>
import { useClipboard } from '@/Composables/useClipboard';
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import QuickReply from '@/Components/QuickReply.vue';
import { orderColour } from '@/Utils/orderColour';
import { localDate } from '@/Utils/localDateTime';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    month: String,
    monthLabel: String,
    gridStart: String,
    gridEnd: String,
    items: { type: Array, default: () => [] },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
    feedUrl: String,
});

const urlScoped = computed(() => Boolean(route().params.slug));
const routeName = computed(() => (urlScoped.value ? 'member.calendar' : 'members.calendar'));
const routeParams = computed(() => (urlScoped.value ? { slug: props.scopeClub.slug } : {}));
const clubQuery = computed(() => (urlScoped.value ? {} : { club: props.scopeClub?.slug }));

const colourFor = (colour) => orderColour(colour).soft;

const dateKey = (date) => localDate(new Date(date));
const fromKey = (key) => new Date(`${key}T00:00:00`);
const addDays = (key, count) => {
    const date = fromKey(key);
    date.setDate(date.getDate() + count);

    return localDate(date);
};
const mondayOf = (key) => addDays(key, -((fromKey(key).getDay() + 6) % 7));

const days = computed(() => {
    const out = [];

    for (let key = props.gridStart; key <= props.gridEnd; key = addDays(key, 1)) {
        out.push({ key, day: fromKey(key).getDate(), inMonth: key.startsWith(props.month) });
    }

    return out;
});

const byDay = computed(() => props.items.reduce((acc, item) => {
    (acc[dateKey(item.start)] ??= []).push(item);

    return acc;
}, {}));

const today = localDate();
const selected = ref(null);

// The week the strip shows: the one asked for when the week arrows crossed into this month, else this week when it is in
// the month, else the month's first week.
const requestedDay = typeof window !== 'undefined' ? new URLSearchParams(window.location.search).get('day') : null;
const inGrid = (key) => /^\d{4}-\d{2}-\d{2}$/.test(key ?? '') && key >= props.gridStart && key <= props.gridEnd;
const startDay = inGrid(requestedDay) ? requestedDay : (today.startsWith(props.month) ? today : `${props.month}-01`);
const weekStart = ref(mondayOf(startDay));
const weekEnd = computed(() => addDays(weekStart.value, 6));
const week = computed(() => Array.from({ length: 7 }, (_, index) => {
    const key = addDays(weekStart.value, index);

    return { key, day: fromKey(key).getDate(), letter: 'MTWTFSS'[index], items: byDay.value[key] ?? [] };
}));

const shortDate = (key, options = {}) => fromKey(key).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', ...options });
const weekLabel = computed(() => `${shortDate(weekStart.value)} – ${shortDate(weekEnd.value, { year: 'numeric' })}`);

const shiftWeek = (delta) => {
    const next = addDays(weekStart.value, delta * 7);
    selected.value = null;

    if (next >= props.gridStart && addDays(next, 6) <= props.gridEnd) {
        weekStart.value = next;

        return;
    }

    // The week is outside the dates loaded for this month, so load the month it starts in.
    router.get(route(routeName.value, routeParams.value), { month: next.slice(0, 7), day: next, ...clubQuery.value }, { preserveScroll: true });
};

const selectDay = (key) => {
    selected.value = selected.value === key ? null : key;
    weekStart.value = mondayOf(key);
};

const longDate = (iso) => new Date(iso).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });

const monthEnd = computed(() => {
    const [year, month] = props.month.split('-').map(Number);

    return localDate(new Date(year, month, 0));
});

const sections = computed(() => {
    if (selected.value) {
        return [{ key: 'day', heading: longDate(fromKey(selected.value)), items: byDay.value[selected.value] ?? [], empty: 'Nothing on that day.' }];
    }

    const inWeek = props.items.filter((item) => dateKey(item.start) >= weekStart.value && dateKey(item.start) <= weekEnd.value);
    const later = props.items.filter((item) => dateKey(item.start) > weekEnd.value && dateKey(item.start).startsWith(props.month));
    const thisWeek = today >= weekStart.value && today <= weekEnd.value;

    return [
        { key: 'week', heading: thisWeek ? 'This week' : `Week of ${shortDate(weekStart.value)}`, items: inWeek, empty: 'Nothing on this week.' },
        ...(weekEnd.value < monthEnd.value ? [{ key: 'later', heading: `Later in ${props.monthLabel.split(' ')[0]}`, items: later, empty: 'Nothing else this month.' }] : []),
    ];
});

const shiftMonth = (delta) => {
    const [year, month] = props.month.split('-').map(Number);
    const next = new Date(year, month - 1 + delta, 1);

    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
};

const time = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
const weekday = (iso) => new Date(iso).toLocaleDateString('en-GB', { weekday: 'short' });

const STATUS = {
    attending_dining: ['Attending, dining', 'success'],
    attending_meeting_only: ['Attending', 'success'],
    attending: ['Attending', 'success'],
    tentative: ['Maybe', 'info'],
    waitlisted: ['Waiting list', 'neutral'],
    apologies: ['Apologies', 'neutral'],
    declined: ['Not going', 'neutral'],
};

// Unpaid comes first: it is the one thing still to do once someone has said they are coming.
const statusFor = (item) => {
    if (item.unpaid && item.said_paid) return { label: 'Payment sent', variant: 'info' };
    if (item.unpaid) return { label: 'Unpaid', variant: 'warning' };
    if (item.type === 'lodge' || item.type === 'passed_on') return null;
    if (STATUS[item.reply]) return { label: STATUS[item.reply][0], variant: STATUS[item.reply][1] };

    // Meetings still take replies after dining closes.
    if (item.reply || (item.type === 'event' && item.closed)) return null;

    // An event with tickets or a menu is booked on its own page.
    return { label: item.type === 'event' && !item.simple ? 'Book' : 'Reply', variant: 'info' };
};

const { copied, copy: copyText } = useClipboard();
// Where the browser refuses, the link stays in the field to select by hand.
const copy = () => copyText(props.feedUrl);

const replaceLink = () => {
    if (confirm('Replace your calendar link? Calendars using the old link will stop updating.')) {
        router.post(route('members.calendar.regenerate'), {}, { preserveScroll: true });
    }
};

const webcal = computed(() => props.feedUrl.replace(/^https?:/, 'webcal:'));

const chip = (active) => ['inline-flex min-h-11 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-3.5 text-sm font-medium transition-colors sm:min-h-9', active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800'];
</script>

<template>
    <MembersLayout title="Calendar" :club="urlScoped ? scopeClub : null" active-tab="calendar">
        <div class="space-y-4">
            <div class="flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ scopeClub?.name ?? 'All your lodges' }}</p>
                    <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Calendar</h2>
                </div>
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="secondary" :href="route(routeName, routeParams)">Today</Button>
                    <div class="hidden items-center gap-2 sm:flex">
                        <Link :href="route(routeName, routeParams)" :data="{ month: shiftMonth(-1), ...clubQuery }" class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Previous month" preserve-scroll>‹</Link>
                        <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-900 dark:text-white">{{ monthLabel }}</span>
                        <Link :href="route(routeName, routeParams)" :data="{ month: shiftMonth(1), ...clubQuery }" class="rounded-xl border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Next month" preserve-scroll>›</Link>
                    </div>
                </div>
            </div>

            <!-- On /members/{club}/… the club is fixed by the URL, so there is nothing to choose. -->
            <div v-if="!urlScoped && clubOptions.length > 1" class="flex flex-wrap gap-2" role="group" aria-label="Filter by lodge">
                <Link :href="route('members.calendar')" :data="{ month }" :class="chip(!scopeClub)" :aria-current="!scopeClub ? 'true' : undefined" preserve-scroll>All</Link>
                <Link v-for="club in clubOptions" :key="club.slug" :href="route('members.calendar')" :data="{ club: club.slug, month }" :class="chip(scopeClub?.slug === club.slug)" :aria-current="scopeClub?.slug === club.slug ? 'true' : undefined" preserve-scroll>
                    <span :class="['h-2 w-2 rounded-full', orderColour(club.colour).dot]" aria-hidden="true" />{{ club.name }}
                </Link>
            </div>

            <!-- Phones: one week at a time. -->
            <Card padding="none" class="p-3 sm:hidden">
                <div class="flex items-center justify-between px-1">
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Previous week" @click="shiftWeek(-1)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    </button>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white" aria-live="polite">{{ weekLabel }}</p>
                    <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800" aria-label="Next week" @click="shiftWeek(1)">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6" /></svg>
                    </button>
                </div>
                <div class="mt-2 grid grid-cols-7 gap-1 text-center">
                    <span v-for="(day, index) in week" :key="`letter-${index}`" class="text-[11px] font-semibold text-slate-500 dark:text-slate-400" aria-hidden="true">{{ day.letter }}</span>
                    <button
                        v-for="day in week"
                        :key="day.key"
                        type="button"
                        :class="['flex min-h-11 flex-col items-center gap-1 rounded-xl py-1.5 text-[15px] font-semibold tabular-nums transition-colors', day.key === today ? 'bg-blue-600 text-white' : 'text-slate-900 hover:bg-slate-100 dark:text-white dark:hover:bg-slate-800', selected === day.key ? 'ring-2 ring-blue-500 ring-offset-1 dark:ring-offset-slate-900' : '']"
                        :aria-pressed="selected === day.key"
                        :aria-label="`${longDate(fromKey(day.key))}, ${day.items.length} ${day.items.length === 1 ? 'item' : 'items'}`"
                        @click="selectDay(day.key)"
                    >
                        {{ day.day }}
                        <span class="flex h-1.5 gap-0.5" aria-hidden="true">
                            <span v-for="item in day.items.slice(0, 3)" :key="item.key" :class="['h-1.5 w-1.5 rounded-full', day.key === today ? 'bg-white' : orderColour(item.club.colour).dot]" />
                        </span>
                    </button>
                </div>
            </Card>

            <!-- Wider screens: the month. -->
            <Card padding="none" class="hidden sm:block">
                <div class="grid grid-cols-7 border-b border-slate-200 text-center text-[11px] font-medium uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <div v-for="name in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="name" class="py-2">{{ name }}</div>
                </div>
                <div class="grid grid-cols-7">
                    <button
                        v-for="day in days"
                        :key="day.key"
                        type="button"
                        :class="['min-h-[6rem] space-y-1 border-b border-r border-slate-200 p-1.5 text-left align-top transition-colors last:border-r-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50', day.inMonth ? '' : 'bg-slate-50/60 text-slate-500 dark:text-slate-400 dark:bg-slate-900/40', selected === day.key ? 'ring-2 ring-inset ring-blue-500' : '']"
                        :aria-pressed="selected === day.key"
                        :aria-label="`${day.key}, ${(byDay[day.key] ?? []).length} items`"
                        @click="selectDay(day.key)"
                    >
                        <span :class="['inline-flex h-6 w-6 items-center justify-center rounded-full text-xs', day.key === today ? 'bg-blue-600 font-bold text-white' : '']">{{ day.day }}</span>
                        <span v-for="item in (byDay[day.key] ?? []).slice(0, 3)" :key="item.key" :class="['block truncate rounded px-1 py-0.5 text-[11px] font-medium leading-tight', colourFor(item.club.colour), item.clash ? 'ring-1 ring-amber-500' : '']"><span v-if="item.installation" aria-label="Installation meeting"><UiIcon name="star" :size="10" class="inline-block align-text-bottom" /> </span>{{ item.title }}</span>
                        <span v-if="(byDay[day.key] ?? []).length > 3" class="block text-[10px] text-slate-500">+{{ byDay[day.key].length - 3 }} more</span>
                    </button>
                </div>
            </Card>

            <section v-for="section in sections" :key="section.key" :aria-labelledby="`calendar-${section.key}`" class="space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <h3 :id="`calendar-${section.key}`" class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ section.heading }}</h3>
                    <button v-if="section.key === 'day'" type="button" class="min-h-11 px-2 text-sm font-semibold text-blue-600 hover:underline dark:text-blue-400 sm:min-h-0" @click="selected = null">Show the week</button>
                </div>
                <Card v-if="section.items.length" padding="none">
                    <ul>
                        <li v-for="item in section.items" :key="item.key" class="border-b border-slate-200 last:border-b-0 dark:border-slate-800">
                            <Link :href="item.url" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <span class="w-12 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white text-center dark:border-slate-700 dark:bg-slate-900" aria-hidden="true">
                                    <span :class="['block text-[10px] font-bold uppercase leading-[18px]', orderColour(item.club.colour).tile]">{{ weekday(item.start) }}</span>
                                    <span class="block font-display text-xl font-semibold leading-[30px] text-slate-900 [font-variant-numeric:lining-nums] dark:text-white">{{ new Date(item.start).getDate() }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">
                                        <span class="sr-only">{{ longDate(item.start) }}: </span>
                                        <UiIcon v-if="item.installation" name="star" :size="12" class="mr-0.5 inline-block align-baseline text-amber-500" />{{ item.title }}
                                    </span>
                                    <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ item.all_day ? 'Time not listed' : time(item.start) }}<template v-if="item.where"> · {{ item.where }}</template>
                                    </span>
                                    <span v-if="!scopeClub || item.clash" class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                        <template v-if="!scopeClub">{{ item.club.name }}</template><template v-if="item.clash"><template v-if="!scopeClub"> · </template><span class="font-medium text-amber-700 dark:text-amber-400">Clashes with another item</span></template>
                                    </span>
                                </span>
                                <Badge v-if="statusFor(item)" :variant="statusFor(item).variant" class="shrink-0">{{ statusFor(item).label }}</Badge>
                            </Link>
                            <!-- Wider screens keep the quick reply buttons; on a phone the row opens the meeting or event to reply. -->
                            <div v-if="!['lodge', 'passed_on'].includes(item.type)" class="hidden px-4 pb-3 pl-19 sm:block">
                                <QuickReply :item="{ type: item.type, id: item.id, slug: item.club.slug, reply: item.reply, closed: item.closed, simple: item.simple }" />
                            </div>
                        </li>
                    </ul>
                </Card>
                <Card v-else><p class="py-2 text-center text-sm text-slate-500 dark:text-slate-400">{{ section.empty }}</p></Card>
            </section>

            <a :href="webcal" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-900 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800 sm:hidden">
                <UiIcon name="calendar" :size="18" />Add all to my phone's calendar
            </a>

            <Card>
                <template #header><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Add to your calendar</h3></template>
                <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Subscribe once in Apple Calendar, Google Calendar or Outlook and every meeting and event across your lodges stays up to date.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input :value="feedUrl" readonly class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300" aria-label="Your private calendar link" @focus="$event.target.select()" />
                    <Button size="sm" variant="secondary" @click="copy">{{ copied ? 'Copied' : 'Copy link' }}</Button>
                    <a :href="webcal" class="hidden items-center justify-center rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500 sm:inline-flex">Open in calendar app</a>
                    <Button size="sm" variant="ghost" @click="replaceLink">Replace link</Button>
                </div>
                <Alert class="mt-3" variant="warning">Keep this link private. Anyone who has it can see your meetings and events.</Alert>
            </Card>
        </div>
    </MembersLayout>
</template>
