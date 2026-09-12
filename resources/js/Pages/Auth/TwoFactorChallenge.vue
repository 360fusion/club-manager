<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';

const useRecoveryCode = ref(false);

const form = useForm({
  code: '',
  recovery_code: '',
});

const submit = () => {
  form.post('/two-factor-challenge', {
    onFinish: () => form.reset(),
  });
};
</script>

<template>
  <Head title="Two-Factor Verification" />

  <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-3xl p-8 shadow-2xl shadow-indigo-950/40 space-y-6">
      
      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-2xl mb-2">
          🔐
        </div>
        <h1 class="text-2xl font-black bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
          Two-Factor Authentication
        </h1>
        <p class="text-xs text-slate-400">
          {{ useRecoveryCode ? 'Enter one of your emergency recovery codes.' : 'Enter the 6-digit verification code from your authenticator app.' }}
        </p>
      </div>

      <!-- Errors -->
      <div v-if="form.errors.code" class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 rounded-xl text-xs text-center font-medium">
        {{ form.errors.code }}
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-5">
        <div v-if="!useRecoveryCode">
          <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
            6-Digit Authenticator Code
          </label>
          <input
            v-model="form.code"
            type="text"
            inputmode="numeric"
            autofocus
            placeholder="123456"
            maxlength="6"
            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-center text-xl font-mono tracking-widest text-white focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        <div v-else>
          <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
            Emergency Recovery Code
          </label>
          <input
            v-model="form.recovery_code"
            type="text"
            autofocus
            placeholder="abcdef-12345"
            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-center font-mono text-sm text-white focus:outline-none focus:border-indigo-500 transition-colors"
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full py-3.5 bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-50"
        >
          {{ form.processing ? 'Verifying...' : 'Verify Code & Sign In' }}
        </button>

        <div class="text-center pt-2">
          <button
            type="button"
            @click="useRecoveryCode = !useRecoveryCode; form.reset()"
            class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors font-medium"
          >
            {{ useRecoveryCode ? 'Use 6-digit authenticator code instead' : 'Use an emergency recovery code' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
