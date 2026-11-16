<script setup>
import { onMounted, reactive, ref, watch } from 'vue';
import { teamUrl } from '../team';
import { t } from '../i18n';
import StatusAlert from './StatusAlert.vue';

// A team's media library: folders, search, upload, and (on the page) the details of a file. In "pick" mode a click on a file emits `pick` with it.
// It talks to the media routes in JSON; `tree` is every folder (the page has it) for the "Move to" list.
const props = defineProps({
    team: { type: String, required: true },
    mode: { type: String, default: 'page' },
    kind: { type: String, default: '' },
    uploadFolder: { type: String, default: '' },
    tree: { type: Array, default: () => [] },
});
const emit = defineEmits(['pick']);

const base = teamUrl(props.team, 'media');
const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '');
const call = (method, url, body) => fetch(url, {
    method,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}) },
    body: body instanceof FormData ? body : (body ? JSON.stringify(body) : undefined),
});
const firstError = (body) => Object.values(body?.errors ?? {})[0]?.[0] ?? null;

const data = ref({ folders: [], files: [], breadcrumbs: [], page: 1, pages: 1, tags: [], used: 0, quota: null, trashed: false });
const folder = ref(null);
const trashed = ref(false);
const filters = reactive({ q: '', kind: props.kind, sort: 'newest', everywhere: false });
const view = ref('grid');
const error = ref('');
const notice = ref('');
const busy = ref(false);
const selected = ref(null);
const edit = reactive({ name: '', alt: '', notes: '', tags: '', folder_id: '' });
const duplicate = ref(null); // { file, found } while waiting on "Use it" or "Upload anyway"
const folderName = ref('');
let uploadId;

const load = async (page = 1) => {
    const query = new URLSearchParams({ page });
    folder.value && query.set('folder', folder.value);
    trashed.value && query.set('trashed', 1);
    filters.q && query.set('q', filters.q);
    filters.kind && query.set('kind', filters.kind);
    filters.sort !== 'newest' && query.set('sort', filters.sort);
    filters.everywhere && query.set('everywhere', 1);
    const response = await call('GET', `${base}/browse?${query}`);
    if (!response.ok) return fail(response);
    const body = await response.json();
    // paging adds to the list
    data.value = page > 1 ? { ...body, files: [...data.value.files, ...body.files] } : body;
};
const fail = async (response) => { error.value = firstError(await response.json().catch(() => ({}))) ?? t("That didn't work."); };
const open = (id) => { folder.value = id; trashed.value = false; selected.value = null; load(); };
const showTrash = () => { trashed.value = true; folder.value = null; selected.value = null; load(); };

let timer;
watch(() => [filters.q, filters.kind, filters.sort, filters.everywhere], () => { clearTimeout(timer); timer = setTimeout(load, 250); });
onMounted(load);

const hash = async (file) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer())), (b) => b.toString(16).padStart(2, '0')).join('');

// the upload folder is looked for among the top folders by name, and made only when it is not there
const target = async () => {
    if (uploadId !== undefined) return uploadId;
    if (props.uploadFolder) {
        const list = await (await call('GET', `${base}/browse`)).json();
        const found = list.folders.find((f) => f.name.toLowerCase() === props.uploadFolder.toLowerCase());
        if (found) return (uploadId = found.id);
        const made = await call('POST', `${base}/folders`, { name: props.uploadFolder });
        if (!made.ok) throw new Error('folder');
        return (uploadId = (await made.json()).id);
    }
    return (uploadId = folder.value);
};

const send = async (file) => {
    const form = new FormData();
    form.append('file', file);
    const id = props.uploadFolder ? await target() : folder.value;
    id && form.append('folder_id', id);
    const response = await call('POST', base, form);
    if (!response.ok) return fail(response);
    const added = await response.json();
    if (props.mode === 'pick') return emit('pick', added);
    await load();
};
const upload = async (list) => {
    error.value = '';
    duplicate.value = null;
    busy.value = true;
    try {
        for (const file of list) {
            // crypto.subtle only exists on https pages and localhost; without it there is no duplicate check
            const checked = window.crypto?.subtle ? await call('POST', `${base}/check`, { hash: await hash(file) }) : null;
            const found = checked?.ok ? (await checked.json()).file : null;
            if (found) { duplicate.value = { file, found }; return; }
            await send(file);
        }
    } catch {
        error.value = t("The upload didn't work.");
    } finally {
        busy.value = false;
    }
};
const useExisting = () => { const { found } = duplicate.value; duplicate.value = null; props.mode === 'pick' ? emit('pick', found) : select(found); };
const uploadAnyway = async () => { const { file } = duplicate.value; duplicate.value = null; await send(file); };
const onDrop = (event) => upload([...event.dataTransfer.files]);

