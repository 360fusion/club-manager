<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every page that needs no record id must open without a server error for the person it is
 * meant for: superadmin pages for a superadmin, other signed-in pages for the club's owner,
 * public pages for a guest. AdminRouteSweepTest covers who is refused.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_without_a_record_id_opens_for_the_person_it_is_meant_for(): void
    {
        $club = Club::factory()->create();
        $owner = User::factory()->create();
        $club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $superadmin = User::factory()->create();
        $superadmin->forceFill(['is_super_admin' => true])->save();
        $hall = MasonicHall::factory()->create();
        $lodge = Lodge::factory()->create(['masonic_hall_id' => $hall->id]);

        $failures = [];
        $opened = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $otherParameters = array_diff($route->parameterNames(), ['clubSlug', 'slug']);

            if (! in_array('GET', $route->methods(), true) || $otherParameters !== [] || preg_match('#^(webhooks|internal|api|livewire|_|storage|up$|logout)#', $uri)) {
                continue;
            }

            $user = match (true) {
                str_starts_with($uri, 'superadmin') => $superadmin,
                preg_grep('#^auth(:|$)#', array_filter($route->gatherMiddleware(), 'is_string')) !== [] => $owner,
                default => null,
            };
            $slug = match (true) {
                str_starts_with($uri, 'lodges/halls/') => $hall->slug,
                str_starts_with($uri, 'lodges/') => $lodge->slug,
                default => $club->slug,
            };

            $request = $user ? $this->actingAs($user) : $this;
            $response = $request->get('/'.str_replace(['{clubSlug}', '{slug}'], $slug, $uri));
            $opened++;

            if ($response->getStatusCode() >= 500) {
                $failures[] = $uri.' => '.$response->getStatusCode().' '.($response->exception?->getMessage() ?? '');
            }

            auth()->logout();
        }

        $this->assertGreaterThan(100, $opened);
        $this->assertSame([], $failures, "Pages that failed:\n".implode("\n", $failures));
    }
}
