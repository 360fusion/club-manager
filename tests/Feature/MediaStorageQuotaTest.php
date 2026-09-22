<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->makeClubAdmin($this->user, $this->club);
    }

    public function test_default_quota_comes_from_config(): void
    {
        config(['club_media.default_storage_quota_mb' => 1]);

        $this->assertSame(1 * 1024 * 1024, $this->club->fresh()->storageQuotaBytes());
    }

    public function test_club_setting_overrides_the_default_quota(): void
    {
        config(['club_media.default_storage_quota_mb' => 5120]);
        $this->club->update(['settings' => ['storage_quota_mb' => 2]]);

        $this->assertSame(2 * 1024 * 1024, $this->club->fresh()->storageQuotaBytes());
    }

    public function test_null_quota_means_unlimited(): void
    {
        config(['club_media.default_storage_quota_mb' => null]);

        $this->assertNull($this->club->fresh()->storageQuotaBytes());
    }

    public function test_upload_is_blocked_once_the_quota_is_exceeded(): void
    {
        config(['club_media.default_storage_quota_mb' => 1]); // 1MB quota

        $bigFile = UploadedFile::fake()->create('big.pdf', 2000, 'application/pdf'); // 2MB

        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $bigFile, 'folder' => 'documents']);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('media', ['file_name' => 'big.pdf']);
    }

    public function test_trashed_media_still_counts_toward_usage(): void
    {
        config(['club_media.default_storage_quota_mb' => 1]); // 1MB quota

        // fake()->create() produces an empty file with only a spoofed getSize(); use real
        // content here so the size actually stored on disk (and summed for usage) is real.
        $file = UploadedFile::fake()->createWithContent('doc.pdf', str_repeat('a', 800 * 1024)); // 0.78MB
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'documents'])
            ->json('media.id');

        $this->assertDatabaseHas('media', ['id' => $id, 'size' => 800 * 1024]);

        $this->actingAs($this->user)->deleteJson("/{$this->club->slug}/admin/media/{$id}");

        $secondFile = UploadedFile::fake()->createWithContent('doc2.pdf', str_repeat('a', 800 * 1024)); // over quota once trashed usage is included
        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $secondFile, 'folder' => 'documents']);

        $response->assertStatus(422);
    }
}
