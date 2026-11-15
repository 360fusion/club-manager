<script setup>
// The website builder's Publish screen: build the website as plain files (each build is a version that can be made
// live again later), serve it on the lodge's domain, download it as a zip, and send it to an FTP(S) server or GitHub.
// Everything is done on the server (StaticSiteController); this only shows the state and posts the actions.
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm, usePoll } from '@inertiajs/vue3';
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

const busy = ref(false);
const post = (url, data = {}) => {
    busy.value = true;
    router.post(url, data, { preserveScroll: true, onFinish: () => { busy.value = false; } });
};

// Publish now queues a version at once (or starts the one waiting after recent edits), and the screen follows it.
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

// The versions on their way to being live (waiting, then building), oldest first, and the sends of the newest one.
const due = (release) => !release.scheduled_for || new Date(release.scheduled_for) <= new Date();
const inProgress = computed(() => releases.value.filter((release) => ['queued', 'building'].includes(release.status)).reverse());
const waitingNow = computed(() => inProgress.value.some((release) => release.status === 'queued' && !release.automatic));
const autoTargets = computed(() => (data.value.targets || []).filter((target) => target.auto_send));
const sending = computed(() => (data.value.deploys || []).filter((deploy) => ['queued', 'running'].includes(deploy.status)));
const queueText = (release) => {
    if (!due(release)) return `Waiting for editing to finish: builds at ${time(release.scheduled_for)} unless you publish now.`;
    if (release.ahead > 0) return `In the queue, with ${release.ahead} ${release.ahead === 1 ? 'website' : 'websites'} building or waiting before it.`;
    return 'Next in the queue.';
};

// While a version is waiting its turn, building or being sent, the screen checks every few seconds so each step shows
// up by itself. A version waiting for editing to finish isn't checked on until it is due.
const working = computed(() => inProgress.value.some(due) || sending.value.length > 0);
const { start: startPolling, stop: stopPolling } = usePoll(4000, { only: ['publishing'] }, { autoStart: false });
watch(working, (now) => (now ? startPolling() : stopPolling()), { immediate: true });

const size = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const time = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
const when = (iso) => (iso ? new Date(iso).toLocaleString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '');
const statusVariant = { ready: 'success', done: 'success', building: 'info', queued: 'info', running: 'info', failed: 'danger' };

// ---- destinations --------------------------------------------------------------------------------------------------
const TYPE_OPTIONS = [
    { value: 'cpanel', label: 'cPanel hosting (with an API token)' },
    { value: 'ftps', label: 'FTP server (cPanel and most web hosts)' },
    { value: 'sftp', label: 'SFTP server (file transfer over SSH)' },
    { value: 'github', label: 'GitHub repository' },
];
const DEFAULT_PORTS = { cpanel: 2083, ftps: 21, sftp: 22 };
const blankTarget = () => ({ id: null, type: 'cpanel', name: '', host: '', port: 2083, username: '', password: '', path: 'public_html', insecure: false, repository: '', branch: 'main', cpanel_path: '', token: '', auto_send: false });
const targetForm = useForm(blankTarget());
// A new destination's port follows its kind until someone types their own.
watch(() => targetForm.type, (type, previous) => {
    if (!targetForm.id && (!targetForm.port || targetForm.port === DEFAULT_PORTS[previous])) targetForm.port = DEFAULT_PORTS[type] || null;
});
const editing = ref(false);

