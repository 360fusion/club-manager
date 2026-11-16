<script setup>
import { Head } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';

defineProps({ events: { type: Array, default: () => [] } });

const when = (iso) => new Date(iso).toLocaleString(locale(), { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head :title="$t('Activity')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Activity') }}</h1>
            <p class="lp-muted">{{ $t('Your latest sign-ins and changes to how you sign in. If something here is not you, change your password and turn on two-factor sign-in.') }}</p>
            <div class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('What') }}</th><th scope="col">{{ $t('Device') }}</th><th scope="col">{{ $t('Address') }}</th><th scope="col">{{ $t('When') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="(event, i) in events" :key="i">
                            <td>{{ event.label }}</td><td>{{ event.device }}</td><td>{{ event.ip ?? $t('Unknown') }}</td><td>{{ when(event.at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!events.length" class="lp-muted">{{ $t('Nothing yet.') }}</p>
        </div>
    </AccountShell>
</template>
