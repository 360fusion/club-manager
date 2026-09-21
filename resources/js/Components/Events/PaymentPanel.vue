<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    clubSlug: { type: String, required: true },
    eventId: { type: Number, required: true },
    registrationId: { type: Number, required: true },
    name: { type: String, default: '' },
});

const emit = defineEmits(['close', 'changed']);

const loading = ref(true);
const data = ref(null);
const errors = ref({});
const busy = ref(false);

const params = { clubSlug: props.clubSlug, id: props.eventId, registrationId: props.registrationId };

const load = async () => {
    const response = await fetch(route('admin.events.payment.history', params), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    data.value = response.ok ? await response.json() : null;
    loading.value = false;
};

onMounted(load);

const today = new Date().toISOString().slice(0, 10);
const paid = ref({ amount: '', method: '', received_on: today, comment: '' });
const reason = ref('');
const refundAmount = ref('');
const mode = ref(null); // 'unpaid' | 'waive' | 'refund'

const post = (name, payload) => {
    busy.value = true;
    errors.value = {};
    router.post(route(`admin.events.payment.${name}`, params), payload, {
        preserveScroll: true,
        onError: (e) => { errors.value = e; },
        onSuccess: async () => {
            mode.value = null;
            reason.value = '';
            paid.value = { amount: '', method: '', received_on: today, comment: '' };
            await load();
            emit('changed');
        },
        onFinish: () => { busy.value = false; },
    });
};

const submitPaid = () => post('paid', { ...paid.value, amount: paid.value.amount || null, method: paid.value.method || data.value?.payment?.method_label || null });
const submitReason = () => post(mode.value === 'refund' ? 'refund' : mode.value, { comment: reason.value, ...(mode.value === 'refund' && refundAmount.value ? { amount: refundAmount.value } : {}) });

const ACTIONS = {
    marked_paid: ['Marked as paid', 'text-emerald-600'],
    marked_part_paid: ['Part payment recorded', 'text-amber-600'],
    marked_unpaid: ['Payment taken back', 'text-rose-600'],
    waived: ['Charge waived', 'text-blue-600'],
    refunded: ['Refunded', 'text-rose-600'],
    stripe_paid: ['Paid online', 'text-emerald-600'],
    reconciled: ['Matched to a bank line', 'text-emerald-600'],
};

const input = 'w-full px-3 py-2 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500';
const label = 'block text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1';
</script>

<template>
    <Modal :open="true" size="lg" title="Payment" :subtitle="name" @close="emit('close')">
        <div v-if="loading" class="py-8 text-center text-xs text-slate-500">Loading...</div>
        <div v-else-if="!data" class="py-8 text-center text-xs text-rose-600">You don't have access to payment details.</div>

        <div v-else class="space-y-5 text-xs">
            <!-- Summary -->
            <div class="grid grid-cols-3 gap-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 p-3">
                <div><div :class="label">Total</div><div class="font-mono text-sm font-bold">{{ formatMoney(data.payment.total) }}</div></div>
                <div><div :class="label">Paid</div><div class="font-mono text-sm font-bold text-emerald-600">{{ formatMoney(data.payment.amount_paid) }}</div></div>
                <div><div :class="label">Still to pay</div><div class="font-mono text-sm font-bold">{{ formatMoney(data.payment.balance) }}</div></div>
                <div class="col-span-3 text-slate-500 dark:text-slate-400">
                    <span v-if="data.payment.method_label">Chose: {{ data.payment.method_label }}</span>
                    <span v-if="data.payment.reference"> · Reference <span class="font-mono font-semibold">{{ data.payment.reference }}</span></span>
                    <span v-if="data.payment.due_at"> · Due {{ data.payment.due_at }}</span>
                </div>
            </div>

            <!-- History -->
            <div>
                <h3 class="mb-2 text-sm font-bold text-slate-900 dark:text-white">History</h3>
                <p v-if="!data.history.length" class="text-slate-500 dark:text-slate-400">Nothing recorded yet.</p>
                <ol v-else class="space-y-2">
                    <li v-for="row in data.history" :key="row.id" class="rounded-lg border border-slate-200 dark:border-slate-800 p-2.5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span :class="['font-bold', (ACTIONS[row.action] ?? ['', ''])[1]]">{{ (ACTIONS[row.action] ?? [row.action])[0] }} <span v-if="Number(row.amount) > 0" class="font-mono">{{ formatMoney(row.amount) }}</span></span>
                            <span class="text-[10px] text-slate-500">{{ row.at }}</span>
                        </div>
                        <div class="text-slate-500 dark:text-slate-400">
                            By {{ row.by }}<span v-if="row.method"> · {{ row.method }}</span><span v-if="row.received_on"> · received {{ row.received_on }}</span>
                        </div>
                        <div v-if="row.comment" class="mt-1 rounded bg-slate-50 dark:bg-slate-800/50 px-2 py-1 text-slate-700 dark:text-slate-200">"{{ row.comment }}"</div>
                    </li>
                </ol>
            </div>

            <p v-if="errors.payment || errors.amount || errors.comment" class="rounded-lg bg-rose-500/10 p-2 font-semibold text-rose-600" role="alert">{{ errors.payment || errors.amount || errors.comment }}</p>

            <!-- Record a payment -->
            <form v-if="Number(data.payment.balance) > 0 && !['waived', 'refunded'].includes(data.payment.status)" class="space-y-3 rounded-xl border border-slate-200 dark:border-slate-800 p-3" @submit.prevent="submitPaid">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Record a payment</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label :class="label">Amount received</label><input v-model="paid.amount" type="number" step="0.01" min="0.01" :placeholder="data.payment.balance" :class="input" /></div>
                    <div><label :class="label">How</label><input v-model="paid.method" type="text" maxlength="100" :placeholder="data.payment.method_label || 'Bank transfer'" :class="input" /></div>
                    <div><label :class="label">Date received</label><input v-model="paid.received_on" type="date" :class="input" /></div>
                </div>
                <div><label :class="label">Comment (optional)</label><input v-model="paid.comment" type="text" maxlength="1000" placeholder="e.g. Checked the statement, reference J SMITH" :class="input" /></div>
                <button type="submit" :disabled="busy" class="rounded-lg bg-emerald-600 px-4 py-2 font-bold text-white hover:bg-emerald-700 disabled:opacity-60">Mark as paid</button>
                <span class="ml-2 text-slate-500 dark:text-slate-400">Leave the amount blank to mark the whole balance as paid.</span>
            </form>

            <!-- Change it back -->
            <div class="space-y-2">
                <div class="flex flex-wrap gap-2">
                    <button v-if="Number(data.payment.amount_paid) > 0" type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-semibold" @click="mode = 'unpaid'">Take payment back</button>
                    <button v-if="Number(data.payment.amount_paid) > 0 && data.can_refund" type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-semibold" @click="mode = 'refund'">Refund</button>
                    <button v-if="Number(data.payment.amount_paid) === 0 && Number(data.payment.total) > 0 && data.payment.status === 'unpaid'" type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 font-semibold" @click="mode = 'waive'">Waive the charge</button>
                </div>
                <form v-if="mode" class="space-y-2 rounded-xl border border-amber-300/60 bg-amber-50/50 dark:bg-amber-950/20 p-3" @submit.prevent="submitReason">
                    <label :class="label">Why? (kept in the history)</label>
                    <input v-model="reason" type="text" required minlength="3" maxlength="1000" :class="input" placeholder="e.g. Recorded against the wrong booking" />
                    <div v-if="mode === 'refund'"><label :class="label">Amount to refund (blank = everything paid)</label><input v-model="refundAmount" type="number" step="0.01" min="0.01" :class="input" /></div>
                    <div class="flex gap-2">
                        <button type="submit" :disabled="busy" class="rounded-lg bg-rose-600 px-4 py-2 font-bold text-white hover:bg-rose-700 disabled:opacity-60">{{ { unpaid: 'Take payment back', waive: 'Waive the charge', refund: 'Record refund' }[mode] }}</button>
                        <button type="button" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 font-semibold" @click="mode = null">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template>
