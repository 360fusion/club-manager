<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import SignaturePad from '@/Components/Ui/SignaturePad.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="check" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Signed, thank you</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You signed <strong>{{ documentLabel }}</strong> on {{ signedAt }}.</p>
                    <!-- A plain link, not the kit Button's Inertia Link, so the browser downloads the PDF. -->
                    <a :href="route('member.signatures.pdf', { slug: club.slug, id })" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"><UiIcon name="download" />Download PDF copy</a>
                </div>
            </Card>

            <Card v-else-if="status === 'declined'">
                <div class="text-center space-y-3 py-4">
                    <div class="w-12 h-12 bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="x" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">Declined</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">You declined to sign <strong>{{ documentLabel }}</strong>.</p>
                </div>
            </Card>

            <Card v-else-if="status === 'expired' || status === 'cancelled'">
                <div class="text-center space-y-3 py-4">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400 rounded-2xl flex items-center justify-center mx-auto"><UiIcon name="warning" :size="20" /></div>
                    <h1 class="text-xl font-semibold text-slate-900 dark:text-white">This request is no longer open</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">Ask whoever requested it to send a fresh request if a signature is still needed.</p>
                </div>
            </Card>

            <Card v-else>
                <div class="space-y-4">
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900 dark:text-white">You have been asked to sign</h1>
                        <p class="text-base font-semibold text-slate-900 dark:text-white mt-1">{{ documentLabel }}</p>
                    </div>

                    <div v-if="!declining" class="space-y-4">
                        <SignaturePad @update="onSignatureUpdate" />

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
                </div>
            </Card>
        </div>
    </MembersLayout>
</template>
