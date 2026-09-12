<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
  club: Object,
  newsletter: Object,
});

const form = useForm({
  id: props.newsletter.id || null,
  subject: props.newsletter.subject || '',
  content: props.newsletter.content || '',
  target_roles: props.newsletter.target_roles || ['member', 'admin', 'coach'],
  status: props.newsletter.status || 'draft',
});

const availableRoles = [
  { id: 'member', label: 'Members / Athletes' },
  { id: 'coach', label: 'Coaches & Captains' },
  { id: 'admin', label: 'Admins & Executive Board' },
  { id: 'treasurer', label: 'Treasurers' },
];

const toggleRole = (roleId) => {
  const index = form.target_roles.indexOf(roleId);
  if (index > -1) {
    form.target_roles.splice(index, 1);
  } else {
    form.target_roles.push(roleId);
  }
};

const saveDraft = () => {
  form.status = 'draft';
  form.post(route('admin.newsletters.store', { clubSlug: props.club.slug }));
};

const sendBroadcast = () => {
  if (confirm('Send this email broadcast to all targeted members now?')) {
    form.status = 'sent';
    form.post(route('admin.newsletters.store', { clubSlug: props.club.slug }));
  }
};
</script>

<template>
  <AdminLayout :title="`${newsletter.id ? 'Edit' : 'Compose'} Newsletter`" :club="club" active-tab="newsletters">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">{{ newsletter.id ? 'Edit Newsletter Broadcast' : 'Compose New Email Broadcast' }}</h2>
          <p class="text-xs text-slate-500 mt-0.5">Target specific member roles and send email broadcasts.</p>
        </div>
        <Link :href="route('admin.newsletters.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Newsletters
        </Link>
      </div>

      <!-- Form -->
      <form class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Email Subject Line</label>
          <input v-model="form.subject" type="text" required placeholder="Summer Regatta Schedule & Dinner Invites" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-amber-500" />
        </div>

        <!-- Target Roles Selector -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Target Member Roles</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div 
              v-for="role in availableRoles" 
              :key="role.id"
              @click="toggleRole(role.id)"
              :class="[
                'p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all flex items-center justify-between',
                form.target_roles.includes(role.id)
                  ? 'bg-amber-50 border-amber-300 text-amber-900 shadow-sm'
                  : 'bg-slate-50 border-slate-200 text-slate-500'
              ]"
            >
              <span>{{ role.label }}</span>
              <span v-if="form.target_roles.includes(role.id)" class="text-amber-600">✓</span>
            </div>
          </div>
        </div>

        <!-- Newsletter Content -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Newsletter Content (WYSIWYG)</label>
          <RichTextEditor v-model="form.content" placeholder="Write rich email newsletter content here..." />
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
          <button type="button" @click="saveDraft" :disabled="form.processing" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs border border-slate-300 transition-all">
            💾 Save as Draft
          </button>
          <button type="button" @click="sendBroadcast" :disabled="form.processing" class="flex-1 py-3 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-xs shadow-md shadow-amber-600/20 transition-all">
            🚀 Send Email Broadcast Now
          </button>
        </div>

      </form>

    </div>

  </AdminLayout>
</template>
