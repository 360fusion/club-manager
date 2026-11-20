<script setup>
// The Treasurer matching bank lines on a phone, straight after importing a statement (MemberAccountsController::match).
// Only certain matches (reference and amount) are offered here; anything else goes to the full reconciliation screen.
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';

const props = defineProps({
    club: { type: Object, required: true },
    lines: { type: Array, required: true },
    more: { type: Number, default: 0 },
});

const matched = computed(() => props.lines.filter((line) => line.match));
const other = computed(() => props.lines.filter((line) => !line.match));
const busy = ref(null);

const post = (name, params, key) => router.post(route(name, params), {}, {
    preserveScroll: true,
    onStart: () => { busy.value = key; },
    onFinish: () => { busy.value = null; },
});
const confirm = (line) => post('member.accounts.match.confirm', { slug: props.club.slug, id: line.id }, line.id);
const confirmAll = () => post('member.accounts.match.all', { slug: props.club.slug }, 'all');
</script>

<template>
    <MembersLayout title="Match payments" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-2xl space-y-5">
            <div>
                <Link :href="route('member.accounts', { slug: club.slug })" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to the accounts</Link>
                <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900 dark:text-white">Match payments</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Bank lines whose reference and amount match one payment for certain. Confirming marks it paid and records it in the books.</p>
            </div>

            <Card v-if="!lines.length"><p class="text-sm text-slate-600 dark:text-slate-300">Every bank line is reconciled.</p></Card>

            <template v-else>
                <div v-if="matched.length > 1" class="flex items-center justify-between gap-3">
                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ matched.length }} certain matches</p>
                    <Button :loading="busy === 'all'" @click="confirmAll">Confirm all {{ matched.length }}</Button>
                </div>

                <Card v-if="matched.length" padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div v-for="line in matched" :key="line.id" class="space-y-2 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ line.description }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ line.date }}<template v-if="line.reference"> · Ref {{ line.reference }}</template></p>
                            </div>
                            <strong :class="['shrink-0 text-sm tabular-nums', line.in ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white']">{{ line.in ? '+' : '−' }}{{ line.amount }}</strong>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-emerald-50 px-3 py-2 dark:bg-emerald-950/40">
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-emerald-900 dark:text-emerald-200">{{ line.match.title }}</p>
                                <p v-if="line.match.reason" class="text-xs text-emerald-800 dark:text-emerald-300">{{ line.match.reason }}</p>
                            </div>
                            <Button size="sm" :loading="busy === line.id" @click="confirm(line)">Confirm</Button>
                        </div>
                    </div>
                </Card>

                <Card v-if="other.length || more" padding="none" class="divide-y divide-slate-100 dark:divide-slate-800">
                    <div class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="text-sm font-medium text-slate-900 dark:text-white">To match by hand</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">No certain match. Choose what each one is on the full screen.</p>
                        </div>
                        <Badge variant="warning">{{ other.length + more }}</Badge>
                    </div>
                    <div v-for="line in other" :key="line.id" class="flex items-start justify-between gap-3 px-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-slate-800 dark:text-slate-100">{{ line.description }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ line.date }}<template v-if="line.reference"> · Ref {{ line.reference }}</template></p>
                        </div>
                        <span :class="['shrink-0 text-sm tabular-nums', line.in ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-200']">{{ line.in ? '+' : '−' }}{{ line.amount }}</span>
                    </div>
                    <div class="px-4 py-3">
                        <Button variant="secondary" size="sm" :href="route('banking.bank-reconciliation-workspace', { clubSlug: club.slug })">Open the reconciliation screen</Button>
                    </div>
                </Card>
            </template>
        </div>
    </MembersLayout>
</template>
