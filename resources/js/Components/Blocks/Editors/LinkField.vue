<script setup>
// One link box for the builder: either a page on this site (picked in a "Choose Page" pop-up) or an external web
// address. An external link typed without https:// gets it added, so it opens the other site instead of a page here.
// Mutates nothing itself; it emits the new address through v-model.
import { computed, ref } from 'vue';
import PageChooserModal from '@/Components/PageChooserModal.vue';
import { completeWebAddress, hasUnsafeScheme, isValidExternalLink } from '@/Utils/links';
import { SMALL_INPUT, MINI_BUTTON, MINI_DANGER, HINT } from './styles';

const props = defineProps({
    modelValue: { type: String, default: '' },
    pages: { type: Array, default: () => [] },
    club: { type: Object, required: true },
    label: { type: String, default: 'Link' },
});

const emit = defineEmits(['update:modelValue']);

const urlFor = (page) => `/site/${props.club.slug}${page.is_homepage ? '' : `/${page.slug}`}`;

const value = computed(() => String(props.modelValue ?? ''));
const isSitePath = computed(() => value.value.startsWith('/') && !value.value.startsWith('//'));

// Which kind of link the box is showing: an existing page link (or nothing yet) starts on "Page", anything else on "External".
const mode = ref(value.value === '' || isSitePath.value ? 'page' : 'external');

const chosenPage = computed(() => props.pages.find((page) => urlFor(page) === value.value) || null);
const choosable = computed(() => props.pages.map((page) => ({ id: page.id, title: page.title, is_homepage: page.is_homepage, note: page.is_published ? '' : 'Not published' })));

const chooserOpen = ref(false);
const notice = ref('');

const pick = (ids) => {
    const page = props.pages.find((p) => p.id === ids[0]);
    chooserOpen.value = false;
    if (page) emit('update:modelValue', urlFor(page));
};

const clear = () => emit('update:modelValue', '');

const complete = () => {
    const { url, changed } = completeWebAddress(value.value);
    notice.value = changed ? 'Added https:// at the start so the link opens properly.' : '';
    if (url !== value.value) emit('update:modelValue', url);
};

const problem = computed(() => {
    if (mode.value !== 'external' || value.value === '') return '';
    if (hasUnsafeScheme(value.value)) return 'Only web addresses (https://), email (mailto:) and phone (tel:) links can be used.';
    if (!isSitePath.value && !value.value.startsWith('#') && !isValidExternalLink(value.value)) return "That doesn't look like a web address. Check for spaces or a missing website name.";
    return '';
});

const TAB = 'px-3 py-1 text-[11px] font-bold cursor-pointer transition-colors';
</script>

<template>
    <div class="space-y-2">
        <div class="inline-flex rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden" role="group" :aria-label="`${label} type`">
            <button type="button" :aria-pressed="mode === 'page'" :class="[TAB, mode === 'page' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']" @click="mode = 'page'">Page on this site</button>
            <button type="button" :aria-pressed="mode === 'external'" :class="[TAB, 'border-l border-slate-200 dark:border-slate-700', mode === 'external' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800']" @click="mode = 'external'">External link</button>
        </div>

        <div v-if="mode === 'page'" class="space-y-1">
            <div class="flex items-center gap-2">
                <span :class="[SMALL_INPUT, 'flex-1 min-w-0 truncate', chosenPage ? 'font-bold' : 'text-slate-400 italic']">
                    {{ chosenPage ? chosenPage.title : (value ? 'Not one of your pages yet' : 'No page chosen') }}
                </span>
                <button type="button" :class="MINI_BUTTON" @click="chooserOpen = true">Choose Page</button>
                <button v-if="value" type="button" :class="MINI_DANGER" :aria-label="`Clear ${label.toLowerCase()}`" @click="clear">✕</button>
            </div>
            <p v-if="value && !chosenPage" :class="[HINT, 'font-mono break-all']">Currently: {{ value }}</p>
        </div>

        <div v-else class="space-y-1">
            <input
                :value="modelValue"
                type="text"
                inputmode="url"
                autocomplete="off"
                placeholder="https://www.example.org"
                :aria-label="`${label} web address`"
                :aria-invalid="problem !== ''"
                :class="[SMALL_INPUT, 'font-mono text-[11px]']"
                @input="emit('update:modelValue', $event.target.value); notice = ''"
                @blur="complete"
                @keydown.enter.prevent="complete"
            />
            <p v-if="notice" role="status" class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400">{{ notice }}</p>
            <p v-if="problem" role="alert" class="text-[10px] font-bold text-rose-600 dark:text-rose-400">{{ problem }}</p>
            <p v-else-if="!notice" :class="HINT">Type or paste the address. If you leave off https:// it is added for you.</p>
        </div>

        <PageChooserModal
            :show="chooserOpen"
            single
            :title="`Choose a page for ${label.toLowerCase()}`"
            intro="The link will go to this page on your site."
            :pages="choosable"
            :selected="chosenPage ? [chosenPage.id] : null"
            empty-text="There are no pages to link to yet."
            @close="chooserOpen = false"
            @apply="pick"
        />
    </div>
</template>
