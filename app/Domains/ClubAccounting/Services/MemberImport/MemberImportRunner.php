<?php

namespace App\Domains\ClubAccounting\Services\MemberImport;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportRow;
use App\Domains\ClubAccounting\Services\MemberInvitationService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Writes a reviewed import to the roster, one row at a time according to the action chosen for it, and records
 * what was done to each row so the import can be reported on and undone.
 */
class MemberImportRunner
{
    public function __construct(private MemberInvitationService $invitations) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: int, invited: int, invite_skipped: array<string, int>}
     *
     * @throws RuntimeException when the import was already run or cancelled
     */
    public function run(MemberImport $import, ?User $actor = null, bool $invite = false): array
    {
        $createdIds = [];

        $result = DB::transaction(function () use ($import, &$createdIds) {
            $locked = MemberImport::where('club_id', $import->club_id)->whereKey($import->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== MemberImport::STAGED) {
                throw new RuntimeException('This import has already been run or was cancelled.');
            }

            $skipRows = $this->rowsSkippedByFileDuplicatePolicy($locked);
            $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

            $locked->rows()->orderBy('row_number')->each(function (MemberImportRow $row) use ($locked, $skipRows, &$counts, &$createdIds) {
                [$outcome, $note] = $this->apply($locked, $row, $skipRows, $createdIds);

                $row->forceFill(['outcome' => $outcome, 'outcome_note' => $note])->save();

                match ($outcome) {
                    'created' => $counts['created']++,
                    'updated' => $counts['updated']++,
                    'failed' => $counts['errors']++,
                    default => $counts['skipped']++,
                };
            });

            $locked->update(['status' => MemberImport::IMPORTED, 'imported_at' => now(), 'result' => $counts]);

            if ($locked->stored_path) {
                Storage::disk('local')->delete($locked->stored_path);
                $locked->update(['stored_path' => null]);
            }

            return $counts;
        });

        $result += ['invited' => 0, 'invite_skipped' => []];

        if ($invite && $createdIds !== []) {
            $invited = $this->invitations->inviteMany($import->club, $createdIds, $actor);
            $result['invited'] = $invited['invited'];
            $result['invite_skipped'] = $invited['skipped'];
        }

        $import->update(['result' => $result]);

        return $result;
    }

    /**
     * Row numbers a "keep first" or "keep last" choice leaves out, for rows that repeat each other in the file.
     *
     * @return array<int, string> row number => reason
     */
    private function rowsSkippedByFileDuplicatePolicy(MemberImport $import): array
    {
        $policy = $import->option('file_duplicates', 'keep_first');

        if ($policy === 'keep_both') {
            return [];
        }

        $skip = [];

        $import->rows()->whereNotNull('duplicate_of_row')->orderBy('row_number')->get(['row_number', 'duplicate_of_row'])
            ->groupBy('duplicate_of_row')
            ->each(function ($followers, $leader) use ($policy, &$skip) {
                $numbers = $followers->pluck('row_number')->all();

                if ($policy === 'keep_last') {
                    $last = max($numbers);
                    $skip[(int) $leader] = "Replaced by row {$last}, which repeats it";

                    foreach ($numbers as $number) {
                        if ($number !== $last) {
                            $skip[$number] = "Replaced by row {$last}, which repeats it";
                        }
                    }

                    return;
                }

                foreach ($numbers as $number) {
                    $skip[$number] = "Repeats row {$leader}, which was kept";
                }
            });

        return $skip;
    }

    /**
     * @param  array<int, string>  $skipRows
     * @param  array<int, int>  $createdIds
     * @return array{0: string, 1: ?string} outcome and a note
     */
    private function apply(MemberImport $import, MemberImportRow $row, array $skipRows, array &$createdIds): array
    {
        if ($row->status === MemberImportRow::ERROR) {
            return ['failed', implode('; ', $row->errors ?? [])];
        }

        if (isset($skipRows[$row->row_number])) {
            return ['skipped', $skipRows[$row->row_number]];
        }

        $data = $row->data ?? [];

        if ($row->action === 'create') {
            $member = Member::create($this->newAttributes($import->club_id, $data));
            $row->created_member_id = $member->id;
            $createdIds[] = $member->id;

            return ['created', null];
        }

        if (! in_array($row->action, ['fill', 'overwrite'], true)) {
            return ['skipped', 'Skipped by choice'];
        }

        $member = $row->match_member_id ? Member::where('club_id', $import->club_id)->find($row->match_member_id) : null;

        if (! $member) {
            return ['skipped', 'The matching member no longer exists'];
        }

        $changes = [];

        foreach ($data as $field => $incoming) {
            $existing = MemberImportFields::valueOf($member, $field);

            if ($existing === $incoming) {
                continue;
            }

            if ($row->action === 'fill' && filled($existing)) {
                continue;
            }

            $changes[$field] = $incoming;
        }

        if ($changes === []) {
            return ['skipped', 'Nothing to change'];
        }

        $row->previous_values = MemberImportFields::snapshot($member, array_keys($changes));
        $row->applied_values = $changes;
        $member->update($changes);

        return ['updated', count($changes).' '.(count($changes) === 1 ? 'field' : 'fields').' changed'];
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function newAttributes(int $clubId, array $data): array
    {
        return ['club_id' => $clubId] + $data + [
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active->value,
            'current_office' => LodgeOffice::Member->value,
        ];
    }
}
