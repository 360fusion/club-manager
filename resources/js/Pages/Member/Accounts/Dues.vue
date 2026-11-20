<script setup>
// The Treasurer's dues list on a phone: everyone's subscription for the year, behind and overdue first (DuesTracker).
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import ToggleSwitch from '@/Components/Ui/ToggleSwitch.vue';

const props = defineProps({
    club: { type: Object, required: true },
    dues: { type: Object, required: true },
    reminders: { type: Object, required: true },
});

function setReminders(on) {
    router.post(route('member.accounts.dues.reminders', { slug: props.club.slug }), { on }, { preserveScroll: true });
}

const PACE = { paid: ['Paid', 'success'], on_track: ['On track', 'success'], due: ['To pay', 'info'], behind: ['Behind', 'warning'], overdue: ['Overdue', 'danger'] };
const filter = ref('all');
const FILTERS = [['all', 'Everyone'], ['owing', 'Still owing'], ['behind', 'Behind or overdue']];
const rows = computed(() => props.dues.rows.filter((row) => filter.value === 'all'
    || (filter.value === 'owing' && row.pace.state !== 'paid')
    || (filter.value === 'behind' && ['behind', 'overdue'].includes(row.pace.state))));
</script>

<template>
    <MembersLayout title="Dues" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div>
                <Link :href="route('member.accounts', { slug: club.slug })" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to the accounts</Link>
                <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900 dark:text-white">{{ dues.year ? `Dues for ${dues.year}` : 'Dues' }}</h2>
            </div>

            <Card padding="sm" class="flex items-start justify-between gap-3">
                <div class="text-sm">
                    <p class="font-medium text-slate-900 dark:text-white">Reminders by email and in the app</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sent to anyone behind, overdue or two weeks from the due date, at most every four weeks. Not to anyone on track or who says they've paid.</p>
                </div>
                <ToggleSwitch v-if="reminders.can_change" :model-value="reminders.on" label="Send dues reminders" on-text="On" off-text="Off" @update:model-value="setReminders" />
                <Badge v-else :variant="reminders.on ? 'success' : 'neutral'">{{ reminders.on ? 'On' : 'Off' }}</Badge>
            </Card>

            <Card v-if="!dues.rows.length"><p class="text-sm text-slate-600 dark:text-slate-300">No subscriptions have been raised yet.</p></Card>

            <template v-else>
                <div class="grid grid-cols-3 gap-2 text-sm">
                    <Card padding="sm"><span class="block text-xs text-slate-500 dark:text-slate-400">Due</span><strong class="tabular-nums">{{ dues.totals.due }}</strong></Card>
                    <Card padding="sm"><span class="block text-xs text-slate-500 dark:text-slate-400">Paid</span><strong class="tabular-nums">{{ dues.totals.paid }}</strong></Card>
                    <Card padding="sm"><span class="block text-xs text-slate-500 dark:text-slate-400">Left</span><strong class="tabular-nums">{{ dues.totals.left }}</strong></Card>
                </div>

                <div class="flex gap-2 overflow-x-auto" role="group" aria-label="Show">
                    <button v-for="[key, label] in FILTERS" :key="key" type="button" :aria-pressed="filter === key" :class="['min-h-11 shrink-0 rounded-full border px-4 text-sm font-medium', filter === key ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 bg-white text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200']" @click="filter = key">{{ label }}</button>
                </div>

                <Card padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <p v-if="!rows.length" class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No one here.</p>
                    <details v-for="row in rows" :key="row.id" class="px-4 py-3">
                        <summary class="cursor-pointer list-none space-y-1.5">
                            <div class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate text-sm font-medium text-slate-900 dark:text-white">{{ row.name }}</span>
                                <Badge :variant="PACE[row.pace.state]?.[1] ?? 'neutral'">{{ PACE[row.pace.state]?.[0] ?? row.status_label }}</Badge>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" :aria-label="`${row.percent}% paid`">
                                <div class="h-full rounded-full bg-emerald-600 dark:bg-emerald-500" :style="{ width: `${row.percent}%` }"></div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ row.paid }} of {{ row.amount_due }} · {{ row.left }} left</p>
                        </summary>
                        <div class="mt-2 space-y-1 text-xs text-slate-600 dark:text-slate-300">
                            <p>{{ row.pace.message }}</p>
                            <p v-if="row.said_paid_on" class="font-medium text-amber-700 dark:text-amber-400">Says they paid on {{ row.said_paid_on }}: look for it in the bank.</p>
                            <p v-if="row.reference">Reference {{ row.reference }}</p>
                            <p v-for="(payment, index) in row.payments" :key="index">{{ payment.date }} · {{ payment.amount }}</p>
                        </div>
                    </details>
                </Card>
            </template>
        </div>
    </MembersLayout>
</template>
