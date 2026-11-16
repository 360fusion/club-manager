<script setup>
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AccountShell from '../../../Components/AccountShell.vue';
import Avatar from '../../../Components/Avatar.vue';
import FormField from '../../../Components/FormField.vue';

const props = defineProps({
    name: { type: String, required: true },
    email: { type: String, required: true },
    verified: Boolean,
    timezone: { type: String, default: null },
    timezones: { type: Array, default: () => [] },
    locale: { type: String, default: null },
    theme: { type: String, default: null },
    needsCurrentPassword: Boolean,
    avatarUrl: { type: String, default: null },
});

// a file goes as a multipart post, which Inertia does by itself when the form holds a File
const photo = useForm({ avatar: null });
const upload = () => photo.post('/account/profile/avatar', { forceFormData: true, onSuccess: () => photo.reset() });

const page = usePage();
const locales = computed(() => page.props.locales ?? []);

// the language is only sent when the page offers a choice, so a saved one is never cleared by a hidden select
const form = useForm({ name: props.name, email: props.email, timezone: props.timezone ?? '', theme: props.theme ?? '', ...(locales.value.length > 1 ? { locale: props.locale ?? '' } : {}), current_password: '' });
// a new address is where reset mail will go: the password is asked for before it changes
const changingEmail = computed(() => form.email.trim().toLowerCase() !== props.email);
// the page does not reload after saving, so the chosen appearance is put on the page here too
const applyTheme = () => (form.theme ? document.documentElement.setAttribute('data-theme', form.theme) : document.documentElement.removeAttribute('data-theme'));
const save = () => form.put('/account/profile', { onSuccess: () => { form.reset('current_password'); applyTheme(); } });
</script>

<template>
    <Head :title="$t('Profile')" />
    <AccountShell>
        <div class="lp-card">
            <h1>{{ $t('Profile') }}</h1>
            <div class="lp-avatar-edit">
                <Avatar :url="avatarUrl" :name="name || email" :size="72" />
                <div>
                    <form @submit.prevent="upload">
                        <div class="lp-field">
                            <label for="avatar">{{ $t('Photo') }}</label>
                            <input id="avatar" type="file" accept="image/jpeg,image/png,image/webp" required :aria-invalid="photo.errors.avatar ? 'true' : null" @input="photo.avatar = $event.target.files[0]">
                            <p v-if="photo.errors.avatar" class="lp-error">{{ photo.errors.avatar }}</p>
                        </div>
                        <button class="lp-btn lp-btn--quiet" type="submit" :disabled="photo.processing">{{ $t('Upload') }}</button>
                    </form>
                    <button v-if="avatarUrl" class="lp-btn lp-btn--quiet" type="button" @click="router.delete('/account/profile/avatar')">{{ $t('Remove photo') }}</button>
                    <p class="lp-muted">{{ $t('JPEG, PNG or WebP, up to 2 MB. It is cut to a square.') }}</p>
                </div>
            </div>
            <form @submit.prevent="save">
                <FormField id="name" v-model="form.name" :label="$t('Name')" autocomplete="name" :error="form.errors.name" />
                <FormField id="email" v-model="form.email" :label="$t('Email address')" type="email" autocomplete="email" :error="form.errors.email" />
                <p v-if="!verified" class="lp-muted">
                    <Trans k="Your email address is not confirmed yet. :send.">
                        <template #send><Link href="/verify-email">{{ $t('Send the link again') }}</Link></template>
                    </Trans>
                </p>
                <FormField v-if="changingEmail && needsCurrentPassword" id="current_password" v-model="form.current_password" :label="$t('Current password (to change your email)')" type="password" autocomplete="current-password" :error="form.errors.current_password" />
                <div class="lp-field">
                    <label for="timezone">{{ $t('Time zone') }}</label>
                    <select id="timezone" v-model="form.timezone">
                        <option value="">{{ $t('Not set') }}</option>
                        <option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option>
                    </select>
                    <p v-if="form.errors.timezone" class="lp-error">{{ form.errors.timezone }}</p>
                </div>
                <div v-if="locales.length > 1" class="lp-field">
                    <label for="locale">{{ $t('Language') }}</label>
                    <select id="locale" v-model="form.locale">
                        <option value="">{{ $t("Use the browser's") }}</option>
                        <option v-for="language in locales" :key="language.code" :value="language.code">{{ language.name }}</option>
                    </select>
                    <p v-if="form.errors.locale" class="lp-error">{{ form.errors.locale }}</p>
                </div>
                <div class="lp-field">
                    <label for="theme">{{ $t('Appearance') }}</label>
                    <select id="theme" v-model="form.theme">
                        <option value="">{{ $t('As this device is set') }}</option>
                        <option value="light">{{ $t('Light') }}</option>
                        <option value="dark">{{ $t('Dark') }}</option>
                    </select>
                    <p v-if="form.errors.theme" class="lp-error">{{ form.errors.theme }}</p>
                </div>
                <button class="lp-btn" type="submit" :disabled="form.processing">{{ $t('Save') }}</button>
            </form>
        </div>
    </AccountShell>
</template>
