<?php

namespace Tests\Feature\Launchpad;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Launchpad\Models\WebhookEndpoint;
use Launchpad\Models\Workspace;
use Launchpad\Models\WorkspaceEvent;
use Launchpad\Webhooks\DeliverWebhook;
use Tests\TestCase;

/** A club is a Launchpad team: member changes go in its activity log and out to its webhooks */
class ClubMemberEventsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
    }

    /** @return list<string> the actions in the club's activity log, oldest first */
    private function actions(Club $club): array
    {
        return WorkspaceEvent::withoutGlobalScopes()->where('workspace_id', $club->id)->orderBy('id')->pluck('action')->all();
    }

    public function test_a_club_is_a_launchpad_team(): void
    {
        $team = $this->club->asTeam();

        $this->assertInstanceOf(Workspace::class, $team);
        $this->assertSame([$this->club->id, 'Club A'], [$team->id, $team->name]);
    }

    public function test_member_changes_are_recorded_once_each_and_other_columns_are_not(): void
    {
        $user = User::factory()->create(['name' => 'Mia Member']);

        $this->club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
        $this->club->users()->updateExistingPivot($user->id, ['rank' => 'Bro', 'phone' => '0123']);
        $this->club->users()->updateExistingPivot($user->id, ['role' => 'admin']);
        $this->club->users()->updateExistingPivot($user->id, ['status' => 'inactive']);
        $this->club->users()->detach($user->id);

        $this->assertSame(['member.added', 'member.role_changed', 'member.status_changed', 'member.removed'], $this->actions($this->club));
        $line = WorkspaceEvent::withoutGlobalScopes()->where(['workspace_id' => $this->club->id, 'action' => 'member.role_changed'])->first();
        $this->assertStringContainsString('Mia Member', $line->summary);
        $this->club->users()->attach(User::factory()->create(['name' => 'Sam Sec'])->id, ['role' => 'coach', 'status' => 'active']);
        $this->assertTrue(WorkspaceEvent::withoutGlobalScopes()->where('summary', 'added Sam Sec to the club as Secretary')->exists());
    }

    public function test_sync_from_the_user_side_is_recorded_too_and_other_clubs_see_nothing(): void
    {
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $user = User::factory()->create();

        $user->clubs()->syncWithoutDetaching([$this->club->id => ['role' => 'member', 'status' => 'active']]);

        $this->assertSame(['member.added'], $this->actions($this->club));
        $this->assertSame([], $this->actions($other));
    }

    public function test_a_subscribed_endpoint_is_sent_the_event(): void
    {
        Queue::fake();
        WebhookEndpoint::create(['workspace_id' => $this->club->id, 'url' => 'https://hooks.example.test/in', 'secret' => 's', 'events' => ['member.added'], 'active' => true]);

        $this->club->users()->attach(User::factory()->create()->id, ['role' => 'member', 'status' => 'active']);

        Queue::assertPushed(DeliverWebhook::class, 1);
    }
}
