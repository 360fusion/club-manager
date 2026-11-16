<script setup>
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    token: { type: Object, required: true },
    days: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    graces: { type: Array, default: () => [] },
});

const form = useForm({ grace: '24h' });
const rotate = () => form.post(teamUrl(props.workspace.slug, `api/${props.token.id}/rotate`));

const day = (value) => new Date(`${value}T00:00:00Z`).toLocaleDateString(locale(), { timeZone: 'UTC' });
const when = (value) => (value ? new Date(value).toLocaleDateString(locale()) : null);
</script>

<template>
    <Head :title="$t(':name API token', { name: token.name })" />
    <AccountShell>
        <div v-if="$page.props.errors.token" class="lp-alert lp-alert--error" role="alert">{{ $page.props.errors.token }}</div>
        <div class="lp-card">
            <h1>{{ token.name }}</h1>
            <p><Link :href="teamUrl(workspace.slug, 'api')">{{ $t('All API tokens of :team', { team: workspace.name }) }}</Link></p>
            <dl class="lp-facts">
                <dt>{{ $t('Can') }}</dt><dd>{{ token.abilities.join(', ') }}</dd>
                <dt>{{ $t('Made by') }}</dt><dd>{{ $t(':person, :date', { person: token.createdBy ?? $t('Someone who has left'), date: when(token.createdAt) }) }}</dd>
                <dt>{{ $t('Last used') }}</dt><dd>{{ token.lastIp ? $t(':when from :address', { when: when(token.lastUsedAt) ?? $t('Never'), address: token.lastIp }) : (when(token.lastUsedAt) ?? $t('Never')) }}</dd>
                <template v-if="token.lastUserAgent"><dt>{{ $t('Last program') }}</dt><dd>{{ token.lastUserAgent }}</dd></template>
                <dt>{{ $t('Expires') }}</dt><dd>{{ token.expired ? $t('Expired') : (when(token.expiresAt) ?? $t('Never')) }}<template v-if="token.replaced"> {{ $t('(being replaced: it stops working then)') }}</template></dd>
            </dl>
        </div>
        <div id="rotate" class="lp-card">
            <h2>{{ $t('Replace this token') }}</h2>
            <p class="lp-muted">{{ $t('Makes a new token with the same name, abilities and lifetime, shown once. The old one keeps working for the time you choose, so the program that uses it can be switched over.') }}</p>
            <form @submit.prevent="rotate">
                <div class="lp-field">
                    <label for="grace">{{ $t('Keep the old token working for') }}</label>
                    <select id="grace" v-model="form.grace">
                        <option v-for="grace in graces" :key="grace.value" :value="grace.value">{{ grace.label }}</option>
                    </select>
                    <p v-if="form.errors.grace" class="lp-error">{{ form.errors.grace }}</p>
                </div>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Replace token') }}</button>
            </form>
        </div>
        <div class="lp-card">
            <h2>{{ $t('The last 30 days') }}</h2>
            <p class="lp-muted">{{ $tc('{0} No calls, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.|{1} :count call, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.|[2,*] :count calls, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.', totals.requests, { errors: totals.errors }) }}</p>
            <div class="lp-table-wrap">
                <table>
                    <caption class="lp-skip">{{ $t('Calls and errors for each of the last 30 days') }}</caption>
                    <thead><tr><th scope="col">{{ $t('Day') }}</th><th scope="col">{{ $t('Calls') }}</th><th scope="col">{{ $t('Errors') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="d in days" :key="d.date"><td>{{ day(d.date) }}</td><td>{{ d.requests }}</td><td>{{ d.errors }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AccountShell>
</template>
