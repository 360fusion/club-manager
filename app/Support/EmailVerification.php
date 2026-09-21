<?php

namespace App\Support;

/**
 * Whether new accounts must confirm their email address before signing in.
 *
 * On by default once a real mail driver is configured. While mail is only
 * logged (or unset) nobody could receive the link, so sign-up stays open;
 * REQUIRE_EMAIL_VERIFICATION overrides this either way.
 */
class EmailVerification
{
    public static function required(): bool
    {
        $override = config('auth.require_email_verification');

        if ($override !== null) {
            return (bool) $override;
        }

        return ! in_array(config('mail.default'), ['log', 'array', null, ''], true);
    }
}
