@extends('launchpad::layouts.account')
@section('title', __('Profile'))
@section('account')
<div class="lp-card">
    <h1>{{ __('Profile') }}</h1>
    @unless ($user->hasVerifiedEmail())
        <p class="lp-muted">{{ \Launchpad\Support\Trans::html('Your email address is not confirmed yet. :send.', ['send' => new \Illuminate\Support\HtmlString('<a href="'.e(route('verification.notice')).'">'.e(__('Send the link again')).'</a>')]) }}</p>
    @endunless
    <div class="lp-avatar-edit">
        <x-launchpad::avatar :user="$user" :size="72" />
        <div>
            <form method="post" action="{{ route('account.avatar.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="lp-field">
                    <label for="avatar">{{ __('Photo') }}</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required @error('avatar') aria-invalid="true" @enderror>
                    @error('avatar')<p class="lp-error">{{ $message }}</p>@enderror
                </div>
                <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Upload') }}</button>
            </form>
            @if (\Launchpad\Accounts\Avatar::url($user))
                <form class="lp-inline" method="post" action="{{ route('account.avatar.destroy') }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Remove photo') }}</button></form>
            @endif
            <p class="lp-muted">{{ __('JPEG, PNG or WebP, up to 2 MB. It is cut to a square.') }}</p>
        </div>
    </div>
    <form method="post" action="{{ route('account.profile.update') }}">
        @csrf @method('put')
        <x-launchpad::field name="name" :label="__('Name')" autocomplete="name" :value="$user->name" />
        <x-launchpad::field name="email" :label="__('Email address')" type="email" autocomplete="email" :value="$user->email" />
        @if ($user->password_set_at !== null)
            <x-launchpad::field name="current_password" :label="__('Current password (to change your email)')" type="password" autocomplete="current-password" :required="false" />
        @endif
        <div class="lp-field">
            <label for="timezone">{{ __('Time zone') }}</label>
            <select id="timezone" name="timezone">
                <option value="">{{ __('Not set') }}</option>
                @foreach ($timezones as $zone)
                    <option value="{{ $zone }}" @selected(old('timezone', $user->timezone) === $zone)>{{ $zone }}</option>
                @endforeach
            </select>
        </div>
        @if (count(\Launchpad\Support\Locales::supported()) > 1)
            <div class="lp-field">
                <label for="locale">{{ __('Language') }}</label>
                <select id="locale" name="locale">
                    <option value="">{{ __("Use the browser's") }}</option>
                    @foreach (\Launchpad\Support\Locales::supported() as $code => $name)
                        <option value="{{ $code }}" @selected(old('locale', $user->locale) === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('locale')<p class="lp-error">{{ $message }}</p>@enderror
            </div>
        @endif
        <div class="lp-field">
            <label for="theme">{{ __('Appearance') }}</label>
            <select id="theme" name="theme">
                <option value="">{{ __('As this device is set') }}</option>
                <option value="light" @selected(old('theme', $user->theme) === 'light')>{{ __('Light') }}</option>
                <option value="dark" @selected(old('theme', $user->theme) === 'dark')>{{ __('Dark') }}</option>
            </select>
            @error('theme')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <button class="lp-btn" type="submit">{{ __('Save') }}</button>
    </form>
</div>
@endsection
