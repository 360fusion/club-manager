<script setup>
// "My Masonic record" (MasonicRecord): degrees, ranks, offices held, attendance and visits. Read-only; the Secretary
// keeps the record, so corrections go to them.
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';

defineProps({
    club: { type: Object, required: true },
    record: { type: Object, default: null },
});

const overline = 'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const STEP = {
    done: 'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-500',
    now: 'border-blue-600 bg-white text-blue-700 ring-2 ring-blue-600/30 dark:border-blue-400 dark:bg-slate-900 dark:text-blue-300',
    to_come: 'border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-500',
};
// "12, up from 9": this year against the year before, when both are known.
const compared = (now, before) => (before === null || before === undefined || (now === 0 && before === 0) ? '' : now > before ? `, up from ${before}` : now < before ? `, down from ${before}` : ', the same as the year before');
const years = (count) => (count === 0 ? 'this year' : `${count} ${count === 1 ? 'year' : 'years'} ago`);
</script>

<template>
    <MembersLayout title="My Masonic record" :club="club" active-tab="profile">
        <div class="mx-auto max-w-xl space-y-5">
            <div>
                <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">My Masonic record</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ club.name }}<template v-if="club.lodge_number"> No. {{ club.lodge_number }}</template></p>
            </div>

            <Card v-if="!record">
                <p class="text-sm text-slate-600 dark:text-slate-300">The lodge hasn't set up your record yet. The Secretary keeps it, so ask them to add your details.</p>
            </Card>

            <template v-else>
                <Card class="space-y-1">
                    <p class="font-display text-xl font-semibold text-slate-900 dark:text-white">{{ record.name }}</p>
                    <p v-if="record.current_office" class="text-sm text-slate-600 dark:text-slate-300">{{ record.current_office }} this year</p>
                    <p v-for="rank in record.ranks" :key="rank.label" class="text-sm text-slate-600 dark:text-slate-300">{{ rank.label }}: {{ rank.value }}</p>
                    <p v-if="record.grand_lodge_number" class="text-xs text-slate-500 dark:text-slate-400">Grand Lodge registration {{ record.grand_lodge_number }}</p>
                </Card>

                <section class="space-y-2" aria-labelledby="path-heading">
                    <h3 id="path-heading" :class="overline">Your path</h3>
                    <Card class="space-y-4">
                        <ol class="flex items-center gap-2" aria-label="Degrees">
                            <li v-for="degree in record.path.degrees" :key="degree.label" class="flex items-center gap-1.5 text-sm">
                                <span :class="['flex h-6 w-6 items-center justify-center rounded-full border text-xs font-bold', degree.done ? STEP.done : STEP.to_come]" aria-hidden="true">{{ degree.done ? '✓' : '' }}</span>
                                <span :class="degree.done ? 'font-medium text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">{{ degree.label }}<span class="sr-only">{{ degree.done ? ', done' : ', to come' }}</span></span>
                            </li>
                        </ol>
                        <ol class="space-y-1.5" aria-label="The usual offices to the Chair">
                            <li v-for="office in record.path.offices" :key="office.label" class="flex items-center gap-2.5 text-sm">
                                <span :class="['h-3 w-3 shrink-0 rounded-full border', STEP[office.state]]" aria-hidden="true"></span>
                                <span :class="office.state === 'now' ? 'font-semibold text-blue-700 dark:text-blue-300' : office.state === 'done' ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400'">{{ office.label }}</span>
                                <span class="sr-only">{{ office.state === 'now' ? ', this year' : office.state === 'done' ? ', held' : ', to come' }}</span>
                                <Badge v-if="office.state === 'now'" variant="info">This year</Badge>
                            </li>
                        </ol>
                        <p v-if="record.path.past_master" class="text-sm font-medium text-slate-900 dark:text-white">You are a Past Master of the lodge.</p>
                        <p v-else-if="record.path.next" class="text-sm text-slate-700 dark:text-slate-200">Usually next: <strong>{{ record.path.next }}</strong>. The lodge decides each step, so speak to the Director of Ceremonies.</p>
                    </Card>
                </section>

                <section class="space-y-2" aria-labelledby="year-heading">
                    <h3 id="year-heading" :class="overline">Your year</h3>
                    <Card class="space-y-1.5 text-sm text-slate-700 dark:text-slate-200">
                        <p v-if="record.year.meetings !== null"><strong>{{ record.year.meetings }}</strong> {{ record.year.meetings === 1 ? 'meeting' : 'meetings' }} in the last 12 months{{ compared(record.year.meetings, record.year.meetings_before) }}.</p>
                        <p><strong>{{ record.year.visits }}</strong> {{ record.year.visits === 1 ? 'visit' : 'visits' }} to other lodges{{ compared(record.year.visits, record.year.visits_before) }}.</p>
                        <p v-if="record.year.next_anniversary" :class="record.year.next_anniversary.milestone ? 'font-medium text-amber-800 dark:text-amber-300' : ''">Next: {{ record.year.next_anniversary.label }} on {{ record.year.next_anniversary.date }}.</p>
                    </Card>
                </section>

                <section class="space-y-2" aria-labelledby="degrees-heading">
                    <h3 id="degrees-heading" :class="overline">Degrees</h3>
                    <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <p v-if="!record.milestones.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No dates recorded yet.</p>
                        <div v-for="step in record.milestones" :key="step.label" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span>
                                <span class="block font-medium text-slate-900 dark:text-white">{{ step.label }}</span>
                                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ step.date }} · {{ years(step.years) }}</span>
                            </span>
                            <Badge v-if="step.anniversary_soon" variant="info">Anniversary soon</Badge>
                        </div>
                    </Card>
                </section>

                <section class="space-y-2" aria-labelledby="offices-heading">
                    <h3 id="offices-heading" :class="overline">Offices held</h3>
                    <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <p v-if="!record.offices.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">None recorded yet.</p>
                        <div v-for="(office, index) in record.offices" :key="index" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900 dark:text-white">{{ office.office }}</span>
                            <span class="text-xs text-slate-500 dark:text-slate-400">{{ office.year }}</span>
                        </div>
                    </Card>
                </section>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Card class="space-y-1">
                        <h3 :class="overline">Attendance, last 12 months</h3>
                        <p v-if="record.attendance" class="text-sm text-slate-700 dark:text-slate-200"><strong class="text-lg">{{ record.attendance.attended }}</strong> of {{ record.attendance.meetings }} meetings<template v-if="record.attendance.last">, last on {{ record.attendance.last }}</template></p>
                        <p v-else class="text-sm text-slate-500 dark:text-slate-400">The lodge isn't using the signing-in book yet.</p>
                    </Card>
                    <Card class="space-y-1">
                        <h3 :class="overline">Visits to other lodges</h3>
                        <p class="text-sm text-slate-700 dark:text-slate-200"><strong class="text-lg">{{ record.visits.count }}</strong> recorded</p>
                        <p v-for="(visit, index) in record.visits.recent" :key="index" class="truncate text-xs text-slate-500 dark:text-slate-400">{{ visit.date }} · {{ visit.lodge }}</p>
                        <p v-for="(visit, index) in record.visits.upcoming" :key="`up-${index}`" class="truncate text-xs font-medium text-blue-700 dark:text-blue-300">Coming up: {{ visit.date }} · {{ visit.lodge }}</p>
                        <Link :href="route('lodges.index')" class="inline-block pt-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Find a lodge to visit</Link>
                    </Card>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">Something wrong or missing? The Secretary keeps these records, so let them know.</p>
            </template>
        </div>
    </MembersLayout>
</template>
