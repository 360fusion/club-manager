<script setup>
import { Head } from '@inertiajs/vue3';
import { teamUrl } from '../../../team';

defineProps({ workspaceSlug: { type: String, default: null } });
</script>

<template>
    <Head :title="$t('Thank you')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Thank you') }}</h1>
            <p>{{ $t('Your payment is with us. Your plan appears on the billing page within a minute or so, as soon as Stripe has told us.') }}</p>
            <a v-if="workspaceSlug" class="lp-btn" :href="teamUrl(workspaceSlug, 'billing')">{{ $t('Go to billing') }}</a>
        </div>
    </div>
</template>
