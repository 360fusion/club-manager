@extends('launchpad::layouts.base')
@section('title', $query === '' ? __('Search') : __('Results for :query', ['query' => $query]))
@section('noindex', '1')
@section('main-class', 'lp-main--wide')
@section('content')
<div class="lp-card">
    <h1>{{ __('Search the help centre') }}</h1>
    @include('launchpad::help.search-box', ['query' => $query])
    @if ($query !== '')
        @forelse ($results as $result)
            <p>
                <a href="{{ $result['kind'] === 'faq' ? route('faq.show', $result['article']->slug) : route('help.article', $result['article']->slug) }}"><strong>{{ $result['article']->title }}</strong></a>
                <span class="lp-muted">{{ $result['kind'] === 'faq' ? __('FAQ') : __('Guide') }}</span><br>
                <span class="lp-muted">{{ \Illuminate\Support\Str::limit($result['article']->description ?: $result['article']->text, 140) }}</span>
            </p>
        @empty
            <p>{{ \Launchpad\Support\Trans::html('Nothing matched ":query". Try other words, or :ask.', ['query' => $query, 'ask' => new \Illuminate\Support\HtmlString('<a href="'.e(route('contact')).'">'.e(__('ask us')).'</a>')]) }}</p>
        @endforelse
    @endif
</div>
@endsection
