<script setup>
// The Manage Pages table of the website builder, in its own file so the page stays readable.
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Card from '@/Components/Ui/Card.vue';
import UiIcon from '@/Components/Ui/UiIcon.vue';

const props = defineProps({
    club: { type: [Object, Array], default: undefined },
    pages: { type: [Object, Array], default: undefined },
    getPageIcon: { type: Function, default: undefined },
    lifecycleText: { type: Function, default: undefined },
    movePageUp: { type: Function, default: undefined },
    movePageDown: { type: Function, default: undefined },
    togglePublishPage: { type: Function, default: undefined },
    selectionUrl: { type: Function, default: undefined },
    navClick: { type: Function, default: undefined },
    duplicatePage: { type: Function, default: undefined },
    isDefaultPage: { type: Function, default: undefined },
    triggerDeleteModal: { type: Function, default: undefined }
});

// getPageIcon (Utils/pageBuilderUrl) answers with an emoji; the table shows the matching interface icon instead.
const PAGE_ICONS = { '\u{1F3E0}': 'home', 'ℹ️': 'info', '\u{1F91D}': 'users', '\u{1F4F0}': 'document', '✉️': 'mail' };
const pageIconName = (p) => PAGE_ICONS[props.getPageIcon(p)] ?? 'document';

const ICON_BUTTON = 'h-8 w-8 !p-0';
const ICON_LINK = 'inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-300 bg-white text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';
</script>

<template>
<Card padding="none" class="overflow-hidden">
    <div class="p-6 border-b border-slate-200 dark:border-slate-800">
        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Manage pages</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Every page set up for {{ club.name }}.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs whitespace-nowrap">
            <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                <tr>
                    <th class="p-4 w-12 text-center">#</th>
                    <th class="p-4">Page title</th>
                    <th class="p-4">URL slug</th>
                    <th class="p-4">Blocks</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right min-w-[200px]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <tr v-for="(p, idx) in pages" :key="p.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="p-4 text-center font-medium text-slate-400">
                        {{ idx + 1 }}
                    </td>

                    <td class="p-4 font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                        <UiIcon :name="pageIconName(p)" class="text-slate-400" />
                        <span>{{ p.title }}</span>
                    </td>

                    <td class="p-4 font-mono text-slate-500 dark:text-slate-400">
                        /{{ p.slug }}
                    </td>

                    <td class="p-4">
                        <Badge>{{ p.blocks ? p.blocks.length : 0 }} blocks</Badge>
                    </td>

                    <td class="p-4">
                        <div class="flex flex-wrap items-center gap-1">
                            <Badge v-if="p.lifecycle_status === 'live'" variant="success">Published</Badge>
                            <Badge v-else-if="p.lifecycle_status === 'scheduled'" variant="warning" :title="lifecycleText(p)">Scheduled</Badge>
                            <Badge v-else-if="p.lifecycle_status === 'expired'" :title="lifecycleText(p)">Expired</Badge>
                            <Badge v-else>Draft</Badge>
                            <Badge v-if="p.draft_saved_at" variant="info" title="Has unpublished edits">Has draft</Badge>
                        </div>
                    </td>

                    <td class="p-4 text-right">
                        <div class="flex items-center justify-end gap-1.5 flex-nowrap">
                            <Button
                                variant="secondary"
                                size="sm"
                                :class="ICON_BUTTON"
                                @click="movePageUp(idx)"
                                :disabled="idx <= 1"
                                :title="idx === 0 ? 'The home page stays at the top' : (idx === 1 ? 'First page after the home page' : 'Move page up')"
                                aria-label="Move page up"
                            >
                                <UiIcon name="chevron-down" class="rotate-180" />
                            </Button>

                            <Button
                                variant="secondary"
                                size="sm"
                                :class="ICON_BUTTON"
                                @click="movePageDown(idx)"
                                :disabled="idx === 0 || idx >= pages.length - 1"
                                :title="idx === 0 ? 'The home page stays at the top' : 'Move page down'"
                                aria-label="Move page down"
                            >
                                <UiIcon name="chevron-down" />
                            </Button>

                            <Button
                                variant="secondary"
                                size="sm"
                                :class="ICON_BUTTON"
                                @click="togglePublishPage(p)"
                                :disabled="p.is_homepage || p.slug === 'home'"
                                :title="p.is_homepage ? 'The home page must stay published' : (p.is_published ? 'Published: click to hide' : 'Hidden: click to publish')"
                                :aria-label="p.is_published ? 'Hide page' : 'Publish page'"
                            >
                                <UiIcon :name="p.is_published ? 'eye' : 'eye-off'" />
                            </Button>

                            <a
                                :href="selectionUrl(p.id, 'edit')"
                                @click="navClick($event, p.id, 'edit')"
                                :class="ICON_LINK"
                                title="Edit in the page builder"
                                aria-label="Edit page"
                            >
                                <UiIcon name="pencil" />
                            </a>

                            <a
                                :href="selectionUrl(p.id, 'preview')"
                                @click="navClick($event, p.id, 'preview')"
                                :class="ICON_LINK"
                                title="Preview the page"
                                aria-label="Preview page"
                            >
                                <UiIcon name="eye" />
                            </a>

                            <a
                                :href="p.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${p.slug}`"
                                target="_blank"
                                :class="ICON_LINK"
                                title="Open the live page in a new tab"
                                aria-label="Open the live page"
                            >
                                <UiIcon name="external-link" />
                            </a>

                            <Button
                                variant="secondary"
                                size="sm"
                                :class="ICON_BUTTON"
                                @click="duplicatePage(p)"
                                title="Duplicate page"
                                aria-label="Duplicate page"
                            >
                                <UiIcon name="copy" />
                            </Button>

                            <Button
                                v-if="!isDefaultPage(p)"
                                variant="secondary"
                                size="sm"
                                :class="[ICON_BUTTON, '!text-rose-600 dark:!text-rose-400']"
                                @click="triggerDeleteModal(p)"
                                title="Delete page"
                                aria-label="Delete page"
                            >
                                <UiIcon name="trash" />
                            </Button>
                            <Button
                                v-else
                                variant="secondary"
                                size="sm"
                                :class="ICON_BUTTON"
                                disabled
                                title="A default page cannot be deleted"
                                aria-label="Delete page"
                            >
                                <UiIcon name="trash" />
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</Card>
</template>
