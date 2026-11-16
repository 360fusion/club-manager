<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';

const props = defineProps({ email: { type: String, default: '' }, token: { type: String, required: true } });

const form = useForm({ token: props.token, email: props.email ?? '', password: '', password_confirmation: '' });
const submit = () => form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <Head :title="$t('Choose a new password')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Choose a new password') }}</h1>
            <form @submit.prevent="submit">
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="username" :error="form.errors.email" />
                <FormField id="password" v-model="form.password" :label="$t('New password')" type="password" autocomplete="new-password" :error="form.errors.password" autofocus />
                <FormField id="password_confirmation" v-model="form.password_confirmation" :label="$t('Confirm new password')" type="password" autocomplete="new-password" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Save password') }}</button>
            </form>
        </div>
    </div>
</template>
