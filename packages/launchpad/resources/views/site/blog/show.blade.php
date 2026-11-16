@extends('launchpad::layouts.base')
@section('title', $post->title)
@section('description', $post->summary ?? '')
@section('content')
<article class="lp-card lp-prose">
    <p><a href="{{ route('blog.index') }}">{{ __('Blog') }}</a></p>
    <h1>{{ $post->title }}</h1>
    <p class="lp-muted"><time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>@if ($post->author) · {{ $post->author->name }}@endif</p>
    {!! $post->html() !!}
</article>
@endsection
