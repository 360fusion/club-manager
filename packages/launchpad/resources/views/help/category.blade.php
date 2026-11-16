@extends('launchpad::layouts.base')
@section('title', $info['title'])
@section('description', $info['description'])
@section('main-class', 'lp-main--wide')
@section('content')
<div class="lp-card">
    <p><a href="{{ route('help') }}">{{ __('Help centre') }}</a></p>
    <h1>{{ $info['title'] }}</h1>
    <p class="lp-muted">{{ $info['description'] }}</p>
    <ul>@foreach ($articles as $article)<li><a href="{{ route('help.article', $article->slug) }}">{{ $article->title }}</a><br><span class="lp-muted">{{ $article->description }}</span></li>@endforeach</ul>
</div>
@endsection
