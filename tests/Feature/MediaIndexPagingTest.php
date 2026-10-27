<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaIndexPagingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);

        foreach (range(1, 65) as $i) {
            $this->club->addMedia(UploadedFile::fake()->image("photo-{$i}.jpg", 20, 20))->toMediaCollection('gallery');
        }
    }

    private function listing(array $query = [])
    {
        return $this->actingAs($this->admin)->getJson(route('admin.media.index', ['clubSlug' => 'club-a'] + $query))->assertOk();
    }

    public function test_the_file_manager_loads_a_page_at_a_time_and_says_when_there_is_more(): void
    {
        $first = $this->listing(['page' => 1]);
        $this->assertCount(60, $first->json('media'));
        $this->assertTrue($first->json('has_more'));
        $this->assertSame(65, $first->json('total'));

        $second = $this->listing(['page' => 2]);
        $this->assertCount(5, $second->json('media'));
        $this->assertFalse($second->json('has_more'));
        $this->assertNotContains($second->json('media.0.id'), array_column($first->json('media'), 'id'));
    }

    public function test_callers_that_do_not_ask_for_a_page_get_every_file_and_the_filters_still_list_them_all(): void
    {
        $all = $this->listing();
        $this->assertCount(65, $all->json('media'));
        $this->assertFalse($all->json('has_more'));
        $this->assertSame(['jpg'], $all->json('available_extensions'));
    }
}
