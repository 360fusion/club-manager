<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';

defineProps({
    workspace: { type: Object, required: true },
    role: { type: String, required: true },
    ownerName: { type: String, default: null },
    what: { type: String, required: true },
    ownUrl: { type: String, default: null },
    ownName: { type: String, default: null },
});
</script>

<template>
    <Head :title="$t('No permission')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('You do not have permission for this') }}</h1>
            <p>
                <Trans v-if="ownerName" k="You are signed in to :team (owned by :owner) as a :role, and your permissions there do not include the right to :what." :values="{ owner: ownerName, role: role.toLowerCase(), what }">
                    <template #team><strong>{{ workspace.name }}</strong></template>
                </Trans>
                <Trans v-else k="You are signed in to :team as a :role, and your permissions there do not include the right to :what." :values="{ role: role.toLowerCase(), what }">
                    <template #team><strong>{{ workspace.name }}</strong></template>
                </Trans>
            </p>
            <p class="lp-muted">{{ ownerName ? $t('Ask :person if you need it.', { person: ownerName }) : $t('Ask an owner or admin if you need it.') }}</p>
            <p>
                <Link v-if="ownUrl" class="lp-btn" :href="ownUrl">{{ $t('Go to your own :team team', { team: ownName }) }}</Link>
                <Link class="lp-btn lp-btn--quiet" href="/workspaces">{{ $t('Your teams') }}</Link>
            </p>
        </div>
    </AccountShell>
</template>
