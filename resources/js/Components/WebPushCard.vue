<script setup>
// On the website (not in the app): turn phone or computer notifications on for this device (Support/webPush.js).
import { onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import { currentSubscription, needsHomeScreenFirst, turnOffWebPush, turnOnWebPush, webPushSupported } from '@/Support/webPush';

const key = usePage().props.webPushKey;
// 'unsupported', 'home-screen' (an iPhone in Safari), 'blocked', 'off' or 'on'.
const state = ref('unsupported');
const busy = ref(false);
const error = ref('');

onMounted(async () => {
    if (!key) return;
    if (needsHomeScreenFirst()) {
        state.value = 'home-screen';
        return;
    }
    if (!webPushSupported(key)) return;
    state.value = Notification.permission === 'denied' ? 'blocked' : ((await currentSubscription()) ? 'on' : 'off');
});

async function change(on) {
    busy.value = true;
    error.value = '';
    try {
        if (on) {
            state.value = (await turnOnWebPush(key)) ? 'on' : (Notification.permission === 'denied' ? 'blocked' : 'off');
        } else {
            await turnOffWebPush();
            state.value = 'off';
        }
    } catch (failure) {
        error.value = failure.message || 'That did not work. Try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Card v-if="state !== 'unsupported'" padding="sm" class="space-y-2 text-sm">
        <template v-if="state === 'home-screen'">
            <p class="font-medium text-slate-900 dark:text-white">Get notifications on this iPhone</p>
            <p class="text-slate-600 dark:text-slate-300">Add Club Manager to your Home Screen first: tap Share, then Add to Home Screen. Open it from there and turn notifications on here.</p>
        </template>
        <template v-else-if="state === 'blocked'">
            <p class="font-medium text-slate-900 dark:text-white">Notifications are blocked</p>
            <p class="text-slate-600 dark:text-slate-300">Allow notifications for this site in your browser's settings, then come back here.</p>
        </template>
        <div v-else-if="state === 'on'" class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-slate-700 dark:text-slate-200">Notifications are on for this device. <Link :href="route('members.app_settings')" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Choose which ones</Link></p>
            <Button variant="secondary" size="sm" :loading="busy" @click="change(false)">Turn off</Button>
        </div>
        <div v-else class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-slate-700 dark:text-slate-200">Get summonses, notices and reminders on this device as they arrive.</p>
            <Button size="sm" :loading="busy" @click="change(true)">Turn on notifications</Button>
        </div>
        <p v-if="error" class="text-rose-600 dark:text-rose-400" role="alert">{{ error }}</p>
    </Card>
</template>
