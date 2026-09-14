<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsletterAttachmentsTest extends TestCase
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
            'slug' => 'oxford-apollo',
            'lodge_number' => '357',
            'province_region' => 'Oxfordshire',
            'town_city' => 'Oxford',
            'is_directory_listed' => true,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_store_newsletter_with_file_attachments(): void
    {
        Storage::fake('public');

        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, ['role' => 'admin']);

        $pdf = UploadedFile::fake()->create('Meeting-Summons.pdf', 500, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post(route('admin.newsletters.store', $club->slug), [
                'subject' => 'Monthly Summons with Attachment',
                'content' => '<p>Please find attached the official summons document.</p>',
                'target_roles' => ['member'],
                'status' => 'draft',
                'new_attachments' => [$pdf],
            ]);

        $response->assertRedirect(route('admin.newsletters.index', $club->slug));

        $newsletter = Newsletter::where('club_id', $club->id)->first();
        $this->assertNotNull($newsletter);
        $this->assertEquals('Monthly Summons with Attachment', $newsletter->subject);
        $this->assertIsArray($newsletter->attachments);
        $this->assertCount(1, $newsletter->attachments);
        $this->assertEquals('Meeting-Summons.pdf', $newsletter->attachments[0]['name']);

        $storedPath = str_replace('/storage/', '', $newsletter->attachments[0]['url']);
        Storage::disk('public')->assertExists($storedPath);
    }
}
