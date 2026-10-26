<script setup>
import { formatBytes } from '@/Utils/fileSize';
import { formatLocal } from '@/Utils/localDateTime';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import MediaLibraryModal from '@/Components/MediaLibraryModal.vue';
import Select from '@/Components/Ui/Select.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Modal from '@/Components/Ui/Modal.vue';
import { orderColour } from '@/Utils/orderColour';

const props = defineProps({
  club: Object,
  post: Object,
  visibilityOptions: { type: Array, default: () => [] },
  tags: { type: Array, default: () => [] },
});

const existingAttachments = ref([...(props.post.attachments || [])]);
const newFiles = ref([]);
const fileInput = ref(null);

const coverImageFile = ref(null);
const coverImagePreview = ref(props.post.cover_image_url || '');

const blocks = ref([...(props.post.blocks || [])]);

// Spatie Media Library Modal State
const showMediaModal = ref(false);
const mediaTarget = ref(null);
const mediaDefaultFolder = ref('news');

const openMediaLibrary = (type, targetObj = null, folder = 'news') => {
  mediaTarget.value = { type, targetObj };
  mediaDefaultFolder.value = folder;
  showMediaModal.value = true;
};

const onMediaSelect = (mediaItem) => {
  if (!mediaTarget.value) return;

  const { type, targetObj } = mediaTarget.value;

  if (type === 'cover') {
    form.cover_image_url = mediaItem.url;
    coverImagePreview.value = mediaItem.url;
  } else if (type === 'block_image' && targetObj) {
    targetObj.url = mediaItem.url;
  } else if (type === 'gallery_image' && targetObj) {
    targetObj.url = mediaItem.url;
  } else if (type === 'attachment') {
    existingAttachments.value.push({
      name: mediaItem.file_name || mediaItem.name,
      url: mediaItem.url,
      size: mediaItem.size,
      mime_type: mediaItem.mime_type,
    });
    form.existing_attachments = existingAttachments.value;
  }
};

// Default block templates
if (!blocks.value.length) {
  blocks.value = [
    {
      id: 'block-' + Date.now(),
      type: 'text',
      content: props.post.content || '',
      isCollapsed: false,
    }
  ];
}

const showPublishSettings = ref(true);
const isPublished = ref(props.post.status === 'published' || props.post.status === undefined);
const showPreviewModal = ref(false);

const form = useForm({
  id: props.post.id || null,
  title: props.post.title || '',
  slug: props.post.slug || '',
  excerpt: props.post.excerpt || '',
  content: props.post.content || '',
  cover_image_url: props.post.cover_image_url || '',
  cover_image: null,
  status: isPublished.value ? 'published' : 'draft',
  visibility: props.post.visibility || 'club',
  published_at: props.post.published_at || '',
  expires_at: props.post.expires_at || '',
  tag_ids: [...(props.post.tag_ids || [])],
  action_type: 'save',
  blocks: blocks.value,
  existing_attachments: existingAttachments.value,
  new_attachments: [],
});

watch(isPublished, (val) => {
  form.status = val ? 'published' : 'draft';
});

const toggleTag = (id) => {
  form.tag_ids = form.tag_ids.includes(id) ? form.tag_ids.filter((t) => t !== id) : [...form.tag_ids, id];
};

const visibilityHint = computed(() => props.visibilityOptions.find((o) => o.value === form.visibility)?.description ?? '');

