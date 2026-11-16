@extends('launchpad::layouts.account')
@section('title', __(':name API token', ['name' => $token->name]))
@section('account')
@error('token')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
<div class="lp-card">
    <h1>{{ $token->name }}</h1>
    <p><a href="{{ route('workspace.api', $workspace->slug) }}">{{ __('All API tokens of :team', ['team' => $workspace->name]) }}</a></p>
    <dl class="lp-facts">
        <dt>{{ __('Can') }}</dt><dd>{{ implode(', ', $token->abilities) }}</dd>
        <dt>{{ __('Made by') }}</dt><dd>{{ __(':person, :date', ['person' => $token->tokenable?->name ?? __('Someone who has left'), 'date' => $token->created_at->isoFormat('ll')]) }}</dd>
        <dt>{{ __('Last used') }}</dt><dd>{{ $token->last_ip ? __(':when from :address', ['when' => $token->last_used_at ? $token->last_used_at->diffForHumans() : __('Never'), 'address' => $token->last_ip]) : ($token->last_used_at ? $token->last_used_at->diffForHumans() : __('Never')) }}</dd>
        @if ($token->last_user_agent)<dt>{{ __('Last program') }}</dt><dd>{{ $token->last_user_agent }}</dd>@endif
        <dt>{{ __('Expires') }}</dt><dd>@if (! $token->expires_at){{ __('Never') }} @elseif ($token->expires_at->isPast()){{ __('Expired') }} @else{{ $token->expires_at->isoFormat('ll') }}@endif @if ($token->replaced_at){{ __('(being replaced: it stops working then)') }}@endif</dd>
    </dl>
</div>
<div class="lp-card" id="rotate">
    <h2>{{ __('Replace this token') }}</h2>
    <p class="lp-muted">{{ __('Makes a new token with the same name, abilities and lifetime, shown once. The old one keeps working for the time you choose, so the program that uses it can be switched over.') }}</p>
    <form method="post" action="{{ route('workspace.api.rotate', [$workspace->slug, $token->id]) }}">
        @csrf
        <div class="lp-field">
            <label for="grace">{{ __('Keep the old token working for') }}</label>
            <select id="grace" name="grace">
                @foreach ($graces as $grace)<option value="{{ $grace['value'] }}" @selected(old('grace', '24h') === $grace['value'])>{{ $grace['label'] }}</option>@endforeach
            </select>
            @error('grace')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <button class="lp-btn" type="submit">{{ __('Replace token') }}</button>
    </form>
</div>
<div class="lp-card">
    <h2>{{ __('The last 30 days') }}</h2>
    <p class="lp-muted">{{ trans_choice('{0} No calls, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.|{1} :count call, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.|[2,*] :count calls, :errors of them errors. A call with no good token, or one stopped by the rate limit, is not counted.', $totals['requests'], ['errors' => $totals['errors']]) }}</p>
    <div class="lp-table-wrap">
        <table>
            <caption class="lp-skip">{{ __('Calls and errors for each of the last 30 days') }}</caption>
            <thead><tr><th scope="col">{{ __('Day') }}</th><th scope="col">{{ __('Calls') }}</th><th scope="col">{{ __('Errors') }}</th></tr></thead>
            <tbody>
            @foreach ($days as $day)
                <tr data-day="{{ $day['date'] }}"><td>{{ \Illuminate\Support\Carbon::parse($day['date'])->isoFormat('ll') }}</td><td>{{ $day['requests'] }}</td><td>{{ $day['errors'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
