<script setup>
// Public events calendar: a month grid (or an upcoming list) of the events this visitor may see. The server sends
// dates already worked out in the club's timezone, so the grid never shifts with the visitor's own timezone.
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    block: { type: Object, required: true },
    theme: { type: Object, required: true },
    club: { type: Object, required: true },
    calendar: { type: Object, default: null },
    interactive: { type: Boolean, default: true },
    radiusMd: { type: String, default: 'rounded-2xl' },
    radiusLg: { type: String, default: 'rounded-3xl' },
});

const view = ref(props.block.default_view === 'list' ? 'list' : 'month');
const loading = ref(false);
const sundayFirst = computed(() => props.block.week_starts === 'sunday');
const weekdays = computed(() => (sundayFirst.value ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']));

const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getUTCFullYear()}-${pad(d.getUTCMonth() + 1)}-${pad(d.getUTCDate())}`;
const utc = (isoDate) => new Date(`${isoDate}T00:00:00Z`);

const days = computed(() => {
    if (!props.calendar?.month) return [];
    const first = utc(`${props.calendar.month}-01`);
    const offset = (first.getUTCDay() - (sundayFirst.value ? 0 : 1) + 7) % 7;
    const start = new Date(first.getTime() - offset * 86400000);
    const lastOfMonth = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 0));
    const total = Math.ceil((offset + lastOfMonth.getUTCDate()) / 7) * 7;

    return Array.from({ length: total }, (_, i) => {
        const date = new Date(start.getTime() + i * 86400000);
        return { date: iso(date), day: date.getUTCDate(), inMonth: date.getUTCMonth() === first.getUTCMonth() };
    });
});

const byDay = computed(() => {
    const map = {};
    (props.calendar?.events || []).forEach((event) => {
        const end = utc(event.end_date || event.start_date);
        let cursor = utc(event.start_date);
        for (let n = 0; n < 7 && cursor <= end; n += 1) {
            (map[iso(cursor)] ||= []).push(event);
            cursor = new Date(cursor.getTime() + 86400000);
        }
    });
    return map;
});

const upcoming = computed(() => (props.calendar?.upcoming || []).slice(0, Number(props.block.list_length) || 10));
const eventUrl = (event) => `/site/${props.club.slug}/events/${event.slug}`;
const longDate = (isoDate) => utc(isoDate).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' });

const go = (month) => {
    if (!props.interactive || !month) return;
    const url = new URL(window.location.href);
    url.searchParams.set('cal', month);
    router.get(url.pathname + url.search, {}, {
        only: ['calendar'], preserveState: true, preserveScroll: true, replace: true,
        onStart: () => { loading.value = true; },
        onFinish: () => { loading.value = false; },
    });
};
</script>

<template>
    <section v-if="calendar || !interactive" class="max-w-6xl mx-auto space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 v-if="block.heading" :class="['text-2xl sm:text-3xl font-bold', theme.headingText]">{{ block.heading }}</h2>
            <div v-if="block.allow_switch !== false" :class="['inline-flex p-1 border text-xs font-bold', radiusMd, theme.cardBg]" role="group" aria-label="Calendar view">
                <button type="button" :aria-pressed="view === 'month'" :class="['px-3 py-1.5 cursor-pointer rounded-lg', view === 'month' ? theme.accentBg + ' text-white' : theme.bodyText]" @click="view = 'month'">Month</button>
                <button type="button" :aria-pressed="view === 'list'" :class="['px-3 py-1.5 cursor-pointer rounded-lg', view === 'list' ? theme.accentBg + ' text-white' : theme.bodyText]" @click="view = 'list'">List</button>
            </div>
        </div>

        <p v-if="!calendar" :class="['text-sm opacity-70', theme.bodyText]">The calendar loads your club's events on the live site.</p>

        <template v-else>
            <div v-if="view === 'month'" :class="['border overflow-hidden transition-opacity', loading ? 'opacity-60' : '', radiusLg, theme.cardBg]">
                <div class="flex items-center justify-between px-4 py-3 border-b border-current/10">
                    <button type="button" :disabled="!interactive || !calendar.prev" :class="['px-3 py-1.5 text-sm font-bold rounded-lg cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed', theme.bodyText]" aria-label="Previous month" @click="go(calendar.prev)">‹</button>
                    <div class="flex items-center gap-3">
                        <span :class="['font-extrabold', theme.headingText]" aria-live="polite">{{ calendar.label }}</span>
                        <button v-if="interactive && calendar.month !== calendar.today_month" type="button" :class="['text-xs font-bold underline cursor-pointer', theme.accentText]" @click="go(calendar.today_month)">Today</button>
                    </div>
                    <button type="button" :disabled="!interactive || !calendar.next" :class="['px-3 py-1.5 text-sm font-bold rounded-lg cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed', theme.bodyText]" aria-label="Next month" @click="go(calendar.next)">›</button>
                </div>

                <div class="grid grid-cols-7 text-center text-[11px] font-bold uppercase tracking-wider opacity-70 py-2">
                    <div v-for="d in weekdays" :key="d" :class="theme.bodyText">{{ d }}</div>
                </div>

                <div class="grid grid-cols-7 border-t border-current/10">
                    <div v-for="cell in days" :key="cell.date" :class="['min-h-[5.5rem] sm:min-h-[7rem] p-1.5 border-b border-r border-current/10 space-y-1', cell.inMonth ? '' : 'opacity-40']">
                        <div :class="['text-xs font-bold w-6 h-6 flex items-center justify-center rounded-full', cell.date === calendar.today ? theme.accentBg + ' text-white' : theme.bodyText]">{{ cell.day }}</div>
                        <component
                            :is="interactive ? 'a' : 'span'"
                            v-for="event in (byDay[cell.date] || []).slice(0, 3)"
                            :key="event.id + cell.date"
                            :href="interactive ? eventUrl(event) : undefined"
                            :title="event.title"
                            :class="['block truncate px-1.5 py-0.5 text-[11px] font-semibold rounded', theme.heroPill]"
                        >
                            <span v-if="block.show_times !== false && event.time" class="opacity-70">{{ event.time }} </span>{{ event.title }}
                        </component>
                        <div v-if="(byDay[cell.date] || []).length > 3" :class="['text-[11px] font-bold px-1', theme.accentText]">+{{ byDay[cell.date].length - 3 }} more</div>
                    </div>
                </div>

                <p v-if="!calendar.events.length" :class="['text-sm text-center py-4 opacity-70', theme.bodyText]">No events this month.</p>
            </div>

            <div v-else class="space-y-3">
                <p v-if="!upcoming.length" :class="['text-sm opacity-70', theme.bodyText]">No upcoming events.</p>
                <component
                    :is="interactive ? 'a' : 'div'"
                    v-for="event in upcoming"
                    :key="event.id + event.start_date"
                    :href="interactive ? eventUrl(event) : undefined"
                    :class="['flex gap-4 p-4 border hover:scale-[1.01] transition-transform', radiusMd, theme.cardBg]"
                >
                    <div :class="['w-14 shrink-0 text-center py-1.5 rounded-lg', theme.heroPill]">
                        <div class="text-xl font-black leading-none">{{ utc(event.start_date).getUTCDate() }}</div>
                        <div class="text-[10px] font-bold uppercase">{{ utc(event.start_date).toLocaleDateString('en-GB', { month: 'short', timeZone: 'UTC' }) }}</div>
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p :class="['font-bold', theme.headingText]">{{ event.title }}</p>
                        <p :class="['text-xs opacity-80', theme.bodyText]">
                            {{ longDate(event.start_date) }}<span v-if="block.show_times !== false && event.time"> · {{ event.time }}</span>
                            <span v-if="event.is_recurring"> · repeats</span>
                        </p>
                        <p v-if="block.show_location !== false && event.location" :class="['text-xs', theme.bodyText]">📍 {{ event.location }}</p>
                        <p v-if="block.show_price && event.price" :class="['text-xs font-semibold', theme.accentText]">{{ event.price }}</p>
                    </div>
                </component>
            </div>

            <p v-if="block.show_subscribe !== false && calendar.subscribe_url" class="text-sm">
                <component :is="interactive ? 'a' : 'span'" :href="interactive ? calendar.subscribe_url : undefined" :class="['font-semibold underline', theme.accentText]">📅 Add to your calendar (.ics)</component>
            </p>
        </template>
    </section>
</template>
