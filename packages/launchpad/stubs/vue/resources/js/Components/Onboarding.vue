<script setup>
import { Link, router } from '@inertiajs/vue3';

// The "Getting started" card (Launchpad's Onboarding), from the shared `onboarding` prop; null when there is nothing to show.
defineProps({ card: { type: Object, default: null } });
</script>

<template>
    <section v-if="card" class="lp-card lp-card--accent" aria-labelledby="lp-onboarding-title">
        <h2 id="lp-onboarding-title">{{ $t('Getting started') }} <span class="lp-muted">{{ $t(':done of :total done', { done: card.done, total: card.total }) }}</span></h2>
        <ol class="lp-steps">
            <li v-for="(step, index) in card.steps" :key="step.key">
                <span class="lp-step" aria-hidden="true">{{ step.done ? '✓' : index + 1 }}</span>
                <div>
                    <template v-if="step.done"><s>{{ step.label }}</s> <span class="lp-skip">{{ $t('Done') }}</span></template>
                    <Link v-else :href="step.url">{{ step.label }}</Link>
                </div>
            </li>
        </ol>
        <button class="lp-btn lp-btn--quiet" type="button" @click="router.post(card.dismissUrl)">{{ $t('Hide this') }}</button>
    </section>
</template>
