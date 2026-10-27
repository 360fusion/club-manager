<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { describePattern } from '@/Utils/lodgeDates';

const props = defineProps({
    corrections: { type: Object, required: true },
    status: { type: String, default: 'open' },
    counts: { type: Object, required: true },
    fields: { type: Object, required: true },
    halls: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
});

const TABS = [
    { key: 'open', label: 'Waiting' },
    { key: 'applied', label: 'Applied' },
    { key: 'rejected', label: 'Turned down' },
    { key: 'all', label: 'All' },
];

const STATUS = {
    pending: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300',
    applied: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    rejected: 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300',
};

// One action open at a time: { id, kind: 'apply' | 'reject' }.
const acting = ref(null);
const form = reactive({ fields: [], values: {}, note: '', reason: '' });
const errors = ref({});

// Applying offers every detail of the listing: the suggested ones ticked and filled in, the rest as
// they are now, so a hall added after the suggestion or a fix described only in the note can be applied too.
const APPLICABLE = ['meets_text', 'masonic_hall_id', 'status', 'name', 'number', 'website_url'];

function open(correction, kind) {
    acting.value = { id: correction.id, kind };
    const suggested = Object.fromEntries(correction.changes.map((change) => [change.field, change.value]));
    Object.assign(form, {
        fields: APPLICABLE.filter((field) => field in suggested),
        values: Object.fromEntries(APPLICABLE.map((field) => [field, (field in suggested ? suggested[field] : correction.listing[field]) ?? ''])),
        note: '',
        reason: '',
    });
    errors.value = {};
}

function send(correction) {
    const apply = acting.value.kind === 'apply';
    const payload = apply ? { fields: form.fields, values: form.values, note: form.note } : { reason: form.reason };

    router.post(route(apply ? 'superadmin.lodge_corrections.apply' : 'superadmin.lodge_corrections.reject', correction.id), payload, {
        preserveScroll: true,
        onSuccess: () => { acting.value = null; },
        onError: (e) => { errors.value = e; },
    });
}

const label = (field) => props.fields[field] ?? (field === 'installation_month' ? 'Installation month' : field);
const inputClass = 'w-full bg-slate-100 border border-slate-300 text-slate-900 rounded-md px-3 py-2 text-xs dark:bg-slate-800 dark:border-slate-700 dark:text-white';
</script>

