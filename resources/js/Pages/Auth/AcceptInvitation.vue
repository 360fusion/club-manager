<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: {
    type: Object,
    required: true,
  },
  token: {
    type: String,
    required: true,
  },
  user: {
    type: Object,
    required: true,
  },
  isExistingUser: {
    type: Boolean,
    default: false,
  },
});

const form = useForm({
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('invitation.submit', { slug: props.club.slug, token: props.token }), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <Head :title="`Join ${club.name}`" />

  <!-- The same look as the sign-in page, with the lodge's own name and logo. -->
  <div class="min-h-dvh bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-200 font-sans flex flex-col justify-center px-4 sm:px-6 py-8">
    <div class="w-full max-w-sm mx-auto flex flex-col gap-6">
      <div class="flex flex-col items-center gap-3 text-center">
        <img v-if="club.logo_url" :src="club.logo_url" :alt="club.name" class="h-16 max-w-44 object-contain" />
        <div v-else class="cm-nav w-18 h-18 rounded-2xl bg-slate-800 text-gold-500 dark:text-gold-400 flex items-center justify-center">
          <UiIcon name="bank" :size="36" />
        </div>
        <h1 class="font-display text-3xl font-semibold text-slate-900 dark:text-white">{{ isExistingUser ? `Welcome back, ${user.name}` : `Welcome to ${club.name}` }}</h1>
        <div class="w-10 h-0.75 rounded-full bg-gold-500 dark:bg-gold-400" aria-hidden="true"></div>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          {{ isExistingUser ? `Sign in with your password to join ${club.name}.` : `${club.name} has invited you to its meetings, dinners and notices. Choose a password to finish.` }}
        </p>
      </div>

      <Alert v-if="Object.keys(form.errors).length" variant="danger">
        <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
      </Alert>

      <Card padding="sm">
        <form @submit.prevent="submit" class="flex flex-col gap-4">
          <div class="text-sm">
            <div class="font-semibold text-slate-900 dark:text-white">{{ user.name }}</div>
            <div class="text-slate-500 dark:text-slate-400 break-all">{{ user.email }}</div>
          </div>

          <Input
            v-model="form.password"
            :label="isExistingUser ? 'Your password' : 'Choose a password'"
            type="password"
            required
            autofocus
            :autocomplete="isExistingUser ? 'current-password' : 'new-password'"
            :hint="isExistingUser ? null : 'At least 8 characters.'"
          />

          <Input
            v-if="!isExistingUser"
            v-model="form.password_confirmation"
            label="Type it again"
            type="password"
            required
            autocomplete="new-password"
          />

          <Button type="submit" size="lg" block :loading="form.processing">
            {{ isExistingUser ? `Sign in and join ${club.name}` : 'Finish and sign in' }}
          </Button>
        </form>
      </Card>
    </div>
  </div>
</template>
