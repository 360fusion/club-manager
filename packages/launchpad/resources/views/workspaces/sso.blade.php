@extends('launchpad::layouts.account')
@section('title', __('Single sign-on'))
@section('account')
<div class="lp-card">
    <h1>{{ __(':team: Single sign-on', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ __('Let people sign in with their company account through your identity provider (OpenID Connect: Okta, Microsoft Entra, Google Workspace and others). Anyone with an email address on your domain who signs in this way joins this team.') }}</p>
    <h2>{{ __('1. Register this app with your provider') }}</h2>
    <p>{{ __('Make an OpenID Connect web application with this sign-in redirect address, and copy its issuer, client ID and client secret below.') }}</p>
    <pre class="lp-code"><code>{{ $callback }}</code></pre>
    @if ($saml)
    <p>{{ __('Your team uses SAML. Give your provider these:') }}</p>
    <div class="lp-table-wrap"><table><tbody>
        <tr><th scope="row">{{ __('Entity ID (and metadata address)') }}</th><td><code>{{ $saml['entityId'] }}</code></td></tr>
        <tr><th scope="row">{{ __('Sign-in return address (ACS)') }}</th><td><code>{{ $saml['acs'] }}</code></td></tr>
        <tr><th scope="row">{{ __('Name ID') }}</th><td>{{ __('The person\'s email address') }}</td></tr>
    </tbody></table></div>
    @endif
    <h2>{{ __('2. Your provider') }}</h2>
    <form method="post" action="{{ route('workspace.sso.update', $workspace->slug) }}">
        @csrf @method('put')
        @php($protocol = old('protocol', $connection?->protocol ?? 'oidc'))
        @if ($samlAvailable)
        <fieldset>
            <legend>{{ __('How your provider signs people in') }}</legend>
            <label class="lp-check"><input type="radio" name="protocol" value="oidc" @checked($protocol === 'oidc')> <span>{{ __('OpenID Connect (recommended)') }}</span></label>
            <label class="lp-check"><input type="radio" name="protocol" value="saml" @checked($protocol === 'saml')> <span>{{ __('SAML 2.0') }}</span></label>
        </fieldset>
        @endif
        <div data-protocol="oidc" @if ($protocol !== 'oidc') hidden @endif>
            <x-launchpad::field name="issuer" :label="__('Issuer address')" :value="$connection?->protocol === 'oidc' ? $connection->issuer : null" placeholder="https://example.okta.com" :required="false" />
            <x-launchpad::field name="client_id" :label="__('Client ID')" :value="$connection?->client_id" :required="false" />
            <x-launchpad::field name="client_secret" :label="$connection?->protocol === 'oidc' ? __('Client secret (leave blank to keep the saved one)') : __('Client secret')" type="password" autocomplete="off" :required="false" />
        </div>
        @if ($samlAvailable)
        <div data-protocol="saml" @if ($protocol !== 'saml') hidden @endif>
            @if ($saml)<p class="lp-muted">{{ __('Saved provider: :idp. Give new metadata only to change it.', ['idp' => $saml['idp']]) }}</p>@endif
            <x-launchpad::field name="metadata_url" :label="__('Your provider\'s metadata address')" placeholder="https://example.okta.com/app/.../sso/saml/metadata" :required="false" />
            <div class="lp-field">
                <label for="metadata_xml">{{ __('Or paste its metadata XML') }}</label>
                <textarea id="metadata_xml" name="metadata_xml" rows="4">{{ old('metadata_xml') }}</textarea>
                @error('metadata_xml')<p class="lp-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @push('scripts')
        <script nonce="{{ $cspNonce ?? '' }}">
            document.querySelectorAll('input[name="protocol"]').forEach((radio) => radio.addEventListener('change', () => {
                document.querySelectorAll('[data-protocol]').forEach((el) => { el.hidden = el.dataset.protocol !== radio.value; });
            }));
        </script>
        @endpush
        @endif
        <x-launchpad::field name="domain" :label="__('Email domain')" :value="$connection?->domain" placeholder="example.com" />
        <div class="lp-field">
            <label for="default_role">{{ __('Role for people who join this way') }}</label>
            <select id="default_role" name="default_role">
                @foreach ($roles as $role)<option value="{{ $role['value'] }}" @selected(old('default_role', $connection?->default_role ?? 'member') === $role['value'])>{{ $role['label'] }}</option>@endforeach
            </select>
        </div>
        <div class="lp-check"><input type="hidden" name="enforce" value="0"><input type="checkbox" id="enforce" name="enforce" value="1" @checked(old('enforce', $connection?->enforce))><label for="enforce">{{ __('People on this domain must use single sign-on (no password sign-in)') }}</label></div>
        <button class="lp-btn" type="submit">{{ __('Save') }}</button>
    </form>
</div>
@if ($connection)
<div class="lp-card">
    <h2>{{ __('3. Prove you own the domain') }}</h2>
    @if ($connection->domain_verified_at)
        <p>{{ __(':domain is verified. Single sign-on is on.', ['domain' => $connection->domain]) }}</p>
    @else
        <p>{{ __('Add this TXT record to your domain\'s DNS, then check it. Single sign-on starts working once it is found.') }}</p>
        <div class="lp-table-wrap"><table><thead><tr><th scope="col">{{ __('Name') }}</th><th scope="col">{{ __('Value') }}</th></tr></thead>
            <tbody><tr><td><code>{{ $record['name'] }}</code></td><td><code>{{ $record['value'] }}</code></td></tr></tbody></table></div>
        @error('domain')<p class="lp-error">{{ $message }}</p>@enderror
        <form method="post" action="{{ route('workspace.sso.verify', $workspace->slug) }}">@csrf <button class="lp-btn" type="submit">{{ __('Check the record') }}</button></form>
    @endif
    <form method="post" action="{{ route('workspace.sso.destroy', $workspace->slug) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Turn off single sign-on') }}</button></form>
</div>
@if ($scimUrl && $connection->domain_verified_at)
<div class="lp-card">
    <h2>{{ __('4. Directory provisioning (optional)') }}</h2>
    <p>{{ __('Let your directory add people to the team, keep their names and addresses up to date, and take them out when they leave. In your provider, set up SCIM 2.0 with this address and a token from here.') }}</p>
    <p><code>{{ $scimUrl }}</code></p>
    @if ($scimToken)<div class="lp-alert" role="status">{{ __('Your token (copy it now, it is not shown again):') }} <code>{{ $scimToken }}</code></div>@endif
    <p class="lp-muted">{{ $connection->scim_token_hash ? __('Provisioning is on. A new token replaces the old one at once.') : __('Provisioning is off.') }}</p>
    <form class="lp-inline" method="post" action="{{ route('workspace.sso.scim', $workspace->slug) }}">@csrf <button class="lp-btn" type="submit">{{ $connection->scim_token_hash ? __('Make a new token') : __('Turn on and make a token') }}</button></form>
    @if ($connection->scim_token_hash)<form class="lp-inline" method="post" action="{{ route('workspace.sso.scim.off', $workspace->slug) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Turn off provisioning') }}</button></form>@endif
</div>
@endif
@endif
@endsection
