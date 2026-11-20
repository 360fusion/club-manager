<script setup>
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Card from '@/Components/Ui/Card.vue';
import ClubChips from '@/Components/ClubChips.vue';

defineProps({
    subscriptions: { type: Array, default: () => [] },
    invoices: { type: Array, default: () => [] },
    scopeClub: { type: Object, default: null },
    clubOptions: { type: Array, default: () => [] },
});

const day = (date) => (date ? new Date(`${date}T00:00:00`).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '');
// Where the member stands (DuesTracker::pace).
const PACE = { paid: ['Paid', 'success'], on_track: ['On track', 'success'], due: ['To pay', 'info'], behind: ['Behind', 'warning'], overdue: ['Overdue', 'danger'] };
</script>

<template>
    <MembersLayout title="Dues" active-tab="dues">
        <div class="space-y-6">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Dues and payments</h2>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.dues" />

            <section aria-labelledby="subs-heading" class="space-y-2">
                <h2 id="subs-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Subscriptions</h2>
                <Card v-for="item in subscriptions" :key="item.id" class="space-y-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.club.name }} <span class="font-normal text-slate-500 dark:text-slate-400">· {{ item.year }}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.amount_due }}<template v-if="item.due_date"> due by {{ item.due_date }}</template></p>
                        </div>
                        <Badge :variant="PACE[item.pace.state]?.[1] ?? 'neutral'">{{ PACE[item.pace.state]?.[0] ?? item.status_label }}</Badge>
                    </div>

                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span><strong class="text-slate-900 dark:text-white">{{ item.paid }}</strong> paid</span>
                            <span><strong class="text-slate-900 dark:text-white">{{ item.left }}</strong> left</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="img" :aria-label="`${item.percent}% paid`">
                            <div class="h-full rounded-full bg-emerald-600 dark:bg-emerald-500" :style="{ width: `${item.percent}%` }"></div>
                        </div>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ item.pace.message }}</p>
                    </div>

                    <details v-if="item.payments.length" class="text-sm">
                        <summary class="cursor-pointer font-medium text-slate-700 dark:text-slate-200">{{ item.payments.length }} {{ item.payments.length === 1 ? 'payment' : 'payments' }} received</summary>
                        <ul class="mt-2 divide-y divide-slate-100 dark:divide-slate-800">
                            <li v-for="(payment, index) in item.payments" :key="index" class="flex justify-between gap-2 py-1.5">
                                <span class="text-slate-500 dark:text-slate-400">{{ payment.date }}</span>
                                <span class="font-medium tabular-nums text-slate-900 dark:text-white">{{ payment.amount }}</span>
                            </li>
                        </ul>
                    </details>

                    <div v-if="item.outstanding && item.bank" class="space-y-1 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                        <p class="font-semibold text-slate-900 dark:text-white">Pay by bank transfer, in one go or by a monthly standing order</p>
                        <p v-if="item.bank.account_name">Account name: <strong>{{ item.bank.account_name }}</strong></p>
                        <p v-if="item.bank.sort_code">{{ item.bank.code_label || 'Sort code' }}: <strong class="font-mono">{{ item.bank.sort_code }}</strong></p>
                        <p v-if="item.bank.account_number">Account number: <strong class="font-mono">{{ item.bank.account_number }}</strong></p>
                        <p v-if="item.bank.iban">IBAN: <strong class="font-mono">{{ item.bank.iban }}</strong></p>
                        <p v-if="item.reference">Reference: <strong class="font-mono">{{ item.reference }}</strong> (please use it, so the Treasurer can match your payments)</p>
                    </div>
                </Card>
                <Card v-if="!subscriptions.length"><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">No subscription records yet.</p></Card>
            </section>

            <section aria-labelledby="inv-heading" class="space-y-2">
                <h2 id="inv-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Invoices and receipts</h2>
                <Card v-if="invoices.length" padding="none">
                    <ul>
                        <li v-for="invoice in invoices" :key="invoice.id" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ invoice.title }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ invoice.club.name }} · {{ invoice.number }} · {{ day(invoice.created_at) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ invoice.club.currency_symbol }}{{ invoice.amount }}</span>
                                <Badge :variant="invoice.status === 'paid' ? 'success' : 'warning'">{{ invoice.status }}</Badge>
                                <a :href="route('invoices.download', { slug: invoice.club.slug, id: invoice.id })" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Download</a>
                            </div>
                        </li>
                    </ul>
                </Card>
                <Card v-else><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">No invoices yet.</p></Card>
            </section>
        </div>
    </MembersLayout>
</template>
