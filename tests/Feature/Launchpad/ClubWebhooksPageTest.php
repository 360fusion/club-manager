<?php

namespace Tests\Feature\Launchpad;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Launchpad\Models\WebhookDelivery;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Webhooks\DeliverWebhook;
use Launchpad\Webhooks\Webhooks;
use Tests\TestCase;

/** A club's webhook endpoints, managed at /{club}/admin/webhooks */
class ClubWebhooksPageTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // the example host resolves to a public address; nothing is looked up
        Webhooks::resolveUsing(fn () => ['93.184.216.34']);

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->other = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $this->owner = $this->makeClubAdmin(User::factory()->create(), $this->club, 'owner');
    }

    protected function tearDown(): void
    {
        Webhooks::resolveUsing(null);

        parent::tearDown();
    }

    public function test_an_owner_adds_an_endpoint_and_sees_the_secret_once(): void
    {
        $this->actingAs($this->owner)
            ->post('/club-a/admin/webhooks', ['url' => 'https://hooks.example.com/x', 'events' => ['member.added']])
            ->assertRedirect('/club-a/admin/webhooks')
            ->assertSessionHas('new_secret', fn ($secret) => str_starts_with($secret, 'whsec_'));

        $endpoint = WebhookEndpoint::sole();
        $this->assertSame($this->club->id, $endpoint->workspace_id);
        $this->assertSame(['member.added'], $endpoint->events);
    }

    public function test_a_private_address_is_refused(): void
    {
        Webhooks::resolveUsing(fn () => ['127.0.0.1']);

        $this->actingAs($this->owner)
            ->post('/club-a/admin/webhooks', ['url' => 'http://127.0.0.1/x', 'events' => ['member.added']])
            ->assertSessionHasErrors('url');

        $this->assertSame(0, WebhookEndpoint::count());
    }

    public function test_an_unknown_event_is_refused(): void
    {
        $this->actingAs($this->owner)
            ->post('/club-a/admin/webhooks', ['url' => 'https://hooks.example.com/x', 'events' => ['nope']])
            ->assertSessionHasErrors('events.0');
    }

    public function test_another_clubs_endpoint_is_not_found(): void
    {
        $theirs = $this->endpointFor($this->other);

        $this->actingAs($this->owner)->post("/club-a/admin/webhooks/{$theirs->id}/toggle")->assertNotFound();
        $this->actingAs($this->owner)->post("/club-a/admin/webhooks/{$theirs->id}/test")->assertNotFound();
        $this->actingAs($this->owner)->delete("/club-a/admin/webhooks/{$theirs->id}")->assertNotFound();
        $this->assertTrue($theirs->fresh()->active);
    }

    public function test_an_endpoint_is_switched_off_and_deleted(): void
    {
        $endpoint = $this->endpointFor($this->club);

        $this->actingAs($this->owner)->post("/club-a/admin/webhooks/{$endpoint->id}/toggle")->assertRedirect();
        $this->assertFalse($endpoint->fresh()->active);

        $this->actingAs($this->owner)->delete("/club-a/admin/webhooks/{$endpoint->id}")->assertRedirect();
        $this->assertNull($endpoint->fresh());
    }

    public function test_send_test_queues_a_ping(): void
    {
        Queue::fake();
        $endpoint = $this->endpointFor($this->club);

        $this->actingAs($this->owner)->post("/club-a/admin/webhooks/{$endpoint->id}/test")->assertRedirect();

        Queue::assertPushed(DeliverWebhook::class, 1);
        $this->assertSame('ping', WebhookDelivery::sole()->event);
    }

    public function test_the_page_lists_the_clubs_endpoints_only(): void
    {
        $this->endpointFor($this->club);
        $this->endpointFor($this->other, 'https://theirs.example.com/x');

        $this->actingAs($this->owner)->get('/club-a/admin/webhooks')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Webhooks/Index')->has('endpoints', 1)
                ->where('endpoints.0.url', 'https://hooks.example.com/x'));
    }

    public function test_a_plain_member_is_refused(): void
    {
        $member = $this->makeClubAdmin(User::factory()->create(), $this->club, 'member');

        $this->actingAs($member)->get('/club-a/admin/webhooks')->assertForbidden();
    }

    public function test_a_treasurer_is_refused(): void
    {
        $treasurer = $this->makeClubAdmin(User::factory()->create(), $this->club, 'treasurer');

        $this->actingAs($treasurer)->get('/club-a/admin/webhooks')->assertForbidden();
    }

    public function test_a_super_admin_gets_in(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($staff)->get('/club-a/admin/webhooks')->assertOk();
    }

    private function endpointFor(Club $club, string $url = 'https://hooks.example.com/x'): WebhookEndpoint
    {
        return WebhookEndpoint::create(['workspace_id' => $club->id, 'url' => $url, 'secret' => Webhooks::newSecret(), 'events' => ['member.added'], 'active' => true]);
    }
}
