<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Tabs from '@/Components/Ui/Tabs.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
    claims: { type: Object, required: true },
    status: { type: String, default: 'open' },
    counts: { type: Object, required: true },
    clubs: { type: Array, default: () => [] },
});

const TABS = [
    { key: 'open', label: 'Waiting' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Closed' },
    { key: 'all', label: 'All' },
];

// Badge variant for each claim status.
const STATUS = {
    pending: 'info',
    more_info: 'warning',
    approved: 'success',
    rejected: 'danger',
    withdrawn: 'neutral',
    superseded: 'neutral',
};

const EVENTS = {
    submitted: 'Claim sent',
    more_info_requested: 'We asked',
    info_provided: 'They replied',
    approved: 'Approved (new club)',
    linked: 'Approved (linked to a club)',
    rejected: 'Refused',
    withdrawn: 'Withdrawn',
    superseded: 'Closed: another claim approved',
    unlinked: 'Listing taken back',
};

// One action open at a time: { id, kind: 'approve' | 'reject' | 'info' }.
const acting = ref(null);
const form = reactive({ mode: 'new', club_id: '', make_claimant_admin: false, note: '', reason: '', question: '' });
const errors = ref({});

function open(claim, kind) {
    acting.value = { id: claim.id, kind };
    Object.assign(form, { mode: 'new', club_id: '', make_claimant_admin: false, note: '', reason: '', question: '' });
    errors.value = {};
}

function send(claim) {
    const routes = { approve: 'superadmin.lodge_claims.approve', reject: 'superadmin.lodge_claims.reject', info: 'superadmin.lodge_claims.request_info' };
    const payload = acting.value.kind === 'approve'
        ? { mode: form.mode, club_id: form.mode === 'link' ? form.club_id : null, make_claimant_admin: form.make_claimant_admin, note: form.note }
        : acting.value.kind === 'reject' ? { reason: form.reason } : { question: form.question };

    router.post(route(routes[acting.value.kind], claim.id), payload, {
        preserveScroll: true,
        onSuccess: () => { acting.value = null; },
        onError: (e) => { errors.value = e; },
    });
}

</script>

<template>
    <SuperAdminLayout title="Lodge claims">
        <Head title="Superadmin - Lodge claims" />

        <div class="space-y-6">
            <PageHeader title="Lodge claims" description="People asking to manage a lodge. Check that they belong to it before you approve: the province's own page for the lodge is linked on each claim." />

            <Tabs
                label="Claims"
                :active="status"
                :items="TABS.map((tab) => ({
                    key: tab.key,
                    label: tab.label + (tab.key === 'open' && counts.open ? ` (${counts.open})` : '') + (tab.key === 'approved' ? ` (${counts.approved})` : ''),
                    href: route('superadmin.lodge_claims.index', { status: tab.key }),
                }))"
            />

            <Card v-if="!claims.data.length" class="border-dashed text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">No claims here.</p>
            </Card>

            <ul class="space-y-4">
                <li v-for="claim in claims.data" :key="claim.id">
                    <Card class="space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ claim.lodge.name }}<span v-if="claim.lodge.number" class="font-normal text-slate-500 dark:text-slate-400"> · No. {{ claim.lodge.number }}</span></h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ claim.lodge.order }}<span v-if="claim.lodge.province"> · {{ claim.lodge.province }}</span><span v-if="claim.lodge.hall"> · {{ claim.lodge.hall }}</span>
                                </p>
                                <p class="mt-1 text-xs">
                                    <Link :href="route('lodges.show', claim.lodge.slug)" class="text-blue-600 hover:underline dark:text-blue-400">Public page</Link>
                                    <template v-if="claim.lodge.source_url"> · <a :href="claim.lodge.source_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-blue-600 hover:underline dark:text-blue-400">The province's page for this lodge <UiIcon name="external-link" :size="12" /></a></template>
                                </p>
                            </div>
                            <Badge :variant="STATUS[claim.status]" class="capitalize">{{ claim.status.replace('_', ' ') }}</Badge>
                        </div>

                        <div class="grid gap-4 text-sm md:grid-cols-2">
                            <div class="space-y-1">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Who is asking</h3>
                                <p class="font-semibold text-slate-900 dark:text-white">{{ claim.claimant.name }} <span class="font-normal text-slate-500 dark:text-slate-400">({{ claim.claimant_role }})</span></p>
                                <p><a :href="`mailto:${claim.claimant.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ claim.claimant.email }}</a>
                                    <span :class="claim.claimant.email_verified ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"> · {{ claim.claimant.email_verified ? 'verified' : 'not verified' }}</span></p>
                                <p v-if="claim.phone">Phone: {{ claim.phone }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Account since {{ claim.claimant.joined }}<span v-if="claim.claimant.other_claims"> · {{ claim.claimant.other_claims }} other claim(s)</span></p>
                                <p v-if="claim.claimant.clubs.length" class="text-xs text-slate-500 dark:text-slate-400">Member of: {{ claim.claimant.clubs.join(', ') }}</p>
                            </div>
                            <div class="space-y-1">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">What they said</h3>
                                <p class="whitespace-pre-wrap">{{ claim.message }}</p>
                                <p v-if="claim.evidence" class="whitespace-pre-wrap rounded-xl bg-slate-50 p-2 text-xs dark:bg-slate-800/60"><strong>To help us check:</strong> {{ claim.evidence }}</p>
                            </div>
                        </div>

                        <Alert v-if="claim.others_waiting" variant="warning">{{ claim.others_waiting }} other claim(s) for this lodge are also waiting. Approving one closes the others.</Alert>
                        <p v-if="claim.status === 'approved' && claim.club" class="text-sm">Managed by <strong>{{ claim.club.name }}</strong> ({{ claim.decided_by }}, {{ claim.decided_at }}).</p>
                        <p v-if="claim.decision_note && claim.status !== 'approved'" class="text-sm"><strong>Note:</strong> {{ claim.decision_note }}</p>

                        <ol class="space-y-1 border-l-2 border-slate-200 pl-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">
                            <li v-for="(event, i) in claim.events" :key="i"><strong>{{ EVENTS[event.type] ?? event.type }}</strong> · {{ event.actor ?? 'System' }} · {{ event.at }}<span v-if="event.note">: {{ event.note }}</span></li>
                        </ol>

                        <div v-if="claim.open" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <div v-if="!acting || acting.id !== claim.id" class="flex flex-wrap gap-2">
                                <Button size="sm" @click="open(claim, 'approve')">Approve…</Button>
                                <Button v-if="claim.status === 'pending'" variant="secondary" size="sm" @click="open(claim, 'info')">Ask a question…</Button>
                                <Button variant="secondary" size="sm" @click="open(claim, 'reject')">Refuse…</Button>
                            </div>

                            <form v-else class="space-y-3" @submit.prevent="send(claim)">
                                <template v-if="acting.kind === 'approve'">
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="form.mode" type="radio" value="new" class="text-blue-600 focus:ring-blue-500" /> Create a new club for them and make them its owner</label>
                                    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="form.mode" type="radio" value="link" class="text-blue-600 focus:ring-blue-500" /> Link to a club that already exists</label>
                                    <div v-if="form.mode === 'link'" class="space-y-2 pl-6">
                                        <Select v-model.number="form.club_id" required aria-label="Club" :error="errors.club_id" :options="[{ value: '', label: 'Choose a club' }, ...clubs.map((club) => ({ value: club.id, label: club.name }))]" />
                                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input v-model="form.make_claimant_admin" type="checkbox" class="rounded text-blue-600 focus:ring-blue-500" /> Also make them an admin of that club</label>
                                    </div>
                                    <Textarea v-model="form.note" :rows="2" aria-label="Note" placeholder="A note for the record (optional)" />
                                </template>
                                <template v-else-if="acting.kind === 'reject'">
                                    <Textarea v-model="form.reason" :rows="3" required aria-label="Reason" placeholder="Why the claim was not approved. The person will see this." :error="errors.reason" />
                                </template>
                                <template v-else>
                                    <Textarea v-model="form.question" :rows="3" required aria-label="Question" placeholder="What you need to know. The person will see this and can reply." :error="errors.question" />
                                </template>
                                <p v-if="errors.claim" class="text-xs text-rose-600 dark:text-rose-400">{{ errors.claim }}</p>
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm">Confirm</Button>
                                    <Button variant="ghost" size="sm" @click="acting = null">Cancel</Button>
                                </div>
                            </form>
                        </div>
                    </Card>
                </li>
            </ul>

            <Pagination :links="claims.links" />
        </div>
    </SuperAdminLayout>
</template>
