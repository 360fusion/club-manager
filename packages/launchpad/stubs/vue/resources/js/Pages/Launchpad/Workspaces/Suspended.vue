<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';

defineProps({
    workspace: { type: Object, required: true },
    billingUrl: { type: String, default: null },
});
</script>

<template>
    <Head :title="$t('Team suspended')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('This team is suspended') }}</h1>
            <p>
                <Trans k="Support has suspended :team for now, so its pages and API keys are not available.">
                    <template #team><strong>{{ workspace.name }}</strong></template>
                </Trans>
            </p>
            <p class="lp-muted">{{ $t("The team's owner can contact support to find out why and what happens next.") }}</p>
            <p>
                <Link v-if="billingUrl" class="lp-btn" :href="billingUrl">{{ $t('Go to billing') }}</Link>
                <Link class="lp-btn lp-btn--quiet" href="/workspaces">{{ $t('Your teams') }}</Link>
            </p>
        </div>
    </AccountShell>
</template>
