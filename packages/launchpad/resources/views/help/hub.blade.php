@extends('launchpad::layouts.base')
@section('title', __('Help centre'))
@section('description', __('Guides and answers for your account, your team and billing.'))
@section('main-class', 'lp-main--wide')
@section('content')
<div class="lp-card">
    <h1>{{ __('How can we help?') }}</h1>
    @include('launchpad::help.search-box')
    <p class="lp-muted"><a href="{{ route('faq') }}">{{ __('Frequently asked questions') }}</a> · <a href="{{ route('contact') }}">{{ __('Contact us') }}</a></p>
</div>
<div class="lp-plans">
@foreach ($categories as $slug => $info)
    <section class="lp-card" aria-labelledby="cat-{{ $slug }}">
        <h2 id="cat-{{ $slug }}"><a href="{{ route('help.category', $slug) }}">{{ $info['title'] }}</a></h2>
        <p class="lp-muted">{{ $info['description'] }}</p>
        <ul>@foreach ($help->inCategory($slug)->take(5) as $article)<li><a href="{{ route('help.article', $article->slug) }}">{{ $article->title }}</a></li>@endforeach</ul>
    </section>
@endforeach
</div>
@endsection
