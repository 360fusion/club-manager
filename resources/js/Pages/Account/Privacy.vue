<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';

const props = defineProps({
  closureRequestedAt: { type: String, default: null },
  purgeDate: { type: String, default: null },
  waitDays: { type: Number, required: true },
  blockers: { type: Array, default: () => [] },
});

const exportPassword = ref('');
const closeForm = useForm({ password: '' });

const csrf = computed(() => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');

// The download is a plain form post so the browser saves the file; Inertia would try to read it as a page.
const exportAction = computed(() => route('account.privacy.export'));

const close = () => closeForm.post(route('account.privacy.close'), {
  preserveScroll: true,
  onFinish: () => closeForm.reset('password'),
});

const cancel = () => router.delete(route('account.privacy.cancel'), { preserveScroll: true });
</script>

<template>
  <MembersLayout title="Data and privacy" active-tab="">
    <div class="max-w-3xl space-y-6">
      <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Data and privacy</h1>

      <Alert v-if="closureRequestedAt" variant="warning">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <span>Your account is set to be closed on <strong>{{ purgeDate }}</strong>. Until then nothing has been removed.</span>
          <Button variant="secondary" @click="cancel">Cancel closing my account</Button>
        </div>
      </Alert>

      <Card class="space-y-3">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Download your data</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">
          A file with your account details, club memberships, the member and contact records clubs hold that are linked to you,
          event bookings, subscriptions, notifications and the names of your API tokens. It never includes your password or any secret.
        </p>
        <form method="post" :action="exportAction" class="flex flex-wrap items-end gap-3">
          <input type="hidden" name="_token" :value="csrf">
          <Input v-model="exportPassword" name="password" type="password" label="Your password" autocomplete="current-password" required :error="$page.props.errors?.export?.password" />
          <Button type="submit">Download my data</Button>
        </form>
      </Card>

      <Card class="space-y-3">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Close your account</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">
          You have {{ waitDays }} days to change your mind: your account is only closed after that, and signing in and cancelling stops it.
          When it closes, you are removed from every club, your sign-in, sessions, notifications, subscriptions and API tokens are deleted,
          and your name and email are blanked.
        </p>
        <p class="text-sm text-slate-600 dark:text-slate-300">
          What stays: each club's own records (its member list, contacts, accounts, audit trail and event bookings) are the club's to keep and are
          unlinked from you. Ask the club if you want a record changed or removed.
        </p>

        <Alert v-if="blockers.length" variant="warning">
          <ul class="list-disc space-y-1 pl-5">
            <li v-for="b in blockers" :key="b">{{ b }}</li>
          </ul>
        </Alert>

        <form v-if="!closureRequestedAt && !blockers.length" class="flex flex-wrap items-end gap-3" @submit.prevent="close">
          <Input v-model="closeForm.password" type="password" label="Your password" autocomplete="current-password" required :error="closeForm.errors.password || closeForm.errors.closure" />
          <Button variant="danger" type="submit" :disabled="closeForm.processing">Close my account</Button>
        </form>
      </Card>
    </div>
  </MembersLayout>
</template>
