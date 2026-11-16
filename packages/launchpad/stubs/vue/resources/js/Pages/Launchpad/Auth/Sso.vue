<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';

const props = defineProps({ email: { type: String, default: '' } });
const form = useForm({ email: props.email });
</script>

<template>
    <Head :title="$t('Sign in with single sign-on')" />
    <div class="lp-card">
        <h1>{{ $t('Sign in with single sign-on') }}</h1>
        <p class="lp-muted">{{ $t('If your team signs in with your company account, enter your work email address and you will be sent to your company to sign in.') }}</p>
        <form @submit.prevent="form.post('/sso')">
            <FormField id="email" v-model="form.email" type="email" autocomplete="username" :label="$t('Work email address')" :error="form.errors.email" />
            <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Continue') }}</button>
        </form>
    </div>
    <p class="lp-links"><Link href="/login">{{ $t('Sign in with a password instead') }}</Link></p>
</template>
