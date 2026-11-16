@extends('launchpad::layouts.base')
@section('main-class', 'lp-main--account')
@section('content')
<div class="lp-account">
    <nav class="lp-account__menu" aria-label="{{ __('Account') }}">
        <h2 class="lp-account__title">{{ __('My account') }}</h2>
        <ul>
            @foreach (\Launchpad\Http\AccountMenu::items(auth()->user()) as [$label, $href, $current])
                <li><a href="{{ $href }}" @if ($current) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
    <div class="lp-account__body">
        <x-launchpad::status />
        <x-launchpad::announcements />
        <x-launchpad::onboarding />
        @yield('account')
    </div>
</div>
@endsection
