<script setup>
// The lodge's accounts at a glance on a phone (LodgeAccountsOverview): bank, this year against the budget, what
// needs doing, and charity collected. Read-only; the full accounts are in the club admin.
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Button from '@/Components/Ui/Button.vue';

defineProps({
    club: { type: Object, required: true },
    overview: { type: Object, required: true },
    canImport: { type: Boolean, default: false },
});

const overline = 'text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400';
</script>

<template>
    <MembersLayout title="Lodge accounts" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-slate-900 dark:text-white">Lodge accounts</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Financial year {{ overview.year.label }}</p>
                </div>
                <Button v-if="canImport" size="sm" :href="route('member.accounts.import', { slug: club.slug })">Import a statement</Button>
            </div>

            <section class="space-y-2" aria-labelledby="bank-heading">
                <h3 id="bank-heading" :class="overline">In the bank</h3>
                <Card v-if="!overview.bank.length" padding="sm"><p class="text-sm text-slate-600 dark:text-slate-300">No bank account is set up in the accounts yet.</p></Card>
                <Card v-for="account in overview.bank" :key="account.name" padding="sm">
                    <div class="flex items-baseline justify-between gap-3">
                        <span class="font-medium text-slate-900 dark:text-white">{{ account.name }}</span>
                        <span class="font-display text-2xl font-semibold tabular-nums text-slate-900 dark:text-white">{{ account.balance }}</span>
                    </div>
                    <p class="mt-1 text-xs" :class="account.days_old === null || account.days_old > 14 ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400'">
                        <template v-if="account.statement_date">Last statement line {{ account.statement_date }} ({{ account.days_old === 0 ? 'today' : `${account.days_old} days ago` }})<template v-if="account.statement_balance">, when the bank showed {{ account.statement_balance }}</template>.</template>
                        <template v-else>No statement imported yet.</template>
                    </p>
                </Card>
            </section>

            <section class="space-y-2" aria-labelledby="todo-heading">
                <h3 id="todo-heading" :class="overline">To do</h3>
                <Card padding="none" class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <div class="flex items-center justify-between gap-3 px-4 py-3"><span>Bank lines to reconcile</span><strong class="tabular-nums">{{ overview.todo.unreconciled }}</strong></div>
                    <div class="flex items-center justify-between gap-3 px-4 py-3"><span>Bills waiting for approval</span><strong class="tabular-nums">{{ overview.todo.bills_to_approve.count }} · {{ overview.todo.bills_to_approve.total }}</strong></div>
                    <div class="flex items-center justify-between gap-3 px-4 py-3"><span>Bills to pay</span><strong class="tabular-nums">{{ overview.todo.bills_to_pay.count }} · {{ overview.todo.bills_to_pay.total }}</strong></div>
                    <div v-if="overview.todo.awaiting_banking" class="flex items-center justify-between gap-3 px-4 py-3"><span>Alms and raffle cash to pay in</span><strong class="tabular-nums">{{ overview.todo.awaiting_banking }}</strong></div>
                    <div class="flex items-center justify-between gap-3 px-4 py-3"><span>Dues outstanding</span><strong class="tabular-nums">{{ overview.todo.dues.owed }} · {{ overview.todo.dues.members }} {{ overview.todo.dues.members === 1 ? 'member' : 'members' }}</strong></div>
                    <div class="flex items-center justify-between gap-3 px-4 py-3"><span>Event bookings still to pay</span><strong class="tabular-nums">{{ overview.todo.bookings.owed }} · {{ overview.todo.bookings.count }}</strong></div>
                </Card>
            </section>

            <section v-for="side in [['income', 'Money in this year'], ['spending', 'Money out this year']]" :key="side[0]" class="space-y-2" :aria-labelledby="`${side[0]}-heading`">
                <div class="flex items-baseline justify-between gap-3">
                    <h3 :id="`${side[0]}-heading`" :class="overline">{{ side[1] }}</h3>
                    <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ overview[side[0]].total }}</span>
                </div>
                <Card padding="none" class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <p v-if="!overview[side[0]].rows.length" class="px-4 py-3 text-slate-500 dark:text-slate-400">Nothing yet this year.</p>
                    <div v-for="row in overview[side[0]].rows" :key="row.name" class="space-y-1.5 px-4 py-3">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0 truncate">{{ row.name }}</span>
                            <span class="shrink-0 tabular-nums"><strong>{{ row.amount }}</strong><span v-if="row.budget" class="text-xs text-slate-500 dark:text-slate-400"> of {{ row.budget }}</span></span>
                        </div>
                        <div v-if="row.share !== null" class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" :aria-label="`${row.share}% of the budget`">
                            <div class="h-full rounded-full bg-blue-600 dark:bg-blue-500" :style="{ width: `${row.share}%` }"></div>
                        </div>
                    </div>
                </Card>
            </section>

            <Card padding="sm" class="space-y-1">
                <div class="flex items-baseline justify-between gap-3 text-sm"><span>Money in less money out</span><strong class="tabular-nums">{{ overview.net }}</strong></div>
                <div class="flex items-baseline justify-between gap-3 text-sm"><span>Alms and raffle this year</span><strong class="tabular-nums">{{ overview.charity }}</strong></div>
            </Card>

            <p class="text-xs text-slate-500 dark:text-slate-400">
                The full accounts, reconciling and reports are in
                <Link :href="route('admin.accounting.index', { clubSlug: club.slug })" class="text-blue-600 hover:underline dark:text-blue-400">the club admin</Link>.
            </p>
        </div>
    </MembersLayout>
</template>
