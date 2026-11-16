<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    tokens: { type: Array, default: () => [] },
    abilities: { type: Array, default: () => [] },
    expiries: { type: Array, default: () => [] },
    newToken: { type: String, default: null },
    mcpUrl: { type: String, default: null },
});

const base = teamUrl(props.workspace.slug);
const form = useForm({ name: '', expiry: 'never', abilities: ['read'] });
const when = (value) => (value ? new Date(value).toLocaleDateString(locale()) : null);
const revoke = (token) => router.delete(`${base}/api/${token.id}`);
</script>

<template>
    <Head :title="$t(':team API', { team: workspace.name })" />
    <AccountShell>
        <div v-if="newToken" class="lp-alert" role="alert">
            <p>
                <Trans k=":title. Copy it now: it is not shown again.">
                    <template #title><strong>{{ $t('Your new token') }}</strong></template>
                </Trans>
            </p>
            <pre class="lp-code"><code>{{ newToken }}</code></pre>
        </div>
        <div class="lp-card">
            <h1>{{ $t(':team: API', { team: workspace.name }) }}</h1>
            <p class="lp-muted">
                <Trans k="A token lets a program of yours read this team. Send it as :header to :url. Keep it secret, and revoke it if it leaks.">
                    <template #header><code>Authorization: Bearer &lt;token&gt;</code></template>
                    <template #url><code>/api/v1</code></template>
                </Trans>
            </p>
            <p><Link :href="`${base}/webhooks`">{{ $t('Webhooks') }}</Link></p>
            <p v-if="!tokens.length">{{ $t('No tokens yet.') }}</p>
            <div v-else class="lp-table-wrap">
                <table>
                    <thead><tr><th scope="col">{{ $t('Name') }}</th><th scope="col">{{ $t('Can') }}</th><th scope="col">{{ $t('Last used') }}</th><th scope="col">{{ $t('Last address') }}</th><th scope="col">{{ $t('Expires') }}</th><th scope="col"><span class="lp-skip">{{ $t('Actions') }}</span></th></tr></thead>
                    <tbody>
                        <tr v-for="token in tokens" :key="token.id">
                            <td><Link :href="`${base}/api/${token.id}`">{{ token.name }}</Link></td>
                            <td>{{ token.abilities.join(', ') }}</td>
                            <td>{{ when(token.last_used_at) ?? $t('Never') }}</td>
                            <td>{{ token.last_ip }}</td>
                            <td>{{ token.expired ? $t('Expired') : (when(token.expires_at) ?? $t('Never')) }}<template v-if="token.replaced"> ({{ $t('being replaced') }})</template></td>
                            <td><template v-if="!token.replaced"><Link :href="`${base}/api/${token.id}#rotate`">{{ $t('Rotate') }}</Link> </template><button class="lp-btn lp-btn--quiet" type="button" @click="revoke(token)">{{ $t('Revoke') }}</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Create a token') }}</h2>
            <form @submit.prevent="form.post(`${base}/api`, { onSuccess: () => form.reset('name') })">
                <FormField id="name" v-model="form.name" :label="$t('Name')" :error="form.errors.name" />
                <div class="lp-field">
                    <label for="expiry">{{ $t('Expires') }}</label>
                    <select id="expiry" v-model="form.expiry">
                        <option v-for="option in expiries" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <p v-if="form.errors.expiry" class="lp-error">{{ form.errors.expiry }}</p>
                </div>
                <fieldset>
                    <legend>{{ $t('Can') }}</legend>
                    <label v-for="ability in abilities" :key="ability.key" class="lp-check">
                        <input v-model="form.abilities" type="checkbox" :value="ability.key"> <span>{{ ability.label }}</span>
                    </label>
                    <p v-if="form.errors.abilities" class="lp-error">{{ form.errors.abilities }}</p>
                </fieldset>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Create token') }}</button>
            </form>
        </div>
        <div v-if="mcpUrl" class="lp-card">
            <h2>{{ $t('Connect an AI assistant') }}</h2>
            <p>{{ $t('Assistants that support MCP (Claude, ChatGPT, Cursor and others) can read this team through its own address, with a token from this page.') }}</p>
            <p><code>{{ mcpUrl }}</code></p>
            <p class="lp-muted">{{ $t('Give the assistant the address, and the token as a bearer token (the header Authorization: Bearer followed by the token). It can do what the token can do, and nothing in other teams.') }}</p>
        </div>
    </AccountShell>
</template>
