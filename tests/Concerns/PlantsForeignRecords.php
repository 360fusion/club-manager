<?php

namespace Tests\Concerns;

use App\Models\Club;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Fixtures and row-level change detection for tests that check one club cannot reach another club's records.
 */
trait PlantsForeignRecords
{
    private const IGNORED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'];

    /**
     * Columns that tie a row without a club column to a parent row that has one.
     */
    private const PARENT_COLUMNS = [
        'journal_entry_id' => 'accounting_journal_entries',
        'event_id' => 'events',
        'meeting_id' => 'meetings',
        'committee_meeting_id' => 'club_acc_committee_meetings',
        'member_id' => 'club_acc_members',
        'candidate_id' => 'club_acc_candidates',
        'newsletter_id' => 'newsletters',
        'bill_id' => 'accounting_bills',
        'registration_id' => 'event_registrations',
        'bank_import_id' => 'club_acc_bank_imports',
        'bank_account_id' => 'club_acc_bank_accounts',
    ];

    /**
     * @var list<array{0: string, 1: int, 2: int, 3: ?string}> table, id and club of every fixture row planted, and for a child row the column that ties it to its parent
     */
    private array $plantedRows = [];

    private const TEXT_COLUMNS = ['title', 'name', 'subject', 'vendor_name', 'first_name', 'last_name', 'email', 'heading', 'label'];

    /**
     * Every row of every table, keyed by table and row, with the club it belongs to when it has one.
     *
     * @return array<string, array<string, array{hash: string, club: mixed}>>
     */
    private function snapshot(): array
    {
        $rows = [];
        $clubsOfUser = [];

        foreach (DB::table('club_user')->get(['user_id', 'club_id']) as $membership) {
            $clubsOfUser[$membership->user_id][] = (int) $membership->club_id;
        }

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            if (in_array($table, self::IGNORED_TABLES, true)) {
                continue;
            }

            foreach (DB::table($table)->get() as $i => $row) {
                $row = (array) $row;
                $key = isset($row['id']) ? (string) $row['id'] : (isset($row['club_id'], $row['user_id']) ? $row['club_id'].':'.$row['user_id'] : 'row'.$i);
                $club = $row['club_id'] ?? ($table === 'clubs' ? ($row['id'] ?? null) : null);
                $links = [];
                $clubs = match (true) {
                    $table === 'users' => $clubsOfUser[$row['id']] ?? [],
                    $table === 'notifications' => $clubsOfUser[$row['notifiable_id'] ?? 0] ?? [],
                    default => [],
                };

                if ($club === null) {
                    foreach (self::PARENT_COLUMNS as $column => $parent) {
                        if (isset($row[$column]) && $parent !== $table) {
                            $links[] = [$parent, (string) $row[$column]];
                        }
                    }
                }

                $rows[$table][$key] = ['hash' => md5(json_encode($row)), 'club' => $club, 'links' => $links, 'clubs' => $clubs];
            }
        }

        // A row without a club takes the club of its parent; two passes cover a booking's guests, whose parent is a booking.
        for ($pass = 0; $pass < 2; $pass++) {
            foreach ($rows as $table => $tableRows) {
                foreach ($tableRows as $key => $row) {
                    if ($row['club'] !== null) {
                        continue;
                    }

                    foreach ($row['links'] as [$parent, $parentId]) {
                        $parentClub = $rows[$parent][$parentId]['club'] ?? null;

                        if ($parentClub !== null) {
                            $rows[$table][$key]['club'] = $parentClub;
                            break;
                        }
                    }
                }
            }
        }

