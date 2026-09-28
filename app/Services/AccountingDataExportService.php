<?php

namespace App\Services;

use App\Models\Club;
use App\Support\Csv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Everything a club holds in its books, one CSV per table in a zip, so a lodge can leave
 * with its data or hand it to an examiner. Credentials (API keys, tokens, secrets) are
 * never written, only the fact that a gateway was connected.
 */
class AccountingDataExportService
{
    /**
     * Tables with no club_id of their own, reached through their parent: child => [parent, foreign key].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const CHILD_TABLES = [
        'accounting_journal_items' => ['accounting_journal_entries', 'journal_entry_id'],
        'club_acc_annual_officer_assignments' => ['club_acc_annual_officer_rosters', 'roster_id'],
        'club_acc_committee_agenda_items' => ['club_acc_committee_meetings', 'committee_meeting_id'],
        'club_acc_committee_attendees' => ['club_acc_committee_meetings', 'committee_meeting_id'],
        'club_acc_committee_tasks' => ['club_acc_committee_meetings', 'committee_meeting_id'],
        'club_acc_member_festival_giving' => ['club_acc_members', 'member_id'],
        'club_acc_member_import_rows' => ['club_acc_member_imports', 'import_id'],
    ];

    private const SECRET_COLUMN = '/(secret|token|api_key|password)/i';

    /**
     * @return list<string> the tables that hold this club's accounting records
     */
    public function tables(): array
    {
        $tables = collect(Schema::getTableListing())
            ->map(fn (string $t) => preg_replace('/^[^.]+\./', '', $t))
            ->filter(fn (string $t) => str_starts_with($t, 'accounting_') || str_starts_with($t, 'club_acc_') || in_array($t, ['invoices', 'independent_examiner_reports'], true))
            ->filter(fn (string $t) => Schema::hasColumn($t, 'club_id') || isset(self::CHILD_TABLES[$t]))
            ->sort()
            ->values();

        return $tables->all();
    }

    /**
     * Build the zip and return its path; the caller deletes it once sent.
     */
    public function build(Club $club): string
    {
        $dir = sys_get_temp_dir().'/accounting-export-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $zipPath = $dir.'.zip';

        try {
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the export archive.');
            }

            $tables = $this->tables();

            foreach ($tables as $table) {
                $this->writeTable($club, $table, "{$dir}/{$table}.csv");
                $zip->addFile("{$dir}/{$table}.csv", "{$table}.csv");
            }

            $zip->addFromString('README.txt', "Accounting export for {$club->name}\nGenerated ".now()->toDateTimeString()."\nOne CSV per table ({$this->count($tables)} tables). Passwords, API keys, tokens and secrets are not included.\n");
            $zip->close();
        } finally {
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($dir);
        }

        return $zipPath;
    }

    /**
     * @param  list<string>  $tables
     */
    private function count(array $tables): int
    {
        return count($tables);
    }

    private function writeTable(Club $club, string $table, string $path): void
    {
        $columns = array_values(array_filter(Schema::getColumnListing($table), fn (string $c) => ! preg_match(self::SECRET_COLUMN, $c)));
        $handle = fopen($path, 'w');
        fwrite($handle, Csv::line($columns));

        $query = DB::table($table)->select($columns);

        if (isset(self::CHILD_TABLES[$table])) {
            [$parent, $foreignKey] = self::CHILD_TABLES[$table];
            $query->whereIn($foreignKey, DB::table($parent)->where('club_id', $club->id)->select('id'));
        } else {
            $query->where('club_id', $club->id);
        }

        $query->orderBy(in_array('id', $columns, true) ? 'id' : $columns[0])->lazy(500)->each(function ($row) use ($handle, $columns) {
            fwrite($handle, Csv::line(array_map(fn ($c) => $this->cell($row->{$c}), $columns)));
        });

        fclose($handle);
    }

    private function cell(mixed $value): string|int|float|null
    {
        return is_bool($value) ? (int) $value : (is_scalar($value) || $value === null ? $value : json_encode($value));
    }
}
