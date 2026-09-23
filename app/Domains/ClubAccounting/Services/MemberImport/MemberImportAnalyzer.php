<?php

namespace App\Domains\ClubAccounting\Services\MemberImport;

use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportRow;
use App\Models\Club;
use App\Support\CsvReader;
use App\Support\MasonicRanks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Reads the uploaded file through the chosen column mapping, checks every row, works out which rows repeat an
 * existing member or each other, and stages the result. Nothing is written to the roster here.
 */
class MemberImportAnalyzer
{
    private const CHUNK = 500;

    /**
     * Why a mapping cannot be used, or null when it can.
     *
     * @param  array<int, string|null>  $mapping  column index => field key
     */
    public static function mappingProblem(array $mapping): ?string
    {
        $fields = array_values(array_filter($mapping));

        if ($fields !== array_unique($fields)) {
            $dupe = array_keys(array_filter(array_count_values($fields), fn ($n) => $n > 1))[0];

            return MemberImportFields::label($dupe).' is matched to more than one column.';
        }

        $hasNames = in_array('first_name', $fields, true) && in_array('last_name', $fields, true);

        if (! $hasNames && ! in_array(MemberImportFields::FULL_NAME, $fields, true)) {
            return 'Match a column to First name and Last name, or to Full name.';
        }

        if (in_array(MemberImportFields::FULL_NAME, $fields, true) && (in_array('first_name', $fields, true) || in_array('last_name', $fields, true))) {
            return 'Use either Full name or separate First name and Last name columns, not both.';
        }

        return null;
    }

