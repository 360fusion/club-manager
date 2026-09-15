<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
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
        ]);

        $this->user = User::factory()->create([
            'name' => 'Jane Rower',
            'email' => 'jane@oxfordrowing.co.uk',
        ]);

        $this->club->users()->attach($this->user->id, [
            'role' => 'admin',
            'member_number' => 'OBC-101',
            'status' => 'active',
        ]);
    }

    public function test_user_can_update_profile_avatar_url_preset(): void
    {
        $avatarUrl = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb';

        $response = $this->actingAs($this->user)
            ->put(route('profile.update'), [
                'name' => 'Jane Rower Updated',
                'email' => 'jane@oxfordrowing.co.uk',
                'avatar_url' => $avatarUrl,
            ]);

        $response->assertRedirect();
        
        $this->user->refresh();
        $this->assertEquals('Jane Rower Updated', $this->user->name);
        $this->assertEquals($avatarUrl, $this->user->avatar_url);
    }

    public function test_user_can_upload_custom_profile_photo(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $response = $this->actingAs($this->user)
            ->put(route('profile.update'), [
                'name' => 'Jane Rower',
                'email' => 'jane@oxfordrowing.co.uk',
                'avatar' => $file,
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar_url);
        Storage::disk('public')->assertExists($this->user->avatar_url);
    }

    public function test_member_portal_can_update_profile_and_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('member_avatar.png', 300, 300);

        $response = $this->actingAs($this->user)
            ->post(route('member.profile.update', ['slug' => $this->club->slug]), [
                'name' => 'Jane Rower Member',
                'email' => 'jane@oxfordrowing.co.uk',
                'avatar' => $file,
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertEquals('Jane Rower Member', $this->user->name);
        $this->assertNotNull($this->user->avatar_url);
        Storage::disk('public')->assertExists($this->user->avatar_url);
    }
}
