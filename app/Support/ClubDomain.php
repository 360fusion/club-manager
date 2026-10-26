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
     * The DNS companies lodges commonly use, recognised by the end of their nameserver hostnames, with where to
     * sign in, their own help article and where their DNS screen is. Anything else gets the guide's generic steps.
     *
     * @var list<array{name: string, nameservers: list<string>, url: string, help: string, steps: string}>
     */
    private const PROVIDERS = [
        ['name' => 'GoDaddy', 'nameservers' => ['domaincontrol.com'], 'url' => 'https://dcc.godaddy.com/control/portfolio', 'help' => 'https://www.godaddy.com/help/add-a-cname-record-19236',
            'steps' => 'Sign in, open your Domain Portfolio, choose the domain, then the DNS tab and Add New Record.'],
        ['name' => '123 Reg', 'nameservers' => ['123-reg.co.uk'], 'url' => 'https://www.123-reg.co.uk/secure', 'help' => 'https://www.123-reg.co.uk/support/domains/how-do-i-set-up-a-cname-record-on-my-domain-name/',
            'steps' => 'Sign in to the Control Panel, choose the domain, then Manage DNS (Advanced DNS).'],
        ['name' => 'IONOS', 'nameservers' => ['ui-dns.com', 'ui-dns.org', 'ui-dns.de', 'ui-dns.biz', 'ui-dns.co.uk'], 'url' => 'https://my.ionos.co.uk/domains', 'help' => 'https://www.ionos.co.uk/help/domains/configuring-cname-records-for-subdomains/configuring-a-cname-record-for-a-subdomain/',
            'steps' => 'Sign in, open Domains & SSL, click the cog next to the domain, then DNS and Add record. The Name box is called "Host name".'],
        ['name' => 'Namecheap', 'nameservers' => ['registrar-servers.com'], 'url' => 'https://ap.www.namecheap.com/domains/list/', 'help' => 'https://www.namecheap.com/support/knowledgebase/article.aspx/9646/2237/how-to-create-a-cname-record-for-your-domain/',
            'steps' => 'Sign in, open Domain List, click Manage next to the domain, then the Advanced DNS tab and Add New Record. The Name box is called "Host".'],
        ['name' => 'Cloudflare', 'nameservers' => ['ns.cloudflare.com'], 'url' => 'https://dash.cloudflare.com/', 'help' => 'https://developers.cloudflare.com/dns/manage-dns-records/how-to/create-dns-records/',
            'steps' => 'Sign in, choose the domain, then DNS → Records → Add record. Set Proxy status to "DNS only" (grey cloud), or the record cannot be checked.'],
        ['name' => 'Fasthosts', 'nameservers' => ['livedns.co.uk', 'fasthosts.net.uk'], 'url' => 'https://admin.fasthosts.co.uk/', 'help' => 'https://www.fasthosts.co.uk/guides',
            'steps' => 'Sign in to the control panel, open Domain Names, choose the domain, then DNS Settings.'],
        ['name' => 'LCN', 'nameservers' => ['lcn.com'], 'url' => 'https://www.lcn.com/login', 'help' => 'https://www.lcn.com/support/articles/how-to-create-an-a-aaaa-cname-or-txt-record/',
            'steps' => 'Sign in to the Control Panel, choose the domain, then DNS Settings (tick the box to activate Advanced DNS if asked).'],
        ['name' => 'Squarespace', 'nameservers' => ['squarespacedns.com', 'googledomains.com'], 'url' => 'https://account.squarespace.com/domains', 'help' => 'https://support.squarespace.com/hc/en-us/articles/360002101888',
            'steps' => 'Sign in, open Domains, choose the domain, then DNS → Custom records → Add record. Domains moved over from Google Domains are managed here too.'],
        ['name' => 'Wix', 'nameservers' => ['wixdns.net'], 'url' => 'https://www.wix.com/account/domains', 'help' => 'https://support.wix.com/en/article/adding-or-updating-cname-records-in-your-wix-account',
            'steps' => 'Sign in, open Domains, click the ⋯ next to the domain, then Manage DNS Records and + Add Record under CNAME.'],
        ['name' => 'Krystal', 'nameservers' => ['krystal.co.uk', 'krystal.uk'], 'url' => 'https://krystal.io/client', 'help' => 'https://help.krystal.io/domains/managing-dns-for-standalone-domains-not-c-panel-hosted',
            'steps' => 'Sign in to the Client Area, open the domain\'s DNS Records, then Create Record (if the domain is on cPanel hosting, use the Zone Editor in cPanel instead).'],
    ];

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
     * Which company runs the domain's DNS, worked out from its nameservers (looked up once an hour), or null when
     * it is not one we know or cannot be found.
     *
     * @return array{name: string, url: string, help: string, steps: string}|null
     */
    public static function provider(string $domain): ?array
    {
        if (app()->runningUnitTests()) {
            return null;
        }

        $nameservers = Cache::remember('club_domain.nameservers.'.$domain, 3600, function () use ($domain) {
            // A subdomain such as www has no NS records of its own, so walk up to the zone that does.
            for ($name = $domain; substr_count($name, '.') >= 1; $name = substr($name, strpos($name, '.') + 1)) {
                $records = @dns_get_record($name, DNS_NS);

                if (is_array($records) && $records !== []) {
                    return array_values(array_filter(array_column($records, 'target')));
                }
            }

            return [];
        });

        return self::matchProvider($nameservers);
    }

    /**
     * The known DNS company whose nameservers these are, without the matching rules.
     *
     * @param  list<string>  $nameservers
     * @return array{name: string, url: string, help: string, steps: string}|null
     */
    public static function matchProvider(array $nameservers): ?array
    {
        foreach (self::PROVIDERS as $provider) {
            foreach ($nameservers as $host) {
                $host = strtolower(rtrim($host, '.'));

                foreach ($provider['nameservers'] as $suffix) {
                    if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                        return array_diff_key($provider, ['nameservers' => true]);
                    }
                }
            }
        }

        return null;
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
