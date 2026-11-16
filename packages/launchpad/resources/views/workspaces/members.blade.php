@extends('launchpad::layouts.account')
@section('title', 'Team')
@section('account')
@error('member')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
<div class="lp-card">
    <h1>Your team</h1>
    <p class="lp-working" role="group" aria-label="Working in">
        <span class="lp-muted">Working in</span>
        @foreach ($teams as $team)
            <a class="lp-pill" href="{{ route('workspace.members', $team['slug']) }}" @if ($team['current']) aria-current="page" @endif>{{ $team['name'] }}</a>
        @endforeach
        <a href="{{ route('workspaces.index') }}">All teams</a>
        @if ($me->can(\Launchpad\Workspaces\Ability::ManageWorkspace))<a href="{{ route('workspace.settings', $workspace->slug) }}">Team settings</a>@endif
    </p>
    <h2>About team members</h2>
    <p>Team members are people you invite to work in this team with their own login, so nobody shares a password. What they can do is up to you, and billing and deleting the team always stay with the owner.</p>
    <ol class="lp-steps">
        <li><span class="lp-step" aria-hidden="true">1</span><div><strong>Invite</strong><br>Type their email address below and choose what they may do.</div></li>
        <li><span class="lp-step" aria-hidden="true">2</span><div><strong>They accept</strong><br>They get an email with a link that works for {{ \Launchpad\Workspaces\Invitations::DAYS }} days. They sign in, or sign up with that address, and click Join.</div></li>
        <li><span class="lp-step" aria-hidden="true">3</span><div><strong>Manage</strong><br>Change what someone may do, or take their access away, whenever you like, in the list below.</div></li>
    </ol>
    <p class="lp-muted">{{ $members->count() }} {{ Str::plural('person', $members->count()) }} in this team{{ $invitations->isNotEmpty() ? ', and '.$invitations->count().' '.Str::plural('invitation', $invitations->count()).' waiting' : '' }}.</p>
</div>
@if ($me->can(\Launchpad\Workspaces\Ability::ManageMembers))
<div class="lp-card lp-card--accent">
    <h2>Invite a team member</h2>
    <form method="post" action="{{ route('workspace.invitations.store', $workspace->slug) }}">
        @csrf
        <x-launchpad::field name="email" label="Email address" type="email" />
        <div class="lp-field">
            <label for="invite-role">Role</label>
            <select id="invite-role" name="role">@foreach ($assignable as $option)<option value="{{ $option['value'] }}">{{ $option['label'] }}</option>@endforeach</select>
            @error('role')<p class="lp-error">{{ $message }}</p>@enderror
        </div>
        <fieldset class="lp-field lp-field--set">
            <legend>They may also</legend>
            @foreach ($permissions as $key => [$label, $about])
                <label class="lp-check lp-check--tight" title="{{ $about }}">
                    <input type="checkbox" name="permissions[{{ $key }}]" value="1" @checked(old("permissions.{$key}")) @disabled(! $me->can($key))>
                    <span>{{ $label }} <span class="lp-muted">{{ $about }}</span></span>
                </label>
            @endforeach
            @error('permissions')<p class="lp-error">{{ $message }}</p>@enderror
        </fieldset>
        <button class="lp-btn" type="submit">Send invitation</button>
    </form>
