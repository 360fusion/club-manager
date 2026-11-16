@extends('launchpad::layouts.account')
@section('title', 'Teams')
@section('account')
<div class="lp-card">
    <h1>Your teams</h1>
    <p class="lp-muted">A team is the shared space for a group or business. It holds the people you invite (each with a role: owner, admin, manager or member) and it is what the plan and billing belong to. You get a personal one when you sign up, and can make more for each team or client.</p>
    <ul>
        @foreach ($memberships as $membership)
            <li>
                <strong>{{ $membership->workspace->labelFor(auth()->user()) }}</strong>
                <span class="lp-muted">({{ $membership->roleLabel() }}{{ $membership->workspace->isPersonal() ? ', personal' : '' }})</span>
                <a href="{{ route('workspace.members', $membership->workspace->slug) }}">Team</a>
                @if ($membership->can(\Launchpad\Workspaces\Ability::ManageWorkspace))<a href="{{ route('workspace.settings', $membership->workspace->slug) }}">Settings</a>@endif
                @if ($membership->can(\Launchpad\Workspaces\Ability::ManageBilling) && Route::has('workspace.billing'))<a href="{{ route('workspace.billing', $membership->workspace->slug) }}">Billing</a>@endif
            </li>
        @endforeach
    </ul>
</div>
<div class="lp-card">
    <h2>Create a team</h2>
    <form method="post" action="{{ route('workspaces.store') }}">
        @csrf
        <x-launchpad::field name="name" label="Name" />
        <button class="lp-btn" type="submit">Create</button>
    </form>
</div>
@endsection
