<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every club admin page must refuse a plain member and an admin of another club,
 * so a route added later without protection fails here instead of in production.
 */
class AdminRouteSweepTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function adminGetUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) && preg_match('#^\{(clubSlug|slug)\}/(admin|members/export)#', $route->uri())) {
                $uris[] = $route->uri();
            }
        }

        return $uris;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function adminWriteRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $method = array_values(array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']))[0] ?? null;

            if ($method && preg_match('#^\{(clubSlug|slug)\}/(admin|domain$|members/)#', $route->uri())) {
                $routes[] = [$method, $route->uri()];
            }
        }

        return $routes;
    }

    private function fill(string $uri): string
    {
        return preg_replace(['#\{(clubSlug|slug)\}#', '#\{[^}]+\??\}#'], ['club-a', '1'], $uri);
    }

    public function test_club_admin_pages_refuse_members_and_admins_of_other_clubs(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $a = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $b = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $member = User::factory()->create();
        $a->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $b->users()->attach($otherAdmin->id, ['role' => 'admin', 'status' => 'active']);

        $uris = $this->adminGetUris();
        $this->assertNotEmpty($uris);

        $leaks = [];

        foreach ([$member, $otherAdmin] as $user) {
            foreach ($uris as $uri) {
                $status = $this->actingAs($user)->get('/'.$this->fill($uri))->getStatusCode();

                if ($status === 200) {
                    $leaks[] = ($user->is($member) ? 'member ' : 'other-club admin ').$uri;
                }
            }
        }

        $this->assertSame([], $leaks, "Admin pages that returned 200 to someone without access:\n".implode("\n", $leaks));
    }

    public function test_club_admin_actions_refuse_members_and_admins_of_other_clubs(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $a = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $b = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $member = User::factory()->create();
        $a->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $otherAdmin = User::factory()->create();
        $b->users()->attach($otherAdmin->id, ['role' => 'admin', 'status' => 'active']);

        $routes = $this->adminWriteRoutes();
        $this->assertNotEmpty($routes);

        $leaks = [];

        foreach ([$member, $otherAdmin] as $user) {
            foreach ($routes as [$method, $uri]) {
                $status = $this->actingAs($user)->call($method, '/'.$this->fill($uri))->getStatusCode();

                if ($status !== 403) {
                    $leaks[] = ($user->is($member) ? 'member ' : 'other-club admin ').$method.' '.$uri.' => '.$status;
                }
            }
        }

        $this->assertSame([], $leaks, "Admin actions that did not return 403:\n".implode("\n", $leaks));
    }

    public function test_member_area_pages_for_a_club_refuse_people_who_are_not_members_of_it(): void
    {
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $outsider = User::factory()->create();

        $leaks = [];

        foreach (Route::getRoutes() as $route) {
            if (! preg_match('#^members/\{slug\}#', $route->uri())) {
                continue;
            }

            $method = in_array('GET', $route->methods(), true) ? 'GET' : 'POST';
            $response = $this->actingAs($outsider)->call($method, '/'.$this->fill($route->uri()), ['attendance_status' => str_contains($route->uri(), '/events/') ? 'declined' : 'apologies']);
            $status = $response->getStatusCode();
            $location = (string) $response->headers->get('Location');
            $sentAway = $status === 302 && (str_contains($location, '/site/') || str_contains($location, '/members/dashboard'));

            if (! $sentAway && ! in_array($status, [403, 404], true)) {
                $leaks[] = $method.' '.$route->uri().' => '.$status.' '.$location;
            }
        }

        $this->assertSame([], $leaks, "Member pages open to non-members:\n".implode("\n", $leaks));
    }
}
