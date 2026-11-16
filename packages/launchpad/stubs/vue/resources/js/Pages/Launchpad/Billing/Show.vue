<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { t } from '../../../i18n';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    plan: { type: Object, required: true },
    period: { type: String, default: null },
    seats: Number,
    paymentProblem: Boolean,
    trialEndsAt: { type: String, default: null },
    overview: { type: Object, required: true },
    canCancel: Boolean,
    changeOptions: { type: Array, default: () => [] },
    meters: { type: Array, default: () => [] },
});

// the period as the sentence "billed yearly" / "billed monthly"
const billed = computed(() => (props.period === 'annual' ? t('billed yearly') : t('billed monthly')));
const base = teamUrl(props.workspace.slug, 'billing');
const portal = () => router.post(`${base}/portal`);
const cancel = () => router.post(`${base}/cancel`);
</script>

<template>
    <Head :title="$t('Billing')" />
    <AccountShell>
        <div v-if="paymentProblem" class="lp-alert lp-alert--error" role="alert">
            <strong>{{ $t('Your last payment did not go through.') }}</strong> {{ $t('Until it does, :team has the features of the free plan.', { team: workspace.name }) }}
            <button class="lp-btn" type="button" @click="portal">{{ $t('Update your card') }}</button>
        </div>
        <div class="lp-card">
            <h1>{{ $t('Billing for :team', { team: workspace.name }) }}</h1>
            <h2>{{ $t('Your plan') }}</h2>
            <p>
                <strong>{{ plan.name }}</strong>
                <span v-if="period" class="lp-muted"> ({{ plan.perSeat ? $t(':billed, :seats', { billed: billed, seats: $tc('{1} :count seat|[2,*] :count seats', seats) }) : billed }})</span>
            </p>
            <p v-if="trialEndsAt" class="lp-alert">{{ $t('Your free trial ends on :date. After that your card is charged and the plan continues; cancel before then to pay nothing.', { date: trialEndsAt }) }}</p>
            <p v-if="overview.endsAt" class="lp-alert">{{ $t('Your plan ends on :date, then :team moves to the free plan.', { date: overview.endsAt, team: workspace.name }) }}</p>
            <p v-else-if="overview.renewsAt" class="lp-muted">{{ $t('Renews on :date.', { date: overview.renewsAt }) }}</p>
            <p v-if="overview.discount">
                {{ $t('Discount: :description.', { description: overview.discount.description }) }}
                <template v-if="overview.discount.code">{{ $t('Applied with the promo code :code.', { code: overview.discount.code }) }}</template>
                <template v-if="overview.discount.endsAt">{{ $t('It ends on :date.', { date: overview.discount.endsAt }) }}</template>
            </p>
            <p v-if="overview.card">{{ $t('Card: :brand ending :last_four', { brand: overview.card.brand.charAt(0).toUpperCase() + overview.card.brand.slice(1), last_four: overview.card.last_four }) }}</p>
            <button v-if="workspace.hasStripe" class="lp-btn lp-btn--quiet" type="button" @click="portal">{{ $t('Card, address and VAT number') }}</button>
            <button v-if="canCancel" class="lp-btn lp-btn--quiet" type="button" @click="cancel">{{ $t('Cancel plan') }}</button>
        </div>
        <div v-if="meters.length" class="lp-card">
            <h2>{{ $t('Usage') }}</h2>
            <ul class="lp-usage">
                <li v-for="m in meters" :key="m.key">
                    <template v-if="m.limit !== null">
                        {{ $t(m.monthly ? ':used of :limit :label this month' : ':used of :limit :label', m) }}
                        <meter min="0" :max="Math.max(1, m.limit)" :value="Math.min(m.used, m.limit)" :high="Math.ceil(m.limit * 0.8)" :aria-label="m.label">{{ m.used }}</meter>
                        <template v-if="m.billed"><br><span class="lp-muted">{{ $t(m.cost ? ':extra extra so far, about :cost (charged on your next invoice)' : ':extra extra so far (charged on your next invoice)', m) }}</span></template>
                    </template>
                    <template v-else>{{ $t(m.monthly ? ':used :label this month' : ':used :label', m) }}</template>
                </li>
            </ul>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Change plan') }}</h2>
            <ul>
                <li v-for="option in changeOptions" :key="`${option.plan}-${option.period}`">
                    <Link :href="`${base}/change/${option.plan}/${option.period}`">{{ option.period === 'annual' ? $t(':plan, yearly', { plan: option.name }) : $t(':plan, monthly', { plan: option.name }) }}</Link>
                    <span class="lp-muted"> {{ option.price }}</span>
                </li>
            </ul>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Invoices') }}</h2>
            <p v-for="invoice in overview.invoices" :key="invoice.number">
                {{ invoice.date }}: {{ invoice.total }} {{ invoice.paid ? '' : $t('(unpaid)') }}
                <a v-if="invoice.url" :href="invoice.url" rel="noopener">{{ $t('View :number', { number: invoice.number }) }}</a>
                <a v-if="invoice.pdf" :href="invoice.pdf" rel="noopener">{{ $t('PDF') }}</a>
            </p>
            <p v-if="!overview.invoices.length" class="lp-muted">{{ $t('No invoices yet.') }}</p>
        </div>
    </AccountShell>
</template>
