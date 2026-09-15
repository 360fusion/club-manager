<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSaveActionsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity',
            'slug' => 'oxford-boating',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_save_action_redirects_to_edit_page(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Save',
                'slug' => 'test-save',
                'status' => 'published',
                'action_type' => 'save',
            ]);

        $post = Post::where('slug', 'test-save')->firstOrFail();
        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts/{$post->id}/edit");
    }

    public function test_save_and_close_redirects_to_index(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Save and Close',
                'slug' => 'test-save-and-close',
                'status' => 'published',
                'action_type' => 'save_and_close',
            ]);

        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts");
    }

    public function test_save_and_new_redirects_to_create_form(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Save and New',
                'slug' => 'test-save-and-new',
                'status' => 'published',
                'action_type' => 'save_and_new',
            ]);

        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts/create");
    }

    public function test_save_and_duplicate_creates_copy_and_redirects_to_edit(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Original Post',
                'slug' => 'test-original-post',
                'status' => 'published',
                'action_type' => 'save_and_duplicate',
            ]);

        $duplicate = Post::where('title', 'Test Original Post (Copy)')->firstOrFail();
        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts/{$duplicate->id}/edit");
    }

    public function test_save_and_edit_redirects_to_edit_page(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Save and Edit',
                'slug' => 'test-save-and-edit',
                'status' => 'published',
                'action_type' => 'save_and_edit',
            ]);

        $post = Post::where('slug', 'test-save-and-edit')->firstOrFail();
        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts/{$post->id}/edit");
    }

    public function test_save_and_go_back_redirects_to_index(): void
    {
        $response = $this->actingAs($this->user)
            ->post("/clubs/{$this->club->slug}/admin/posts", [
                'title' => 'Test Save and Go Back',
                'slug' => 'test-save-and-go-back',
                'status' => 'published',
                'action_type' => 'save_and_go_back',
            ]);

        $response->assertRedirect("/clubs/{$this->club->slug}/admin/posts");
    }
}
