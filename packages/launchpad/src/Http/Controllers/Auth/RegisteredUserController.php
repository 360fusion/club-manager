<?php

namespace Launchpad\Http\Controllers\Auth;

use Closure;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Launchpad\Accounts\AccountRegistration;
use Launchpad\Accounts\Turnstile;
use Launchpad\Accounts\Waitlist;
use Launchpad\Http\Ui;

class RegisteredUserController extends Controller
{
    /** ?email= only fills the field, and only with a well-formed address */
    public function create(Request $request): View|Responsable
    {
        return Ui::render('auth.register', [
            'turnstileKey' => Turnstile::siteKey(),
            'email' => $this->wellFormedEmail($request->query('email')),
            'waitlistUrl' => Waitlist::enabled() ? route('waitlist') : null,
        ]);
    }

    public function store(Request $request, AccountRegistration $accounts, Turnstile $turnstile): RedirectResponse
    {
        $this->createAccount($request, $accounts, $turnstile);

        return redirect()->route('verification.notice');
    }

    /** Checks the form, makes the account and signs it in; the pages that sign people up differ only in where they send them next */
    protected function createAccount(Request $request, AccountRegistration $accounts, Turnstile $turnstile): Model
    {
        $turnstile->verify($request);

        $request->merge([
            'email' => AccountRegistration::normalizeEmail($request->input('email')),
            'name' => AccountRegistration::cleanName($request->input('name')),
        ]);

        $request->validate([
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail) {
                if ($problem = AccountRegistration::nameProblem($value)) {
                    $fail($problem);
                }
            }],
            'email' => [...AccountRegistration::emailRules(), fn (string $attribute, mixed $value, Closure $fail) => Waitlist::allows((string) $value) ?: $fail(Waitlist::message())],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('Please agree to the Terms to create an account.')]);

        $user = $accounts->register($request->only('name', 'email', 'password'));

        Auth::login($user);
        $request->session()->regenerate();

        return $user;
    }

    private function wellFormedEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = AccountRegistration::normalizeEmail($email);

        return Validator::make(['email' => $email], ['email' => 'email|max:255'])->passes() ? $email : null;
    }
}
