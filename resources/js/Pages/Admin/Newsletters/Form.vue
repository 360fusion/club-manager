<script setup>
import { formatBytes } from '@/Utils/fileSize';
import Modal from '@/Components/Ui/Modal.vue';
import ConfirmDialog from '@/Components/Ui/ConfirmDialog.vue';
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import { postJson } from '@/Utils/postJson';
import { ref, computed } from 'vue';
import { useForm, Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import ContentPickerModal from '@/Components/ContentPickerModal.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
  club: Object,
  newsletter: Object,
  types: Array,
  posts: Array,
  pickerApprovedUpdates: { type: Array, default: () => [] },
  pickerMeetings: { type: Array, default: () => [] },
  pickerEvents: { type: Array, default: () => [] },
});

const showContentPicker = ref(false);
const showMediaModal = ref(false);

const handleMediaSelect = (media) => {
  if (media && media.original_url) {
    existingAttachments.value.push({
      name: media.name || media.file_name,
      url: media.original_url,
      size: media.human_size || '',
      mime_type: media.mime_type || '',
    });
    form.existing_attachments = existingAttachments.value;
  }
  showMediaModal.value = false;
};

const handleContentSelectedFromPicker = ({ updateIds, meetingIds, eventIds, newsIds, attachFiles = true }) => {
  let html = `<div style="margin: 20px 0; font-family: Arial, sans-serif;">`;

  // 1. Approved Updates
  if (updateIds && updateIds.length) {
    const selectedUpdates = (props.pickerApprovedUpdates || []).filter(u => updateIds.includes(u.id));
    if (selectedUpdates.length) {
      html += `<h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #4f46e5;">📜 Summonses & Member Bulletins</h3>`;
      selectedUpdates.forEach(u => {
        html += `<div style="background: #ffffff; padding: 16px; border-radius: 12px; margin-bottom: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">`;
        html += `<h4 style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: #0f172a;">${u.title}</h4>`;

        let cleanSummary = u.summary || '';
        if (u.attachments && u.attachments.length) {
          u.attachments.forEach(att => {
            if (att.name) {
              const escapedName = att.name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
              cleanSummary = cleanSummary.replace(new RegExp(`📄\\s*${escapedName}`, 'gi'), '');
              cleanSummary = cleanSummary.replace(new RegExp(`${escapedName}`, 'gi'), '');
            }
          });
          cleanSummary = cleanSummary.trim();
        }

        if (cleanSummary) {
          html += `<div style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 10px;">${cleanSummary}</div>`;
        }

        if (u.attachments && u.attachments.length) {
          const validAttachments = (u.attachments || []).filter(att => att && att.url && !att.url.startsWith('blob:') && !att.isPendingFile);
          const uniqueAttachments = [];
          const seenKeys = new Set();
          validAttachments.forEach(att => {
            const key = (att.name || att.url).toLowerCase();
            if (!seenKeys.has(key)) {
              seenKeys.add(key);
              uniqueAttachments.push(att);
            }
          });

          if (uniqueAttachments.length) {
            html += `<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">`;
            uniqueAttachments.forEach(att => {
              html += `<a href="${att.url}" target="_blank" style="display: inline-block; font-size: 12px; font-weight: 700; color: #ffffff; background-color: #4f46e5; padding: 6px 14px; border-radius: 8px; text-decoration: none; margin-right: 8px; margin-bottom: 6px;"><UiIcon name="download" class="inline-block align-text-bottom" /> Download ${att.name}</a>`;

              if (attachFiles) {
                const alreadyAttached = existingAttachments.value.some(existing => existing.url === att.url || existing.name === att.name);
                if (!alreadyAttached) {
                  existingAttachments.value.push({
                    name: att.name,
                    url: att.url,
                    size: att.size ? (typeof att.size === 'number' ? formatBytes(att.size) : att.size) : 'Attached Document',
                    mime_type: att.mime_type || 'application/pdf',
                  });
                }
              }
            });
            html += `</div>`;
          }
        }
        html += `</div>`;
      });
    }
  }

  if (attachFiles) {
    form.existing_attachments = existingAttachments.value;
  }

  // 2. Upcoming Meetings
  if (meetingIds && meetingIds.length) {
    const selectedMeetings = (props.pickerMeetings || []).filter(m => meetingIds.includes(m.id));
    if (selectedMeetings.length) {
      html += `<h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 20px; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #059669;">📅 Upcoming Meetings</h3>`;
      selectedMeetings.forEach(m => {
        html += `<div style="background: #ffffff; padding: 12px 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">`;
        html += `<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">${m.title}</h4>`;
        html += `<p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">🗓️ ${m.date} • 📍 ${m.room}</p>`;
        html += `</div>`;
      });
    }
  }

  // 3. Upcoming Events
  if (eventIds && eventIds.length) {
    const selectedEvts = (props.pickerEvents || []).filter(e => eventIds.includes(e.id));
    if (selectedEvts.length) {
      html += `<h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 20px; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #d97706;">🎟️ Upcoming Events & Dining</h3>`;
      selectedEvts.forEach(e => {
        html += `<div style="background: #ffffff; padding: 12px 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">`;
        html += `<h4 style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">${e.title}</h4>`;
        html += `<p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">🗓️ ${e.date} • 💰 ${e.price}</p>`;
        html += `</div>`;
      });
    }
  }

  // 4. Published News Posts
  if (newsIds && newsIds.length) {
    const selectedNews = (props.posts || []).filter(n => newsIds.includes(n.id));
    if (selectedNews.length) {
      html += `<h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin-top: 20px; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 2px solid #0284c7;">📰 News & Announcements</h3>`;
      selectedNews.forEach(n => {
        html += `<div style="background: #ffffff; padding: 12px 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">`;
        html += `<h4 style="margin: 0 0 4px 0; font-size: 14px; font-weight: 700; color: #0f172a;">${n.title}</h4>`;
        if (n.excerpt) html += `<p style="margin: 0; font-size: 12px; color: #64748b;">${n.excerpt}</p>`;
        html += `</div>`;
      });
    }
  }

  html += `</div><p><br></p>`;
  form.content = (form.content || '') + html;
};

