<script setup>
// A section of the website builder page, in its own file so the page stays readable.
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';

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
    <Card padding="lg" class="space-y-6">
        <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Redirects</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Send an old address to a page that exists now, for example from your previous website, so links and search results keep working. Renaming a page's address already keeps the old one working by itself.</p>
        </div>

        <Alert v-if="isSavedSuccess" variant="success">Saved.</Alert>

        <form @submit.prevent="addRedirect" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label for="redirect-from" class="block text-xs font-medium text-slate-700 dark:text-slate-300">Old address</label>
                    <div class="flex items-stretch">
                        <span class="px-3 flex items-center bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-300 dark:border-slate-700 rounded-l-xl font-mono text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">/site/{{ club.slug }}</span>
                        <input id="redirect-from" v-model="redirectForm.from_path" type="text" placeholder="/about-us/history" :aria-invalid="redirectForm.errors.from_path ? 'true' : undefined" :class="['w-full rounded-r-xl border px-3 py-2 font-mono text-sm bg-white text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:text-white dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500', redirectForm.errors.from_path ? 'border-rose-400 dark:border-rose-700' : 'border-slate-300 dark:border-slate-700']" />
                    </div>
                    <p v-if="redirectForm.errors.from_path" class="text-xs text-rose-600 dark:text-rose-400">{{ redirectForm.errors.from_path }}</p>
                </div>
                <Input v-model="redirectForm.to_url" label="Send visitors to" :placeholder="`/site/${club.slug}/about`" :error="redirectForm.errors.to_url" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2.5 cursor-pointer text-sm font-medium text-slate-700 dark:text-slate-300 select-none">
                    <input type="checkbox" v-model="redirectForm.is_permanent" class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-blue-600 cursor-pointer" />
                    <span>Permanent <span class="font-normal text-slate-500 dark:text-slate-400">(tells search engines to use the new address instead)</span></span>
                </label>
                <Button type="submit" :loading="redirectForm.processing" :disabled="!redirectForm.from_path || !redirectForm.to_url">Add redirect</Button>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400">A page that already exists at an address always shows instead of a redirect. Send visitors to a page on this site (starting with /) or to a full web address.</p>
        </form>

        <div v-if="redirects.length" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="p-3">Old address</th>
                        <th class="p-3">Goes to</th>
                        <th class="p-3">Type</th>
                        <th class="p-3 text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-mono">
                    <tr v-for="r in redirects" :key="r.id">
                        <td class="p-3 text-slate-700 dark:text-slate-200 break-all">/site/{{ club.slug }}{{ r.from_path }}</td>
                        <td class="p-3 text-slate-500 dark:text-slate-400 break-all">{{ r.to_url }}</td>
                        <td class="p-3 font-sans"><Badge>{{ r.is_permanent ? 'Permanent' : 'Temporary' }}</Badge></td>
                        <td class="p-3 text-right font-sans"><Button variant="danger" size="sm" :aria-label="`Remove redirect from ${r.from_path}`" @click="removeRedirect(r.id)">Remove</Button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No redirects yet.</p>
    </Card>
</template>
