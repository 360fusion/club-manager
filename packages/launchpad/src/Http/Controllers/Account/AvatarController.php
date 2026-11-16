<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Launchpad\Accounts\Avatar;

/** The photo on the Profile page */
class AvatarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);

        Avatar::store($request->user(), $request->file('avatar'));

        return back()->with('status', __('Your photo is saved.'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Avatar::remove($request->user());

        return back()->with('status', __('Your photo is removed.'));
    }
}
