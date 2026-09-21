<script setup>
import { Link } from '@inertiajs/vue3';
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
const statusVariant = (status) => ({ paid: 'success', waived: 'neutral', partially_paid: 'info', unpaid: 'warning', arrears_warning: 'danger' }[status] ?? 'neutral');
</script>

<template>
    <MembersLayout title="Dues" active-tab="dues">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Dues and payments</h1>
            <ClubChips :options="clubOptions" :current="scopeClub?.slug ?? null" route-name="members.dues" />

            <section aria-labelledby="subs-heading" class="space-y-2">
                <h2 id="subs-heading" class="text-sm font-medium text-slate-500 dark:text-slate-400">Subscriptions</h2>
                <Card v-if="subscriptions.length" padding="none">
                    <ul>
                        <li v-for="item in subscriptions" :key="item.id" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.club.name }} <span class="font-normal text-slate-500">· {{ item.year }}</span></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ item.club.currency_symbol }}{{ item.amount_paid }} paid of {{ item.club.currency_symbol }}{{ item.amount_due }}<template v-if="item.due_date"> · due {{ day(item.due_date) }}</template></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="item.outstanding" class="text-sm font-semibold text-slate-900 dark:text-white">{{ item.club.currency_symbol }}{{ item.balance }} to pay</span>
                                <Badge :variant="statusVariant(item.status)">{{ item.status_label }}</Badge>
                            </div>
                        </li>
                    </ul>
                </Card>
                <Card v-else><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">No subscription records yet.</p></Card>
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
                                <Link :href="route('invoices.download', { slug: invoice.club.slug, id: invoice.id })" class="text-xs font-medium text-blue-600 hover:underline dark:text-blue-400">Download</Link>
                            </div>
                        </li>
                    </ul>
                </Card>
                <Card v-else><p class="py-4 text-center text-sm text-slate-500 dark:text-slate-400">No invoices yet.</p></Card>
            </section>
        </div>
    </MembersLayout>
</template>
