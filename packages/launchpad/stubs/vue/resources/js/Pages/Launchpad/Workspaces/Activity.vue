<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    workspace: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    newerUrl: { type: String, default: null },
    olderUrl: { type: String, default: null },
    downloadUrl: { type: String, default: null },
});

const when = (value) => new Date(value).toLocaleString(locale());
</script>

<template>
    <Head :title="$t(':team activity', { team: workspace.name })" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t(':team: Team activity', { team: workspace.name }) }}</h1>
            <p class="lp-muted">{{ $t("Changes to the team's people, roles, API, webhooks, files and plan, newest first.") }} {{ $t('The lines below are a log: they are written once, in English.') }}</p>
            <p v-if="!events.length">{{ $t('Nothing yet.') }}</p>
            <div v-else class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('What happened') }}</th><th scope="col">{{ $t('When') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id">
                            <td>{{ event.text }}</td>
                            <td><time :datetime="event.at">{{ when(event.at) }}</time></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="events.length"><a class="lp-btn lp-btn--quiet" :href="downloadUrl">{{ $t('Download all (CSV)') }}</a></p>
            <p>
                <Link v-if="newerUrl" :href="newerUrl">{{ $t('Newer') }}</Link>
                <Link v-if="olderUrl" :href="olderUrl">{{ $t('Older') }}</Link>
            </p>
        </div>
    </AccountShell>
</template>
