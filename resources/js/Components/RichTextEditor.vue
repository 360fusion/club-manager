<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { FontSize, FONT_SIZES } from '@/Utils/tiptapFontSize';

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: 'Write content here...',
  },
  // Id of an external <label>/heading this editor is the field for. Threaded onto the actual
  // contenteditable element (not just this component's root) so it reaches Tiptap's own div.
  ariaLabelledby: {
    type: String,
    default: null,
  },
});

const emit = defineEmits(['update:modelValue']);

const showLinkModal = ref(false);
const linkUrl = ref('');

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
    FontSize,
    Link.configure({
      openOnClick: false,
      autolink: true,
      HTMLAttributes: {
        class: 'text-blue-600 dark:text-blue-400 underline font-bold hover:text-blue-800 dark:hover:text-blue-200 transition-colors',
        target: '_blank',
        rel: 'noopener noreferrer',
      },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'focus:outline-none min-h-[180px] p-4 text-slate-800 dark:text-slate-100 text-xs leading-relaxed font-sans bg-slate-50 dark:bg-slate-800/50 rounded-b-xl border border-t-0 border-slate-300 dark:border-slate-700',
      role: 'textbox',
      'aria-multiline': 'true',
      ...(props.ariaLabelledby ? { 'aria-labelledby': props.ariaLabelledby } : {}),
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

const setSize = (size) => {
  if (!editor.value) return;
  if (size) {
    editor.value.chain().focus().setFontSize(size).run();
  } else {
    editor.value.chain().focus().unsetFontSize().run();
  }
};

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
  <div class="border border-slate-300 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-slate-900 shadow-sm relative">
    <!-- Formatting Toolbar -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-slate-100 dark:bg-slate-800 border-b border-slate-300 dark:border-slate-700 text-xs font-semibold text-slate-700 dark:text-slate-200">
      
      <!-- Bold -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBold().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all font-bold cursor-pointer', editor.isActive('bold') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Bold (Ctrl+B)"
      >
        B
      </button>

      <!-- Italic -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleItalic().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all italic font-serif cursor-pointer', editor.isActive('italic') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Italic (Ctrl+I)"
      >
        I
      </button>

      <!-- Strike -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleStrike().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all line-through cursor-pointer', editor.isActive('strike') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Strikethrough"
      >
        S
      </button>

      <!-- Hyperlink -->
      <button 
        type="button"
        @click="openLinkPrompt"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center gap-1 font-bold cursor-pointer', editor.isActive('link') ? 'bg-blue-200 dark:bg-blue-900/60 text-blue-900 dark:text-blue-200 border border-blue-300 dark:border-blue-700/60' : '']"
        title="Insert or Edit Hyperlink"
      >
        🔗 Link
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- H1 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all text-xs font-black cursor-pointer', editor.isActive('heading', { level: 1 }) ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Heading 1"
      >
        H1
      </button>

      <!-- H2 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all text-xs font-bold cursor-pointer', editor.isActive('heading', { level: 2 }) ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Heading 2"
      >
        H2
      </button>

      <!-- H3 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all text-xs font-semibold cursor-pointer', editor.isActive('heading', { level: 3 }) ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Heading 3"
      >
        H3
      </button>

      <!-- Text size -->
      <select
        :value="editor.getAttributes('fontSize').size || ''"
        class="rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-1.5 py-1 text-xs font-semibold cursor-pointer"
        title="Text size (select some text first)"
        aria-label="Text size"
        @change="setSize($event.target.value)"
      >
        <option value="">Normal size</option>
        <option v-for="size in FONT_SIZES" :key="size.id" :value="size.id">{{ size.label }}</option>
      </select>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- Bullet List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBulletList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer', editor.isActive('bulletList') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Bulleted List"
      >
        • List
      </button>

      <!-- Numbered List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleOrderedList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all cursor-pointer', editor.isActive('orderedList') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
        title="Numbered List"
      >
        1. List
      </button>

      <!-- Blockquote -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBlockquote().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-all font-serif cursor-pointer', editor.isActive('blockquote') ? 'bg-slate-300 text-slate-900 dark:text-white' : '']"
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
        class="px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-40 transition-all cursor-pointer"
        title="Undo"
      >
        ↩
      </button>

      <!-- Redo -->
      <button 
        type="button"
        @click="editor.chain().focus().redo().run()"
        :disabled="!editor.can().redo()"
        class="px-2 py-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-40 transition-all cursor-pointer"
        title="Redo"
      >
        ↪
      </button>
    </div>

    <!-- Inline Link Dialog Popup -->
    <div v-if="showLinkModal" class="p-3 bg-blue-50/90 dark:bg-blue-950/90 border-b border-blue-200 dark:border-blue-800/60 flex flex-wrap items-center gap-2 text-xs">
      <span class="font-bold text-blue-950 dark:text-blue-100">🔗 Enter URL:</span>
      <input 
        v-model="linkUrl" 
        type="text" 
        placeholder="https://example.com" 
        class="px-3 py-1.5 bg-white dark:bg-slate-900 border border-blue-300 dark:border-blue-700/60 rounded-lg text-xs text-slate-900 dark:text-white font-mono focus:outline-none focus:border-blue-600 flex-1 min-w-[200px]" 
        @keyup.enter="applyLink"
      />
      <button 
        type="button" 
        @click="applyLink" 
        class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg shadow-sm cursor-pointer"
      >
        Apply Link
      </button>
      <button 
        v-if="editor && editor.isActive('link')"
        type="button" 
        @click="removeLink" 
        class="px-3 py-1.5 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 font-bold rounded-lg cursor-pointer"
      >
        Remove Link
      </button>
      <button 
        type="button" 
        @click="showLinkModal = false" 
        class="px-2.5 py-1.5 text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 font-bold cursor-pointer"
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
