@extends('launchpad::layouts.base')
@section('title', __('Contact us'))
@section('description', __('Send us a message and we will reply by email.'))
@section('content')
<div class="lp-card">
    <h1>{{ __('Contact us') }}</h1>
    @if (session('sent'))
        <div class="lp-alert" role="status">{{ \Launchpad\Support\Trans::html('Thank you, we have your message. Your reference is :reference; we have emailed you a copy.', ['reference' => new \Illuminate\Support\HtmlString('<strong>'.e(session('sent')).'</strong>')]) }}</div>
    @else
        <p class="lp-muted">{{ \Launchpad\Support\Trans::html('Tell us what you need and we will reply by email. Looking for an answer now? Try the :help.', ['help' => new \Illuminate\Support\HtmlString('<a href="'.e(route('help')).'">'.e(__('help centre')).'</a>')]) }}</p>
        <form method="post" action="{{ route('contact.send') }}">
            @csrf
            <x-launchpad::field name="name" :label="__('Your name')" autocomplete="name" :value="$user?->name" />
            <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="email" :value="$user?->email" />
            @if ($topics)
                <div class="lp-field">
                    <label for="topic">{{ __('Topic') }}</label>
                    <select id="topic" name="topic">@foreach ($topics as $topic)<option value="{{ $topic }}" @selected(old('topic') === $topic)>{{ __($topic) }}</option>@endforeach</select>
                    @error('topic')<p class="lp-error">{{ $message }}</p>@enderror
                </div>
            @endif
            <div class="lp-field">
                <label for="message">{{ __('Message') }}</label>
                <textarea id="message" name="message" rows="6" required @error('message') aria-invalid="true" aria-describedby="message-error" @enderror>{{ old('message') }}</textarea>
                @error('message')<p class="lp-error" id="message-error">{{ $message }}</p>@enderror
            </div>
            {{-- a field only a script fills in: people never see it --}}
            <div class="lp-skip" aria-hidden="true"><label for="website">{{ __('Leave this empty') }}</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
            <x-launchpad::turnstile :key="$turnstileKey" />
            <button class="lp-btn" type="submit">{{ __('Send message') }}</button>
        </form>
    @endif
</div>
@endsection
