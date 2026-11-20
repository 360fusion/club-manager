<script setup>
import { onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Button from '@/Components/Ui/Button.vue';
import { appLockOn, appLockSupport, biometricSignIn, setAppLock, turnOffBiometricSignIn, turnOnBiometricSignIn } from '@/Support/nativeApp';

const props = defineProps({
    pushMuted: { type: Array, default: () => [] },
});

// Which kinds alert the phone. Payments, signatures and membership news always do (ClubNotification::PUSH_OPTIONAL).
const PUSH_KINDS = [
    { value: 'summons', label: 'Summonses' },
    { value: 'event', label: 'New events' },
    { value: 'reminder', label: 'Event reminders' },
    { value: 'news', label: 'News' },
    { value: 'notice', label: 'Notices' },
];
const togglePush = (kind, on) => router.post(route('members.push_settings.update'), {
    muted: on ? props.pushMuted.filter((muted) => muted !== kind) : [...props.pushMuted, kind],
}, { preserveScroll: true });

// The lock is kept on this phone, so it is read from the phone rather than sent by the server.
const LOCK_NAMES = { face: 'Face ID', fingerprint: 'fingerprint', passcode: 'passcode' };
const lockSupport = ref(null);
const lockOn = ref(appLockOn());
const lockError = ref('');
onMounted(async () => {
    lockSupport.value = await appLockSupport();
});
// Face ID / fingerprint sign-in: the key is on this phone, so its state is read from the phone too.
const user = usePage().props.auth?.user;
const signIn = ref(null);
const signInPassword = ref('');
const signInNeedsPassword = ref(false);
const signInError = ref('');
const signInBusy = ref(false);
const refreshSignIn = async () => {
    signIn.value = await biometricSignIn();
};
onMounted(refreshSignIn);
const signInOn = () => signIn.value?.owner?.id === user?.id;
const signInName = () => (signIn.value?.kind === 'face' ? 'Face ID' : 'fingerprint');
const toggleSignIn = async (on) => {
    signInError.value = '';
    signInBusy.value = true;
    try {
        if (on) {
            await turnOnBiometricSignIn(user, signInNeedsPassword.value ? signInPassword.value : null);
            signInNeedsPassword.value = false;
            signInPassword.value = '';
        } else {
            await turnOffBiometricSignIn(user);
        }
    } catch (errors) {
        signInNeedsPassword.value = Boolean(errors?.password);
        signInError.value = errors?.password ?? errors?.key ?? 'That did not work.';
    } finally {
        signInBusy.value = false;
        await refreshSignIn();
    }
};

const toggleLock = async (on) => {
    lockError.value = '';
    try {
        await setAppLock(on);
        lockOn.value = on;
    } catch {
        lockOn.value = appLockOn();
        lockError.value = 'The lock was not turned on.';
    }
};
</script>

<template>
    <MembersLayout title="App settings" active-tab="">
        <div class="mx-auto max-w-xl space-y-6">
            <h2 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">App settings</h2>

            <Card>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Phone notifications</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Choose what this app alerts you about. Everything still arrives in your inbox, and payments, signatures and membership news always alert you.</p>
                <div class="mt-3 grid gap-2">
                    <label v-for="kind in PUSH_KINDS" :key="kind.value" class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-200">
                        <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="!pushMuted.includes(kind.value)" @change="togglePush(kind.value, $event.target.checked)" />
                        {{ kind.label }}
                    </label>
                </div>
                <Link :href="route('members.passed_on.choices')" class="mt-3 inline-block text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Choose what is passed on to you from other lodges</Link>
            </Card>

            <Card v-if="signIn?.available">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Sign in with {{ signInName() }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sign back in with your {{ signInName() === 'Face ID' ? 'face' : 'fingerprint' }} instead of your password. It stops working if you change your password or add a new face or fingerprint to this phone.</p>
                <label class="mt-3 flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="signInOn()" :disabled="signInBusy" @change="toggleSignIn($event.target.checked)" />
                    Sign in with {{ signInName() }} on this phone
                </label>
                <form v-if="signInNeedsPassword" class="mt-3 flex items-end gap-2" @submit.prevent="toggleSignIn(true)">
                    <Input v-model="signInPassword" type="password" label="Your password" autocomplete="current-password" class="flex-1" />
                    <Button type="submit" :loading="signInBusy">Turn on</Button>
                </form>
                <p v-if="signInError" class="mt-2 text-xs text-rose-700 dark:text-rose-400" role="alert">{{ signInError }}</p>
            </Card>

            <Card v-if="lockSupport?.available">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">App lock</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Ask for your {{ LOCK_NAMES[lockSupport.kind] ?? 'passcode' }} when the app opens, or when you come back to it after a minute away. Your phone's passcode always works too.</p>
                <label class="mt-3 flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-200">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600" :checked="lockOn" @change="toggleLock($event.target.checked)" />
                    Lock with {{ LOCK_NAMES[lockSupport.kind] ?? 'passcode' }}
                </label>
                <p v-if="lockError" class="mt-2 text-xs text-rose-700 dark:text-rose-400" role="alert">{{ lockError }}</p>
            </Card>
        </div>
    </MembersLayout>
</template>
