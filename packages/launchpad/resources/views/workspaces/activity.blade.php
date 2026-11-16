@extends('launchpad::layouts.account')
@section('title', __(':team activity', ['team' => $workspace->name]))
@section('account')
<div class="lp-card">
    <h1>{{ __(':team: Team activity', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ __("Changes to the team's people, roles, API, webhooks, files and plan, newest first.") }} {{ __('The lines below are a log: they are written once, in English.') }}</p>
    @if ($events->isEmpty())
        <p>{{ __('Nothing yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('What happened') }}</th><th scope="col">{{ __('When') }}</th></tr></thead>
                <tbody>
                @foreach ($events as $event)
                    <tr>
                        <td>{{ $event->actor_name }} {{ $event->summary }}</td>
                        <td><time datetime="{{ $event->created_at->toIso8601String() }}" title="{{ $event->created_at->toDayDateTimeString() }}">{{ $event->created_at->diffForHumans() }}</time></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p><a class="lp-btn lp-btn--quiet" href="{{ route('workspace.activity.download', $workspace->slug) }}">{{ __('Download all (CSV)') }}</a></p>
        <p>
            @if ($events->previousPageUrl())<a href="{{ $events->previousPageUrl() }}">{{ __('Newer') }}</a>@endif
            @if ($events->nextPageUrl())<a href="{{ $events->nextPageUrl() }}">{{ __('Older') }}</a>@endif
        </p>
    @endif
</div>
@endsection
