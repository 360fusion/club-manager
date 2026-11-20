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
