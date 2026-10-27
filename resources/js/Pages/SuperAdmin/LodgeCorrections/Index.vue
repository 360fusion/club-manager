<script setup>
import { ref, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Tabs from '@/Components/Ui/Tabs.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Pagination from '@/Components/Ui/Pagination.vue';
import { describePattern } from '@/Utils/lodgeDates';

const props = defineProps({
    corrections: { type: Object, required: true },
    status: { type: String, default: 'open' },
    counts: { type: Object, required: true },
    fields: { type: Object, required: true },
    halls: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    // A provincial admin sees this screen in the members area, limited to their province (ProvinceLodgeCorrectionController).
    routes: { type: String, default: 'superadmin.lodge_corrections' },
    layout: { type: String, default: 'superadmin' },
});

const Layout = props.layout === 'members' ? MembersLayout : SuperAdminLayout;

const TABS = [
    { key: 'open', label: 'Waiting' },
    { key: 'applied', label: 'Applied' },
    { key: 'rejected', label: 'Turned down' },
    { key: 'all', label: 'All' },
];

// Badge variant for each suggestion status.
const STATUS = {
    pending: 'info',
    applied: 'success',
    rejected: 'danger',
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

    router.post(route(`${props.routes}.${apply ? 'apply' : 'reject'}`, correction.id), payload, {
        preserveScroll: true,
        onSuccess: () => { acting.value = null; },
        onError: (e) => { errors.value = e; },
    });
}

const label = (field) => props.fields[field] ?? (field === 'installation_month' ? 'Installation month' : field);
// The apply form's fields stay native: each one's label belongs to the checkbox beside it, so they are styled like the kit's Input.
const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder-slate-400 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500';
</script>

<template>
    <component :is="Layout" title="Suggested fixes" active-tab="lodges">
        <Head title="Suggested fixes" />

        <div class="space-y-6">
            <PageHeader title="Suggested fixes" description="People telling us a directory listing is wrong. Compare each change with the listing, adjust it if needed, then apply it or turn it down. A hall that is not on our list has to be added on the Masonic halls page first." />

            <Tabs
                label="Suggestions"
                :active="status"
                :items="TABS.map((tab) => ({
                    key: tab.key,
                    label: tab.label + (tab.key === 'open' && counts.open ? ` (${counts.open})` : '') + (tab.key === 'applied' ? ` (${counts.applied})` : ''),
                    href: route(`${routes}.index`, { status: tab.key }),
                }))"
            />

            <Card v-if="!corrections.data.length" class="border-dashed text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">No suggestions here.</p>
            </Card>

            <ul class="space-y-4">
                <li v-for="correction in corrections.data" :key="correction.id">
                    <Card class="space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ correction.lodge.name }}<span v-if="correction.lodge.number" class="font-normal text-slate-500 dark:text-slate-400"> · No. {{ correction.lodge.number }}</span></h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ correction.lodge.order }}<span v-if="correction.lodge.province"> · {{ correction.lodge.province }}</span></p>
                                <p class="mt-1 text-xs">
                                    <Link v-if="correction.lodge.listed" :href="route('lodges.show', correction.lodge.slug)" class="text-blue-600 hover:underline dark:text-blue-400">Public page</Link>
                                    <span v-else class="text-slate-500 dark:text-slate-400">No longer listed</span>
                                    <template v-if="correction.lodge.club"> · <Badge variant="warning">Managed by {{ correction.lodge.club.name }}</Badge></template>
                                </p>
                            </div>
                            <Badge :variant="STATUS[correction.status]" class="capitalize">{{ correction.status }}</Badge>
                        </div>

                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            From <strong class="text-slate-700 dark:text-slate-200">{{ correction.suggester.name }}</strong> ·
                            <a :href="`mailto:${correction.suggester.email}`" class="text-blue-600 hover:underline dark:text-blue-400">{{ correction.suggester.email }}</a>
                            <span :class="correction.suggester.email_verified ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"> ({{ correction.suggester.email_verified ? 'verified' : 'not verified' }})</span>
                            · account since {{ correction.suggester.joined }} · sent {{ correction.created_at }}
                        </p>

                        <table v-if="correction.open || !correction.applied.length" class="w-full text-left text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><tr><th class="py-1 pr-3">Detail</th><th class="py-1 pr-3">Listing now</th><th class="py-1">Suggested</th></tr></thead>
                            <tbody>
                                <tr v-for="change in correction.changes" :key="change.field" class="border-t border-slate-200 align-top dark:border-slate-800">
                                    <td class="py-2 pr-3 font-semibold">{{ label(change.field) }}</td>
                                    <td class="whitespace-pre-wrap py-2 pr-3 text-slate-500 dark:text-slate-400">{{ change.current ?? '—' }}</td>
                                    <td class="whitespace-pre-wrap py-2">{{ change.shown ?? '(remove it)' }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-else class="w-full text-left text-sm">
                            <thead class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"><tr><th class="py-1 pr-3">Detail</th><th class="py-1 pr-3">Was</th><th class="py-1">Now</th></tr></thead>
                            <tbody>
                                <tr v-for="change in correction.applied" :key="change.field" class="border-t border-slate-200 align-top dark:border-slate-800">
                                    <td class="py-2 pr-3 font-semibold">{{ label(change.field) }}</td>
                                    <td class="whitespace-pre-wrap py-2 pr-3 text-slate-500 dark:text-slate-400">{{ change.from ?? '—' }}</td>
                                    <td class="whitespace-pre-wrap py-2">{{ change.to ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <div v-if="correction.open && correction.parsed" class="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
                            <p class="font-semibold">What we read from the suggested wording</p>
                            <ul v-if="correction.parsed.patterns.length" class="mt-1 list-disc pl-5">
                                <li v-for="(pattern, i) in correction.parsed.patterns" :key="i">{{ describePattern(pattern) }}<span v-if="pattern.start_time"> at {{ pattern.start_time }}</span></li>
                            </ul>
                            <p v-else class="mt-1 text-rose-600 dark:text-rose-400">No meeting pattern could be read. Edit the wording before applying, or untick it.</p>
                            <p v-if="correction.parsed.installation_month" class="mt-1">Installation: {{ correction.parsed.installation_month }}</p>
                            <p class="mt-1 text-slate-500 dark:text-slate-400">Applying replaces all of the lodge's meeting patterns. If you edit the wording below, it is read again when you apply.</p>
                        </div>

                        <p v-if="correction.note" class="whitespace-pre-wrap rounded-xl bg-slate-50 p-2 text-sm dark:bg-slate-800/60"><strong>Their note:</strong> {{ correction.note }}</p>
                        <Alert v-if="correction.others_waiting" variant="warning">{{ correction.others_waiting }} other suggestion(s) for this lodge are also waiting.</Alert>
                        <p v-if="!correction.open" class="text-sm">{{ correction.status === 'applied' ? 'Applied' : 'Turned down' }} by {{ correction.decided_by ?? 'someone' }}, {{ correction.decided_at }}<span v-if="correction.decision_note">: {{ correction.decision_note }}</span></p>

                        <div v-if="correction.open" class="space-y-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <div v-if="!acting || acting.id !== correction.id" class="flex flex-wrap gap-2">
                                <Button size="sm" @click="open(correction, 'apply')">Apply…</Button>
                                <Button variant="secondary" size="sm" @click="open(correction, 'reject')">Turn down…</Button>
                            </div>

                            <form v-else class="space-y-3" @submit.prevent="send(correction)">
                                <template v-if="acting.kind === 'apply'">
                                    <div v-for="field in APPLICABLE" :key="field" class="flex items-start gap-2">
                                        <input :id="`apply-${correction.id}-${field}`" v-model="form.fields" type="checkbox" :value="field" class="mt-2 rounded text-blue-600 focus:ring-blue-500" />
                                        <div class="flex-1 space-y-1">
                                            <label :for="`apply-${correction.id}-${field}`" class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ label(field) }}</label>
                                            <textarea v-if="field === 'meets_text'" v-model="form.values.meets_text" rows="2" :class="inputClass" />
                                            <select v-else-if="field === 'masonic_hall_id'" v-model="form.values.masonic_hall_id" :class="inputClass">
                                                <option v-for="hall in halls" :key="hall.id" :value="hall.id">{{ hall.name }}<template v-if="hall.town">, {{ hall.town }}</template></option>
                                            </select>
                                            <select v-else-if="field === 'status'" v-model="form.values.status" :class="inputClass">
                                                <option v-for="option in statuses" :key="option" :value="option">{{ option }}</option>
                                            </select>
                                            <input v-else v-model="form.values[field]" type="text" :class="inputClass" placeholder="(empty removes it)" />
                                            <p v-if="errors[field] || errors[`values.${field}`]" class="text-xs text-rose-600 dark:text-rose-400">{{ errors[field] || errors[`values.${field}`] }}</p>
                                        </div>
                                    </div>
                                    <p v-if="correction.changes.some((c) => c.field === 'hall_text')" class="text-xs text-slate-500 dark:text-slate-400">The hall they named is not on our list: {{ layout === 'members' ? 'ask the platform team to add it, then pick it above' : 'add it on the Masonic halls page, reload this page, then pick it above' }}.</p>
                                    <Textarea v-model="form.note" :rows="2" aria-label="Note" placeholder="A note for the record (optional)" />
                                    <p v-if="errors.fields" class="text-xs text-rose-600 dark:text-rose-400">{{ errors.fields }}</p>
                                </template>
                                <template v-else>
                                    <Textarea v-model="form.reason" :rows="3" required aria-label="Reason" placeholder="Why it was not applied. The person will see this." :error="errors.reason" />
                                </template>
                                <p v-if="errors.correction" class="text-xs text-rose-600 dark:text-rose-400">{{ errors.correction }}</p>
                                <div class="flex gap-2">
                                    <Button type="submit" size="sm">Confirm</Button>
                                    <Button variant="ghost" size="sm" @click="acting = null">Cancel</Button>
                                </div>
                            </form>
                        </div>
                    </Card>
                </li>
            </ul>

            <Pagination :links="corrections.links" />
        </div>
    </component>
</template>
