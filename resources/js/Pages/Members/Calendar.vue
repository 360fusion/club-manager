<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChips from '@/Components/ClubChips.vue';
import QuickReply from '@/Components/QuickReply.vue';

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

const COLOURS = [
    'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-200',
    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200',
    'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200',
    'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-200',
    'bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100',
];
const colourFor = (slug) => COLOURS[Math.max(0, props.clubOptions.findIndex((club) => club.slug === slug)) % COLOURS.length];

const dateKey = (date) => new Date(date).toLocaleDateString('en-CA');

const days = computed(() => {
    const out = [];
    const cursor = new Date(`${props.gridStart}T00:00:00`);
    const end = new Date(`${props.gridEnd}T00:00:00`);

    while (cursor <= end) {
        out.push({ key: cursor.toLocaleDateString('en-CA'), day: cursor.getDate(), inMonth: cursor.toLocaleDateString('en-CA').startsWith(props.month), date: new Date(cursor) });
        cursor.setDate(cursor.getDate() + 1);
    }

    return out;
});

const byDay = computed(() => props.items.reduce((acc, item) => {
    (acc[dateKey(item.start)] ??= []).push(item);

    return acc;
}, {}));

const selected = ref(null);
const today = new Date().toLocaleDateString('en-CA');

const agenda = computed(() => (selected.value ? (byDay.value[selected.value] ?? []) : props.items.filter((item) => dateKey(item.start).startsWith(props.month))));

const shiftMonth = (delta) => {
    const [year, month] = props.month.split('-').map(Number);
    const next = new Date(year, month - 1 + delta, 1);

    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`;
};

const time = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
const longDate = (iso) => new Date(iso).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' });

const copied = ref(false);
const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.feedUrl);
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    } catch (e) {
        // Clipboard unavailable; the field is selectable instead.
    }
};

const replaceLink = () => {
    if (confirm('Replace your calendar link? Calendars using the old link will stop updating.')) {
        router.post(route('members.calendar.regenerate'), {}, { preserveScroll: true });
    }
};

const webcal = computed(() => props.feedUrl.replace(/^https?:/, 'webcal:'));
</script>

<template>
    <MembersLayout title="Calendar" :club="urlScoped ? scopeClub : null" active-tab="calendar">
        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Calendar</h1>
                <div class="flex items-center gap-2">
                    <Button size="sm" variant="secondary" :href="route(routeName, routeParams)">Today</Button>
                    <Link :href="route(routeName, routeParams)" :data="{ month: shiftMonth(-1), club: urlScoped ? undefined : scopeClub?.slug }" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Previous month" preserve-scroll>‹</Link>
                    <span class="min-w-[9rem] text-center text-sm font-semibold text-slate-900 dark:text-white">{{ monthLabel }}</span>
                    <Link :href="route(routeName, routeParams)" :data="{ month: shiftMonth(1), club: urlScoped ? undefined : scopeClub?.slug }" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Next month" preserve-scroll>›</Link>
                </div>
            </div>

            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.calendar" />

            <Card padding="none">
                <div class="grid grid-cols-7 border-b border-slate-200 text-center text-[11px] font-medium uppercase text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <div v-for="name in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="name" class="py-2">{{ name }}</div>
                </div>
                <div class="grid grid-cols-7">
                    <button
                        v-for="day in days"
                        :key="day.key"
                        type="button"
                        :class="['min-h-[4.5rem] space-y-1 border-b border-r border-slate-200 p-1.5 text-left align-top transition-colors last:border-r-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50 sm:min-h-[6rem]', day.inMonth ? '' : 'bg-slate-50/60 text-slate-400 dark:bg-slate-900/40', selected === day.key ? 'ring-2 ring-inset ring-blue-500' : '']"
                        :aria-label="`${day.key}, ${(byDay[day.key] ?? []).length} items`"
                        @click="selected = selected === day.key ? null : day.key"
                    >
                        <span :class="['inline-flex h-6 w-6 items-center justify-center rounded-full text-xs', day.key === today ? 'bg-blue-600 font-bold text-white' : '']">{{ day.day }}</span>
                        <span v-for="item in (byDay[day.key] ?? []).slice(0, 3)" :key="item.key" :class="['block truncate rounded px-1 py-0.5 text-[10px] font-medium leading-tight sm:text-[11px]', colourFor(item.club.slug), item.clash ? 'ring-1 ring-amber-500' : '']">{{ item.title }}</span>
                        <span v-if="(byDay[day.key] ?? []).length > 3" class="block text-[10px] text-slate-500">+{{ byDay[day.key].length - 3 }} more</span>
                    </button>
                </div>
            </Card>

            <section aria-labelledby="agenda-heading" class="space-y-3">
                <h2 id="agenda-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ selected ? longDate(selected) : `Agenda for ${monthLabel}` }}</h2>
                <Card v-if="agenda.length" padding="none">
                    <ul>
                        <li v-for="item in agenda" :key="item.key" class="space-y-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <Badge :variant="item.type === 'meeting' ? 'info' : 'success'">{{ item.type === 'meeting' ? 'Meeting' : 'Event' }}</Badge>
                                <Badge v-if="item.clash" variant="warning">Clashes with another item</Badge>
                                <span>{{ item.club.name }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ longDate(item.start) }}, {{ time(item.start) }}</span>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <Link :href="item.url" class="text-sm font-semibold text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-300">{{ item.title }}</Link>
                                    <p v-if="item.where" class="text-xs text-slate-500 dark:text-slate-400">{{ item.where }}</p>
                                </div>
                                <QuickReply :item="{ type: item.type, id: item.id, slug: item.club.slug, reply: item.reply, closed: item.closed, simple: item.simple }" />
                            </div>
                        </li>
                    </ul>
                </Card>
                <Card v-else><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">Nothing scheduled {{ selected ? 'that day' : 'this month' }}.</p></Card>
            </section>

            <Card>
                <template #header><h2 class="text-sm font-semibold text-slate-900 dark:text-white">Add to your calendar</h2></template>
                <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Subscribe once in Apple Calendar, Google Calendar or Outlook and every meeting and event across your clubs stays up to date.</p>
                <div class="flex flex-wrap items-center gap-2">
                    <input :value="feedUrl" readonly class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-3 py-2 font-mono text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300" aria-label="Your private calendar link" @focus="$event.target.select()" />
                    <Button size="sm" variant="secondary" @click="copy">{{ copied ? 'Copied' : 'Copy link' }}</Button>
                    <a :href="webcal" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-500">Open in calendar app</a>
                    <Button size="sm" variant="ghost" @click="replaceLink">Replace link</Button>
                </div>
                <Alert class="mt-3" variant="warning">Keep this link private. Anyone who has it can see your meetings and events.</Alert>
            </Card>
        </div>
    </MembersLayout>
</template>