const startTarget = (target = null) => {
    targetForm.defaults({ ...blankTarget(), ...(target || {}), password: '', token: '' });
    targetForm.reset();
    targetForm.clearErrors();
    editing.value = true;
};
// Where the files live when the lodge hosts them itself (canonical links, link previews, sitemap).
const addressForm = useForm({ site_url: '' });
const addressShown = ref(false);
const startAddress = () => {
    addressForm.defaults({ site_url: data.value.site_url || '' });
    addressForm.reset();
    addressShown.value = true;
};
const saveAddress = () => addressForm.post(`${base.value}/address`, { preserveScroll: true, onSuccess: () => { addressShown.value = false; } });

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
                <Button :loading="busy" :disabled="waitingNow" @click="buildNow">Publish now</Button>
                <Button variant="secondary" @click="refresh">Refresh</Button>
                <span class="text-sm text-slate-500 dark:text-slate-400">Builds a new version with your latest changes and makes it live{{ autoTargets.length ? ', then sends it to your automatic destinations' : '' }}.</span>
            </div>

            <ol v-for="release in inProgress" :key="release.id" class="p-4 space-y-2 text-sm border border-blue-200 dark:border-blue-900/60 bg-blue-50/60 dark:bg-blue-950/30 rounded-2xl" :aria-label="`Version ${release.id} on its way to being live`">
                <li class="font-semibold text-slate-900 dark:text-white">Version {{ release.id }}<span class="font-normal text-slate-500 dark:text-slate-400"> · {{ release.automatic ? 'after recent edits' : `asked for by ${release.created_by || 'an editor'}` }}</span></li>
                <li class="flex items-start gap-2" :class="release.status === 'queued' ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                    <Badge :variant="release.status === 'queued' ? 'info' : 'success'">{{ release.status === 'queued' ? 'Now' : 'Done' }}</Badge>
                    <span>{{ release.status === 'queued' ? queueText(release) : 'Waited its turn in the queue.' }}</span>
                    <Button v-if="release.status === 'queued' && release.automatic" size="sm" class="ml-auto" :disabled="busy" @click="buildNow">Publish now</Button>
                </li>
                <li class="flex items-start gap-2" :class="release.status === 'building' ? 'text-slate-900 dark:text-white' : 'text-slate-500 dark:text-slate-400'">
                    <Badge :variant="release.status === 'building' ? 'info' : 'neutral'">{{ release.status === 'building' ? 'Now' : 'Next' }}</Badge>
                    <span>{{ release.status === 'building' ? (!release.pages_total ? 'Building: getting the pages ready.' : release.page_count < release.pages_total ? `Building: ${release.page_count} of ${release.pages_total} pages done.` : `Building: all ${release.pages_total} pages done, now copying the files.`) : 'Build the pages.' }}</span>
                </li>
                <li class="flex items-start gap-2 text-slate-500 dark:text-slate-400">
                    <Badge variant="neutral">Next</Badge>
                    <span>Make it the live version{{ data.serving && data.domain ? ` on ${data.domain}` : '' }}.</span>
                </li>
                <li v-for="target in autoTargets" :key="target.id" class="flex items-start gap-2 text-slate-500 dark:text-slate-400">
                    <Badge variant="neutral">Next</Badge>
                    <span>Send it to {{ target.name }}.</span>
                </li>
            </ol>

            <ul v-if="sending.length" class="p-4 space-y-2 text-sm border border-blue-200 dark:border-blue-900/60 bg-blue-50/60 dark:bg-blue-950/30 rounded-2xl">
                <li v-for="deploy in sending" :key="deploy.id" class="flex items-start gap-2 text-slate-900 dark:text-white">
                    <Badge variant="info">{{ deploy.status === 'running' ? 'Sending' : 'Waiting' }}</Badge>
                    <span>Version {{ deploy.release_id }} {{ deploy.status === 'running' ? 'is being sent' : 'is waiting in the queue to be sent' }} to {{ deploy.target || 'a removed destination' }}.</span>
                </li>
            </ul>

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
                                <Badge v-else-if="release.status !== 'ready'" :variant="statusVariant[release.status]" class="ml-2">{{ { building: 'Building', queued: 'Waiting' }[release.status] || 'Failed' }}</Badge>
                                <p v-if="release.error" class="text-rose-600 dark:text-rose-400 mt-1">{{ release.error }}</p>
                            </td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ when(release.created_at) }}<template v-if="release.created_by"> by {{ release.created_by }}</template></td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ release.status === 'ready' ? release.page_count : '' }}</td>
                            <td class="p-3 text-slate-500 dark:text-slate-400">{{ release.status === 'ready' ? size(release.bytes) : '' }}</td>
                            <td class="p-3">
                                <div v-if="release.status === 'ready'" class="flex flex-wrap justify-end gap-2">
                                    <a :href="`${base}/releases/${release.id}/preview/`" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 whitespace-nowrap">Preview</a>
                                    <Button v-if="!release.is_current" size="sm" variant="secondary" :disabled="busy" @click="makeLive(release)">Make live</Button>
                                    <a :href="`${base}/releases/${release.id}/download`" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 whitespace-nowrap">Download zip</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">No versions yet. Press Publish now to build the first one.</p>

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
                <p class="text-xs text-slate-500 dark:text-slate-400">With the published version, a new version is built 30 minutes after your last change and again every night. Press Publish now to put changes live straight away.</p>
            </template>
            <p v-else class="text-sm text-slate-500 dark:text-slate-400">Once your own domain is connected (in Website and SEO settings), it can show the published version from here.</p>
        </Card>

        <Card padding="lg" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">Send to your web host or GitHub</h3>
                <Button v-if="!editing" size="sm" variant="secondary" @click="startTarget()">Add a destination</Button>
            </div>

            <div class="text-sm text-slate-600 dark:text-slate-300">
                <form v-if="addressShown" class="flex flex-wrap items-end gap-3" @submit.prevent="saveAddress">
                    <Input v-model="addressForm.site_url" type="url" label="Your website's address" placeholder="https://www.yourlodge.org.uk" :error="addressForm.errors.site_url" class="flex-1 min-w-[16rem]" />
                    <Button type="submit" :loading="addressForm.processing">Save</Button>
                    <Button variant="ghost" @click="addressShown = false">Cancel</Button>
                </form>
                <p v-else>
                    <template v-if="data.site_url">Built for <strong class="font-medium">{{ data.site_url }}</strong>.</template>
                    <template v-else>Tell us your website's address so search engines and link previews show it, and the version includes a sitemap.</template>
                    <button type="button" class="ml-1 font-medium text-blue-600 dark:text-blue-400 hover:underline" @click="startAddress">{{ data.site_url ? 'Change' : 'Set the address' }}</button>
                </p>
            </div>

            <form v-if="editing" class="space-y-4 p-4 border border-slate-200 dark:border-slate-800 rounded-2xl" @submit.prevent="saveTarget">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <Select v-model="targetForm.type" label="Kind" :options="TYPE_OPTIONS" :disabled="!!targetForm.id" />
                    <Input v-model="targetForm.name" label="Name" placeholder="For example, Lodge web hosting" :error="targetForm.errors.name" />
                </div>
                <template v-if="targetForm.type !== 'github'">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <Input v-model="targetForm.host" label="Server" :placeholder="targetForm.type === 'cpanel' ? 'cpanel.yourhost.co.uk' : 'ftp.yourlodge.org.uk'" :error="targetForm.errors.host" class="sm:col-span-2" />
                        <Input v-model.number="targetForm.port" type="number" label="Port" :error="targetForm.errors.port" />
                        <Input v-model="targetForm.username" :label="targetForm.type === 'cpanel' ? 'cPanel username' : 'Username'" autocomplete="off" :error="targetForm.errors.username" />
                        <Input v-if="targetForm.type === 'cpanel'" v-model="targetForm.token" type="password" label="API token" autocomplete="new-password" :placeholder="targetForm.id ? 'Leave empty to keep the saved one' : ''" :error="targetForm.errors.token" />
                        <Input v-else v-model="targetForm.password" type="password" label="Password" autocomplete="new-password" :placeholder="targetForm.id ? 'Leave empty to keep the saved one' : ''" :error="targetForm.errors.password" />
                        <Input v-model="targetForm.path" label="Folder" placeholder="public_html" :error="targetForm.errors.path" />
                    </div>
                    <label v-if="targetForm.type === 'ftps'" class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                        <input v-model="targetForm.insecure" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-blue-600" />
                        <span>Use plain FTP <span class="text-slate-500 dark:text-slate-400">(sends the password unencrypted: only if your host has no FTP with TLS)</span></span>
                    </label>
                    <Alert v-if="targetForm.type === 'cpanel'" variant="info">In cPanel, open Security, then Manage API Tokens, and create a token (give it an expiry date if you like). The server is the address you open cPanel at, without "https://" or the port. Files removed from your website go to cPanel's trash, where you can restore them.</Alert>
                    <Alert v-else-if="targetForm.type === 'ftps'" variant="info">In cPanel, open FTP Accounts and create an account whose directory is just your website's folder (public_html), then use that here. If the password ever leaked, it could only reach the website files.</Alert>
                    <Alert v-else variant="info">Use the SSH or SFTP login your host gives you. The server's key is remembered on the first send, and later sends stop if it ever changes.</Alert>
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
                <label class="flex items-start gap-2.5 text-sm text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                    <input v-model="targetForm.auto_send" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-slate-300 dark:border-slate-700 accent-blue-600" />
                    <span>Send each new version here automatically <span class="text-slate-500 dark:text-slate-400">(a new version is built 30 minutes after your last change to the website, every night, and whenever someone presses Publish now)</span></span>
                </label>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :loading="targetForm.processing">Save</Button>
                    <Button variant="ghost" @click="editing = false">Cancel</Button>
                </div>
            </form>

            <ul v-if="data.targets?.length" class="divide-y divide-slate-200 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-2xl">
                <li v-for="target in data.targets" :key="target.id" class="p-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ target.name }} <Badge v-if="target.auto_send" variant="info" class="ml-1">Automatic</Badge></p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-mono break-all">
                            <template v-if="target.type === 'github'">github.com/{{ target.repository }} ({{ target.branch }})</template>
                            <template v-else-if="target.type === 'cpanel'">cPanel: {{ target.username }}@{{ target.host }}:{{ target.port }}/{{ target.path }}</template>
                            <template v-else>{{ target.type === 'sftp' ? 'sftp' : (target.insecure ? 'ftp' : 'ftps') }}://{{ target.username }}@{{ target.host }}:{{ target.port }}/{{ target.path }}</template>
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
