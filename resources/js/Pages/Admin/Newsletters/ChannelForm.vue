<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  club: Object,
  type: Object,
});

const form = useForm({
  id: props.type.id || null,
  name: props.type.name || '',
  description: props.type.description || '',
  color: props.type.color || '#4f46e5',
  icon: props.type.icon || '✉️',
  is_external_subscribable: props.type.is_external_subscribable ?? true,
  require_approval: props.type.require_approval ?? false,
  is_mandatory: props.type.is_mandatory ?? false,
  require_home_club_info: props.type.require_home_club_info ?? true,
  default_roles: props.type.default_roles || ['member'],
  sender_name: props.type.sender_name || '',
  sender_email: props.type.sender_email || '',
});

const availableRoles = [
  { id: 'member', label: 'Members / Brethren' },
  { id: 'coach', label: 'Coaches & Officers' },
  { id: 'admin', label: 'Admins & Executive Board' },
  { id: 'treasurer', label: 'Treasurers' },
];

const toggleRole = (roleId) => {
  const index = form.default_roles.indexOf(roleId);
  if (index > -1) {
    form.default_roles.splice(index, 1);
  } else {
    form.default_roles.push(roleId);
  }
};

const saveChannel = () => {
  form.post(route('admin.newsletters.types.store', { clubSlug: props.club.slug }));
};
</script>

<template>
  <AdminLayout :title="`${type.id ? 'Edit' : 'Create'} Newsletter Channel`" :club="club" active-tab="newsletters">
    <Head :title="`${type.id ? 'Edit' : 'Create'} Newsletter Channel`" />

    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <Link
              :href="route('admin.newsletters.types', club.slug)"
              class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-indigo-600 transition-colors"
            >
              ← Back to Channels
            </Link>
          </div>
          <h2 class="text-xl font-bold text-slate-900">
            {{ type.id ? '⚙️ Edit Channel Settings — ' + type.name : '✨ Create Newsletter Channel' }}
          </h2>
          <p class="text-xs text-slate-500 mt-0.5">
            Configure channel identity, visiting brethren access, Secretary approval rules, and delivery settings.
          </p>
        </div>
      </div>

      <!-- Form Container -->
      <form @submit.prevent="saveChannel" class="space-y-6">
        
        <!-- Section 1: Channel Identity & Branding -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
            1. Channel Identity & Branding
          </h3>

          <div class="grid grid-cols-4 gap-4">
            <div class="col-span-1">
              <label class="block text-xs font-bold text-slate-700 mb-1">Channel Icon *</label>
              <input v-model="form.icon" type="text" class="w-full p-2.5 border border-slate-300 rounded-xl text-center text-xl font-bold bg-slate-50" required />
            </div>
            <div class="col-span-3">
              <label class="block text-xs font-bold text-slate-700 mb-1">Channel Name *</label>
              <input v-model="form.name" type="text" placeholder="e.g. Meeting Summonses, General News, Social Bulletins" class="w-full p-2.5 border border-slate-300 rounded-xl font-bold text-slate-900 bg-slate-50" required />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Channel Description</label>
            <textarea v-model="form.description" rows="2" placeholder="Briefly describe the purpose of this channel..." class="w-full p-2.5 border border-slate-300 rounded-xl text-xs bg-slate-50"></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 mb-1">Badge Theme Color</label>
            <div class="flex items-center gap-3">
              <input v-model="form.color" type="color" class="w-12 h-9 rounded-xl border border-slate-300 cursor-pointer" />
              <input v-model="form.color" type="text" class="w-32 p-2 border border-slate-300 rounded-xl font-mono text-xs font-bold bg-slate-50 text-slate-900" />
              <span :style="{ backgroundColor: form.color }" class="px-3 py-1 rounded-lg text-white font-bold text-xs shadow-xs flex items-center gap-1">
                {{ form.icon }} {{ form.name || 'Sample Badge' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Section 2: Visibility & Visiting Brethren Access -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
            2. Visibility & External Subscriptions
          </h3>

          <div class="space-y-3">
            <label class="flex items-start gap-3 cursor-pointer bg-slate-50 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 transition-all">
              <input v-model="form.is_external_subscribable" type="checkbox" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
              <div>
                <span class="font-bold text-slate-900 text-sm">🌐 Allow External Subscriptions (Visiting Brethren / Other Lodges)</span>
                <p class="text-slate-500 text-xs mt-0.5">When enabled, this channel will be listed in the National Directory allowing brethren from other lodges to subscribe.</p>
              </div>
            </label>

            <label v-if="form.is_external_subscribable" class="flex items-start gap-3 cursor-pointer bg-slate-50 p-4 rounded-xl border border-slate-200 hover:border-amber-300 transition-all ml-4">
              <input v-model="form.require_approval" type="checkbox" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
              <div>
                <span class="font-bold text-slate-900 text-sm">⏳ Require Secretary Approval for Visiting Brethren</span>
                <p class="text-slate-500 text-xs mt-0.5">External subscription requests will enter a pending approval queue before receiving broadcasts.</p>
              </div>
            </label>

            <label class="flex items-start gap-3 cursor-pointer bg-slate-50 p-4 rounded-xl border border-slate-200 hover:border-purple-300 transition-all">
              <input v-model="form.is_mandatory" type="checkbox" class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4" />
              <div>
                <span class="font-bold text-slate-900 text-sm">🔒 Mandatory Official Notice (No Member Unsubscribe)</span>
                <p class="text-slate-500 text-xs mt-0.5">Enforces lodge bylaws compliance — active internal members cannot unsubscribe from official notices like Summonses.</p>
              </div>
            </label>
          </div>
        </div>

        <!-- Section 3: Default Target Roles -->
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-4">
          <h3 class="text-sm font-bold uppercase tracking-wider text-slate-400 border-b border-slate-100 pb-2">
            3. Default Recipient Roles
          </h3>

          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div 
              v-for="role in availableRoles" 
              :key="role.id"
              @click="toggleRole(role.id)"
              :class="[
                'p-3.5 rounded-xl border text-xs font-bold cursor-pointer transition-all flex items-center justify-between',
                form.default_roles.includes(role.id)
                  ? 'bg-indigo-50 border-indigo-300 text-indigo-900 shadow-sm'
                  : 'bg-slate-50 border-slate-200 text-slate-500'
              ]"
            >
              <span>{{ role.label }}</span>
              <span v-if="form.default_roles.includes(role.id)" class="text-indigo-600">✓</span>
            </div>
          </div>
        </div>

        <!-- Submit Bar -->
        <div class="flex items-center justify-end gap-3 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
          <Link
            :href="route('admin.newsletters.types', club.slug)"
            class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-all"
          >
            Cancel
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-600/20 transition-all cursor-pointer"
          >
            💾 Save Newsletter Channel
          </button>
        </div>

      </form>

    </div>
  </AdminLayout>
</template>
