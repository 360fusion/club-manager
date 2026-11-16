<?php

namespace Launchpad\Accounts;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Launchpad\Launchpad;
use Launchpad\Models\SocialAccount;

/**
 * How an account comes to exist, and how an email address is kept and compared.
 *
 * An address is stored trimmed and in lower case, and every lookup compares it that way ("John.Doe@Gmail.com" is the same address as
 * "john.doe@gmail.com"), including against an old row that still holds capitals: on SQLite and Postgres the database would otherwise
 * call them two accounts.
 */
class AccountRegistration
{
    /** An address as it is stored and compared */
    public static function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    /** The account that has this address, whatever capitals either side used */
    public static function findByEmail(?string $email): ?Model
    {
        $email = self::normalizeEmail($email);

        return $email === '' ? null : Launchpad::userModel()::whereRaw('LOWER(email) = ?', [$email])->first();
    }

    /** Whether an account other than $exceptUserId already has this address */
    public static function emailTaken(?string $email, int|string|null $exceptUserId = null): bool
    {
        $email = self::normalizeEmail($email);

        return $email !== '' && Launchpad::userModel()::whereRaw('LOWER(email) = ?', [$email])
            ->when($exceptUserId, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    /**
     * A name as it is kept: one line, no markup, and none of the characters nobody can see (zero-width spaces, the controls that turn
     * text round). It is shown on pages and in mail, and "Welcome, <b>Win a prize</b>" is not a name.
     */
    public static function cleanName(?string $name): string
    {
        $name = strip_tags((string) $name);
        $name = preg_replace('/[\p{Cc}\p{Cf}\x{2028}\x{2029}]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    /** What a name must be to be given to an account: something that reads as a name, with no address or link in it */
    public static function nameProblem(string $name): ?string
    {
        if (! preg_match('/[\p{L}\p{N}]/u', $name)) {
            return __('Please use your name: it needs at least one letter.');
        }

        // a link, or an address, in a name is what a script writes: the name goes into mail and onto pages
        if (self::containsLinkOrAddress($name)) {
            return __('A name cannot contain a link or an email address.');
        }

        return null;
    }

    /** Whether a text has a web address or an email address in it */
    public static function containsLinkOrAddress(string $text): bool
    {
        return (bool) preg_match('~(https?:|ftp:|www\.)|\S+@\S+\.\S+~i', $text);
    }

    /** Whether the address is on a service that gives out throwaway inboxes (config launchpad.signup.blocked_domains) */
    public static function isThrowaway(?string $email): bool
    {
        $domain = Str::after(self::normalizeEmail($email), '@');

        foreach ((array) config('launchpad.signup.blocked_domains', []) as $blocked) {
            if ($domain === $blocked || str_ends_with($domain, '.'.$blocked)) {
                return true;
            }
        }

        return false;
    }

    /** Whether the address ends in the domain of a country that is not served (config launchpad.signup.blocked_country_domains) */
    public static function isFromBlockedCountry(?string $email): bool
    {
        $domain = rtrim(Str::after(self::normalizeEmail($email), '@'), '.');

        return in_array(mb_strtolower(Str::afterLast($domain, '.')), (array) config('launchpad.signup.blocked_country_domains', []), true);
    }

    /**
     * What an address must pass to be given an account: well formed (and, on a live site, on a domain that can receive mail), not taken,
     * not a throwaway inbox, and not on the domain of a country that is not served.
     *
     * @param  int|string|null  $exceptUserId  the account whose own address is not "taken" (a profile keeping its address)
     * @return list<mixed>
     */
    public static function emailRules(int|string|null $exceptUserId = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            config('launchpad.signup.dns_check') ? 'email:rfc,dns' : 'email:rfc',
            function (string $attribute, mixed $value, \Closure $fail) use ($exceptUserId) {
                $holder = self::emailTaken($value, $exceptUserId) ? self::findByEmail($value) : null;

                // a closed account keeps its address until it is removed, so that it can be restored
                if ($holder && method_exists($holder, 'isClosed') && $holder->isClosed()) {
                    $fail(__('That address belongs to an account that has been closed. Contact support to have it restored, or use it again once the account has been removed.'));
                } elseif (self::emailTaken($value, $exceptUserId)) {
                    $fail(trans('validation.unique', ['attribute' => $attribute]));
                } elseif (self::isThrowaway($value)) {
                    $fail(__('Please use an address you keep: temporary email services are not accepted.'));
                } elseif (self::isFromBlockedCountry($value)) {
                    $fail(__((string) config('launchpad.signup.blocked_country_message')));
                }
            },
        ];
    }

    /**
     * Creates the account and says so (Registered, which makes the framework mail the verification link).
     *
     * Pass $emailVerified when the provider the person signed in with vouches for the address: the account is made verified before
     * Registered is fired, so no mail asks for a click it does not need, and Verified follows.
     *
     * Pass $passwordChosen as false when nobody chose the password (a sign-in with Google gets a random one): the account may then set
     * a first password without giving a current one.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data, bool $emailVerified = false, bool $passwordChosen = true): Model
    {
        $email = self::normalizeEmail($data['email']);
        // a provider's name goes through the same cleaning; with nothing left, the part of the address before the @
        $name = self::cleanName($data['name']) ?: Str::before($email, '@');

        $model = Launchpad::userModel();
        $user = new $model;
        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($data['password']),
        ]);

        // the version of the Terms the person agreed to by signing up, and when
        $user->forceFill([
            'terms_version' => (string) config('launchpad.legal.terms_version'),
            'terms_accepted_at' => $user->freshTimestamp(),
            'password_set_at' => $passwordChosen ? $user->freshTimestamp() : null,
            'email_verified_at' => $emailVerified ? $user->freshTimestamp() : null,
        ])->save();

        event(new Registered($user));

        if ($emailVerified) {
            event(new Verified($user));
        }

        return $user;
    }

    /**
     * An address someone else registered but never proved now belongs to the person a trusted provider vouches for: whatever the first
     * registrant set up (password, second factor, linked sign-ins, signed-in browsers) stops working, and the address counts as verified.
     */
    public function takeBack(Model $user): void
    {
        Schema::hasTable('social_accounts') && SocialAccount::where('user_id', $user->getKey())->delete();

        $user->forceFill([
            'password' => Hash::make(Str::random(40)),
            'password_set_at' => null,
            'remember_token' => Str::random(60),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // browsers that are signed in to the account stop being (only when sessions are kept in the database)
        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->getKey())->delete();
        }

        $user->markEmailAsVerified();
    }
}
