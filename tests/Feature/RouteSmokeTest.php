<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * No page that needs no id may answer 500, whoever asks: a logged-out visitor, a plain member, a club owner or a
 * super admin. It would have caught the passkey endpoints that failed for everyone.
 */
class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    private const SKIP = '#(logout|webhook|oauth|callback|return|refresh|verify|reset|unsubscribe|sign/|\.ics|feed|livewire|storage|_ignition|up$|export|download|pdf|csv|preview-link|stripe|paypal)#';

    /**
     * @return list<string>
     */
    private function urls(): array
    {
        $urls = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true) || preg_match(self::SKIP, $uri)) {
                continue;
            }

            $uri = str_replace(['{clubSlug}', '{slug}'], 'smoke-lodge', $uri);
            $uri = preg_replace('#/\{[a-zA-Z]+\?\}#', '', $uri);

            if (! str_contains($uri, '{')) {
                $urls[$uri] = true;
            }
        }

        return array_keys($urls);
    }

    public function test_no_parameterless_page_answers_500_for_anyone(): void
    {
        $club = Club::factory()->create(['slug' => 'smoke-lodge']);
        $owner = User::factory()->create();
        $club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->withExceptionHandling();
        $failures = [];

        foreach ([null, $member, $owner, $super] as $who) {
            $who ? $this->actingAs($who) : auth()->logout();

            foreach ($this->urls() as $url) {
                $status = $this->get('/'.$url)->getStatusCode();

                if ($status >= 500) {
                    $failures[] = ($who ? "user {$who->id}" : 'guest')." GET /{$url} => {$status}";
                }
            }
        }

        $this->assertSame([], $failures, "Pages that crashed:\n".implode("\n", $failures));
    }
}
