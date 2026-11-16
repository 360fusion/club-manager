<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of a person's own Activity page: a sign-in or a change to how they sign in */
class AccountEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * What each type says, in English.
     *
     * @deprecated Use label(), which translates
     */
    public const LABELS = [
        'signed_in' => 'Signed in',
        'password_changed' => 'Password changed',
        'two_factor_on' => 'Two-factor sign-in turned on',
        'two_factor_off' => 'Two-factor sign-in turned off',
        'email_changed' => 'Email address changed',
        'support_signed_in' => 'Support signed in to your account to help you',
        'sessions_ended' => 'Signed out of other devices',
        'sign_in_method_removed' => 'Sign-in method removed',
        'sign_in_method_added' => 'Sign-in method added',
    ];

    /** What a type says on the page (an unknown type is shown as it is) */
    public static function label(string $type): string
    {
        return match ($type) {
            'signed_in' => __('Signed in'),
            'password_changed' => __('Password changed'),
            'two_factor_on' => __('Two-factor sign-in turned on'),
            'two_factor_off' => __('Two-factor sign-in turned off'),
            'email_changed' => __('Email address changed'),
            'support_signed_in' => __('Support signed in to your account to help you'),
            'sessions_ended' => __('Signed out of other devices'),
            'sign_in_method_removed' => __('Sign-in method removed'),
            'sign_in_method_added' => __('Sign-in method added'),
            default => $type,
        };
    }

    public static function record(object $user, string $type): void
    {
        $request = request();

        static::create([
            'user_id' => $user->getKey(),
            'type' => $type,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
