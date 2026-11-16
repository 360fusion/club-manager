<?php

namespace Launchpad\Http;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\HtmlString;

/**
 * Turns what a controller has into what can go to a browser as JSON: models and collections become arrays, enums their value, dates ISO text. Whatever
 * the keys say is a secret is dropped wherever it is found, so a model handed over by mistake does not take a password hash or a 2FA secret with it.
 */
final class Props
{
    private const SECRET_KEYS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'app_authentication_secret',
        'app_authentication_recovery_codes', 'token_hash', 'stripe_id', 'pm_type', 'pm_last_four'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function from(array $data): array
    {
        return (array) self::value($data);
    }

    private static function value(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof HtmlString => (string) $value,
            $value instanceof Arrayable => self::value($value->toArray()),
            is_array($value) => self::array($value),
            is_object($value) => self::array(get_object_vars($value)),
            default => null,
        };
    }

    /** @param  array<int|string, mixed>  $items */
    private static function array(array $items): array
    {
        $out = [];

        foreach ($items as $key => $item) {
            if (is_string($key) && in_array($key, self::SECRET_KEYS, true)) {
                continue;
            }

            $out[$key] = self::value($item);
        }

        return $out;
    }
}
