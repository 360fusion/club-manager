<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\ClubUpdate;
use App\Models\User;
use App\Services\WeeklyUpdateDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklyUpdateDigestTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(): Club
    {
        $clubType = ClubType::firstOrCreate([
            'code' => 'masonic',
        ], [
            'name' => 'Masonic Lodge',
            'available_modules' => ['newsletters'],
            'default_settings' => [],
        ]);

        return Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Apollo Lodge',
            'slug' => 'oxford-apollo-' . uniqid(),
            'lodge_number' => '357',
            'is_active' => true,
        ]);
    }

    public function test_can_create_club_update_and_manage_status_lifecycle(): void
    {
        $club = $this->createClub();
        $user = User::factory()->create();

        // 1. Create Draft update
        $update = ClubUpdate::create([
            'club_id' => $club->id,
            'author_id' => $user->id,
            'title' => 'Visiting Summons - Lodge No. 100',
            'category' => 'summons',
            'summary' => "From: secretary@test.com\nSent: Monday\n\nPlease join us for our annual meeting.",
            'status' => 'draft',
        ]);

        $this->assertEquals('draft', $update->status);

        // 2. Approve update
        $update->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->assertEquals('approved', $update->status);
        $this->assertNotNull($update->approved_at);
    }

    public function test_digest_service_compiles_html_and_dispatches_newsletter(): void
    {
        $club = $this->createClub();
        $user = User::factory()->create();

        // Create approved update
        $update = ClubUpdate::create([
            'club_id' => $club->id,
            'author_id' => $user->id,
            'title' => 'Provincial News Bulletin',
            'category' => 'provincial',
            'summary' => 'Provincial Grand Lodge announcements and circulars.',
            'status' => 'approved',
            'attachments' => [
                ['name' => 'Bulletin PDF', 'url' => 'https://example.com/bulletin.pdf']
            ],
        ]);

        $digestService = new WeeklyUpdateDigestService();
        $newsletter = $digestService->dispatchWeeklyDigest($club);

        $this->assertNotNull($newsletter);
        $this->assertStringContainsString('Provincial News Bulletin', $newsletter->content);
        $this->assertStringContainsString('Bulletin PDF', $newsletter->content);

        // Verify update status transitioned to 'sent'
        $update->refresh();
        $this->assertEquals('sent', $update->status);
        $this->assertNotNull($update->sent_at);
        $this->assertEquals($newsletter->id, $update->newsletter_id);
    }

    public function test_artisan_command_sends_weekly_digest(): void
    {
        $club = $this->createClub();
        $user = User::factory()->create();

        ClubUpdate::create([
            'club_id' => $club->id,
            'author_id' => $user->id,
            'title' => 'Approved Test Notice',
            'category' => 'general',
            'summary' => 'Test summary text.',
            'status' => 'approved',
        ]);

        $this->artisan('app:send-weekly-digest', ['--club' => $club->slug])
            ->assertExitCode(0);

        $this->assertDatabaseHas('club_updates', [
            'club_id' => $club->id,
            'status' => 'sent',
        ]);
    }
}
