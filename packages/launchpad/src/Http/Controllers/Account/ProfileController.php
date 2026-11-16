<?php

namespace Launchpad\Http\Controllers\Account;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Avatar;
use Launchpad\Accounts\PasswordCheck;
use Launchpad\Http\Ui;
use Launchpad\Models\AccountEvent;
use Launchpad\Support\Locales;

/** The Profile page: name, email address and time zone */
class ProfileController extends Controller
{
    public function show(Request $request): View|Responsable
    {
        $user = $request->user();

        return Ui::render('account.profile', ['user' => $user, 'timezones' => timezone_identifiers_list()], fn () => [
            'name' => $user->name,
            'email' => $user->email,
            'verified' => $user->hasVerifiedEmail(),
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'avatarUrl' => Avatar::url($user),
            'timezones' => timezone_identifiers_list(),
            'needsCurrentPassword' => $user->password_set_at !== null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $email = AccountRegistration::normalizeEmail($request->input('email'));
        $changing = $email !== $user->email;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => AccountRegistration::emailRules($user->getKey()),
            'timezone' => ['nullable', Rule::in(timezone_identifiers_list())],
            'locale' => ['nullable', Rule::in(array_keys(Locales::supported()))],
            'theme' => ['nullable', Rule::in(['light', 'dark'])],
            // a changed address is where reset mail goes: a stolen session must not be able to take it
            'current_password' => $changing && $user->password_set_at !== null
                ? ['required', fn ($attribute, $value, $fail) => PasswordCheck::verify($user, (string) $value) ?: $fail(__('The password is not right.'))]
                : ['nullable'],
        ]);

        $user->forceFill(['name' => $data['name'], 'timezone' => ($data['timezone'] ?? null) ?: null]);

        // the page only offers a language when there is a choice: leave the saved one alone when the field was not sent
        if ($request->has('locale')) {
            $user->forceFill(['locale' => ($data['locale'] ?? null) ?: null]);
        }

        if ($request->has('theme')) {
            $user->forceFill(['theme' => ($data['theme'] ?? null) ?: null]);
        }

        if ($changing) {
            $user->forceFill(['email' => $email, 'email_verified_at' => null]);
        }

        $user->save();

        if (! $changing) {
            return back()->with('status', __('Your profile is saved.'));
        }

        AccountEvent::record($user, 'email_changed');

        if (method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('status', __('Your profile is saved. We have sent a link to your new address: please confirm it.'));
    }
}
