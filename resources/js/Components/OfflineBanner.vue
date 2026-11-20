<script setup>
// A banner when the phone has no signal, or a page couldn't load on a weak one, pointing to what is saved on the phone
// (public/offline.html, served by the service worker in public/sw.js).
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const offline = ref(false);
const failed = ref(false);
// Hidden until the signal comes and goes again: it must not sit over a ticket being shown at the door.
const hidden = ref(false);
const canListSaved = ref(false);

const update = () => {
    offline.value = typeof navigator !== 'undefined' && navigator.onLine === false;
    if (!offline.value) hidden.value = false;
};
const hide = () => { hidden.value = true; failed.value = false; };
const removers = [];

onMounted(() => {
    update();
    canListSaved.value = Boolean(navigator.serviceWorker?.controller);
    window.addEventListener('online', update);
    window.addEventListener('offline', update);
    removers.push(router.on('networkError', () => { failed.value = true; }));
    removers.push(router.on('navigate', () => { failed.value = false; }));
});

onBeforeUnmount(() => {
    window.removeEventListener('online', update);
    window.removeEventListener('offline', update);
    removers.forEach((remove) => remove());
});
</script>

<template>
    <div v-if="(offline || failed) && !hidden" role="status" class="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+0.75rem)] z-50 mx-auto max-w-md rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-lg dark:bg-slate-800">
        <p class="font-semibold">{{ offline ? "You're offline" : "That page didn't load" }}</p>
        <p class="mt-0.5 text-slate-300">{{ offline ? 'Pages need a signal, apart from tickets, summonses and the dashboard you have opened before.' : 'The signal may be too weak. Try again in a moment.' }}</p>
        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
            <a v-if="canListSaved" href="/offline.html" class="inline-flex min-h-11 items-center font-semibold text-amber-300 underline">See what's saved on this phone</a>
            <button type="button" class="inline-flex min-h-11 items-center font-semibold text-amber-300 underline" @click="hide">Hide</button>
        </div>
    </div>
</template>
