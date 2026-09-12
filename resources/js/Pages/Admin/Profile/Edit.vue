<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  user: Object,
  status: String,
});

const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const updateProfile = () => {
  profileForm.put(route('profile.update'));
};

const updatePassword = () => {
  passwordForm.put(route('profile.password.update'), {
    onSuccess: () => passwordForm.reset(),
  });
};
</script>

<template>
  <AdminLayout title="Settings" active-tab="profile">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">Profile & Security Settings</h2>
          <p class="text-xs text-slate-500 mt-1">Manage your administrator account details, password, and two-factor authentication.</p>
        </div>
        <Link :href="route('admin.profile.two-factor')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all text-center flex items-center gap-1.5 justify-center">
          <span>🔐 2FA Settings</span>
        </Link>
      </div>

      <!-- Status Notification -->
      <div v-if="status" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-semibold">
        {{ status }}
      </div>

      <!-- Profile Information Card -->
      <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6">
        <div>
          <h3 class="text-base font-bold text-slate-900">Profile Information</h3>
          <p class="text-xs text-slate-500 mt-0.5">Update your account's name and email address.</p>
        </div>

        <form @submit.prevent="updateProfile" class="space-y-4 max-w-lg">
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
              Name
            </label>
            <input
              v-model="profileForm.name"
              type="text"
              required
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"
            />
            <div v-if="profileForm.errors.name" class="text-xs text-rose-600 mt-1">{{ profileForm.errors.name }}</div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
              Email Address
            </label>
            <input
              v-model="profileForm.email"
              type="email"
              required
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"
            />
            <div v-if="profileForm.errors.email" class="text-xs text-rose-600 mt-1">{{ profileForm.errors.email }}</div>
          </div>

          <button
            type="submit"
            :disabled="profileForm.processing"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-indigo-600/20"
          >
            {{ profileForm.processing ? 'Saving...' : 'Save Profile Changes' }}
          </button>
        </form>
      </div>

      <!-- Password Change Card -->
      <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6">
        <div>
          <h3 class="text-base font-bold text-slate-900">Update Password</h3>
          <p class="text-xs text-slate-500 mt-0.5">Ensure your account is using a long, random password to stay secure.</p>
        </div>

        <form @submit.prevent="updatePassword" class="space-y-4 max-w-lg">
          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
              Current Password
            </label>
            <input
              v-model="passwordForm.current_password"
              type="password"
              required
              placeholder="••••••••"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"
            />
            <div v-if="passwordForm.errors.current_password" class="text-xs text-rose-600 mt-1">{{ passwordForm.errors.current_password }}</div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
              New Password
            </label>
            <input
              v-model="passwordForm.password"
              type="password"
              required
              placeholder="••••••••"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"
            />
            <div v-if="passwordForm.errors.password" class="text-xs text-rose-600 mt-1">{{ passwordForm.errors.password }}</div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
              Confirm New Password
            </label>
            <input
              v-model="passwordForm.password_confirmation"
              type="password"
              required
              placeholder="••••••••"
              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <button
            type="submit"
            :disabled="passwordForm.processing"
            class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-emerald-600/20"
          >
            {{ passwordForm.processing ? 'Updating Password...' : 'Update Password' }}
          </button>
        </form>
      </div>

    </div>

  </AdminLayout>
</template>
