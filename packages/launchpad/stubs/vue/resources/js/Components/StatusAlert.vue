<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// The one-line message a page was redirected with (Laravel's session "status"). A few are codes the pages send; anything else is shown as it is
// (translated if it is a sentence the language file has).
const status = computed(() => usePage().props.flash?.status ?? null);
</script>

<template>
    <div v-if="status" class="lp-alert" role="status">
        <template v-if="status === 'verification-link-sent'">{{ $t('A new link is on its way.') }}</template>
        <template v-else-if="status === 'password-updated'">{{ $t('Your password has been changed.') }}</template>
        <template v-else-if="status === 'two-factor-disabled'">{{ $t('Two-factor sign-in is off.') }}</template>
        <template v-else>{{ $t(status) }}</template>
    </div>
</template>
