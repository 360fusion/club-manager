@extends('launchpad::layouts.account')
@section('title', __(':team webhooks', ['team' => $workspace->name]))
@section('account')
@if ($newSecret)
<div class="lp-alert" role="alert">
    <p>{{ \Launchpad\Support\Trans::html(':title. Copy it now: it is not shown again. Use it to check the :header header of each delivery.', ['title' => new \Illuminate\Support\HtmlString('<strong>'.e(__('Your signing secret')).'</strong>'), 'header' => new \Illuminate\Support\HtmlString('<code>X-Launchpad-Signature</code>')]) }}</p>
    <pre class="lp-code"><code>{{ $newSecret }}</code></pre>
</div>
@endif
<div class="lp-card">
    <h1>{{ __(':team: webhooks', ['team' => $workspace->name]) }}</h1>
    <p class="lp-muted">{{ __('We send a signed POST to each address below when something you chose happens in this team.') }}</p>
    <p><a href="{{ route('workspace.api', $workspace->slug) }}">{{ __('API tokens') }}</a></p>
    @error('url')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    @if ($endpoints->isEmpty())
        <p>{{ __('No endpoints yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('Address') }}</th><th scope="col">{{ __('Events') }}</th><th scope="col">{{ __('State') }}</th><th scope="col"><span class="lp-skip">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                @foreach ($endpoints as $endpoint)
                    <tr>
                        <td>{{ $endpoint->url }}@if ($endpoint->description)<br><span class="lp-muted">{{ $endpoint->description }}</span>@endif</td>
                        <td>{{ implode(', ', $endpoint->events) }}</td>
                        <td>{{ $endpoint->active ? __('On') : __('Off') }}</td>
                        <td>
                            <form class="lp-inline" method="post" action="{{ route('workspace.webhooks.update', [$workspace->slug, $endpoint->id]) }}">@csrf @method('put') <button class="lp-btn lp-btn--quiet" type="submit">{{ $endpoint->active ? __('Switch off') : __('Switch on') }}</button></form>
                            @if ($endpoint->active)<form class="lp-inline" method="post" action="{{ route('workspace.webhooks.test', [$workspace->slug, $endpoint->id]) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Send test event') }}</button></form>@endif
                            <form class="lp-inline" method="post" action="{{ route('workspace.webhooks.destroy', [$workspace->slug, $endpoint->id]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">{{ __('Delete') }}</button></form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
<div class="lp-card">
    <h2>{{ __('Add an endpoint') }}</h2>
    <form method="post" action="{{ route('workspace.webhooks.store', $workspace->slug) }}">
        @csrf
        <x-launchpad::field name="url" :label="__('Address (https)')" inputmode="url" />
        <x-launchpad::field name="description" :label="__('Description (optional)')" :required="false" />
        <fieldset>
            <legend>{{ __('Send me') }}</legend>
            @foreach ($events as $key => $label)
                <label class="lp-check"><input type="checkbox" name="events[]" value="{{ $key }}" @checked(in_array($key, old('events', []), true))> <span>{{ \Launchpad\Support\Trans::html(':event (:key)', ['event' => __($label), 'key' => new \Illuminate\Support\HtmlString('<code>'.e($key).'</code>')]) }}</span></label>
            @endforeach
            @error('events')<p class="lp-error">{{ $message }}</p>@enderror
        </fieldset>
        <button class="lp-btn" type="submit">{{ __('Add endpoint') }}</button>
    </form>
</div>
<div class="lp-card">
    <h2>{{ __('Latest deliveries') }}</h2>
    @if ($deliveries->isEmpty())
        <p>{{ __('Nothing sent yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('When') }}</th><th scope="col">{{ __('Event') }}</th><th scope="col">{{ __('Address') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Response') }}</th></tr></thead>
                <tbody>
                @foreach ($deliveries as $delivery)
                    <tr>
                        <td>{{ $delivery->created_at->diffForHumans() }}</td>
                        <td>{{ $delivery->event }}</td>
                        <td>{{ $delivery->endpoint->url }}</td>
                        <td>{{ match ($delivery->status) { 'delivered' => __('Delivered'), 'failed' => __('Failed'), 'pending' => __('Pending'), default => ucfirst($delivery->status) } }}@if ($delivery->attempts > 1) {{ trans_choice('{1} (:count try)|[2,*] (:count tries)', $delivery->attempts) }}@endif</td>
                        <td>{{ $delivery->response_code ?? ($delivery->last_error ?: '') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
