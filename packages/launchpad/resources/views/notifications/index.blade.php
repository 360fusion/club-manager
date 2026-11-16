@extends('launchpad::layouts.account')
@section('title', __('Notifications'))
@section('account')
<div class="lp-card">
    <div class="lp-card__head">
        <h1>{{ __('Notifications') }}</h1>
        @if ($unread)<form method="post" action="{{ route('notifications.read-all') }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Mark all as read') }}</button></form>@endif
    </div>
    @forelse ($rows as $row)
        <div class="lp-note @unless ($row['read']) lp-note--unread @endunless">
            <div>
                <p class="lp-note__title"><a href="{{ route('notifications.open', $row['id']) }}">{{ $row['title'] }}</a>@unless ($row['read'])<span class="lp-skip"> {{ __('(unread)') }}</span>@endunless</p>
                @if ($row['message'])<p>{{ $row['message'] }}</p>@endif
                <time class="lp-muted" datetime="{{ $row['at']?->toIso8601String() }}">{{ $row['at']?->diffForHumans() }}</time>
            </div>
            <div class="lp-note__actions">
                @unless ($row['read'])<form class="lp-inline" method="post" action="{{ route('notifications.read', $row['id']) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Mark as read') }}</button></form>@endunless
                <form class="lp-inline" method="post" action="{{ route('notifications.destroy', $row['id']) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete') }}</button></form>
            </div>
        </div>
    @empty
        <p>{{ __('You have no notifications. When something happens that you should know about, it appears here and under the bell.') }}</p>
    @endforelse
    @if ($page->hasPages())
        <p class="lp-links">
            @if ($page->previousPageUrl())<a href="{{ $page->previousPageUrl() }}">{{ __('Newer') }}</a>@else<span></span>@endif
            @if ($page->nextPageUrl())<a href="{{ $page->nextPageUrl() }}">{{ __('Older') }}</a>@endif
        </p>
    @endif
</div>
@endsection
