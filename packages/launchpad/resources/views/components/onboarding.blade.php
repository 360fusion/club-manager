@php($card = auth()->check() ? \Launchpad\Onboarding\Onboarding::forUser(auth()->user()) : null)
@if ($card)
<section class="lp-card lp-card--accent" aria-labelledby="lp-onboarding-title">
    <h2 id="lp-onboarding-title">{{ __('Getting started') }} <span class="lp-muted">{{ __(':done of :total done', ['done' => $card['done'], 'total' => $card['total']]) }}</span></h2>
    <ol class="lp-steps">
        @foreach ($card['steps'] as $step)
            <li>
                <span class="lp-step" aria-hidden="true">{{ $step['done'] ? '✓' : $loop->iteration }}</span>
                <div>@if ($step['done'])<s>{{ $step['label'] }}</s> <span class="lp-skip">{{ __('Done') }}</span>@else<a href="{{ $step['url'] }}">{{ $step['label'] }}</a>@endif</div>
            </li>
        @endforeach
    </ol>
    <form method="post" action="{{ $card['dismissUrl'] }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Hide this') }}</button></form>
</section>
@endif
