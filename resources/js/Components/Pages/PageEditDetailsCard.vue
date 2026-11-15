<script setup>
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';
import { checkbox, field, HEADER_STYLES, linkButton, tooltip } from '@/Support/pageEditorFields';

// The page editor's "Page details and navigation" card: saving, previewing, title, menus and schedule.
const showHistory = defineModel('showHistory', { type: Boolean, default: false });
const publishAtLocal = defineModel('publishAtLocal', { type: String, default: '' });
const unpublishAtLocal = defineModel('unpublishAtLocal', { type: String, default: '' });

defineProps({
    form: { type: Object, required: true },
    club: { type: Object, required: true },
    activePage: { type: Object, default: null },
    hasDraft: { type: Boolean, default: false },
    canDraft: { type: Boolean, default: false },
    isSavedSuccess: { type: Boolean, default: false },
    previewOpen: { type: Boolean, default: false },
    previewUrl: { type: String, default: '' },
    previewCopied: { type: Boolean, default: false },
    previewError: { type: String, default: '' },
    submitForm: { type: Function, required: true },
    saveDraft: { type: Function, required: true },
    publishDraft: { type: Function, required: true },
    openPreviewPanel: { type: Function, required: true },
    copyPreviewLink: { type: Function, required: true },
    renewPreviewLink: { type: Function, required: true },
    lifecycleText: { type: Function, required: true },
});
</script>

