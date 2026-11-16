@php($providers = \Launchpad\Accounts\SocialProviders::enabled())
@if ($providers)
    <p class="lp-muted" style="text-align:center">{{ __('or') }}</p>
    <p style="display:grid;gap:8px">
        @foreach ($providers as $provider)
            <a class="lp-btn lp-btn--quiet" href="{{ route('social.redirect', $provider) }}">{{ __('Continue with :provider', ['provider' => \Launchpad\Accounts\SocialProviders::name($provider)]) }}</a>
        @endforeach
    </p>
@endif
