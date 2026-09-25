<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\PlantsForeignRecords;
use Tests\TestCase;

/**
 * An admin of one club must not be able to read or change another club's records by guessing ids.
 *
 * The attacker's club owns no records at all, so any id that exists belongs to one of the two seeded
 * clubs. Every admin route that takes a record id is requested with ids 1-24; afterwards nothing in the
 * database may have changed and no seeded club's content may appear in any response.
 */
class CrossClubRecordSweepTest extends TestCase
{
    use PlantsForeignRecords;
    use RefreshDatabase;

    private const IDS = 24;

    /**
     * Payloads for write routes whose validation the generic solver cannot satisfy.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PAYLOADS = [
        'POST {clubSlug}/admin/users/{userId}/role' => ['role' => 'admin'],
        'PUT {clubSlug}/admin/accounting/journal-entries/{id}' => [
            'description' => 'x',
            'entry_date' => '2026-06-01',
            'items' => [['account_id' => 1, 'debit' => 10, 'credit' => 0], ['account_id' => 2, 'debit' => 0, 'credit' => 10]],
        ],
        'POST {clubSlug}/admin/charity/festival/giving/{memberId}' => ['regular_giving_amount' => 12345, 'total_donated_to_date' => 54321],
    ];

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function recordRoutes(): array
    {
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! preg_match('#^\{(clubSlug|slug)\}/admin#', $uri)) {
                continue;
            }

            if (! preg_match('#\{(?!clubSlug|slug)[^}]+\}#', $uri)) {
                continue;
            }

            $method = array_values(array_intersect($route->methods(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']))[0] ?? null;

            if ($method) {
                $routes[] = [$method, $uri];
            }
        }

        return $routes;
    }

    /**
     * The urls to try for a route: every id in turn for the first record parameter (others fixed at 1), then
     * every id for each other record parameter (the first fixed at 1). String-keyed parameters get a real key.
     *
     * @return list<array{0: string, 1: string}> pairs of a label for the ids used and the url
     */
    private function urlsFor(string $uri, string $slug): array
    {
        $named = ['key' => 'event_booking_confirmation', 'purpose' => 'year_audit_auditor_one', 'report' => 'account_summary', 'tab' => 'overview'];
        preg_match_all('#\{([^}?]+)\??\}#', $uri, $found);
        $params = array_values(array_filter($found[1], fn (string $name) => ! in_array($name, ['clubSlug', 'slug'], true)));
        $recordParams = array_values(array_filter($params, fn (string $name) => ! isset($named[$name])));

        $combos = [];
        foreach ($recordParams === [] ? [null] : $recordParams as $varying) {
            foreach (range(1, self::IDS) as $id) {
                $values = [];
                foreach ($params as $name) {
                    $values[$name] = $named[$name] ?? ($name === $varying ? $id : 1);
                }
                $combos[json_encode($values)] = $values;
            }
        }

        $urls = [];
        foreach ($combos as $label => $values) {
            $url = str_replace(['{clubSlug}', '{slug}'], $slug, $uri);
            foreach ($values as $name => $value) {
                $url = preg_replace('#\{'.preg_quote($name, '#').'\??\}#', (string) $value, $url);
            }
            $urls[] = [$label, '/'.$url];
        }

        return $urls;
    }