<template>
    <SuperAdminLayout title="Suggested Fixes">
        <Head title="Superadmin - Suggested Fixes" />

        <div class="space-y-6">
            <div class="border-b border-slate-200 pb-5 dark:border-slate-800">
                <h1 class="text-2xl font-bold text-slate-900 tracking-tight dark:text-white">Suggested Fixes</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">People telling us a directory listing is wrong. Compare each change with the listing, adjust it if needed, then apply it or turn it down. A hall that is not on our list has to be added on the Masonic Halls page first.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link v-for="tab in TABS" :key="tab.key" :href="route('superadmin.lodge_corrections.index', { status: tab.key })" preserve-scroll
                    :class="['rounded-xl px-3 py-1.5 text-xs font-bold', status === tab.key ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300']">
                    {{ tab.label }}<span v-if="tab.key === 'open' && counts.open"> ({{ counts.open }})</span><span v-if="tab.key === 'applied'"> ({{ counts.applied }})</span>
                </Link>
            </div>

            <p v-if="!corrections.data.length" class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No suggestions here.</p>

            <ul class="space-y-4">
                <li v-for="correction in corrections.data" :key="correction.id" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ correction.lodge.name }}<span v-if="correction.lodge.number" class="font-normal text-slate-500"> · No. {{ correction.lodge.number }}</span></h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ correction.lodge.order }}<span v-if="correction.lodge.province"> · {{ correction.lodge.province }}</span></p>
                            <p class="mt-1 text-xs">
                                <Link v-if="correction.lodge.listed" :href="route('lodges.show', correction.lodge.slug)" class="text-blue-600 hover:underline dark:text-blue-400">Public page</Link>
                                <span v-else class="text-slate-500">No longer listed</span>
                                <template v-if="correction.lodge.club"> · <span class="rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900 dark:bg-amber-950 dark:text-amber-200">Managed by {{ correction.lodge.club.name }}</span></template>
                            </p>
                        </div>
                        <span :class="['rounded-full px-2.5 py-1 text-[11px] font-bold', STATUS[correction.status]]">{{ correction.status }}</span>
                    </div>

                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        From <strong class="text-slate-700 dark:text-slate-200">{{ correction.suggester.name }}</strong> ·
                        <a :href="`mailto:${correction.suggester.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ correction.suggester.email }}</a>
                        <span :class="correction.suggester.email_verified ? 'text-emerald-600' : 'text-amber-600'"> ({{ correction.suggester.email_verified ? 'verified' : 'not verified' }})</span>
                        · account since {{ correction.suggester.joined }} · sent {{ correction.created_at }}
                    </p>

                    <table v-if="correction.open || !correction.applied.length" class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-1 pr-3">Detail</th><th class="py-1 pr-3">Listing now</th><th class="py-1">Suggested</th></tr></thead>
                        <tbody>
                            <tr v-for="change in correction.changes" :key="change.field" class="border-t border-slate-100 align-top dark:border-slate-800">
                                <td class="py-2 pr-3 font-semibold">{{ label(change.field) }}</td>
                                <td class="whitespace-pre-wrap py-2 pr-3 text-slate-500">{{ change.current ?? '—' }}</td>
                                <td class="whitespace-pre-wrap py-2">{{ change.shown ?? '(remove it)' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-else class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500"><tr><th class="py-1 pr-3">Detail</th><th class="py-1 pr-3">Was</th><th class="py-1">Now</th></tr></thead>
                        <tbody>
                            <tr v-for="change in correction.applied" :key="change.field" class="border-t border-slate-100 align-top dark:border-slate-800">
                                <td class="py-2 pr-3 font-semibold">{{ label(change.field) }}</td>
                                <td class="whitespace-pre-wrap py-2 pr-3 text-slate-500">{{ change.from ?? '—' }}</td>
                                <td class="whitespace-pre-wrap py-2">{{ change.to ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="correction.open && correction.parsed" class="rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                        <p class="font-semibold">What we read from the suggested wording</p>
                        <ul v-if="correction.parsed.patterns.length" class="mt-1 list-disc pl-5">
                            <li v-for="(pattern, i) in correction.parsed.patterns" :key="i">{{ describePattern(pattern) }}<span v-if="pattern.start_time"> at {{ pattern.start_time }}</span></li>
                        </ul>
                        <p v-else class="mt-1 text-rose-600">No meeting pattern could be read. Edit the wording before applying, or untick it.</p>
                        <p v-if="correction.parsed.installation_month" class="mt-1">Installation: {{ correction.parsed.installation_month }}</p>
                        <p class="mt-1 text-slate-500">Applying replaces all of the lodge's meeting patterns. If you edit the wording below, it is read again when you apply.</p>
                    </div>

                    <p v-if="correction.note" class="whitespace-pre-wrap rounded bg-slate-50 p-2 text-sm dark:bg-slate-800/60"><strong>Their note:</strong> {{ correction.note }}</p>
                    <p v-if="correction.others_waiting" class="rounded-lg bg-amber-50 p-2 text-xs text-amber-900 dark:bg-amber-950/30 dark:text-amber-200">{{ correction.others_waiting }} other suggestion(s) for this lodge are also waiting.</p>
                    <p v-if="!correction.open" class="text-sm">{{ correction.status === 'applied' ? 'Applied' : 'Turned down' }} by {{ correction.decided_by ?? 'someone' }}, {{ correction.decided_at }}<span v-if="correction.decision_note">: {{ correction.decision_note }}</span></p>

                    <div v-if="correction.open" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                        <div v-if="!acting || acting.id !== correction.id" class="flex flex-wrap gap-2">
                            <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-500" @click="open(correction, 'apply')">Apply…</button>
                            <button class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-rose-500" @click="open(correction, 'reject')">Turn down…</button>
                        </div>

                        <form v-else class="space-y-3" @submit.prevent="send(correction)">
                            <template v-if="acting.kind === 'apply'">
                                <div v-for="field in APPLICABLE" :key="field" class="flex items-start gap-2">
                                    <input :id="`apply-${correction.id}-${field}`" v-model="form.fields" type="checkbox" :value="field" class="mt-2" />
                                    <div class="flex-1 space-y-1">
                                        <label :for="`apply-${correction.id}-${field}`" class="text-xs font-semibold">{{ label(field) }}</label>
                                        <textarea v-if="field === 'meets_text'" v-model="form.values.meets_text" rows="2" :class="inputClass" />
                                        <select v-else-if="field === 'masonic_hall_id'" v-model="form.values.masonic_hall_id" :class="inputClass">
                                            <option v-for="hall in halls" :key="hall.id" :value="hall.id">{{ hall.name }}<template v-if="hall.town">, {{ hall.town }}</template></option>
                                        </select>
                                        <select v-else-if="field === 'status'" v-model="form.values.status" :class="inputClass">
                                            <option v-for="option in statuses" :key="option" :value="option">{{ option }}</option>
                                        </select>
                                        <input v-else v-model="form.values[field]" type="text" :class="inputClass" placeholder="(empty removes it)" />
                                        <p v-if="errors[field] || errors[`values.${field}`]" class="text-xs text-rose-600">{{ errors[field] || errors[`values.${field}`] }}</p>
                                    </div>
                                </div>
                                <p v-if="correction.changes.some((c) => c.field === 'hall_text')" class="text-xs text-slate-500">The hall they named is not on our list: add it on the Masonic Halls page, reload this page, then pick it above.</p>
                                <textarea v-model="form.note" rows="2" placeholder="A note for the record (optional)" :class="inputClass" />
                                <p v-if="errors.fields" class="text-xs text-rose-600">{{ errors.fields }}</p>
                            </template>
                            <template v-else>
                                <textarea v-model="form.reason" rows="3" required placeholder="Why it was not applied. The person will see this." :class="inputClass" />
                                <p v-if="errors.reason" class="text-xs text-rose-600">{{ errors.reason }}</p>
                            </template>
                            <p v-if="errors.correction" class="text-xs text-rose-600">{{ errors.correction }}</p>
                            <div class="flex gap-2">
                                <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-500">Confirm</button>
                                <button type="button" class="rounded-md px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="acting = null">Cancel</button>
                            </div>
                        </form>
                    </div>
                </li>
            </ul>

            <nav v-if="corrections.links.length > 3" class="flex flex-wrap gap-1.5" aria-label="Pages">
                <template v-for="link in corrections.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url" preserve-scroll :class="['rounded-md border px-2.5 py-1 text-xs', link.active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-300 hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800']"><span v-html="link.label" /></Link>
                    <span v-else class="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-400 dark:border-slate-800" v-html="link.label" />
                </template>
            </nav>
        </div>
    </SuperAdminLayout>
</template>
