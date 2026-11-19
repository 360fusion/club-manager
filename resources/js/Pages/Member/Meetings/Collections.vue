<script setup>
// Alms and raffle for one meeting, entered on a phone by whoever counted them (MeetingCollectionController).
import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    club: { type: Object, required: true },
    meeting: { type: Object, required: true },
    entries: { type: Array, required: true },
    total: { type: String, required: true },
    people: { type: Array, required: true },
});

// Signed in at the door first, so tonight's counters are at the top.
const peopleOptions = computed(() => [
    { value: '', label: 'Choose' },
    ...props.people.map((person) => ({ value: person.id, label: person.signed_in ? `${person.name} (here tonight)` : person.name })),
]);

const forms = reactive(Object.fromEntries(props.entries.map((entry) => [entry.type, useForm({
    type: entry.type,
    cash_amount: entry.cash_amount ?? '',
    cheque_amount: entry.cheque_amount ?? '',
    card_amount: entry.card_amount ?? '',
    counted_by_member_id: entry.counted_by_member_id ?? '',
    witnessed_by_member_id: entry.witnessed_by_member_id ?? '',
    notes: entry.notes ?? '',
})])));

const subtotal = (form) => (Number(form.cash_amount) || 0) + (Number(form.cheque_amount) || 0) + (Number(form.card_amount) || 0);

const save = (type) => forms[type]
    .transform((data) => ({ ...data, counted_by_member_id: data.counted_by_member_id || null, witnessed_by_member_id: data.witnessed_by_member_id || null }))
    .post(route('member.meetings.collections.store', { slug: props.club.slug, id: props.meeting.id }), { preserveScroll: true });
</script>

<template>
    <MembersLayout title="Alms and raffle" :club="club" active-tab="meetings">
        <div class="mx-auto max-w-xl space-y-5">
            <div>
                <Link :href="route('member.meetings.summons', { slug: club.slug, id: meeting.id })" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to the meeting</Link>
                <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900 dark:text-white">Alms and raffle</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ meeting.title }} · {{ meeting.date }}</p>
                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">Total so far: {{ total }}</p>
            </div>

            <Card v-for="entry in entries" :key="entry.type">
                <form class="space-y-4" @submit.prevent="save(entry.type)">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ entry.label }}</h3>
                        <Badge v-if="entry.banked" variant="success">Paid into the bank</Badge>
                        <span v-else-if="entry.saved_at" class="text-xs text-slate-500 dark:text-slate-400">Saved {{ entry.saved_at }}</span>
                    </div>

                    <p v-if="entry.banked" class="text-sm text-slate-600 dark:text-slate-300">This has been paid into the bank, so it can no longer be changed here. Ask the Treasurer if something is wrong.</p>

                    <fieldset :disabled="entry.banked" class="space-y-4">
                        <div class="grid grid-cols-3 gap-2">
                            <Input v-model="forms[entry.type].cash_amount" type="number" inputmode="decimal" step="0.01" min="0" label="Cash" :prefix="$cs" :error="forms[entry.type].errors.cash_amount" />
                            <Input v-model="forms[entry.type].cheque_amount" type="number" inputmode="decimal" step="0.01" min="0" label="Cheques" :prefix="$cs" :error="forms[entry.type].errors.cheque_amount" />
                            <Input v-model="forms[entry.type].card_amount" type="number" inputmode="decimal" step="0.01" min="0" label="Card" :prefix="$cs" :error="forms[entry.type].errors.card_amount" />
                        </div>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ entry.label }} total: <strong class="text-slate-900 dark:text-white">{{ formatMoney(subtotal(forms[entry.type])) }}</strong></p>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <Select v-model.number="forms[entry.type].counted_by_member_id" :options="peopleOptions" label="Counted by" :error="forms[entry.type].errors.counted_by_member_id" />
                            <Select v-model.number="forms[entry.type].witnessed_by_member_id" :options="peopleOptions" label="Witnessed by" :error="forms[entry.type].errors.witnessed_by_member_id" />
                        </div>
                        <Input v-model="forms[entry.type].notes" label="Note (optional)" maxlength="1000" />

                        <Button type="submit" block :loading="forms[entry.type].processing">Save {{ entry.label.toLowerCase() }}</Button>
                    </fieldset>
                </form>
            </Card>

            <p class="text-xs text-slate-500 dark:text-slate-400">Cash and cheques go into the lodge's books as money waiting to be paid in. Card takings come through the card reader's own payouts.</p>
        </div>
    </MembersLayout>
</template>
