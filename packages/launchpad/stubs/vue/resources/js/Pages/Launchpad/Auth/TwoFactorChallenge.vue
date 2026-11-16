<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';

const code = useForm({ code: '' });
const recovery = useForm({ recovery_code: '' });
</script>

<template>
    <Head :title="$t('Two-factor sign-in')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Two-factor sign-in') }}</h1>
            <p class="lp-muted">{{ $t('Enter the code from your authenticator app.') }}</p>
            <form @submit.prevent="code.post('/two-factor-challenge')">
                <FormField id="code" v-model="code.code" :label="$t('Code')" autocomplete="one-time-code" inputmode="numeric" :error="code.errors.code" :required="false" autofocus />
                <button class="lp-btn" type="submit" :disabled="code.processing">{{ $t('Sign in') }}</button>
            </form>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Lost your phone?') }}</h2>
            <form @submit.prevent="recovery.post('/two-factor-challenge')">
                <FormField id="recovery_code" v-model="recovery.recovery_code" :label="$t('Recovery code')" autocomplete="off" :error="recovery.errors.recovery_code" :required="false" />
                <button class="lp-btn lp-btn--quiet" type="submit" :disabled="recovery.processing">{{ $t('Use a recovery code') }}</button>
            </form>
        </div>
    </div>
</template>
