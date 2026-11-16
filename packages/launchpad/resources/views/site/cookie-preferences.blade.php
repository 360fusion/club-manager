@extends('launchpad::layouts.base')
@section('title', __('Cookie preferences'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Cookie preferences') }}</h1>
    <p class="lp-muted">{{ \Launchpad\Support\Trans::html('Strictly necessary cookies are always on: without them you could not sign in. The others only run if you allow them. See the :policy.', ['policy' => new \Illuminate\Support\HtmlString('<a href="/cookies">'.e(__('Cookie Policy')).'</a>')]) }}</p>
    <form method="post" action="{{ route('cookie-consent') }}">
        @csrf
        <input type="hidden" name="choice" value="custom">
        @foreach (\Launchpad\Site\Consent::CATEGORIES as $category)
            <div class="lp-check"><input type="checkbox" id="c-{{ $category }}" name="categories[]" value="{{ $category }}" @checked(\Launchpad\Site\Consent::granted($category))><label for="c-{{ $category }}">{{ \Launchpad\Site\Consent::label($category) }}</label></div>
        @endforeach
        <button class="lp-btn" type="submit">{{ __('Save my choice') }}</button>
    </form>
</div>
@endsection
