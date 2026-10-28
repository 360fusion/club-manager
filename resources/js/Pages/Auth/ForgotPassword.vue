<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
  status: String,
});

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <Head title="Forgot password" />

  <!-- The card is always dark, so the kit components take their dark look inside it. -->
  <div class="dark min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 shadow-2xl shadow-blue-950/40 space-y-6">

      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 mb-2">
          <UiIcon name="key" :size="20" />
        </div>
        <h1 class="text-2xl font-semibold text-white">
          Forgot your password?
        </h1>
        <p class="text-xs text-slate-400">
          Enter your registered email address and we'll send you a password reset link.
        </p>
      </div>

      <!-- Status notification -->
      <Alert v-if="status" variant="success">{{ status }}</Alert>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-5">
        <Input
          v-model="form.email"
          label="Email address"
          type="email"
          required
          autofocus
          placeholder="admin@example.com"
          :error="form.errors.email"
        />

        <Button type="submit" size="lg" block :loading="form.processing">
          {{ form.processing ? 'Sending link...' : 'Send password reset link' }}
        </Button>

        <div class="text-center pt-2">
          <Link :href="route('login')" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-white transition-colors">
            <UiIcon name="arrow-left" /> Back to login
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>
