<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    connection: { type: Object, default: null },
    record: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
    callback: { type: String, required: true },
    scimUrl: { type: String, default: null },
    scimToken: { type: String, default: null },
    samlAvailable: Boolean,
    saml: { type: Object, default: null },
});

const base = teamUrl(props.workspace.slug, 'sso');
const form = useForm({
    protocol: props.connection?.protocol ?? 'oidc', metadata_url: '', metadata_xml: '',
    issuer: props.connection?.protocol === 'oidc' ? props.connection.issuer : '', client_id: props.connection?.clientId ?? '', client_secret: '',
    domain: props.connection?.domain ?? '', default_role: props.connection?.defaultRole ?? 'member', enforce: props.connection?.enforce ?? false,
});
const verify = () => router.post(`${base}/verify`, {}, { preserveScroll: true });
const turnOff = () => router.delete(base);
</script>

<template>
    <Head :title="$t('Single sign-on')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t(':team: Single sign-on', { team: workspace.name }) }}</h1>
            <p class="lp-muted">{{ $t('Let people sign in with their company account through your identity provider (OpenID Connect: Okta, Microsoft Entra, Google Workspace and others). Anyone with an email address on your domain who signs in this way joins this team.') }}</p>
            <h2>{{ $t('1. Register this app with your provider') }}</h2>
            <p>{{ $t('Make an OpenID Connect web application with this sign-in redirect address, and copy its issuer, client ID and client secret below.') }}</p>
            <pre class="lp-code"><code>{{ callback }}</code></pre>
            <template v-if="saml">
                <p>{{ $t('Your team uses SAML. Give your provider these:') }}</p>
                <div class="lp-table-wrap"><table><tbody>
                    <tr><th scope="row">{{ $t('Entity ID (and metadata address)') }}</th><td><code>{{ saml.entityId }}</code></td></tr>
                    <tr><th scope="row">{{ $t('Sign-in return address (ACS)') }}</th><td><code>{{ saml.acs }}</code></td></tr>
                    <tr><th scope="row">{{ $t('Name ID') }}</th><td>{{ $t("The person's email address") }}</td></tr>
                </tbody></table></div>
            </template>
            <h2>{{ $t('2. Your provider') }}</h2>
            <form @submit.prevent="form.put(base)">
                <fieldset v-if="samlAvailable">
                    <legend>{{ $t('How your provider signs people in') }}</legend>
                    <label class="lp-check"><input v-model="form.protocol" type="radio" value="oidc"> <span>{{ $t('OpenID Connect (recommended)') }}</span></label>
                    <label class="lp-check"><input v-model="form.protocol" type="radio" value="saml"> <span>{{ $t('SAML 2.0') }}</span></label>
                </fieldset>
                <template v-if="form.protocol === 'oidc'">
                    <FormField id="issuer" v-model="form.issuer" :label="$t('Issuer address')" :error="form.errors.issuer" placeholder="https://example.okta.com" :required="false" />
                    <FormField id="client_id" v-model="form.client_id" :label="$t('Client ID')" :error="form.errors.client_id" :required="false" />
                    <FormField id="client_secret" v-model="form.client_secret" type="password" autocomplete="off" :label="connection?.protocol === 'oidc' ? $t('Client secret (leave blank to keep the saved one)') : $t('Client secret')" :error="form.errors.client_secret" :required="false" />
                </template>
                <template v-else>
                    <p v-if="saml" class="lp-muted">{{ $t('Saved provider: :idp. Give new metadata only to change it.', { idp: saml.idp }) }}</p>
                    <FormField id="metadata_url" v-model="form.metadata_url" :label="$t('Your provider\'s metadata address')" :error="form.errors.metadata_url" :required="false" />
                    <div class="lp-field">
                        <label for="metadata_xml">{{ $t('Or paste its metadata XML') }}</label>
                        <textarea id="metadata_xml" v-model="form.metadata_xml" rows="4" />
                        <p v-if="form.errors.metadata_xml" class="lp-error">{{ form.errors.metadata_xml }}</p>
                    </div>
                </template>
                <FormField id="domain" v-model="form.domain" :label="$t('Email domain')" :error="form.errors.domain" placeholder="example.com" />
                <div class="lp-field">
                    <label for="default_role">{{ $t('Role for people who join this way') }}</label>
                    <select id="default_role" v-model="form.default_role">
                        <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                    </select>
                </div>
                <div class="lp-check"><input id="enforce" v-model="form.enforce" type="checkbox"><label for="enforce">{{ $t('People on this domain must use single sign-on (no password sign-in)') }}</label></div>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Save') }}</button>
            </form>
        </div>
        <div v-if="connection" class="lp-card">
            <h2>{{ $t('3. Prove you own the domain') }}</h2>
            <p v-if="connection.verified">{{ $t(':domain is verified. Single sign-on is on.', { domain: connection.domain }) }}</p>
            <template v-else>
                <p>{{ $t('Add this TXT record to your domain\'s DNS, then check it. Single sign-on starts working once it is found.') }}</p>
                <div class="lp-table-wrap"><table><thead><tr><th scope="col">{{ $t('Name') }}</th><th scope="col">{{ $t('Value') }}</th></tr></thead>
                    <tbody><tr><td><code>{{ record.name }}</code></td><td><code>{{ record.value }}</code></td></tr></tbody></table></div>
                <p v-if="$page.props.errors.domain" class="lp-error">{{ $page.props.errors.domain }}</p>
                <button class="lp-btn" type="button" @click="verify">{{ $t('Check the record') }}</button>
            </template>
            <button class="lp-btn lp-btn--quiet" type="button" @click="turnOff">{{ $t('Turn off single sign-on') }}</button>
        </div>
        <div v-if="connection && connection.verified && scimUrl" class="lp-card">
            <h2>{{ $t('4. Directory provisioning (optional)') }}</h2>
            <p>{{ $t('Let your directory add people to the team, keep their names and addresses up to date, and take them out when they leave. In your provider, set up SCIM 2.0 with this address and a token from here.') }}</p>
            <p><code>{{ scimUrl }}</code></p>
            <div v-if="scimToken" class="lp-alert" role="status">{{ $t('Your token (copy it now, it is not shown again):') }} <code>{{ scimToken }}</code></div>
            <p class="lp-muted">{{ connection.scimOn ? $t('Provisioning is on. A new token replaces the old one at once.') : $t('Provisioning is off.') }}</p>
            <button class="lp-btn" type="button" @click="router.post(`${base}/scim`)">{{ connection.scimOn ? $t('Make a new token') : $t('Turn on and make a token') }}</button>
            <button v-if="connection.scimOn" class="lp-btn lp-btn--quiet" type="button" @click="router.delete(`${base}/scim`)">{{ $t('Turn off provisioning') }}</button>
        </div>
    </AccountShell>
</template>
