<script setup>
import { Link } from '@inertiajs/vue3';
import MembersLayout from '@/Layouts/MembersLayout.vue';
import Card from '@/Components/Ui/Card.vue';

defineProps({
    documents: { type: Object, required: true },
});
</script>

<template>
    <MembersLayout title="Signed documents" active-tab="">
        <div class="space-y-6">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">Signed documents</h1>

            <Card v-if="documents.data.length" padding="none">
                <ul>
                    <li v-for="item in documents.data" :key="item.id" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-800">
                        <div class="min-w-0">
                            <span class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span>{{ item.club.name }}</span>
                                <span aria-hidden="true">·</span>
                                <span>{{ item.method === 'typed' ? 'Typed' : 'Drawn' }} signature</span>
                                <span aria-hidden="true">·</span>
                                <time>{{ item.signed_at }}</time>
                            </span>
                            <span class="mt-1 block text-sm font-semibold text-slate-900 dark:text-white">{{ item.document_label }}</span>
                        </div>
                        <a :href="route('member.signatures.pdf', { slug: item.club.slug, id: item.id })" class="shrink-0 rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600">Download PDF</a>
                    </li>
                </ul>
            </Card>
            <Card v-else><p class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">You haven't signed anything yet. Anything you sign, in any of your clubs, will show up here with a downloadable copy.</p></Card>

            <div v-if="documents.prev_page_url || documents.next_page_url" class="flex justify-between">
                <Link v-if="documents.prev_page_url" :href="documents.prev_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">← Newer</Link><span v-else />
                <Link v-if="documents.next_page_url" :href="documents.next_page_url" class="text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">Older →</Link>
            </div>
        </div>
    </MembersLayout>
</template>
