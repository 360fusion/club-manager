<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';

const props = defineProps({ choices: { type: Array, required: true } });

const form = useForm({ notify: Object.fromEntries(props.choices.map((c) => [c.key, c.on])) });
// only the ticked boxes are sent, as notify[key]=1, the way the form posts in Blade
const save = () => form.transform((data) => ({ notify: Object.fromEntries(Object.entries(data.notify).filter(([, on]) => on).map(([key]) => [key, 1])) })).put('/account/notifications');
</script>

<template>
    <Head :title="$t('Email preferences')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Email preferences') }}</h1>
            <p class="lp-muted">{{ $t("Choose which optional emails you get. Emails about your account's security, and receipts, are always sent.") }}</p>
            <form @submit.prevent="save">
                <p v-if="!choices.length">{{ $t('There are no optional emails to choose from.') }}</p>
                <div v-for="choice in choices" :key="choice.key" class="lp-check">
                    <input :id="`notify-${choice.key}`" v-model="form.notify[choice.key]" type="checkbox">
                    <label :for="`notify-${choice.key}`"><strong>{{ choice.label }}</strong><br><span class="lp-muted">{{ choice.about }}</span></label>
                </div>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Save') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
