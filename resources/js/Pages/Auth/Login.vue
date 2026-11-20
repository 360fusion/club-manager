<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { biometricSignIn, isNativeApp, signInWithBiometrics } from '@/Support/nativeApp';
import { passkeyLikelyHere, signInWithPasskey } from '@/Support/passkeys';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    demoCredentials: { type: Object, default: null },
});

const page = usePage();
const notice = computed(() => page.props.flash?.success || page.props.status || null);

const form = useForm({
    email: props.demoCredentials?.email ?? '',
    password: props.demoCredentials?.password ?? '',
    remember: true,
});

// In the app, a phone that turned on Face ID or fingerprint sign-in shows a button for it (DeviceSignInController).
const biometric = ref(null);
const refreshBiometric = async () => {
    const state = await biometricSignIn();
    biometric.value = state.available && state.owner ? state : null;
};
onMounted(refreshBiometric);
const biometricLabel = computed(() => (biometric.value?.kind === 'face' ? 'Sign in with Face ID' : 'Sign in with your fingerprint'));
const biometricBusy = ref(false);
const useBiometrics = () => {
    biometricBusy.value = true;
    // Afterwards the button is checked again: a key the phone or the server dropped hides it.
    signInWithBiometrics({ onFinish: () => { biometricBusy.value = false; refreshBiometric(); } });
};

// On the website, a passkey (Face ID, fingerprint or the computer's own lock) signs in without the password. The app has
// its own Face ID sign-in above.
const canUsePasskey = ref(false);
onMounted(async () => { canUsePasskey.value = !isNativeApp && await passkeyLikelyHere(); });
const passkeyBusy = ref(false);
const passkeyError = ref('');
const usePasskey = async () => {
    passkeyBusy.value = true;
    passkeyError.value = '';
    try {
        await signInWithPasskey(form.remember);
    } catch (error) {
        passkeyError.value = error?.name === 'NotAllowedError'
            ? 'Cancelled, or there is no passkey for this site on this device. Add one on your security page after signing in with your password.'
            : error.message;
        passkeyBusy.value = false;
    }
};

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign in" />

    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-200 font-sans flex flex-col justify-center px-6 py-8">
        <div class="w-full max-w-sm mx-auto flex flex-col gap-6">
            <div class="flex flex-col items-center gap-3 text-center">
                <!-- cm-nav gives the tile the lodge navy of the app's top bar. -->
                <div class="cm-nav w-18 h-18 rounded-2xl bg-slate-800 text-gold-500 dark:text-gold-400 flex items-center justify-center">
                    <UiIcon name="bank" :size="36" />
                </div>
                <h1 class="font-display text-3xl font-semibold text-slate-900 dark:text-white">Club Manager</h1>
                <div class="w-10 h-0.75 rounded-full bg-gold-500 dark:bg-gold-400" aria-hidden="true"></div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Your lodge meetings, dinners and notices in one place.</p>
            </div>

            <Alert v-if="notice" variant="success">{{ notice }}</Alert>
            <Alert v-if="page.props.errors?.device_key" variant="warning" role="alert">{{ page.props.errors.device_key }}</Alert>

            <div v-if="biometric" class="flex flex-col gap-2">
                <Button size="lg" block :loading="biometricBusy" @click="useBiometrics">{{ biometricLabel }}</Button>
                <p class="text-center text-xs text-slate-500 dark:text-slate-400">As {{ biometric.owner.name }}. Or sign in with your password below.</p>
            </div>

            <div v-if="canUsePasskey && !biometric" class="flex flex-col gap-2">
                <Button variant="secondary" size="lg" block :loading="passkeyBusy" @click="usePasskey">Sign in with your face or fingerprint</Button>
                <p v-if="passkeyError" class="text-center text-xs text-rose-600 dark:text-rose-400" role="alert">{{ passkeyError }}</p>
            </div>

            <!-- Demo credentials, only sent on a local install -->
            <Alert v-if="demoCredentials" variant="info" title="Demo admin credentials">
                <div class="font-mono">Email: <strong>{{ demoCredentials.email }}</strong></div>
                <div class="font-mono">Password: <strong>{{ demoCredentials.password }}</strong></div>
            </Alert>

            <Card padding="sm">
                <form @submit.prevent="submit" class="flex flex-col gap-4">
                    <Input
                        v-model="form.email"
                        label="Email"
                        type="email"
                        required
                        autocomplete="username"
                        :error="form.errors.email"
                    />

                    <Input
                        v-model="form.password"
                        label="Password"
                        type="password"
                        required
                        autocomplete="current-password"
                        :error="form.errors.password"
                    />

                    <label class="flex items-center gap-2 min-h-11 text-sm text-slate-700 dark:text-slate-300 cursor-pointer">
                        <input type="checkbox" v-model="form.remember" class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-blue-600" />
                        Remember me
                    </label>

                    <Button type="submit" size="lg" block :loading="form.processing">
                        Sign in
                    </Button>

                    <Link :href="route('password.request')" class="inline-flex items-center justify-center min-h-11 text-sm font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                        Forgotten your password?
                    </Link>
                </form>
            </Card>

            <p class="text-xs text-center text-slate-500 dark:text-slate-400">New to your lodge? Ask your secretary for an invitation.</p>

            <Link href="/" class="self-center inline-flex items-center gap-1.5 min-h-11 text-xs text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <UiIcon name="arrow-left" /> Back to platform hub
            </Link>
        </div>
    </div>
</template>
