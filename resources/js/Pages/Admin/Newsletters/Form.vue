<script setup>
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
  club: Object,
  newsletter: Object,
  types: Array,
});

const existingAttachments = ref([...(props.newsletter.attachments || [])]);
const newFiles = ref([]);
const fileInput = ref(null);

const form = useForm({
  id: props.newsletter.id || null,
  newsletter_type_id: props.newsletter.newsletter_type_id || props.types?.[0]?.id || null,
  subject: props.newsletter.subject || '',
  content: props.newsletter.content || '',
  target_roles: props.newsletter.target_roles || ['member', 'admin', 'coach'],
  status: props.newsletter.status || 'draft',
  existing_attachments: existingAttachments.value,
  new_attachments: [],
});

const availableRoles = [
  { id: 'member', label: 'Members / Brethren' },
  { id: 'coach', label: 'Coaches & Officers' },
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

const triggerFileInput = () => {
  if (fileInput.value) {
    fileInput.value.click();
  }
};

const onFileSelect = (e) => {
  const selected = Array.from(e.target.files || []);
  selected.forEach(file => {
    newFiles.value.push(file);
  });
  form.new_attachments = newFiles.value;
};

const removeNewFile = (index) => {
  newFiles.value.splice(index, 1);
  form.new_attachments = newFiles.value;
};

const removeExistingAttachment = (index) => {
  existingAttachments.value.splice(index, 1);
  form.existing_attachments = existingAttachments.value;
};

const getFileIcon = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return '📄';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg')) return '🖼️';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return '📊';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return '📝';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return '📦';
  return '📎';
};

const formatBytes = (bytes) => {
  if (!bytes) return '';
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  return (bytes / 1024).toFixed(1) + ' KB';
};

const submitForm = (targetStatus) => {
  form.status = targetStatus;
  form.existing_attachments = existingAttachments.value;
  form.new_attachments = newFiles.value;
  
  form.post(route('admin.newsletters.store', { clubSlug: props.club.slug }), {
    forceFormData: true,
  });
};

const saveDraft = () => {
  submitForm('draft');
};

const sendBroadcast = () => {
  if (confirm('Send this email broadcast to all targeted members and external subscribers now?')) {
    submitForm('sent');
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
          <p class="text-xs text-slate-500 mt-0.5">Target specific channels, member roles, visiting subscribers, and attach downloadable files.</p>
        </div>
        <Link :href="route('admin.newsletters.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Newsletters
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="col-span-1">
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Newsletter Channel *</label>
            <select v-model="form.newsletter_type_id" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 focus:outline-none focus:border-indigo-500">
              <option v-for="t in types" :key="t.id" :value="t.id">
                {{ t.icon }} {{ t.name }}
              </option>
            </select>
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Email Subject Line *</label>
            <input v-model="form.subject" type="text" required placeholder="Summer Regatta Schedule & Summons Circular" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-indigo-500" />
          </div>
        </div>

        <!-- Target Roles Selector -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Target Internal Member Roles</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div 
              v-for="role in availableRoles" 
              :key="role.id"
              @click="toggleRole(role.id)"
              :class="[
                'p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all flex items-center justify-between',
                form.target_roles.includes(role.id)
                  ? 'bg-indigo-50 border-indigo-300 text-indigo-900 shadow-sm'
                  : 'bg-slate-50 border-slate-200 text-slate-500'
              ]"
            >
              <span>{{ role.label }}</span>
              <span v-if="form.target_roles.includes(role.id)" class="text-indigo-600">✓</span>
            </div>
          </div>
        </div>

        <!-- Newsletter Content -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Newsletter Content (WYSIWYG)</label>
          <RichTextEditor v-model="form.content" placeholder="Write rich email newsletter content here..." />
        </div>

        <!-- Attachments Section -->
        <div class="space-y-3 pt-4 border-t border-slate-100">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">📎 Attachments & Downloads</label>
              <p class="text-[11px] text-slate-500">Attach Summons PDFs, meeting agendas, financial reports, or image circulars for recipients.</p>
            </div>
            <button
              type="button"
              @click="triggerFileInput"
              class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all flex items-center gap-1 cursor-pointer"
            >
              + Add Attachments
            </button>
            <input
              ref="fileInput"
              type="file"
              multiple
              class="hidden"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.zip"
              @change="onFileSelect"
            />
          </div>

          <!-- Attachments List Grid -->
          <div v-if="existingAttachments.length || newFiles.length" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            
            <!-- Existing Saved Attachments -->
            <div
              v-for="(att, idx) in existingAttachments"
              :key="'existing-' + idx"
              class="flex items-center justify-between bg-slate-50 p-3 rounded-xl border border-slate-200/80 text-xs"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span class="text-lg">{{ getFileIcon(att.mime_type || att.name) }}</span>
                <div class="truncate">
                  <a :href="att.url" target="_blank" class="font-bold text-slate-900 hover:text-indigo-600 truncate block">
                    {{ att.name }}
                  </a>
                  <span class="text-[10px] text-slate-500">{{ att.size || 'Saved File' }}</span>
                </div>
              </div>
              <button
                type="button"
                @click="removeExistingAttachment(idx)"
                class="text-slate-400 hover:text-rose-600 font-bold p-1 rounded transition-colors cursor-pointer"
                title="Remove attachment"
              >
                ✕
              </button>
            </div>

            <!-- Newly Selected Files -->
            <div
              v-for="(file, idx) in newFiles"
              :key="'new-' + idx"
              class="flex items-center justify-between bg-indigo-50/50 p-3 rounded-xl border border-indigo-200/80 text-xs"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span class="text-lg">{{ getFileIcon(file.name) }}</span>
                <div class="truncate">
                  <span class="font-bold text-indigo-900 truncate block">{{ file.name }}</span>
                  <span class="text-[10px] text-indigo-600 font-semibold">{{ formatBytes(file.size) }} (Pending upload)</span>
                </div>
              </div>
              <button
                type="button"
                @click="removeNewFile(idx)"
                class="text-indigo-400 hover:text-rose-600 font-bold p-1 rounded transition-colors cursor-pointer"
                title="Remove file"
              >
                ✕
              </button>
            </div>

          </div>

          <div v-else @click="triggerFileInput" class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-300 transition-colors cursor-pointer bg-slate-50/50">
            <span class="text-2xl block mb-1">📁</span>
            <span class="text-xs font-bold text-slate-700 block">Click to upload attachments</span>
            <span class="text-[11px] text-slate-400">PDFs, Word Documents, Excel sheets, Images, or Zip files (up to 10MB per file)</span>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
          <button type="button" @click="saveDraft" :disabled="form.processing" class="flex-1 py-3 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs border border-slate-300 transition-all cursor-pointer">
            💾 Save as Draft
          </button>
          <button type="button" @click="sendBroadcast" :disabled="form.processing" class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-xs shadow-md shadow-indigo-600/20 transition-all cursor-pointer">
            🚀 Send Email Broadcast Now
          </button>
        </div>

      </form>

    </div>

  </AdminLayout>
</template>
