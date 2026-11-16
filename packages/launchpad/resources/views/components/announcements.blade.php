@foreach (auth()->check() ? app(\Launchpad\Site\Announcements::class)->forUser(auth()->user()) : [] as $announcement)
<section class="lp-alert {{ $announcement->level === 'warning' ? 'lp-alert--warning' : '' }}" aria-labelledby="lp-announcement-{{ $announcement->id }}">
    <h2 id="lp-announcement-{{ $announcement->id }}" class="lp-alert__title">{{ $announcement->title }}</h2>
    {{-- staff-written Markdown, HTML stripped by the renderer --}}
    {!! $announcement->html() !!}
    @if ($announcement->dismissible)
        <form class="lp-inline" method="post" action="{{ route('announcements.dismiss', $announcement) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Hide') }}</button></form>
    @endif
</section>
@endforeach
