<script setup>
// The header's bell: the latest notices, a count of the unread ones, and the way to the full list (as Launchpad's Blade layouts/bell).
import { router } from '@inertiajs/vue3';
import { locale } from '../i18n';

defineProps({ bell: { type: Object, required: true } });

const ago = (iso) => {
    // like Blade's diffForHumans, in the person's language
    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    const relative = new Intl.RelativeTimeFormat(locale(), { numeric: 'auto' });
    if (minutes < 60) return relative.format(-Math.max(minutes, 0), 'minute');
    const hours = Math.round(minutes / 60);
    if (hours < 24) return relative.format(-hours, 'hour');
    return new Date(iso).toLocaleDateString(locale(), { day: 'numeric', month: 'short' });
};
</script>

<template>
    <details class="lp-bell">
        <summary :aria-label="bell.unread ? $t('Notifications, :count unread', { count: bell.unread }) : $t('Notifications')">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" /></svg>
            <span v-if="bell.unread" class="lp-badge">{{ bell.unread > 9 ? '9+' : bell.unread }}</span>
        </summary>
        <div class="lp-bell__panel">
            <div class="lp-bell__head">
                <h2>{{ $t('Notifications') }}</h2>
                <button v-if="bell.unread" type="button" @click="router.post('/notifications/read-all', {}, { preserveScroll: true })">{{ $t('Mark all as read') }}</button>
            </div>
            <!-- an ordinary link: opening a notice marks it read and redirects to wherever it points -->
            <a v-for="row in bell.latest" :key="row.id" class="lp-bell__item" :class="{ 'lp-bell__item--unread': !row.read }" :href="`/notifications/${row.id}/open`">
                <strong>{{ row.title }}<span v-if="!row.read" class="lp-skip"> {{ $t('(unread)') }}</span></strong>
                <span v-if="row.message">{{ row.message }}</span>
                <time class="lp-muted" :datetime="row.at">{{ ago(row.at) }}</time>
            </a>
            <p v-if="!bell.latest.length" class="lp-bell__empty lp-muted">{{ $t('You are all caught up.') }}</p>
            <a class="lp-bell__all" href="/notifications">{{ $t('View all notifications') }}</a>
        </div>
    </details>
</template>
