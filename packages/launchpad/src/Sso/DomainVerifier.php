<?php

namespace Launchpad\Sso;

use Closure;
use Launchpad\Models\SsoConnection;

/** Proving a team controls an email domain: a TXT record `launchpad-verification=<token>` on `_launchpad.<domain>` */
final class DomainVerifier
{
    /** @var (Closure(string): list<string>)|null */
    private static ?Closure $resolver = null;

    /** @param  (Closure(string): list<string>)|null  $resolver  for tests: the TXT values of a name */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @return array{name: string, value: string} */
    public static function record(SsoConnection $connection): array
    {
        return ['name' => '_launchpad.'.$connection->domain, 'value' => 'launchpad-verification='.$connection->verification_token];
    }

    public static function check(SsoConnection $connection): bool
    {
        $record = self::record($connection);

        return in_array($record['value'], self::txt($record['name']), true);
    }

    /** @return list<string> */
    private static function txt(string $name): array
    {
        if (self::$resolver) {
            return (self::$resolver)($name);
        }

        return array_values(array_map(fn ($r) => (string) ($r['txt'] ?? ''), @dns_get_record($name, DNS_TXT) ?: []));
    }
}
