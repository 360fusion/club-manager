<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import MediaBrowser from '../../../Components/MediaBrowser.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    workspace: { type: Object, required: true },
    tree: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="$t(':team media', { team: workspace.name })" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t(':team: Media', { team: workspace.name }) }}</h1>
            <p class="lp-muted">{{ $t('Anyone in the team can add, tag and move files. Only the person who added one, or a team manager, can delete it.') }}</p>
            <MediaBrowser :team="workspace.slug" mode="page" :tree="tree" />
        </div>
    </AccountShell>
</template>
