<script setup>
// Import a bank statement from a phone: choose the file the banking app exported (saved to Files), see what was read,
// then import. Nothing is added until "Import these lines" (MemberAccountsController).
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Select from '@/Components/Ui/Select.vue';
import Button from '@/Components/Ui/Button.vue';
import Badge from '@/Components/Ui/Badge.vue';

const props = defineProps({
    club: { type: Object, required: true },
    bankAccounts: { type: Array, default: () => [] },
    recent: { type: Array, default: () => [] },
    preview: { type: Object, default: null },
});

const form = useForm({
    statement_file: null,
    bank_account_id: props.bankAccounts.length === 1 ? props.bankAccounts[0].id : '',
});
const fileName = ref('');
const chooseFile = (event) => {
    form.statement_file = event.target.files?.[0] ?? null;
    fileName.value = form.statement_file?.name ?? '';
};
const read = () => form.post(route('member.accounts.import.preview', { slug: props.club.slug }), { forceFormData: true });

const accountOptions = computed(() => [{ value: '', label: 'Choose an account' }, ...props.bankAccounts.map((account) => ({ value: account.id, label: account.name }))]);

const importing = ref(false);
const confirm = () => router.post(route('member.accounts.import.store', { slug: props.club.slug }), { token: props.preview.token }, {
    onStart: () => { importing.value = true; },
    onFinish: () => { importing.value = false; },
});
const startAgain = () => router.get(route('member.accounts.import', { slug: props.club.slug }));
</script>

<template>
    <MembersLayout title="Import a statement" :club="club" active-tab="lodges">
        <div class="mx-auto max-w-xl space-y-5">
            <div>
                <Link :href="route('member.accounts', { slug: club.slug })" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Back to the accounts</Link>
                <h2 class="mt-2 font-display text-2xl font-semibold text-slate-900 dark:text-white">Import a statement</h2>
            </div>

            <template v-if="!preview">
                <Card class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                    <p>In your banking app, export the statement as <strong>CSV</strong> or <strong>OFX</strong> (often under Statements, then Export or Download), and use Share, then Save to Files.</p>
                    <p>Then choose that file here. You'll see what was read before anything is imported, and lines already imported are skipped.</p>
                </Card>

                <Card>
                    <form class="space-y-4" @submit.prevent="read">
                        <Select v-if="bankAccounts.length > 1" v-model.number="form.bank_account_id" :options="accountOptions" label="Bank account" :error="form.errors.bank_account_id" />
                        <div>
                            <label for="statement-file" class="flex min-h-12 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-slate-300 px-4 text-sm font-semibold text-slate-700 hover:border-blue-400 dark:border-slate-700 dark:text-slate-200">
                                {{ fileName || 'Choose the statement file' }}
                            </label>
                            <input id="statement-file" type="file" accept=".csv,.ofx,.qfx,.txt,text/csv,application/x-ofx" class="sr-only" @change="chooseFile" />
                            <p v-if="form.errors.statement_file" class="mt-1 text-xs text-rose-700 dark:text-rose-400" role="alert">{{ form.errors.statement_file }}</p>
                        </div>
                        <Button type="submit" block :disabled="!form.statement_file" :loading="form.processing">Read the statement</Button>
                    </form>
                </Card>

                <section v-if="recent.length" class="space-y-2" aria-labelledby="recent-heading">
                    <h3 id="recent-heading" class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Recent imports</h3>
                    <Card padding="none" class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        <div v-for="item in recent" :key="item.filename + item.at" class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <span class="min-w-0 truncate">{{ item.filename }}</span>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ item.lines }} lines · {{ item.at }}</span>
                        </div>
                    </Card>
                </section>
            </template>

            <template v-else>
                <Card class="space-y-3">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        <strong class="text-slate-900 dark:text-white">{{ preview.filename }}</strong><br />
                        {{ preview.from }} to {{ preview.to }}
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="block text-xs text-slate-500 dark:text-slate-400">New lines</span><strong class="text-lg">{{ preview.new_count }}</strong></div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="block text-xs text-slate-500 dark:text-slate-400">Already imported</span><strong class="text-lg">{{ preview.duplicate_count }}</strong></div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="block text-xs text-slate-500 dark:text-slate-400">Money in</span><strong>{{ preview.money_in }}</strong></div>
                        <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800"><span class="block text-xs text-slate-500 dark:text-slate-400">Money out</span><strong>{{ preview.money_out }}</strong></div>
                    </div>
                    <p v-if="preview.skipped_count" class="text-xs text-amber-700 dark:text-amber-400">{{ preview.skipped_count }} {{ preview.skipped_count === 1 ? 'row' : 'rows' }} in the file could not be read and will be left out.</p>
                    <p v-if="!bankAccounts.length || (bankAccounts.length > 1 && !preview.bank_account_id)" class="text-xs text-amber-700 dark:text-amber-400">No bank account was chosen, so the lines will not be tied to one.</p>
                </Card>

                <Card padding="none" class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <div v-for="(line, index) in preview.lines" :key="index" :class="['flex items-start justify-between gap-3 px-4 py-2.5', line.duplicate ? 'opacity-50' : '']">
                        <span class="min-w-0">
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ line.date }}<Badge v-if="line.duplicate" variant="neutral" class="ml-2">Already imported</Badge></span>
                            <span class="block break-words">{{ line.description }}</span>
                        </span>
                        <strong :class="['shrink-0 tabular-nums', line.in ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-900 dark:text-white']">{{ line.amount }}</strong>
                    </div>
                </Card>

                <div class="flex gap-2">
                    <Button variant="secondary" block @click="startAgain">Choose another file</Button>
                    <Button block :loading="importing" :disabled="!preview.new_count" @click="confirm">Import {{ preview.new_count }} {{ preview.new_count === 1 ? 'line' : 'lines' }}</Button>
                </div>
            </template>
        </div>
    </MembersLayout>
</template>
