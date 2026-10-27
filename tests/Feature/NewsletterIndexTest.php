<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterIndexTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => ['newsletters'], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
    }

    private function letter(string $subject, string $status = 'draft', array $roles = ['member'], $sentAt = null): Newsletter
    {
        return Newsletter::create(['club_id' => $this->club->id, 'subject' => $subject, 'content' => '<p>Body</p>', 'status' => $status, 'target_roles' => $roles, 'sent_at' => $sentAt]);
    }

    private function index(array $query = [])
    {
        return $this->actingAs($this->admin)->get(route('admin.newsletters.index', ['clubSlug' => 'club-a'] + $query));
    }

    public function test_the_list_is_paged_and_counts_cover_every_newsletter(): void
    {
        foreach (range(1, 18) as $i) {
            $this->letter("Letter {$i}", $i <= 5 ? 'sent' : 'draft', ['member'], $i <= 5 ? now()->subDays($i) : null);
        }

        $this->index()->assertInertia(fn ($page) => $page
            ->component('Admin/Newsletters/Index')
            ->has('newsletters.data', 15)
            ->where('newsletters.total', 18)
            ->where('counts.all', 18)
            ->where('counts.sent', 5)
            ->where('counts.draft', 13));

        $this->index(['page' => 2])->assertInertia(fn ($page) => $page->has('newsletters.data', 3));
    }

    public function test_the_search_status_role_and_sent_filters_run_on_the_server(): void
    {
        $this->letter('Installation summons', 'sent', ['admin'], now()->subDays(3));
        $this->letter('Old news', 'sent', ['member'], now()->subYears(2));
        $this->letter('Draft about dinner', 'draft', ['member']);

        $this->index(['q' => 'dinner'])->assertInertia(fn ($page) => $page->has('newsletters.data', 1)->where('newsletters.data.0.subject', 'Draft about dinner'));
        $this->index(['status' => 'sent'])->assertInertia(fn ($page) => $page->has('newsletters.data', 2));
        $this->index(['role' => 'admin'])->assertInertia(fn ($page) => $page->has('newsletters.data', 1)->where('newsletters.data.0.subject', 'Installation summons'));
        $this->index(['sent' => '30_days'])->assertInertia(fn ($page) => $page->has('newsletters.data', 1)->where('newsletters.data.0.subject', 'Installation summons'));
    }
}
