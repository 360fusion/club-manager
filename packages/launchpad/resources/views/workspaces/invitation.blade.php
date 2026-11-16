@extends('launchpad::layouts.base')
@section('title', 'Invitation')
@section('noindex', '1')
@section('content')
<div class="lp-card">
@if (! $invitation)
    <h1>This invitation is no longer valid</h1>
    <p class="lp-muted">It may have expired, been withdrawn or already been used. Ask for a new one.</p>
@else
    <h1>Join {{ $invitation->workspace->name }}</h1>
    <p>{{ $invitation->inviter?->name ?? 'Someone' }} invited you to join as <strong>{{ $invitation->role->label() }}</strong>.</p>
    @error('invitation')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    @if (! $user)
        <p>Sign in, or create an account, with <strong>{{ $invitation->email }}</strong> to accept.</p>
        <a class="lp-btn" href="{{ route('login') }}">Sign in</a>
        <a class="lp-btn lp-btn--quiet" href="{{ route('register', ['email' => $invitation->email]) }}">Create an account</a>
    @elseif ($rightAccount)
        <form method="post" action="{{ route('invitations.accept', $token) }}">@csrf <button class="lp-btn" type="submit">Accept the invitation</button></form>
    @else
        <p class="lp-alert lp-alert--error" role="alert">You are signed in as {{ $user->email }}, but this invitation is for another address. Sign out and use the invited one.</p>
    @endif
@endif
</div>
@endsection
