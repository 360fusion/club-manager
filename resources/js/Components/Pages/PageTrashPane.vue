<script setup>
// Recently deleted custom pages of the website builder, with restore and delete-forever.
defineProps({
    club: { type: [Object, Array], default: undefined },
    trashedPages: { type: [Object, Array], default: undefined },
    restorePage: { type: Function, default: undefined },
    forceDeletePage: { type: Function, default: undefined }
});
</script>

<template>
<div v-if="trashedPages.length" class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden">
    <div class="p-6 border-b border-slate-100 dark:border-slate-800">
        <h2 class="text-base font-bold text-slate-900 dark:text-white">🗑️ Trash</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Deleted pages stay here until restored or removed permanently.</p>
    </div>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <div v-for="tp in trashedPages" :key="tp.id" class="p-4 flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ tp.title }}</p>
                <p class="text-[11px] text-slate-400 font-mono truncate">/site/{{ club.slug }}/{{ tp.slug }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button @click="restorePage(tp)" class="py-1.5 px-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60 font-bold text-[11px] cursor-pointer transition-colors">
                    ↩️ Restore
                </button>
                <button @click="forceDeletePage(tp)" class="py-1.5 px-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-100 dark:hover:bg-rose-900/40 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60 font-bold text-[11px] cursor-pointer transition-colors">
                    Delete forever
                </button>
            </div>
        </div>
    </div>
</div>
</template>
