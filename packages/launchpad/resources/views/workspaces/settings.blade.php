@extends('launchpad::layouts.account')
@section('title', 'Team settings')
@section('account')
<div class="lp-card">
    <h1>Team settings</h1>
    <p><a href="{{ route('workspace.members', $workspace->slug) }}">Back to your team</a></p>
    <form method="post" action="{{ route('workspace.settings.update', $workspace->slug) }}">
        @csrf @method('put')
        <x-launchpad::field name="name" label="Name" :value="$workspace->name" />
        <x-launchpad::field name="timezone" label="Time zone" :value="$workspace->timezone" />
        <button class="lp-btn" type="submit">Save</button>
    </form>
</div>
@php($editing = $editable && request()->boolean('edit'))
<div class="lp-card" id="roles">
    <div class="lp-card__head">
        <div>
            <h2>What each role can do</h2>
            <p class="lp-muted">Choose what a person gets from their role in this team. The owner can always do everything. You can still change what one person can do on their row of the team page.</p>
        </div>
        @if ($editing)
            <div class="lp-actions">
                <form class="lp-inline" method="post" action="{{ route('workspace.settings.roles.reset', $workspace->slug) }}">@csrf @method('delete') <input type="hidden" name="from" value="settings"> <button class="lp-btn lp-btn--quiet" type="submit">Reset defaults</button></form>
                <a class="lp-btn lp-btn--quiet" href="{{ route('workspace.settings', $workspace->slug) }}#roles">Cancel</a>
                <button class="lp-btn" type="submit" form="role-settings">Save</button>
            </div>
        @elseif ($editable)
            <div class="lp-actions"><a class="lp-btn" href="{{ route('workspace.settings', $workspace->slug) }}?edit=1#roles">Edit</a></div>
        @endif
    </div>
    <form id="role-settings" class="lp-roles" method="post" action="{{ route('workspace.settings.roles', $workspace->slug) }}">
        @csrf @method('put')
        <input type="hidden" name="from" value="settings">
        @foreach ($roles as $r)
            <fieldset class="lp-role-card">
                <legend class="lp-skip">{{ $r['label'] }}</legend>
                <h3>{{ $r['label'] }}</h3>
                <p class="lp-muted">{{ $r['about'] }}</p>
                <p class="lp-role-card__label">Permissions</p>
                @foreach ($permissionList as $p)
                    <label class="lp-check lp-check--tight" title="{{ $p['about'] }}">
                        <input type="checkbox" name="roles[{{ $r['value'] }}][{{ $p['key'] }}]" value="1" @checked($r['permissions'][$p['key']]) @disabled(! $editing || ! in_array($r['value'], array_map(fn ($e) => $e->value, $editable), true))>
                        <span>{{ $p['label'] }}<br><span class="lp-muted lp-small">{{ $p['about'] }}</span></span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
    </form>
    @error('roles')<div class="lp-alert lp-alert--error" role="alert">{{ $message }}</div>@enderror
    @if (! $editable)<p class="lp-muted">Only an owner or admin can change this.</p>@elseif (! in_array(\Launchpad\Workspaces\Role::Admin, $editable, true))<p class="lp-muted">Only the owner can change what an admin can do.</p>@endif
</div>
@if ($customBases !== [] || $customRoles->isNotEmpty())
<div class="lp-card" id="custom-roles">
    <h2>Your own roles</h2>
    <p class="lp-muted">Make a role of your own, such as Bookkeeper. It sits on Manager or Member, which decides who can manage it, and you choose what else it can do. Then pick it when you invite someone or change their role.</p>
    @if ($errors->hasAny(['role_name', 'role_base', 'custom_role']))<div class="lp-alert lp-alert--error" role="alert">{{ $errors->first('role_name') ?: ($errors->first('role_base') ?: $errors->first('custom_role')) }}</div>@endif
    <div class="lp-roles">
        @foreach ($customRoles as $c)
            <form class="lp-role-card" method="post" action="{{ route('workspace.custom-roles.update', [$workspace->slug, $c['id']]) }}">
                @csrf @method('put')
                <label for="role-name-{{ $c['id'] }}">Name</label>
                <input id="role-name-{{ $c['id'] }}" name="role_name" value="{{ $c['name'] }}" maxlength="40" required @disabled(! $c['canEdit'])>
                <p class="lp-muted">Works like {{ $c['baseLabel'] }}</p>
                <p class="lp-role-card__label">Permissions</p>
                @foreach ($permissionList as $p)
                    <label class="lp-check lp-check--tight" title="{{ $p['about'] }}">
                        <input type="checkbox" name="permissions[{{ $p['key'] }}]" value="1" @checked($c['permissions'][$p['key']]) @disabled(! $c['canEdit'])>
                        <span>{{ $p['label'] }}<br><span class="lp-muted lp-small">{{ $p['about'] }}</span></span>
                    </label>
                @endforeach
                @if ($c['canEdit'])
                    <div class="lp-actions">
                        <button class="lp-btn" type="submit">Save</button>
                        <button class="lp-btn lp-btn--quiet" type="submit" form="delete-custom-role-{{ $c['id'] }}">Remove</button>
                    </div>
                @endif
            </form>
            @if ($c['canEdit'])<form id="delete-custom-role-{{ $c['id'] }}" method="post" action="{{ route('workspace.custom-roles.destroy', [$workspace->slug, $c['id']]) }}">@csrf @method('delete')</form>@endif
        @endforeach
        @if ($customBases !== [])
            <form class="lp-role-card" method="post" action="{{ route('workspace.custom-roles.store', $workspace->slug) }}">
                @csrf
                <h3>New role</h3>
                <label for="role-name-new">Name</label>
                <input id="role-name-new" name="role_name" maxlength="40" required>
                <label for="role-base-new">Works like</label>
                <select id="role-base-new" name="role_base">@foreach ($customBases as $b)<option value="{{ $b['value'] }}">{{ $b['label'] }}</option>@endforeach</select>
                <p class="lp-role-card__label">Permissions</p>
                @foreach ($permissionList as $p)
                    <label class="lp-check lp-check--tight" title="{{ $p['about'] }}">
                        <input type="checkbox" name="permissions[{{ $p['key'] }}]" value="1">
                        <span>{{ $p['label'] }}<br><span class="lp-muted lp-small">{{ $p['about'] }}</span></span>
                    </label>
                @endforeach
                <button class="lp-btn" type="submit">Add role</button>
            </form>
        @endif
    </div>
</div>
@endif
@if ($role === \Launchpad\Workspaces\Role::Owner && ! $workspace->isPersonal())
<div class="lp-card">
    <h2>Delete this team</h2>
    <p class="lp-muted">This removes the team and its members' access. You will be asked for your password.</p>
    <form method="post" action="{{ route('workspace.destroy', $workspace->slug) }}">@csrf @method('delete') <button class="lp-btn lp-btn--danger" type="submit">Delete team</button></form>
</div>
@endif
@endsection
