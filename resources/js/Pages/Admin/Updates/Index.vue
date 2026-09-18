<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContentPickerModal from '@/Components/ContentPickerModal.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';

const props = defineProps({
  club: Object,
  updates: Array,
  counts: Object,
  pickerMeetings: Array,
  pickerEvents: Array,
  pickerNews: Array,
});

const activeTab = ref('all'); // 'all', 'draft', 'approved', 'sent'
const activeCategoryFilter = ref('all');

const showCreateDrawer = ref(false);
const showContentPicker = ref(false);
const showMediaModal = ref(false);
const mediaTarget = ref(null); // 'cover' or 'attachment'
const mediaDefaultFolder = ref('news');
const isEditing = ref(false);

const coverImagePreview = ref('');
const coverImageFile = ref(null);

const documentFileInput = ref(null);
const pendingUploadedFiles = ref([]);

const form = useForm({
  id: null,
  title: '',
  category: 'summons',
  summary: '',
  cover_image_url: '',
  cover_image_file: null,
  attachments: [],
  attachment_files: [],
  status: 'approved', // default status when manually creating
  is_important: false,
  clean_text: false,
});

const openCreateModal = () => {
  isEditing.value = false;
  form.reset();
  form.id = null;
  form.status = 'approved';
  form.category = 'summons';
  coverImagePreview.value = '';
  coverImageFile.value = null;
  pendingUploadedFiles.value = [];
  showCreateDrawer.value = true;
};

const editUpdate = (item) => {
  isEditing.value = true;
  form.id = item.id;
  form.title = item.title;
  form.category = item.category;
  form.summary = item.summary || '';
  form.cover_image_url = item.cover_image_url || '';
  form.cover_image_file = null;
  form.attachments = [...(item.attachments || [])];
  form.attachment_files = [];
  form.status = item.status;
  form.is_important = Boolean(item.is_important);
  coverImagePreview.value = item.cover_image_url || '';
  coverImageFile.value = null;
  pendingUploadedFiles.value = [];
  showCreateDrawer.value = true;
};

const saveUpdate = () => {
  form.post(route('admin.updates.store', { clubSlug: props.club.slug }), {
    forceFormData: true,
    onSuccess: () => {
      showCreateDrawer.value = false;
      form.reset();
      coverImagePreview.value = '';
      coverImageFile.value = null;
      pendingUploadedFiles.value = [];
    },
  });
};

const updateStatus = (item, newStatus) => {
  router.post(route('admin.updates.status.update', { clubSlug: props.club.slug, id: item.id }), {
    status: newStatus,
  });
};

const deleteUpdate = (item) => {
  if (confirm(`Are you sure you want to delete "${item.title}"?`)) {
    router.delete(route('admin.updates.destroy', { clubSlug: props.club.slug, id: item.id }));
  }
};

const triggerDigestDispatch = () => {
  if (confirm(`Compile and send the Weekly Digest newsletter to members now? (${props.counts.approved} approved updates included)`)) {
    router.post(route('admin.updates.dispatch_digest', { clubSlug: props.club.slug }));
  }
};

const cleanTextInForm = () => {
  if (!form.summary) return;
  let text = form.summary;
  text = text.replace(/From:.*?\n/gi, '');
  text = text.replace(/Sent:.*?\n/gi, '');
  text = text.replace(/To:.*?\n/gi, '');
  text = text.replace(/Subject:.*?\n/gi, '');
  text = text.replace(/---------- Forwarded message ---------/gi, '');
  text = text.replace(/-----Original Message-----/gi, '');
  form.summary = text.trim();
};

// Cover Image File Selection
const onCoverFileSelect = (e) => {
  const file = e.target.files?.[0];
  if (file) {
    coverImageFile.value = file;
    form.cover_image_file = file;
    coverImagePreview.value = URL.createObjectURL(file);
  }
};

const removeCoverImage = () => {
  form.cover_image_url = '';
  form.cover_image_file = null;
  coverImageFile.value = null;
  coverImagePreview.value = '';
};

// Media Library Handlers
const openMediaLibrary = (target, folder = 'news') => {
  mediaTarget.value = target;
  mediaDefaultFolder.value = folder;
  showMediaModal.value = true;
};