const select = (file) => {
    selected.value = file;
    Object.assign(edit, { name: file.name, alt: file.alt ?? '', notes: file.notes ?? '', tags: file.tags.join(', '), folder_id: file.folder_id ?? '' });
};
const act = async (method, url, body, done) => {
    error.value = '';
    const response = await call(method, url, body);
    if (!response.ok) return fail(response);
    notice.value = done;
    await load();
    return response.json();
};
const save = async () => {
    const tags = edit.tags.split(',').map((tag) => tag.trim()).filter(Boolean);
    const saved = await act('PATCH', `${base}/${selected.value.id}`, { name: edit.name, alt: edit.alt, notes: edit.notes, tags, folder_id: edit.folder_id || null }, t('Saved.'));
    saved && select(saved);
};
const trash = async (file) => { await act('DELETE', `${base}/${file.id}`, null, t('Moved to the trash.')); selected.value = null; };
const restore = (file) => act('POST', `${base}/${file.id}/restore`, null, t('Restored.'));
const forever = (file, ask) => confirm(ask) && act('DELETE', `${base}/${file.id}/forever`, null, t('Deleted for good.'));
const makeFolder = async () => {
    if (!folderName.value) return;
    const made = await act('POST', `${base}/folders`, { name: folderName.value, parent_id: folder.value }, t('Folder made.'));
    made && (folderName.value = '');
};
const trashFolder = (f) => act('DELETE', `${base}/folders/${f.id}`, null, t('Moved to the trash.'));
const restoreFolder = (f) => act('POST', `${base}/folders/${f.id}/restore`, null, t('Restored.'));
const folderForever = (f, ask) => confirm(ask) && act('DELETE', `${base}/folders/${f.id}/forever`, null, t('Deleted for good.'));
const click = (file) => (props.mode === 'pick' ? emit('pick', file) : select(file));
</script>