const existingAttachments = ref([...(props.newsletter.attachments || [])]);
const newFiles = ref([]);
const fileInput = ref(null);

// News Items Builder state
const showNewsBuilder = ref(false);
const selectedPostId = ref('');
const customHeadline = ref('');
const customTeaser = ref('');
const customImageUrl = ref('');
const customLinkUrl = ref('');
const imagePosition = ref('left'); // 'left' or 'right'

const stagedNewsItems = ref([]);

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

const isSent = props.newsletter.status === 'sent';

// Merge tags fill in each person's own name when the email goes out.
const MERGE_TAGS = [['{{first_name}}', 'First name'], ['{{name}}', 'Full name'], ['{{club_name}}', 'Club name']];
const insertTag = (tag) => {
  form.content = /<\/p>\s*$/.test(form.content) ? form.content.replace(/<\/p>\s*$/, ` ${tag}</p>`) : `${form.content}<p>${tag}</p>`;
};

// Preview the email as it will look, and send a test to yourself. Neither saves anything.
const previewOpen = ref(false);
const previewHtml = ref('');
const previewMobile = ref(false);
const previewError = ref('');
const draftPayload = () => ({ subject: form.subject, content: form.content, newsletter_type_id: form.newsletter_type_id });

const openPreview = async () => {
  previewError.value = '';
  previewOpen.value = true;
  const result = await postJson(route('admin.newsletters.preview', { clubSlug: props.club.slug }), draftPayload());
  if (result.ok) previewHtml.value = result.data.html;
  else previewError.value = result.data?.message || 'Add a subject and some content to preview it.';
};

const sendTest = () => router.post(route('admin.newsletters.test', { clubSlug: props.club.slug }), draftPayload(), { preserveScroll: true });

