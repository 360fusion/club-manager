<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head :title="$t('Payment cancelled')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Nothing was charged') }}</h1>
            <p>{{ $t('You left the payment page, so your plan has not changed.') }}</p>
            <Link class="lp-btn" href="/pricing">{{ $t('Back to pricing') }}</Link>
        </div>
    </div>
</template>
