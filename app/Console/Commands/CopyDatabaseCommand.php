<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Copies every row from another database (the old MySQL one) into this app's database, which must be freshly migrated
 * (the starter rows some migrations add are replaced). Moving a server to PostgreSQL: point .env at the new database, run `migrate`, then this with the old
 * database's address. Parent tables go first so foreign keys hold, 0/1 become true/false where the new column is a
 * boolean, and PostgreSQL's id counters are moved past the copied ids. Nothing is written to the old database.
 */
class CopyDatabaseCommand extends Command
{
    protected $signature = 'app:copy-database
        {source : The old database, e.g. mysql://user:password@127.0.0.1:3306/forge (or the name of a configured connection)}
        {--chunk=500 : Rows read and written at a time}';

    protected $description = 'Copy all data from another database into this freshly migrated one (moving to PostgreSQL)';

    /** Tables the new database fills itself or that hold nothing worth carrying over. */
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches'];

    public function handle(): int
    {
        $source = $this->sourceConnection((string) $this->argument('source'));
        $target = DB::connection();

        $tables = array_values(array_diff(array_column($target->getSchemaBuilder()->getTables(), 'name'), self::SKIP));
        $sourceTables = array_column($source->getSchemaBuilder()->getTables(), 'name');

        // Some migrations add starter rows (email templates); those are cleared. Anyone signed up means it is in use.
        if (in_array('users', $tables, true) && $target->table('users')->exists()) {
            $this->error('The new database already has users. Copy only into a freshly migrated database (`php artisan migrate:fresh --force`).');

            return self::FAILURE;
        }

        $order = $this->parentsFirst($target, $tables);
        foreach (array_reverse($order) as $table) {
            $target->table($table)->delete();
        }

        $mismatches = [];

        foreach ($order as $table) {
            if (! in_array($table, $sourceTables, true)) {
                $this->line("  {$table}: not in the old database, left empty");

                continue;
            }

            $copied = $this->copyTable($source, $target, $table);
            $expected = $source->table($table)->count();
            $this->line("  {$table}: {$copied}");

            if ($copied !== $expected) {
                $mismatches[] = "{$table} ({$copied} of {$expected})";
            }
        }

        if ($target->getDriverName() === 'pgsql') {
            $this->resetSequences($target, $tables);
        }

        if ($mismatches !== []) {
            $this->error('Row counts differ for: '.implode(', ', $mismatches));

            return self::FAILURE;
        }

        $this->info('Copied '.count($tables).' tables.');

        return self::SUCCESS;
    }

    private function sourceConnection(string $source): Connection
    {
        if (! str_contains($source, '://')) {
            return DB::connection($source);
        }

        $driver = strtolower((string) parse_url($source, PHP_URL_SCHEME));
        $base = config("database.connections.{$driver}");

        if (! is_array($base)) {
            throw new RuntimeException("Unknown database type [{$driver}] in the source address.");
        }

        config(['database.connections.copy_source' => ['url' => $source] + $base]);

        return DB::connection('copy_source');
    }

    /**
     * Tables in an order where each comes after the tables its foreign keys point at.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function parentsFirst(Connection $target, array $tables): array
    {
        $parents = [];
        foreach ($tables as $table) {
            $parents[$table] = array_values(array_diff(array_intersect(array_column($target->getSchemaBuilder()->getForeignKeys($table), 'foreign_table'), $tables), [$table]));
        }

        $ordered = [];
        while ($parents !== []) {
            $ready = array_keys(array_filter($parents, fn (array $needs) => array_diff($needs, $ordered) === []));

            if ($ready === []) {
                throw new RuntimeException('These tables point at each other, so no order satisfies their foreign keys: '.implode(', ', array_keys($parents)));
            }

            sort($ready);
            array_push($ordered, ...$ready);
            $parents = array_diff_key($parents, array_flip($ready));
        }

        return $ordered;
    }

    private function copyTable(Connection $source, Connection $target, string $table): int
    {
        $targetColumns = collect($target->getSchemaBuilder()->getColumns($table))->keyBy('name');
        $columns = array_values(array_intersect(array_column($source->getSchemaBuilder()->getColumns($table), 'name'), $targetColumns->keys()->all()));
        $booleans = $targetColumns->filter(fn (array $column) => in_array(strtolower($column['type_name']), ['bool', 'boolean'], true))->keys()->all();
        $dates = $targetColumns->filter(fn (array $column) => str_contains(strtolower($column['type_name']), 'date') || str_contains(strtolower($column['type_name']), 'time'))->keys()->all();

        $query = $source->table($table)->select($columns);
        $query = in_array('id', $columns, true) ? $query->orderBy('id') : $query->orderBy($columns[0]);
        $copied = 0;

        foreach ($query->lazy((int) $this->option('chunk'))->chunk((int) $this->option('chunk')) as $rows) {
            $target->table($table)->insert($rows->map(function (object $row) use ($booleans, $dates) {
                $row = (array) $row;

                foreach ($booleans as $column) {
                    if (array_key_exists($column, $row) && $row[$column] !== null) {
                        $row[$column] = (bool) $row[$column];
                    }
                }

                // MySQL lets a "zero" date in; PostgreSQL does not, and it never meant a real day.
                foreach ($dates as $column) {
                    if (is_string($row[$column] ?? null) && str_starts_with($row[$column], '0000-00-00')) {
                        $row[$column] = null;
                    }
                }

                return $row;
            })->all());

            $copied += $rows->count();
        }

        return $copied;
    }

    /**
     * New rows must get ids after the copied ones.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(Connection $target, array $tables): void
    {
        foreach ($tables as $table) {
            if (! $target->getSchemaBuilder()->hasColumn($table, 'id')) {
                continue;
            }

            $sequence = $target->selectOne('select pg_get_serial_sequence(?, ?) as name', [$table, 'id'])?->name;

            if ($sequence) {
                $target->statement("select setval(?, coalesce((select max(id) from \"{$table}\"), 0) + 1, false)", [$sequence]);
            }
        }
    }
}
