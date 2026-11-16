<script setup>
import { router } from '@inertiajs/vue3';

const choose = (choice) => router.post('/cookie-consent', { choice }, { preserveScroll: true });
</script>

<template>
    <div class="lp-consent" role="region" :aria-label="$t('Cookies')">
        <p>
            {{ $t('We use cookies that are needed to sign you in. With your permission we would also use others to see how the site is used.') }}
            <a href="/cookie-preferences">{{ $t('Choose') }}</a> &middot; <a href="/cookies">{{ $t('Cookie Policy') }}</a>
        </p>
        <div>
            <button class="lp-btn lp-btn--quiet" type="button" @click="choose('necessary')">{{ $t('Necessary only') }}</button>
            <button class="lp-btn" type="button" @click="choose('all')">{{ $t('Allow all') }}</button>
        </div>
    </div>
</template>
