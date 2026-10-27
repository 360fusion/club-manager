<script setup>
// The Manage Pages table of the website builder, in its own file so the page stays readable.
defineProps({
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
</script>

<template>
<div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
        <h2 class="text-base font-bold text-slate-900 dark:text-white">Manage Pages</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Comprehensive view of all pages configured for {{ club.name }}.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs whitespace-nowrap">
            <thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider">
                <tr>
                    <th class="p-4 w-12 text-center">#</th>
                    <th class="p-4">Page Title</th>
                    <th class="p-4">URL Slug</th>
                    <th class="p-4">Blocks</th>
                    <th class="p-4">Status</th>
                    <th class="p-4 text-right min-w-[200px]">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                <tr v-for="(p, idx) in pages" :key="p.id" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="p-4 text-center font-bold text-slate-400">
                        {{ idx + 1 }}
                    </td>

                    <td class="p-4 font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="text-base">{{ getPageIcon(p) }}</span>
                        <span>{{ p.title }}</span>
                    </td>

                    <td class="p-4 font-mono text-slate-500 dark:text-slate-400">
                        /{{ p.slug }}
                    </td>

                    <td class="p-4">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60">
                            {{ p.blocks ? p.blocks.length : 0 }} Blocks
                        </span>
                    </td>

                    <td class="p-4">
                        <div class="flex flex-wrap items-center gap-1">
                            <span v-if="p.lifecycle_status === 'live'" class="text-[10px] font-extrabold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-200 dark:border-emerald-800/60">PUBLISHED</span>
                            <span v-else-if="p.lifecycle_status === 'scheduled'" class="text-[10px] font-extrabold text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800/60" :title="lifecycleText(p)">SCHEDULED</span>
                            <span v-else-if="p.lifecycle_status === 'expired'" class="text-[10px] font-extrabold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700" :title="lifecycleText(p)">EXPIRED</span>
                            <span v-else class="text-[10px] font-extrabold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded">DRAFT</span>
                            <span v-if="p.draft_saved_at" class="text-[10px] font-extrabold text-violet-700 dark:text-violet-300 bg-violet-50 dark:bg-violet-950/40 px-2 py-0.5 rounded border border-violet-200 dark:border-violet-800/60" title="Has unpublished edits">HAS DRAFT</span>
                        </div>
                    </td>

                    <td class="p-4 text-right">
                        <div class="flex items-center justify-end gap-1.5 flex-nowrap">
                            <button
                                @click="movePageUp(idx)"
                                :disabled="idx <= 1"
                                :class="[
                                    'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                    idx <= 1 ? 'bg-slate-100 dark:bg-slate-800 text-slate-300 opacity-40 cursor-not-allowed' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800'
                                ]"
                                :title="idx === 0 ? 'Home page is anchored at top' : (idx === 1 ? 'First non-home page' : 'Move page up')"
                            >
                                ▲
                            </button>

                            <button
                                @click="movePageDown(idx)"
                                :disabled="idx === 0 || idx >= pages.length - 1"
                                :class="[
                                    'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                    (idx === 0 || idx >= pages.length - 1) ? 'bg-slate-100 dark:bg-slate-800 text-slate-300 opacity-40 cursor-not-allowed' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800'
                                ]"
                                :title="idx === 0 ? 'Home page is anchored at top' : 'Move page down'"
                            >
                                ▼
                            </button>

                            <button
                                @click="togglePublishPage(p)"
                                :disabled="p.is_homepage || p.slug === 'home'"
                                :class="[
                                    'w-8 h-8 rounded-xl flex items-center justify-center text-xs font-bold transition-all cursor-pointer',
                                    p.is_homepage ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 opacity-60 cursor-not-allowed' :
                                    p.is_published ? 'bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60' : 'bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-800'
                                ]"
                                :title="p.is_homepage ? 'Homepage must remain published' : (p.is_published ? 'Published - Click to hide/unpublish' : 'Hidden - Click to publish')"
                            >
                                <svg v-if="p.is_published" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a10.007 10.007 0 014.122-.963c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21m-4.225-4.225L3 3" />
                                </svg>
                            </button>

                            <a
                                :href="selectionUrl(p.id, 'edit')"
                                @click="navClick($event, p.id, 'edit')"
                                class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                title="Edit Page Builder"
                            >
                                ✏️
                            </a>

                            <a
                                :href="selectionUrl(p.id, 'preview')"
                                @click="navClick($event, p.id, 'preview')"
                                class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/40 hover:bg-amber-100 dark:hover:bg-amber-900/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                title="Live Page Preview"
                            >
                                👁️
                            </a>

                            <a
                                :href="p.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${p.slug}`"
                                target="_blank"
                                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-xs font-bold transition-colors"
                                title="Open Live Site in New Tab"
                            >
                                🌐
                            </a>

                            <button
                                @click="duplicatePage(p)"
                                class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                title="Duplicate Page"
                            >
                                📄
                            </button>

                            <button
                                v-if="!isDefaultPage(p)"
                                @click="triggerDeleteModal(p)"
                                class="w-8 h-8 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 flex items-center justify-center text-xs font-bold cursor-pointer transition-colors"
                                title="Delete Custom Page"
                            >
                                🗑️
                            </button>
                            <button
                                v-else
                                disabled
                                class="w-8 h-8 rounded-xl bg-slate-50 dark:bg-slate-800/50 text-slate-300 border border-slate-100 dark:border-slate-800 flex items-center justify-center text-xs opacity-40 cursor-not-allowed"
                                title="Original default page cannot be deleted"
                            >
                                🗑️
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</template>
