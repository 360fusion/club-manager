<script setup>
// In the app, straight after signing in with a password: offer Face ID or fingerprint sign-in once (nativeApp.js).
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';
import { biometricSignIn, declineBiometricSignIn, shouldOfferBiometricSignIn, turnOnBiometricSignIn } from '@/Support/nativeApp';

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const kind = ref(null);
const open = ref(false);
const busy = ref(false);
const message = ref('');

onMounted(async () => {
    if (await shouldOfferBiometricSignIn(user.value)) {
        kind.value = (await biometricSignIn()).kind;
        open.value = true;
    }
});

const method = computed(() => (kind.value === 'face' ? 'Face ID' : 'your fingerprint'));

const turnOn = async () => {
    busy.value = true;
    message.value = '';
    try {
        await turnOnBiometricSignIn(user.value);
        open.value = false;
    } catch (errors) {
        message.value = errors?.password ?? errors?.key ?? 'It was not turned on.';
    } finally {
        busy.value = false;
    }
};

const notNow = () => {
    declineBiometricSignIn(user.value);
    open.value = false;
};
</script>

<template>
    <div v-if="open" class="fixed inset-x-0 bottom-0 z-50 px-4 pb-[calc(env(safe-area-inset-bottom)+5rem)]">
        <section role="dialog" aria-labelledby="biometric-offer-title" class="mx-auto max-w-md space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-900">
            <h2 id="biometric-offer-title" class="text-base font-semibold text-slate-900 dark:text-white">Sign in with {{ method }} next time?</h2>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Your phone keeps a sign-in key that only {{ method }} can unlock. Club Manager never sees your face or fingerprint. You can turn it off in App settings.
            </p>
            <p v-if="message" class="text-sm text-rose-700 dark:text-rose-400" role="alert">{{ message }}</p>
            <div class="flex gap-2">
                <Button variant="secondary" block @click="notNow">Not now</Button>
                <Button block :loading="busy" @click="turnOn">Turn on</Button>
            </div>
        </section>
    </div>
</template>
