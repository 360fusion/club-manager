<?php

namespace Tests\Feature;

use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Rowing',
            'code' => 'rowing',
            'available_modules' => ['website_builder', 'memberships'],
            'default_settings' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'status' => 'active',
            'settings' => [
                'primary_color' => '#0369a1',
                'tagline' => 'Excellence on the Thames',
            ],
        ]);
    }

    public function test_admin_can_add_member_and_send_email_invitation(): void
    {
        Mail::fake();

        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.store', ['clubSlug' => $this->club->slug]), [
                'name' => 'Jane Invited',
                'email' => 'jane@example.com',
                'role' => 'member',
                'send_invite' => true,
            ]);

        $response->assertRedirect();

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);

        $pivot = $user->clubs()->where('clubs.id', $this->club->id)->first()->pivot;
        $this->assertNotNull($pivot->invitation_token);
        $this->assertNotNull($pivot->invited_at);

        Mail::assertSent(MemberInvitationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_invited_member_can_view_activation_form(): void
    {
        $user = User::factory()->create();
        $token = 'test-token-12345';

        $this->club->users()->attach($user->id, [
            'role' => 'member',
            'invitation_token' => $token,
            'invited_at' => now(),
        ]);

        $response = $this->get(route('invitation.accept', ['slug' => $this->club->slug, 'token' => $token]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Auth/AcceptInvitation')
            ->where('token', $token)
            ->where('user.email', $user->email)
        );
    }

    public function test_invited_member_can_set_password_and_activate_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('temp-secret')]);
        $token = 'test-token-activate-99';

        $this->club->users()->attach($user->id, [
            'role' => 'member',
            'invitation_token' => $token,
            'invited_at' => now(),
            'status' => 'active',
        ]);

        $response = $this->post(route('invitation.submit', ['slug' => $this->club->slug, 'token' => $token]), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertRedirect(route('member.dashboard', ['slug' => $this->club->slug]));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue(Hash::check('new-secure-password', $user->password));

        $pivot = $user->clubs()->where('clubs.id', $this->club->id)->first()->pivot;
        $this->assertNull($pivot->invitation_token);
        $this->assertNotNull($pivot->invitation_accepted_at);
    }

    public function test_admin_can_revoke_member_invitation(): void
    {
        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);

        $member = User::factory()->create();
        $token = 'revoke-token-123';
        $this->club->users()->attach($member->id, [
            'role' => 'member',
            'invitation_token' => $token,
            'invited_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.revoke_invite', ['clubSlug' => $this->club->slug, 'userId' => $member->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pivot = $member->clubs()->where('clubs.id', $this->club->id)->first()->pivot;
        $this->assertNull($pivot->invitation_token);
        $this->assertNull($pivot->invited_at);
    }

    public function test_expired_invitation_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = 'expired-token-456';

        // Set invited_at to 15 days ago (default limit is 14 days)
        $this->club->users()->attach($user->id, [
            'role' => 'member',
            'invitation_token' => $token,
            'invited_at' => now()->subDays(15),
        ]);

        $response = $this->get(route('invitation.accept', ['slug' => $this->club->slug, 'token' => $token]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_existing_platform_user_shares_single_account_and_accesses_multiple_clubs(): void
    {
        // 1. Existing user with established password in Club A
        $user = User::factory()->create([
            'email' => 'existing.member@example.com',
            'password' => Hash::make('original-secret-123'),
        ]);

        $this->club->users()->attach($user->id, [
            'role' => 'member',
            'invitation_accepted_at' => now(),
            'status' => 'active',
        ]);

        // 2. Create second Club B
        $clubB = Club::create([
            'club_type_id' => $this->club->club_type_id,
            'name' => 'Cambridge Boat Club',
            'slug' => 'cambridge-boating',
            'status' => 'active',
        ]);

        // 3. Admin of Club B adds existing user
        $adminB = User::factory()->create();
        $clubB->users()->attach($adminB->id, ['role' => 'admin', 'status' => 'active']);

        $response = $this->actingAs($adminB)
            ->post(route('admin.users.store', ['clubSlug' => $clubB->slug]), [
                'name' => 'Existing Member',
                'email' => 'existing.member@example.com',
                'role' => 'member',
                'send_invite' => true,
            ]);

        $response->assertRedirect();

        // 4. Verify user was attached to Club B without duplicating user record
        $userCount = User::where('email', 'existing.member@example.com')->count();
        $this->assertEquals(1, $userCount, 'Existing platform user should only have 1 single account.');

        // 5. Verify user now belongs to 2 clubs
        $this->assertCount(2, $user->fresh()->clubs);

        // 6. Test logging in via invitation for Club B with existing password
        $pivotB = $user->clubs()->where('clubs.id', $clubB->id)->first()->pivot;
        $token = $pivotB->invitation_token;

        $acceptResponse = $this->post(route('invitation.submit', ['slug' => $clubB->slug, 'token' => $token]), [
            'password' => 'original-secret-123',
        ]);

        $acceptResponse->assertRedirect(route('member.dashboard', ['slug' => $clubB->slug]));
        $this->assertAuthenticatedAs($user);

        // Verify pivot is active
        $pivotBUpdated = $user->fresh()->clubs()->where('clubs.id', $clubB->id)->first()->pivot;
        $this->assertNotNull($pivotBUpdated->invitation_accepted_at);
        $this->assertNull($pivotBUpdated->invitation_token);
    }
}
