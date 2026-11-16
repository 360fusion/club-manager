<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { add as addPasskey, passkeysSupported } from '../../../passkeys';
import AccountShell from '../../../Components/AccountShell.vue';
import FormField from '../../../Components/FormField.vue';
import { locale, t } from '../../../i18n';

defineProps({
    twoFactorOn: Boolean,
    qrCode: { type: String, default: null },
    secret: { type: String, default: null },
    recoveryCodes: { type: Array, default: null },
    recoveryLeft: { type: Number, default: 0 },
    needsCurrentPassword: Boolean,
    connections: { type: Array, default: () => [] },
    devices: { type: Array, default: null },
    passkeys: { type: Array, default: null },
});

const password = useForm({ current_password: '', password: '', password_confirmation: '' });
const confirm = useForm({ code: '' });

const savePassword = () => password.put('/password', { onSuccess: () => password.reset() });
const day = (iso) => new Date(iso).toLocaleDateString(locale(), { day: 'numeric', month: 'short', year: 'numeric' });
const passkeyName = ref(t('My passkey'));
const passkeyError = ref(null);
const savePasskey = () => addPasskey(passkeyName.value, t('The passkey was cancelled.')).then((url) => router.visit(url)).catch((e) => (passkeyError.value = e.message));
const when = (iso) => new Date(iso).toLocaleString(locale(), { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head :title="$t('Security')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Security') }}</h1>
            <h2>{{ $t('Password') }}</h2>
            <form @submit.prevent="savePassword">
                <FormField v-if="needsCurrentPassword" id="current_password" v-model="password.current_password" :label="$t('Current password')" type="password" autocomplete="current-password" :error="password.errors.current_password" />
                <FormField id="password" v-model="password.password" :label="$t('New password')" type="password" autocomplete="new-password" :error="password.errors.password" />
                <FormField id="password_confirmation" v-model="password.password_confirmation" :label="$t('Confirm new password')" type="password" autocomplete="new-password" />
                <button class="lp-btn" type="submit" :disabled="password.processing">{{ $t('Save password') }}</button>
            </form>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Two-factor sign-in') }}</h2>
            <div v-if="recoveryCodes" class="lp-alert" role="status">
                <p>
                    <Trans k=":keep Each works once, and they are not shown again.">
                        <template #keep><strong>{{ $t('Keep these recovery codes somewhere safe.') }}</strong></template>
                    </Trans>
                </p>
                <ul class="lp-codes"><li v-for="code in recoveryCodes" :key="code">{{ code }}</li></ul>
            </div>
            <template v-if="twoFactorOn">
                <p>{{ $t('Two-factor sign-in is on.') }} {{ $tc('{0} You have no recovery codes left.|{1} You have :count recovery code left.|[2,*] You have :count recovery codes left.', recoveryLeft) }}</p>
                <button class="lp-btn lp-btn--quiet" type="button" @click="router.post('/account/two-factor/recovery-codes')">{{ $t('Make new recovery codes') }}</button>
                <button class="lp-btn lp-btn--danger" type="button" @click="router.delete('/account/two-factor')">{{ $t('Turn off') }}</button>
            </template>
            <template v-else-if="qrCode">
                <p>{{ $t('Scan this with an authenticator app, then enter the code it shows.') }}</p>
                <!-- an SVG the server drew from the secret: no text from anyone else is in it -->
                <div class="lp-qr" v-html="qrCode" />
                <p class="lp-muted">
                    <Trans k="Cannot scan it? Enter this key: :key">
                        <template #key><code>{{ secret }}</code></template>
                    </Trans>
                </p>
                <form @submit.prevent="confirm.post('/account/two-factor/confirm')">
                    <FormField id="code" v-model="confirm.code" :label="$t('Code from the app')" autocomplete="one-time-code" inputmode="numeric" :error="confirm.errors.code" />
                    <button class="lp-btn" type="submit" :disabled="confirm.processing">{{ $t('Turn on') }}</button>
                </form>
            </template>
            <template v-else>
                <p class="lp-muted">{{ $t('Add a second step to signing in, with a code from an authenticator app.') }}</p>
                <button class="lp-btn" type="button" @click="router.post('/account/two-factor')">{{ $t('Set up') }}</button>
            </template>
        </div>
        <div v-if="connections.length" class="lp-card">
            <h2>{{ $t('Sign-in methods') }}</h2>
            <div v-if="$page.props.errors.connections" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.connections }}</div>
            <ul class="lp-list">
                <li v-for="c in connections" :key="c.provider">
                    {{ c.name }} <span class="lp-muted">{{ c.linked ? $t('linked') : $t('not linked') }}</span>
                    <button v-if="c.linked" class="lp-btn lp-btn--quiet" type="button" @click="router.delete(`/account/connections/${c.provider}`)">{{ $t('Remove') }}</button>
                    <!-- connecting leaves for the provider's own page: a real form post, not a visit -->
                    <form v-else class="lp-inline" method="post" :action="`/account/connections/${c.provider}`">
                        <input type="hidden" name="_token" :value="$page.props.csrf">
                        <button class="lp-btn lp-btn--quiet" type="submit">{{ $t('Link') }}</button>
                    </form>
                </li>
            </ul>
            <p class="lp-muted">{{ $t('You can always sign in with your email address and password.') }}</p>
        </div>
        <div v-if="passkeys" class="lp-card">
            <h2>{{ $t('Passkeys') }}</h2>
            <p class="lp-muted">{{ $t('Sign in with your fingerprint, face or device PIN instead of a password. A passkey stays on your device or in your password manager.') }}</p>
            <ul v-if="passkeys.length" class="lp-list">
                <li v-for="p in passkeys" :key="p.id">
                    <span>{{ p.name }}<br><span class="lp-muted">{{ p.lastUsed ? $t('Added :added, last used :used', { added: day(p.added), used: when(p.lastUsed) }) : $t('Added :added, not used yet', { added: day(p.added) }) }}</span></span>
                    <button class="lp-btn lp-btn--quiet" type="button" @click="router.delete(`/account/passkeys/${p.id}`)">{{ $t('Remove') }}</button>
                </li>
            </ul>
            <p v-if="!passkeysSupported" class="lp-muted">{{ $t('This browser cannot add a passkey here. Passkeys need a secure (https) page and a browser that supports them.') }}</p>
            <form v-else @submit.prevent="savePasskey">
                <div v-if="passkeyError" class="lp-alert lp-alert--error" role="alert">{{ passkeyError }}</div>
                <FormField id="passkey_name" v-model="passkeyName" :label="$t('Name it, so you can tell it apart')" maxlength="60" />
                <button class="lp-btn" type="submit">{{ $t('Add a passkey') }}</button>
            </form>
        </div>
        <div v-if="devices" class="lp-card">
            <h2>{{ $t('Where you are signed in') }}</h2>
            <ul class="lp-list">
                <li v-for="(d, i) in devices" :key="i">
                    {{ d.device }} <strong v-if="d.current">{{ $t('(this device)') }}</strong>
                    <span class="lp-muted">{{ $t(':address, last active :when', { address: d.ip ?? $t('Unknown address'), when: when(d.lastActive) }) }}</span>
                </li>
            </ul>
            <button v-if="devices.length > 1" class="lp-btn lp-btn--quiet" type="button" @click="router.delete('/account/sessions')">{{ $t('Sign out of all other devices') }}</button>
        </div>
    </AccountShell>
</template>
