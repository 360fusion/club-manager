<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import FormField from '../../../Components/FormField.vue';
import Turnstile from '../../../Components/Turnstile.vue';

const props = defineProps({
    topics: { type: Array, default: () => [] },
    turnstileKey: { type: String, default: null },
    user: { type: Object, default: null },
    sent: { type: String, default: null },
});

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    topic: props.topics[0] ?? '',
    message: '',
    website: '',
    cf_turnstile_response: '',
});
</script>

<template>
    <Head :title="$t('Contact us')">
        <meta name="description" :content="$t('Send us a message and we will reply by email.')">
    </Head>
    <div class="lp-narrow">
        <div class="lp-card">
            <h1>{{ $t('Contact us') }}</h1>
            <div v-if="sent" class="lp-alert" role="status">
                <Trans k="Thank you, we have your message. Your reference is :reference; we have emailed you a copy.">
                    <template #reference><strong>{{ sent }}</strong></template>
                </Trans>
            </div>
            <template v-else>
                <p class="lp-muted">
                    <Trans k="Tell us what you need and we will reply by email. Looking for an answer now? Try the :help.">
                        <template #help><a href="/help">{{ $t('help centre') }}</a></template>
                    </Trans>
                </p>
                <form @submit.prevent="form.post('/contact', { onSuccess: () => form.reset('message') })">
                    <FormField id="name" v-model="form.name" :label="$t('Your name')" autocomplete="name" :error="form.errors.name" />
                    <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="email" :error="form.errors.email" />
                    <div v-if="topics.length" class="lp-field">
                        <label for="topic">{{ $t('Topic') }}</label>
                        <select id="topic" v-model="form.topic"><option v-for="topic in topics" :key="topic" :value="topic">{{ $t(topic) }}</option></select>
                        <p v-if="form.errors.topic" class="lp-error">{{ form.errors.topic }}</p>
                    </div>
                    <div class="lp-field">
                        <label for="message">{{ $t('Message') }}</label>
                        <textarea id="message" v-model="form.message" rows="6" required :aria-invalid="form.errors.message ? 'true' : null" />
                        <p v-if="form.errors.message" class="lp-error">{{ form.errors.message }}</p>
                    </div>
                    <!-- a field only a script fills in: people never see it -->
                    <div class="lp-skip" aria-hidden="true"><label for="website">{{ $t('Leave this empty') }}</label><input id="website" v-model="form.website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
                    <Turnstile :site-key="turnstileKey" :error="form.errors.turnstile" @verified="form.cf_turnstile_response = $event" />
                    <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Send message') }}</button>
                </form>
            </template>
        </div>
    </div>
</template>
