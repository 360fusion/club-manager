<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryAdminTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts'],
        ]);

        $this->club = Club::create([
            'name' => 'Oxford Boating Club',
            'slug' => 'oxford-boating',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_admin_can_upload_file_to_media_library_folder(): void
    {
        $file = UploadedFile::fake()->image('club-logo.png', 400, 400);

        $response = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'logos',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('media.collection_name', 'logos');

        $this->assertDatabaseHas('media', [
            'model_id' => $this->club->id,
            'collection_name' => 'logos',
            'file_name' => 'club-logo.png',
        ]);
    }

    public function test_admin_can_fetch_and_filter_media_items_by_folder(): void
    {
        $logoFile = UploadedFile::fake()->image('logo.png');
        $docFile = UploadedFile::fake()->create('document.pdf', 100);

        $this->actingAs($this->user)->postJson("/clubs/{$this->club->slug}/admin/media", [
            'file' => $logoFile,
            'folder' => 'logos',
        ]);

        $this->actingAs($this->user)->postJson("/clubs/{$this->club->slug}/admin/media", [
            'file' => $docFile,
            'folder' => 'documents',
        ]);

        // Filter by logos
        $responseLogos = $this->actingAs($this->user)
            ->getJson("/clubs/{$this->club->slug}/admin/media?folder=logos");

        $responseLogos->assertOk()
            ->assertJsonCount(1, 'media')
            ->assertJsonPath('media.0.collection_name', 'logos');

        // Filter by all
        $responseAll = $this->actingAs($this->user)
            ->getJson("/clubs/{$this->club->slug}/admin/media?folder=all");

        $responseAll->assertOk()
            ->assertJsonCount(2, 'media');
    }

    public function test_admin_can_delete_media_item(): void
    {
        $file = UploadedFile::fake()->image('news-banner.jpg');

        $uploadRes = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'news',
            ]);

        $mediaId = $uploadRes->json('media.id');

        $deleteRes = $this->actingAs($this->user)
            ->deleteJson("/clubs/{$this->club->slug}/admin/media/{$mediaId}");

        $deleteRes->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('media', [
            'id' => $mediaId,
        ]);
    }

    public function test_upload_fails_if_file_exceeds_10mb(): void
    {
        // Fake file larger than 10MB (11MB = 11264 KB)
        $file = UploadedFile::fake()->create('large-doc.pdf', 11264);

        $response = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'documents',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_fails_for_executable_or_unallowed_file_types(): void
    {
        $file = UploadedFile::fake()->create('malicious.php', 100);

        $response = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'documents',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_fails_for_non_image_in_image_only_folder(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'logos', // logos folder only accepts images
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_fails_for_svg_with_embedded_script(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';
        $file = UploadedFile::fake()->createWithContent('exploit.svg', $svgContent);

        $response = $this->actingAs($this->user)
            ->postJson("/clubs/{$this->club->slug}/admin/media", [
                'file' => $file,
                'folder' => 'images',
            ]);

        $response->assertStatus(422);
    }
}
