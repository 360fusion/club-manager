@extends('launchpad::layouts.account')
@section('title', __('Email preferences'))
@section('account')
<div class="lp-card">
    <h1>{{ __('Email preferences') }}</h1>
    <p class="lp-muted">{{ __("Choose which optional emails you get. Emails about your account's security, and receipts, are always sent.") }}</p>
    @if ($choices->isEmpty())
        <p>{{ __('There are no optional emails to choose from.') }}</p>
    @else
        <form method="post" action="{{ route('account.notifications.update') }}">
            @csrf @method('put')
            @foreach ($choices as $choice)
                <label class="lp-check">
                    <input type="checkbox" name="notify[{{ $choice['key'] }}]" value="1" @checked($choice['on'])>
                    <span><strong>{{ $choice['label'] }}</strong><br><span class="lp-muted">{{ $choice['about'] }}</span></span>
                </label>
            @endforeach
            <button class="lp-btn" type="submit">{{ __('Save') }}</button>
        </form>
    @endif
</div>
@endsection
