@props(['name', 'team', 'folder' => '', 'kind' => null, 'value' => null])
@php
    $slug = $team->slug;
    $current = $value ? \Launchpad\Models\MediaFile::withoutGlobalScope(\Launchpad\Workspaces\WorkspaceScope::class)->where('workspace_id', $team->id)->find($value) : null;
    $browse = route('workspace.media.browse', $slug).($kind ? '?'.http_build_query(['kind' => $kind]) : '');
    $key = 'media-picker-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $name);
@endphp
<div class="lp-media-picker" data-media-picker>
    <input type="hidden" name="{{ $name }}" value="{{ $current?->id }}" data-media-value>
    <p data-media-preview>
        @if ($current && $current->kind === 'image')<img src="{{ route('workspace.media.show', [$slug, $current->id]) }}" alt="{{ $current->alt }}" width="48" height="48" style="object-fit:cover;vertical-align:middle;margin-right:8px">@endif
        <span data-media-name>{{ $current?->name ?? __('Nothing chosen.') }}</span>
    </p>
    <button class="lp-btn lp-btn--quiet" type="button" data-media-open aria-haspopup="dialog">{{ __('Choose') }}</button>
    <button class="lp-btn lp-btn--quiet" type="button" data-media-clear @if (! $current) hidden @endif>{{ __('Remove') }}</button>
    <dialog aria-labelledby="{{ $key }}-title" data-browse="{{ $browse }}" data-folder="{{ $folder }}" data-store="{{ route('workspace.media.store', $slug) }}" data-check="{{ route('workspace.media.check', $slug) }}" data-folders="{{ route('workspace.media.folders.store', $slug) }}" style="max-width:720px;width:92vw">
        <h2 id="{{ $key }}-title">{{ __('Choose a file') }}</h2>
        <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
            <div class="lp-field"><label for="{{ $key }}-q">{{ __('Search') }}</label><input id="{{ $key }}-q" type="search" maxlength="200" data-media-q></div>
            <div class="lp-field"><label for="{{ $key }}-file">{{ __('Add a file') }}</label><input id="{{ $key }}-file" type="file" accept="{{ \Launchpad\Media\Library::accept($kind) }}" data-media-file></div>
        </div>
        <p class="lp-error" role="alert" data-media-error hidden></p>
        <div role="alert" data-media-duplicate hidden>
            <p>{{ __('This file is already in your library') }}</p>
            <button class="lp-btn lp-btn--quiet" type="button" data-media-use>{{ __('Use it') }}</button>
            <button class="lp-btn lp-btn--quiet" type="button" data-media-anyway>{{ __('Upload anyway') }}</button>
        </div>
        <p class="lp-muted" data-media-crumbs></p>
        <ul data-media-list style="list-style:none;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px"></ul>
        <p data-media-empty hidden>{{ __('Nothing here yet.') }}</p>
        <button class="lp-btn lp-btn--quiet" type="button" data-media-more hidden>{{ __('Show more') }}</button>
        <button class="lp-btn lp-btn--quiet" type="button" data-media-close>{{ __('Close') }}</button>
    </dialog>
