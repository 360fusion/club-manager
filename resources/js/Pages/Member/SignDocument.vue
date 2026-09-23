<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';
import SignaturePad from '@/Components/Ui/SignaturePad.vue';

const props = defineProps({
    club: { type: Object, required: true },
    id: { type: Number, required: true },
    status: { type: String, required: true },
    documentLabel: { type: String, required: true },
    signedAt: { type: String, default: null },
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
    form.post(route('member.signatures.store', { slug: props.club.slug, id: props.id }), { preserveScroll: true });
};

const declining = ref(false);
const declineForm = useForm({ reason: '' });
const submitDecline = () => {
    declineForm.post(route('member.signatures.decline', { slug: props.club.slug, id: props.id }), { preserveScroll: true });
};
</script>

<template>
    <MembersLayout title="Sign document" :club="club" active-tab="dashboard">
        <div class="max-w-lg space-y-4">
            <Card v-if="status === 'signed'">
                <div class="text-center space-y-3 py-4">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">✓</div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Signed, thank you</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You signed <strong>{{ documentLabel }}</strong> on {{ signedAt }}.</p>
                    <a :href="route('member.signatures.pdf', { slug: club.slug, id })" class="inline-block px-4 py-2 bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white font-bold rounded-xl text-xs">Download PDF copy</a>
                </div>
            </Card>

            <Card v-else-if="status === 'declined'">
                <div class="text-center space-y-3 py-4">
                    <div class="w-16 h-16 bg-slate-100 text-slate-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">✕</div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">Declined</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You declined to sign <strong>{{ documentLabel }}</strong>.</p>
                </div>
            </Card>

            <Card v-else-if="status === 'expired' || status === 'cancelled'">
                <div class="text-center space-y-3 py-4">
                    <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center text-3xl mx-auto">⚠️</div>
                    <h1 class="text-xl font-bold text-slate-900 dark:text-white">This request is no longer open</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Ask whoever requested it to send a fresh request if a signature is still needed.</p>
                </div>
            </Card>

            <Card v-else>
                <div class="space-y-4">
                    <div>
                        <h1 class="text-lg font-bold text-slate-900 dark:text-white">You have been asked to sign</h1>
                        <p class="text-base font-bold text-slate-900 dark:text-white mt-1">{{ documentLabel }}</p>
                    </div>

                    <div v-if="!declining" class="space-y-4">
                        <SignaturePad @update="onSignatureUpdate" />

                        <label class="flex items-start gap-2 text-xs text-slate-600 dark:text-slate-400 cursor-pointer">
                            <input type="checkbox" v-model="form.consent" class="mt-0.5 rounded" />
                            <span>I confirm this is my electronic signature and I intend it to have the same effect as a handwritten signature.</span>
                        </label>
                        <p v-if="form.errors.consent" class="text-rose-600 text-xs font-semibold">Please confirm the statement above.</p>
                        <p v-if="form.errors.signature" class="text-rose-600 text-xs font-semibold">{{ form.errors.signature }}</p>
                        <p v-if="form.errors.image" class="text-rose-600 text-xs font-semibold">{{ form.errors.image }}</p>

                        <div class="flex items-center justify-between gap-3 pt-2">
                            <button type="button" @click="declining = true" class="text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 cursor-pointer">I can't sign this</button>
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
                        <label class="block text-xs font-bold text-slate-600 dark:text-slate-400">Why can't you sign this? (optional)</label>
                        <textarea v-model="declineForm.reason" rows="2" class="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl"></textarea>
                        <div class="flex items-center justify-between gap-3">
                            <button type="button" @click="declining = false" class="text-xs font-bold text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 cursor-pointer">Back</button>
                            <button type="button" @click="submitDecline" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl text-sm cursor-pointer">Confirm I can't sign</button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </MembersLayout>
</template>
