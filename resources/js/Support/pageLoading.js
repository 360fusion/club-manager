// An instant visit (the phone's tab bar) shows the next page straight away, before its own data arrives; until it does
// the layout shows placeholders in place of the page's content (MembersLayout).
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

export const pageLoading = reactive({ instant: false });

export function trackInstantVisits() {
    // Only the instant visit itself ends the placeholders: Inertia also reports the pages it fetches ahead of a tap.
    let current = null;
    router.on('start', (event) => {
        const visit = event.detail.visit;
        if (visit.prefetch) return;
        current = visit.component ? visit : null;
        pageLoading.instant = Boolean(current);
    });
    const done = () => {
        current = null;
        pageLoading.instant = false;
    };
    router.on('finish', (event) => {
        const visit = event.detail.visit;
        if (current && !visit.prefetch && visit.url?.href === current.url?.href) done();
    });
    router.on('networkError', done);
    router.on('httpException', done);
}
