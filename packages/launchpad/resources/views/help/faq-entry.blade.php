@extends('launchpad::layouts.base')
@section('title', $faq->title)
@section('description', \Illuminate\Support\Str::limit($faq->text, 150))
@section('main-class', 'lp-main--wide')
@section('content')
<article class="lp-card lp-prose">
    <p><a href="{{ route('faq') }}">{{ __('Frequently asked questions') }}</a></p>
    <h1>{{ $faq->title }}</h1>
    {!! $faq->html !!}
</article>
@endsection