</div>
@endif
<div class="lp-card">
    <h2>People in this team</h2>
    <table>
        <thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Role</th><th scope="col">Permissions</th><th scope="col"><span class="lp-skip">Actions</span></th></tr></thead>
        <tbody>
        @foreach ($members as $member)
            <tr>
                <td>{{ $member->user->name }}</td>
                <td>{{ $member->user->email }}</td>
                <td>
                    @if ($role->outranks($member->role) && $me->can(\Launchpad\Workspaces\Ability::ManageMembers))
                        <form class="lp-inline" method="post" action="{{ route('members.update', [$workspace->slug, $member->id]) }}">
                            @csrf @method('put')
                            <label class="lp-skip" for="role-{{ $member->id }}">Role for {{ $member->user->name }}</label>
                            <select id="role-{{ $member->id }}" name="role">
                                @foreach ($assignable as $option)<option value="{{ $option['value'] }}" @selected($member->roleValue() === $option['value'])>{{ $option['label'] }}</option>@endforeach
                            </select>
                            <button class="lp-btn lp-btn--quiet" type="submit">Change</button>
                        </form>
                    @else
                        {{ $member->roleLabel() }}
                    @endif
                </td>
                <td>
                    @if ($member->role === \Launchpad\Workspaces\Role::Owner)
                        <span class="lp-muted">Everything</span>
                    @elseif ($role->outranks($member->role) && $me->can(\Launchpad\Workspaces\Ability::ManageMembers))
                        <form class="lp-perms" method="post" action="{{ route('members.permissions', [$workspace->slug, $member->id]) }}">
                            @csrf @method('put')
                            @foreach ($permissions as $key => [$label, $about])
                                <label class="lp-check lp-check--tight" title="{{ $about }}">
                                    <input type="checkbox" name="permissions[{{ $key }}]" value="1" @checked($member->can($key)) @disabled(! $me->can($key))>
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                            <button class="lp-btn lp-btn--quiet" type="submit">Save</button>
                        </form>
                    @else
                        @php($granted = collect($permissions)->filter(fn ($p, $key) => $member->can($key))->map(fn ($p) => $p[0])->values())
                        {{ $granted->isEmpty() ? 'None' : $granted->implode(', ') }}
                    @endif
                </td>
                <td>
                    @if ($role->outranks($member->role) && $me->can(\Launchpad\Workspaces\Ability::ManageMembers))
                        <form class="lp-inline" method="post" action="{{ route('members.destroy', [$workspace->slug, $member->id]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">Remove</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($invitations->isNotEmpty())
        <h3>Waiting to accept</h3>
        <ul>
            @foreach ($invitations as $invitation)
                <li>{{ $invitation->email }} ({{ $invitation->roleLabel() }}@if ($permissionLabels($invitation->permissions)), {{ implode(', ', $permissionLabels($invitation->permissions)) }}@endif)
                    <form class="lp-inline" method="post" action="{{ route('workspace.invitations.destroy', [$workspace->slug, $invitation->id]) }}">@csrf @method('delete') <button class="lp-btn lp-btn--quiet" type="submit">Withdraw</button></form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@php($editable = array_map(fn ($e) => $e->value, $role->editableRoles()))
@php($editing = $editable && request()->boolean('edit'))
<div class="lp-card" id="roles">
    <div class="lp-card__head">
        <div>
            <h2>What each role can do</h2>
            <p class="lp-muted">This is what each role gets in this team. {{ $editable ? 'Press Edit to change it for everyone in a role.' : 'An owner or admin can change it.' }} {{ $me->can(\Launchpad\Workspaces\Ability::ManageMembers) ? 'You can also change what one person can do, on their row above.' : '' }}</p>
        </div>
        @if ($editing)
            <div class="lp-actions">
                <form class="lp-inline" method="post" action="{{ route('workspace.settings.roles.reset', $workspace->slug) }}">@csrf @method('delete') <input type="hidden" name="from" value="members"> <button class="lp-btn lp-btn--quiet" type="submit">Reset to defaults</button></form>
                <a class="lp-btn lp-btn--quiet" href="{{ route('workspace.members', $workspace->slug) }}#roles">Cancel</a>
                <button class="lp-btn" type="submit" form="role-table">Save</button>
            </div>
        @elseif ($editable)
            <div class="lp-actions"><a class="lp-btn" href="{{ route('workspace.members', $workspace->slug) }}?edit=1#roles">Edit</a></div>
        @endif
    </div>
    @error('roles')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    <form id="role-table" method="post" action="{{ route('workspace.settings.roles', $workspace->slug) }}">
        @csrf @method('put')
        <input type="hidden" name="from" value="members">
        <div class="lp-table-wrap">
            <table>
                <thead><tr><th scope="col">Can</th>@foreach ($matrixColumns as $r)<th scope="col">{{ $r['label'] }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($roleMatrix as $row)
                    <tr>
                        <th scope="row">{{ $row['label'] }}<br><span class="lp-muted lp-small">{{ $row['about'] }}</span></th>
                        @foreach ($matrixColumns as $r)
                            <td>
                                @if ($editing && $row['key'] && in_array($r['value'], $editable, true))
                                    <label><input type="checkbox" name="roles[{{ $r['value'] }}][{{ $row['key'] }}]" value="1" @checked($row['roles'][$r['value']])><span class="lp-skip">{{ $row['label'] }} for {{ $r['label'] }}</span></label>
                                @elseif ($row['roles'][$r['value']])<span aria-hidden="true">&#10003;</span><span class="lp-skip">Yes</span>@else<span class="lp-muted" aria-hidden="true">&ndash;</span><span class="lp-skip">No</span>@endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </form>
</div>
@if ($role !== \Launchpad\Workspaces\Role::Owner)
<form method="post" action="{{ route('workspace.leave', $workspace->slug) }}">@csrf <button class="lp-btn lp-btn--quiet" type="submit">Leave this team</button></form>
@endif
@endsection
