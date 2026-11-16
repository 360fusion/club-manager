<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { locale } from '../../../i18n';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    endpoints: { type: Array, default: () => [] },
    deliveries: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    newSecret: { type: String, default: null },
});

const base = teamUrl(props.workspace.slug);
const form = useForm({ url: '', description: '', events: [] });
const toggle = (endpoint) => router.put(`${base}/webhooks/${endpoint.id}`, {}, { preserveScroll: true });
const test = (endpoint) => router.post(`${base}/webhooks/${endpoint.id}/test`, {}, { preserveScroll: true });
const remove = (endpoint) => router.delete(`${base}/webhooks/${endpoint.id}`, { preserveScroll: true });
const ucfirst = (text) => text.charAt(0).toUpperCase() + text.slice(1);
</script>

<template>
    <Head :title="$t(':team webhooks', { team: workspace.name })" />
    <AccountShell>
        <div v-if="newSecret" class="lp-alert" role="alert">
            <p>
                <Trans k=":title. Copy it now: it is not shown again. Use it to check the :header header of each delivery.">
                    <template #title><strong>{{ $t('Your signing secret') }}</strong></template>
                    <template #header><code>X-Launchpad-Signature</code></template>
                </Trans>
            </p>
            <pre class="lp-code"><code>{{ newSecret }}</code></pre>
        </div>
        <div class="lp-card">
            <h1>{{ $t(':team: webhooks', { team: workspace.name }) }}</h1>
            <p class="lp-muted">{{ $t('We send a signed POST to each address below when something you chose happens in this team.') }}</p>
            <p><Link :href="`${base}/api`">{{ $t('API tokens') }}</Link></p>
            <div v-if="$page.props.errors.url && !form.errors.url" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.url }}</div>
            <p v-if="!endpoints.length">{{ $t('No endpoints yet.') }}</p>
            <div v-else class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('Address') }}</th><th scope="col">{{ $t('Events') }}</th><th scope="col">{{ $t('State') }}</th><th scope="col"><span class="lp-skip">{{ $t('Actions') }}</span></th></tr></thead>
                    <tbody>
                        <tr v-for="endpoint in endpoints" :key="endpoint.id">
                            <td>{{ endpoint.url }}<template v-if="endpoint.description"><br><span class="lp-muted">{{ endpoint.description }}</span></template></td>
                            <td>{{ endpoint.events.join(', ') }}</td>
                            <td>{{ endpoint.active ? $t('On') : $t('Off') }}</td>
                            <td>
                                <button class="lp-btn lp-btn--quiet" type="button" @click="toggle(endpoint)">{{ endpoint.active ? $t('Switch off') : $t('Switch on') }}</button>
                                <button v-if="endpoint.active" class="lp-btn lp-btn--quiet" type="button" @click="test(endpoint)">{{ $t('Send test event') }}</button>
                                <button class="lp-btn lp-btn--quiet" type="button" @click="remove(endpoint)">{{ $t('Delete') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Add an endpoint') }}</h2>
            <form @submit.prevent="form.post(`${base}/webhooks`, { onSuccess: () => form.reset() })">
                <FormField id="url" v-model="form.url" :label="$t('Address (https)')" inputmode="url" :error="form.errors.url" />
                <FormField id="description" v-model="form.description" :label="$t('Description (optional)')" :required="false" :error="form.errors.description" />
                <fieldset>
                    <legend>{{ $t('Send me') }}</legend>
                    <label v-for="event in events" :key="event.key" class="lp-check">
                        <input v-model="form.events" type="checkbox" :value="event.key"> <span>
                            <Trans k=":event (:key)" :values="{ event: event.label }">
                                <template #key><code>{{ event.key }}</code></template>
                            </Trans>
                        </span>
                    </label>
                    <p v-if="form.errors.events" class="lp-error">{{ form.errors.events }}</p>
                </fieldset>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Add endpoint') }}</button>
            </form>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Latest deliveries') }}</h2>
            <p v-if="!deliveries.length">{{ $t('Nothing sent yet.') }}</p>
            <div v-else class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('When') }}</th><th scope="col">{{ $t('Event') }}</th><th scope="col">{{ $t('Address') }}</th><th scope="col">{{ $t('Status') }}</th><th scope="col">{{ $t('Response') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="delivery in deliveries" :key="delivery.id">
                            <td>{{ new Date(delivery.created_at).toLocaleString(locale()) }}</td>
                            <td>{{ delivery.event }}</td>
                            <td>{{ delivery.url }}</td>
                            <td>{{ { delivered: $t('Delivered'), failed: $t('Failed'), pending: $t('Pending') }[delivery.status] ?? ucfirst(delivery.status) }}<template v-if="delivery.attempts > 1"> {{ $tc('{1} (:count try)|[2,*] (:count tries)', delivery.attempts) }}</template></td>
                            <td>{{ delivery.response_code ?? delivery.last_error ?? '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AccountShell>
</template>
