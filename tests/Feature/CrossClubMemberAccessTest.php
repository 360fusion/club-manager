<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A club admin holds power over their own club's members only. Every `{userId}` admin route must treat a
 * user outside the club as not found, without changing them or revealing who they are.
 */
class CrossClubMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    private Club $a;

    private Club $b;

    private User $adminA;

    private User $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->a = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->b = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $this->adminA = User::factory()->create();
        $this->a->users()->attach($this->adminA->id, ['role' => 'admin', 'status' => 'active']);

        $this->memberB = User::factory()->create(['name' => 'Zebediah Outsider', 'email' => 'zebediah@club-b.test']);
        $this->b->users()->attach($this->memberB->id, ['role' => 'member', 'status' => 'active']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>}>
     */
    public static function userRoutes(): array
    {
        return [
            'show' => ['get', 'admin.users.show', []],
            'invite' => ['post', 'admin.users.invite', []],
            'revoke invite' => ['post', 'admin.users.revoke_invite', []],
            'status' => ['post', 'admin.users.status.update', ['status' => 'past']],
            'role' => ['post', 'admin.users.role.update', ['role' => 'admin']],
            'rank' => ['post', 'admin.users.rank.update', ['rank' => 'Secretary']],
            'committee role' => ['post', 'admin.users.committee_role.update', ['committee_role' => 'chair']],
            'remove' => ['delete', 'admin.users.destroy', []],
            'force delete' => ['delete', 'admin.users.force_delete', []],
        ];
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('userRoutes')]
    public function test_admin_cannot_reach_a_user_outside_their_club(string $method, string $route, array $payload): void
    {
        $response = $this->actingAs($this->adminA)->{$method}(
            route($route, ['clubSlug' => 'club-a', 'userId' => $this->memberB->id]),
            $payload,
        );

        $this->assertContains($response->getStatusCode(), [403, 404], "{$route} answered {$response->getStatusCode()} for a user outside the club");
        $this->assertStringNotContainsString('Zebediah', (string) $response->getContent());
        $this->assertStringNotContainsString('zebediah@club-b.test', (string) $response->getContent());
        $this->assertSame('active', $this->b->users()->where('users.id', $this->memberB->id)->first()->pivot->status);
        $this->assertSame('member', $this->b->users()->where('users.id', $this->memberB->id)->first()->pivot->role);
    }

    /**
     * @param  array<string, string>  $payload
     */
    #[DataProvider('userRoutes')]
    public function test_refused_request_carries_no_flash_message_naming_the_user(string $method, string $route, array $payload): void
    {
        $this->actingAs($this->adminA)->{$method}(
            route($route, ['clubSlug' => 'club-a', 'userId' => $this->memberB->id]),
            $payload,
        );

        $this->assertStringNotContainsString('Zebediah', json_encode(session()->all()));
    }
}
