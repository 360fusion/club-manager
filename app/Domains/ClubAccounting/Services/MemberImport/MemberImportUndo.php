<?php

namespace App\Domains\ClubAccounting\Services\MemberImport;

use App\Domains\ClubAccounting\Enums\MemberAccountStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberImport;
use App\Domains\ClubAccounting\Models\MemberImportRow;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rolls an import back: removes the members it created and puts back the fields it changed. Anyone who has moved
 * on since (an account, subscriptions, offices, or a field edited again) is left alone and reported.
 */
class MemberImportUndo
{
    /**
     * @return array{removed: int, restored: int, blocked: array<int, string>}
     *
     * @throws RuntimeException when the import is not one that was completed
     */
    public function undo(MemberImport $import): array
    {
        return DB::transaction(function () use ($import) {
            $locked = MemberImport::where('club_id', $import->club_id)->whereKey($import->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== MemberImport::IMPORTED) {
                throw new RuntimeException('Only a completed import can be undone.');
            }

            $club = $locked->club;
            $summary = ['removed' => 0, 'restored' => 0, 'blocked' => []];

            $locked->rows()->whereIn('outcome', ['created', 'updated'])->orderBy('row_number')->each(function (MemberImportRow $row) use ($locked, $club, &$summary) {
                if ($row->outcome === 'created') {
                    $this->removeCreated($row, $locked, $club, $summary);
                } else {
                    $this->restoreUpdated($row, $locked, $summary);
                }
            });

            $locked->update([
                'status' => MemberImport::UNDONE,
                'undone_at' => now(),
                'result' => ($locked->result ?? []) + ['undo' => $summary],
            ]);

            return $summary;
        });
    }

    /**
     * @param  array{removed: int, restored: int, blocked: array<int, string>}  $summary
     */
    private function removeCreated(MemberImportRow $row, MemberImport $import, $club, array &$summary): void
    {
        $member = $row->created_member_id ? Member::where('club_id', $import->club_id)->find($row->created_member_id) : null;

        if (! $member) {
            return;
        }

        $reason = match (true) {
            $member->accountStatus($club) === MemberAccountStatus::HasAccount => 'now has an online account',
            $member->subscriptions()->exists() => 'has subscriptions',
            $member->annualAssignments()->exists() => 'has been given a lodge office',
            $member->customer_account_id !== null => 'is linked to a ledger account',
            default => null,
        };

        if ($reason) {
            $summary['blocked'][] = "Row {$row->row_number} ({$member->full_name}) was kept because the member {$reason}";

            return;
        }

        $member->delete();
        $summary['removed']++;
    }

    /**
     * @param  array{removed: int, restored: int, blocked: array<int, string>}  $summary
     */
    private function restoreUpdated(MemberImportRow $row, MemberImport $import, array &$summary): void
    {
        $member = $row->match_member_id ? Member::where('club_id', $import->club_id)->find($row->match_member_id) : null;

        if (! $member) {
            return;
        }

        $restore = [];
        $kept = [];

        foreach ($row->applied_values ?? [] as $field => $applied) {
            if (MemberImportFields::valueOf($member, $field) === $applied) {
                $restore[$field] = ($row->previous_values ?? [])[$field] ?? null;
            } else {
                $kept[] = MemberImportFields::label($field);
            }
        }

        if ($restore !== []) {
            $member->update($restore);
            $summary['restored']++;
        }

        if ($kept !== []) {
            $summary['blocked'][] = "Row {$row->row_number} ({$member->full_name}): ".implode(', ', $kept).' changed again since, so they were left as they are';
        }
    }
}
