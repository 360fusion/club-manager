<script setup>


// A section of the website builder page, in its own file so the page stays readable.
defineProps({
    club: { type: [Object, Array], default: undefined },
    addRedirect: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    form: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    isSavedSuccess: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    redirectForm: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    redirects: { type: [Object, Array, Function, String, Number, Boolean], default: undefined },
    removeRedirect: { type: [Object, Array, Function, String, Number, Boolean], default: undefined }
});
</script>

<template>
    <div class="bg-white dark:bg-slate-900 p-6 sm:p-8 rounded-3xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm space-y-6">
        <div class="border-b border-slate-100 dark:border-slate-800 pb-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">↪️ Redirects</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Send an old address to a page that exists now, for example from your previous website, so links and search results keep working. Renaming a page's address already keeps the old one working by itself.</p>
        </div>

        <div v-if="isSavedSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-2xl text-emerald-800 dark:text-emerald-200 text-xs font-bold">✅ Saved.</div>

        <form @submit.prevent="addRedirect" class="space-y-4 text-xs">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    <label for="redirect-from" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Old address</label>
                    <div class="flex items-stretch">
                        <span class="px-3 flex items-center bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-200 dark:border-slate-800 rounded-l-xl font-mono text-[11px] text-slate-500 whitespace-nowrap">/site/{{ club.slug }}</span>
                        <input id="redirect-from" v-model="redirectForm.from_path" type="text" placeholder="/about-us/history" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 !rounded-l-none font-mono text-[11px]" />
                    </div>
                    <p v-if="redirectForm.errors.from_path" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ redirectForm.errors.from_path }}</p>
                </div>
                <div>
                    <label for="redirect-to" class="block font-bold text-slate-700 dark:text-slate-200 mb-1">Send visitors to</label>
                    <input id="redirect-to" v-model="redirectForm.to_url" type="text" :placeholder="`/site/${club.slug}/about`" class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 rounded-xl font-medium text-slate-900 dark:text-white outline-none focus:ring-2 focus:ring-blue-500 font-mono text-[11px]" />
                    <p v-if="redirectForm.errors.to_url" class="text-[10px] text-rose-600 dark:text-rose-400 font-bold mt-1">{{ redirectForm.errors.to_url }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2.5 cursor-pointer font-bold text-slate-700 dark:text-slate-200 select-none">
                    <input type="checkbox" v-model="redirectForm.is_permanent" class="w-4 h-4 rounded accent-blue-600 cursor-pointer" />
                    <span>Permanent <span class="font-normal text-slate-400">(tells search engines to use the new address instead)</span></span>
                </label>
                <button type="submit" :disabled="redirectForm.processing || !redirectForm.from_path || !redirectForm.to_url" class="py-2.5 px-5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-xs shadow-md disabled:opacity-50 cursor-pointer">Add redirect</button>
            </div>
            <p class="text-[10px] text-slate-400">A page that already exists at an address always shows instead of a redirect. Send visitors to a page on this site (starting with /) or to a full web address.</p>
        </form>

        <div v-if="redirects.length" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-300 font-bold uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Old address</th>
                        <th class="p-3">Goes to</th>
                        <th class="p-3">Type</th>
                        <th class="p-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-mono text-[11px]">
                    <tr v-for="r in redirects" :key="r.id">
                        <td class="p-3 text-slate-700 dark:text-slate-200 break-all">/site/{{ club.slug }}{{ r.from_path }}</td>
                        <td class="p-3 text-slate-500 dark:text-slate-400 break-all">{{ r.to_url }}</td>
                        <td class="p-3 font-sans font-bold text-slate-500">{{ r.is_permanent ? 'Permanent' : 'Temporary' }}</td>
                        <td class="p-3 text-right"><button type="button" @click="removeRedirect(r.id)" class="px-2.5 py-1 rounded-xl font-sans font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/60 cursor-pointer" :aria-label="`Remove redirect from ${r.from_path}`">Remove</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-xs text-slate-400 italic">No redirects yet.</p>
    </div>
</template>
