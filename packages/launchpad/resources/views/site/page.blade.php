@extends('launchpad::layouts.base')
@section('title', $page->title)
@section('description', $page->description)
@if ($page->noindex())@section('noindex', '1')@endif
@section('main-class', 'lp-main--wide')
@section('content')
<article class="lp-card lp-prose">
    @if ($isLegal && $status === 'template' && ! app()->isProduction())
        <div class="lp-alert lp-alert--error" role="note"><strong>{{ __('Template text.') }}</strong> {{ __('This page has not been read and approved yet. It is shown like this only outside production, and the doctor check stops a production site that still has it.') }}</div>
    @endif
    <h1>{{ $page->title }}</h1>
    @if ($page->updated())<p class="lp-muted">{{ __('Last updated :date', ['date' => \Illuminate\Support\Carbon::parse($page->updated())->translatedFormat('j F Y')]) }}</p>@endif
    {!! $page->html !!}
</article>
@endsection
