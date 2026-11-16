<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import Turnstile from '../../../Components/Turnstile.vue';

const props = defineProps({
    plan: { type: Object, required: true },
    frequency: { type: String, required: true },
    turnstileKey: { type: String, default: null },
    email: { type: String, default: null },
});

const form = useForm({ name: '', email: props.email ?? '', password: '', password_confirmation: '', terms: false, cf_turnstile_response: '' });
const submit = () => form.post(`/checkout/${props.plan.key}/${props.frequency}/account`, { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head :title="$t('Create your account to buy :plan', { plan: plan.name })" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Create your account') }}</h1>
            <p>
                <Trans k="You are buying :plan. Make your account first, then you go straight to payment.">
                    <template #plan><strong>{{ plan.name }}</strong></template>
                </Trans>
            </p>
            <form @submit.prevent="submit">
                <FormField id="name" v-model="form.name" :label="$t('Your name')" autocomplete="name" :error="form.errors.name" autofocus />
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="username" :error="form.errors.email" />
                <FormField id="password" v-model="form.password" :label="$t('Password')" type="password" autocomplete="new-password" :error="form.errors.password" />
                <FormField id="password_confirmation" v-model="form.password_confirmation" :label="$t('Confirm password')" type="password" autocomplete="new-password" />
                <div class="lp-check">
                    <input id="terms" v-model="form.terms" type="checkbox" required>
                    <label for="terms">
                        <Trans k="I agree to the :terms and have read the :privacy.">
                            <template #terms><a href="/terms">{{ $t('Terms') }}</a></template>
                            <template #privacy><a href="/privacy">{{ $t('Privacy Policy') }}</a></template>
                        </Trans>
                    </label>
                </div>
                <p v-if="form.errors.terms" class="lp-error">{{ form.errors.terms }}</p>
                <Turnstile :site-key="turnstileKey" :error="form.errors.turnstile" @verified="form.cf_turnstile_response = $event" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Continue to payment') }}</button>
            </form>
        </div>
        <p class="lp-links"><Link href="/login">{{ $t('Already have an account? Sign in') }}</Link></p>
    </div>
</template>
