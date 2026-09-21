<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

defineProps({
    // Text on the button when no `trigger` slot is given.
    label: { type: String, default: '' },
    buttonClass: { type: String, default: '' },
    align: { type: String, default: 'right' },
    ariaLabel: { type: String, default: '' },
});

const open = ref(false);
const root = ref(null);

const onDocumentClick = (event) => {
    if (root.value && !root.value.contains(event.target)) open.value = false;
};
const onKeydown = (event) => {
    if (event.key === 'Escape') open.value = false;
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative inline-block text-left">
        <button type="button" :class="buttonClass" aria-haspopup="menu" :aria-expanded="open" :aria-label="ariaLabel || label || 'More actions'" @click="open = !open">
            <slot name="trigger">{{ label }} <span aria-hidden="true">&#9662;</span></slot>
        </button>
        <div v-if="open" role="menu" :class="['absolute z-30 mt-1 min-w-[13rem] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900', align === 'right' ? 'right-0' : 'left-0']" @click="open = false">
            <slot />
        </div>
    </div>
</template>
