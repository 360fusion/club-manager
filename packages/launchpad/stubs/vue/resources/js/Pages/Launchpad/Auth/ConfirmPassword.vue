<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';

const form = useForm({ password: '' });
const submit = () => form.post('/confirm-password', { onFinish: () => form.reset() });
</script>

<template>
    <Head :title="$t('Confirm your password')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Confirm your password') }}</h1>
            <p class="lp-muted">{{ $t('This is a secure area. Please confirm your password to carry on.') }}</p>
            <form @submit.prevent="submit">
                <FormField id="password" v-model="form.password" :label="$t('Password')" type="password" autocomplete="current-password" :error="form.errors.password" autofocus />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Confirm') }}</button>
            </form>
        </div>
    </div>
</template>
