@php($menuUser = auth()->user())
@php($adminUrl = \Launchpad\Http\AccountMenu::adminUrl($menuUser))
<details class="lp-usermenu">
    <summary>
        <x-launchpad::avatar :user="$menuUser" />
        <span class="lp-usermenu__name">{{ $menuUser->name ?: $menuUser->email }}</span>
    </summary>
    <div class="lp-usermenu__panel">
        <p class="lp-muted lp-usermenu__email">{{ $menuUser->email }}</p>
        <ul>
            <li><a href="{{ route('account.profile') }}">{{ __('My account') }}</a></li>
            <li><a href="{{ route('account.security') }}">{{ __('Security') }}</a></li>
            @if (Route::has('workspaces.index'))<li><a href="{{ route('workspaces.index') }}">{{ __('Teams') }}</a></li>@endif
            @if ($adminUrl)<li><a href="{{ $adminUrl }}">{{ __('HQ') }}</a></li>@endif
        </ul>
        @php($memberships = \Launchpad\Http\AccountMenu::memberships($menuUser))
        @if ($memberships->count() > 1)
            <p class="lp-usermenu__label">{{ __('Switch team') }}</p>
            <ul>
                @foreach ($memberships as $membership)
                    <li>
                        <form method="post" action="{{ route('workspaces.switch', $membership->workspace->slug) }}">
                            @csrf
                            <button type="submit" @if ($membership->workspace_id === $menuUser->current_workspace_id) aria-current="true" @endif>{{ $membership->workspace->labelFor($menuUser) }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf <button type="submit">{{ __('Sign out') }}</button></form>
    </div>
</details>
