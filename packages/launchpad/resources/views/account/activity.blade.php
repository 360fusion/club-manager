@extends('launchpad::layouts.account')
@section('title', __('Activity'))
@section('account')
<div class="lp-card">
    <h1>{{ __('Activity') }}</h1>
    <p class="lp-muted">{{ __('Your latest sign-ins and changes to how you sign in. If something here is not you, change your password and turn on two-factor sign-in.') }}</p>
    @if ($events->isEmpty())
        <p>{{ __('Nothing yet.') }}</p>
    @else
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">{{ __('What') }}</th><th scope="col">{{ __('Device') }}</th><th scope="col">{{ __('Address') }}</th><th scope="col">{{ __('When') }}</th></tr></thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ $event['label'] }}</td>
                            <td>{{ $event['device'] }}</td>
                            <td>{{ $event['ip'] ?? __('Unknown') }}</td>
                            <td><time datetime="{{ $event['at']?->toIso8601String() }}">{{ $event['at']?->diffForHumans() }}</time></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
