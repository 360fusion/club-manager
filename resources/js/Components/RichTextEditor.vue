<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write content here...',
  },
});

const emit = defineEmits(['update:modelValue']);

const showLinkModal = ref(false);
const linkUrl = ref('');

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    Link.configure({
      openOnClick: false,
      autolink: true,
      HTMLAttributes: {
        class: 'text-indigo-600 underline font-bold hover:text-indigo-800 transition-colors',
        target: '_blank',
        rel: 'noopener noreferrer',
      },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'focus:outline-none min-h-[180px] p-4 text-slate-800 text-xs leading-relaxed font-sans bg-slate-50 rounded-b-xl border border-t-0 border-slate-300',
    },
  },
  onUpdate: () => {
    emit('update:modelValue', editor.value?.getHTML() || '');
  },
});

watch(() => props.modelValue, (value) => {
  const isSame = editor.value?.getHTML() === value;
  if (isSame) {
    return;
  }
  editor.value?.commands.setContent(value || '', false);
});

onBeforeUnmount(() => {
  editor.value?.destroy();
});

const openLinkPrompt = () => {
  if (!editor.value) return;
  const previousUrl = editor.value.getAttributes('link').href || '';
  linkUrl.value = previousUrl;
  showLinkModal.value = true;
};

const applyLink = () => {
  if (!editor.value) return;
  
  if (!linkUrl.value.trim()) {
    editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
  } else {
    let url = linkUrl.value.trim();
    if (!/^https?:\/\//i.test(url) && !url.startsWith('/') && !url.startsWith('mailto:')) {
      url = 'https://' + url;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
  }
  showLinkModal.value = false;
  linkUrl.value = '';
};

const removeLink = () => {
  if (!editor.value) return;
  editor.value.chain().focus().unsetLink().run();
  showLinkModal.value = false;
  linkUrl.value = '';
};
</script>

<template>
  <div class="border border-slate-300 rounded-xl overflow-hidden bg-white shadow-sm relative">
    <!-- Formatting Toolbar -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-slate-100 border-b border-slate-300 text-xs font-semibold text-slate-700">
      
      <!-- Bold -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBold().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all font-bold cursor-pointer', editor.isActive('bold') ? 'bg-slate-300 text-slate-900' : '']"
        title="Bold (Ctrl+B)"
      >
        B
      </button>

      <!-- Italic -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleItalic().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all italic font-serif cursor-pointer', editor.isActive('italic') ? 'bg-slate-300 text-slate-900' : '']"
        title="Italic (Ctrl+I)"
      >
        I
      </button>

      <!-- Strike -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleStrike().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all line-through cursor-pointer', editor.isActive('strike') ? 'bg-slate-300 text-slate-900' : '']"
        title="Strikethrough"
      >
        S
      </button>

      <!-- Hyperlink -->
      <button 
        type="button"
        @click="openLinkPrompt"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all flex items-center gap-1 font-bold cursor-pointer', editor.isActive('link') ? 'bg-indigo-200 text-indigo-900 border border-indigo-300' : '']"
        title="Insert or Edit Hyperlink"
      >
        🔗 Link
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- H1 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-black cursor-pointer', editor.isActive('heading', { level: 1 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 1"
      >
        H1
      </button>

      <!-- H2 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-bold cursor-pointer', editor.isActive('heading', { level: 2 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 2"
      >
        H2
      </button>

      <!-- H3 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-semibold cursor-pointer', editor.isActive('heading', { level: 3 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 3"
      >
        H3
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- Bullet List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBulletList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all cursor-pointer', editor.isActive('bulletList') ? 'bg-slate-300 text-slate-900' : '']"
        title="Bulleted List"
      >
        • List
      </button>

      <!-- Numbered List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleOrderedList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all cursor-pointer', editor.isActive('orderedList') ? 'bg-slate-300 text-slate-900' : '']"
        title="Numbered List"
      >
        1. List
      </button>

      <!-- Blockquote -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBlockquote().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all font-serif cursor-pointer', editor.isActive('blockquote') ? 'bg-slate-300 text-slate-900' : '']"
        title="Quote Block"
      >
        “ Quote
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- Undo -->
      <button 
        type="button"
        @click="editor.chain().focus().undo().run()"
        :disabled="!editor.can().undo()"
        class="px-2 py-1 rounded hover:bg-slate-200 disabled:opacity-40 transition-all cursor-pointer"
        title="Undo"
      >
        ↩
      </button>

      <!-- Redo -->
      <button 
        type="button"
        @click="editor.chain().focus().redo().run()"
        :disabled="!editor.can().redo()"
        class="px-2 py-1 rounded hover:bg-slate-200 disabled:opacity-40 transition-all cursor-pointer"
        title="Redo"
      >
        ↪
      </button>
    </div>

    <!-- Inline Link Dialog Popup -->
    <div v-if="showLinkModal" class="p-3 bg-indigo-50/90 border-b border-indigo-200 flex flex-wrap items-center gap-2 text-xs">
      <span class="font-bold text-indigo-950">🔗 Enter URL:</span>
      <input 
        v-model="linkUrl" 
        type="text" 
        placeholder="https://example.com" 
        class="px-3 py-1.5 bg-white border border-indigo-300 rounded-lg text-xs text-slate-900 font-mono focus:outline-none focus:border-indigo-600 flex-1 min-w-[200px]" 
        @keyup.enter="applyLink"
      />
      <button 
        type="button" 
        @click="applyLink" 
        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm cursor-pointer"
      >
        Apply Link
      </button>
      <button 
        v-if="editor && editor.isActive('link')"
        type="button" 
        @click="removeLink" 
        class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-bold rounded-lg cursor-pointer"
      >
        Remove Link
      </button>
      <button 
        type="button" 
        @click="showLinkModal = false" 
        class="px-2.5 py-1.5 text-slate-500 hover:text-slate-700 font-bold cursor-pointer"
      >
        ✕
      </button>
    </div>

    <!-- Tiptap Editable Content Area -->
    <EditorContent :editor="editor" />
  </div>
</template>

<style>
/* Tiptap content styling inside editor */
.ProseMirror p {
  margin-bottom: 0.5rem;
}
.ProseMirror a {
  color: #4f46e5;
  text-decoration: underline;
  font-weight: 700;
}
.ProseMirror h1 {
  font-size: 1.25rem;
  font-weight: 800;
  margin-top: 0.75rem;
  margin-bottom: 0.5rem;
}
.ProseMirror h2 {
  font-size: 1.1rem;
  font-weight: 700;
  margin-top: 0.5rem;
  margin-bottom: 0.35rem;
}
.ProseMirror h3 {
  font-size: 0.95rem;
  font-weight: 700;
  margin-top: 0.5rem;
  margin-bottom: 0.25rem;
}
.ProseMirror ul {
  list-style-type: disc;
  padding-left: 1.25rem;
  margin-bottom: 0.5rem;
}
.ProseMirror ol {
  list-style-type: decimal;
  padding-left: 1.25rem;
  margin-bottom: 0.5rem;
}
.ProseMirror blockquote {
  border-left: 3px solid #cbd5e1;
  padding-left: 0.75rem;
  font-style: italic;
  color: #475569;
  margin-bottom: 0.5rem;
}
</style>
