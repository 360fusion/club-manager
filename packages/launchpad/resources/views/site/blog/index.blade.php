@extends('launchpad::layouts.base')
@section('title', __('Blog'))
@push('head')<link rel="alternate" type="application/atom+xml" title="{{ __('Blog') }}" href="{{ route('blog.feed') }}">@endpush
@section('content')
<div class="lp-card lp-prose">
    <h1>{{ __('Blog') }}</h1>
    @forelse ($posts as $post)
        <article>
            <h2><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>
            <p class="lp-muted"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time></p>
            @if ($post->summary)<p>{{ $post->summary }}</p>@endif
        </article>
    @empty
        <p>{{ __('Nothing here yet.') }}</p>
    @endforelse
    <p>
        @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}">{{ __('Newer') }}</a>@endif
        @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}">{{ __('Older') }}</a>@endif
    </p>
</div>
@endsection
