<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Makes what a superadmin submitted or changed safe to keep in the audit log: a field named like a secret is
 * recorded as changed, never with its value, and long values are cut.
 */
final class SuperAdminLog
{
    public const CHANGED = '(changed)';

    private const MAX_VALUE = 1000;

    private const SECRET_WORDS = ['password', 'secret', 'token', 'key', 'apikey', 'credential', 'dsn', 'recovery', 'salt', 'passphrase', 'config'];

    private const NOISE = ['updated_at', 'created_at', 'remember_token'];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function clean(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if (in_array($key, self::NOISE, true)) {
                continue;
            }

            if (self::isSecret((string) $key)) {
                $out[$key] = self::CHANGED;
            } elseif (is_array($value)) {
                $out[$key] = self::clean($value);
            } elseif ($value instanceof \UnitEnum) {
                $out[$key] = $value instanceof \BackedEnum ? $value->value : $value->name;
            } elseif (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? Str::limit($value, self::MAX_VALUE, '...') : $value;
            } elseif ($value instanceof \DateTimeInterface) {
                $out[$key] = $value->format('Y-m-d H:i:s');
            } else {
                $out[$key] = '(file or object)';
            }
        }

        return $out;
    }

    private static function isSecret(string $key): bool
    {
        return (bool) array_intersect(preg_split('/[^a-z0-9]+/', Str::snake($key)) ?: [], self::SECRET_WORDS);
    }
}
