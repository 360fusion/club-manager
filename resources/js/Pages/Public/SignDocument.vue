<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import SignaturePad from '@/Components/Ui/SignaturePad.vue';

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

    <div class="min-h-screen bg-slate-100 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-lg w-full mx-auto bg-white rounded-3xl p-8 shadow-xl border border-slate-200 space-y-5">
            <template v-if="!found">
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">⚠️</div>
                    <h1 class="text-xl font-bold text-slate-900">Link expired or invalid</h1>
                    <p class="text-xs text-slate-500 leading-relaxed">This signature link is no longer valid. Please ask whoever sent it to resend it.</p>
                </div>
            </template>

            <template v-else-if="status === 'signed'">
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">✓</div>
                    <h1 class="text-xl font-bold text-slate-900">Signed, thank you</h1>
                    <p class="text-xs text-slate-500 leading-relaxed">You signed <strong>{{ documentLabel }}</strong> on {{ signedAt }}.</p>
                </div>
            </template>

            <template v-else-if="status === 'declined'">
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">✕</div>
                    <h1 class="text-xl font-bold text-slate-900">Declined</h1>
                    <p class="text-xs text-slate-500 leading-relaxed">You declined to sign <strong>{{ documentLabel }}</strong>. Contact {{ clubName }} if this was not what you intended.</p>
                </div>
            </template>

            <template v-else-if="status === 'expired' || status === 'cancelled'">
                <div class="text-center space-y-3">
                    <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">⚠️</div>
                    <h1 class="text-xl font-bold text-slate-900">This request is no longer open</h1>
                    <p class="text-xs text-slate-500 leading-relaxed">Ask {{ clubName }} to send you a fresh link if a signature is still needed.</p>
                </div>
            </template>

            <template v-else>
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wide">{{ clubName }}</p>
                    <h1 class="text-xl font-bold text-slate-900 mt-1">Hello {{ signerName }}</h1>
                    <p class="text-sm text-slate-600 mt-2">You have been asked to sign:</p>
                    <p class="text-base font-bold text-slate-900 mt-1">{{ documentLabel }}</p>
                </div>

                <div v-if="!declining" class="space-y-4">
                    <SignaturePad :signer-name="signerName" @update="onSignatureUpdate" />

                    <label class="flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
                        <input type="checkbox" v-model="form.consent" class="mt-0.5 rounded" />
                        <span>I confirm this is my electronic signature and I intend it to have the same effect as a handwritten signature.</span>
                    </label>
                    <p v-if="form.errors.consent" class="text-rose-600 text-xs font-semibold">Please confirm the statement above.</p>
                    <p v-if="form.errors.signature" class="text-rose-600 text-xs font-semibold">{{ form.errors.signature }}</p>
                    <p v-if="form.errors.image" class="text-rose-600 text-xs font-semibold">{{ form.errors.image }}</p>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <button type="button" @click="declining = true" class="text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer">I can't sign this</button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="!signature.isValid || !form.consent || form.processing"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm disabled:opacity-50 cursor-pointer"
                        >
                            Sign
                        </button>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <label class="block text-xs font-bold text-slate-600">Why can't you sign this? (optional)</label>
                    <textarea v-model="declineForm.reason" rows="2" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-200 rounded-xl"></textarea>
                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="declining = false" class="text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer">Back</button>
                        <button type="button" @click="submitDecline" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-sm cursor-pointer">Confirm I can't sign</button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
