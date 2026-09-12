<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    club: Object,
    pages: Array,
});
</script>

<template>
    <AdminLayout title="Forms & CMS Pages" :club="club" active-tab="pages">
        
        <div class="space-y-6">
            
            <!-- Top Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Website Builder & Page Manager</h2>
                    <p class="text-xs text-slate-500 mt-1">Manage public pages, section blocks, and navigation links.</p>
                </div>

                <div class="flex items-center gap-3">
                    <Link :href="`/site/${club.slug}`" target="_blank" class="py-2.5 px-4 rounded-xl bg-slate-100 border border-slate-300 text-slate-700 font-bold text-xs">
                        🌐 View Live Site
                    </Link>
                    <Link :href="`/clubs/${club.slug}/admin/pages/create`" class="py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/20">
                        ➕ Create New Page
                    </Link>
                </div>
            </div>

            <!-- Pages Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase tracking-wider">
                        <tr>
                            <th class="p-4">Page Title</th>
                            <th class="p-4">URL Slug</th>
                            <th class="p-4">Section Blocks</th>
                            <th class="p-4">Homepage</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in pages" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="p-4 font-bold text-slate-900">
                                {{ p.title }}
                            </td>
                            <td class="p-4 font-mono text-slate-500">
                                /{{ p.slug }}
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                    {{ p.blocks ? p.blocks.length : 0 }} Blocks
                                </span>
                            </td>
                            <td class="p-4">
                                <span v-if="p.is_homepage" class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">HOMEPAGE</span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="p-4">
                                <span v-if="p.is_published" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">PUBLISHED</span>
                                <span v-else class="text-[10px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">DRAFT</span>
                            </td>
                            <td class="p-4 text-right space-x-2">
                                <Link :href="`/clubs/${club.slug}/admin/pages/${p.id}/edit`" class="py-1.5 px-3 rounded-lg bg-indigo-600 text-white font-bold text-xs">
                                    Edit Builder
                                </Link>
                                <Link :href="p.is_homepage ? `/site/${club.slug}` : `/site/${club.slug}/${p.slug}`" target="_blank" class="py-1.5 px-3 rounded-lg bg-slate-100 text-slate-700 font-bold text-xs border border-slate-200">
                                    View
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

    </AdminLayout>
</template>
