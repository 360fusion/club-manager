<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ContentPickerModal from '@/Components/ContentPickerModal.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import CommunicationsTabs from '@/Components/CommunicationsTabs.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';

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

const updateToDelete = ref(null);
const deleteUpdate = () => {
  router.delete(route('admin.updates.destroy', { clubSlug: props.club.slug, id: updateToDelete.value.id }), {
    onFinish: () => { updateToDelete.value = null; },
  });
};

const confirmDigestOpen = ref(false);
const triggerDigestDispatch = () => {
  router.post(route('admin.updates.dispatch_digest', { clubSlug: props.club.slug }), {}, {
    onFinish: () => { confirmDigestOpen.value = false; },
  });
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

const CATEGORY_LABELS = {
  summons: 'Visiting summons',
  provincial: 'Provincial news',
  event_notice: 'Event notice',
  charity: 'Charity notice',
};
const categoryLabel = (cat) => CATEGORY_LABELS[cat] ?? 'General notice';

const STATUS_FILTERS = [['all', 'All items'], ['draft', 'Drafts (needs review)'], ['approved', 'Approved (queued)'], ['sent', 'Sent']];
const STATUS_BADGES = {
  draft: { variant: 'warning', label: 'Draft (review required)' },
  approved: { variant: 'success', label: 'Approved and queued' },
  sent: { variant: 'info', label: 'Sent in digest' },
};

const categoryFilterOptions = [
  { value: 'all', label: 'All categories' },
  { value: 'summons', label: 'Visiting summonses' },
  { value: 'provincial', label: 'Provincial news' },
  { value: 'event_notice', label: 'Event notices' },
  { value: 'general', label: 'General notices' },
  { value: 'charity', label: 'Charity notices' },
];
const categoryOptions = [
  { value: 'summons', label: 'Visiting summons' },
  { value: 'provincial', label: 'Provincial news' },
  { value: 'event_notice', label: 'Event notice' },
  { value: 'general', label: 'General announcement' },
  { value: 'charity', label: 'Charity notice' },
];
const statusOptions = [
  { value: 'draft', label: 'Draft (review required)' },
  { value: 'approved', label: 'Approved and queued for the mailer' },
  { value: 'sent', label: 'Sent' },
];

const fieldClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const fileInputClass = 'w-full text-sm text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-xl file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-50 dark:file:border-slate-700 dark:file:bg-slate-900 dark:file:text-slate-200 dark:hover:file:bg-slate-800';
const smallSecondaryLink = 'inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
</script>

<template>
  <AdminLayout title="Updates and weekly digest" :club="club" active-tab="updates">
    <Head :title="`${club.name} - Updates and weekly digest`" />

    <div class="max-w-6xl mx-auto space-y-6">
      <CommunicationsTabs :club="club" active-tab="updates" />

      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-bold text-slate-900 dark:text-white">Updates and weekly digest</h2>
              <Badge v-if="counts.approved > 0" variant="success">{{ counts.approved }} approved, ready to send</Badge>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Collect forwarded summonses, provincial bulletins and notices to compile into the weekly newsletter automatically.</p>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" size="sm" @click="showContentPicker = true">Content picker</Button>
            <Button variant="secondary" size="sm" @click="openCreateModal">Log new update</Button>
            <Button size="sm" :disabled="!counts.approved" @click="confirmDigestOpen = true">Send weekly digest now ({{ counts.approved }})</Button>
          </div>
        </div>
      </Card>

      <Card padding="sm">
        <div class="flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
          <div class="min-w-0">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Email forwarding address</h3>
            <p class="mt-0.5 break-all font-mono text-sm text-slate-900 dark:text-white">updates-{{ club.slug }}@inbound.360fusionhosting.co.uk</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Forward emails here to log drafts automatically, with PDF summonses and flyer attachments.</p>
          </div>
          <Badge>Forwarded emails are saved as drafts</Badge>
        </div>
      </Card>

      <Card padding="sm">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div class="flex items-center gap-1 overflow-x-auto" role="group" aria-label="Show">
            <button
              v-for="[key, label] in STATUS_FILTERS"
              :key="key"
              type="button"
              :aria-pressed="activeTab === key"
              :class="['rounded-full px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors', activeTab === key ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
              @click="activeTab = key"
            >
              {{ label }} ({{ counts[key] }})
            </button>
          </div>

          <Select v-model="activeCategoryFilter" class="w-full sm:w-56" aria-label="Category" :options="categoryFilterOptions" />
        </div>
      </Card>

      <div v-if="filteredUpdates.length" class="space-y-3">
        <Card v-for="item in filteredUpdates" :key="item.id" padding="sm">
          <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
            <div class="flex min-w-0 flex-1 items-start gap-4">
              <img
                v-if="item.cover_image_url"
                :src="item.cover_image_url"
                alt=""
                class="h-16 w-16 shrink-0 rounded-xl border border-slate-200 object-cover dark:border-slate-800"
              />
              <div class="min-w-0 flex-1 space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                  <Badge>{{ categoryLabel(item.category) }}</Badge>
                  <Badge v-if="STATUS_BADGES[item.status]" :variant="STATUS_BADGES[item.status].variant">{{ STATUS_BADGES[item.status].label }}</Badge>
                </div>

                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ item.title }}</h3>
                <p v-if="item.summary" class="line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ item.summary }}</p>

                <div v-if="item.attachments && item.attachments.length" class="flex flex-wrap items-center gap-1.5 pt-1">
                  <span class="text-xs text-slate-500 dark:text-slate-400">Attachments:</span>
                  <a
                    v-for="(att, aIdx) in item.attachments"
                    :key="aIdx"
                    :href="att.url"
                    target="_blank"
                    :class="smallSecondaryLink"
                  >
                    {{ att.name }}
                  </a>
                </div>
              </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 md:border-t-0 md:pt-0">
              <Button v-if="item.status === 'draft'" size="sm" @click="updateStatus(item, 'approved')">Approve and queue</Button>
              <Button v-if="item.status === 'approved'" variant="secondary" size="sm" @click="updateStatus(item, 'draft')">Revert to draft</Button>
              <Button variant="secondary" size="sm" @click="editUpdate(item)">Edit</Button>
              <Button variant="ghost" size="sm" :aria-label="`Delete ${item.title}`" @click="updateToDelete = item">Delete</Button>
            </div>
          </div>
        </Card>
      </div>

      <Card v-else>
        <div class="space-y-3 py-6 text-center">
          <h3 class="text-base font-semibold text-slate-900 dark:text-white">No updates found</h3>
          <p class="text-sm text-slate-500 dark:text-slate-400">Log forwarded summonses, provincial bulletins or news items to get started.</p>
          <Button size="sm" @click="openCreateModal">Log your first update</Button>
        </div>
      </Card>
    </div>

    <Modal :open="showCreateDrawer" :title="isEditing ? 'Edit update' : 'Log new update'" size="lg" @close="showCreateDrawer = false">
      <form id="update-form" class="space-y-5" @submit.prevent="saveUpdate">
        <Input v-model="form.title" label="Title or subject" required placeholder="Visiting summons - Lodge of Fidelity No. 452" :error="form.errors.title" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Select v-model="form.category" label="Category" required :options="categoryOptions" :error="form.errors.category" />
          <Select v-model="form.status" label="Approval status" required :options="statusOptions" :error="form.errors.status" />
        </div>

        <div class="space-y-2">
          <Textarea v-model="form.summary" label="Summary or body text" :rows="5" placeholder="Paste or clean the forwarded email body here" :error="form.errors.summary" />
          <div class="flex justify-end">
            <Button variant="secondary" size="sm" title="Strip email headers and forwarded signatures" @click="cleanTextInForm">Clean email text</Button>
          </div>
        </div>

        <section class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Cover photo or banner</h3>
            <div class="flex items-center gap-2">
              <Button v-if="coverImagePreview || form.cover_image_url" variant="ghost" size="sm" @click="removeCoverImage">Remove</Button>
              <Button variant="secondary" size="sm" @click="openMediaLibrary('cover', 'updates')">Choose from media library</Button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Input v-model="form.cover_image_url" label="Image URL" placeholder="https://..." :error="form.errors.cover_image_url" />
            <div class="space-y-1.5">
              <label for="update-cover-file" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Or upload an image file</label>
              <input id="update-cover-file" type="file" accept="image/*" :class="fileInputClass" @change="onCoverFileSelect" />
              <p v-if="form.errors.cover_image_file" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.cover_image_file }}</p>
            </div>
          </div>

          <div v-if="coverImagePreview || form.cover_image_url" class="max-w-xs overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
            <img :src="coverImagePreview || form.cover_image_url" alt="Cover image preview" class="h-28 w-full object-cover" />
          </div>
        </section>

        <section class="space-y-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Downloadable attachments and documents</h3>
              <p class="text-xs text-slate-500 dark:text-slate-400">Upload PDF summonses, agendas, spreadsheets or flyers.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Button variant="secondary" size="sm" @click="openMediaLibrary('attachment', 'updates')">Select from media library</Button>
              <Button variant="secondary" size="sm" @click="triggerDocumentUpload">Upload document files</Button>
            </div>
            <input
              ref="documentFileInput"
              type="file"
              multiple
              accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip"
              class="hidden"
              aria-label="Upload document files"
              @change="onDocumentFilesSelect"
            />
          </div>

          <ul v-if="form.attachments && form.attachments.length" class="space-y-1.5">
            <li
              v-for="(att, idx) in form.attachments"
              :key="idx"
              class="flex items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white p-2.5 text-sm dark:border-slate-800 dark:bg-slate-900"
            >
              <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium text-slate-900 dark:text-white">{{ att.name }}</span>
                <Badge v-if="att.isPendingFile" variant="info">New upload</Badge>
              </div>
              <Button variant="ghost" size="sm" :aria-label="`Remove ${att.name}`" @click="removeAttachment(idx)">Remove</Button>
            </li>
          </ul>
          <p v-if="form.errors.attachment_files" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.attachment_files }}</p>

          <div class="space-y-2 border-t border-slate-200 pt-3 dark:border-slate-800">
            <p class="text-xs font-medium text-slate-700 dark:text-slate-300">Or add a document by web link</p>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
              <input v-model="newAttachmentName" type="text" aria-label="Document label" placeholder="Document label (for example, Summons PDF)" :class="fieldClass" />
              <div class="flex items-center gap-2">
                <input v-model="newAttachmentUrl" type="text" aria-label="Document web link" placeholder="URL (https://...)" :class="[fieldClass, 'flex-1 font-mono']" />
                <Button variant="secondary" size="sm" @click="addAttachmentByUrl">Add link</Button>
              </div>
            </div>
          </div>
        </section>
      </form>

      <template #footer>
        <Button variant="secondary" @click="showCreateDrawer = false">Cancel</Button>
        <Button type="submit" form="update-form" :loading="form.processing">Save update</Button>
      </template>
    </Modal>

    <ConfirmDialog :open="Boolean(updateToDelete)" title="Delete this update?" confirm-label="Delete update" @confirm="deleteUpdate" @close="updateToDelete = null">
      "{{ updateToDelete?.title }}" will be deleted.
    </ConfirmDialog>

    <ConfirmDialog :open="confirmDigestOpen" title="Send the weekly digest?" confirm-label="Send now" variant="primary" @confirm="triggerDigestDispatch" @close="confirmDigestOpen = false">
      The weekly digest newsletter will be compiled and sent to members now, with {{ counts.approved }} approved update{{ counts.approved === 1 ? '' : 's' }}.
    </ConfirmDialog>

    <ContentPickerModal
      :show="showContentPicker"
      :updates="updates.filter(u => u.status === 'approved')"
      :meetings="pickerMeetings"
      :events="pickerEvents"
      :news="pickerNews"
      @close="showContentPicker = false"
      @select-content="showContentPicker = false"
    />

    <MediaLibraryModal
      :show="showMediaModal"
      :club-slug="club.slug"
      :default-folder="mediaDefaultFolder"
      @close="showMediaModal = false"
      @select="onMediaSelect"
    />
  </AdminLayout>
</template>
