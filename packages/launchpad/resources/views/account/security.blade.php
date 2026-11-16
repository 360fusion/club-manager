@extends('launchpad::layouts.account')
@section('title', __('Security'))
@section('account')
<div class="lp-card">
    <h1>{{ __('Security') }}</h1>
    <h2>{{ __('Password') }}</h2>
    <form method="post" action="{{ route('password.update') }}">
        @csrf @method('put')
        @if ($needsCurrentPassword)
            <x-launchpad::field name="current_password" :label="__('Current password')" type="password" autocomplete="current-password" />
        @endif
        <x-launchpad::field name="password" :label="__('New password')" type="password" autocomplete="new-password" />
        <x-launchpad::field name="password_confirmation" :label="__('Confirm new password')" type="password" autocomplete="new-password" />
        <button class="lp-btn" type="submit">{{ __('Save password') }}</button>
    </form>
</div>
<div class="lp-card">
    <h2>{{ __('Two-factor sign-in') }}</h2>
    @if ($recoveryCodes)
        <div class="lp-alert" role="status">
            <p>{{ \Launchpad\Support\Trans::html(':keep Each works once, and they are not shown again.', ['keep' => new \Illuminate\Support\HtmlString('<strong>'.e(__('Keep these recovery codes somewhere safe.')).'</strong>')]) }}</p>
            <ul class="lp-codes">@foreach ($recoveryCodes as $code)<li>{{ $code }}</li>@endforeach</ul>
        </div>
    @endif
    @if ($twoFactorOn)
        <p>{{ __('Two-factor sign-in is on.') }} {{ trans_choice('{0} You have no recovery codes left.|{1} You have :count recovery code left.|[2,*] You have :count recovery codes left.', count($user->two_factor_recovery_codes ?? [])) }}</p>
        <form class="lp-inline" method="post" action="{{ route('two-factor.recovery-codes') }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Make new recovery codes') }}</button></form>
        <form class="lp-inline" method="post" action="{{ route('two-factor.disable') }}">@csrf @method('delete') <button class="lp-btn lp-btn--danger" type="submit">{{ __('Turn off') }}</button></form>
    @elseif ($qrCode)
        <p>{{ __('Scan this with an authenticator app, then enter the code it shows.') }}</p>
        <div class="lp-qr">{!! $qrCode !!}</div>
        <p class="lp-muted">{{ \Launchpad\Support\Trans::html('Cannot scan it? Enter this key: :key', ['key' => new \Illuminate\Support\HtmlString('<code>'.e($secret).'</code>')]) }}</p>
        <form method="post" action="{{ route('two-factor.confirm') }}">
            @csrf
            <x-launchpad::field name="code" :label="__('Code from the app')" autocomplete="one-time-code" inputmode="numeric" />
            <button class="lp-btn" type="submit">{{ __('Turn on') }}</button>
        </form>
    @else
        <p class="lp-muted">{{ __('Add a second step to signing in, with a code from an authenticator app.') }}</p>
        <form method="post" action="{{ route('two-factor.enable') }}">@csrf <button class="lp-btn" type="submit">{{ __('Set up') }}</button></form>
    @endif
</div>
@if ($connections)
<div class="lp-card">
    <h2>{{ __('Sign-in methods') }}</h2>
    @error('connections')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    <ul class="lp-list">
        @foreach ($connections as $connection)
            <li>
                <span>{{ $connection['name'] }} <span class="lp-muted">{{ $connection['linked'] ? __('linked') : __('not linked') }}</span></span>
                @if ($connection['linked'])
                    <form class="lp-inline" method="post" action="{{ route('account.connections.disconnect', $connection['provider']) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Remove') }}</button></form>
                @else
                    <form class="lp-inline" method="post" action="{{ route('account.connections.connect', $connection['provider']) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Link') }}</button></form>
                @endif
            </li>
        @endforeach
    </ul>
    <p class="lp-muted">{{ __('You can always sign in with your email address and password.') }}</p>
</div>
@endif
@if ($passkeys !== null)
<div class="lp-card">
    <h2>{{ __('Passkeys') }}</h2>
    <p class="lp-muted">{{ __('Sign in with your fingerprint, face or device PIN instead of a password. A passkey stays on your device or in your password manager.') }}</p>
    @if ($passkeys->isNotEmpty())
    <ul class="lp-list">
        @foreach ($passkeys as $passkey)
            <li>
                <span>{{ $passkey->name }}<br><span class="lp-muted">{{ $passkey->last_used_at ? __('Added :added, last used :used', ['added' => $passkey->created_at->toFormattedDateString(), 'used' => $passkey->last_used_at->diffForHumans()]) : __('Added :added, not used yet', ['added' => $passkey->created_at->toFormattedDateString()]) }}</span></span>
                <form class="lp-inline" method="post" action="{{ route('account.passkeys.destroy', $passkey->id) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Remove') }}</button></form>
            </li>
        @endforeach
    </ul>
    @endif
    <p class="lp-muted" data-no-passkey hidden>{{ __('This browser cannot add a passkey here. Passkeys need a secure (https) page and a browser that supports them.') }}</p>
    <form id="lp-passkey-add" data-passkey hidden>
        <div class="lp-alert lp-alert--error" role="alert" id="lp-passkey-error" hidden></div>
        <x-launchpad::field name="passkey_name" :label="__('Name it, so you can tell it apart')" :value="__('My passkey')" maxlength="60" />
        <button class="lp-btn" type="submit">{{ __('Add a passkey') }}</button>
    </form>
</div>
@include('launchpad::partials.passkeys')
@push('scripts')
<script nonce="{{ $cspNonce ?? '' }}">
    document.getElementById('lp-passkey-add')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const error = document.getElementById('lp-passkey-error');
        window.lpPasskeys.add(document.getElementById('passkey_name').value).then((url) => { window.location = url; })
            .catch((err) => { error.textContent = err.message; error.hidden = false; });
    });
</script>
@endpush
@endif
@if ($devices !== null)
<div class="lp-card">
    <h2>{{ __('Where you are signed in') }}</h2>
    <ul class="lp-list">
        @foreach ($devices as $device)
            <li>
                <span>{{ $device['device'] }} @if ($device['current'])<strong>{{ __('(this device)') }}</strong>@endif<br><span class="lp-muted">{{ __(':address, last active :when', ['address' => $device['ip'] ?? __('Unknown address'), 'when' => $device['last_active']->diffForHumans()]) }}</span></span>
            </li>
        @endforeach
    </ul>
    @if (count($devices) > 1)
        <form method="post" action="{{ route('account.sessions.destroy') }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Sign out of all other devices') }}</button></form>
    @endif
</div>
@endif
@endsection