<template>
    <Card padding="lg" class="space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-5 space-y-4">
            <div>
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Page details and navigation</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">The page title, and where the page appears in the site's menus.</p>
            </div>

            <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
                <!-- Saving: the first puts your edits on the live page, the second keeps them aside -->
                <div role="group" aria-label="Save">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Save</p>
                    <div class="flex items-center gap-1.5">
                        <div class="relative group">
                            <Button size="sm" :loading="form.processing" aria-describedby="tip-save" @click="submitForm">
                                <UiIcon v-if="!form.processing" name="check" :size="14" />
                                <span>{{ form.processing ? 'Saving...' : (isSavedSuccess ? 'Saved' : 'Save page') }}</span>
                            </Button>
                            <span id="tip-save" role="tooltip" :class="[tooltip, 'left-0']">{{ hasDraft ? 'Saves the page as it looks here and makes it live straight away, replacing the draft. To publish the draft as it was saved, use Publish draft.' : 'Saves your changes and makes them live straight away.' }}</span>
                        </div>
                        <div v-if="canDraft" class="relative group">
                            <Button variant="secondary" size="sm" :disabled="form.processing" aria-describedby="tip-draft" class="whitespace-nowrap" @click="saveDraft">Save draft</Button>
                            <span id="tip-draft" role="tooltip" :class="[tooltip, 'left-0']">Keeps your edits aside. Visitors still see the live page until you publish the draft.</span>
                        </div>
                        <div v-if="canDraft" class="relative group">
                            <Button variant="secondary" size="sm" :disabled="!hasDraft || form.processing" aria-describedby="tip-publish-draft" class="whitespace-nowrap" @click="publishDraft">Publish draft</Button>
                            <span id="tip-publish-draft" role="tooltip" :class="[tooltip, 'left-0']">{{ hasDraft ? 'Makes your saved draft live for visitors.' : 'There is no draft to publish. Use Save as draft first.' }}</span>
                        </div>
                        <div v-if="form.id" class="relative group">
                            <Button variant="secondary" size="sm" aria-describedby="tip-history" aria-label="History" @click="showHistory = true">
                                <UiIcon name="clock" :size="14" />
                            </Button>
                            <span id="tip-history" role="tooltip" :class="[tooltip, 'left-0']">History: earlier saves of this page. Restore one into the editor, then save to keep it.</span>
                        </div>
                    </div>
                </div>

                <!-- Previewing: the first is what visitors see now, the second is a secret link that includes drafts -->
                <div v-if="form.id" role="group" aria-label="Preview">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Preview</p>
                    <div class="flex items-center gap-1.5">
                        <div class="relative group">
                            <a v-if="form.slug && activePage?.lifecycle_status === 'live'" :href="form.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${form.slug}`" target="_blank" rel="noopener" aria-describedby="tip-live" :class="linkButton">
                                <span>View live</span>
                                <UiIcon name="external-link" :size="14" />
                            </a>
                            <Button v-else variant="secondary" size="sm" disabled aria-describedby="tip-live" class="whitespace-nowrap">
                                <span>View live</span>
                                <UiIcon name="external-link" :size="14" />
                            </Button>
                            <span id="tip-live" role="tooltip" :class="[tooltip, 'right-0']">{{ activePage?.lifecycle_status === 'live' ? 'View live page: opens the page as visitors see it right now, in a new tab.' : "View live page: this page is not live yet, so visitors can't see it. Use Draft preview instead." }}</span>
                        </div>
                        <div class="relative group">
                            <Button variant="secondary" size="sm" :aria-expanded="previewOpen" aria-describedby="tip-private" class="whitespace-nowrap" @click="openPreviewPanel">Draft preview</Button>
                            <span id="tip-private" role="tooltip" :class="[tooltip, 'right-0']">Makes a secret link that shows this page with your draft, even before it is live. Share it with someone to get their feedback.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Private preview link -->
        <div v-if="previewOpen" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2 text-sm">
            <p class="font-medium text-slate-900 dark:text-white">Draft preview link</p>
            <p class="text-xs text-slate-600 dark:text-slate-300">Anyone with this link can see this page{{ hasDraft ? ', including its unsaved draft,' : '' }} even if it is not live yet{{ form.is_members_only ? ', and even though it is for members only' : '' }}. Only share it with people you trust.</p>
            <div class="flex flex-wrap items-center gap-2">
                <input :value="previewUrl" readonly aria-label="Draft preview link" @focus="$event.target.select()" :class="[field, 'flex-1 min-w-[220px] font-mono text-xs']" />
                <Button variant="secondary" size="sm" :disabled="!previewUrl" @click="copyPreviewLink">
                    <UiIcon :name="previewCopied ? 'check' : 'copy'" :size="14" />
                    <span>{{ previewCopied ? 'Copied' : 'Copy link' }}</span>
                </Button>
                <a v-if="previewUrl" :href="previewUrl" target="_blank" rel="noopener" :class="linkButton">
                    <span>Open</span>
                    <UiIcon name="external-link" :size="14" />
                </a>
                <Button variant="secondary" size="sm" :disabled="!previewUrl" @click="renewPreviewLink">New link</Button>
            </div>
            <p v-if="previewError" class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ previewError }}</p>
        </div>

        <Input v-model="form.title" label="Page title" placeholder="For example, About our lodge" />

        <div class="flex flex-wrap items-center gap-6 pt-2 border-t border-slate-100 dark:border-slate-800">
            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                <input type="checkbox" v-model="form.is_published" :disabled="form.is_homepage || form.slug === 'home'" :class="checkbox" />
                <span>Published</span>
            </label>

            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                <input type="checkbox" v-model="form.show_in_navigation" :class="checkbox" />
                <span>Show in the top menu</span>
            </label>

            <label v-if="!form.is_homepage" class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                <input type="checkbox" v-model="form.is_members_only" :class="checkbox" />
                <UiIcon name="lock" :size="14" class="text-slate-500 dark:text-slate-400" />
                <span>Members only</span>
            </label>

            <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none" title="A separate menu of links at the bottom of every page">
                <input type="checkbox" v-model="form.show_in_footer" :class="checkbox" />
                <span>Show in the footer menu</span>
            </label>
        </div>

        <!-- Top menu style and schedule -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <Select v-model="form.header_style" label="Top menu on this page" :options="HEADER_STYLES" hint="Handy for a landing page." />
            <Input v-if="!form.is_homepage" v-model="publishAtLocal" type="datetime-local" label="Go live on (optional)" />
            <div v-if="!form.is_homepage">
                <Input v-model="unpublishAtLocal" type="datetime-local" label="Come down on (optional)" :error="form.errors.unpublish_at" />
                <p v-if="form.publish_at && form.unpublish_at && form.unpublish_at <= form.publish_at" class="text-xs text-rose-600 dark:text-rose-400 mt-1">This must be after the go-live time.</p>
            </div>
            <p v-if="!form.is_homepage" class="sm:col-span-3 text-xs text-slate-500 dark:text-slate-400 -mt-2">Times are in your own timezone. The page must also be ticked as Published for these dates to apply.<template v-if="lifecycleText(activePage)"> <strong class="font-medium text-slate-700 dark:text-slate-300">{{ lifecycleText(activePage) }}.</strong></template></p>
        </div>
    </Card>
</template>
