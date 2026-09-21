<script setup>
import { computed } from 'vue';
import { formatMoney } from '@/Utils/currency';

const props = defineProps({
    // The response from the quote endpoint: { charged, options, quote }.
    quote: { type: Object, default: null },
    modelValue: { type: [Number, null], default: null },
    // 'light' for the members area, 'dark' for the public pages.
    tone: { type: String, default: 'light' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const dark = computed(() => props.tone === 'dark');
const options = computed(() => props.quote?.options ?? []);
const selected = computed(() => options.value.find((o) => o.id === props.modelValue) ?? null);
// With options, show the chosen one's breakdown; without, the single price.
const breakdown = computed(() => selected.value?.quote ?? props.quote?.quote ?? null);

const box = computed(() => (dark.value ? 'border-slate-800 bg-slate-950/60 text-slate-200' : 'border-slate-200 bg-slate-50 text-slate-800 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-200'));
const muted = computed(() => (dark.value ? 'text-slate-400' : 'text-slate-500 dark:text-slate-400'));
const strong = computed(() => (dark.value ? 'text-white' : 'text-slate-900 dark:text-white'));

const adjustmentLabel = (b) => (b.method_adjustment < 0 ? `${b.method_label ?? 'Payment'} discount` : `${b.method_label ?? 'Payment'} fee`);
</script>

<template>
    <div v-if="quote && quote.charged && breakdown && breakdown.total >= 0" class="space-y-3">
        <p v-if="error" class="rounded-lg bg-rose-500/10 p-2 text-xs font-semibold text-rose-500" role="alert">{{ error }}</p>

        <div v-if="options.length" class="space-y-2" role="radiogroup" aria-label="How would you like to pay?">
            <h4 :class="['text-[11px] font-bold uppercase tracking-wider', muted]">How would you like to pay?</h4>
            <label v-for="option in options" :key="option.id"
                :class="['flex cursor-pointer items-start gap-3 rounded-xl border p-3 text-xs transition-all', modelValue === option.id ? 'border-blue-500 ring-1 ring-blue-500/40' : (dark ? 'border-slate-800' : 'border-slate-200 dark:border-slate-800'), box]">
                <input :checked="modelValue === option.id" type="radio" name="payment-option" class="mt-0.5" @change="emit('update:modelValue', option.id)" />
                <span class="flex-1">
                    <span :class="['flex items-center justify-between gap-2 font-bold', strong]">
                        {{ option.label }}
                        <span class="font-mono">{{ formatMoney(option.total) }}</span>
                    </span>
                    <span v-if="option.saving > 0" class="mt-0.5 block font-semibold text-emerald-600">Save {{ formatMoney(option.saving) }} compared with the dearest option</span>
                    <span v-if="option.instructions" :class="['mt-0.5 block', muted]">{{ option.instructions }}</span>
                    <span v-if="option.type === 'paypal'" :class="['mt-0.5 block', muted]">You'll be taken to PayPal to pay securely, then brought back here.</span>
                    <span v-if="option.type === 'card_online'" :class="['mt-0.5 block', muted]">You'll be taken to a secure card payment page, then brought back here.</span>
                    <span v-if="option.type === 'bank_transfer'" :class="['mt-0.5 block', muted]">You'll get the bank details and your payment reference once you've booked.</span>
                </span>
            </label>
        </div>

        <div v-if="breakdown" :class="['space-y-1 rounded-xl border p-3 text-xs', box]">
            <div class="flex justify-between"><span :class="muted">Tickets{{ breakdown.people.some((p) => p.dining > 0) ? ' and dinner' : '' }}</span><span class="font-mono">{{ formatMoney(breakdown.subtotal) }}</span></div>
            <div v-if="breakdown.promo_discount > 0" class="flex justify-between"><span :class="muted">Promo {{ breakdown.promo_code }}</span><span class="font-mono text-emerald-600">-{{ formatMoney(breakdown.promo_discount) }}</span></div>
            <div v-if="breakdown.method_adjustment !== 0" class="flex justify-between"><span :class="muted">{{ adjustmentLabel(breakdown) }}</span><span :class="['font-mono', breakdown.method_adjustment < 0 ? 'text-emerald-600' : '']">{{ breakdown.method_adjustment < 0 ? '-' : '+' }}{{ formatMoney(Math.abs(breakdown.method_adjustment)) }}</span></div>
            <div v-if="breakdown.booking_fee > 0" class="flex justify-between"><span :class="muted">{{ breakdown.booking_fee_label }}</span><span class="font-mono">{{ formatMoney(breakdown.booking_fee) }}</span></div>
            <div :class="['flex justify-between border-t pt-1.5 text-sm font-bold', dark ? 'border-slate-800' : 'border-slate-200 dark:border-slate-700', strong]"><span>Total</span><span class="font-mono">{{ formatMoney(breakdown.total) }}</span></div>
        </div>
    </div>
</template>