// Auto-generate slug from title
watch(() => form.title, (newTitle) => {
  if (!props.post.id && newTitle) {
    form.slug = newTitle
      .toLowerCase()
      .trim()
      .replace(/[^a-z0-9 -]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-');
  }
});

const onCoverImageFileSelect = (e) => {
  const file = e.target.files?.[0];
  if (file) {
    coverImageFile.value = file;
    form.cover_image = file;
    coverImagePreview.value = URL.createObjectURL(file);
  }
};

const removeCoverImage = () => {
  form.cover_image_url = '';
  form.cover_image = null;
  coverImageFile.value = null;
  coverImagePreview.value = '';
};

const removeBlockImage = (block) => {
  block.url = '';
  block.file = null;
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

// Block Element Builder Functions
const blockTypes = [
  { type: 'text', label: 'Text block', desc: 'Rich text paragraph or formatted text' },
  { type: 'image', label: 'Single image', desc: 'An image with size and position controls' },
  { type: 'images', label: 'Image gallery', desc: 'Several images in a grid' },
  { type: 'notice', label: 'Callout box', desc: 'A highlighted notice or announcement' },
  { type: 'button', label: 'Button link', desc: 'A button that links somewhere' },
];

const blockLabel = (block) => {
  const label = blockTypes.find((item) => item.type === block.type)?.label ?? block.type;

  return block.type === 'images' ? `${label} (${block.columns} columns)` : label;
};

const IMAGE_POSITIONS = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Centred' },
  { value: 'right', label: 'Right' },
  { value: 'full', label: 'Full-width banner' },
];

const IMAGE_SIZES = [
  { value: 'small', label: 'Small (a quarter of the width)' },
  { value: 'medium', label: 'Medium (half the width)' },
  { value: 'large', label: 'Large (three quarters of the width)' },
  { value: 'full', label: 'Full width' },
];

const NOTICE_STYLES = [
  { value: 'info', label: 'Information or announcement' },
  { value: 'warning', label: 'Warning or reminder' },
  { value: 'important', label: 'Important or by-laws' },
  { value: 'success', label: 'Success or milestone' },
];

const BUTTON_ALIGNMENTS = [
  { value: 'left', label: 'Left' },
  { value: 'center', label: 'Centre' },
  { value: 'right', label: 'Right' },
];

const noticeVariant = (style) => ({ warning: 'warning', success: 'success' }[style] ?? 'info');

// Fields inside repeated rows (gallery images) keep the kit's field look.
const field = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
const fileField = 'w-full text-xs text-slate-600 dark:text-slate-300 file:mr-3 file:rounded-xl file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-50 dark:file:border-slate-700 dark:file:bg-slate-900 dark:file:text-slate-200 cursor-pointer';
const linkButton = 'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';

const activeInsertIndex = ref(null);

const toggleInsertMenu = (index) => {
  if (activeInsertIndex.value === index) {
    activeInsertIndex.value = null;
  } else {
    activeInsertIndex.value = index;
  }
};

const handleDocumentClick = (e) => {
  if (activeInsertIndex.value !== null && !e.target.closest('.insert-menu-container')) {
    activeInsertIndex.value = null;
  }
};

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
});

onUnmounted(() => {
  document.removeEventListener('click', handleDocumentClick);
});

const addBlock = (type, targetIndex = null) => {
  const id = 'block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 4);
  let newBlock = null;

  if (type === 'text') {
    newBlock = { id, type: 'text', content: '', isCollapsed: false };
  } else if (type === 'image') {
    newBlock = {
      id,
      type: 'image',
      url: '',
      caption: '',
      position: 'center', // 'left', 'center', 'right', 'full'
      size: 'large', // 'small' (25%), 'medium' (50%), 'large' (75%), 'full' (100%)
      file: null,
      isCollapsed: false,
    };
  } else if (type === 'images') {
    newBlock = {
      id,
      type: 'images',
      columns: 3, // 2, 3, 4
      items: [
        { id: id + '-g1', url: '', caption: '', file: null },
        { id: id + '-g2', url: '', caption: '', file: null },
      ],
      isCollapsed: false,
    };
  } else if (type === 'notice') {
    newBlock = {
      id,
      type: 'notice',
      style: 'info', // 'info', 'warning', 'important', 'success'
      title: 'Important Notice',
      text: '',
      isCollapsed: false,
    };
  } else if (type === 'button') {
    newBlock = {
      id,
      type: 'button',
      label: 'Learn More →',
      url: '',
      align: 'center', // 'left', 'center', 'right'
      isCollapsed: false,
    };
  }

  if (newBlock) {
    if (targetIndex !== null && targetIndex >= 0 && targetIndex <= blocks.value.length) {
      blocks.value.splice(targetIndex, 0, newBlock);
    } else {
      blocks.value.push(newBlock);
    }
  }

  activeInsertIndex.value = null;
};

const onBlockImageFileSelect = (e, block) => {
  const file = e.target.files?.[0];
  if (file) {
    block.file = file;
    block.url = URL.createObjectURL(file);
  }
};

const onGalleryImageFileSelect = (e, gItem) => {
  const file = e.target.files?.[0];
  if (file) {
    gItem.file = file;
    gItem.url = URL.createObjectURL(file);
  }
};

