@extends('launchpad::layouts.base')
@section('title', __('Roadmap'))
@section('main-class', 'lp-main--wide')
@section('content')
<div class="lp-card">
    <h1>{{ __('Roadmap') }}</h1>
    <p class="lp-muted">{{ __('What we are building, what is planned, and ideas you can vote for.') }}</p>
    <x-launchpad::status />
</div>
@foreach ($groups as $status => $items)
    @if ($items->isNotEmpty())
    <section class="lp-card">
        <h2>{{ __(\Launchpad\Models\RoadmapItem::HEADINGS[$status]) }}</h2>
        <ul class="lp-list">
            @foreach ($items as $item)
                <li>
                    <span><strong>{{ $item->title }}</strong>@if ($item->description)<br><span class="lp-muted">{{ $item->description }}</span>@endif</span>
                    @if (in_array($status, ['idea', 'planned', 'building'], true))
                        @auth
                        <form class="lp-inline" method="post" action="{{ route('roadmap.vote', $item) }}">@csrf
                            <button class="lp-btn {{ in_array($item->id, $voted, true) ? '' : 'lp-btn--quiet' }}" type="submit" aria-pressed="{{ in_array($item->id, $voted, true) ? 'true' : 'false' }}">{{ trans_choice(':count vote|:count votes', $item->votes) }}</button>
                        </form>
                        @else
                        <span class="lp-muted">{{ trans_choice(':count vote|:count votes', $item->votes) }}</span>
                        @endauth
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
    @endif
@endforeach
<div class="lp-card">
    <h2>{{ __('Suggest an idea') }}</h2>
    @auth
    <form method="post" action="{{ route('roadmap.store') }}">
        @csrf
        <x-launchpad::field name="title" :label="__('Your idea')" maxlength="160" />
        <div class="lp-field">
            <label for="description">{{ __('More detail (optional)') }}</label>
            <textarea id="description" name="description" maxlength="2000" rows="3">{{ old('description') }}</textarea>
            @error('description')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <button class="lp-btn" type="submit">{{ __('Suggest it') }}</button>
    </form>
    @else
    <p><a href="{{ route('login') }}">{{ __('Sign in to suggest an idea or vote.') }}</a></p>
    @endauth
</div>
@endsection
