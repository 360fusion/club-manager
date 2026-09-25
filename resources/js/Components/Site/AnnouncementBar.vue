<script setup>
// The bar across the top of a club's public site. Used by the real site (interactive) and, without links or the
// close button, as a preview in the builder. A visitor who closes it does not see it again until the wording
// (its `key`) changes.
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    announcement: { type: Object, default: null },
    clubSlug: { type: String, default: '' },
    interactive: { type: Boolean, default: true },
});

const STYLES = {
    info: 'bg-blue-600 text-white',
    success: 'bg-emerald-600 text-white',
    warning: 'bg-amber-400 text-amber-950',
    dark: 'bg-slate-900 text-white',
};

const storageKey = computed(() => `cm_announcement_${props.clubSlug}_${props.announcement?.key}`);
const closed = ref(false);

onMounted(() => {
    try {
        closed.value = props.interactive && localStorage.getItem(storageKey.value) === '1';
    } catch { /* private window: it simply shows again */ }
});

const close = () => {
    closed.value = true;
    try {
        localStorage.setItem(storageKey.value, '1');
    } catch { /* not remembered */ }
};

// On the live site the close button works; in the builder's preview it is drawn but does nothing, so an admin can see
// that visitors will be able to close the bar.
const showClose = computed(() => props.interactive && props.announcement?.dismissible);
const showClosePreview = computed(() => !props.interactive && props.announcement?.dismissible);
const hasLink = computed(() => !!(props.announcement?.link_url && props.announcement?.link_label));
</script>

<template>
    <div
        v-if="announcement && !closed"
        role="region"
        aria-label="Announcement"
        :class="['relative z-[60] px-4 py-2 text-center text-xs sm:text-sm font-semibold', STYLES[announcement.style] || STYLES.info, (showClose || showClosePreview) ? 'pr-10' : '']"
    >
        <span>{{ announcement.text }}</span>
        <template v-if="hasLink">
            {{ ' ' }}
            <a v-if="interactive" :href="announcement.link_url" class="underline underline-offset-2 font-bold hover:opacity-80">{{ announcement.link_label }}</a>
            <span v-else class="underline underline-offset-2 font-bold">{{ announcement.link_label }}</span>
        </template>
        <button
            v-if="showClose"
            type="button"
            class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center rounded hover:bg-black/10 cursor-pointer"
            aria-label="Dismiss announcement"
            @click="close"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
        <span
            v-else-if="showClosePreview"
            class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 flex items-center justify-center rounded"
            aria-hidden="true"
            title="Visitors can close this bar"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 6l12 12M18 6L6 18" /></svg>
        </span>
    </div>
</template>
