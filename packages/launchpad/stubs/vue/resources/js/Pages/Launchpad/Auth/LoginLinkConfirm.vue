<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ action: { type: String, required: true } });

const form = useForm({});
const submit = () => form.post(props.action);
</script>

<template>
    <Head :title="$t('Sign in')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Sign in') }}</h1>
            <p>{{ $t('Press the button to finish signing in on this device.') }}</p>
            <form @submit.prevent="submit">
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Sign in') }}</button>
            </form>
        </div>
    </div>
</template>
