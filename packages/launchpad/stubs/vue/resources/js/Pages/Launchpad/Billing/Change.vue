<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { teamUrl } from '../../../team';

const props = defineProps({
    workspace: { type: Object, required: true },
    from: { type: Object, required: true },
    to: { type: Object, required: true },
    toPeriod: { type: String, required: true },
    timing: { type: String, required: true },
    hasSubscription: Boolean,
});

const go = () => router.post(teamUrl(props.workspace.slug, `billing/change/${props.to.key}/${props.toPeriod}`));
</script>

<template>
    <Head :title="$t('Change plan')" />
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Change plan') }}</h1>
            <p>
                <Trans k="From :from to :to (:period)." :values="{ period: toPeriod === 'annual' ? $t('billed yearly') : $t('billed monthly') }">
                    <template #from><strong>{{ from.name }}</strong></template>
                    <template #to><strong>{{ to.name }}</strong></template>
                </Trans>
            </p>
            <p v-if="!hasSubscription">{{ $t('You will pay for it on the next page.') }}</p>
            <p v-else-if="timing === 'now'">
                <Trans k="The change starts :now. You pay only the difference for the time left in this period.">
                    <template #now><strong>{{ $t('now') }}</strong></template>
                </Trans>
            </p>
            <p v-else>
                <Trans k="The change starts at your :renewal, so you keep what you have paid for until then.">
                    <template #renewal><strong>{{ $t('next renewal') }}</strong></template>
                </Trans>
            </p>
            <button class="lp-btn" type="button" @click="go">{{ $t('Carry on to payment') }}</button>
            <Link class="lp-btn lp-btn--quiet" :href="teamUrl(workspace.slug, 'billing')">{{ $t('Not now') }}</Link>
        </div>
    </div>
</template>
