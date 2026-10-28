<script setup>
import { ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

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
  <Head title="Two-factor verification" />

  <!-- The card is always dark, so the kit components take their dark look inside it. -->
  <div class="dark min-h-screen bg-slate-950 flex flex-col justify-center items-center p-6 text-slate-100 font-sans">
    <div class="w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-8 shadow-2xl shadow-blue-950/40 space-y-6">

      <!-- Header -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 mb-2">
          <UiIcon name="shield" :size="20" />
        </div>
        <h1 class="text-2xl font-semibold text-white">
          Two-factor authentication
        </h1>
        <p class="text-xs text-slate-400">
          {{ useRecoveryCode ? 'Enter one of your emergency recovery codes.' : 'Enter the 6-digit verification code from your authenticator app.' }}
        </p>
      </div>

      <!-- Errors -->
      <Alert v-if="form.errors.code || form.errors.recovery_code" variant="danger">{{ form.errors.code || form.errors.recovery_code }}</Alert>

      <!-- Form. The code fields stay raw inputs (styled like the kit's) so the code can be centred in a monospace face. -->
      <form @submit.prevent="submit" class="space-y-5">
        <div v-if="!useRecoveryCode" class="space-y-1.5">
          <label for="two-factor-code" class="block text-xs font-medium text-slate-300">
            6-digit authenticator code
          </label>
          <input
            id="two-factor-code"
            v-model="form.code"
            type="text"
            inputmode="numeric"
            autofocus
            placeholder="123456"
            maxlength="6"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-center text-xl font-mono tracking-widest text-white placeholder-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <div v-else class="space-y-1.5">
          <label for="two-factor-recovery-code" class="block text-xs font-medium text-slate-300">
            Emergency recovery code
          </label>
          <input
            id="two-factor-recovery-code"
            v-model="form.recovery_code"
            type="text"
            autofocus
            placeholder="abcdef-12345"
            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-center font-mono text-sm text-white placeholder-slate-500 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>

        <Button type="submit" size="lg" block :loading="form.processing">
          {{ form.processing ? 'Verifying...' : 'Verify code and sign in' }}
        </Button>

        <div class="text-center pt-2">
          <Button variant="ghost" size="sm" @click="useRecoveryCode = !useRecoveryCode; form.reset()">
            {{ useRecoveryCode ? 'Use 6-digit authenticator code instead' : 'Use an emergency recovery code' }}
          </Button>
        </div>
      </form>
    </div>
  </div>
</template>
