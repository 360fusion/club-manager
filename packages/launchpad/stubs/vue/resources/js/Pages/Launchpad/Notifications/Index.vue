<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';
import { locale } from '../../../i18n';

defineProps({
    notifications: { type: Array, default: () => [] },
    unread: { type: Number, default: 0 },
    nextPageUrl: { type: String, default: null },
    prevPageUrl: { type: String, default: null },
});

const when = (iso) => new Date(iso).toLocaleString(locale(), { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
const opts = { preserveScroll: true };
</script>

<template>
    <Head :title="$t('Notifications')" />
    <AccountShell>
        <div class="lp-card">
            <div class="lp-card__head">
                <h1>{{ $t('Notifications') }}</h1>
                <button v-if="unread" class="lp-btn lp-btn--quiet" type="button" @click="router.post('/notifications/read-all', {}, opts)">{{ $t('Mark all as read') }}</button>
            </div>
            <div v-for="row in notifications" :key="row.id" class="lp-note" :class="{ 'lp-note--unread': !row.read }">
                <div>
                    <p class="lp-note__title"><a :href="`/notifications/${row.id}/open`">{{ row.title }}</a><span v-if="!row.read" class="lp-skip"> {{ $t('(unread)') }}</span></p>
                    <p v-if="row.message">{{ row.message }}</p>
                    <time class="lp-muted" :datetime="row.at">{{ when(row.at) }}</time>
                </div>
                <div class="lp-note__actions">
                    <button v-if="!row.read" class="lp-btn lp-btn--quiet" type="button" @click="router.post(`/notifications/${row.id}/read`, {}, opts)">{{ $t('Mark as read') }}</button>
                    <button class="lp-btn lp-btn--quiet" type="button" @click="router.delete(`/notifications/${row.id}`, opts)">{{ $t('Delete') }}</button>
                </div>
            </div>
            <p v-if="!notifications.length">{{ $t('You have no notifications. When something happens that you should know about, it appears here and under the bell.') }}</p>
            <p v-if="nextPageUrl || prevPageUrl" class="lp-links">
                <Link v-if="prevPageUrl" :href="prevPageUrl">{{ $t('Newer') }}</Link><span v-else />
                <Link v-if="nextPageUrl" :href="nextPageUrl">{{ $t('Older') }}</Link>
            </p>
        </div>
    </AccountShell>
</template>