        return $rows;
    }

    /**
     * Rows that were added, changed or removed and do not belong to the attacker's own club.
     *
     * @param  array<string, array<string, array{hash: string, club: mixed}>>  $before
     * @param  array<string, array<string, array{hash: string, club: mixed}>>  $after
     * @return list<string>
     */
    private function foreignChanges(array $before, array $after, int $attackerClubId): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $table) {
            foreach (array_unique([...array_keys($before[$table] ?? []), ...array_keys($after[$table] ?? [])]) as $key) {
                $was = $before[$table][$key] ?? null;
                $now = $after[$table][$key] ?? null;

                if ($was === $now) {
                    continue;
                }

                $club = ($now ?? $was)['club'];
                $clubs = ($now ?? $was)['clubs'] ?? [];

                if (in_array($attackerClubId, $clubs, true)) {
                    continue;
                }

                if ($club !== null && (int) $club === $attackerClubId) {
                    continue;
                }

                $changes[] = "{$table}#{$key}".($club === null ? ' (no club column)' : " (club {$club})");
            }
        }

        return $changes;
    }

    /**
     * One row in every club-scoped table for each seeded club, every text column carrying a marker unique to
     * that club, table and column. A marker showing up in a response names exactly what leaked.
     *
     * @param  list<int>  $clubIds
     * @return array<string, string> tables that could not be filled, with the reason
     */
    private function plantForeignRecords(array $clubIds): array
    {
        $skipped = [];
        DB::statement('PRAGMA defer_foreign_keys = ON');

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            $columns = Schema::getColumns($table);
            $names = array_column($columns, 'name');

            if (! in_array('club_id', $names, true) || in_array($table, ['club_user', 'sessions', 'migrations'], true)) {
                continue;
            }

            $allowed = [...$this->enumDefaults($table), ...$this->allowedValues($table)];

            foreach ($clubIds as $clubId) {
                $row = $this->rowFor($table, $columns, $allowed, (string) $clubId, ['club_id' => $clubId]);

                try {
                    $this->plantedRows[] = [$table, (int) DB::table($table)->insertGetId($row), (int) $clubId, null];
                } catch (\Throwable $e) {
                    $skipped[$table] = mb_substr($e->getMessage(), 0, 160);
                }

                if (in_array('deleted_at', $names, true)) {
                    foreach (['slug', 'uuid', 'template_key', 'key'] as $unique) {
                        if (isset($row[$unique])) {
                            $row[$unique] .= '-trashed';
                        }
                    }

                    try {
                        $this->plantedRows[] = [$table, (int) DB::table($table)->insertGetId([...$row, 'deleted_at' => '2026-06-02 12:00:00']), (int) $clubId, null];
                    } catch (\Throwable) {
                        // a unique constraint the twin cannot satisfy; the live row is enough
                    }
                }
            }
        }

        $this->plantChildRecords();
        $this->pointRowsAtTheirOwnClub();

        foreach ($clubIds as $clubId) {
            foreach (['general', 'accounting'] as $collection) {
                foreach ([null, '2026-06-02 12:00:00'] as $trashedAt) {
                    DB::table('media')->insert([
                        'deleted_at' => $trashedAt,
                        'model_type' => Club::class,
                        'model_id' => $clubId,
                        'uuid' => (string) Str::uuid(),
                        'collection_name' => $collection,
                        'name' => "Zzcanary c{$clubId} media {$collection}",
                        'file_name' => "zzcanary-c{$clubId}-{$collection}.png",
                        'mime_type' => 'image/png',
                        'disk' => 'public',
                        'conversions_disk' => 'public',
                        'size' => 1,
                        'manipulations' => '[]',
                        'custom_properties' => '[]',
                        'generated_conversions' => '[]',
                        'responsive_images' => '[]',
                        'created_at' => '2026-06-01 12:00:00',
                        'updated_at' => '2026-06-01 12:00:00',
                    ]);
                }
            }
        }

        return $skipped;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, string>  $allowed
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function rowFor(string $table, array $columns, array $allowed, string $tag, array $overrides = []): array
    {
        $row = [];

        foreach ($columns as $column) {
            $name = $column['name'];
            $type = strtolower($column['type_name']);

            if ($column['auto_increment']) {
                continue;
            }

            $row[$name] = match (true) {
                array_key_exists($name, $overrides) => $overrides[$name],
                isset($allowed[$name]) => $allowed[$name],
                str_contains($name, 'password') || str_contains($name, 'remember_token') => null,
                str_ends_with($name, '_at') || str_contains($type, 'timestamp') || str_contains($type, 'datetime') => $column['nullable'] ? null : '2026-06-01 12:00:00',
                $type === 'date' => '2026-06-01',
                $type === 'time' => '12:00:00',
                str_contains($type, 'json') => '[]',
                str_contains($type, 'bool') => 1,
                str_contains($type, 'int') => 1,
                str_contains($type, 'decimal') || str_contains($type, 'numeric') || str_contains($type, 'float') || str_contains($type, 'double') || str_contains($type, 'real') => 10,
                str_contains($name, 'email') => "zzcanary{$tag}x{$table}@canary.test",
                str_contains($name, 'uuid') => (string) Str::uuid(),
                str_contains($name, 'url') || str_contains($name, 'link') => "https://canary.test/zzcanary-c{$tag}-{$table}-{$name}",
                default => "Zzcanary c{$tag} {$table} {$name}",
            };
        }

        return $row;
    }

    /**
     * Fixture rows all reference `*_id = 1`; point each at a row of the same club instead, so one club's
     * fixtures never lean on another club's rows and the checks do not report that as a leak.
     */
    private function pointRowsAtTheirOwnClub(): void
    {
        $tables = collect(Schema::getTableListing())->map(fn (string $t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t)->all();
        $targets = [];

        foreach ($this->plantedRows as [$table, $id, $clubId, $parentColumn]) {
            foreach (Schema::getColumns($table) as $column) {
                $name = $column['name'];

                if (! str_ends_with($name, '_id') || in_array($name, ['club_id', 'id', $parentColumn], true)) {
                    continue;
                }

                if (str_ends_with($name, 'user_id')) {
                    $user = DB::table('club_user')->where('club_id', $clubId)->min('user_id');

                    if ($user !== null) {
                        DB::table($table)->where('id', $id)->update([$name => $user]);
                    }

                    continue;
                }

                $key = $name;

                if (! array_key_exists($key, $targets)) {
                    // counted_by_member_id -> by_member -> member: the shortest tail that names a table
                    $words = explode('_', Str::beforeLast($name, '_id'));
                    $targets[$key] = null;

                    for ($from = 0; $from < count($words) && $targets[$key] === null; $from++) {
                        $plural = Str::plural(implode('_', array_slice($words, $from)));
                        $matches = array_values(array_filter($tables, fn (string $t) => $t === $plural || str_ends_with($t, '_'.$plural)));
                        usort($matches, fn (string $a, string $b) => strlen($a) <=> strlen($b));
                        $targets[$key] = $matches[0] ?? null;
                    }
                }

                $target = $targets[$key];

                if ($target === null || $target === $table || ! Schema::hasColumn($target, 'club_id')) {
                    continue;
                }

                $own = DB::table($target)->where('club_id', $clubId)->min('id');

                if ($own !== null) {
                    DB::table($table)->where('id', $id)->update([$name => $own]);
                }
            }
        }
    }

    /**
     * Rows in tables that have no club column but hang off an event, a booking or a meeting, so routes such as
     * events/{id}/registrations/{registrationId} have something to find.
     */
    private function plantChildRecords(): void
    {
        foreach ([['event_id', 'events'], ['meeting_id', 'meetings'], ['committee_meeting_id', 'club_acc_committee_meetings'], ['candidate_id', 'club_acc_candidates'], ['registration_id', 'event_registrations']] as [$parentColumn, $parentTable]) {
            $parentClubs = Schema::hasColumn($parentTable, 'club_id')
                ? DB::table($parentTable)->pluck('club_id', 'id')
                : DB::table($parentTable)->join('events', 'events.id', '=', $parentTable.'.event_id')->pluck('events.club_id', $parentTable.'.id');
            $parents = $parentClubs->keys();

            foreach (Schema::getTableListing() as $table) {
                $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
                $columns = Schema::getColumns($table);
                $names = array_column($columns, 'name');

                if (in_array('club_id', $names, true) || ! in_array($parentColumn, $names, true) || $table === $parentTable) {
                    continue;
                }

                $allowed = [...$this->enumDefaults($table), ...$this->allowedValues($table)];

                foreach ($parents as $parentId) {
                    try {
                        $this->plantedRows[] = [$table, (int) DB::table($table)->insertGetId($this->rowFor($table, $columns, $allowed, "p{$parentId}", [$parentColumn => $parentId])), (int) $parentClubs[$parentId], $parentColumn];
                    } catch (\Throwable) {
                        // a constraint the generic row cannot satisfy
                    }
                }
            }
        }
    }

    /**
     * The first case of every enum-cast column, read from the models, so fixture rows hold values the app can load.
     *
     * @return array<string, string|int>
     */
    private function enumDefaults(string $table): array
    {
        static $byTable = null;

        if ($byTable === null) {
            $byTable = [];
            $base = base_path('app').DIRECTORY_SEPARATOR;

            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php' || ! str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $class = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', substr($file->getPathname(), strlen($base), -4));

                if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                    continue;
                }

                $model = new $class;

                foreach ($model->getCasts() as $column => $cast) {
                    if (is_string($cast) && enum_exists($cast) && is_subclass_of($cast, \BackedEnum::class)) {
                        $byTable[$model->getTable()][$column] = $cast::cases()[0]->value;
                    }
                }
            }
        }

        return $byTable[$table] ?? [];
    }

    /**
     * The first allowed value of each enum-style column (a CHECK ... IN (...) constraint on SQLite).
     *
     * @return array<string, string>
     */
    private function allowedValues(string $table): array
    {
        $sql = (string) (DB::selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table])->sql ?? '');
        preg_match_all('/"(\w+)" in \(\'([^\']*)\'/i', $sql, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => $m[2]])->all();
    }

    /**
     * Distinctive strings from the seeded clubs' rows, minus anything an unrelated admin page already shows.
     *
     * @return list<string>
     */
    private function canaries(): array
    {
        $found = [];

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            if (in_array($table, self::IGNORED_TABLES, true) || in_array($table, ['users', 'clubs', 'club_types'], true)) {
                continue;
            }

            foreach (array_intersect(self::TEXT_COLUMNS, Schema::getColumnListing($table)) as $column) {
                foreach (DB::table($table)->whereNotNull($column)->limit(40)->pluck($column) as $value) {
                    if (is_string($value) && preg_match('/^[A-Za-z0-9 ,.&+\'-]{10,80}$/', $value)) {
                        $found[$value] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    /**
     * Whether a response body contains a marker, however Inertia or HTML encoding has escaped it.
     */
    private function contains(string $body, string $canary): bool
    {
        return str_contains($body, $canary)
            || str_contains($body, htmlspecialchars($canary, ENT_QUOTES))
            || str_contains($body, trim(json_encode($canary, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_QUOT), '"'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $method, string $url, array $payload = []): TestResponse
    {
        try {
            return $this->call($method, $url, $payload);
        } catch (HttpExceptionInterface $e) {
            return new TestResponse(new Response($e->getMessage(), $e->getStatusCode()));
        } catch (\Throwable $e) {
            return new TestResponse(new Response(get_class($e).': '.$e->getMessage(), 500));
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function validationErrors(TestResponse $response): array
    {
        if ($response->getStatusCode() === 422) {
            $decoded = json_decode((string) $response->getContent(), true);

            return is_array($decoded) && is_array($decoded['errors'] ?? null) ? $decoded['errors'] : [];
        }

        if ($response->getStatusCode() !== 302) {
            return [];
        }

        $errors = session('errors');
        $bag = $errors instanceof ViewErrorBag ? $errors->getBag('default')->toArray() : (is_array($errors) ? ($errors['default'] ?? $errors) : []);

        if (is_array($bag) && isset($bag['messages']) && is_array($bag['messages'])) {
            $bag = $bag['messages'];
        }

        return is_array($bag) ? $bag : [];
    }

    /**
     * Guess values for the fields a validation failure names, so a write request reaches its record lookup.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, list<string>|string>  $errors
     * @return array<string, mixed>
     */
    private function fillFromErrors(array $payload, array $errors): array
    {
        foreach ($errors as $field => $messages) {
            $message = strtolower((string) json_encode($messages));
            $root = explode('.', $field)[0];

            $payload[$root] = match (true) {
                str_contains($message, 'email') => 'zz@canary.test',
                str_contains($message, 'valid url') => 'https://canary.test/x',
                str_contains($message, 'date') => '2026-06-01',
                str_contains($message, 'array') => [],
                str_contains($message, 'integer') || str_contains($message, 'number') || str_ends_with($root, '_id') => 1,
                str_contains($message, 'true or false') || str_contains($message, 'boolean') => true,
                str_contains($message, 'selected') && str_contains($message, 'invalid') => 'active',
                default => 'x',
            };
        }

        return $payload;
    }
}
