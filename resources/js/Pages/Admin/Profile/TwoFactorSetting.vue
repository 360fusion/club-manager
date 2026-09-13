<script setup>
import { ref } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
  twoFactorEnabled: Boolean,
  twoFactorPending: Boolean,
  qrCodeSvg: String,
  secretKey: String,
  recoveryCodes: Array,
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
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans p-6 md:p-12">
    <div class="max-w-4xl mx-auto space-y-8">
      
      <!-- Top Navigation & Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-slate-800 pb-6">
        <div>
          <span class="px-3 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 text-xs font-semibold rounded-full uppercase tracking-wider">
            Security & Authentication
          </span>
          <h1 class="text-3xl font-extrabold tracking-tight mt-2 bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
            Two-Factor Authentication (2FA)
          </h1>
          <p class="text-slate-400 text-sm mt-1">
            Secure your admin account using Google Authenticator, Authy, or 1Password.
          </p>
        </div>

        <Link :href="route('home')" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 text-sm font-medium rounded-xl transition-all self-start md:self-auto">
          &larr; Back to Dashboard
        </Link>
      </div>

      <!-- Main Status Card -->
      <div class="bg-slate-900/60 backdrop-blur-xl border border-slate-800 rounded-2xl p-6 md:p-8 space-y-6">
        
        <!-- Status Header -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-3xl">🛡️</span>
            <div>
              <h2 class="text-lg font-bold text-white">2FA Security Status</h2>
              <p class="text-xs text-slate-400">
                {{ twoFactorEnabled ? 'Two-Factor Authentication is active and protecting your account.' : (twoFactorPending ? '2FA setup initiated. Confirm your authenticator code below.' : 'Two-Factor Authentication is currently disabled.') }}
              </p>
            </div>
          </div>

          <span
            :class="[
              'px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wider',
              twoFactorEnabled ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : (twoFactorPending ? 'bg-amber-500/10 text-amber-400 border-amber-500/20' : 'bg-slate-800 text-slate-400 border-slate-700')
            ]"
          >
            {{ twoFactorEnabled ? 'Active' : (twoFactorPending ? 'Pending Confirmation' : 'Disabled') }}
          </span>
        </div>

        <!-- Setup Step 1: Enable 2FA Button -->
        <div v-if="!twoFactorEnabled && !twoFactorPending" class="pt-4 border-t border-slate-800">
          <button
            @click="enable2FA"
            class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-cyan-600 hover:from-indigo-500 hover:to-cyan-500 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-indigo-600/20"
          >
            Enable Two-Factor Authentication &rarr;
          </button>
        </div>

        <!-- Setup Step 2: Pending Confirmation (QR Code Display) -->
        <div v-if="twoFactorPending" class="pt-4 border-t border-slate-800 space-y-6">
          <div class="bg-slate-950 p-6 rounded-2xl border border-slate-800 flex flex-col md:flex-row items-center gap-6">
            <div v-if="qrCodeSvg" v-html="qrCodeSvg" class="p-3 bg-white rounded-xl shadow-lg"></div>

            <div class="space-y-3">
              <h3 class="font-bold text-white text-sm">Scan with your Authenticator App</h3>
              <p class="text-xs text-slate-400">
                Scan the QR code using Google Authenticator, Authy, 1Password, or Microsoft Authenticator.
              </p>
              <div v-if="secretKey" class="text-xs font-mono bg-slate-900 px-3 py-2 rounded-lg border border-slate-800 text-slate-300">
                Secret Key: <strong class="text-indigo-400 select-all">{{ secretKey }}</strong>
              </div>
            </div>
          </div>

          <!-- Confirm TOTP Form -->
          <form @submit.prevent="confirm2FA" class="space-y-4 max-w-sm">
            <div>
              <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                Enter 6-Digit Code to Confirm Setup
              </label>
              <input
                v-model="confirmForm.code"
                type="text"
                maxlength="6"
                placeholder="123456"
                class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-center text-lg font-mono text-white focus:outline-none focus:border-indigo-500"
              />
            </div>

            <button
              type="submit"
              :disabled="confirmForm.processing"
              class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm transition-all shadow-lg shadow-emerald-600/20"
            >
              {{ confirmForm.processing ? 'Confirming...' : 'Confirm & Activate 2FA' }}
            </button>
          </form>
        </div>

        <!-- Step 3: 2FA Active & Recovery Codes -->
        <div v-if="twoFactorEnabled" class="pt-4 border-t border-slate-800 space-y-6">
          
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h3 class="font-bold text-white text-sm">Emergency Recovery Codes</h3>
              <button
                @click="generateNewRecoveryCodes"
                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg border border-slate-700 transition-all"
              >
                Regenerate Codes
              </button>
            </div>
            <p class="text-xs text-slate-400">
              Store these single-use recovery codes in a secure password manager. If you lose access to your phone, these codes allow emergency login.
            </p>
          </div>

          <div v-if="recoveryCodes.length" class="grid grid-cols-2 md:grid-cols-4 gap-2 bg-slate-950 p-4 rounded-xl border border-slate-800">
            <div v-for="code in recoveryCodes" :key="code" class="p-2 bg-slate-900 rounded font-mono text-xs text-center text-slate-300 border border-slate-800/60 select-all">
              {{ code }}
            </div>
          </div>

          <!-- Disable Button -->
          <div class="pt-4 border-t border-slate-800 flex justify-end">
            <button
              @click="disable2FA"
              class="px-5 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-bold rounded-xl transition-all"
            >
              Disable Two-Factor Authentication
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
