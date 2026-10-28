<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  email: String,
  token: String,
});

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('password.update'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <Head title="Reset password" />

  <!-- The card is always dark, so the kit components take their dark look inside it. -->
  <div class="dark min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 shadow-2xl shadow-blue-950/40 space-y-6">

      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 mb-2">
          <UiIcon name="lock" :size="20" />
        </div>
        <h1 class="text-2xl font-semibold text-white">
          Set a new password
        </h1>
        <p class="text-xs text-slate-400">
          Enter your email and choose a strong new password for your account.
        </p>
      </div>

      <!-- Errors -->
      <Alert v-if="Object.keys(form.errors).length" variant="danger">
        <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
      </Alert>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <Input
          v-model="form.email"
          label="Email address"
          type="email"
          required
        />

        <Input
          v-model="form.password"
          label="New password"
          type="password"
          required
          autofocus
          placeholder="••••••••"
        />

        <Input
          v-model="form.password_confirmation"
          label="Confirm new password"
          type="password"
          required
          placeholder="••••••••"
        />

        <Button type="submit" size="lg" block :loading="form.processing" class="mt-2">
          {{ form.processing ? 'Resetting...' : 'Reset password and log in' }}
        </Button>
      </form>
    </div>
  </div>
</template>
