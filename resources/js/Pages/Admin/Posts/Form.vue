<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

const props = defineProps({
  club: Object,
  post: Object,
});

const existingAttachments = ref([...(props.post.attachments || [])]);
const newFiles = ref([]);
const fileInput = ref(null);

const coverImageFile = ref(null);
const coverImagePreview = ref(props.post.cover_image_url || '');

const blocks = ref([...(props.post.blocks || [])]);

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
  published_at: props.post.published_at || '',
  expires_at: props.post.expires_at || '',
  action_type: 'save',
  blocks: blocks.value,
  existing_attachments: existingAttachments.value,
  new_attachments: [],
});

watch(isPublished, (val) => {
  form.status = val ? 'published' : 'draft';
});

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
  { type: 'text', icon: '📝', label: 'Text Block', desc: 'Rich text paragraph or formatted text', color: 'text-blue-600', bg: 'bg-blue-50' },
  { type: 'image', icon: '🖼️', label: 'Single Image', desc: 'Image upload with size & position controls', color: 'text-sky-600', bg: 'bg-sky-50' },
  { type: 'images', icon: '🖼️', label: 'Image Gallery', desc: 'Multi-image grid layout', color: 'text-purple-600', bg: 'bg-purple-50' },
  { type: 'notice', icon: '📢', label: 'Callout Box', desc: 'Highlighted notice or announcement box', color: 'text-amber-600', bg: 'bg-amber-50' },
  { type: 'button', icon: '🔗', label: 'Button Link', desc: 'Call to action button link', color: 'text-indigo-600', bg: 'bg-indigo-50' },
];

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
  <AdminLayout :title="`${post.id ? 'Edit' : 'Create'} Post`" :club="club" active-tab="posts">
    
    <div class="max-w-4xl mx-auto space-y-6">
      
      <!-- Top Action Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
        <div>
          <h2 class="text-xl font-bold text-slate-900">{{ post.id ? 'Edit News Post' : 'Create News Article' }}</h2>
          <p class="text-xs text-slate-500 mt-0.5">Build structured page elements, images, positioning, and downloadable files.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            @click="showPreviewModal = true"
            class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer"
          >
            👁️ Preview Article
          </button>
          <a
            v-if="post.id"
            :href="route('member.posts.show', { slug: club.slug, id: post.id })"
            target="_blank"
            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all flex items-center gap-1"
            title="Open live post in member portal"
          >
            ↗️ Live Link
          </a>
          <Link :href="route('admin.posts.index', { clubSlug: club.slug })" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 text-xs font-semibold rounded-xl transition-all">
            &larr; Back to Posts
          </Link>
        </div>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200/80 space-y-6">
        
        <!-- Header Metadata Section -->
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Article Title *</label>
              <input v-model="form.title" type="text" required placeholder="Summer Regatta Results & Trophy Ceremony" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 focus:outline-none focus:border-sky-500" />
            </div>

            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">URL Slug *</label>
              <input v-model="form.slug" type="text" required placeholder="summer-regatta-results" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono text-slate-900 focus:outline-none focus:border-sky-500" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">Teaser Excerpt</label>
            <input v-model="form.excerpt" type="text" placeholder="Short summary for member portal dashboard and newsletters..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 focus:outline-none focus:border-sky-500" />
          </div>

          <!-- Cover Image Upload & URL -->
          <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">🖼️ Article Banner / Cover Image</label>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Image URL</label>
                <input v-model="form.cover_image_url" type="text" placeholder="https://..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl text-xs font-mono" />
              </div>

              <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Or Upload Image File</label>
                <input type="file" accept="image/*" @change="onCoverImageFileSelect" class="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 cursor-pointer" />
              </div>
            </div>

            <div v-if="coverImagePreview || form.cover_image_url" class="relative max-w-sm rounded-xl overflow-hidden border border-slate-200 mt-2">
              <img :src="coverImagePreview || form.cover_image_url" class="w-full h-36 object-cover" />
            </div>
          </div>
        </div>

        <!-- Modular Page Element Builder -->
        <div class="space-y-4 pt-4 border-t border-slate-200">
          <div class="space-y-3">
            <div>
              <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                <span>🧩</span> Page Elements & Content Builder
              </h3>
              <p class="text-xs text-slate-500 mt-0.5">Construct article pages with reorderable text blocks, single images, multi-image galleries, and notice callouts.</p>
            </div>

            <!-- Add Element Toolbar Buttons (Listed Under Text) -->
            <div class="flex flex-wrap items-center gap-2 pt-1">
              <button type="button" @click="addBlock('text')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-300 transition-all cursor-pointer">
                + Text Block
              </button>
              <button type="button" @click="addBlock('image')" class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-xl border border-sky-200 transition-all cursor-pointer">
                + Image
              </button>
              <button type="button" @click="addBlock('images')" class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 text-xs font-bold rounded-xl border border-purple-200 transition-all cursor-pointer">
                + Image Gallery
              </button>
              <button type="button" @click="addBlock('notice')" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-bold rounded-xl border border-amber-200 transition-all cursor-pointer">
                + Callout Box
              </button>
              <button type="button" @click="addBlock('button')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200 transition-all cursor-pointer">
                + Button Link
              </button>
            </div>
          </div>

          <!-- Element Stack List -->
          <div class="space-y-3">
            <template v-for="(block, bIdx) in blocks" :key="block.id">
              
              <!-- Insert Divider Above First Block (Index 0) -->
              <div v-if="bIdx === 0" class="relative py-1 flex items-center justify-center insert-menu-container">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-dashed border-slate-200 hover:border-slate-300 transition-colors"></div>
                </div>
                <div class="relative flex justify-center">
                  <button
                    type="button"
                    @click.stop="toggleInsertMenu(0)"
                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 border border-slate-200 hover:border-sky-300 rounded-full text-xs font-bold shadow-sm transition-all cursor-pointer hover:scale-105"
                  >
                    <span class="w-4 h-4 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-xs font-black">+</span>
                    <span>Insert block at top</span>
                  </button>

                  <!-- Insert Menu Dropdown Popover -->
                  <div
                    v-if="activeInsertIndex === 0"
                    class="absolute top-full mt-2 z-30 w-72 bg-white rounded-2xl shadow-xl border border-slate-200 p-2 space-y-1 animate-in fade-in zoom-in-95 duration-100"
                  >
                    <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 flex items-center justify-between">
                      <span>Insert Element Here</span>
                      <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 text-xs cursor-pointer">✕</button>
                    </div>

                    <button
                      v-for="item in blockTypes"
                      :key="item.type"
                      type="button"
                      @click="addBlock(item.type, 0)"
                      class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-xl transition-all cursor-pointer group"
                    >
                      <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-black transition-transform group-hover:scale-110" :class="[item.bg, item.color]">
                        {{ item.icon }}
                      </span>
                      <div>
                        <span class="block font-bold text-slate-900 group-hover:text-sky-600 transition-colors">{{ item.label }}</span>
                        <span class="text-[10px] text-slate-400 font-normal leading-tight block">{{ item.desc }}</span>
                      </div>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Block Item Card -->
              <div class="bg-slate-50 rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden transition-all">
                <!-- Block Header & Controls -->
                <div class="flex items-center justify-between px-4 py-2.5 bg-slate-100/90 border-b border-slate-200 text-xs font-bold text-slate-700">
                  <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded bg-white text-slate-600 flex items-center justify-center text-[11px] font-black border border-slate-200">
                      {{ bIdx + 1 }}
                    </span>

                    <!-- Type Badge -->
                    <span v-if="block.type === 'text'" class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 text-[10px] uppercase font-bold">
                      📝 Text Block
                    </span>
                    <span v-else-if="block.type === 'image'" class="px-2 py-0.5 rounded bg-sky-50 text-sky-700 border border-sky-200 text-[10px] uppercase font-bold">
                      🖼️ Single Image
                    </span>
                    <span v-else-if="block.type === 'images'" class="px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 text-[10px] uppercase font-bold">
                      🖼️ Image Gallery ({{ block.columns }} Cols)
                    </span>
                    <span v-else-if="block.type === 'notice'" class="px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 text-[10px] uppercase font-bold">
                      📢 Callout Box
                    </span>
                    <span v-else-if="block.type === 'button'" class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] uppercase font-bold">
                      🔗 Button Link
                    </span>
                  </div>

                  <!-- Control Buttons (Up, Down, Duplicate, Delete) -->
                  <div class="flex items-center gap-1">
                    <button
                      type="button"
                      @click="moveBlockUp(bIdx)"
                      :disabled="bIdx === 0"
                      class="p-1 text-slate-500 hover:text-slate-900 disabled:opacity-30 cursor-pointer"
                      title="Move Up"
                    >
                      ▲
                    </button>
                    <button
                      type="button"
                      @click="moveBlockDown(bIdx)"
                      :disabled="bIdx === blocks.length - 1"
                      class="p-1 text-slate-500 hover:text-slate-900 disabled:opacity-30 cursor-pointer"
                      title="Move Down"
                    >
                      ▼
                    </button>
                    <button
                      type="button"
                      @click="duplicateBlock(bIdx)"
                      class="p-1 text-slate-500 hover:text-indigo-600 cursor-pointer"
                      title="Duplicate Element"
                    >
                      📋
                    </button>
                    <button
                      type="button"
                      @click="removeBlock(bIdx)"
                      class="p-1 text-slate-400 hover:text-rose-600 cursor-pointer"
                      title="Delete Element"
                    >
                      🗑️
                    </button>
                  </div>
                </div>

                <!-- Block Body Editors -->
                <div class="p-4 space-y-3">
                  
                  <!-- 1. Text Block -->
                  <div v-if="block.type === 'text'">
                    <RichTextEditor v-model="block.content" placeholder="Write rich body content here..." />
                  </div>

                  <!-- 2. Single Image Block with Position & Sizing Options -->
                  <div v-else-if="block.type === 'image'" class="space-y-3 text-xs">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <div class="col-span-1 md:col-span-1">
                        <label class="block font-bold text-slate-700 mb-1">Image URL</label>
                        <input v-model="block.url" type="text" placeholder="https://example.com/photo.jpg" class="w-full p-2 bg-white border border-slate-300 rounded-xl font-mono text-[11px]" />
                      </div>

                      <div class="col-span-1 md:col-span-1">
                        <label class="block font-bold text-slate-700 mb-1">Or Upload Image File</label>
                        <input
                          type="file"
                          accept="image/*"
                          @change="onBlockImageFileSelect($event, block)"
                          class="w-full text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 cursor-pointer"
                        />
                      </div>

                      <div class="col-span-1 md:col-span-1">
                        <label class="block font-bold text-slate-700 mb-1">Caption / Alt Text</label>
                        <input v-model="block.caption" type="text" placeholder="e.g., Award ceremony at Oxford Boating Club" class="w-full p-2 bg-white border border-slate-300 rounded-xl" />
                      </div>
                    </div>

                    <!-- Positioning & Sizing Controls -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-white rounded-xl border border-slate-200">
                      <div>
                        <label class="block font-bold text-slate-700 mb-1">Image Positioning</label>
                        <select v-model="block.position" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                          <option value="left">Left Aligned</option>
                          <option value="center">Centered</option>
                          <option value="right">Right Aligned</option>
                          <option value="full">Full Width Banner</option>
                        </select>
                      </div>

                      <div>
                        <label class="block font-bold text-slate-700 mb-1">Image Sizing</label>
                        <select v-model="block.size" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-xl font-semibold">
                          <option value="small">Small (25% Width)</option>
                          <option value="medium">Medium (50% Width)</option>
                          <option value="large">Large (75% Width)</option>
                          <option value="full">Full Container Width (100%)</option>
                        </select>
                      </div>
                    </div>

                    <!-- Live Thumbnail Preview -->
                    <div v-if="block.url" class="pt-2">
                      <span class="text-[10px] font-bold text-slate-400 block mb-1">Preview Layout:</span>
                      <div :class="['flex', block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full']">
                        <div :class="[
                          'rounded-xl overflow-hidden border border-slate-200 bg-white p-1',
                          block.size === 'small' ? 'w-1/4' : block.size === 'medium' ? 'w-1/2' : block.size === 'large' ? 'w-3/4' : 'w-full'
                        ]">
                          <img :src="block.url" class="w-full h-auto max-h-64 object-cover rounded-lg" />
                          <p v-if="block.caption" class="text-[11px] text-center text-slate-500 italic mt-1">{{ block.caption }}</p>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- 3. Image Gallery Block (Multi-Images) -->
                  <div v-else-if="block.type === 'images'" class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <label class="font-bold text-slate-700">Grid Layout Columns:</label>
                        <select v-model.number="block.columns" class="p-1.5 bg-white border border-slate-300 rounded-lg font-bold">
                          <option :value="2">2 Columns</option>
                          <option :value="3">3 Columns</option>
                          <option :value="4">4 Columns</option>
                        </select>
                      </div>

                      <button
                        type="button"
                        @click="addGalleryImage(block)"
                        class="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 font-bold rounded-lg border border-purple-200 transition-all cursor-pointer"
                      >
                        + Add Image to Gallery
                      </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div
                        v-for="(gItem, gIdx) in block.items"
                        :key="gIdx"
                        class="p-3 bg-white rounded-xl border border-slate-200 space-y-2 relative"
                      >
                        <div class="flex items-center justify-between">
                          <span class="font-bold text-slate-600 text-[11px]">Image {{ gIdx + 1 }}</span>
                          <button
                            type="button"
                            @click="removeGalleryImage(block, gIdx)"
                            class="text-slate-400 hover:text-rose-600 font-bold p-0.5"
                          >
                            ✕
                          </button>
                        </div>

                        <div class="space-y-1">
                          <input v-model="gItem.url" type="text" placeholder="https://example.com/gallery-photo.jpg" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg font-mono text-[11px]" />
                          <input
                            type="file"
                            accept="image/*"
                            @change="onGalleryImageFileSelect($event, gItem)"
                            class="w-full text-[10px] text-slate-600 file:mr-2 file:py-0.5 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-bold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100 cursor-pointer"
                          />
                        </div>

                        <input v-model="gItem.caption" type="text" placeholder="Caption (optional)" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-[11px]" />

                        <img v-if="gItem.url" :src="gItem.url" class="w-full h-24 object-cover rounded-lg border border-slate-200" />
                      </div>
                    </div>
                  </div>

                  <!-- 4. Notice Callout Box -->
                  <div v-else-if="block.type === 'notice'" class="space-y-3 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div>
                        <label class="block font-bold text-slate-700 mb-1">Callout Style</label>
                        <select v-model="block.style" class="w-full p-2 bg-white border border-slate-300 rounded-xl font-bold">
                          <option value="info">💡 Info / Announcement (Blue)</option>
                          <option value="warning">⚠️ Warning / Reminder (Amber)</option>
                          <option value="important">🚨 Important / Bylaws (Purple)</option>
                          <option value="success">✅ Success / Milestone (Emerald)</option>
                        </select>
                      </div>

                      <div>
                        <label class="block font-bold text-slate-700 mb-1">Callout Title</label>
                        <input v-model="block.title" type="text" class="w-full p-2 bg-white border border-slate-300 rounded-xl font-bold" />
                      </div>
                    </div>

                    <div>
                      <label class="block font-bold text-slate-700 mb-1">Callout Text Body</label>
                      <textarea v-model="block.text" rows="2" placeholder="Write notice callout text here..." class="w-full p-2 bg-white border border-slate-300 rounded-xl"></textarea>
                    </div>
                  </div>

                  <!-- 5. Button Link Block -->
                  <div v-else-if="block.type === 'button'" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                      <label class="block font-bold text-slate-700 mb-1">Button Label *</label>
                      <input v-model="block.label" type="text" placeholder="Read Full Story →" class="w-full p-2 bg-white border border-slate-300 rounded-xl font-bold" />
                    </div>

                    <div>
                      <label class="block font-bold text-slate-700 mb-1">Target URL *</label>
                      <input v-model="block.url" type="text" placeholder="https://..." class="w-full p-2 bg-white border border-slate-300 rounded-xl font-mono text-[11px]" />
                    </div>

                    <div>
                      <label class="block font-bold text-slate-700 mb-1">Alignment</label>
                      <select v-model="block.align" class="w-full p-2 bg-white border border-slate-300 rounded-xl font-semibold">
                        <option value="left">Left Aligned</option>
                        <option value="center">Center Aligned</option>
                        <option value="right">Right Aligned</option>
                      </select>
                    </div>
                  </div>

                </div>
              </div>

              <!-- Insert Divider Between / After Blocks (Index bIdx + 1) -->
              <div class="relative py-1 flex items-center justify-center insert-menu-container">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-dashed border-slate-200 hover:border-slate-300 transition-colors"></div>
                </div>
                <div class="relative flex justify-center">
                  <button
                    type="button"
                    @click.stop="toggleInsertMenu(bIdx + 1)"
                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 border border-slate-200 hover:border-sky-300 rounded-full text-xs font-bold shadow-sm transition-all cursor-pointer hover:scale-105"
                  >
                    <span class="w-4 h-4 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-xs font-black">+</span>
                    <span>Add block here</span>
                  </button>

                  <!-- Insert Menu Dropdown Popover -->
                  <div
                    v-if="activeInsertIndex === bIdx + 1"
                    class="absolute top-full mt-2 z-30 w-72 bg-white rounded-2xl shadow-xl border border-slate-200 p-2 space-y-1 animate-in fade-in zoom-in-95 duration-100"
                  >
                    <div class="px-3 py-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100 flex items-center justify-between">
                      <span>Insert Element Here</span>
                      <button type="button" @click="activeInsertIndex = null" class="text-slate-400 hover:text-slate-600 text-xs cursor-pointer">✕</button>
                    </div>

                    <button
                      v-for="item in blockTypes"
                      :key="item.type"
                      type="button"
                      @click="addBlock(item.type, bIdx + 1)"
                      class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:bg-slate-50 rounded-xl transition-all cursor-pointer group"
                    >
                      <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm font-black transition-transform group-hover:scale-110" :class="[item.bg, item.color]">
                        {{ item.icon }}
                      </span>
                      <div>
                        <span class="block font-bold text-slate-900 group-hover:text-sky-600 transition-colors">{{ item.label }}</span>
                        <span class="text-[10px] text-slate-400 font-normal leading-tight block">{{ item.desc }}</span>
                      </div>
                    </button>
                  </div>
                </div>
              </div>

            </template>
          </div>
        </div>

        <!-- Downloadable Attachments Section -->
        <div class="space-y-3 pt-4 border-t border-slate-200">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider">📎 Downloadable Files & Documents</label>
              <p class="text-[11px] text-slate-500">Attach PDFs, agendas, meeting minutes, spreadsheets, or documents for members.</p>
            </div>
            <button
              type="button"
              @click="triggerFileInput"
              class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 text-xs font-bold rounded-xl border border-sky-200 transition-all flex items-center gap-1 cursor-pointer"
            >
              + Attach Files
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
                  <a :href="att.url" target="_blank" class="font-bold text-slate-900 hover:text-sky-600 truncate block">
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
              class="flex items-center justify-between bg-sky-50/50 p-3 rounded-xl border border-sky-200/80 text-xs"
            >
              <div class="flex items-center gap-2.5 overflow-hidden">
                <span class="text-lg">{{ getFileIcon(file.name) }}</span>
                <div class="truncate">
                  <span class="font-bold text-sky-900 truncate block">{{ file.name }}</span>
                  <span class="text-[10px] text-sky-600 font-semibold">{{ formatBytes(file.size) }} (Pending upload)</span>
                </div>
              </div>
              <button
                type="button"
                @click="removeNewFile(idx)"
                class="text-sky-400 hover:text-rose-600 font-bold p-1 rounded transition-colors cursor-pointer"
                title="Remove file"
              >
                ✕
              </button>
            </div>

          </div>

          <div v-else @click="triggerFileInput" class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-sky-300 transition-colors cursor-pointer bg-slate-50/50">
            <span class="text-2xl block mb-1">📁</span>
            <span class="text-xs font-bold text-slate-700 block">Click to upload downloadable files</span>
            <span class="text-[11px] text-slate-400">PDFs, Word Documents, Excel sheets, Images, or Zip files (up to 10MB per file)</span>
          </div>
        </div>

        <!-- Publish Settings Section -->
        <div class="pt-4 border-t border-slate-200">
          <!-- Accordion Header -->
          <button
            type="button"
            @click="showPublishSettings = !showPublishSettings"
            class="w-full flex items-center justify-between text-left text-xs font-semibold text-slate-500 hover:text-slate-800 transition-colors cursor-pointer py-1"
          >
            <span class="flex items-center gap-1.5">
              <span class="text-[10px] transform transition-transform duration-200" :class="{ 'rotate-180': !showPublishSettings }">▼</span>
              <span class="text-xs font-medium text-slate-700">Publish settings</span>
            </span>
          </button>

          <!-- Accordion Content -->
          <div v-show="showPublishSettings" class="mt-4 space-y-4 text-xs">
            <!-- Publish Item Checkbox -->
            <div class="space-y-0.5">
              <label class="inline-flex items-center gap-2 font-bold text-slate-800 cursor-pointer">
                <input
                  type="checkbox"
                  v-model="isPublished"
                  class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
                />
                <span>Publish item</span>
              </label>
              <p class="text-[11px] text-slate-500 pl-6">
                Make the news item publicly visible on the website.
              </p>
            </div>

            <!-- Date Range Controls Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-1">
              <!-- Show From -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Show from</label>
                <div class="relative">
                  <input
                    v-model="form.published_at"
                    type="datetime-local"
                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
                  />
                </div>
                <p class="text-[11px] text-slate-500 mt-1 leading-normal">
                  If you want to prevent the news item from showing on the website before a certain date/time, you can specify it here.
                </p>
              </div>

              <!-- Show Until -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Show until</label>
                <div class="relative">
                  <input
                    v-model="form.expires_at"
                    type="datetime-local"
                    class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-mono text-slate-900 focus:outline-none focus:border-sky-500 shadow-sm"
                  />
                </div>
                <p class="text-[11px] text-slate-500 mt-1 leading-normal">
                  If you want to prevent the news item from showing on the website after a certain date/time, you can specify it here.
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Action Buttons Bar (Matching User's Screenshot) -->
        <div class="bg-slate-100/90 -mx-6 -mb-6 p-4 rounded-b-2xl border-t border-slate-200/90 flex flex-wrap items-center gap-2 md:gap-2.5">
          <button
            type="button"
            @click="submitWithAction('save')"
            :disabled="form.processing"
            class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 shadow-sm transition-all cursor-pointer disabled:opacity-50"
          >
            Save
          </button>
          <button
            type="button"
            @click="submitWithAction('save_and_close')"
            :disabled="form.processing"
            class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 shadow-sm transition-all cursor-pointer disabled:opacity-50"
          >
            Save and close
          </button>
          <button
            type="button"
            @click="submitWithAction('save_and_new')"
            :disabled="form.processing"
            class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 shadow-sm transition-all cursor-pointer disabled:opacity-50"
          >
            Save and new
          </button>
          <button
            type="button"
            @click="submitWithAction('save_and_duplicate')"
            :disabled="form.processing"
            class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 shadow-sm transition-all cursor-pointer disabled:opacity-50"
          >
            Save and duplicate
          </button>
        </div>

      </form>

    </div>

    <!-- Live Preview Modal -->
    <div v-if="showPreviewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 overflow-y-auto">
      <div class="bg-slate-100 rounded-3xl max-w-3xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-200 space-y-4 p-6 relative">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <span class="text-lg">👁️</span>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Member Portal Article Preview</h3>
              <p class="text-[11px] text-slate-500">Live preview of how this post will render in the member portal.</p>
            </div>
          </div>
          <button
            type="button"
            @click="showPreviewModal = false"
            class="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl transition-colors cursor-pointer text-xs"
          >
            ✕ Close Preview
          </button>
        </div>

        <!-- Preview Article Card -->
        <article class="bg-white rounded-2xl p-6 md:p-8 shadow-sm border border-slate-200/80 space-y-6 text-left">
          <!-- Header -->
          <div class="space-y-3">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
              <span class="px-2.5 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-bold border border-indigo-100 uppercase tracking-wider text-[10px]">
                📰 News Bulletin
              </span>
              <span>•</span>
              <span>{{ form.published_at ? 'Scheduled: ' + form.published_at : 'Publishing Immediately' }}</span>
            </div>

            <h1 class="text-2xl md:text-3xl font-black text-slate-900 leading-tight">
              {{ form.title || 'Untitled News Article' }}
            </h1>

            <p v-if="form.excerpt" class="text-sm font-medium text-slate-600 italic border-l-4 border-indigo-500 pl-3 py-1 bg-slate-50 rounded-r-xl">
              {{ form.excerpt }}
            </p>
          </div>

          <!-- Cover Image -->
          <div v-if="coverImagePreview || form.cover_image_url" class="rounded-xl overflow-hidden border border-slate-200">
            <img :src="coverImagePreview || form.cover_image_url" :alt="form.title" class="w-full max-h-96 object-cover" />
          </div>

          <!-- Structured Page Blocks -->
          <div v-if="blocks && blocks.length" class="space-y-6">
            <div v-for="block in blocks" :key="block.id">
              
              <!-- 1. Text Block -->
              <div
                v-if="block.type === 'text'"
                class="prose prose-slate max-w-none text-sm leading-relaxed text-slate-800 space-y-3"
                v-html="block.content || '<p class=\'text-slate-400 italic\'>[Empty text block]</p>'"
              ></div>

              <!-- 2. Single Image -->
              <div
                v-else-if="block.type === 'image' && block.url"
                :class="[
                  'my-4 flex',
                  block.position === 'left' ? 'justify-start' : block.position === 'right' ? 'justify-end' : block.position === 'center' ? 'justify-center' : 'w-full'
                ]"
              >
                <figure :class="[
                  'rounded-xl overflow-hidden border border-slate-200 bg-white p-1 shadow-sm',
                  block.size === 'small' ? 'w-full sm:w-1/4' : block.size === 'medium' ? 'w-full sm:w-1/2' : block.size === 'large' ? 'w-full sm:w-3/4' : 'w-full'
                ]">
                  <img :src="block.url" :alt="block.caption || form.title" class="w-full h-auto max-h-[500px] object-cover rounded-lg" />
                  <figcaption v-if="block.caption" class="text-xs text-center text-slate-500 italic mt-2 p-1">
                    {{ block.caption }}
                  </figcaption>
                </figure>
              </div>

              <!-- 3. Image Gallery -->
              <div v-else-if="block.type === 'images' && block.items && block.items.length" class="my-6">
                <div :class="[
                  'grid gap-3',
                  block.columns === 2 ? 'grid-cols-1 sm:grid-cols-2' : block.columns === 4 ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3'
                ]">
                  <figure
                    v-for="(gItem, gIdx) in block.items"
                    :key="gIdx"
                    class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50 p-1 shadow-sm"
                  >
                    <img v-if="gItem.url" :src="gItem.url" :alt="gItem.caption || ''" class="w-full h-40 object-cover rounded-lg" />
                    <figcaption v-if="gItem.caption" class="text-[11px] text-center text-slate-600 font-medium italic mt-1.5 p-1">
                      {{ gItem.caption }}
                    </figcaption>
                  </figure>
                </div>
              </div>

              <!-- 4. Notice Callout Box -->
              <div
                v-else-if="block.type === 'notice'"
                :class="[
                  'p-4 rounded-xl border text-xs space-y-1 my-4',
                  block.style === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-900' :
                  block.style === 'important' ? 'bg-purple-50 border-purple-200 text-purple-900' :
                  block.style === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' :
                  'bg-blue-50 border-blue-200 text-blue-900'
                ]"
              >
                <h4 class="font-bold text-sm flex items-center gap-1.5">
                  <span>{{ block.style === 'warning' ? '⚠️' : block.style === 'important' ? '🚨' : block.style === 'success' ? '✅' : '💡' }}</span>
                  {{ block.title }}
                </h4>
                <p class="leading-relaxed font-medium">{{ block.text }}</p>
              </div>

              <!-- 5. Button Link -->
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
                  class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md transition-all inline-flex items-center gap-1"
                >
                  {{ block.label }}
                </a>
              </div>

            </div>
          </div>

          <!-- Downloadable Attachments -->
          <div v-if="existingAttachments.length || newFiles.length" class="pt-6 border-t border-slate-100 space-y-3">
            <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
              <span>📎</span> Downloadable Files & Documents ({{ existingAttachments.length + newFiles.length }})
            </h4>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div
                v-for="(att, aIdx) in existingAttachments"
                :key="'ex-' + aIdx"
                class="flex items-center justify-between p-3.5 bg-slate-50 rounded-xl border border-slate-200 text-xs"
              >
                <div class="flex items-center gap-3 overflow-hidden">
                  <span class="text-xl">{{ getFileIcon(att.mime_type || att.name) }}</span>
                  <div class="truncate">
                    <span class="font-bold text-slate-900 truncate block">{{ att.name }}</span>
                    <span class="text-[10px] text-slate-500 font-semibold">{{ att.size || 'Download File' }}</span>
                  </div>
                </div>
              </div>

              <div
                v-for="(file, fIdx) in newFiles"
                :key="'nf-' + fIdx"
                class="flex items-center justify-between p-3.5 bg-sky-50 rounded-xl border border-sky-200 text-xs"
              >
                <div class="flex items-center gap-3 overflow-hidden">
                  <span class="text-xl">{{ getFileIcon(file.name) }}</span>
                  <div class="truncate">
                    <span class="font-bold text-sky-900 truncate block">{{ file.name }}</span>
                    <span class="text-[10px] text-sky-600 font-semibold">{{ formatBytes(file.size) }} (Pending upload)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </div>

  </AdminLayout>
</template>
