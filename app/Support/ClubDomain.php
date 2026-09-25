<?php

namespace App\Support;

use App\Models\Club;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * The one place a lodge's custom domain is validated, saved and checked. Three admin screens used to each
 * have their own copy of this; now they all call here, so the rule and the "what happens when it changes"
 * logic can only ever be one thing.
 */
class ClubDomain
{
    /**
     * The validation rule for a custom domain field, ready to drop into any `$request->validate()` call.
     *
     * @return list<mixed>
     */
    public static function rule(Club $club): array
    {
        return ['nullable', 'string', 'max:255', 'regex:/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', Rule::unique('clubs', 'custom_domain')->ignore($club->id)];
    }

    /**
     * Save a (possibly unchanged) domain. A genuinely new domain always starts "pending": it only becomes
     * active once {@see verify()} confirms the DNS record is in place, whoever last saved it.
     */
    public static function apply(Club $club, ?string $rawDomain): void
    {
        $domain = $rawDomain !== null && trim($rawDomain) !== '' ? strtolower(trim($rawDomain)) : null;

        if ($domain === $club->custom_domain) {
            return;
        }

        $club->custom_domain = $domain;
        $club->domain_status = $domain ? 'pending' : null;
        $club->domain_verified_at = null;
    }

    /**
     * What a lodge needs to set up at its DNS provider: the host a CNAME record points to, and that host's IPv4
     * addresses for a bare domain (which most providers only allow an A record on). The addresses are looked up
     * once an hour and left empty when they cannot be found.
     *
     * @return array{type: string, target: string, ips: list<string>}
     */
    public static function instructions(): array
    {
        $target = (string) config('services.club_domain.target', 'manager.360fusionhosting.co.uk');

        return [
            'type' => 'CNAME',
            'target' => $target,
            'ips' => app()->runningUnitTests() ? [] : Cache::remember('club_domain.target_ips.'.$target, 3600, function () use ($target) {
                $records = @dns_get_record($target, DNS_A);

                return is_array($records) ? array_values(array_unique(array_filter(array_column($records, 'ip')))) : [];
            }),
        ];
    }

    /**
     * Look up whether the domain's DNS now points here, and record the result. Returns whether it is verified.
     */
    public static function verify(Club $club): bool
    {
        if (! $club->custom_domain) {
            return false;
        }

        $target = self::instructions()['target'];
        $verified = self::resolves($club->custom_domain, $target);

        $club->update([
            'domain_status' => $verified ? 'active' : 'pending',
            'domain_verified_at' => $verified ? now() : null,
        ]);

        return $verified;
    }

    /**
     * Whether the domain's DNS (CNAME, or an A/AAAA record shared with the target) points at our target host.
     */
    private static function resolves(string $domain, string $target): bool
    {
        if (app()->runningUnitTests()) {
            // A real DNS lookup is a network call the test suite should never make.
            return false;
        }

        $cname = @dns_get_record($domain, DNS_CNAME);

        if (is_array($cname) && collect($cname)->contains(fn ($record) => rtrim((string) ($record['target'] ?? ''), '.') === rtrim($target, '.'))) {
            return true;
        }

        $ours = @dns_get_record($target, DNS_A + DNS_AAAA);
        $theirs = @dns_get_record($domain, DNS_A + DNS_AAAA);

        if (! is_array($ours) || ! is_array($theirs) || $ours === [] || $theirs === []) {
            return false;
        }

        $ourAddresses = collect($ours)->pluck('ip')->filter()->all();

        return collect($theirs)->pluck('ip')->filter()->contains(fn ($ip) => in_array($ip, $ourAddresses, true));
    }
}