const availableRoles = [
  { id: 'member', label: 'Members and brethren' },
  { id: 'coach', label: 'Coaches and officers' },
  { id: 'admin', label: 'Admins and executive board' },
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

const onSelectPost = () => {
  if (!selectedPostId.value) return;
  const post = (props.posts || []).find(p => p.id === Number(selectedPostId.value));
  if (post) {
    customHeadline.value = post.title || '';
    customTeaser.value = post.excerpt || '';
    customImageUrl.value = post.cover_image_url || '';
    customLinkUrl.value = route('member.posts.show', { slug: props.club.slug, id: post.id });
  }
};

const addNewsItemToList = () => {
  if (!customHeadline.value.trim()) return;

  stagedNewsItems.value.push({
    headline: customHeadline.value.trim(),
    teaser: customTeaser.value.trim(),
    imageUrl: customImageUrl.value.trim(),
    linkUrl: customLinkUrl.value.trim(),
    imagePosition: imagePosition.value,
  });

  // Reset inputs
  selectedPostId.value = '';
  customHeadline.value = '';
  customTeaser.value = '';
  customImageUrl.value = '';
  customLinkUrl.value = '';
};

const removeStagedNewsItem = (index) => {
  stagedNewsItems.value.splice(index, 1);
};

const insertNewsListIntoContent = () => {
  if (!stagedNewsItems.value.length) return;

  let html = `<div class="newsletter-news-list" style="margin: 20px 0; font-family: sans-serif;">`;
  html += `<h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 14px; padding-bottom: 6px; border-bottom: 2px solid #e2e8f0;">📰 News & Announcements</h3>`;

  stagedNewsItems.value.forEach(item => {
    const hasImage = Boolean(item.imageUrl);
    const imgHtml = hasImage 
      ? `<div style="flex-shrink: 0; width: 140px;"><img src="${item.imageUrl}" alt="${item.headline}" style="width: 140px; height: 95px; object-fit: cover; border-radius: 8px; display: block;" /></div>`
      : '';

    const textHtml = `
      <div style="flex-grow: 1;">
        <h4 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 700; color: #0f172a; line-height: 1.3;">${item.headline}</h4>
        ${item.teaser ? `<p style="margin: 0 0 8px 0; font-size: 13px; color: #475569; line-height: 1.5;">${item.teaser}</p>` : ''}
        ${item.linkUrl ? `<a href="${item.linkUrl}" target="_blank" style="display: inline-block; font-size: 12px; font-weight: 700; color: #4f46e5; text-decoration: none;">Read full story &rarr;</a>` : ''}
      </div>
    `;

    html += `<div style="display: flex; gap: 16px; align-items: start; padding: 14px; margin-bottom: 12px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff; shadow: 0 1px 2px rgba(0,0,0,0.05);">`;
    
    if (item.imagePosition === 'right' && hasImage) {
      html += textHtml + imgHtml;
    } else {
      html += imgHtml + textHtml;
    }

    html += `</div>`;
  });

  html += `</div><p><br></p>`;

  form.content = (form.content || '') + html;
  stagedNewsItems.value = [];
  showNewsBuilder.value = false;
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

// Sending emails everyone once and cannot be undone, so it waits for the confirm dialog.
const confirmingSend = ref(false);
const sendBroadcast = () => {
  confirmingSend.value = false;
  submitForm('sent');
};

const channelOptions = computed(() => (props.types || []).map((t) => ({ value: t.id, label: `${t.icon} ${t.name}` })));
const postOptions = computed(() => [
  { value: '', label: 'Choose a published post to import' },
  ...(props.posts || []).map((post) => ({ value: post.id, label: `${post.title} (${post.published_at ? new Date(post.published_at).toLocaleDateString() : 'Published'})` })),
]);
const IMAGE_POSITION_OPTIONS = [
  { value: 'left', label: 'Image on the left, text on the right' },
  { value: 'right', label: 'Image on the right, text on the left' },
];
const rowBox = 'rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50';
const checkbox = 'h-4 w-4 rounded accent-blue-600';
const sectionTitle = 'text-sm font-semibold text-slate-900 dark:text-white';
</script>

<template>
  <AdminLayout :title="newsletter.id ? 'Edit newsletter' : 'Write a newsletter'" :club="club" active-tab="newsletters">
    <Head :title="newsletter.id ? 'Edit newsletter' : 'Write a newsletter'" />

    <div class="mx-auto max-w-4xl space-y-6">
      <Card>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ newsletter.id ? 'Edit the newsletter' : 'Write a newsletter' }}</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Choose the channel, member roles and visiting subscribers it goes to, and attach files to download.</p>
          </div>
          <Button variant="ghost" size="sm" :href="route('admin.newsletters.index', { clubSlug: club.slug })">Back to newsletters</Button>
        </div>
      </Card>

      <Card>
      <form class="space-y-6" @submit.prevent>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <Select v-model="form.newsletter_type_id" label="Channel" required :options="channelOptions" :error="form.errors.newsletter_type_id" />
          <Input v-model="form.subject" class="md:col-span-2" label="Subject line" required placeholder="Summer regatta schedule and summons" :error="form.errors.subject" />
        </div>

        <fieldset>
          <legend class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Member roles to send to</legend>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <label v-for="role in availableRoles" :key="role.id" :class="[rowBox, 'flex cursor-pointer items-center gap-2 p-3 text-sm font-medium text-slate-900 dark:text-white']">
              <input type="checkbox" :checked="form.target_roles.includes(role.id)" :class="checkbox" @change="toggleRole(role.id)" />
              {{ role.label }}
            </label>
          </div>
          <p v-if="form.errors.target_roles" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.target_roles }}</p>
        </fieldset>

        <div :class="[rowBox, 'flex flex-col justify-between gap-3 sm:flex-row sm:items-center']">
          <div>
            <h3 :class="sectionTitle">Insert content from the site</h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Choose approved summonses, upcoming meetings, events or news posts to add to the email.</p>
          </div>
          <Button variant="secondary" size="sm" class="shrink-0" @click="showContentPicker = true">Choose content</Button>
        </div>

        <div :class="[rowBox, 'flex flex-col justify-between gap-3 sm:flex-row sm:items-center']">
          <div>
            <h3 :class="sectionTitle">Add a list of news items</h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Lay out published club news or your own stories with images, headlines and teaser text.</p>
          </div>
          <Button variant="secondary" size="sm" class="shrink-0" :aria-expanded="showNewsBuilder" @click="showNewsBuilder = !showNewsBuilder">
            {{ showNewsBuilder ? 'Hide the news builder' : 'Open the news builder' }}
          </Button>
        </div>

        <!-- News Items Builder Card -->
        <div v-if="showNewsBuilder" :class="[rowBox, 'space-y-4']">
          <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-200 pb-3 dark:border-slate-800">
            <h3 :class="sectionTitle">Build a list of news items</h3>
            <span class="text-xs text-slate-500 dark:text-slate-400">Each item shows a thumbnail beside its headline and teaser.</span>
          </div>

          <Select
            v-if="posts && posts.length"
            :model-value="selectedPostId"
            label="Import a published club post"
            :options="postOptions"
            @update:model-value="(value) => { selectedPostId = value; onSelectPost(); }"
          />

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Input v-model="customHeadline" label="Headline" placeholder="For example: annual regatta trophies awarded" />
            <Input v-model="customImageUrl" label="Cover image URL" hint="Optional." placeholder="https://example.com/image.jpg" />
          </div>

          <Textarea v-model="customTeaser" label="Teaser text" :rows="2" placeholder="A short summary shown below the headline" />

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Input v-model="customLinkUrl" label="Link to the full story" hint="Optional." placeholder="https://..." />
            <Select v-model="imagePosition" label="Thumbnail layout" :options="IMAGE_POSITION_OPTIONS" />
          </div>

          <div class="flex justify-end">
            <Button size="sm" :disabled="!customHeadline.trim()" @click="addNewsItemToList">Add to the list</Button>
          </div>

          <div v-if="stagedNewsItems.length" class="space-y-3 border-t border-slate-200 pt-3 dark:border-slate-800">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 :class="sectionTitle">News list ({{ stagedNewsItems.length }} {{ stagedNewsItems.length === 1 ? 'item' : 'items' }})</h4>
              <Button size="sm" @click="insertNewsListIntoContent">Insert the list into the content</Button>
            </div>

            <ul class="space-y-2">
              <li
                v-for="(item, idx) in stagedNewsItems"
                :key="idx"
                class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <img v-if="item.imageUrl" :src="item.imageUrl" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover" />
                  <div v-else class="h-12 w-12 shrink-0 rounded-lg bg-slate-100 dark:bg-slate-800" aria-hidden="true"></div>
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ item.headline }}</p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ item.teaser || 'No teaser text' }}</p>
                  </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                  <a
                    v-if="item.linkUrl"
                    :href="item.linkUrl"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                  >
                    Open link
                  </a>
                  <Button variant="ghost" size="sm" :aria-label="`Remove ${item.headline}`" @click="removeStagedNewsItem(idx)">Remove</Button>
                </div>
              </li>
            </ul>
          </div>
        </div>

        <div>
          <p class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Content</p>
          <div class="mb-2 flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
            <span>Personalise:</span>
            <Button v-for="[tag, name] in MERGE_TAGS" :key="tag" variant="secondary" size="sm" class="font-mono" :title="`Adds ${name}`" @click="insertTag(tag)">{{ tag }}</Button>
          </div>
          <RichTextEditor v-model="form.content" placeholder="Write the newsletter here" />
          <p v-if="form.errors.content" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.content }}</p>
        </div>

        <!-- Attachments Section -->
        <div class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800">
          <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
            <div>
              <h3 :class="sectionTitle">Attachments and downloads</h3>
              <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Attach summons PDFs, meeting agendas, financial reports or image circulars for the people it goes to.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
              <Button variant="secondary" size="sm" @click="showMediaModal = true">Choose from the file manager</Button>
              <Button variant="secondary" size="sm" @click="triggerFileInput">Upload a file</Button>
            </div>
            <input
              ref="fileInput"
              type="file"
              multiple
              class="hidden"
              aria-label="Upload attachments"
              accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.zip"
              @change="onFileSelect"
            />
          </div>

          <ul v-if="existingAttachments.length || newFiles.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <li
              v-for="(att, idx) in existingAttachments"
              :key="'existing-' + idx"
              :class="[rowBox, 'flex items-center justify-between gap-2 p-3']"
            >
              <div class="min-w-0">
                <a :href="att.url" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ att.name }}</a>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ att.size || 'Saved file' }}</span>
              </div>
              <Button variant="ghost" size="sm" :aria-label="`Remove ${att.name}`" @click="removeExistingAttachment(idx)">Remove</Button>
            </li>

            <li
              v-for="(file, idx) in newFiles"
              :key="'new-' + idx"
              :class="[rowBox, 'flex items-center justify-between gap-2 p-3']"
            >
              <div class="min-w-0">
                <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ file.name }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatBytes(file.size) }}, uploads when you save</span>
              </div>
              <Button variant="ghost" size="sm" :aria-label="`Remove ${file.name}`" @click="removeNewFile(idx)">Remove</Button>
            </li>
          </ul>

          <button
            v-else
            type="button"
            class="block w-full rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 p-6 text-center transition-colors hover:border-blue-300 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-blue-700/60"
            @click="triggerFileInput"
          >
            <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Click to upload attachments</span>
            <span class="block text-xs text-slate-500 dark:text-slate-400">PDFs, Word documents, Excel sheets, images or zip files (up to 10MB each)</span>
          </button>
        </div>

        <Alert v-if="isSent" variant="warning">This newsletter has been sent, so it can no longer be changed. Go back and use Duplicate to send something similar.</Alert>

        <div v-if="!isSent" class="flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
          <Button variant="secondary" size="sm" @click="openPreview">Preview</Button>
          <Button variant="secondary" size="sm" @click="sendTest">Send a test to me</Button>
        </div>

        <div v-if="!isSent" class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-slate-800 sm:flex-row">
          <Button variant="secondary" size="lg" class="flex-1" :loading="form.processing" @click="saveDraft">Save as draft</Button>
          <Button size="lg" class="flex-1" :disabled="form.processing" @click="confirmingSend = true">Send now</Button>
        </div>
      </form>
      </Card>
    </div>

    <ConfirmDialog
      :open="confirmingSend"
      title="Send this newsletter now?"
      confirm-label="Send now"
      cancel-label="Not yet"
      variant="primary"
      :processing="form.processing"
      @confirm="sendBroadcast"
      @close="confirmingSend = false"
    >
      It will be emailed to all the targeted members and external subscribers now. It cannot be changed or sent again afterwards.
    </ConfirmDialog>

    <Modal :open="previewOpen" size="xl" title="Email preview" subtitle="How it will look in an inbox, with a sample name filled in" @close="previewOpen = false">
      <div class="mb-3 flex gap-1" role="group" aria-label="Preview size">
        <button
          v-for="[mobile, label] in [[false, 'Desktop'], [true, 'Mobile']]"
          :key="label"
          type="button"
          :aria-pressed="previewMobile === mobile"
          :class="['rounded-full px-3 py-1.5 text-xs font-medium transition-colors', previewMobile === mobile ? 'bg-slate-900 text-white dark:bg-slate-700' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800']"
          @click="previewMobile = mobile"
        >
          {{ label }}
        </button>
      </div>
      <Alert v-if="previewError" variant="danger">{{ previewError }}</Alert>
      <div v-else class="flex justify-center rounded-xl bg-slate-100 p-3 dark:bg-slate-800">
        <iframe sandbox="" :srcdoc="previewHtml" title="Email preview" :class="['h-[60vh] rounded-lg border-0 bg-white', previewMobile ? 'w-[375px]' : 'w-full']"></iframe>
      </div>
    </Modal>

    <!-- Interactive Content Picker Modal -->
    <ContentPickerModal
      :show="showContentPicker"
      :updates="pickerApprovedUpdates"
      :meetings="pickerMeetings"
      :events="pickerEvents"
      :news="posts"
      @close="showContentPicker = false"
      @select-content="handleContentSelectedFromPicker"
    />

    <!-- Media Library Modal -->
    <MediaLibraryModal
      :show="showMediaModal"
      :club-slug="club.slug"
      default-folder="newsletters"
      @close="showMediaModal = false"
      @select="handleMediaSelect"
    />

  </AdminLayout>
</template>