const onMediaSelect = (mediaItem) => {
  if (mediaTarget.value === 'cover') {
    form.cover_image_url = mediaItem.url;
    coverImagePreview.value = mediaItem.url;
  } else if (mediaTarget.value === 'attachment') {
    form.attachments.push({
      name: mediaItem.file_name || mediaItem.name,
      url: mediaItem.url,
      size: mediaItem.size || 0,
      mime_type: mediaItem.mime_type || 'application/pdf',
    });
  }
};

// Document Attachment File Upload Handlers
const triggerDocumentUpload = () => {
  if (documentFileInput.value) {
    documentFileInput.value.click();
  }
};

const onDocumentFilesSelect = (e) => {
  const files = Array.from(e.target.files || []);
  files.forEach(file => {
    pendingUploadedFiles.value.push(file);
    form.attachments.push({
      name: file.name,
      url: URL.createObjectURL(file),
      size: file.size,
      mime_type: file.type,
      isPendingFile: true,
    });
  });
  form.attachment_files = pendingUploadedFiles.value;
};

const newAttachmentName = ref('');
const newAttachmentUrl = ref('');

const addAttachmentByUrl = () => {
  if (!newAttachmentUrl.value) return;
  form.attachments.push({
    name: newAttachmentName.value || 'Attached Document',
    url: newAttachmentUrl.value,
    size: 0,
    mime_type: 'application/pdf',
  });
  newAttachmentName.value = '';
  newAttachmentUrl.value = '';
};

const removeAttachment = (index) => {
  const item = form.attachments[index];
  if (item && item.isPendingFile) {
    const fileIdx = pendingUploadedFiles.value.findIndex(f => f.name === item.name);
    if (fileIdx > -1) {
      pendingUploadedFiles.value.splice(fileIdx, 1);
      form.attachment_files = pendingUploadedFiles.value;
    }
  }
  form.attachments.splice(index, 1);
};

const filteredUpdates = computed(() => {
  return props.updates.filter(item => {
    const matchesTab = activeTab.value === 'all' || item.status === activeTab.value;
    const matchesCat = activeCategoryFilter.value === 'all' || item.category === activeCategoryFilter.value;
    return matchesTab && matchesCat;
  });
});

