@extends('launchpad::layouts.base')
@section('title', __('Frequently asked questions'))
@section('description', __('Short answers to the questions we are asked most.'))
@section('main-class', 'lp-main--wide')
@section('content')
<div class="lp-card">
    <h1>{{ __('Frequently asked questions') }}</h1>
    @include('launchpad::help.search-box', ['action' => 'faq', 'query' => $query, 'label' => 'Search the questions'])
    @forelse ($faqs as $faq)
        <details><summary>{{ $faq->title }}</summary><div class="lp-prose">{!! $faq->html !!}<p><a href="{{ route('faq.show', $faq->slug) }}">{{ __('Link to this answer') }}</a></p></div></details>
    @empty
        <p>{{ \Launchpad\Support\Trans::html('No question matched. :ask.', ['ask' => new \Illuminate\Support\HtmlString('<a href="'.e(route('contact')).'">'.e(__('Ask us')).'</a>')]) }}</p>
    @endforelse
</div>
@endsection