</div>
@once
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    const words = @json(['nothing' => __('Nothing chosen.'), 'up' => __('All media'), 'failed' => __("The upload didn't work.")]);
    const token = @json(csrf_token());
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': token };
    const post = (url, body) => fetch(url, { method: 'POST', headers: body instanceof FormData ? headers : { ...headers, 'Content-Type': 'application/json' }, body: body instanceof FormData ? body : JSON.stringify(body), credentials: 'same-origin' });
    const hash = async (file) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer())), (b) => b.toString(16).padStart(2, '0')).join('');
    const el = (tag, text, attrs = {}) => Object.assign(document.createElement(tag), { textContent: text ?? '' }, attrs);

    document.querySelectorAll('[data-media-picker]').forEach((root) => {
        if (root.dataset.ready) return;
        root.dataset.ready = '1';
        const $ = (name) => root.querySelector(`[data-media-${name}]`);
        const dialog = root.querySelector('dialog');
        const input = $('value');
        let folder = null; // the folder being looked at: an id, or null for the top
        let uploadId; // the id of the folder uploads land in, found or made on first use
        let waiting = null;
        let page = 1;

        const fail = (text) => { $('error').textContent = text; $('error').hidden = !text; };
        const choose = (file) => {
            input.value = file ? file.id : '';
            $('name').textContent = file ? file.name : words.nothing;
            const old = $('preview').querySelector('img');
            old && old.remove();
            if (file && file.kind === 'image') $('preview').prepend(el('img', '', { src: file.url, alt: file.alt || '', width: 48, height: 48, style: 'object-fit:cover;vertical-align:middle;margin-right:8px' }));
            $('clear').hidden = !file;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            dialog.close();
        };

        const load = async (more = false) => {
            page = more ? page + 1 : 1;
            const url = new URL(dialog.dataset.browse, location.href);
            if (folder) url.searchParams.set('folder', folder);
            if ($('q').value) url.searchParams.set('q', $('q').value);
            url.searchParams.set('page', page);
            const body = await (await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
            more || $('list').replaceChildren();
            $('crumbs').replaceChildren(...[{ id: null, name: words.up }, ...body.breadcrumbs].flatMap((crumb, i) => [i ? ' / ' : '', el('button', crumb.name, { type: 'button', className: 'lp-btn lp-btn--quiet', onclick: () => { folder = crumb.id; load(); } })]));
            if (!more) body.folders.forEach((f) => { const li = el('li'); li.append(el('button', f.name, { type: 'button', className: 'lp-btn lp-btn--quiet', onclick: () => { folder = f.id; load(); } })); $('list').append(li); });
            body.files.forEach((f) => {
                const button = el('button', '', { type: 'button', className: 'lp-btn lp-btn--quiet', onclick: () => choose(f) });
                button.setAttribute('aria-label', f.name);
                // the name under the picture too, so two like images can be told apart
                f.kind === 'image' && button.append(el('img', '', { src: f.url, alt: f.alt || '', width: 96, height: 96, loading: 'lazy', style: 'object-fit:cover;display:block' }));
                button.append(el('span', f.name, { style: 'display:block;max-width:96px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.8rem' }));
                const li = el('li'); li.append(button); $('list').append(li);
            });
            $('empty').hidden = $('list').children.length > 0;
            $('more').hidden = body.page >= body.pages;
        };

        // the upload folder is looked for among the top folders by name, and made only when it is not there
        const target = async () => {
            if (uploadId !== undefined) return uploadId;
            const name = dialog.dataset.folder;
            if (!name) return (uploadId = null);
            const list = await (await fetch(dialog.dataset.browse.split('?')[0], { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
            const found = list.folders.find((f) => f.name.toLowerCase() === name.toLowerCase());
            if (found) return (uploadId = found.id);
            const made = await post(dialog.dataset.folders, { name });
            if (!made.ok) throw new Error('folder');
            return (uploadId = (await made.json()).id);
        };
        const send = async (file) => {
            const data = new FormData();
            data.append('file', file);
            const id = await target();
            if (id) data.append('folder_id', id);
            const response = await post(dialog.dataset.store, data);
            if (!response.ok) {
                const errors = (await response.json().catch(() => ({}))).errors || {};
                return fail((errors.file || errors.folder_id || [])[0] || words.failed);
            }
            choose(await response.json());
        };
        const upload = async (file) => {
            fail('');
            $('duplicate').hidden = true;
            // crypto.subtle only exists on https pages and localhost; without it there is no duplicate check
            const checked = window.crypto?.subtle ? await post(dialog.dataset.check, { hash: await hash(file) }) : null;
            const found = checked?.ok ? (await checked.json()).file : null;
            if (!found) return send(file);
            waiting = { file, found };
            $('duplicate').hidden = false;
            $('use').focus();
        };

        $('open').addEventListener('click', () => { dialog.showModal(); fail(''); load(); });
        $('clear').addEventListener('click', () => choose(null));
        $('q').addEventListener('input', () => { clearTimeout($('q').timer); $('q').timer = setTimeout(load, 250); });
        $('close').addEventListener('click', () => dialog.close());
        // Enter searches; it must not submit the form this field sits in
        $('q').addEventListener('keydown', (event) => { if (event.key === 'Enter') { event.preventDefault(); clearTimeout($('q').timer); load(); } });
        $('more').addEventListener('click', () => load(true));
        $('file').addEventListener('change', (event) => { const file = event.target.files[0]; event.target.value = ''; file && upload(file).catch(() => fail(words.failed)); });
        $('use').addEventListener('click', () => { const { found } = waiting; waiting = null; $('duplicate').hidden = true; choose(found); });
        $('anyway').addEventListener('click', () => { const { file } = waiting; waiting = null; $('duplicate').hidden = true; send(file).catch(() => fail(words.failed)); });
    });
})();
</script>
@endonce
