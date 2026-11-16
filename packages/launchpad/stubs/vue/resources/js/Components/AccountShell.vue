<script setup>
import { Link } from '@inertiajs/vue3';
import { sameHost } from '../team';
import Announcements from './Announcements.vue';
import Onboarding from './Onboarding.vue';
import StatusAlert from './StatusAlert.vue';

// The account area: its menu down the side (Launchpad's AccountMenu, the same list the Blade pages show) and the page beside it.
</script>

<template>
    <div class="lp-account">
        <nav class="lp-account__menu" :aria-label="$t('Account')">
            <h2 class="lp-account__title">{{ $t('My account') }}</h2>
            <ul>
                <li v-for="item in $page.props.auth.menu" :key="item.href">
                    <component :is="sameHost(item.href) ? Link : 'a'" :href="item.href" :aria-current="item.current ? 'page' : null">{{ item.label }}</component>
                </li>
            </ul>
        </nav>
        <div class="lp-account__body">
            <StatusAlert />
            <Announcements :items="$page.props.announcements ?? []" />
            <Onboarding :card="$page.props.onboarding" />
            <slot />
        </div>
    </div>
</template>
