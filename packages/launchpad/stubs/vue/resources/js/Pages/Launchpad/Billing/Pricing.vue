<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({ period: { type: String, required: true }, plans: { type: Array, required: true }, promo: { type: String, default: null } });
</script>

<template>
    <Head :title="$t('Pricing')"><meta name="description" :content="$t('Simple plans. Change or cancel whenever you like.')"></Head>
    <div class="lp-card">
        <h1>{{ $t('Pricing') }}</h1>
        <div v-if="$page.props.errors.plan" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.plan }}</div>
        <p v-if="promo" class="lp-alert" role="status">
            <Trans k="Promo code :code will be applied when you check out, if it is still valid.">
                <template #code><strong>{{ promo }}</strong></template>
            </Trans>
        </p>
        <p role="group" :aria-label="$t('Billing period')">
            <Link class="lp-btn" :class="{ 'lp-btn--quiet': period !== 'monthly' }" href="/pricing?frequency=monthly" :aria-current="period === 'monthly' ? 'true' : null">{{ $t('Monthly') }}</Link>
            <Link class="lp-btn" :class="{ 'lp-btn--quiet': period !== 'annual' }" href="/pricing?frequency=annual" :aria-current="period === 'annual' ? 'true' : null">{{ $t('Yearly') }}</Link>
        </p>
    </div>
    <div class="lp-plans">
        <section v-for="plan in plans" :key="plan.key" class="lp-card" :aria-labelledby="`plan-${plan.key}`">
            <h2 :id="`plan-${plan.key}`">{{ plan.name }}</h2>
            <p class="lp-muted">{{ plan.description }}</p>
            <p class="lp-price">
                <template v-if="plan.free">{{ $t('Free') }}</template>
                <template v-else-if="plan.price">
                    {{ plan.price }}
                    <span class="lp-muted">{{ plan.perSeat ? (period === 'annual' ? $t('per person a year') : $t('per person a month')) : (period === 'annual' ? $t('a year') : $t('a month')) }}</span>
                    <template v-if="plan.perSeat && plan.minSeats > 1"><br><span class="lp-muted">{{ $tc('{1} Minimum :count seat|[2,*] Minimum :count seats', plan.minSeats) }}</span></template>
                </template>
            </p>
            <p v-if="plan.trialDays > 0 && !plan.free"><strong>{{ $tc('{1} :count-day free trial|[2,*] :count-day free trial', plan.trialDays) }}</strong></p>
            <Link v-if="plan.free" class="lp-btn lp-btn--quiet" href="/register">{{ $t('Start free') }}</Link>
            <span v-else-if="plan.held" class="lp-btn lp-btn--quiet" aria-disabled="true">{{ $t('Coming soon') }}</span>
            <a v-else-if="plan.onSale" class="lp-btn" :href="plan.href">{{ plan.trialDays > 0 ? $t('Start free trial') : $t('Choose :plan', { plan: plan.name }) }}</a>
            <span v-else class="lp-muted">{{ $t('Not available for this period.') }}</span>
        </section>
    </div>
</template>