    /**
     * Analyse the import's file with its current mapping and options, replacing any earlier staging.
     */
    public function analyse(MemberImport $import): MemberImport
    {
        $mapping = $import->mapping ?? [];

        if ($problem = self::mappingProblem($mapping)) {
            throw new InvalidArgumentException($problem);
        }

        $document = CsvReader::read(Storage::disk('local')->path((string) $import->stored_path));
        $dateOrder = $import->option('date_order', 'dmy');

        $indexes = $this->indexRoster($import->club_id);
        $club = $import->club;
        $seen = ['email' => [], 'grand_lodge_number' => [], 'name' => []];
        $staged = [];
        $counts = ['new' => 0, 'duplicate' => 0, 'possible_duplicate' => 0, 'error' => 0];

        foreach ($document['rows'] as $offset => $cells) {
            $rowNumber = $offset + 1;
            [$data, $errors, $warnings] = $this->extract($cells, $mapping, $dateOrder, $club);

            $status = MemberImportRow::NEW;
            $matchType = null;
            $matchId = null;
            $duplicateOf = null;
            $action = 'create';

            if ($errors !== []) {
                $status = MemberImportRow::ERROR;
                $action = 'skip';
            } else {
                [$status, $matchType, $matchId] = $this->matchExisting($data, $indexes);
                $action = $status === MemberImportRow::NEW ? 'create' : 'skip';

                [$fileMatch, $firstRow] = $this->matchEarlierRow($data, $seen, $rowNumber);

                if ($fileMatch === 'firm') {
                    $duplicateOf = $firstRow;
                    if ($status === MemberImportRow::NEW) {
                        $status = MemberImportRow::DUPLICATE;
                        $matchType = 'file';
                        $action = 'create';
                    }
                } elseif ($fileMatch === 'weak' && $status === MemberImportRow::NEW) {
                    $status = MemberImportRow::POSSIBLE;
                    $matchType = 'file';
                    $duplicateOf = $firstRow;
                    $action = 'skip';
                }
            }

            $counts[$status]++;

            $staged[] = [
                'import_id' => $import->id,
                'row_number' => $rowNumber,
                'data' => json_encode($data),
                'status' => $status,
                'match_type' => $matchType,
                'match_member_id' => $matchId,
                'duplicate_of_row' => $duplicateOf,
                'action' => $action,
                'raw' => $errors === [] ? null : json_encode($cells),
                'errors' => $errors === [] ? null : json_encode($errors),
                'warnings' => $warnings === [] ? null : json_encode($warnings),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($import, $staged, $counts, $document) {
            $import->rows()->delete();

            foreach (array_chunk($staged, self::CHUNK) as $chunk) {
                MemberImportRow::insert($chunk);
            }

            $import->update([
                'headers' => $document['headers'],
                'total_rows' => count($staged),
                'new_count' => $counts[MemberImportRow::NEW],
                'duplicate_count' => $counts[MemberImportRow::DUPLICATE],
                'possible_count' => $counts[MemberImportRow::POSSIBLE],
                'error_count' => $counts[MemberImportRow::ERROR],
            ]);
        });

        return $import->refresh();
    }

    /**
     * One row's cells as clean member values, plus what is wrong with it. Empty cells are left out, so an
     * update can never blank a field.
     *
     * @param  array<int, string>  $cells
     * @param  array<int, string|null>  $mapping
     * @return array{0: array<string, string>, 1: array<int, string>, 2: array<int, string>}
     */
    public function extract(array $cells, array $mapping, string $dateOrder, ?Club $club = null): array
    {
        $data = [];
        $errors = [];
        $warnings = [];

        foreach ($mapping as $index => $field) {
            if ($field === null) {
                continue;
            }

            $value = MemberImportValues::clean($cells[$index] ?? '');

            if ($value === '') {
                continue;
            }

            if ($field === MemberImportFields::FULL_NAME) {
                foreach (MemberImportValues::splitName($value) as $part => $partValue) {
                    if ($partValue !== null && $partValue !== '' && ! isset($data[$part])) {
                        $data[$part] = $partValue;
                    }
                }

                continue;
            }

            $spec = MemberImportFields::FIELDS[$field];
            $label = $spec['label'];

            switch ($spec['kind']) {
                case 'email':
                    $value = mb_strtolower($value);
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = "{$label} '{$value}' is not a valid email address";

                        continue 2;
                    }
                    break;
                case 'date':
                    $date = MemberImportValues::date($value, $dateOrder);
                    if ($date === null) {
                        $errors[] = "{$label} '{$value}' is not a date we can read";

                        continue 2;
                    }
                    $value = $date;
                    break;
                case 'status':
                    $status = MemberImportValues::status($value);
                    if ($status === null) {
                        $errors[] = "Status '{$value}' is not recognised";

                        continue 2;
                    }
                    $value = $status->value;
                    break;
                case 'rank':
                    $rank = MemberImportValues::masonicRank($value);
                    if ($rank === null) {
                        $warnings[] = "Masonic rank '{$value}' is not recognised (use Bro, WBro, VWBro, RWBro or MWBro), so it was left out";

                        continue 2;
                    }
                    $value = $rank;
                    break;
                case 'grand_rank':
                case 'provincial_rank':
                    // Written as an abbreviation or full title, it is matched to the lodge's list. Something the list
                    // does not have is kept as written (lodges edit their own lists), with a warning.
                    $kind = $spec['kind'] === 'grand_rank' ? MasonicRanks::GRAND : MasonicRanks::PROVINCIAL;
                    $listed = $club ? MasonicRanks::normalise($club, $kind, $value) : null;
                    if ($listed !== null) {
                        $value = $listed;
                    } elseif ($club) {
                        $warnings[] = "{$label} '{$value}' is not in your rank list";
                    }
                    break;
                case 'office':
                    $office = MemberImportValues::office($value);
                    if ($office === null) {
                        $warnings[] = "Office '{$value}' is not recognised, so it was left out";

                        continue 2;
                    }
                    $value = $office->value;
                    break;
            }

            if (mb_strlen($value) > $spec['max']) {
                $errors[] = "{$label} is longer than {$spec['max']} characters";

                continue;
            }

            $data[$field] = $value;
        }

        if (($data['email'] ?? '') === MemberImportFields::EXAMPLE_EMAIL) {
            $errors[] = 'This is the example row from the template. Delete it before importing';
        }

        foreach (['first_name' => 'first name', 'last_name' => 'last name'] as $required => $label) {
            if (($data[$required] ?? '') === '') {
                $errors[] = "Missing {$label}";
            }
        }

        return [$data, $errors, $warnings];
    }

