<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Launchpad\Accounts\NotificationPreferences;
use Launchpad\Http\Ui;

/** The Notifications page: which optional emails a person gets */
class NotificationsController extends Controller
{
    public function show(Request $request): View|Responsable
    {
        $user = $request->user();
        $choices = collect(NotificationPreferences::all())->map(fn ($c, $key) => ['key' => $key, 'label' => __($c[0]), 'about' => __($c[1]), 'on' => NotificationPreferences::wants($user, $key)])->values();

        return Ui::render('account.notifications', ['choices' => $choices], fn () => ['choices' => $choices]);
    }

    public function update(Request $request): RedirectResponse
    {
        $chosen = [];

        foreach (array_keys(NotificationPreferences::all()) as $key) {
            $chosen[$key] = $request->boolean("notify.{$key}");
        }

        $request->user()->forceFill(['notification_choices' => $chosen])->save();

        return back()->with('status', __('Your choices are saved.'));
    }
}
