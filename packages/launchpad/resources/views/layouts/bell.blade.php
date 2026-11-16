@php($bell = \Launchpad\Notifications\Bell::summary(auth()->user()))
<details class="lp-bell">
    <summary aria-label="{{ $bell['unread'] ? __('Notifications, :count unread', ['count' => $bell['unread']]) : __('Notifications') }}">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 0 0-4-5.7V5a2 2 0 1 0-4 0v.3C7.7 6.2 6 8.4 6 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
        @if ($bell['unread'])<span class="lp-badge">{{ $bell['unread'] > 9 ? '9+' : $bell['unread'] }}</span>@endif
    </summary>
    <div class="lp-bell__panel">
        <div class="lp-bell__head">
            <h2>{{ __('Notifications') }}</h2>
            @if ($bell['unread'])
                <form method="post" action="{{ route('notifications.read-all') }}">@csrf <button type="submit">{{ __('Mark all as read') }}</button></form>
            @endif
        </div>
        @forelse ($bell['latest'] as $row)
            <a class="lp-bell__item @unless ($row['read']) lp-bell__item--unread @endunless" href="{{ route('notifications.open', $row['id']) }}">
                <strong>{{ $row['title'] }}@unless ($row['read'])<span class="lp-skip"> {{ __('(unread)') }}</span>@endunless</strong>
                @if ($row['message'])<span>{{ $row['message'] }}</span>@endif
                <time class="lp-muted" datetime="{{ $row['at']?->toIso8601String() }}">{{ $row['at']?->diffForHumans() }}</time>
            </a>
        @empty
            <p class="lp-bell__empty lp-muted">{{ __('You are all caught up.') }}</p>
        @endforelse
        <a class="lp-bell__all" href="{{ route('notifications.index') }}">{{ __('View all notifications') }}</a>
    </div>
</details>
