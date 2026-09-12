<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';

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

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit,
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
</script>

<template>
  <div class="border border-slate-300 rounded-xl overflow-hidden bg-white shadow-sm">
    <!-- Formatting Toolbar -->
    <div v-if="editor" class="flex flex-wrap items-center gap-1 p-2 bg-slate-100 border-b border-slate-300 text-xs font-semibold text-slate-700">
      
      <!-- Bold -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBold().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all font-bold', editor.isActive('bold') ? 'bg-slate-300 text-slate-900' : '']"
        title="Bold (Ctrl+B)"
      >
        B
      </button>

      <!-- Italic -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleItalic().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all italic font-serif', editor.isActive('italic') ? 'bg-slate-300 text-slate-900' : '']"
        title="Italic (Ctrl+I)"
      >
        I
      </button>

      <!-- Strike -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleStrike().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all line-through', editor.isActive('strike') ? 'bg-slate-300 text-slate-900' : '']"
        title="Strikethrough"
      >
        S
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- H1 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-black', editor.isActive('heading', { level: 1 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 1"
      >
        H1
      </button>

      <!-- H2 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-bold', editor.isActive('heading', { level: 2 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 2"
      >
        H2
      </button>

      <!-- H3 -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
        :class="['px-2 py-1 rounded hover:bg-slate-200 transition-all text-xs font-semibold', editor.isActive('heading', { level: 3 }) ? 'bg-slate-300 text-slate-900' : '']"
        title="Heading 3"
      >
        H3
      </button>

      <div class="h-4 w-px bg-slate-300 mx-1"></div>

      <!-- Bullet List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBulletList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all', editor.isActive('bulletList') ? 'bg-slate-300 text-slate-900' : '']"
        title="Bulleted List"
      >
        • List
      </button>

      <!-- Numbered List -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleOrderedList().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all', editor.isActive('orderedList') ? 'bg-slate-300 text-slate-900' : '']"
        title="Numbered List"
      >
        1. List
      </button>

      <!-- Blockquote -->
      <button 
        type="button"
        @click="editor.chain().focus().toggleBlockquote().run()"
        :class="['px-2.5 py-1 rounded hover:bg-slate-200 transition-all font-serif', editor.isActive('blockquote') ? 'bg-slate-300 text-slate-900' : '']"
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
        class="px-2 py-1 rounded hover:bg-slate-200 disabled:opacity-40 transition-all"
        title="Undo"
      >
        ↩
      </button>

      <!-- Redo -->
      <button 
        type="button"
        @click="editor.chain().focus().redo().run()"
        :disabled="!editor.can().redo()"
        class="px-2 py-1 rounded hover:bg-slate-200 disabled:opacity-40 transition-all"
        title="Redo"
      >
        ↪
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