<template>
    <div class="lp-media" @dragover.prevent @drop.prevent="onDrop">
        <StatusAlert />
        <p v-if="error" class="lp-error" role="alert">{{ error }}</p>
        <p v-if="notice" class="lp-muted" role="status">{{ notice }}</p>

        <nav :aria-label="$t('Folders')" class="lp-muted">
            <button class="lp-btn lp-btn--quiet" type="button" :aria-current="!folder && !trashed ? 'page' : null" @click="open(null)">{{ $t('All media') }}</button>
            <template v-for="crumb in data.breadcrumbs" :key="crumb.id">
                / <button class="lp-btn lp-btn--quiet" type="button" @click="open(crumb.id)">{{ crumb.name }}</button>
            </template>
            <button v-if="mode === 'page'" class="lp-btn lp-btn--quiet" type="button" :aria-current="trashed ? 'page' : null" @click="showTrash">{{ $t('Trash') }}</button>
        </nav>

        <form v-if="!trashed" role="search" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin:12px 0" @submit.prevent="load()">
            <div class="lp-field"><label for="media-q">{{ $t('Search') }}</label><input id="media-q" v-model="filters.q" type="search" maxlength="200"></div>
            <div v-if="!kind" class="lp-field">
                <label for="media-kind">{{ $t('Type') }}</label>
                <select id="media-kind" v-model="filters.kind">
                    <option value="">{{ $t('All types') }}</option>
                    <option value="image">{{ $t('Images') }}</option><option value="video">{{ $t('Video') }}</option><option value="audio">{{ $t('Audio') }}</option>
                    <option value="document">{{ $t('Documents') }}</option><option value="other">{{ $t('Other') }}</option>
                </select>
            </div>
            <div class="lp-field">
                <label for="media-sort">{{ $t('Sort by') }}</label>
                <select id="media-sort" v-model="filters.sort">
                    <option value="newest">{{ $t('Newest') }}</option><option value="oldest">{{ $t('Oldest') }}</option><option value="name">{{ $t('Name') }}</option><option value="size">{{ $t('Size') }}</option>
                </select>
            </div>
            <label><input v-model="filters.everywhere" type="checkbox"> {{ folder ? $t('Search inside sub-folders too') : $t('Search every folder') }}</label>
            <button v-if="mode === 'page'" class="lp-btn lp-btn--quiet" type="button" :aria-pressed="view === 'list'" @click="view = view === 'grid' ? 'list' : 'grid'">{{ view === 'grid' ? $t('Show as a list') : $t('Show as a grid') }}</button>
        </form>

        <div v-if="!trashed" style="margin:12px 0">
            <div class="lp-field">
                <label for="media-file">{{ $t('Add a file') }}</label>
                <input id="media-file" type="file" multiple :accept="['image', 'video', 'audio'].includes(kind) ? `${kind}/*` : undefined" :disabled="busy" @change="upload([...$event.target.files]); $event.target.value = ''">
                <p class="lp-muted">{{ $t('You can also drop files here.') }}</p>
            </div>
            <div v-if="duplicate" role="alert">
                <p>{{ $t('This file is already in your library') }}: {{ duplicate.found.name }}</p>
                <button class="lp-btn lp-btn--quiet" type="button" @click="useExisting">{{ $t('Use it') }}</button>
                <button class="lp-btn lp-btn--quiet" type="button" @click="uploadAnyway">{{ $t('Upload anyway') }}</button>
            </div>
            <form v-if="mode === 'page'" style="display:flex;gap:8px;align-items:end" @submit.prevent="makeFolder">
                <div class="lp-field"><label for="media-folder">{{ $t('New folder') }}</label><input id="media-folder" v-model="folderName" maxlength="100"></div>
                <button class="lp-btn lp-btn--quiet" type="submit">{{ $t('Make folder') }}</button>
            </form>
        </div>

        <ul style="list-style:none;padding:0;margin:0">
            <li v-for="f in data.folders" :key="`d${f.id}`" style="display:flex;gap:8px;align-items:center;margin:4px 0">
                <template v-if="trashed">
                    <span>{{ f.name }}</span>
                    <template v-if="f.can_delete">
                        <button class="lp-btn lp-btn--quiet" type="button" @click="restoreFolder(f)">{{ $t('Restore') }}</button>
                        <button class="lp-btn lp-btn--quiet" type="button" @click="folderForever(f, $t('Delete this folder and everything in it for good?'))">{{ $t('Delete for good') }}</button>
                    </template>
                </template>
                <template v-else>
                    <button class="lp-btn lp-btn--quiet" type="button" @click="open(f.id)">{{ f.name }}</button>
                    <button v-if="mode === 'page' && f.can_delete" class="lp-btn lp-btn--quiet" type="button" @click="trashFolder(f)">{{ $t('Delete') }}</button>
                </template>
            </li>
        </ul>

        <p v-if="!data.files.length && !data.folders.length">{{ trashed ? $t('The trash is empty.') : $t('Nothing here yet.') }}</p>
        <ul :style="view === 'grid' ? 'list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px' : 'list-style:none;padding:0'">
            <li v-for="file in data.files" :key="file.id">
                <button v-if="!trashed" class="lp-btn lp-btn--quiet" type="button" :aria-label="file.name" :aria-pressed="selected?.id === file.id" :style="view === 'grid' ? 'display:flex;flex-direction:column;gap:4px;align-items:flex-start;text-align:left' : 'display:flex;gap:8px;align-items:center;text-align:left'" @click="click(file)">
                    <img v-if="file.kind === 'image'" :src="file.url" :alt="file.alt ?? ''" :width="view === 'grid' ? 96 : 32" :height="view === 'grid' ? 96 : 32" loading="lazy" style="object-fit:cover">
                    <span :style="view === 'grid' && file.kind === 'image' ? 'max-width:96px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem' : ''">{{ file.name }}</span>
                    <span v-if="view === 'list'" class="lp-muted">{{ file.size_label }}</span>
                </button>
                <div v-else style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span>{{ file.name }}</span>
                    <template v-if="file.can_delete">
                        <button class="lp-btn lp-btn--quiet" type="button" @click="restore(file)">{{ $t('Restore') }}</button>
                        <button class="lp-btn lp-btn--quiet" type="button" @click="forever(file, $t('Delete this file for good?'))">{{ $t('Delete for good') }}</button>
                    </template>
                </div>
            </li>
        </ul>
        <button v-if="data.page < data.pages" class="lp-btn lp-btn--quiet" type="button" @click="load(data.page + 1)">{{ $t('Show more') }}</button>

        <section v-if="selected && mode === 'page' && !trashed" class="lp-card" :aria-label="$t('Details')">
            <h2>{{ selected.name }}</h2>
            <img v-if="selected.kind === 'image'" :src="selected.url" :alt="selected.alt ?? ''" style="max-width:100%;max-height:240px">
            <p class="lp-muted">{{ selected.size_label }} · <a :href="selected.url">{{ $t('Open') }}</a></p>
            <form @submit.prevent="save">
                <div class="lp-field"><label for="media-name">{{ $t('Name') }}</label><input id="media-name" v-model="edit.name" maxlength="200" required></div>
                <div class="lp-field"><label for="media-alt">{{ $t('Description for people who cannot see it') }}</label><input id="media-alt" v-model="edit.alt" maxlength="500"></div>
                <div class="lp-field"><label for="media-notes">{{ $t('Notes') }}</label><textarea id="media-notes" v-model="edit.notes" maxlength="5000" rows="3" /></div>
                <div class="lp-field"><label for="media-tags">{{ $t('Tags, separated by commas') }}</label><input id="media-tags" v-model="edit.tags" list="media-tag-list"></div>
                <datalist id="media-tag-list"><option v-for="tag in data.tags" :key="tag" :value="tag" /></datalist>
                <div v-if="tree.length" class="lp-field">
                    <label for="media-move">{{ $t('Move to') }}</label>
                    <select id="media-move" v-model="edit.folder_id">
                        <option value="">{{ $t('All media') }}</option>
                        <option v-for="node in tree" :key="node.id" :value="node.id">{{ '  '.repeat(node.depth) + node.name }}</option>
                    </select>
                </div>
                <button class="lp-btn" type="submit">{{ $t('Save') }}</button>
                <button v-if="selected.can_delete" class="lp-btn lp-btn--quiet" type="button" @click="trash(selected)">{{ $t('Delete') }}</button>
            </form>
        </section>
    </div>
</template>
