<?php

namespace Tests\Feature;

use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaVersionHistoryTest extends TestCase
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

    public function test_replacing_a_file_keeps_the_same_media_id_and_records_a_version(): void
    {
        $original = UploadedFile::fake()->create('agenda.pdf', 100, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $original, 'folder' => 'documents'])
            ->json('media.id');

        $replacement = UploadedFile::fake()->create('agenda-v2.pdf', 150, 'application/pdf');
        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/{$id}/replace", ['file' => $replacement]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('media.id', $id)
            ->assertJsonPath('media.file_name', 'agenda-v2.pdf')
            ->assertJsonPath('media.version_count', 1);

        $this->assertDatabaseHas('media', ['id' => $id, 'file_name' => 'agenda-v2.pdf']);
        $this->assertDatabaseHas('media_versions', [
            'media_id' => $id,
            'club_id' => $this->club->id,
            'file_name' => 'agenda.pdf',
        ]);
    }

    public function test_listing_versions_returns_the_prior_content(): void
    {
        $original = UploadedFile::fake()->image('photo.png', 100, 100);
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $original, 'folder' => 'images'])
            ->json('media.id');

        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/replace", [
            'file' => UploadedFile::fake()->image('photo-v2.png', 100, 100),
        ]);

        $response = $this->actingAs($this->user)->getJson("/{$this->club->slug}/admin/media/{$id}/versions");

        $response->assertOk()->assertJsonCount(1, 'versions')
            ->assertJsonPath('versions.0.file_name', 'photo.png');
    }

    public function test_restoring_a_version_restores_the_file_and_snapshots_the_current_state(): void
    {
        $original = UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $original, 'folder' => 'documents'])
            ->json('media.id');

        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/replace", [
            'file' => UploadedFile::fake()->create('doc-v2.pdf', 60, 'application/pdf'),
        ]);

        $versionId = $this->actingAs($this->user)
            ->getJson("/{$this->club->slug}/admin/media/{$id}/versions")
            ->json('versions.0.id');

        $restoreRes = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/{$id}/versions/{$versionId}/restore");

        $restoreRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('media.file_name', 'doc.pdf');

        $this->assertDatabaseHas('media', ['id' => $id, 'file_name' => 'doc.pdf']);

        // Restoring itself is undoable: two versions now exist (the original replace, and the pre-restore snapshot).
        $this->assertDatabaseCount('media_versions', 2);
    }

    public function test_accounting_protected_media_cannot_be_replaced_or_have_versions_restored(): void
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'accounting'])
            ->json('media.id');

        Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-1',
            'vendor_name' => 'Acme Supplies',
            'due_date' => now()->addDays(30),
            'amount' => 100,
            'status' => 'unpaid',
            'media_id' => $id,
        ]);

        $replaceRes = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/replace", [
            'file' => UploadedFile::fake()->create('receipt-v2.pdf', 50, 'application/pdf'),
        ]);
        $replaceRes->assertStatus(422)->assertJsonPath('success', false);

        // Version history (list and download) is also off-limits: it belongs to the Accounting page.
        $this->actingAs($this->user)->getJson("/{$this->club->slug}/admin/media/{$id}/versions")->assertStatus(403);
        $this->actingAs($this->user)->getJson("/{$this->club->slug}/admin/media/{$id}/versions/1/download")->assertStatus(403);
    }

    public function test_a_staff_role_with_edit_website_but_not_manage_billing_cannot_see_accounting_files(): void
    {
        // Grant edit_website (which gates the file manager) to "coach", without granting manage_billing
        // (which stays owner/admin/treasurer only) — a club can do this via its own permission matrix.
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], [
            'permission_matrix' => [
                'edit_website' => ['roles' => ['owner', 'admin', 'coach']],
            ],
        ])]);

        $coach = User::factory()->create();
        $this->makeClubAdmin($coach, $this->club, 'coach');

        $file = UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'accounting'])
            ->json('media.id');

        Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-4',
            'vendor_name' => 'Acme Supplies',
            'due_date' => now()->addDays(30),
            'amount' => 50,
            'status' => 'unpaid',
            'media_id' => $id,
        ]);

        // Can't browse straight to the Accounting folder.
        $this->actingAs($coach)->getJson("/{$this->club->slug}/admin/media?folder=accounting")->assertStatus(403);

        // The accounting file also doesn't leak into "All Files".
        $allRes = $this->actingAs($coach)->getJson("/{$this->club->slug}/admin/media");
        $allRes->assertOk();
        $this->assertNotContains($id, collect($allRes->json('media'))->pluck('id')->all());

        // Nor through the asset-usage lookup, even by guessing the media id.
        $this->actingAs($coach)->getJson("/{$this->club->slug}/admin/media/{$id}/usage")->assertStatus(403);

        // A billing-capable user (the seeded admin) still sees it fine.
        $this->actingAs($this->user)->getJson("/{$this->club->slug}/admin/media?folder=accounting")->assertOk();
    }

    public function test_cropping_an_image_downscales_oversized_output_like_store_and_replace_do(): void
    {
        $original = UploadedFile::fake()->image('original.png', 800, 600);
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $original, 'folder' => 'images'])
            ->json('media.id');

        $oversizedCrop = UploadedFile::fake()->image('cropped.png', 3000, 2000);
        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/crop", [
            'file' => $oversizedCrop,
            'save_mode' => 'replace',
        ]);

        $response->assertOk();

        $storedPath = Storage::disk('public')->path("{$id}/".$response->json('media.file_name'));
        [$width, $height] = getimagesize($storedPath);
        $this->assertLessThanOrEqual(1920, $width);
        $this->assertLessThanOrEqual(1920, $height);
    }

    public function test_restoring_a_version_is_blocked_once_it_would_exceed_the_storage_quota(): void
    {
        config(['club_media.default_storage_quota_mb' => 2]); // 2MB quota

        $fileA = UploadedFile::fake()->createWithContent('a.pdf', str_repeat('a', 900 * 1024));
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $fileA, 'folder' => 'documents'])
            ->json('media.id');

        $fileB = UploadedFile::fake()->createWithContent('b.pdf', str_repeat('a', 900 * 1024));
        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/replace", ['file' => $fileB]);

        // Usage is now ~1800KB of a 2048KB quota (live file B + version A). Restoring back to A adds
        // another ~900KB on top (the current B becomes a new version, A becomes live) — over quota.
        $versionId = $this->actingAs($this->user)
            ->getJson("/{$this->club->slug}/admin/media/{$id}/versions")
            ->json('versions.0.id');

        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/{$id}/versions/{$versionId}/restore");

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_bulk_move_skips_accounting_protected_media(): void
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'accounting'])
            ->json('media.id');

        Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-2',
            'vendor_name' => 'Acme Supplies',
            'due_date' => now()->addDays(30),
            'amount' => 50,
            'status' => 'unpaid',
            'media_id' => $id,
        ]);

        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/bulk-move", [
            'ids' => [$id],
            'folder' => 'documents',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('media', ['id' => $id, 'collection_name' => 'accounting']);
    }

    public function test_update_is_blocked_for_accounting_protected_media(): void
    {
        $file = UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'accounting'])
            ->json('media.id');

        Bill::create([
            'club_id' => $this->club->id,
            'bill_number' => 'BILL-3',
            'vendor_name' => 'Acme Supplies',
            'due_date' => now()->addDays(30),
            'amount' => 50,
            'status' => 'unpaid',
            'media_id' => $id,
        ]);

        $response = $this->actingAs($this->user)->putJson("/{$this->club->slug}/admin/media/{$id}", [
            'name' => 'Renamed Receipt',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseMissing('media', ['id' => $id, 'name' => 'Renamed Receipt']);
    }

    public function test_cropping_an_image_keeps_the_same_media_id_and_records_a_version(): void
    {
        $original = UploadedFile::fake()->image('original.png', 800, 600);
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $original, 'folder' => 'images'])
            ->json('media.id');

        $cropped = UploadedFile::fake()->image('cropped.png', 400, 400);
        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/{$id}/crop", [
            'file' => $cropped,
            'save_mode' => 'replace',
        ]);

        $response->assertOk()
            ->assertJsonPath('media.id', $id)
            ->assertJsonPath('media.version_count', 1);

        $this->assertDatabaseHas('media_versions', ['media_id' => $id]);
    }
}
