<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import StatusAlert from '../../../Components/StatusAlert.vue';
import Turnstile from '../../../Components/Turnstile.vue';

defineProps({ turnstileKey: { type: String, default: null } });

const form = useForm({ email: '', name: '', cf_turnstile_response: '' });
const submit = () => form.post('/waitlist', { onSuccess: () => form.reset() });
</script>

<template>
    <Head :title="$t('Join the waitlist')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Join the waitlist') }}</h1>
            <p class="lp-muted">{{ $t('Sign-up is by invitation for now. Leave your address and we will email you when there is room.') }}</p>
            <StatusAlert />
            <form @submit.prevent="submit">
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="email" :error="form.errors.email" autofocus />
                <FormField id="name" v-model="form.name" :label="$t('Your name (optional)')" autocomplete="name" :error="form.errors.name" :required="false" />
                <Turnstile :site-key="turnstileKey" :error="form.errors.turnstile" @verified="form.cf_turnstile_response = $event" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Join') }}</button>
            </form>
        </div>
        <p class="lp-links"><Link href="/login">{{ $t('Already have an account? Sign in') }}</Link></p>
    </div>
</template>
