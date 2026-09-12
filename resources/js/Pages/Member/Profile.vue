<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import MemberLayout from '@/Layouts/MemberLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  user: { type: Object, default: () => null },
  memberRole: { type: String, default: 'member' },
  memberNumber: { type: String, default: 'MEM-1001' },
});

const profileForm = useForm({
  name: props.user?.name || '',
  email: props.user?.email || '',
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const submitProfile = () => {
  profileForm.put(route('profile.update'), {
    preserveScroll: true,
  });
};

const submitPassword = () => {
  passwordForm.put(route('profile.password.update'), {
    onSuccess: () => passwordForm.reset(),
    preserveScroll: true,
  });
};
</script>

<template>
  <MemberLayout title="Profile & Security" :club="club" :member-role="memberRole" active-tab="profile">
    
    <div class="space-y-6 max-w-4xl">
      
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900">Member Profile Settings</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
              #{{ memberNumber }}
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Manage your account information, update security credentials, and review 2FA authentication status.</p>
        </div>
      </div>

      <!-- Section 1: Profile Information -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Profile Information</h3>
          <p class="text-xs text-slate-500">Update your account's profile name and email address.</p>
        </div>

        <form @submit.prevent="submitProfile" class="space-y-4 max-w-lg">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Full Name</label>
            <input
              v-model="profileForm.name"
              type="text"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
            <input
              v-model="profileForm.email"
              type="email"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
            />
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="profileForm.processing"
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all"
            >
              Save Profile Changes
            </button>
          </div>
        </form>
      </div>

      <!-- Section 2: Update Password -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="border-b border-slate-100 pb-3">
          <h3 class="text-base font-bold text-slate-900">Update Password</h3>
          <p class="text-xs text-slate-500">Ensure your account is using a long, secure password.</p>
        </div>

        <form @submit.prevent="submitPassword" class="space-y-4 max-w-lg">
          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Current Password</label>
            <input
              v-model="passwordForm.current_password"
              type="password"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">New Password</label>
            <input
              v-model="passwordForm.password"
              type="password"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Confirm New Password</label>
            <input
              v-model="passwordForm.password_confirmation"
              type="password"
              required
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none"
            />
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="passwordForm.processing"
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all"
            >
              Update Security Password
            </button>
          </div>
        </form>
      </div>

      <!-- Section 3: Two-Factor Authentication Status -->
      <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
          <div>
            <h3 class="text-base font-bold text-slate-900">Two-Factor Authentication (2FA)</h3>
            <p class="text-xs text-slate-500 mt-0.5">Add additional security to your member account using two-factor authentication.</p>
          </div>

          <span
            :class="[
              'px-3 py-1 rounded-full text-xs font-bold uppercase border tracking-wider',
              user?.two_factor_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'
            ]"
          >
            {{ user?.two_factor_enabled ? 'Enabled' : 'Disabled' }}
          </span>
        </div>

        <div class="flex items-center justify-between pt-2">
          <p class="text-xs text-slate-500">
            When 2FA is enabled, you will be prompted for a secure, random token during authentication.
          </p>
          <Link
            :href="route('admin.profile.two-factor')"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition-all"
          >
            Configure 2FA Settings
          </Link>
        </div>
      </div>

    </div>

  </MemberLayout>
</template>
