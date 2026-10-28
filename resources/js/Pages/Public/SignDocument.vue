<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Button from '@/Components/Ui/Button.vue';
import SignaturePad from '@/Components/Ui/SignaturePad.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    found: Boolean,
    token: { type: String, default: null },
    status: { type: String, default: null },
    clubName: { type: String, default: null },
    signerName: { type: String, default: null },
    documentLabel: { type: String, default: null },
    signedAt: { type: String, default: null },
    method: { type: String, default: null },
    typedName: { type: String, default: null },
});

const signature = ref({ method: 'typed', typedName: '', image: null, isValid: false });

const form = useForm({
    method: 'typed',
    typed_name: '',
    image: null,
    consent: false,
});

const onSignatureUpdate = (value) => {
    signature.value = value;
    form.method = value.method;
    form.typed_name = value.typedName;
    form.image = value.image;
};

const submit = () => {
    form.post(route('sign.store', { token: props.token }), { preserveScroll: true });
};

const declining = ref(false);
const declineForm = useForm({ reason: '' });
const submitDecline = () => {
    declineForm.post(route('sign.decline', { token: props.token }), { preserveScroll: true });
};
</script>

<template>
    <Head title="Sign document" />

    <div class="min-h-screen bg-slate-100 dark:bg-slate-950 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-lg w-full mx-auto bg-white dark:bg-slate-900 rounded-2xl p-8 shadow-xl border border-slate-200 dark:border-slate-800 space-y-5">
            <template v-if="!found">
                <div class="text-center space-y-3">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="warning" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Link expired or invalid</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">This signature link is no longer valid. Please ask whoever sent it to resend it.</p>
                </div>
            </template>

            <template v-else-if="status === 'signed'">
                <div class="text-center space-y-3">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="check" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Signed, thank you</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You signed <strong>{{ documentLabel }}</strong> on {{ signedAt }}.</p>
                </div>
            </template>

            <template v-else-if="status === 'declined'">
                <div class="text-center space-y-3">
                    <div class="w-12 h-12 bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="x" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Declined</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You declined to sign <strong>{{ documentLabel }}</strong>. Contact {{ clubName }} if this was not what you intended.</p>
                </div>
            </template>

            <template v-else-if="status === 'expired' || status === 'cancelled'">
                <div class="text-center space-y-3">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="warning" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">This request is no longer open</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Ask {{ clubName }} to send you a fresh link if a signature is still needed.</p>
                </div>
            </template>

            <template v-else>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">{{ clubName }}</p>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white mt-1">Hello {{ signerName }}</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-400 mt-2">You have been asked to sign:</p>
                    <p class="text-base font-semibold text-slate-900 dark:text-white mt-1">{{ documentLabel }}</p>
                </div>

                <div v-if="!declining" class="space-y-4">
                    <SignaturePad :signer-name="signerName" @update="onSignatureUpdate" />

                    <label class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                        <input type="checkbox" v-model="form.consent" class="mt-0.5 rounded" />
                        <span>I confirm this is my electronic signature and I intend it to have the same effect as a handwritten signature.</span>
                    </label>
                    <p v-if="form.errors.consent" class="text-rose-600 dark:text-rose-400 text-xs font-medium">Please confirm the statement above.</p>
                    <p v-if="form.errors.signature" class="text-rose-600 dark:text-rose-400 text-xs font-medium">{{ form.errors.signature }}</p>
                    <p v-if="form.errors.image" class="text-rose-600 dark:text-rose-400 text-xs font-medium">{{ form.errors.image }}</p>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <Button variant="ghost" size="sm" @click="declining = true">I can't sign this</Button>
                        <Button size="lg" :disabled="!signature.isValid || !form.consent || form.processing" @click="submit">Sign</Button>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <Textarea v-model="declineForm.reason" :rows="2" label="Why can't you sign this? (optional)" />
                    <div class="flex items-center justify-between gap-3">
                        <Button variant="ghost" size="sm" @click="declining = false">Back</Button>
                        <Button size="lg" @click="submitDecline">Confirm I can't sign</Button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
