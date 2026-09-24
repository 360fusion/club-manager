<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';

const props = defineProps({
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

const STATUS = {
    pending: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    more_info: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    approved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
    withdrawn: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
    superseded: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
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

const inputClass = 'w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 text-xs dark:bg-slate-800 dark:border-slate-700 dark:text-white';
</script>

<template>
    <SuperAdminLayout title="Lodge Claims">
        <Head title="Superadmin - Lodge Claims" />

        <div class="space-y-6">
            <div class="border-b border-slate-200 pb-5 dark:border-slate-800">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Lodge Claims</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">People asking to manage a lodge. Check that they belong to it before you approve: the province's own page for the lodge is linked on each claim.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link v-for="tab in TABS" :key="tab.key" :href="route('superadmin.lodge_claims.index', { status: tab.key })" preserve-scroll
                    :class="['rounded-lg px-3 py-1.5 text-xs font-bold', status === tab.key ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300']">
                    {{ tab.label }}<span v-if="tab.key === 'open' && counts.open"> ({{ counts.open }})</span><span v-if="tab.key === 'approved'"> ({{ counts.approved }})</span>
                </Link>
            </div>

            <p v-if="!claims.data.length" class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No claims here.</p>

            <ul class="space-y-4">
                <li v-for="claim in claims.data" :key="claim.id" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ claim.lodge.name }}<span v-if="claim.lodge.number" class="font-normal text-slate-500"> · No. {{ claim.lodge.number }}</span></h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                {{ claim.lodge.order }}<span v-if="claim.lodge.province"> · {{ claim.lodge.province }}</span><span v-if="claim.lodge.hall"> · {{ claim.lodge.hall }}</span>
                            </p>
                            <p class="mt-1 text-xs">
                                <Link :href="route('lodges.show', claim.lodge.slug)" class="text-blue-600 hover:underline dark:text-blue-400">Public page</Link>
                                <template v-if="claim.lodge.source_url"> · <a :href="claim.lodge.source_url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline dark:text-blue-400">The province's page for this lodge ↗</a></template>
                            </p>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', STATUS[claim.status]]">{{ claim.status.replace('_', ' ') }}</span>
                    </div>

                    <div class="grid gap-4 text-sm md:grid-cols-2">
                        <div class="space-y-1">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Who is asking</h3>
                            <p class="font-semibold">{{ claim.claimant.name }} <span class="font-normal text-slate-500">({{ claim.claimant_role }})</span></p>
                            <p><a :href="`mailto:${claim.claimant.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ claim.claimant.email }}</a>
                                <span :class="claim.claimant.email_verified ? 'text-emerald-600' : 'text-amber-600'"> · {{ claim.claimant.email_verified ? 'verified' : 'not verified' }}</span></p>
                            <p v-if="claim.phone">Phone: {{ claim.phone }}</p>
                            <p class="text-xs text-slate-500">Account since {{ claim.claimant.joined }}<span v-if="claim.claimant.other_claims"> · {{ claim.claimant.other_claims }} other claim(s)</span></p>
                            <p v-if="claim.claimant.clubs.length" class="text-xs text-slate-500">Member of: {{ claim.claimant.clubs.join(', ') }}</p>
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">What they said</h3>
                            <p class="whitespace-pre-wrap">{{ claim.message }}</p>
                            <p v-if="claim.evidence" class="whitespace-pre-wrap rounded bg-slate-50 p-2 text-xs dark:bg-slate-800/60"><strong>To help us check:</strong> {{ claim.evidence }}</p>
                        </div>
                    </div>

                    <p v-if="claim.others_waiting" class="rounded-lg bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ claim.others_waiting }} other claim(s) for this lodge are also waiting. Approving one closes the others.</p>
                    <p v-if="claim.status === 'approved' && claim.club" class="text-sm">Managed by <strong>{{ claim.club.name }}</strong> ({{ claim.decided_by }}, {{ claim.decided_at }}).</p>
                    <p v-if="claim.decision_note && claim.status !== 'approved'" class="text-sm"><strong>Note:</strong> {{ claim.decision_note }}</p>

                    <ol class="space-y-1 border-l-2 border-slate-200 pl-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">
                        <li v-for="(event, i) in claim.events" :key="i"><strong>{{ EVENTS[event.type] ?? event.type }}</strong> · {{ event.actor ?? 'System' }} · {{ event.at }}<span v-if="event.note">: {{ event.note }}</span></li>
                    </ol>

                    <div v-if="claim.open" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <div v-if="!acting || acting.id !== claim.id" class="flex flex-wrap gap-2">
                            <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500" @click="open(claim, 'approve')">Approve…</button>
                            <button v-if="claim.status === 'pending'" class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-400" @click="open(claim, 'info')">Ask a question…</button>
                            <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-500" @click="open(claim, 'reject')">Refuse…</button>
                        </div>

                        <form v-else class="space-y-3" @submit.prevent="send(claim)">
                            <template v-if="acting.kind === 'approve'">
                                <label class="flex items-center gap-2 text-xs"><input v-model="form.mode" type="radio" value="new" /> Create a new club for them and make them its owner</label>
                                <label class="flex items-center gap-2 text-xs"><input v-model="form.mode" type="radio" value="link" /> Link to a club that already exists</label>
                                <div v-if="form.mode === 'link'" class="space-y-2 pl-6">
                                    <select v-model="form.club_id" required :class="inputClass"><option value="" disabled>Choose a club</option><option v-for="club in clubs" :key="club.id" :value="club.id">{{ club.name }}</option></select>
                                    <label class="flex items-center gap-2 text-xs"><input v-model="form.make_claimant_admin" type="checkbox" /> Also make them an admin of that club</label>
                                    <p v-if="errors.club_id" class="text-xs text-rose-600">{{ errors.club_id }}</p>
                                </div>
                                <textarea v-model="form.note" rows="2" placeholder="A note for the record (optional)" :class="inputClass" />
                            </template>
                            <template v-else-if="acting.kind === 'reject'">
                                <textarea v-model="form.reason" rows="3" required placeholder="Why the claim was not approved. The person will see this." :class="inputClass" />
                                <p v-if="errors.reason" class="text-xs text-rose-600">{{ errors.reason }}</p>
                            </template>
                            <template v-else>
                                <textarea v-model="form.question" rows="3" required placeholder="What you need to know. The person will see this and can reply." :class="inputClass" />
                                <p v-if="errors.question" class="text-xs text-rose-600">{{ errors.question }}</p>
                            </template>
                            <p v-if="errors.claim" class="text-xs text-rose-600">{{ errors.claim }}</p>
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-500">Confirm</button>
                                <button type="button" class="rounded-md px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="acting = null">Cancel</button>
                            </div>
                        </form>
                    </div>
                </li>
            </ul>

            <nav v-if="claims.links.length > 3" class="flex flex-wrap gap-1.5" aria-label="Pages">
                <template v-for="link in claims.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll :class="['rounded-md border px-2.5 py-1 text-xs', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800']" v-html="link.label" />
                    <span v-else class="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-400 dark:border-slate-800" v-html="link.label" />
                </template>
            </nav>
        </div>
    </SuperAdminLayout>
</template>
