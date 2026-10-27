<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';

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
  <MembersLayout title="Profile and security" :club="club" :member-role="memberRole" active-tab="profile">

    <div class="space-y-6 max-w-4xl">

      <!-- Top Header & Member Badge -->
      <Card class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ club.name }} membership profile</h2>
            <Badge>#{{ memberNumber }}</Badge>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Manage your roster information, emergency details, and dietary notes for {{ club.name }}.</p>
        </div>

        <Button variant="secondary" size="sm" :href="route('profile.edit')" class="self-start sm:self-auto">
          <UiIcon name="settings" /> Global account and 2FA
        </Button>
      </Card>

      <!-- Section 1: Member Identity & Club Preferences -->
      <Card class="space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
          <div>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Member identity and preferences</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Your profile information and preferences for this club.</p>
          </div>
          <Badge class="capitalize">{{ memberRole }}</Badge>
        </div>

        <form @submit.prevent="submitProfile" class="space-y-6 max-w-xl">

          <!-- Avatar Preview & Upload Controls -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6 p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
            <div class="relative group">
              <div v-if="avatarPreview" class="w-20 h-20 rounded-full overflow-hidden border-2 border-white dark:border-slate-700 shadow-sm">
                <img :src="avatarPreview" alt="Profile photo" class="w-full h-full object-cover" />
              </div>
              <div v-else class="w-20 h-20 rounded-full bg-blue-600 text-white font-semibold text-2xl flex items-center justify-center border-2 border-white dark:border-slate-700 shadow-sm">
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
                <Button variant="secondary" size="sm" @click="$refs.fileInput.click()">
                  <UiIcon name="upload" /> Upload photo
                </Button>
                <Button v-if="avatarPreview" variant="ghost" size="sm" @click="removePhoto">
                  Remove photo
                </Button>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Upload a headshot photo (JPG, PNG, GIF, or WebP up to 4MB).</p>
            </div>
          </div>

          <!-- Basic Info Inputs -->
          <div class="space-y-4">
            <Input v-model="profileForm.name" label="Full name" required />
            <Input v-model="profileForm.email" type="email" label="Email address" required />
          </div>

          <!-- Club Specific Details (Phone, Emergency Contact, Dietary) -->
          <div class="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-4">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">
              {{ club.name }} roster and event details
            </h3>

            <Input v-model="profileForm.phone" label="Contact phone number" placeholder="+44 7700 900123" />
            <Input v-model="profileForm.emergency_contact" label="Emergency contact (name and phone)" placeholder="Sarah Kenyon (+44 7700 900999)" />
            <Textarea
              v-model="profileForm.dietary_notes"
              :rows="3"
              label="Dietary requirements and allergies (for dining RSVPs)"
              placeholder="e.g. vegetarian, gluten-free, peanut allergy..."
            />
          </div>

          <div class="pt-2">
            <Button type="submit" :loading="profileForm.processing">
              {{ profileForm.processing ? 'Saving...' : 'Save membership profile' }}
            </Button>
          </div>
        </form>
      </Card>

      <!-- Section 2: Read-Only Masonic Progress & Key Dates -->
      <Card class="space-y-4">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-3 flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <UiIcon name="award" :size="20" class="text-slate-500 dark:text-slate-400 shrink-0" />
            <div>
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Masonic progress and key dates</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Official lodge advancement records. Managed and updated by the lodge Secretary.</p>
            </div>
          </div>
          <Badge>
            <UiIcon name="lock" :size="12" /> Read-only record
          </Badge>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Initiated</span>
            <span class="font-semibold text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_initiation || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Passed</span>
            <span class="font-semibold text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_passing || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Raised</span>
            <span class="font-semibold text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_raising || '—' }}</span>
          </div>
          <div class="p-3.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200/80 dark:border-slate-800/80 rounded-xl space-y-1">
            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider block">Joined lodge</span>
            <span class="font-semibold text-slate-900 dark:text-white block text-sm">{{ memberProfile.date_of_joining || '—' }}</span>
          </div>
        </div>
      </Card>

      <!-- Section 3: Global Account & Security Banner -->
      <Card>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <UiIcon name="key" :size="20" class="text-slate-500 dark:text-slate-400 shrink-0" />
              <h3 class="text-base font-semibold text-slate-900 dark:text-white">Global account and password security</h3>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-xl">
              Your login password, 2FA credentials, and global profile identity apply across all joined clubs.
            </p>
          </div>

          <Button variant="secondary" :href="route('profile.edit')" class="shrink-0">
            Manage security and 2FA
          </Button>
        </div>
      </Card>

    </div>

  </MembersLayout>
</template>
