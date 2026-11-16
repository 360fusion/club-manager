@extends('launchpad::layouts.account')
@section('title', __(':team media', ['team' => $workspace->name]))
@section('account')
@php
    $slug = $workspace->slug;
    $here = $folder['id'] ?? null;
    $used = \Illuminate\Support\Number::fileSize($used, precision: 1);
    $summary = $quota === null ? __(':used used', ['used' => $used]) : __(':used of :quota used', ['used' => $used, 'quota' => \Illuminate\Support\Number::fileSize($quota, precision: 0)]);
    $go = fn (array $query = []) => route('workspace.media', $slug).($query ? '?'.http_build_query($query) : '');
    $keep = array_filter(['folder' => $here, 'trashed' => $trashed ?: null, 'q' => $filters['q'], 'kind' => $filters['kind'], 'tag' => $filters['tag'], 'month' => $filters['month'], 'sort' => $filters['sort'] === 'newest' ? null : $filters['sort'], 'everywhere' => $filters['everywhere'] ?: null]);
@endphp
<div class="lp-card lp-media">
    <h1>{{ __(':team: Media', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ $summary }}. {{ __('Anyone in the team can add, tag and move files. Only the person who added one, or a team manager, can delete it.') }}</p>

    <nav class="lp-media__folders" aria-label="{{ __('Folders') }}">
        <a href="{{ $go() }}" @if (! $trashed && ! $here) aria-current="page" @endif>{{ __('All media') }}</a>
        @foreach ($tree as $node)
            <a href="{{ $go(['folder' => $node['id']]) }}" @if ($here === $node['id']) aria-current="page" @endif>{{ str_repeat('· ', $node['depth']) }}{{ $node['name'] }}</a>
        @endforeach
        <a href="{{ $go(['trashed' => 1]) }}" @if ($trashed) aria-current="page" @endif>{{ __('Trash') }}</a>
    </nav>

    <div id="media-drop">
        @if ($trashed)
            <h2>{{ __('Trash') }}</h2>
            <p class="lp-muted">{{ __('Items stay here for :days days, then go for good.', ['days' => \Launchpad\Models\MediaFile::trashDays()]) }}</p>
        @else
            <h2>{{ $folder['name'] ?? __('All media') }}</h2>
            @if ($breadcrumbs)
                <p class="lp-muted"><a href="{{ $go() }}">{{ __('All media') }}</a>@foreach ($breadcrumbs as $crumb) / <a href="{{ $go(['folder' => $crumb['id']]) }}">{{ $crumb['name'] }}</a>@endforeach</p>
            @endif

            <div class="lp-media__tools">
                <form method="post" action="{{ route('workspace.media.store', $slug) }}" enctype="multipart/form-data" id="media-upload">
                    @csrf
                    <input type="hidden" name="folder_id" value="{{ $here }}">
                    <div class="lp-field">
                        <label for="media-file">{{ __('Add a file') }}</label>
                        <input id="media-file" type="file" name="file" accept="{{ \Launchpad\Media\Library::accept() }}" required>
                        @error('file')<p class="lp-error">{{ $message }}</p>@enderror
                        <p class="lp-muted" data-media-hint hidden>{{ __('You can also drop files here.') }}</p>
                    </div>
                    <div id="media-error" class="lp-error" role="alert" hidden></div>
                    <div id="media-duplicate" role="alert" hidden>
                        <p>{{ __('This team already has that file.') }} <a data-media-existing href="#"></a></p>
                        <button class="lp-btn lp-btn--quiet" type="button" data-media-use>{{ __('Use the existing one') }}</button>
                        <button class="lp-btn lp-btn--quiet" type="button" data-media-anyway>{{ __('Upload anyway') }}</button>
                    </div>
                    <button class="lp-btn" type="submit">{{ __('Upload') }}</button>
                </form>
                <form method="post" action="{{ route('workspace.media.folders.store', $slug) }}">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $here }}">
                    <div class="lp-field">
                        <label for="folder-name">{{ $here ? __('New folder in here') : __('New folder') }}</label>
                        <input id="folder-name" type="text" name="name" maxlength="100" required>
                        @error('name')<p class="lp-error">{{ $message }}</p>@enderror
                    </div>
                    <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Make folder') }}</button>
                </form>
            </div>

            <details class="lp-media__search" @if (array_filter([$filters['q'], $filters['kind'], $filters['tag'], $filters['month']])) open @endif>
                <summary>{{ __('Search and filter') }}</summary>
                <form method="get" action="{{ route('workspace.media', $slug) }}" role="search">
                    @if ($here)<input type="hidden" name="folder" value="{{ $here }}">@endif
                    <div class="lp-field"><label for="media-q">{{ __('Search') }}</label><input id="media-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="200"></div>
                    <div class="lp-field"><label for="media-kind">{{ __('Type') }}</label>
                        <select id="media-kind" name="kind"><option value="">{{ __('All types') }}</option>@foreach (['image' => __('Images'), 'video' => __('Video'), 'audio' => __('Audio'), 'document' => __('Documents'), 'other' => __('Other')] as $value => $label)<option value="{{ $value }}" @selected($filters['kind'] === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="lp-field"><label for="media-tag">{{ __('Tag') }}</label>
                        <select id="media-tag" name="tag"><option value="">{{ __('Any tag') }}</option>@foreach ($tags as $tag)<option @selected($filters['tag'] === $tag)>{{ $tag }}</option>@endforeach</select></div>
                    <div class="lp-field"><label for="media-month">{{ __('Month') }}</label><input id="media-month" type="month" name="month" value="{{ $filters['month'] }}"></div>
                    <div class="lp-field"><label for="media-sort">{{ __('Sort by') }}</label>
                        <select id="media-sort" name="sort">@foreach (['newest' => __('Newest'), 'oldest' => __('Oldest'), 'name' => __('Name'), 'size' => __('Size')] as $value => $label)<option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>@endforeach</select></div>
                    <label><input type="checkbox" name="everywhere" value="1" @checked($filters['everywhere'])> {{ $here ? __('Search inside sub-folders too') : __('Search every folder') }}</label>
                    <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Search') }}</button>
                </form>
            </details>
        @endif

        @if ($folders)
            <h3>{{ __('Folders') }}</h3>
            <ul class="lp-list">
            @foreach ($folders as $item)
                <li>
                    @if ($trashed)
                        <span>{{ $item['name'] }}</span>
                        @if ($item['can_delete'])
                            <span>
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.folders.restore', [$slug, $item['id']]) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Restore') }}</button></form>
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.folders.destroy', [$slug, $item['id']]) }}" data-confirm="{{ __('Delete this folder and everything in it for good?') }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete for good') }}</button></form>
                            </span>
                        @endif
                    @else
                        <a href="{{ $go(['folder' => $item['id']]) }}">{{ $item['name'] }}</a>
                        <details class="lp-media__edit">
                            <summary>{{ __('Edit') }}</summary>
                            <form method="post" action="{{ route('workspace.media.folders.update', [$slug, $item['id']]) }}">@csrf @method('patch')
                                <div class="lp-field"><label for="folder-rename-{{ $item['id'] }}">{{ __('Name') }}</label><input id="folder-rename-{{ $item['id'] }}" type="text" name="name" value="{{ $item['name'] }}" maxlength="100" required></div>
                                <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Save') }}</button>
                            </form>
                            @if ($item['can_delete'])
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.folders.trash', [$slug, $item['id']]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete folder') }}</button></form>
                            @endif
                        </details>
                    @endif
                </li>
            @endforeach
            </ul>
        @endif

        @if ($files)
            <h3>{{ __('Files') }}</h3>
            <ul class="lp-list lp-media__files">
            @foreach ($files as $file)
                <li>
                    <div class="lp-media__file">
                        @if ($file['kind'] === 'image')<img src="{{ $file['url'] }}" alt="{{ $file['alt'] }}" width="48" height="48" loading="lazy">@endif
                        <div>
                            <a href="{{ $file['url'] }}">{{ $file['name'] }}</a>
                            <p class="lp-muted">{{ $file['size_label'] }} · {{ $file['uploaded_by_name'] ?? __('Someone who has left') }}@if ($file['tags']) · {{ implode(', ', $file['tags']) }}@endif</p>
                        </div>
                    </div>
                    @if ($trashed)
                        @if ($file['can_delete'])
                            <span>
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.restore', [$slug, $file['id']]) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Restore') }}</button></form>
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.destroy', [$slug, $file['id']]) }}" data-confirm="{{ __('Delete this file for good?') }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete for good') }}</button></form>
                            </span>
                        @endif
                    @else
                        <details class="lp-media__edit">
                            <summary>{{ __('Edit') }}</summary>
                            <form method="post" action="{{ route('workspace.media.update', [$slug, $file['id']]) }}">@csrf @method('patch')
                                <div class="lp-field"><label for="file-name-{{ $file['id'] }}">{{ __('Name') }}</label><input id="file-name-{{ $file['id'] }}" type="text" name="name" value="{{ $file['name'] }}" maxlength="200" required></div>
                                @if ($file['kind'] === 'image')
                                    <div class="lp-field"><label for="file-alt-{{ $file['id'] }}">{{ __('Alt text') }}</label><input id="file-alt-{{ $file['id'] }}" type="text" name="alt" value="{{ $file['alt'] }}" maxlength="500"></div>
                                @endif
                                <div class="lp-field"><label for="file-tags-{{ $file['id'] }}">{{ __('Tags, with commas between') }}</label><input id="file-tags-{{ $file['id'] }}" type="text" name="tags_text" value="{{ implode(', ', $file['tags']) }}" maxlength="2000"></div>
                                <div class="lp-field"><label for="file-folder-{{ $file['id'] }}">{{ __('Folder') }}</label>
                                    <select id="file-folder-{{ $file['id'] }}" name="folder_id"><option value="">{{ __('No folder') }}</option>@foreach ($tree as $node)<option value="{{ $node['id'] }}" @selected($file['folder_id'] === $node['id'])>{{ str_repeat('· ', $node['depth']) }}{{ $node['name'] }}</option>@endforeach</select></div>
                                <div class="lp-field"><label for="file-notes-{{ $file['id'] }}">{{ __('Notes') }}</label><textarea id="file-notes-{{ $file['id'] }}" name="notes" rows="2" maxlength="5000">{{ $file['notes'] }}</textarea></div>
                                <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Save') }}</button>
                            </form>
                            @if ($file['can_delete'])
                                <form class="lp-inline" method="post" action="{{ route('workspace.media.trash', [$slug, $file['id']]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete file') }}</button></form>
                            @endif
                        </details>
                    @endif
                </li>
            @endforeach
            </ul>
            @if ($pages > 1)
                <p class="lp-muted">{{ __('Page :page of :pages', ['page' => $page, 'pages' => $pages]) }}
                    @if ($page > 1)<a href="{{ $go($keep + ['page' => $page - 1]) }}">{{ __('Newer') }}</a>@endif
                    @if ($page < $pages)<a href="{{ $go($keep + ['page' => $page + 1]) }}">{{ __('Older') }}</a>@endif
                </p>
            @endif
        @elseif (! $folders)
            <p>{{ $trashed ? __('The trash is empty.') : __('Nothing here yet.') }}</p>
        @endif
    </div>