const getCategoryBadge = (cat) => {
  switch (cat) {
    case 'summons': return { label: '📜 Visiting Summons', color: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
    case 'provincial': return { label: '🏛️ Provincial News', color: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
    case 'event_notice': return { label: '🎟️ Event Notice', color: 'bg-amber-50 text-amber-700 border-amber-200' };
    case 'charity': return { label: '❤️ Charity Notice', color: 'bg-rose-50 text-rose-700 border-rose-200' };
    default: return { label: '📣 General Notice', color: 'bg-sky-50 text-sky-700 border-sky-200' };
  }
};
</script>

<template>
  <AdminLayout title="Updates & Weekly Digest" :club="club" active-tab="updates">
    <Head :title="`${club.name} - Updates & Weekly Digest`" />

    <div class="max-w-6xl mx-auto space-y-6">
      <CommunicationsTabs :club="club" active-tab="updates" />
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-xl font-bold text-slate-900">Updates & Weekly Digest Hub</h2>
            <span v-if="counts.approved > 0" class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 animate-pulse">
              {{ counts.approved }} Approved Ready
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5">Collect forwarded summonses, provincial bulletins, and notices to automatically compile into weekly newsletters.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="showContentPicker = true"
            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all flex items-center gap-1.5 cursor-pointer"
          >
            🧩 Content Picker
          </button>

          <button
            type="button"
            @click="openCreateModal"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer"
          >
            + Log New Update Entry
          </button>

          <button
            type="button"
            @click="triggerDigestDispatch"
            :disabled="!counts.approved"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center gap-1.5 cursor-pointer"
          >
            🚀 Send Weekly Digest Now ({{ counts.approved }})
          </button>
        </div>
      </div>

      <!-- Inbound Forwarding Info Card -->
      <div class="p-4 bg-gradient-to-r from-indigo-900 to-slate-900 text-white rounded-2xl shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <span class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center text-xl">📨</span>
          <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-200">Email Forwarding Address</h3>
            <p class="text-sm font-mono font-bold text-white mt-0.5">updates-{{ club.slug }}@inbound.360fusionhosting.co.uk</p>
            <p class="text-[11px] text-slate-300 mt-0.5">Forward emails here to automatically log drafts with PDF summonses & flyer attachments.</p>
          </div>
        </div>
        <div class="text-xs text-indigo-200 bg-white/10 px-3 py-1.5 rounded-xl border border-white/10">
          Status: <strong>Forwarded emails saved as Drafts</strong>
        </div>
      </div>

      <!-- Main Filter Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-3 rounded-2xl border border-slate-200/80">
        
        <!-- Status Filter Tabs -->
        <div class="flex items-center gap-1 overflow-x-auto">
          <button
            @click="activeTab = 'all'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer',
              activeTab === 'all' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            All Items ({{ counts.all }})
          </button>
          
          <button
            @click="activeTab = 'draft'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
              activeTab === 'draft' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <span>📄 Drafts (Needs Review)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-amber-100 text-amber-900">{{ counts.draft }}</span>
          </button>

          <button
            @click="activeTab = 'approved'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
              activeTab === 'approved' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <span>✅ Approved (Queued)</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-900">{{ counts.approved }}</span>
          </button>

          <button
            @click="activeTab = 'sent'"
            :class="[
              'px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer flex items-center gap-1.5',
              activeTab === 'sent' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100'
            ]"
          >
            <span>✉️ Sent</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-indigo-100 text-indigo-900">{{ counts.sent }}</span>
          </button>
        </div>

        <!-- Category Dropdown Filter -->
        <select
          v-model="activeCategoryFilter"
          class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-700"
        >
          <option value="all">Filter All Categories</option>
          <option value="summons">📜 Visiting Summonses</option>
          <option value="provincial">🏛️ Provincial News</option>
          <option value="event_notice">🎟️ Event Notices</option>
          <option value="general">📣 General Notices</option>
        </select>
      </div>

      <!-- Item Grid Stack -->
      <div v-if="filteredUpdates.length" class="space-y-3">
        <div
          v-for="item in filteredUpdates"
          :key="item.id"
          class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm hover:shadow-md transition-all flex flex-col md:flex-row md:items-center justify-between gap-4"
        >
          <div class="flex items-start gap-4 min-w-0 flex-1">
            <img
              v-if="item.cover_image_url"
              :src="item.cover_image_url"
              class="w-16 h-16 rounded-xl object-cover border border-slate-200 shrink-0"
            />
            <div class="min-w-0 flex-1 space-y-1">
              <div class="flex items-center gap-2 flex-wrap">
                <span :class="['px-2.5 py-0.5 text-[10px] font-bold rounded-full border', getCategoryBadge(item.category).color]">
                  {{ getCategoryBadge(item.category).label }}
                </span>

                <!-- Status Badge -->
                <span v-if="item.status === 'draft'" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-amber-100 text-amber-800 border border-amber-200">
                  📄 Draft (Review Required)
                </span>
                <span v-else-if="item.status === 'approved'" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  ✅ Approved & Queued
                </span>
                <span v-else-if="item.status === 'sent'" class="px-2.5 py-0.5 text-[10px] font-bold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">
                  ✉️ Sent in Digest
                </span>
              </div>

              <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ item.title }}</h3>
              <p v-if="item.summary" class="text-xs text-slate-500 line-clamp-2 leading-relaxed">{{ item.summary }}</p>

              <!-- Attachments -->
              <div v-if="item.attachments && item.attachments.length" class="flex flex-wrap items-center gap-1.5 pt-1">
                <span class="text-[10px] font-bold text-slate-400">Attachments:</span>
                <a
                  v-for="(att, aIdx) in item.attachments"
                  :key="aIdx"
                  :href="att.url"
                  target="_blank"
                  class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold rounded-md border border-slate-200 transition-all flex items-center gap-1"
                >
                  📄 {{ att.name }}
                </a>
              </div>
            </div>
          </div>

          <!-- Item Action Buttons -->
          <div class="flex items-center gap-2 shrink-0 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
            <!-- Approve Button -->
            <button
              v-if="item.status === 'draft'"
              type="button"
              @click="updateStatus(item, 'approved')"
              class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all cursor-pointer"
            >
              ✅ Approve & Queue
            </button>

            <!-- Unapprove Back to Draft -->
            <button
              v-if="item.status === 'approved'"
              type="button"
              @click="updateStatus(item, 'draft')"
              class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold rounded-xl border border-amber-200 transition-all cursor-pointer"
            >
              ↩️ Revert to Draft
            </button>

            <!-- Edit Button -->
            <button
              type="button"
              @click="editUpdate(item)"
              class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all cursor-pointer"
            >
              ✏️ Edit
            </button>

            <!-- Delete Button -->
            <button
              type="button"
              @click="deleteUpdate(item)"
              class="p-1.5 text-slate-400 hover:text-rose-600 font-bold transition-all cursor-pointer"
              title="Delete"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>

      <div v-else class="bg-white rounded-2xl p-12 text-center border border-slate-200/80 space-y-3">
        <span class="text-4xl">📭</span>
        <h3 class="text-sm font-bold text-slate-800">No Update Entries Found</h3>
        <p class="text-xs text-slate-500">Log forwarded summonses, provincial bulletins, or news items to get started.</p>
        <button
          type="button"
          @click="openCreateModal"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all inline-block cursor-pointer"
        >
          + Log First Update Entry
        </button>
      </div>

    </div>

    <!-- Create / Edit Drawer Modal -->
    <div v-if="showCreateDrawer" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-slate-200 flex items-center justify-between bg-slate-50">
          <h3 class="text-base font-bold text-slate-900">{{ isEditing ? 'Edit Update Entry' : 'Log New Update Entry' }}</h3>
          <button type="button" @click="showCreateDrawer = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer">✕</button>
        </div>

        <form @submit.prevent="saveUpdate" class="p-6 overflow-y-auto space-y-4 text-xs">
          <div>
            <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Title / Subject *</label>
            <input v-model="form.title" type="text" required placeholder="Visiting Summons - Lodge of Fidelity No. 452" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-900" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Category *</label>
              <select v-model="form.category" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold">
                <option value="summons">📜 Visiting Summons</option>
                <option value="provincial">🏛️ Provincial News</option>
                <option value="event_notice">🎟️ Event Notice</option>
                <option value="general">📣 General Announcement</option>
                <option value="charity">❤️ Charity Notice</option>
              </select>
            </div>

            <div>
              <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Approval Status *</label>
              <select v-model="form.status" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl font-bold">
                <option value="draft">📄 Draft (Review Required)</option>
                <option value="approved">✅ Approved & Queued for Mailer</option>
                <option value="sent">✉️ Sent</option>
              </select>
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block font-bold text-slate-700 uppercase tracking-wider">Summary / Body Text</label>
              <button
                type="button"
                @click="cleanTextInForm"
                class="px-2.5 py-0.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-lg border border-indigo-200 transition-all cursor-pointer"
                title="Strip email headers and forwarded signatures"
              >
                🧹 Clean Email Text
              </button>
            </div>
            <textarea v-model="form.summary" rows="5" placeholder="Paste or clean forwarded email body here..." class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-xs"></textarea>
          </div>

          <!-- Cover Image Upload & URL Selection -->
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700 uppercase tracking-wider">🖼️ Cover Photo / Banner</label>
              <div class="flex items-center gap-2">
                <button
                  v-if="coverImagePreview || form.cover_image_url"
                  type="button"
                  @click="removeCoverImage"
                  class="px-2 py-0.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[10px] font-bold rounded-lg border border-rose-200 transition-all cursor-pointer"
                >
                  🗑️ Remove
                </button>
                <button
                  type="button"
                  @click="openMediaLibrary('cover', 'updates')"
                  class="px-2.5 py-0.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-[10px] font-bold rounded-lg border border-sky-200 transition-all cursor-pointer flex items-center gap-1"
                >
                  📁 Choose from Media Library
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Image URL</label>
                <input v-model="form.cover_image_url" type="text" placeholder="https://..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono" />
              </div>

              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Or Upload Image File</label>
                <input
                  type="file"
                  accept="image/*"
                  @change="onCoverFileSelect"
                  class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 cursor-pointer"
                />
              </div>
            </div>

            <div v-if="coverImagePreview || form.cover_image_url" class="relative max-w-xs rounded-xl overflow-hidden border border-slate-200 mt-2">
              <img :src="coverImagePreview || form.cover_image_url" class="w-full h-28 object-cover" />
              <button
                type="button"
                @click="removeCoverImage"
                class="absolute top-1.5 right-1.5 px-2 py-0.5 bg-rose-600/90 text-white font-bold text-[10px] rounded-md shadow-md cursor-pointer"
              >
                ✕ Remove
              </button>
            </div>
          </div>

          <!-- Downloadable Attachments & Document Uploads -->
          <div class="space-y-3 p-4 bg-slate-50 rounded-xl border border-slate-200">
            <div class="flex items-center justify-between">
              <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider">📎 Downloadable Attachments & Documents</label>
                <p class="text-[10px] text-slate-500">Upload PDF summonses, agendas, spreadsheets, or flyers.</p>
              </div>
              <div class="flex items-center gap-2">
                <button
                  type="button"
                  @click="openMediaLibrary('attachment', 'updates')"
                  class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 text-[10px] font-bold rounded-lg border border-amber-200 transition-all cursor-pointer flex items-center gap-1"
                >
                  📁 Select from Media Library
                </button>
                <button
                  type="button"
                  @click="triggerDocumentUpload"
                  class="px-2.5 py-1 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold rounded-lg shadow-xs transition-all cursor-pointer flex items-center gap-1"
                >
                  + Upload Document Files
                </button>
              </div>
              <input
                ref="documentFileInput"
                type="file"
                multiple
                accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip"
                class="hidden"
                @change="onDocumentFilesSelect"
              />
            </div>

            <!-- Existing & Added Attachments List -->
            <div v-if="form.attachments && form.attachments.length" class="space-y-1.5">
              <div
                v-for="(att, idx) in form.attachments"
                :key="idx"
                class="flex items-center justify-between bg-white p-2.5 rounded-xl border border-slate-200 text-xs shadow-xs"
              >
                <div class="flex items-center gap-2 truncate pr-2">
                  <span class="text-base">📄</span>
                  <span class="font-bold text-slate-800 truncate">{{ att.name }}</span>
                  <span v-if="att.isPendingFile" class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-indigo-100 text-indigo-800">New File Upload</span>
                </div>
                <button
                  type="button"
                  @click="removeAttachment(idx)"
                  class="text-rose-600 hover:text-rose-800 font-bold px-2 py-0.5 text-xs cursor-pointer"
                  title="Remove Attachment"
                >
                  ✕ Remove
                </button>
              </div>
            </div>

            <!-- Manual Attachment Link Fallback -->
            <div class="pt-2 border-t border-slate-200/80">
              <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Or Add Document by Web Link:</span>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <input v-model="newAttachmentName" type="text" placeholder="Document Label (e.g. Summons PDF)" class="p-2 bg-white border border-slate-300 rounded-lg text-xs" />
                <div class="flex items-center gap-2">
                  <input v-model="newAttachmentUrl" type="text" placeholder="URL (https://...)" class="flex-1 p-2 bg-white border border-slate-300 rounded-lg text-xs font-mono" />
                  <button type="button" @click="addAttachmentByUrl" class="px-3 py-2 bg-slate-700 text-white font-bold rounded-lg text-xs cursor-pointer">+ Link</button>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2">
            <button type="button" @click="showCreateDrawer = false" class="px-4 py-2 bg-slate-100 text-slate-700 font-bold rounded-xl cursor-pointer">Cancel</button>
            <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm cursor-pointer">Save Update Item</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Interactive Content Picker Modal -->
    <ContentPickerModal
      :show="showContentPicker"
      :updates="updates.filter(u => u.status === 'approved')"
      :meetings="pickerMeetings"
      :events="pickerEvents"
      :news="pickerNews"
      @close="showContentPicker = false"
      @select-content="showContentPicker = false"
    />

    <!-- Spatie Media Library Selector Modal -->
    <MediaLibraryModal
      :show="showMediaModal"
      :club-slug="club.slug"
      :default-folder="mediaDefaultFolder"
      @close="showMediaModal = false"
      @select="onMediaSelect"
    />
  </AdminLayout>
</template>
