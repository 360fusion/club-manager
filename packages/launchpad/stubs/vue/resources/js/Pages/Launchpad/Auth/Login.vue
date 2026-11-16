<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { passkeysSupported, signIn } from '../../../passkeys';
import { t } from '../../../i18n';
import FormField from '../../../Components/FormField.vue';
import StatusAlert from '../../../Components/StatusAlert.vue';
import Turnstile from '../../../Components/Turnstile.vue';

defineProps({ canResetPassword: { type: Boolean, default: false }, turnstileKey: { type: String, default: null }, canUseSso: { type: Boolean, default: false }, canUsePasskeys: { type: Boolean, default: false }, canUseLoginLinks: { type: Boolean, default: false } });

const form = useForm({ email: '', password: '', remember: false, cf_turnstile_response: '' });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });

const passkeyError = ref(null);
const passkey = () => signIn(form.remember, t('The passkey was cancelled.')).then((url) => (window.location = url)).catch((e) => (passkeyError.value = e.message));
</script>

<template>
    <Head :title="$t('Sign in')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Sign in') }}</h1>
            <StatusAlert />
            <form @submit.prevent="submit">
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="username" :error="form.errors.email" autofocus />
                <FormField id="password" v-model="form.password" :label="$t('Password')" type="password" autocomplete="current-password" :error="form.errors.password" />
                <div class="lp-check"><input id="remember" v-model="form.remember" type="checkbox"><label for="remember">{{ $t('Keep me signed in') }}</label></div>
                <Turnstile :site-key="turnstileKey" :error="form.errors.turnstile" @verified="form.cf_turnstile_response = $event" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Sign in') }}</button>
            </form>
            <div v-if="canUsePasskeys && passkeysSupported">
                <div v-if="passkeyError" class="lp-alert lp-alert--error" role="alert">{{ passkeyError }}</div>
                <p><button class="lp-btn lp-btn--quiet" type="button" @click="passkey">{{ $t('Sign in with a passkey') }}</button></p>
            </div>
        </div>
        <p class="lp-links">
            <Link v-if="canResetPassword" href="/forgot-password">{{ $t('Forgot your password?') }}</Link>
            <Link v-if="canUseSso" href="/sso">{{ $t('Sign in with single sign-on') }}</Link>
            <Link v-if="canUseLoginLinks" href="/login/link">{{ $t('Email me a sign-in link') }}</Link>
            <Link href="/register">{{ $t('Create an account') }}</Link>
        </p>
    </div>
</template>
