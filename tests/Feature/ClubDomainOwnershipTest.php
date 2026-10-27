<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Support\ClubDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubDomainOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN = 'www.lodge-of-fraternity.example';

    protected function tearDown(): void
    {
        ClubDomain::useDnsLookup(null);

        parent::tearDown();
    }

    private function club(string $status = 'pending', string $domain = self::DOMAIN): Club
    {
        return Club::factory()->create(['custom_domain' => $domain, 'domain_status' => $status]);
    }

    /**
     * Answers DNS lookups from a list: the domain's CNAME may point here, and its ownership TXT record may hold a value.
     */
    private function dns(string $domain, bool $pointsHere, ?string $txt): void
    {
        $target = (string) config('services.club_domain.target');

        ClubDomain::useDnsLookup(fn (string $host, int $type): array => match (true) {
            $host === $domain && $type === DNS_CNAME && $pointsHere => [['target' => $target]],
            $host === '_clubmanager-verify.'.$domain && $type === DNS_TXT && $txt !== null => [['txt' => $txt]],
            default => [],
        });
    }

    public function test_a_domain_pointing_here_without_the_ownership_record_stays_pending(): void
    {
        $club = $this->club();
        $this->dns(self::DOMAIN, pointsHere: true, txt: null);

        $this->assertFalse(ClubDomain::verify($club));
        $this->assertSame('pending', $club->fresh()->domain_status);
    }

    public function test_a_domain_pointing_here_with_the_ownership_record_goes_active(): void
    {
        $club = $this->club();
        $this->dns(self::DOMAIN, pointsHere: true, txt: ClubDomain::ownershipRecord($club)['value']);

        $this->assertTrue(ClubDomain::verify($club));
        $this->assertSame('active', $club->fresh()->domain_status);
        $this->assertNotNull($club->fresh()->domain_verified_at);
    }

    public function test_another_clubs_ownership_value_does_not_prove_ownership(): void
    {
        $club = $this->club();
        $other = $this->club('pending', 'www.other-lodge.example');
        $this->dns(self::DOMAIN, pointsHere: true, txt: ClubDomain::ownershipRecord($other)['value']);

        $this->assertFalse(ClubDomain::verify($club));
        $this->assertSame('pending', $club->fresh()->domain_status);
    }

    public function test_the_ownership_record_alone_is_not_enough_without_dns_pointing_here(): void
    {
        $club = $this->club();
        $this->dns(self::DOMAIN, pointsHere: false, txt: ClubDomain::ownershipRecord($club)['value']);

        $this->assertFalse(ClubDomain::verify($club));
    }

    public function test_a_domain_that_is_already_live_stays_live_when_rechecked_without_the_record(): void
    {
        $club = $this->club('active');
        $this->dns(self::DOMAIN, pointsHere: true, txt: null);

        $this->assertTrue(ClubDomain::verify($club));
        $this->assertSame('active', $club->fresh()->domain_status);
    }

    public function test_the_ownership_value_differs_between_clubs_and_domains(): void
    {
        $club = $this->club();
        $other = $this->club('pending', 'www.other-lodge.example');
        $renamed = $this->club('pending', 'www.renamed-lodge.example');

        $values = [
            ClubDomain::ownershipRecord($club)['value'],
            ClubDomain::ownershipRecord($other)['value'],
            ClubDomain::ownershipRecord($renamed)['value'],
        ];

        $this->assertCount(3, array_unique($values));
        $this->assertSame('_clubmanager-verify.'.self::DOMAIN, ClubDomain::ownershipRecord($club)['host']);
    }
}