</div>
@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
(() => {
    // not an inline onsubmit: a strict CSP blocks those, and the delete would go through unasked
    document.addEventListener('submit', (event) => {
        const message = event.target.dataset && event.target.dataset.confirm;
        if (message && !confirm(message)) event.preventDefault();
    });

    const form = document.getElementById('media-upload');
    const input = document.getElementById('media-file');
    if (!form || !input || !window.crypto || !crypto.subtle) return;

    const alertBox = document.getElementById('media-error');
    const fail = (text) => { alertBox.textContent = text; alertBox.hidden = false; };

    const box = document.getElementById('media-duplicate');
    const token = @json(csrf_token());
    const checkUrl = @json(route('workspace.media.check', $slug));
    const folder = form.elements.folder_id.value;
    document.querySelector('[data-media-hint]').hidden = false;

    const hash = async (file) => Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256', await file.arrayBuffer())), (b) => b.toString(16).padStart(2, '0')).join('');
    const duplicate = async (file) => {
        const response = await fetch(checkUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ hash: await hash(file) }), credentials: 'same-origin' });
        return response.ok ? (await response.json()).file : null;
    };
    const send = async (files) => {
        for (const file of files) {
            const data = new FormData();
            data.append('file', file);
            if (folder) data.append('folder_id', folder);
            const response = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token }, body: data, credentials: 'same-origin' });
            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                const errors = body.errors || {};
                return fail((errors.file || errors.folder_id || [])[0] || @json(__("The upload didn't work.")));
            }
        }
        location.reload();
    };

    // files waiting on the "use the existing one / upload anyway" choice
    let waiting = [];
    const upload = async (files) => {
        waiting = [];
        for (const file of files) {
            const found = await duplicate(file);
            if (!found) { await send([file]); continue; }
            waiting = [file];
            const link = box.querySelector('[data-media-existing]');
            link.textContent = found.name;
            link.href = found.url;
            box.hidden = false;
            return;
        }
    };

    box.querySelector('[data-media-use]').addEventListener('click', () => { box.hidden = true; input.value = ''; });
    box.querySelector('[data-media-anyway]').addEventListener('click', () => send(waiting));
    // if the script cannot reach the server, the plain form still works
    form.addEventListener('submit', (event) => {
        if (!input.files.length) return;
        event.preventDefault();
        upload([...input.files]).catch(() => form.submit());
    });

    const drop = document.getElementById('media-drop');
    drop.addEventListener('dragover', (event) => event.preventDefault());
    drop.addEventListener('drop', (event) => { event.preventDefault(); upload([...event.dataTransfer.files]).catch(() => fail(@json(__("The upload didn't work.")))); });
})();
</script>
@endpush
@endsection
