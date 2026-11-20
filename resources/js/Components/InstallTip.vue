<script setup>
// On a phone's browser: a one-time tip to add Club Manager to the home screen, so it opens full screen like the app.
// iPhones never offer this themselves; Chrome on Android does, and the tip's button asks it.
import { onMounted, ref } from 'vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import { isNativeApp } from '@/Support/nativeApp';
import { canPromptInstall, isHomeScreenApp, promptInstall } from '@/Support/webPush';

const DISMISSED = 'cm-install-tip-dismissed';
const show = ref(false);
const iPhone = ref(false);
const canInstall = ref(false);

onMounted(() => {
    let dismissed = false;
    try { dismissed = localStorage.getItem(DISMISSED) === '1'; } catch { /* private browsing */ }
    const phone = window.matchMedia('(max-width: 767px) and (pointer: coarse)').matches;
    show.value = phone && !dismissed && !isNativeApp && !isHomeScreenApp();
    iPhone.value = /iPhone|iPod/.test(navigator.userAgent);
    // Chrome may only decide it can install a moment after the page loads.
    setTimeout(() => { canInstall.value = canPromptInstall(); }, 1500);
});

function dismiss() {
    show.value = false;
    try { localStorage.setItem(DISMISSED, '1'); } catch { /* private browsing */ }
}

async function install() {
    if (await promptInstall()) dismiss();
    canInstall.value = canPromptInstall();
}
</script>

<template>
    <Card v-if="show" padding="sm" class="space-y-2 text-sm">
        <p class="font-medium text-slate-900 dark:text-white">Put Club Manager on your home screen</p>
        <p v-if="iPhone" class="text-slate-600 dark:text-slate-300">Tap Share at the bottom of Safari, then Add to Home Screen. It opens full screen, like an app, and can send you notifications.</p>
        <p v-else class="text-slate-600 dark:text-slate-300">It opens full screen, like an app, and can send you notifications.<template v-if="!canInstall"> In your browser's menu, choose Add to Home screen or Install app.</template></p>
        <div class="flex gap-2">
            <Button v-if="canInstall" size="sm" @click="install">Add to home screen</Button>
            <Button variant="secondary" size="sm" @click="dismiss">Not now</Button>
        </div>
    </Card>
</template>