const moveBlockUp = (index) => {
  if (index > 0) {
    const temp = blocks.value[index];
    blocks.value[index] = blocks.value[index - 1];
    blocks.value[index - 1] = temp;
  }
};

const moveBlockDown = (index) => {
  if (index < blocks.value.length - 1) {
    const temp = blocks.value[index];
    blocks.value[index] = blocks.value[index + 1];
    blocks.value[index + 1] = temp;
  }
};

const duplicateBlock = (index) => {
  const source = blocks.value[index];
  const copy = JSON.parse(JSON.stringify(source));
  copy.id = 'block-' + Date.now();
  blocks.value.splice(index + 1, 0, copy);
};

const removeBlock = (index) => {
  blocks.value.splice(index, 1);
};

const addGalleryImage = (block) => {
  const gId = block.id + '-g' + Date.now();
  block.items.push({ id: gId, url: '', caption: '', file: null });
};

const removeGalleryImage = (block, idx) => {
  block.items.splice(idx, 1);
};

const fileKind = (mimeOrName) => {
  const name = (mimeOrName || '').toLowerCase();
  if (name.includes('pdf')) return 'PDF';
  if (name.includes('image') || name.endsWith('.png') || name.endsWith('.jpg') || name.endsWith('.jpeg')) return 'Image';
  if (name.includes('sheet') || name.endsWith('.xls') || name.endsWith('.xlsx') || name.endsWith('.csv')) return 'Spreadsheet';
  if (name.includes('word') || name.endsWith('.doc') || name.endsWith('.docx')) return 'Document';
  if (name.endsWith('.zip') || name.endsWith('.rar')) return 'Archive';
  return 'File';
};


const submit = () => {
  // Sync main content field with first text block if available
  const mainTextBlock = blocks.value.find(b => b.type === 'text');
  if (mainTextBlock) {
    form.content = mainTextBlock.content;
  }

  // Gather image files from blocks and build a clean blocks payload
  const blockFiles = {};
  const cleanBlocks = JSON.parse(JSON.stringify(blocks.value));

  blocks.value.forEach((b, idx) => {
    if (b.type === 'image' && b.file) {
      blockFiles[b.id] = b.file;
    }
    if (b.type === 'images' && Array.isArray(b.items)) {
      b.items.forEach((gItem, gIdx) => {
        if (gItem.file) {
          const gId = gItem.id || `${b.id}-g${gIdx}`;
          gItem.id = gId;
          if (cleanBlocks[idx] && cleanBlocks[idx].items && cleanBlocks[idx].items[gIdx]) {
            cleanBlocks[idx].items[gIdx].id = gId;
          }
          blockFiles[gId] = gItem.file;
        }
      });
    }
  });

  // Strip transient file objects from cleanBlocks payload
  cleanBlocks.forEach(b => {
    delete b.file;
    if (Array.isArray(b.items)) {
      b.items.forEach(gItem => delete gItem.file);
    }
  });

  form.blocks = cleanBlocks;
  form.block_files = blockFiles;
  form.existing_attachments = existingAttachments.value;
  form.new_attachments = newFiles.value;

  form.post(route('admin.posts.store', { clubSlug: props.club.slug }), {
    forceFormData: true,
  });
};

const submitWithAction = (actionType) => {
  form.action_type = actionType;
  submit();
};
</script>

