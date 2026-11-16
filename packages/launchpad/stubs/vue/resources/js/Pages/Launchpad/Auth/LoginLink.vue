<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import StatusAlert from '../../../Components/StatusAlert.vue';
import Turnstile from '../../../Components/Turnstile.vue';

defineProps({ turnstileKey: { type: String, default: null } });

const form = useForm({ email: '', cf_turnstile_response: '' });
const submit = () => form.post('/login/link');
</script>

<template>
    <Head :title="$t('Email me a sign-in link')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Sign in with a link') }}</h1>
            <p class="lp-muted">{{ $t('Tell us your email address and we will email you a link that signs you in, no password needed.') }}</p>
            <StatusAlert />
            <form @submit.prevent="submit">
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="username" :error="form.errors.email" autofocus />
                <Turnstile :site-key="turnstileKey" :error="form.errors.turnstile" @verified="form.cf_turnstile_response = $event" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Email me a link') }}</button>
            </form>
        </div>
        <p class="lp-links"><Link href="/login">{{ $t('Back to sign in') }}</Link></p>
    </div>
</template>
