<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\MediaFolder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaFolderTest extends TestCase
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
            'name' => 'The Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->makeClubAdmin($this->user, $this->club);
    }

    public function test_admin_can_create_a_folder_inside_a_fixed_folder(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Committee Dinner 2026', 'parent' => 'galleries']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('folder.name', 'Committee Dinner 2026')
            ->assertJsonPath('folder.slug', 'committee-dinner-2026')
            ->assertJsonPath('folder.parent_slug', 'galleries');

        $this->assertDatabaseHas('media_folders', [
            'club_id' => $this->club->id,
            'name' => 'Committee Dinner 2026',
            'slug' => 'committee-dinner-2026',
            'parent_slug' => 'galleries',
        ]);
    }

    public function test_folder_creation_requires_a_parent(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'No Parent']);

        $response->assertStatus(422)->assertJsonValidationErrors(['parent']);
    }

    public function test_folder_creation_rejects_an_invalid_parent(): void
    {
        // 'accounting' is a real system folder but not nestable: its attachments are only ever
        // created by the Accounting module, never browsed/organized as an ordinary folder.
        foreach (['not-a-folder', 'all', 'trash', 'accounting'] as $invalidParent) {
            $response = $this->actingAs($this->user)
                ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Some Folder', 'parent' => $invalidParent]);
            $response->assertStatus(422);
        }

        $this->assertSame(0, MediaFolder::where('club_id', $this->club->id)->count());
    }

    public function test_folder_creation_rejects_another_custom_folder_as_the_parent(): void
    {
        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Parent Folder', 'parent' => 'galleries']);

        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Child Folder', 'parent' => 'parent-folder']);

        $response->assertStatus(422);
        $this->assertSame(1, MediaFolder::where('club_id', $this->club->id)->count());
    }

    public function test_folder_creation_rejects_duplicate_names_within_a_club(): void
    {
        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Regatta Photos', 'parent' => 'galleries']);

        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Regatta Photos', 'parent' => 'documents']);

        $response->assertStatus(422);
        $this->assertSame(1, MediaFolder::where('club_id', $this->club->id)->count());
    }

    public function test_folder_creation_rejects_reserved_words(): void
    {
        foreach (['Accounting', 'Logos', 'All', 'Trash', 'Summons'] as $reserved) {
            $response = $this->actingAs($this->user)
                ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => $reserved, 'parent' => 'galleries']);
            $response->assertStatus(422);
        }

        $this->assertSame(0, MediaFolder::where('club_id', $this->club->id)->count());
    }

    public function test_folder_creation_rejects_a_name_with_no_letters_or_numbers(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => '!!!', 'parent' => 'galleries']);

        $response->assertStatus(422);
    }

    public function test_admin_can_upload_a_file_into_a_custom_folder(): void
    {
        $folderId = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Committee Dinner 2026', 'parent' => 'galleries'])
            ->json('folder.id');

        $file = UploadedFile::fake()->image('table-plan.jpg');
        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media", [
            'file' => $file,
            'folder' => 'committee-dinner-2026',
        ]);

        $response->assertOk()->assertJsonPath('media.collection_name', 'committee-dinner-2026');

        $listRes = $this->actingAs($this->user)->getJson("/{$this->club->slug}/admin/media?folder=committee-dinner-2026");
        $listRes->assertOk()->assertJsonCount(1, 'media');

        $this->assertNotNull($folderId);
    }

    public function test_uploading_to_an_unknown_folder_is_rejected(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');
        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media", [
            'file' => $file,
            'folder' => 'not-a-real-folder',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['folder']);
    }

    public function test_admin_can_bulk_move_files_into_a_custom_folder(): void
    {
        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Gallery Two', 'parent' => 'galleries']);

        $file = UploadedFile::fake()->image('photo.jpg');
        $id = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'images'])
            ->json('media.id');

        $response = $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media/bulk-move", [
            'ids' => [$id],
            'folder' => 'gallery-two',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('media', ['id' => $id, 'collection_name' => 'gallery-two']);
    }

    public function test_admin_can_rename_a_custom_folder(): void
    {
        $folderId = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Old Name', 'parent' => 'galleries'])
            ->json('folder.id');

        $response = $this->actingAs($this->user)
            ->putJson("/{$this->club->slug}/admin/media/folders/{$folderId}", ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('folder.name', 'New Name');
        $this->assertDatabaseHas('media_folders', ['id' => $folderId, 'name' => 'New Name', 'slug' => 'old-name']);
    }

    public function test_admin_can_delete_an_empty_custom_folder(): void
    {
        $folderId = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Empty Folder', 'parent' => 'galleries'])
            ->json('folder.id');

        $response = $this->actingAs($this->user)->deleteJson("/{$this->club->slug}/admin/media/folders/{$folderId}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('media_folders', ['id' => $folderId]);
    }

    public function test_deleting_a_non_empty_custom_folder_is_blocked(): void
    {
        $folderId = $this->actingAs($this->user)
            ->postJson("/{$this->club->slug}/admin/media/folders", ['name' => 'Has Files', 'parent' => 'galleries'])
            ->json('folder.id');

        $file = UploadedFile::fake()->image('photo.jpg');
        $this->actingAs($this->user)->postJson("/{$this->club->slug}/admin/media", ['file' => $file, 'folder' => 'has-files']);

        $response = $this->actingAs($this->user)->deleteJson("/{$this->club->slug}/admin/media/folders/{$folderId}");

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertDatabaseHas('media_folders', ['id' => $folderId]);
    }

    public function test_a_club_cannot_manage_another_clubs_custom_folder(): void
    {
        $otherClubType = ClubType::create(['name' => 'Rowing Club', 'code' => 'rowing', 'available_modules' => ['posts']]);
        $otherClub = Club::create([
            'name' => 'Other Club',
            'slug' => 'other-club',
            'club_type_id' => $otherClubType->id,
            'is_active' => true,
        ]);
        $otherFolder = MediaFolder::create(['club_id' => $otherClub->id, 'name' => 'Secret', 'slug' => 'secret', 'parent_slug' => 'galleries']);

        $this->actingAs($this->user)->putJson("/{$this->club->slug}/admin/media/folders/{$otherFolder->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);

        $this->actingAs($this->user)->deleteJson("/{$this->club->slug}/admin/media/folders/{$otherFolder->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('media_folders', ['id' => $otherFolder->id, 'name' => 'Secret']);
    }
}
