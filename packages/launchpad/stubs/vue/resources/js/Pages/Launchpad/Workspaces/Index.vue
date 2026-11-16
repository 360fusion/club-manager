<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import { teamUrl } from '../../../team';

defineProps({ workspaces: { type: Array, required: true } });

const form = useForm({ name: '' });
</script>

<template>
    <Head :title="$t('Teams')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Your teams') }}</h1>
            <ul>
                <li v-for="w in workspaces" :key="w.slug">
                    <strong>{{ w.name }}</strong>
                    <span class="lp-muted"> ({{ w.personal ? $t(':role, personal', { role: w.role }) : w.role }})</span>
                    <a :href="teamUrl(w.slug, 'members')">{{ $t('Team') }}</a>
                    <a v-if="w.canSettings" :href="teamUrl(w.slug, 'settings')">{{ $t('Settings') }}</a>
                    <a v-if="w.canBilling" :href="teamUrl(w.slug, 'billing')">{{ $t('Billing') }}</a>
                </li>
            </ul>
        </div>
        <div class="lp-card">
            <h2>{{ $t('Create a team') }}</h2>
            <form @submit.prevent="form.post('/workspaces', { onSuccess: () => form.reset() })">
                <FormField id="name" v-model="form.name" :label="$t('Name')" :error="form.errors.name" />
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Create') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
