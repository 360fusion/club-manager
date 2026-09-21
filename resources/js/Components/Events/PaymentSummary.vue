<script setup>
import { computed } from 'vue';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    payment: { type: Object, required: true },
    tone: { type: String, default: 'light' },
    paying: { type: Boolean, default: false },
});

const emit = defineEmits(['pay']);

const dark = computed(() => props.tone === 'dark');
const owes = computed(() => Number(props.payment.balance) > 0);
const box = computed(() => (dark.value ? 'border-slate-800 bg-slate-950/60 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-200'));
const muted = computed(() => (dark.value ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'));

const STATUS = {
    paid: ['Paid', 'bg-emerald-500/15 text-emerald-600'],
    part_paid: ['Part paid', 'bg-amber-500/15 text-amber-600'],
    unpaid: ['Not paid yet', 'bg-amber-500/15 text-amber-600'],
    waived: ['No charge', 'bg-blue-500/15 text-blue-600'],
    refunded: ['Refunded', 'bg-rose-500/15 text-rose-600'],
};
</script>

<template>
    <div v-if="Number(payment.total) > 0" :class="['space-y-2 rounded-xl border p-3 text-xs', box]">
        <div class="flex items-center justify-between gap-2">
            <span class="font-bold">Payment</span>
            <span :class="['rounded px-2 py-0.5 text-[10px] font-bold uppercase', (STATUS[payment.status] ?? STATUS.unpaid)[1]]">{{ (STATUS[payment.status] ?? STATUS.unpaid)[0] }}</span>
        </div>
        <div class="flex justify-between"><span :class="muted">Total</span><span class="font-mono font-semibold">{{ formatMoney(payment.total) }}</span></div>
        <div v-if="Number(payment.amount_paid) > 0" class="flex justify-between"><span :class="muted">Paid so far</span><span class="font-mono">{{ formatMoney(payment.amount_paid) }}</span></div>
        <div v-if="owes && payment.status !== 'waived'" class="flex justify-between"><span :class="muted">Still to pay</span><span class="font-mono font-bold">{{ formatMoney(payment.balance) }}</span></div>

        <template v-if="owes && payment.status !== 'waived'">
            <p v-if="payment.method_label" :class="muted">Paying by: <span class="font-semibold">{{ payment.method_label }}</span><template v-if="payment.due_at"> · due by {{ payment.due_at }}</template></p>
            <p v-if="payment.instructions" :class="muted">{{ payment.instructions }}</p>

            <div v-if="payment.bank" :class="['rounded-lg border p-2', dark ? 'border-slate-800' : 'border-slate-200 dark:border-slate-700']">
                <div class="font-bold">Pay by bank transfer</div>
                <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5">
                    <template v-if="payment.bank.account_name"><dt :class="muted">Account name</dt><dd class="font-semibold">{{ payment.bank.account_name }}</dd></template>
                    <template v-if="payment.bank.bank_name"><dt :class="muted">Bank</dt><dd class="font-semibold">{{ payment.bank.bank_name }}</dd></template>
                    <template v-if="payment.bank.sort_code"><dt :class="muted">{{ payment.bank.code_label || 'Sort code' }}</dt><dd class="font-mono font-semibold">{{ payment.bank.sort_code }}</dd></template>
                    <template v-if="payment.bank.account_number"><dt :class="muted">Account number</dt><dd class="font-mono font-semibold">{{ payment.bank.account_number }}</dd></template>
                    <template v-if="payment.bank.iban"><dt :class="muted">IBAN</dt><dd class="font-mono font-semibold">{{ payment.bank.iban }}</dd></template>
                    <dt :class="muted">Reference</dt><dd class="font-mono font-bold">{{ payment.reference }}</dd>
                </dl>
                <p :class="['mt-1', muted]">Please use the reference exactly, so we can match your payment.</p>
            </div>
            <p v-else-if="payment.method_type === 'cash_on_door'" :class="muted">Pay when you arrive.</p>

            <template v-if="payment.online?.can_pay">
                <button v-for="offer in payment.online.options" :key="offer.id" type="button" :disabled="paying" class="w-full rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-500 disabled:opacity-60" @click="emit('pay', offer.id)">
                    {{ paying ? 'Taking you to the payment page...' : `Pay ${formatMoney(offer.total)} now ${offer.type === 'paypal' ? 'with PayPal' : 'by card'}` }}
                    <span v-if="offer.saving > 0" class="ml-1 font-normal opacity-90">(save {{ formatMoney(offer.saving) }})</span>
                </button>
            </template>
        </template>
    </div>
</template>