    /**
     * @return array{leaks: list<string>, mutations: list<string>, errors: list<string>}
     */
    private function runSweep(bool $asOwner = false, ?string $only = null): array
    {
        $this->seed(DatabaseSeeder::class);

        $type = ClubType::first();
        $attackerClub = $asOwner
            ? Club::where('slug', 'lodge-of-fraternity')->firstOrFail()
            : Club::withoutEvents(fn () => Club::create(['club_type_id' => $type->id, 'name' => 'Attacker Club', 'slug' => 'attacker-club', 'status' => 'active']));
        $slug = $attackerClub->slug;
        $attacker = User::factory()->create(['id' => 9000]);
        $attackerClub->users()->attach($attacker->id, ['role' => 'owner', 'status' => 'active']);

        $skipped = $this->plantForeignRecords(Club::where('id', '!=', $asOwner ? 0 : $attackerClub->id)->pluck('id')->all());

        $this->withExceptionHandling();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->actingAs($attacker);

        $baseline = '';
        foreach (['dashboard', 'pages', 'posts', 'events', 'members', 'settings', 'accounting'] as $section) {
            $baseline .= (string) $this->get("/{$slug}/admin/".$section)->getContent();
        }

        $canaries = array_values(array_filter($this->canaries(), fn (string $c) => ! $this->contains($baseline, $c)));
        $this->assertNotEmpty($canaries, 'No canary strings found; the seeded data changed shape.');

        $leaks = [];
        $mutations = [];
        $errors = [];
        $coverage = [];

        foreach ($this->recordRoutes() as [$method, $uri]) {
            if ($only !== null && ! str_contains($uri, $only)) {
                continue;
            }

            foreach ($this->urlsFor($uri, $slug) as [$ids, $url]) {
                DB::beginTransaction();
                $before = $this->snapshot();
                $payload = self::PAYLOADS["{$method} {$uri}"] ?? [];
                $response = $this->send($method, $url, $payload);

                for ($round = 0; $round < 4 && $method !== 'GET'; $round++) {
                    $errors422 = $this->validationErrors($response);

                    if (($response->getStatusCode() !== 302 && $response->getStatusCode() !== 422) || $errors422 === []) {
                        break;
                    }

                    $payload = $this->fillFromErrors($payload, $errors422);
                    $response = $this->send($method, $url, $payload);
                }

                $status = $response->getStatusCode();
                $bucket = $this->validationErrors($response) !== [] ? 'validation' : $status;
                $coverage["{$method} {$uri}"][$bucket] = ($coverage["{$method} {$uri}"][$bucket] ?? 0) + 1;

                if ($status >= 500) {
                    $errors["{$method} {$uri} (ids {$ids}) => {$status} ".mb_substr((string) $response->getContent(), 0, 140)] = true;
                }

                if (! $asOwner && $status < 400) {
                    $body = (string) $response->getContent();

                    foreach ($canaries as $canary) {
                        if ($this->contains($body, $canary)) {
                            $leaks["{$method} {$uri} (ids {$ids}) shows \"{$canary}\""] = true;
                            break;
                        }
                    }
                }

                foreach ($this->foreignChanges($before, $this->snapshot(), $attackerClub->id) as $change) {
                    $mutations["{$method} {$uri} (ids {$ids}) [{$status}] changed {$change}"] = true;
                }

                DB::rollBack();
            }
        }

        if (getenv('SWEEP_COVERAGE')) {
            file_put_contents(getenv('SWEEP_COVERAGE').($asOwner ? '.owner' : ''), json_encode(['coverage' => $coverage, 'skipped_tables' => $skipped], JSON_PRETTY_PRINT));
        }

        return ['leaks' => array_keys($leaks), 'mutations' => array_keys($mutations), 'errors' => array_keys($errors)];
    }

    public function test_admin_of_one_club_cannot_read_or_change_another_clubs_records(): void
    {
        $found = $this->runSweep();

        $this->assertSame([], $found['leaks'], "Responses that exposed another club's content:\n".implode("\n", $found['leaks']));
        $this->assertSame([], $found['mutations'], "Requests that changed data with another club's ids:\n".implode("\n", $found['mutations']));
        $this->assertSame([], $found['errors'], "Requests that crashed:\n".implode("\n", $found['errors']));
    }

    /**
     * Diagnostic: the same requests as the club that owns the records, so a route that is reachable here but
     * refused above is known to be guarded, and one that is refused in both was never really exercised.
     */
    public function test_owner_coverage_for_comparison(): void
    {
        if (! getenv('SWEEP_COVERAGE')) {
            $this->markTestSkipped('Set SWEEP_COVERAGE to a file path to write the comparison.');
        }

        $this->runSweep(true);
        $this->assertTrue(true);
    }

    /**
     * Control: two deliberately unscoped routes, defined only here, must be caught. If they are not, a clean
     * result above proves nothing.
     */
    public function test_the_sweep_catches_routes_that_forget_to_scope_by_club(): void
    {
        Route::middleware('web')->group(function () {
            Route::get('{clubSlug}/admin/__probe/read/{id}', fn (string $clubSlug, int $id) => Post::findOrFail($id)->title);
            Route::delete('{clubSlug}/admin/__probe/delete/{id}', function (string $clubSlug, int $id) {
                Post::findOrFail($id)->delete();

                return 'gone';
            });
        });

        $found = $this->runSweep(false, '__probe');

        $this->assertNotEmpty(preg_grep('#__probe/read#', $found['leaks']), 'Sweep missed an unscoped read.');
        $this->assertNotEmpty(preg_grep('#__probe/delete.*posts#', $found['mutations']), 'Sweep missed an unscoped delete.');
    }
}