    /**
     * The roster's emails, Hermes IDs and names, so each row is compared without a query per row.
     *
     * @return array{email: array<string, array<int, int>>, grand_lodge_number: array<string, array<int, int>>, name: array<string, array<int, int>>}
     */
    private function indexRoster(int $clubId): array
    {
        $indexes = ['email' => [], 'grand_lodge_number' => [], 'name' => []];

        Member::where('club_id', $clubId)->select(['id', 'email', 'grand_lodge_number', 'first_name', 'last_name'])->each(function (Member $member) use (&$indexes) {
            if (filled($member->email)) {
                $indexes['email'][mb_strtolower(trim($member->email))][] = $member->id;
            }

            if (filled($member->grand_lodge_number)) {
                $indexes['grand_lodge_number'][mb_strtolower(trim($member->grand_lodge_number))][] = $member->id;
            }

            $indexes['name'][MemberImportValues::nameKey($member->first_name, $member->last_name)][] = $member->id;
        });

        return $indexes;
    }

    /**
     * @param  array<string, string>  $data
     * @param  array{email: array<string, array<int, int>>, grand_lodge_number: array<string, array<int, int>>, name: array<string, array<int, int>>}  $indexes
     * @return array{0: string, 1: ?string, 2: ?int} status, match type, matched member id
     */
    private function matchExisting(array $data, array $indexes): array
    {
        $byEmail = isset($data['email']) ? ($indexes['email'][$data['email']] ?? []) : [];
        $byId = isset($data['grand_lodge_number']) ? ($indexes['grand_lodge_number'][mb_strtolower($data['grand_lodge_number'])] ?? []) : [];

        if (count($byEmail) > 1) {
            return [MemberImportRow::POSSIBLE, 'email_ambiguous', null];
        }

        if (count($byId) > 1) {
            return [MemberImportRow::POSSIBLE, 'id_ambiguous', null];
        }

        if (count($byEmail) === 1 && count($byId) === 1 && $byEmail[0] !== $byId[0]) {
            return [MemberImportRow::POSSIBLE, 'conflict', null];
        }

        if (count($byEmail) === 1) {
            return [MemberImportRow::DUPLICATE, 'email', $byEmail[0]];
        }

        if (count($byId) === 1) {
            return [MemberImportRow::DUPLICATE, 'grand_lodge_number', $byId[0]];
        }

        $byName = $indexes['name'][MemberImportValues::nameKey($data['first_name'] ?? '', $data['last_name'] ?? '')] ?? [];

        if ($byName !== []) {
            return [MemberImportRow::POSSIBLE, count($byName) === 1 ? 'name' : 'name_ambiguous', count($byName) === 1 ? $byName[0] : null];
        }

        return [MemberImportRow::NEW, null, null];
    }

    /**
     * Whether an earlier row in this file is the same person: firmly (same email or ID) or weakly (same name).
     * The first row of a group is remembered, and later rows point at it.
     *
     * @param  array<string, string>  $data
     * @param  array{email: array<string, int>, grand_lodge_number: array<string, int>, name: array<string, int>}  $seen
     * @return array{0: ?string, 1: ?int}
     */
    private function matchEarlierRow(array $data, array &$seen, int $rowNumber): array
    {
        $keys = [
            'email' => $data['email'] ?? null,
            'grand_lodge_number' => isset($data['grand_lodge_number']) ? mb_strtolower($data['grand_lodge_number']) : null,
            'name' => MemberImportValues::nameKey($data['first_name'] ?? '', $data['last_name'] ?? ''),
        ];

        $found = [null, null];

        foreach (['email', 'grand_lodge_number', 'name'] as $type) {
            $key = $keys[$type];

            if ($key === null || $key === '' || $key === '|') {
                continue;
            }

            if (isset($seen[$type][$key])) {
                $found = $found[0] === 'firm' ? $found : [$type === 'name' ? 'weak' : 'firm', $seen[$type][$key]];
            } else {
                $seen[$type][$key] = $rowNumber;
            }
        }

        return $found;
    }
}
