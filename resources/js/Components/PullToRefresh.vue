<script setup>
// Pull down at the top of a page to reload it, in the home-screen app and the iOS and Android app, which have no
// browser reload button. Only starts when nothing under the finger is scrolled, so ordinary scrolling is untouched.
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { isNativeApp } from '@/Support/nativeApp';
import { isHomeScreenApp } from '@/Support/webPush';

const TRIGGER = 64;
const pull = ref(0);
const refreshing = ref(false);
let startY = null;

function scrolledUnder(target) {
    for (let el = target; el && el !== document.documentElement; el = el.parentElement) {
        if (el.scrollTop > 0) return true;
    }
    return window.scrollY > 0;
}

function onStart(event) {
    if (refreshing.value || event.touches.length !== 1 || scrolledUnder(event.target) || document.querySelector('[role=dialog]')) {
        startY = null;
        return;
    }
    startY = event.touches[0].clientY;
}

function onMove(event) {
    if (startY === null) return;
    const distance = event.touches[0].clientY - startY;
    pull.value = distance > 0 ? Math.min(distance * 0.5, 96) : 0;
}

function onEnd() {
    if (startY === null) return;
    startY = null;
    if (pull.value >= TRIGGER) {
        refreshing.value = true;
        pull.value = TRIGGER;
        router.reload({ onFinish: () => { refreshing.value = false; pull.value = 0; } });
    } else {
        pull.value = 0;
    }
}

const active = ref(false);
onMounted(() => {
    active.value = isNativeApp || isHomeScreenApp();
    if (!active.value) return;
    window.addEventListener('touchstart', onStart, { passive: true });
    window.addEventListener('touchmove', onMove, { passive: true });
    window.addEventListener('touchend', onEnd, { passive: true });
});
onBeforeUnmount(() => {
    window.removeEventListener('touchstart', onStart);
    window.removeEventListener('touchmove', onMove);
    window.removeEventListener('touchend', onEnd);
});
</script>

<template>
    <div v-if="active && (pull > 0 || refreshing)" class="pointer-events-none fixed inset-x-0 top-[env(safe-area-inset-top)] z-50 flex justify-center" :style="{ transform: `translateY(${pull}px)` }" role="status" :aria-label="refreshing ? 'Refreshing' : (pull >= 64 ? 'Release to refresh' : 'Pull to refresh')">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700">
            <svg :class="['h-5 w-5 text-blue-600 dark:text-blue-400', refreshing ? 'animate-spin' : '']" :style="refreshing ? null : { transform: `rotate(${pull * 3.5}deg)` }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66" /><path d="M20 4v4h-4" /></svg>
        </div>
    </div>
</template>
