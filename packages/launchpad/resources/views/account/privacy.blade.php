@extends('launchpad::layouts.account')
@section('title', __('Data and privacy'))
@section('account')
<div class="lp-card">
    <h1>{{ __('Data and privacy') }}</h1>
    <h2>{{ __('Your data') }}</h2>
    <p class="lp-muted">{{ __('A copy of what we hold on your account, as a file you can keep.') }}</p>
    <a class="lp-btn" href="{{ route('account.export') }}">{{ __('Download my data') }}</a>
</div>
<div class="lp-card">
    <h2>{{ __('Close your account') }}</h2>
    <p>{{ trans_choice('Your account stops working at once and what you pay for is ended. We keep it for :count day in case you change your mind, then it is removed for good.|Your account stops working at once and what you pay for is ended. We keep it for :count days in case you change your mind, then it is removed for good.', (int) $retentionDays) }}</p>
    @error('account')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    <form method="post" action="{{ route('account.close') }}">
        @csrf
        <x-launchpad::field name="confirm" :label="__('Type CLOSE to confirm')" autocomplete="off" />
        <button class="lp-btn lp-btn--danger" type="submit">{{ __('Close my account') }}</button>
    </form>
</div>
@endsection
