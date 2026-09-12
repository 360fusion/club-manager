<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';

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
  <Head title="Forgot Password" />

  <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-indigo-950/40 space-y-6">
      
      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-2xl mb-2">
          🔑
        </div>
        <h1 class="text-2xl font-black bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
          Forgot Password?
        </h1>
        <p class="text-xs text-slate-400">
          Enter your registered email address and we'll send you a password reset link.
        </p>
      </div>

      <!-- Status Notification -->
      <div v-if="status" class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-xs font-medium text-center">
        {{ status }}
      </div>

      <!-- Errors -->
      <div v-if="form.errors.email" class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs text-center font-medium">
        {{ form.errors.email }}
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
            Email Address
          </label>
          <input
            v-model="form.email"
            type="email"
            required
            autofocus
            placeholder="admin@example.com"
            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-50"
        >
          {{ form.processing ? 'Sending Link...' : 'Send Password Reset Link' }}
        </button>

        <div class="text-center pt-2">
          <Link :href="route('login')" class="text-xs text-slate-400 hover:text-white transition-colors">
            &larr; Back to Login
          </Link>
        </div>
      </form>
    </div>
  </div>
</template>
