<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadsBlockTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $type->id, 'email' => 'club@example.org']);
        $this->admin = $this->user($this->club, 'admin');
    }

    private function user(Club $club, string $role, string $status = 'active'): User
    {
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    private function upload(?UploadedFile $file = null, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->postJson(
            route('admin.pages.downloads.upload', ['clubSlug' => $this->club->slug]),
            ['file' => $file ?? UploadedFile::fake()->create('Minutes September.pdf', 120, 'application/pdf')],
        );
    }

    private function storedFile(): int
    {
        return $this->club->addMedia(UploadedFile::fake()->create('Minutes September.pdf', 120, 'application/pdf'))
            ->toMediaCollection('page_downloads', 'local')->id;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function pageWith(int $mediaId, array $block = [], array $page = []): Page
    {
        return Page::create(array_merge([
            'club_id' => $this->club->id, 'title' => 'Documents', 'slug' => 'documents', 'is_published' => true,
            'blocks' => [array_merge(['type' => 'downloads', 'items' => [['id' => 'a', 'source' => 'upload', 'media_id' => $mediaId, 'title' => 'Minutes']]], $block)],
        ], $page));
    }

    private function fileUrl(int $mediaId, ?Club $club = null): string
    {
        return route('public.site.file', ['clubSlug' => ($club ?? $this->club)->slug, 'mediaId' => $mediaId]);
    }

    public function test_an_upload_lands_on_the_private_disk_and_is_not_in_the_file_manager(): void
    {
        $response = $this->upload()->assertOk()->assertJson(['success' => true, 'file_name' => 'Minutes-September.pdf', 'title' => 'Minutes September']);

        $media = $this->club->media()->findOrFail($response->json('media_id'));
        $this->assertSame('page_downloads', $media->collection_name);
        $this->assertSame('local', $media->disk);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());

        $listing = $this->actingAs($this->admin)->getJson(route('admin.media.index', ['clubSlug' => $this->club->slug]))->assertOk()->getContent();
        $this->assertStringNotContainsString('Minutes', $listing);
    }

    public function test_unsafe_oversize_and_over_quota_uploads_are_refused(): void
    {
        $this->upload(UploadedFile::fake()->create('shell.php', 1, 'application/x-php'))->assertUnprocessable();
        $this->upload(UploadedFile::fake()->create('drawing.svg', 1, 'image/svg+xml'))->assertUnprocessable();
        $this->upload(UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'))->assertUnprocessable();

        $this->club->update(['settings' => ['storage_quota_mb' => 0]]);
        $this->upload()->assertStatus(422)->assertJson(['success' => false]);

        $this->assertSame(0, $this->club->media()->count());
    }

    public function test_only_website_editors_can_upload(): void
    {
        $this->upload(as: $this->user($this->club, 'member'))->assertForbidden();
    }

    public function test_a_public_block_serves_its_file_to_anyone_with_safe_headers(): void
    {
        $id = $this->storedFile();
        $this->pageWith($id);

        $this->get($this->fileUrl($id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename="Minutes-September.pdf"');
    }

    public function test_a_download_setting_forces_an_attachment(): void
    {
        $id = $this->storedFile();
        $this->pageWith($id, ['open_in' => 'download']);

        $this->get($this->fileUrl($id))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="Minutes-September.pdf"');
    }

    public function test_a_members_only_block_is_served_to_active_members_only(): void
    {
        $id = $this->storedFile();
        $this->pageWith($id, ['members_only' => true]);

        $this->get($this->fileUrl($id))->assertRedirect(route('login'));
        $this->actingAs($this->user($this->club, 'member', 'pending'))->get($this->fileUrl($id))->assertRedirect(route('login'));
        $this->actingAs($this->user($this->club, 'member', 'past'))->get($this->fileUrl($id))->assertRedirect(route('login'));
        $this->actingAs($this->user($this->club, 'member'))->get($this->fileUrl($id))->assertOk();
    }

    public function test_a_members_only_page_protects_the_files_in_its_public_blocks(): void
    {
        $id = $this->storedFile();
        $this->pageWith($id, [], ['is_members_only' => true]);

        $this->get($this->fileUrl($id))->assertRedirect(route('login'));
    }

    public function test_a_file_must_be_listed_in_a_block_on_a_published_page_of_that_club(): void
    {
        $id = $this->storedFile();

        $this->get($this->fileUrl($id))->assertNotFound();

        $page = $this->pageWith($id, [], ['is_published' => false]);
        $this->get($this->fileUrl($id))->assertNotFound();

        $page->update(['is_published' => true]);
        $this->get($this->fileUrl($id))->assertOk();

        $other = Club::create(['name' => 'Other', 'slug' => 'other-club', 'club_type_id' => $this->club->club_type_id, 'email' => 'o@example.org']);
        $this->get($this->fileUrl($id, $other))->assertNotFound();

        $this->get($this->fileUrl(999999))->assertNotFound();
    }

    public function test_a_members_only_block_is_emptied_for_visitors_in_the_page_data(): void
    {
        $id = $this->storedFile();
        $this->pageWith($id, ['members_only' => true]);
        $url = route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'documents']);

        $this->get($url)->assertOk()->assertInertia(fn ($page) => $page
            ->where('page.blocks.0.locked', true)
            ->where('page.blocks.0.items', []));

        $this->actingAs($this->user($this->club, 'member'))->get($url)->assertInertia(fn ($page) => $page
            ->missing('page.blocks.0.locked')
            ->where('page.blocks.0.items.0.media_id', $id));
    }
}
