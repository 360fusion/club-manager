<script setup>
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Textarea from '@/Components/Ui/Textarea.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { checkbox, field, fieldLabel } from '@/Support/pageEditorFields';

// The page editor's collapsible search engine card: web address, SEO title and description, share image.
const seoOpen = defineModel('seoOpen', { type: Boolean, default: false });

defineProps({
    form: { type: Object, required: true },
    club: { type: Object, required: true },
    searchPreview: { type: Object, required: true },
    openMediaLibrary: { type: Function, required: true },
});
</script>

<template>
    <Card padding="none">
        <button
            type="button"
            @click="seoOpen = !seoOpen"
            class="w-full flex items-center gap-3 p-4 sm:p-6 sm:px-8 text-left cursor-pointer rounded-2xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
            :aria-expanded="seoOpen"
            aria-controls="page-seo-panel"
        >
            <UiIcon name="chevron-down" class="text-slate-500 dark:text-slate-400 transition-transform" :class="seoOpen ? '' : '-rotate-90'" />
            <span class="min-w-0">
                <span class="block text-base font-semibold text-slate-900 dark:text-white">Search engine optimisation (this page)</span>
                <span v-if="seoOpen" class="block text-sm text-slate-500 dark:text-slate-400 mt-0.5">The page's web address, and what search engines show for it. Overrides the site-wide SEO defaults; leave the title and description blank to use them.</span>
                <span v-else class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-mono truncate">/site/{{ club.slug }}/{{ form.is_homepage ? '' : form.slug }}</span>
            </span>
        </button>

        <div v-show="seoOpen" id="page-seo-panel" class="px-4 sm:px-8 pb-6 sm:pb-8 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 sm:items-start gap-4">
                <Input v-model="form.slug" label="Web address (slug)" placeholder="about" :disabled="form.is_homepage" :hint="form.is_homepage ? 'The homepage is always the site\'s front page.' : `/site/${club.slug}/${form.slug || ''}`" />
                <Input v-model="form.meta_title" label="SEO title" maxlength="255" :placeholder="form.title || 'Page title'" />
            </div>
            <Textarea
                v-model="form.meta_description"
                label="Meta description"
                :rows="3"
                maxlength="500"
                placeholder="Site-wide default description"
                :hint="`${(form.meta_description || '').length}/500. Search engines usually show around 155 characters.`"
            />

            <!-- How it may look in a search engine -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/40 p-4 text-left" aria-label="Search result preview">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Search result preview</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ searchPreview.url }}</p>
                <p class="text-base font-semibold text-blue-700 dark:text-blue-400 truncate">{{ searchPreview.title }}</p>
                <p class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">{{ searchPreview.description }}</p>
                <p v-if="searchPreview.title.length > 60" class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400">The title is {{ searchPreview.title.length }} characters; search engines usually cut it off after about 60.</p>
                <p v-if="(form.meta_description || '').length > 160" class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-400">The description is long; search engines usually cut it off after about 160 characters.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label for="page-share-image" :class="fieldLabel">Share image</label>
                        <div class="flex items-center gap-1.5">
                            <Button v-if="form.share_image" variant="ghost" size="sm" @click="form.share_image = ''">
                                <UiIcon name="trash" :size="14" />
                                <span>Clear</span>
                            </Button>
                            <Button variant="secondary" size="sm" @click="openMediaLibrary('page_share_image', null, 'pages')">
                                <UiIcon name="folder" :size="14" />
                                <span>Media library</span>
                            </Button>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <input id="page-share-image" v-model="form.share_image" type="text" :class="[field, 'font-mono text-xs']" placeholder="Site-wide share image is used" />
                        <img v-if="form.share_image" :src="form.share_image" alt="" class="w-16 h-12 rounded-lg object-cover border border-slate-200 dark:border-slate-700 shrink-0" />
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">The picture shown when this page is shared on Facebook, WhatsApp or Messages. About 1200 × 630 works best.</p>
                </div>
                <div>
                    <span :class="[fieldLabel, 'mb-1.5']">Search engines</span>
                    <label :class="['flex items-start gap-2.5 select-none text-sm', form.is_homepage || form.slug === 'home' ? 'opacity-50' : 'cursor-pointer']">
                        <input type="checkbox" v-model="form.noindex" :disabled="form.is_homepage || form.slug === 'home'" :class="[checkbox, 'mt-0.5']" />
                        <span>
                            <span class="font-medium text-slate-700 dark:text-slate-300">Hide this page from search engines</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400 mt-0.5">People with the link can still open it. It is also left out of the sitemap. The home page always stays visible.</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </Card>
</template>
