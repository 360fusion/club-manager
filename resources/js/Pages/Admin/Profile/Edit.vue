<script setup>
import { ref } from 'vue';
import { useForm, router, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  user: Object,
  status: String,
});

const avatarPreview = ref(props.user?.avatar_url || null);
const fileInput = ref(null);

const profileForm = useForm({
  name: props.user.name,
  email: props.user.email,
  avatar_url: props.user.avatar_url || '',
  avatar: null,
});

const passwordForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const handleFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    profileForm.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
  }
};

const removePhoto = () => {
  profileForm.avatar_url = '';
  profileForm.avatar = null;
  avatarPreview.value = null;
};

const updateProfile = () => {
  if (profileForm.avatar) {
    router.post(route('profile.update'), {
      _method: 'put',
      name: profileForm.name,
      email: profileForm.email,
      avatar: profileForm.avatar,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        profileForm.avatar = null;
      }
    });
  } else {
    profileForm.put(route('profile.update'), {
      preserveScroll: true,
    });
  }
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
          <p class="text-xs text-slate-500 mt-1">Manage your account details, upload your profile photo, password, and two-factor authentication.</p>
        </div>
        <Link :href="route('admin.profile.two-factor')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-600/20 transition-all text-center flex items-center gap-1.5 justify-center">
          <span>🔐 2FA Settings</span>
        </Link>
      </div>

      <!-- Status Notification -->
      <div v-if="status" class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-semibold">
        {{ status }}
      </div>

      <!-- Profile Information & Avatar Card -->
      <div class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6">
        <div>
          <h3 class="text-base font-bold text-slate-900">Profile Photo & Personal Info</h3>
          <p class="text-xs text-slate-500 mt-0.5">Upload a photo of yourself to personalize your account across the club platform.</p>
        </div>

        <form @submit.prevent="updateProfile" class="space-y-6 max-w-xl">
          
          <!-- Avatar Preview & Upload Controls -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-4 rounded-2xl bg-slate-50 border border-slate-200">
            <div class="relative group">
              <div v-if="avatarPreview" class="w-20 h-20 rounded-full overflow-hidden border-2 border-indigo-500 shadow-md">
                <img :src="avatarPreview" alt="Profile Photo" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-20 h-20 rounded-full bg-gradient-to-tr from-indigo-600 to-sky-500 text-white font-black text-2xl flex items-center justify-center border-2 border-indigo-500 shadow-md">
                {{ profileForm.name ? profileForm.name.substring(0, 2).toUpperCase() : 'ME' }}
              </div>
            </div>

            <div class="space-y-2 flex-1">
              <div class="flex items-center gap-3">
                <input 
                  type="file" 
                  ref="fileInput" 
                  @change="handleFileChange" 
                  accept="image/*" 
                  class="hidden" 
                />
                <button 
                  type="button" 
                  @click="$refs.fileInput.click()" 
                  class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  <span>Upload Photo</span>
                </button>
                <button 
                  v-if="avatarPreview"
                  type="button" 
                  @click="removePhoto" 
                  class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all"
                >
                  Remove Photo
                </button>
              </div>
              <p class="text-[11px] text-slate-500">Upload a headshot photo (JPG, PNG, GIF, or WebP up to 4MB).</p>
            </div>
          </div>

          <!-- Name & Email Inputs -->
          <div class="space-y-4">
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
          </div>

          <button
            type="submit"
            :disabled="profileForm.processing"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs transition-all shadow-md shadow-indigo-600/20"
          >
            {{ profileForm.processing ? 'Saving...' : 'Save Profile & Photo' }}
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
