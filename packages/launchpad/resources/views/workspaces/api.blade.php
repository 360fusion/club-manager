@extends('launchpad::layouts.account')
@section('title', __(':team API', ['team' => $workspace->name]))
@section('account')
@if ($newToken)
<div class="lp-alert" role="alert">
    <p>{{ \Launchpad\Support\Trans::html(':title. Copy it now: it is not shown again.', ['title' => new \Illuminate\Support\HtmlString('<strong>'.e(__('Your new token')).'</strong>')]) }}</p>
    <pre class="lp-code"><code>{{ $newToken }}</code></pre>
</div>
@endif
<div class="lp-card">
    <h1>{{ __(':team: API', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ \Launchpad\Support\Trans::html('A token lets a program of yours read this team. Send it as :header to :url. Keep it secret, and revoke it if it leaks.', ['header' => new \Illuminate\Support\HtmlString('<code>Authorization: Bearer &lt;token&gt;</code>'), 'url' => new \Illuminate\Support\HtmlString('<code>'.e(url('api/v1')).'</code>')]) }}</p>
    <p><a href="{{ route('workspace.webhooks', $workspace->slug) }}">{{ __('Webhooks') }}</a></p>
    @if ($tokens->isEmpty())
        <p>{{ __('No tokens yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('Name') }}</th><th scope="col">{{ __('Can') }}</th><th scope="col">{{ __('Last used') }}</th><th scope="col">{{ __('Last address') }}</th><th scope="col">{{ __('Expires') }}</th><th scope="col"><span class="lp-skip">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                @foreach ($tokens as $token)
                    <tr>
                        <td><a href="{{ route('workspace.api.show', [$workspace->slug, $token->id]) }}">{{ $token->name }}</a></td>
                        <td>{{ implode(', ', $token->abilities) }}</td>
                        <td>{{ $token->last_used_at ? $token->last_used_at->diffForHumans() : __('Never') }}</td>
                        <td>{{ $token->last_ip ?? '' }}</td>
                        <td>@if (! $token->expires_at){{ __('Never') }} @elseif ($token->expires_at->isPast()){{ __('Expired') }} @else{{ $token->expires_at->isoFormat('ll') }}@endif @if ($token->replaced_at)({{ __('being replaced') }})@endif</td>
                        <td>@unless ($token->replaced_at)<a href="{{ route('workspace.api.show', [$workspace->slug, $token->id]) }}#rotate">{{ __('Rotate') }}</a> @endunless<form class="lp-inline" method="post" action="{{ route('workspace.api.destroy', [$workspace->slug, $token->id]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Revoke') }}</button></form></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="lp-card">
    <h2>{{ __('Create a token') }}</h2>
    <form method="post" action="{{ route('workspace.api.store', $workspace->slug) }}">
        @csrf
        <x-launchpad::field name="name" :label="__('Name')" />
        <div class="lp-field">
            <label for="expiry">{{ __('Expires') }}</label>
            <select id="expiry" name="expiry">
                @foreach (['never' => __('Never'), '30' => __('In 30 days'), '90' => __('In 90 days'), '365' => __('In 1 year')] as $value => $label)<option value="{{ $value }}" @selected(old('expiry', 'never') === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('expiry')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <fieldset>
            <legend>{{ __('Can') }}</legend>
            @foreach ($abilities as $key => $label)
                <label class="lp-check"><input type="checkbox" name="abilities[]" value="{{ $key }}" @checked(in_array($key, old('abilities', ['read']), true))> <span>{{ __($label) }}</span></label>
            @endforeach
            @error('abilities')<p class="lp-error">{{ $message }}</p>@enderror
        </fieldset>
        <button class="lp-btn" type="submit">{{ __('Create token') }}</button>
    </form>
</div>
@if ($mcpUrl ?? null)
<div class="lp-card">
    <h2>{{ __('Connect an AI assistant') }}</h2>
    <p>{{ __('Assistants that support MCP (Claude, ChatGPT, Cursor and others) can read this team through its own address, with a token from this page.') }}</p>
    <p><code>{{ $mcpUrl }}</code></p>
    <p class="lp-muted">{{ __('Give the assistant the address, and the token as a bearer token (the header Authorization: Bearer followed by the token). It can do what the token can do, and nothing in other teams.') }}</p>
</div>
@endif
@endsection