<template>
  <AdminLayout :title="post.id ? 'Edit post' : 'Create a post'" :club="club" active-tab="posts">
    <div class="mx-auto max-w-6xl space-y-6">
      <Card>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ post.id ? 'Edit the news post' : 'Write a news post' }}</h2>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Build the article from text, images, callouts and files to download.</p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <Button variant="secondary" size="sm" @click="showPreviewModal = true">Preview</Button>
            <a
              v-if="post.id"
              :href="route('member.posts.show', { slug: club.slug, id: post.id })"
              target="_blank"
              rel="noopener"
              :class="linkButton"
            >
              View in the member portal (opens a new tab)
            </a>
            <Button variant="ghost" size="sm" :href="route('admin.posts.index', { clubSlug: club.slug })">Back to posts</Button>
          </div>
        </div>
      </Card>

      <form class="space-y-6" @submit.prevent="submit">
        <Card>
          <template #header><h3 class="text-base font-semibold text-slate-900 dark:text-white">Article</h3></template>
          <div class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Input v-model="form.title" label="Title" required placeholder="Summer regatta results and trophy ceremony" :error="form.errors.title" />
              <Input v-model="form.slug" label="Web address (slug)" required placeholder="summer-regatta-results" :error="form.errors.slug" />
              <Input v-model="form.published_at" type="datetime-local" label="Post date and time" required :error="form.errors.published_at" />
            </div>

            <Input v-model="form.excerpt" label="Excerpt" placeholder="A short summary for the member portal dashboard and newsletters" :error="form.errors.excerpt" />

            <!-- Tags: chosen from the list an admin keeps under Settings -->
            <fieldset>
              <legend class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-slate-300">Tags</legend>
              <div v-if="tags.length" class="flex flex-wrap gap-2">
                <button
                  v-for="tag in tags"
                  :key="tag.id"
                  type="button"
                  :aria-pressed="form.tag_ids.includes(tag.id)"
                  :class="[
                    'rounded-full border-2 px-3 py-1.5 text-xs font-medium transition-colors',
                    form.tag_ids.includes(tag.id)
                      ? [orderColour(tag.color).soft, 'border-current']
                      : 'border-slate-200 text-slate-500 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400 dark:hover:border-slate-600'
                  ]"
                  @click="toggleTag(tag.id)"
                >
                  <span v-if="form.tag_ids.includes(tag.id)" aria-hidden="true">✓ </span>{{ tag.name }}
                </button>
              </div>
              <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                No tags exist yet. An admin can add them in
                <Link :href="`${route('admin.settings.show', { clubSlug: club.slug })}?tab=news-tags`" class="font-medium text-blue-600 hover:underline dark:text-blue-400">Settings</Link>.
              </p>
              <p v-if="form.errors.tag_ids || form.errors['tag_ids.0']" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ form.errors.tag_ids || form.errors['tag_ids.0'] }}</p>
            </fieldset>

            <!-- Cover image: a URL, an upload or a pick from the media library -->
            <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Cover image</h4>
                <div class="flex flex-wrap items-center gap-2">
                  <Button v-if="coverImagePreview || form.cover_image_url" variant="ghost" size="sm" @click="removeCoverImage">Remove image</Button>
                  <Button variant="secondary" size="sm" @click="openMediaLibrary('cover', null, 'news')">Choose from the media library</Button>
                </div>
              </div>

              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <Input v-model="form.cover_image_url" label="Image URL" placeholder="https://..." :error="form.errors.cover_image_url" />
                <div class="space-y-1.5">
                  <label for="post-cover-file" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Or upload an image</label>
                  <input id="post-cover-file" type="file" accept="image/*" :class="fileField" @change="onCoverImageFileSelect" />
                  <p v-if="form.errors.cover_image" class="text-xs text-rose-600 dark:text-rose-400">{{ form.errors.cover_image }}</p>
                </div>
              </div>

              <div v-if="coverImagePreview || form.cover_image_url" class="max-w-sm overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
                <img :src="coverImagePreview || form.cover_image_url" alt="Cover image preview" class="h-36 w-full object-cover" />
              </div>
            </div>
          </div>
        </Card>

        <Card>
          <template #header>
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Page content</h3>
            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Build the article from text blocks, single images, image galleries and callouts, in any order.</p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
              <Button v-for="item in blockTypes" :key="item.type" variant="secondary" size="sm" @click="addBlock(item.type)">Add {{ item.label.toLowerCase() }}</Button>
            </div>
          </template>

          <div class="space-y-3">
            <template v-for="(block, bIdx) in blocks" :key="block.id">
              <!-- Insert point above the first block -->
              <div v-if="bIdx === 0" class="insert-menu-container relative flex items-center justify-center py-1">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800"></div>
                </div>
                <div class="relative flex justify-center">
                  <button
                    type="button"
                    :aria-expanded="activeInsertIndex === 0"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:border-blue-300 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-700/60 dark:hover:text-white"
                    @click.stop="toggleInsertMenu(0)"
                  >
                    <span aria-hidden="true">+</span>
                    <span>Insert a block at the top</span>
                  </button>

                  <div
                    v-if="activeInsertIndex === 0"
                    class="absolute top-full z-30 mt-2 w-72 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                  >
                    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
                      <span>Insert a block here</span>
                      <button type="button" aria-label="Close" class="rounded-lg p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" @click="activeInsertIndex = null">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                      </button>
                    </div>

                    <button
                      v-for="item in blockTypes"
                      :key="item.type"
                      type="button"
                      class="block w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                      @click="addBlock(item.type, 0)"
                    >
                      <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ item.label }}</span>
                      <span class="block text-xs text-slate-500 dark:text-slate-400">{{ item.desc }}</span>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Block -->
              <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-800/50">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-100 px-4 py-2 dark:border-slate-800 dark:bg-slate-800">
                  <div class="flex items-center gap-2">
                    <span class="flex h-5 w-5 items-center justify-center rounded border border-slate-200 bg-white text-xs font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">{{ bIdx + 1 }}</span>
                    <Badge :variant="block.type === 'notice' ? 'warning' : 'info'">{{ blockLabel(block) }}</Badge>
                  </div>

                  <div class="flex flex-wrap items-center gap-1">
                    <Button variant="ghost" size="sm" :disabled="bIdx === 0" @click="moveBlockUp(bIdx)">Move <span class="sr-only">block {{ bIdx + 1 }}</span> up</Button>
                    <Button variant="ghost" size="sm" :disabled="bIdx === blocks.length - 1" @click="moveBlockDown(bIdx)">Move <span class="sr-only">block {{ bIdx + 1 }}</span> down</Button>
                    <Button variant="ghost" size="sm" :aria-label="`Duplicate block ${bIdx + 1}`" @click="duplicateBlock(bIdx)">Duplicate</Button>
                    <Button variant="ghost" size="sm" :aria-label="`Remove block ${bIdx + 1}`" @click="removeBlock(bIdx)">Remove</Button>
                  </div>
                </div>

                <div class="space-y-3 p-4">
                  <!-- Text -->
                  <div v-if="block.type === 'text'">
                    <RichTextEditor v-model="block.content" placeholder="Write the body of the article here" />
                  </div>

                  <!-- Single image -->
                  <div v-else-if="block.type === 'image'" class="space-y-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                      <Button v-if="block.url" variant="ghost" size="sm" @click="removeBlockImage(block)">Clear image</Button>
                      <Button variant="secondary" size="sm" @click="openMediaLibrary('block_image', block, 'images')">Choose from the media library</Button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                      <Input v-model="block.url" label="Image URL" placeholder="https://example.com/photo.jpg" />
                      <div class="space-y-1.5">
                        <label :for="`block-file-${block.id}`" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Or upload an image</label>
                        <input :id="`block-file-${block.id}`" type="file" accept="image/*" :class="fileField" @change="onBlockImageFileSelect($event, block)" />
                      </div>
                      <Input v-model="block.caption" label="Caption and alt text" placeholder="For example, the annual festive board" />
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <Select v-model="block.position" label="Position" :options="IMAGE_POSITIONS" />
                      <Select v-model="block.size" label="Size" :options="IMAGE_SIZES" />
                    </div>

                    <div v-if="block.url" class="pt-2">
                      <span class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Layout preview</span>
                      <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full']">
                        <div :class="[
                          'overflow-hidden rounded-xl border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900',
                          block.size === 'small' ? 'w-1/4' : block.size === 'medium' ? 'w-1/2' : block.size === 'large' ? 'w-3/4' : 'w-full'
                        ]">
                          <img :src="block.url" :alt="block.caption || ''" class="h-auto max-h-64 w-full rounded-lg object-cover" />
                          <p v-if="block.caption" class="mt-1 text-center text-xs italic text-slate-500 dark:text-slate-400">{{ block.caption }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Image gallery -->
                  <div v-else-if="block.type === 'images'" class="space-y-3">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <label :for="`gallery-columns-${block.id}`" class="text-xs font-medium text-slate-700 dark:text-slate-300">Columns</label>
                        <select :id="`gallery-columns-${block.id}`" v-model.number="block.columns" :class="[field, 'w-auto']">
                          <option :value="2">2 columns</option>
                          <option :value="3">3 columns</option>
                          <option :value="4">4 columns</option>
                        </select>
                      </div>
                      <Button variant="secondary" size="sm" @click="addGalleryImage(block)">Add an image</Button>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <div
                        v-for="(gItem, gIdx) in block.items"
                        :key="gIdx"
                        class="space-y-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900"
                      >
                        <div class="flex items-center justify-between gap-2">
                          <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Image {{ gIdx + 1 }}</span>
                          <div class="flex items-center gap-1">
                            <Button variant="secondary" size="sm" @click="openMediaLibrary('gallery_image', gItem, 'galleries')">Media library</Button>
                            <Button variant="ghost" size="sm" :aria-label="`Remove image ${gIdx + 1}`" @click="removeGalleryImage(block, gIdx)">Remove</Button>
                          </div>
                        </div>

                        <input v-model="gItem.url" type="text" placeholder="https://example.com/gallery-photo.jpg" :aria-label="`Image ${gIdx + 1} URL`" :class="field" />
                        <input type="file" accept="image/*" :aria-label="`Upload image ${gIdx + 1}`" :class="fileField" @change="onGalleryImageFileSelect($event, gItem)" />
                        <input v-model="gItem.caption" type="text" placeholder="Caption (optional)" :aria-label="`Image ${gIdx + 1} caption`" :class="field" />

                        <img v-if="gItem.url" :src="gItem.url" :alt="gItem.caption || ''" class="h-24 w-full rounded-lg border border-slate-200 object-cover dark:border-slate-800" />
                      </div>
                    </div>
                  </div>

                  <!-- Callout box -->
                  <div v-else-if="block.type === 'notice'" class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                      <Select v-model="block.style" label="Style" :options="NOTICE_STYLES" />
                      <Input v-model="block.title" label="Title" />
                    </div>
                    <Textarea v-model="block.text" label="Text" :rows="2" placeholder="Write the callout text here" />
                  </div>

                  <!-- Button link -->
                  <div v-else-if="block.type === 'button'" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Input v-model="block.label" label="Button label" placeholder="Read the full story" />
                    <Input v-model="block.url" label="Link to" placeholder="https://..." />
                    <Select v-model="block.align" label="Alignment" :options="BUTTON_ALIGNMENTS" />
                  </div>
                </div>
              </div>

              <!-- Insert point after this block -->
              <div class="insert-menu-container relative flex items-center justify-center py-1">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-dashed border-slate-200 dark:border-slate-800"></div>
                </div>
                <div class="relative flex justify-center">
                  <button
                    type="button"
                    :aria-expanded="activeInsertIndex === bIdx + 1"
                    class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-600 shadow-sm transition-colors hover:border-blue-300 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-blue-700/60 dark:hover:text-white"
                    @click.stop="toggleInsertMenu(bIdx + 1)"
                  >
                    <span aria-hidden="true">+</span>
                    <span>Add a block here</span>
                  </button>

                  <div
                    v-if="activeInsertIndex === bIdx + 1"
                    class="absolute top-full z-30 mt-2 w-72 space-y-1 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                  >
                    <div class="flex items-center justify-between border-b border-slate-100 px-3 py-1.5 text-xs font-medium text-slate-500 dark:border-slate-800 dark:text-slate-400">
                      <span>Insert a block here</span>
                      <button type="button" aria-label="Close" class="rounded-lg p-0.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300" @click="activeInsertIndex = null">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" /></svg>
                      </button>
                    </div>

                    <button
                      v-for="item in blockTypes"
                      :key="item.type"
                      type="button"
                      class="block w-full rounded-xl px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800/50"
                      @click="addBlock(item.type, bIdx + 1)"
                    >
                      <span class="block text-sm font-medium text-slate-900 dark:text-white">{{ item.label }}</span>
                      <span class="block text-xs text-slate-500 dark:text-slate-400">{{ item.desc }}</span>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </Card>

        <Card>
          <template #header>
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Files to download</h3>
                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Attach PDFs, agendas, minutes, spreadsheets or documents for members.</p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <Button variant="secondary" size="sm" @click="openMediaLibrary('attachment', null, 'documents')">Choose from the media library</Button>
                <Button variant="secondary" size="sm" @click="triggerFileInput">Attach files</Button>
              </div>
            </div>
          </template>

          <input
            ref="fileInput"
            type="file"
            multiple
            class="hidden"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.webp,.zip"
            aria-label="Attach files"
            @change="onFileSelect"
          />

          <div v-if="existingAttachments.length || newFiles.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div
              v-for="(att, idx) in existingAttachments"
              :key="'existing-' + idx"
              class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50"
            >
              <div class="flex min-w-0 items-center gap-2.5">
                <Badge>{{ fileKind(att.mime_type || att.name) }}</Badge>
                <div class="min-w-0">
                  <a :href="att.url" target="_blank" rel="noopener" class="block truncate text-sm font-medium text-slate-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ att.name }}</a>
                  <span class="text-xs text-slate-500 dark:text-slate-400">{{ att.size || 'Saved file' }}</span>
                </div>
              </div>
              <Button variant="ghost" size="sm" :aria-label="`Remove ${att.name}`" @click="removeExistingAttachment(idx)">Remove</Button>
            </div>

            <div
              v-for="(file, idx) in newFiles"
              :key="'new-' + idx"
              class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/50"
            >
              <div class="flex min-w-0 items-center gap-2.5">
                <Badge>{{ fileKind(file.name) }}</Badge>
                <div class="min-w-0">
                  <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ file.name }}</span>
                  <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatBytes(file.size) }}</span>
                  <Badge variant="info" class="ml-1">Uploads when you save</Badge>
                </div>
              </div>
              <Button variant="ghost" size="sm" :aria-label="`Remove ${file.name}`" @click="removeNewFile(idx)">Remove</Button>
            </div>
          </div>

          <button
            v-else
            type="button"
            class="block w-full rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-6 text-center transition-colors hover:border-blue-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-slate-800 dark:bg-slate-800/50 dark:hover:border-blue-700/60"
            @click="triggerFileInput"
          >
            <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Choose files to upload</span>
            <span class="block text-xs text-slate-500 dark:text-slate-400">PDFs, Word documents, Excel sheets, images or zip files, up to 10 MB each</span>
          </button>
        </Card>

        <Card>
          <template #header>
            <button
              type="button"
              :aria-expanded="showPublishSettings"
              class="flex w-full items-center justify-between text-left"
              @click="showPublishSettings = !showPublishSettings"
            >
              <span class="text-base font-semibold text-slate-900 dark:text-white">Publish settings</span>
              <svg :class="['h-5 w-5 text-slate-400 transition-transform', showPublishSettings ? 'rotate-180' : '']" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6" /></svg>
            </button>
          </template>

          <div v-show="showPublishSettings" class="space-y-4">
            <label class="flex items-start gap-3 text-sm">
              <input v-model="isPublished" type="checkbox" class="mt-0.5 h-4 w-4 rounded accent-blue-600" />
              <span>
                <span class="font-medium text-slate-900 dark:text-white">Publish</span>
                <span class="block text-slate-500 dark:text-slate-400">Make the news item live. Choose who can see it below.</span>
              </span>
            </label>

            <Select
              v-model="form.visibility"
              label="Who can see this"
              :options="visibilityOptions"
              :hint="visibilityHint"
              :error="form.errors.visibility"
            />

            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
              <Input
                v-model="form.published_at"
                type="datetime-local"
                label="Show from"
                hint="To keep the news item off the website until a certain date and time, set it here."
                :error="form.errors.published_at"
              />
              <Input
                v-model="form.expires_at"
                type="datetime-local"
                label="Show until"
                hint="To take the news item off the website after a certain date and time, set it here."
                :error="form.errors.expires_at"
              />
            </div>
          </div>
        </Card>

        <Card padding="sm">
          <div class="flex flex-wrap items-center gap-2">
            <Button :disabled="form.processing" @click="submitWithAction('save')">Save</Button>
            <Button variant="secondary" :disabled="form.processing" @click="submitWithAction('save_and_close')">Save and close</Button>
            <Button variant="secondary" :disabled="form.processing" @click="submitWithAction('save_and_new')">Save and new</Button>
            <Button variant="secondary" :disabled="form.processing" @click="submitWithAction('save_and_duplicate')">Save and duplicate</Button>
          </div>
        </Card>
      </form>
    </div>

    <Modal :open="showPreviewModal" title="Article preview" subtitle="How this post will look in the member portal." size="xl" @close="showPreviewModal = false">
      <article class="space-y-6 text-left">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
            <Badge variant="info">News bulletin</Badge>
            <span>{{ form.published_at ? 'Scheduled: ' + formatLocal(form.published_at) : 'Publishing immediately' }}</span>
          </div>

          <h1 class="text-2xl font-bold leading-tight text-slate-900 dark:text-white md:text-3xl">
            {{ form.title || 'Untitled news article' }}
          </h1>

          <p v-if="form.excerpt" class="rounded-r-xl border-l-4 border-blue-500 bg-slate-50 py-1 pl-3 text-sm font-medium italic text-slate-600 dark:bg-slate-800/50 dark:text-slate-300">
            {{ form.excerpt }}
          </p>
        </div>

        <div v-if="coverImagePreview || form.cover_image_url" class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-800">
          <img :src="coverImagePreview || form.cover_image_url" :alt="form.title" class="max-h-96 w-full object-cover" />
        </div>

        <div v-if="blocks && blocks.length" class="space-y-6">
          <div v-for="block in blocks" :key="block.id">
            <div
              v-if="block.type === 'text'"
              class="prose prose-slate max-w-none space-y-3 text-sm leading-relaxed text-slate-800 dark:text-slate-100"
              v-html="block.content || '<p class=\'text-slate-400 italic\'>[Empty text block]</p>'"
            ></div>

            <div
              v-else-if="block.type === 'image' && block.url"
              :class="[
                'my-4 flex',
                block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full'
              ]"
            >
              <figure :class="[
                'overflow-hidden rounded-xl border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900',
                block.size === 'small' ? 'w-full sm:w-1/4' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full'
              ]">
                <img :src="block.url" :alt="block.caption || form.title" class="h-auto max-h-[500px] w-full rounded-lg object-cover" />
                <figcaption v-if="block.caption" class="mt-2 p-1 text-center text-xs italic text-slate-500 dark:text-slate-400">
                  {{ block.caption }}
                </figcaption>
              </figure>
            </div>

            <div v-else-if="block.type === 'images' && block.items && block.items.length" class="my-6">
              <div :class="[
                'grid gap-3',
                block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3'
              ]">
                <figure
                  v-for="(gItem, gIdx) in block.items"
                  :key="gIdx"
                  class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 p-1 shadow-sm dark:border-slate-800 dark:bg-slate-800/50"
                >
                  <img v-if="gItem.url" :src="gItem.url" :alt="gItem.caption || ''" class="h-40 w-full rounded-lg object-cover" />
                  <figcaption v-if="gItem.caption" class="mt-1.5 p-1 text-center text-xs font-medium italic text-slate-600 dark:text-slate-300">
                    {{ gItem.caption }}
                  </figcaption>
                </figure>
              </div>
            </div>

            <Alert v-else-if="block.type === 'notice'" :variant="noticeVariant(block.style)" :title="block.title" class="my-4">
              {{ block.text }}
            </Alert>

            <div
              v-else-if="block.type === 'button' && block.url"
              :class="[
                'my-4 flex',
                block.align === 'left' ? 'justify-start' : block.align === 'right' ? 'justify-end' : 'justify-center'
              ]"
            >
              <a
                :href="block.url"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-1 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-500"
              >
                {{ block.label }}
              </a>
            </div>
          </div>
        </div>

        <div v-if="existingAttachments.length || newFiles.length" class="space-y-3 border-t border-slate-100 pt-6 dark:border-slate-800">
          <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
            Files to download ({{ existingAttachments.length + newFiles.length }})
          </h4>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div
              v-for="(att, aIdx) in existingAttachments"
              :key="'ex-' + aIdx"
              class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-800/50"
            >
              <Badge>{{ fileKind(att.mime_type || att.name) }}</Badge>
              <div class="min-w-0">
                <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ att.name }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ att.size || 'Download file' }}</span>
              </div>
            </div>

            <div
              v-for="(file, fIdx) in newFiles"
              :key="'nf-' + fIdx"
              class="flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 dark:border-slate-800 dark:bg-slate-800/50"
            >
              <Badge>{{ fileKind(file.name) }}</Badge>
              <div class="min-w-0">
                <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ file.name }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ formatBytes(file.size) }}, uploads when you save</span>
              </div>
            </div>
          </div>
        </div>
      </article>
    </Modal>

    <!-- Spatie Media Library Modal Component -->
    <MediaLibraryModal
      :show="showMediaModal"
      :club-slug="club.slug"
      :default-folder="mediaDefaultFolder"
      @close="showMediaModal = false"
      @select="onMediaSelect"
    />
  </AdminLayout>
</template>
