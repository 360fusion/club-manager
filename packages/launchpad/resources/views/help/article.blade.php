@extends('launchpad::layouts.base')
@section('title', $article->title)
@section('description', $article->description)
@section('main-class', 'lp-main--wide')
@section('content')
<article class="lp-card lp-prose">
    <p><a href="{{ route('help') }}">{{ __('Help centre') }}</a>@if ($category) / <a href="{{ route('help.category', $article->category) }}">{{ $category['title'] }}</a>@endif</p>
    <h1>{{ $article->title }}</h1>
    <x-launchpad::status />
    {!! $article->html !!}
    @if ($related->isNotEmpty())
        <h2>{{ __('Related') }}</h2>
        <ul>@foreach ($related as $item)<li><a href="{{ route('help.article', $item->slug) }}">{{ $item->title }}</a></li>@endforeach</ul>
    @endif
</article>
<div class="lp-card">
    <h2>{{ __('Was this helpful?') }}</h2>
    <form method="post" action="{{ route('help.feedback', $article->slug) }}">
        @csrf
        <div class="lp-field">
            <label for="comment">{{ __('Anything to add? (optional)') }}</label>
            <textarea id="comment" name="comment" rows="3" maxlength="1000"></textarea>
        </div>
        <button class="lp-btn" name="helpful" value="1" type="submit">{{ __('Yes') }}</button>
        <button class="lp-btn lp-btn--quiet" name="helpful" value="0" type="submit">{{ __('No') }}</button>
    </form>
</div>
@endsection
