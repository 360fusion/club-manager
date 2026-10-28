<script setup>
import { useForm, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

defineProps({
  user: Object,
  twoFactorEnabled: Boolean,
  twoFactorPending: Boolean,
  qrCodeSvg: String,
  secretKey: String,
  recoveryCodes: {
    type: Array,
    default: () => [],
  },
});

const confirmForm = useForm({
  code: '',
});

const enable2FA = () => {
  router.post(route('admin.two-factor.enable'));
};

const confirm2FA = () => {
  confirmForm.post(route('admin.two-factor.confirm'), {
    onSuccess: () => confirmForm.reset(),
  });
};

const disable2FA = () => {
  router.delete(route('admin.two-factor.disable'));
};

const generateNewRecoveryCodes = () => {
  router.post(route('admin.two-factor.recovery-codes'));
};
</script>

<template>
  <MembersLayout title="Security" active-tab="profile">

    <div class="max-w-4xl mx-auto space-y-6">

      <PageHeader
        title="Two-factor authentication (2FA)"
        description="Secure your administrator account using Google Authenticator, Authy, or 1Password."
      >
        <template #actions>
          <Button variant="secondary" size="sm" :href="route('profile.edit')">
            <template #icon><UiIcon name="arrow-left" /></template>
            Back to profile
          </Button>
        </template>
      </PageHeader>

      <!-- Main status card -->
      <Card padding="lg" class="space-y-6">

        <!-- Status header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200 dark:border-slate-800">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-100 dark:border-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
              <UiIcon name="shield" :size="20" />
            </div>
            <div>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">2FA protection status</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ twoFactorEnabled ? 'Two-factor authentication is active and protecting your account.' : (twoFactorPending ? '2FA setup started. Confirm your authenticator code below.' : 'Two-factor authentication is currently off.') }}
              </p>
            </div>
          </div>

          <Badge :variant="twoFactorEnabled ? 'success' : (twoFactorPending ? 'warning' : 'neutral')" class="self-start sm:self-auto">
            {{ twoFactorEnabled ? 'Active' : (twoFactorPending ? 'Pending confirmation' : 'Disabled') }}
          </Badge>
        </div>

        <!-- Setup step 1: enable 2FA -->
        <div v-if="!twoFactorEnabled && !twoFactorPending" class="space-y-4">
          <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
            When two-factor authentication is on, you will be asked for a 6-digit code from your mobile authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.) whenever you sign in.
          </p>

          <Button @click="enable2FA">
            <template #icon><UiIcon name="shield" /></template>
            Enable two-factor authentication
          </Button>
        </div>

        <!-- Setup step 2: pending confirmation (QR code) -->
        <div v-if="twoFactorPending" class="space-y-6">
          <div class="p-6 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center gap-6">
            <div v-if="qrCodeSvg" v-html="qrCodeSvg" class="p-3 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex-shrink-0"></div>

            <div class="space-y-3">
              <h4 class="font-semibold text-slate-900 dark:text-white text-sm">Scan with your authenticator app</h4>
              <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                Open Google Authenticator, Authy, 1Password, or Microsoft Authenticator on your mobile phone and scan the QR code.
              </p>
              <div v-if="secretKey" class="text-xs font-mono bg-white dark:bg-slate-900 px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-200">
                Manual entry key: <strong class="text-blue-600 dark:text-blue-400 select-all font-semibold">{{ secretKey }}</strong>
              </div>
            </div>
          </div>

          <!-- Confirm the code. A raw input styled like the kit's, so the code can be centred in a monospace face. -->
          <form @submit.prevent="confirm2FA" class="space-y-4 max-w-sm">
            <div class="space-y-1.5">
              <label for="two-factor-confirm-code" class="block text-xs font-medium text-slate-700 dark:text-slate-300">
                Enter the 6-digit code from your app
              </label>
              <input
                id="two-factor-confirm-code"
                v-model="confirmForm.code"
                type="text"
                maxlength="6"
                placeholder="123456"
                :aria-invalid="confirmForm.errors.code ? 'true' : undefined"
                :class="[
                  'w-full rounded-xl border px-3 py-2 text-center text-lg font-mono bg-white text-slate-900 placeholder-slate-400 transition-colors dark:bg-slate-950 dark:text-white dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  confirmForm.errors.code ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700',
                ]"
              />
              <p v-if="confirmForm.errors.code" class="text-xs text-rose-600 dark:text-rose-400">{{ confirmForm.errors.code }}</p>
            </div>

            <Button type="submit" block :loading="confirmForm.processing">
              {{ confirmForm.processing ? 'Confirming...' : 'Confirm and turn on 2FA' }}
            </Button>
          </form>
        </div>

        <!-- Step 3: 2FA active and recovery codes -->
        <div v-if="twoFactorEnabled" class="space-y-6">

          <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
              <h4 class="font-semibold text-slate-900 dark:text-white text-sm">Emergency recovery codes</h4>
              <Button variant="secondary" size="sm" @click="generateNewRecoveryCodes">
                <template #icon><UiIcon name="refresh" /></template>
                Regenerate codes
              </Button>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed">
              Store these single-use recovery codes in a secure password manager. If you lose access to your phone, these codes allow emergency login.
            </p>
          </div>

          <div v-if="recoveryCodes && recoveryCodes.length" class="grid grid-cols-2 sm:grid-cols-4 gap-2 bg-slate-50 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200 dark:border-slate-800">
            <div v-for="code in recoveryCodes" :key="code" class="p-2.5 bg-white dark:bg-slate-900 rounded-xl font-mono text-xs text-center text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-800 select-all font-semibold shadow-2xs">
              {{ code }}
            </div>
          </div>

          <!-- Disable -->
          <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
            <Button variant="danger" size="sm" @click="disable2FA">
              Disable two-factor authentication
            </Button>
          </div>
        </div>

      </Card>
    </div>
  </MembersLayout>
</template>
