<script setup>
import { ref } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';

const props = defineProps({
  club: { type: Object, required: true },
  user: { type: Object, default: () => null },
  memberRole: { type: String, default: 'member' },
  memberNumber: { type: String, default: 'MEM-1001' },
  memberProfile: { type: Object, default: () => ({}) },
});

const avatarPreview = ref(props.user?.avatar_url || null);
const fileInput = ref(null);

const profileForm = useForm({
  name: props.user?.name || '',
  email: props.user?.email || '',
  phone: props.memberProfile?.phone || '',
  emergency_contact: props.memberProfile?.emergency_contact || '',
  dietary_notes: props.memberProfile?.dietary_notes || '',
  avatar_url: props.user?.avatar_url || '',
  avatar: null,
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

const submitProfile = () => {
  if (profileForm.avatar) {
    router.post(route('member.profile.update', { slug: props.club.slug }), {
      _method: 'post',
      name: profileForm.name,
      email: profileForm.email,
      phone: profileForm.phone,
      emergency_contact: profileForm.emergency_contact,
      dietary_notes: profileForm.dietary_notes,
      avatar: profileForm.avatar,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        profileForm.avatar = null;
      }
    });
  } else {
    profileForm.post(route('member.profile.update', { slug: props.club.slug }), {
      preserveScroll: true,
    });
  }
};
</script>

<template>
  <MembersLayout title="Profile & Security" :club="club" :member-role="memberRole" active-tab="profile">
    
    <div class="space-y-6 max-w-4xl">
      
      <!-- Top Header & Member Badge -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ club.name }} Membership Profile</h2>
            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
              #{{ memberNumber }}
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage your roster information, emergency details, and dietary notes for {{ club.name }}.</p>
        </div>

        <Link :href="route('profile.edit')" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 transition-all flex items-center gap-1.5 justify-center self-start sm:self-auto">
          <span>⚙️ Global Account & 2FA</span>
        </Link>
      </div>

      <!-- Section 1: Member Identity & Club Preferences -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
          <div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Member Identity & Preferences</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Your profile information and preferences for this club.</p>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
            {{ memberRole }}
          </span>
        </div>

        <form @submit.prevent="submitProfile" class="space-y-6 max-w-xl">
          
          <!-- Avatar Preview & Upload Controls -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/50 border border-emerald-100 dark:border-emerald-900/40">
            <div class="relative group">
              <div v-if="avatarPreview" class="w-20 h-20 rounded-full overflow-hidden border-2 border-emerald-500 shadow-md">
                <img :src="avatarPreview" alt="Profile Photo" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-20 h-20 rounded-full bg-gradient-to-tr from-emerald-600 to-blue-500 text-white font-black text-2xl flex items-center justify-center border-2 border-emerald-500 shadow-md">
                {{ profileForm.name ? profileForm.name.substring(0, 2).toUpperCase() : 'MP' }}
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
                  class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition-all shadow-sm flex items-center gap-1.5"
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
                  class="px-3 py-2 bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all"
                >
                  Remove Photo
                </button>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Upload a headshot photo (JPG, PNG, GIF, or WebP up to 4MB).</p>
            </div>
          </div>

          <!-- Basic Info Inputs -->
          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Full Name</label>
              <input
                v-model="profileForm.name"
                type="text"
                required
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Email Address</label>
              <input
                v-model="profileForm.email"
                type="email"
                required
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
              />
            </div>
          </div>

          <!-- Club Specific Details (Phone, Emergency Contact, Dietary) -->
          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider text-emerald-800">
              {{ club.name }} Roster & Event Details
            </h4>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Contact Phone Number</label>
              <input
                v-model="profileForm.phone"
                type="text"
                placeholder="+44 7700 900123"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Emergency Contact (Name & Phone)</label>
              <input
                v-model="profileForm.emergency_contact"
                type="text"
                placeholder="Sarah Kenyon (+44 7700 900999)"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
              />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">Dietary Requirements & Allergies (For Dining RSVPs)</label>
              <textarea
                v-model="profileForm.dietary_notes"
                rows="3"
                placeholder="e.g. Vegetarian, Gluten-Free, Peanut Allergy..."
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 outline-none"
              ></textarea>
            </div>
          </div>

          <div class="pt-2">
            <button
              type="submit"
              :disabled="profileForm.processing"
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-md shadow-emerald-600/20 transition-all"
            >
              {{ profileForm.processing ? 'Saving...' : 'Save Membership Profile' }}
            </button>
          </div>
        </form>
      </div>

      <!-- Section 2: Read-Only Masonic Progress & Key Dates -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-4">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-lg">🏛️</span>
            <div>
              <h3 class="text-base font-bold text-slate-900 dark:text-white">Masonic Progress &amp; Key Dates</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Official lodge advancement records. Managed and updated by the Lodge Secretary.</p>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800">
            🔒 Read-Only Record
          </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Initiated</span>
            <span class="font-black text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_initiation || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Passed</span>
            <span class="font-black text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_passing || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Raised</span>
            <span class="font-black text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_raising || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Joined Lodge</span>
            <span class="font-black text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_joining || '—' }}</span>
          </div>
        </div>
      </div>

      <!-- Section 2: Global Account & Security Banner -->
      <div class="bg-gradient-to-r from-slate-900 to-blue-950 rounded-2xl p-6 text-white shadow-lg space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl">🔐</span>
              <h3 class="text-base font-bold">Global Account & Password Security</h3>
            </div>
            <p class="text-xs text-slate-300 mt-1 max-w-xl">
              Your login password, 2FA credentials, and global profile identity apply across all joined clubs.
            </p>
          </div>

          <Link
            :href="route('profile.edit')"
            class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/30 transition-all text-center shrink-0"
          >
            Manage Security & 2FA &rarr;
          </Link>
        </div>
      </div>

    </div>

  </MembersLayout>
</template>
