@extends('launchpad::layouts.account')
@section('title', __('No permission'))
@section('account')
<div class="lp-card">
    <h1>{{ __('You do not have permission for this') }}</h1>
    @php($parts = ['team' => new \Illuminate\Support\HtmlString('<strong>'.e($workspace->name).'</strong>'), 'owner' => $ownerName, 'role' => mb_strtolower($role->label()), 'what' => $what])
    @if ($ownerName)
    <p>{{ \Launchpad\Support\Trans::html('You are signed in to :team (owned by :owner) as a :role, and your permissions there do not include the right to :what.', $parts) }}</p>
    @else
    <p>{{ \Launchpad\Support\Trans::html('You are signed in to :team as a :role, and your permissions there do not include the right to :what.', $parts) }}</p>
    @endif
    <p class="lp-muted">{{ $ownerName ? __('Ask :person if you need it.', ['person' => $ownerName]) : __('Ask an owner or admin if you need it.') }}</p>
    <p>
        @if ($ownUrl)<a class="lp-btn" href="{{ $ownUrl }}">{{ __('Go to your own :team team', ['team' => $ownName]) }}</a>@endif
        <a class="lp-btn lp-btn--quiet" href="{{ route('workspaces.index') }}">{{ __('Your teams') }}</a>
    </p>
</div>
@endsection
