<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';

defineProps({ retentionDays: { type: Number, required: true } });

const form = useForm({ confirm: '' });
</script>

<template>
    <Head :title="$t('Data and privacy')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Data and privacy') }}</h1>
            <h2>{{ $t('Your data') }}</h2>
            <p class="lp-muted">{{ $t('A copy of what we hold on your account, as a file you can keep.') }}</p>
            <!-- a download, not a page: an ordinary link -->
            <a class="lp-btn" href="/account/export">{{ $t('Download my data') }}</a>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Close your account') }}</h2>
            <p>{{ $tc('Your account stops working at once and what you pay for is ended. We keep it for :count day in case you change your mind, then it is removed for good.|Your account stops working at once and what you pay for is ended. We keep it for :count days in case you change your mind, then it is removed for good.', retentionDays) }}</p>
            <div v-if="form.errors.account" class="lp-alert lp-alert--error" role="alert">{{ form.errors.account }}</div>
            <form @submit.prevent="form.post('/account/close')">
                <FormField id="confirm" v-model="form.confirm" :label="$t('Type CLOSE to confirm')" autocomplete="off" :error="form.errors.confirm" />
                <button class="lp-btn lp-btn--danger" type="submit" :disabled="form.processing">{{ $t('Close my account') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
