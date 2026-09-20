<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import ContentPickerModal from '@/Components/ContentPickerModal.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';

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
              html += `<a href="${att.url}" target="_blank" style="display: inline-block; font-size: 12px; font-weight: 700; color: #ffffff; background-color: #4f46e5; padding: 6px 14px; border-radius: 8px; text-decoration: none; margin-right: 8px; margin-bottom: 6px;">📥 Download ${att.name}</a>`;

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
      <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-6 rounded-2xl shadow-sm border border-slate-200/80 dark:border-slate-800/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ newsletter.id ? 'Edit Newsletter Broadcast' : 'Compose New Email Broadcast' }}</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Target specific channels, member roles, visiting subscribers, and attach downloadable files.</p>
        </div>
        <Link :href="route('admin.newsletters.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold rounded-xl transition-all">
          &larr; Back to Newsletters
        </Link>
      </div>

      <!-- Form -->
      <form @submit.prevent class="bg-white dark:bg-slate-900 rounded-2xl p-6 shadow-sm border border-slate-200/80 dark:border-slate-800/80 space-y-6">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="col-span-1">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Newsletter Channel *</label>
            <select v-model="form.newsletter_type_id" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500">
              <option v-for="t in types" :key="t.id" :value="t.id">
                {{ t.icon }} {{ t.name }}
              </option>
            </select>
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email Subject Line *</label>
            <input v-model="form.subject" type="text" required placeholder="Summer Regatta Schedule & Summons Circular" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" />
          </div>
        </div>

        <!-- Target Roles Selector -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-2">Target Internal Member Roles</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div 
              v-for="role in availableRoles" 
              :key="role.id"
              @click="toggleRole(role.id)"
              :class="[
                'p-3 rounded-xl border text-xs font-bold cursor-pointer transition-all flex items-center justify-between',
                form.target_roles.includes(role.id)
                  ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-700/60 text-blue-900 dark:text-blue-200 shadow-sm'
                  : 'bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400'
              ]"
            >
              <span>{{ role.label }}</span>
              <span v-if="form.target_roles.includes(role.id)" class="text-blue-600 dark:text-blue-400">✓</span>
            </div>
          </div>
        </div>

        <!-- System Content Picker Trigger Card -->
        <div class="bg-emerald-50/70 dark:bg-emerald-950/70 p-4 rounded-2xl border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-between">
          <div>
            <h4 class="text-xs font-bold text-emerald-950 dark:text-emerald-100 flex items-center gap-1.5">
              <span>🧩</span> Select & Insert System Content
            </h4>
            <p class="text-[11px] text-emerald-800/80 dark:text-emerald-200/80 mt-0.5">Select approved summonses, upcoming meetings, events, or news posts to automatically embed into your email body.</p>
          </div>
          <button
            type="button"
            @click="showContentPicker = true"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1.5"
          >
            <span>🧩</span> Select Content Items
          </button>
        </div>

        <!-- News Items Builder Toggle Bar -->
        <div class="bg-blue-50/60 dark:bg-blue-950/60 p-4 rounded-2xl border border-blue-100 dark:border-blue-900/40 flex items-center justify-between">
          <div>
            <h4 class="text-xs font-bold text-blue-950 dark:text-blue-100 flex items-center gap-1.5">
              <span>📰</span> Add News Items List to Newsletter
            </h4>
            <p class="text-[11px] text-blue-700/80 dark:text-blue-300/80 mt-0.5">Format and embed published club news or custom stories with images, headlines, and teaser text.</p>
          </div>
          <button
            type="button"
            @click="showNewsBuilder = !showNewsBuilder"
            class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-sm transition-all cursor-pointer flex items-center gap-1"
          >
            {{ showNewsBuilder ? 'Hide News Builder' : '⚡ Open News Items Builder' }}
          </button>
        </div>

        <!-- News Items Builder Card -->
        <div v-if="showNewsBuilder" class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-4 text-xs">
          <div class="flex items-center justify-between border-b border-slate-200/80 dark:border-slate-800/80 pb-3">
            <span class="font-bold text-slate-900 dark:text-white text-sm">📰 Construct News Items List</span>
            <span class="text-slate-500 dark:text-slate-400 text-[11px]">Format: Thumbnail image side-by-side with headline & teaser text</span>
          </div>

          <!-- Select from Published Posts -->
          <div v-if="posts && posts.length" class="space-y-1">
            <label class="block font-bold text-slate-700 dark:text-slate-200">Import from Published Club Posts</label>
            <select v-model="selectedPostId" @change="onSelectPost" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-medium text-slate-800 dark:text-slate-100">
              <option value="">-- Choose a published news post to import --</option>
              <option v-for="post in posts" :key="post.id" :value="post.id">
                {{ post.title }} ({{ post.published_at ? new Date(post.published_at).toLocaleDateString() : 'Published' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Headline Title *</label>
              <input v-model="customHeadline" type="text" placeholder="e.g. Annual Regatta Trophies Awarded" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Cover Image URL (Optional)</label>
              <input v-model="customImageUrl" type="text" placeholder="https://example.com/image.jpg" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
            </div>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Teaser Text Excerpt</label>
            <textarea v-model="customTeaser" rows="2" placeholder="Short teaser summary that appears below the headline..." class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl"></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Story Link URL (Optional)</label>
              <input v-model="customLinkUrl" type="text" placeholder="https://..." class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-mono text-[11px]" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Thumbnail Layout</label>
              <select v-model="imagePosition" class="w-full p-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl font-semibold">
                <option value="left">Image on Left (Headline & Teaser on Right)</option>
                <option value="right">Image on Right (Headline & Teaser on Left)</option>
              </select>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2">
            <button
              type="button"
              @click="addNewsItemToList"
              :disabled="!customHeadline.trim()"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-bold rounded-xl transition-all cursor-pointer"
            >
              + Add News Item to List
            </button>
          </div>

          <!-- Staged Items Preview List -->
          <div v-if="stagedNewsItems.length" class="space-y-3 pt-3 border-t border-slate-200 dark:border-slate-800">
            <div class="font-bold text-slate-900 dark:text-white flex items-center justify-between">
              <span>Staged News List ({{ stagedNewsItems.length }} {{ stagedNewsItems.length === 1 ? 'item' : 'items' }})</span>
              <button
                type="button"
                @click="insertNewsListIntoContent"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition-all cursor-pointer flex items-center gap-1"
              >
                📥 Insert Formatted List into Content
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="(item, idx) in stagedNewsItems"
                :key="idx"
                class="p-3 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3"
              >
                <div class="flex items-center gap-3 overflow-hidden">
                  <img v-if="item.imageUrl" :src="item.imageUrl" class="w-12 h-12 rounded-lg object-cover flex-shrink-0" />
                  <div v-else class="w-12 h-12 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center font-bold flex-shrink-0">📰</div>
                  <div class="truncate">
                    <h5 class="font-bold text-slate-900 dark:text-white truncate">{{ item.headline }}</h5>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ item.teaser || 'No teaser text' }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <a
                    v-if="item.linkUrl"
                    :href="item.linkUrl"
                    target="_blank"
                    class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-bold rounded-lg border border-slate-300 dark:border-slate-700 transition-all flex items-center gap-1"
                    title="Preview Target Link"
                  >
                    👁️ Preview
                  </a>
                  <button
                    type="button"
                    @click="removeStagedNewsItem(idx)"
                    class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 font-bold p-1 rounded cursor-pointer"
                    title="Remove item"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Newsletter Content -->
        <div>
          <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider mb-1.5">Newsletter Content (WYSIWYG)</label>
          <RichTextEditor v-model="form.content" placeholder="Write rich email newsletter content here..." />
        </div>

        <!-- Attachments Section -->
        <div class="space-y-3 pt-4 border-t border-slate-100 dark:border-slate-800">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase tracking-wider">📎 Attachments & Downloads</label>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Attach Summons PDFs, meeting agendas, financial reports, or image circulars for recipients.</p>
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="showMediaModal = true"
                class="px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-800 dark:text-amber-200 text-xs font-bold rounded-xl border border-amber-200 dark:border-amber-800/60 transition-all flex items-center gap-1 cursor-pointer"
              >
                📁 Select from File Manager
              </button>
              <button
                type="button"
                @click="triggerFileInput"
                class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold rounded-xl border border-blue-200 dark:border-blue-800/60 transition-all flex items-center gap-1 cursor-pointer"
              >
                + Upload New Attachment
              </button>
            </div>
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
              class="flex items-center justify-between bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200/80 dark:border-slate-800/80 text-xs"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span class="text-lg">{{ getFileIcon(att.mime_type || att.name) }}</span>
                <div class="truncate">
                  <a :href="att.url" target="_blank" class="font-bold text-slate-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 truncate block">
                    {{ att.name }}
                  </a>
                  <span class="text-[10px] text-slate-500 dark:text-slate-400">{{ att.size || 'Saved File' }}</span>
                </div>
              </div>
              <button
                type="button"
                @click="removeExistingAttachment(idx)"
                class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 font-bold p-1 rounded transition-colors cursor-pointer"
                title="Remove attachment"
              >
                ✕
              </button>
            </div>

            <!-- Newly Selected Files -->
            <div
              v-for="(file, idx) in newFiles"
              :key="'new-' + idx"
              class="flex items-center justify-between bg-blue-50/50 dark:bg-blue-950/50 p-3 rounded-xl border border-blue-200/80 dark:border-blue-800/80 text-xs"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span class="text-lg">{{ getFileIcon(file.name) }}</span>
                <div class="truncate">
                  <span class="font-bold text-blue-900 dark:text-blue-200 truncate block">{{ file.name }}</span>
                  <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold">{{ formatBytes(file.size) }} (Pending upload)</span>
                </div>
              </div>
              <button
                type="button"
                @click="removeNewFile(idx)"
                class="text-blue-400 hover:text-rose-600 dark:hover:text-rose-400 font-bold p-1 rounded transition-colors cursor-pointer"
                title="Remove file"
              >
                ✕
              </button>
            </div>

          </div>

          <div v-else @click="triggerFileInput" class="border-2 border-dashed border-slate-200 dark:border-slate-800 rounded-2xl p-6 text-center hover:border-blue-300 dark:hover:border-blue-700/60 transition-colors cursor-pointer bg-slate-50/50 dark:bg-slate-800/50/50">
            <span class="text-2xl block mb-1">📁</span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 block">Click to upload attachments</span>
            <span class="text-[11px] text-slate-400">PDFs, Word Documents, Excel sheets, Images, or Zip files (up to 10MB per file)</span>
          </div>
        </div>

        <div class="flex items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-800">
          <button type="button" @click="saveDraft" :disabled="form.processing" class="flex-1 py-3 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold rounded-xl text-xs border border-slate-300 dark:border-slate-700 transition-all cursor-pointer">
            💾 Save as Draft
          </button>
          <button type="button" @click="sendBroadcast" :disabled="form.processing" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md shadow-blue-600/20 transition-all cursor-pointer">
            🚀 Send Email Broadcast Now
          </button>
        </div>

      </form>

    </div>

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
