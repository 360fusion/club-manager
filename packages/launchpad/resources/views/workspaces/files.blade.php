@extends('launchpad::layouts.account')
@section('title', __(':team files', ['team' => $workspace->name]))
@section('account')
<div class="lp-card">
    <h1>{{ __(':team: Files', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ $summary }}. {{ __('Anyone in the team can add a file and download any of them.') }}</p>
    @if ($files->isEmpty())
        <p>{{ __('No files yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('Name') }}</th><th scope="col">{{ __('Size') }}</th><th scope="col">{{ __('Added by') }}</th><th scope="col">{{ __('When') }}</th><th scope="col"><span class="lp-skip">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                @foreach ($files as $file)
                    <tr>
                        <td><a href="{{ route('workspace.files.download', [$workspace->slug, $file->id]) }}">{{ $file->name }}</a></td>
                        <td>{{ \Illuminate\Support\Number::fileSize($file->size, precision: 1) }}</td>
                        <td>{{ $file->uploader?->name ?? __('Someone who has left') }}</td>
                        <td>{{ $file->created_at->diffForHumans() }}</td>
                        <td>@if ($manages || $file->uploaded_by === $userId)<form class="lp-inline" method="post" action="{{ route('workspace.files.destroy', [$workspace->slug, $file->id]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete') }}</button></form>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="lp-card">
    <h2>{{ __('Add a file') }}</h2>
    <form method="post" action="{{ route('workspace.files.store', $workspace->slug) }}" enctype="multipart/form-data">
        @csrf
        <div class="lp-field">
            <label for="file">{{ __('File') }}</label>
            <input id="file" type="file" name="file" accept="{{ $accept }}" required>
            @error('file')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <button class="lp-btn" type="submit">{{ __('Upload') }}</button>
    </form>
</div>
@endsection
