<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({ email: { type: String, required: true } });

const sent = computed(() => usePage().props.flash?.status === 'verification-link-sent');
const resend = () => router.post('/email/verification-notification');
</script>

<template>
    <Head :title="$t('Check your inbox')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Check your inbox') }}</h1>
            <p>
                <Trans k="We have sent a link to :email. Open it to confirm your address.">
                    <template #email><strong>{{ email }}</strong></template>
                </Trans>
            </p>
            <div v-if="sent" class="lp-alert" role="status">{{ $t('A new link is on its way.') }}</div>
            <button class="lp-btn" type="button" @click="resend">{{ $t('Send the link again') }}</button>
            <p class="lp-muted">{{ $t('Wrong address? Sign out and create the account again.') }}</p>
        </div>
    </div>
</template>
