<script setup>
// The website builder's Publish screen: build the website as plain files (each build is a version that can be made
// live again later), serve it on the lodge's domain, download it as a zip, and send it to an FTP(S) server or GitHub.
// Everything is done on the server (StaticSiteController); this only shows the state and posts the actions.
import { computed, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Card from '@/Components/Ui/Card.vue';
import Alert from '@/Components/Ui/Alert.vue';
import Badge from '@/Components/Ui/Badge.vue';
import Button from '@/Components/Ui/Button.vue';
import Input from '@/Components/Ui/Input.vue';
import Select from '@/Components/Ui/Select.vue';

const props = defineProps({
    club: { type: Object, required: true },
    // Null when the builder switched to this screen without loading the page: it is fetched on mount.
    publishing: { type: Object, default: null },
});

const data = computed(() => props.publishing || { releases: [], targets: [], deploys: [] });

const base = computed(() => `/${props.club.slug}/admin/pages/publish`);
const releases = computed(() => data.value.releases || []);
const current = computed(() => releases.value.find((release) => release.is_current) || null);
const building = computed(() => releases.value.some((release) => release.status === 'building'));

const busy = ref(false);
const post = (url, data = {}) => {
    busy.value = true;
    router.post(url, data, { preserveScroll: true, onFinish: () => { busy.value = false; } });
};

const buildNow = () => post(`${base.value}/build`);
const makeLive = (release) => post(`${base.value}/releases/${release.id}/activate`);
const setServing = (serve) => post(`${base.value}/serve`, { serve });
const pushTo = (target) => post(`${base.value}/targets/${target.id}/deploy`);
const removeTarget = (target) => {
    if (!window.confirm(`Remove ${target.name}? Nothing on that server or repository is deleted.`)) return;
    router.delete(`${base.value}/targets/${target.id}`, { preserveScroll: true });
};
const refresh = () => router.reload({ only: ['publishing'] });
onMounted(() => { if (!props.publishing) refresh(); });

const size = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const when = (iso) => (iso ? new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '');
const statusVariant = { ready: 'success', done: 'success', building: 'info', queued: 'info', running: 'info', failed: 'danger' };

// ---- destinations --------------------------------------------------------------------------------------------------
const TYPE_OPTIONS = [
    { value: 'ftps', label: 'FTP server (cPanel and most web hosts)' },
    { value: 'github', label: 'GitHub repository' },
];
const blankTarget = () => ({ id: null, type: 'ftps', name: '', host: '', port: 21, username: '', password: '', path: 'public_html', insecure: false, repository: '', branch: 'main', cpanel_path: '', token: '' });
const targetForm = useForm(blankTarget());
const editing = ref(false);

const startTarget = (target = null) => {
    targetForm.defaults({ ...blankTarget(), ...(target || {}), password: '', token: '' });
    targetForm.reset();
    targetForm.clearErrors();
    editing.value = true;
};
const saveTarget = () => targetForm.post(`${base.value}/targets`, { preserveScroll: true, onSuccess: () => { editing.value = false; } });
</script>

<template>
    <div class="space-y-6">
        <Card padding="lg" class="space-y-5">
            <div class="border-b border-slate-200 dark:border-slate-800 pb-4">
                <h2 class="text-xl font-bold text-slate-900 dark:text-white">Publish as static files</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Builds your website as plain web pages that any web host can serve, with no database or code running behind them. Each build is kept as a version, so you can go back to an earlier one. Events, bookings, the contact form and members-only pages still come from Club Manager.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button :loading="busy" :disabled="building" @click="buildNow">Build a new version</Button>
                <Button variant="secondary" @click="refresh">Refresh</Button>
                <span v-if="building" class="text-sm text-slate-500 dark:text-slate-400">Building now. This takes a minute or two.</span>
            </div>

            <div v-if="releases.length" class="overflow-x-auto border border-slate-200 dark:border-slate-800 rounded-2xl">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 font-semibold uppercase tracking-wider">
                        <tr>
                            <th class="p-3">Version</th>
                            <th class="p-3">Built</th>
                            <th class="p-3">Pages</th>
                            <th class="p-3">Size</th>
                            <th class="relative p-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr v-for="release in releases" :key="release.id">
                            <td class="p-3 text-slate-700 dark:text-slate-200">
                                <span class="font-semibold">{{ release.id }}</span>
                                <Badge v-if="release.is_current" variant="success" class="ml-2">Live</Badge>
                                <Badge v-else-if="release.status !== 'ready'" :variant="statusVariant[release.status]" class="ml-2">{{ release.status === 'building' ? 'Building' : 'Failed' }}</Badge>
                                <p v-if="release.error" class="text-rose-600 dark:text-rose-400 mt-1">{{ release.error }}</p>
                            </td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ when(release.created_at) }}<template v-if="release.created_by"> by {{ release.created_by }}</template></td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ release.status === 'ready' ? release.page_count : '' }}</td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ release.status === 'ready' ? size(release.bytes) : '' }}</td>
                            <td class="p-3">
                                <div v-if="release.status === 'ready'" class="flex flex-wrap justify-end gap-2">
                                    <Button v-if="!release.is_current" size="sm" variant="secondary" :disabled="busy" @click="makeLive(release)">Make live</Button>
                                    <a :href="`${base}/releases/${release.id}/download`" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 whitespace-nowrap">Download zip</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">No versions yet. Build one to get started.</p>

            <p class="text-xs text-slate-500 dark:text-slate-400">To put the zip on cPanel hosting yourself: open File Manager, go to public_html, upload the zip, then right-click it and choose Extract. Show hidden files to see the .htaccess file it includes.</p>
        </Card>

        <Card padding="lg" class="space-y-4">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">Your domain</h3>
            <template v-if="data.domain">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    <strong class="font-medium">{{ data.domain }}</strong>
                    {{ data.serving ? 'shows the live version of the static website.' : 'shows the website as you edit it, straight away.' }}
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button v-if="!data.serving" variant="secondary" :disabled="busy || !current" @click="setServing(true)">Show the published version on my domain</Button>
                    <Button v-else variant="secondary" :disabled="busy" @click="setServing(false)">Show the website as I edit it</Button>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">With the published version, a new version is built a few minutes after each change and again every night, so changes reach your domain shortly after you save them.</p>
            </template>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">Once your own domain is connected (in Website and SEO settings), it can show the published version from here.</p>
        </Card>

        <Card padding="lg" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Send to your web host or GitHub</h3>
                <Button v-if="!editing" size="sm" variant="secondary" @click="startTarget()">Add a destination</Button>
            </div>

            <form v-if="editing" class="space-y-4 p-4 border border-slate-200 dark:border-slate-800 rounded-2xl" @submit.prevent="saveTarget">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Select v-model="targetForm.type" label="Kind" :options="TYPE_OPTIONS" :disabled="!!targetForm.id" />
                    <Input v-model="targetForm.name" label="Name" placeholder="For example, Lodge web hosting" :error="targetForm.errors.name" />
                </div>
                <template v-if="targetForm.type === 'ftps'">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <Input v-model="targetForm.host" label="Server" placeholder="ftp.yourlodge.org.uk" :error="targetForm.errors.host" class="sm:col-span-2" />
                        <Input v-model.number="targetForm.port" type="number" label="Port" :error="targetForm.errors.port" />
                        <Input v-model="targetForm.username" label="FTP username" autocomplete="off" :error="targetForm.errors.username" />
                        <Input v-model="targetForm.password" type="password" label="FTP password" autocomplete="new-password" :placeholder="targetForm.id ? 'Leave empty to keep the saved one' : ''" :error="targetForm.errors.password" />
                        <Input v-model="targetForm.path" label="Folder" placeholder="public_html" :error="targetForm.errors.path" />
                    </div>
                    <label class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                        <input v-model="targetForm.insecure" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-blue-600" />
                        <span>Use plain FTP <span class="text-slate-500 dark:text-slate-400">(sends the password unencrypted: only if your host has no FTP with TLS)</span></span>
                    </label>
                    <Alert variant="info">In cPanel, open FTP Accounts and create an account whose directory is just your website's folder (public_html), then use that here. If the password ever leaked, it could only reach the website files.</Alert>
                </template>
                <template v-else>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <Input v-model="targetForm.repository" label="Repository" placeholder="yourlodge/website" :error="targetForm.errors.repository" class="sm:col-span-2" />
                        <Input v-model="targetForm.branch" label="Branch" :error="targetForm.errors.branch" />
                        <Input v-model="targetForm.token" type="password" label="Access token" autocomplete="new-password" :placeholder="targetForm.id ? 'Leave empty to keep the saved one' : ''" :error="targetForm.errors.token" class="sm:col-span-3" />
                        <Input v-model="targetForm.cpanel_path" label="cPanel folder (optional)" placeholder="public_html" hint="Adds a .cpanel.yml so cPanel's Git Version Control copies the site into this folder of your hosting account when it deploys." :error="targetForm.errors.cpanel_path" class="sm:col-span-3" />
                    </div>
                    <Alert variant="info">On GitHub, create a fine-grained personal access token for just this repository with "Contents: read and write". Each push is one commit. cPanel's Git Version Control can then pull the repository into your site, or GitHub Pages can serve it.</Alert>
                </template>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :loading="targetForm.processing">Save</Button>
                    <Button variant="ghost" @click="editing = false">Cancel</Button>
                </div>
            </form>

            <ul v-if="data.targets?.length" class="divide-y divide-slate-200 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-2xl">
                <li v-for="target in data.targets" :key="target.id" class="p-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ target.name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all">
                            <template v-if="target.type === 'github'">github.com/{{ target.repository }} ({{ target.branch }})</template>
                            <template v-else>{{ target.insecure ? 'ftp' : 'ftps' }}://{{ target.username }}@{{ target.host }}:{{ target.port }}/{{ target.path }}</template>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button size="sm" :disabled="busy || !current" @click="pushTo(target)">Send the live version</Button>
                        <Button size="sm" variant="secondary" @click="startTarget(target)">Edit</Button>
                        <Button size="sm" variant="danger" :aria-label="`Remove ${target.name}`" @click="removeTarget(target)">Remove</Button>
                    </div>
                </li>
            </ul>
            <p v-else-if="!editing" class="text-sm text-slate-500 dark:text-slate-400">No destinations yet.</p>

            <div v-if="data.deploys?.length" class="space-y-2">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Recent sends</h4>
                <ul class="text-xs space-y-1.5">
                    <li v-for="deploy in data.deploys" :key="deploy.id" class="flex flex-wrap items-center gap-2 text-slate-600 dark:text-slate-300">
                        <Badge :variant="statusVariant[deploy.status]">{{ deploy.status }}</Badge>
                        <span>{{ when(deploy.created_at) }}: version {{ deploy.release_id }} to {{ deploy.target || 'a removed destination' }}<template v-if="deploy.user"> by {{ deploy.user }}</template>.</span>
                        <span v-if="deploy.status === 'done'" class="text-slate-500 dark:text-slate-400">{{ deploy.files_sent }} sent, {{ deploy.files_deleted }} removed.</span>
                        <span v-if="deploy.message" :class="deploy.status === 'failed' ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'">{{ deploy.message }}</span>
                    </li>
                </ul>
            </div>
        </Card>
    </div>
</template>
