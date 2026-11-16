@unless (\Launchpad\Site\Consent::decided())
<div class="lp-consent" role="region" aria-label="{{ __('Cookies') }}">
    <p>{{ __('We use cookies that are needed to sign you in. With your permission we would also use others to see how the site is used.') }} <a href="{{ route('cookie-preferences') }}">{{ __('Choose') }}</a> · <a href="/cookies">{{ __('Cookie Policy') }}</a></p>
    <form method="post" action="{{ route('cookie-consent') }}">
        @csrf
        <button class="lp-btn lp-btn--quiet" name="choice" value="necessary" type="submit">{{ __('Necessary only') }}</button>
        <button class="lp-btn" name="choice" value="all" type="submit">{{ __('Allow all') }}</button>
    </form>
</div>
@endunless
