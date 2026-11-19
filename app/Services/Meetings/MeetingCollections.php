<?php

namespace App\Services\Meetings;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\Member;
use App\Exceptions\LedgerRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\MeetingFinancialReturn;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AccountingService;
use App\Support\ClubAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The alms plate and the raffle at each meeting, entered on a phone by whoever counted them. One entry of each kind per
 * meeting (rows in club_acc_charity_collections, the table the Charity page, Relief Chest and Treasurer's report read).
 *
 * Saving puts the cash and cheques in the books straight away, as money awaiting banking (1150) against Alms (4400) or
 * Raffle (4300); the paying-in line on the bank statement then moves it to the bank (BankReconciliationMatcherService,
 * `meeting_collections`) and locks the entries. Card takings are counted in the totals but reach the books through the
 * card provider's own payouts, so they are never counted twice.
 */
class MeetingCollections
{
    /** The kinds entered here; envelopes and charity boxes stay on the Charity page. */
    public const TYPES = [CollectionType::AlmsPlate, CollectionType::Raffle];

    /** Offices that count the money on the night, whatever their login role. */
    public const OFFICES = [LodgeOffice::CharitySteward, LodgeOffice::Almoner, LodgeOffice::Treasurer, LodgeOffice::Secretary, LodgeOffice::AssistantSecretary];

    /** The holding account for cash and cheques between the meeting and the bank. */
    public const AWAITING_BANKING = '1150';

    public function __construct(private AccountingService $accounting) {}

    /**
     * Owners and admins, the Treasurer and Secretary by role, or anyone holding one of OFFICES in the lodge's roster.
     */
    public static function canRecord(?User $user, Club $club): bool
    {
        if (ClubAccess::can($user, $club, 'manage_billing') || ClubAccess::can($user, $club, 'manage_meetings')) {
            return true;
        }

        return Member::userHoldsOffice($club, $user, self::OFFICES);
    }

    /**
     * This meeting's alms and raffle, keyed by type value (null where nothing is entered yet).
     *
     * @return array<string, ?CharityCollection>
     */
    public function forMeeting(Meeting $meeting): array
    {
        $rows = CharityCollection::where('club_id', $meeting->club_id)->where('meeting_id', $meeting->id)
            ->whereIn('collection_type', array_map(fn (CollectionType $type) => $type->value, self::TYPES))
            ->whereNull('donor_member_id')->whereNull('donor_name')
            ->orderBy('id')->get()->keyBy(fn (CharityCollection $row) => $row->collection_type->value);

        return collect(self::TYPES)->mapWithKeys(fn (CollectionType $type) => [$type->value => $rows->get($type->value)])->all();
    }

    /**
     * Whether this meeting's financial return takes its alms and raffle from here (so the return must not post them).
     */
    public function recordedFor(Meeting $meeting): bool
    {
        return collect($this->forMeeting($meeting))->filter()->isNotEmpty();
    }

    /**
     * Save one kind for a meeting and keep its journal entry in step.
     *
     * @param  array{cash_amount?: mixed, cheque_amount?: mixed, card_amount?: mixed, counted_by_member_id?: ?int, witnessed_by_member_id?: ?int, notes?: ?string}  $data
     */
    public function save(Club $club, Meeting $meeting, CollectionType $type, array $data, User $by): CharityCollection
    {
        abort_unless((int) $meeting->club_id === (int) $club->id && in_array($type, self::TYPES, true), 404);

        $counted = $data['counted_by_member_id'] ?? null;
        $witnessed = $data['witnessed_by_member_id'] ?? null;
        if ($counted && $witnessed && (int) $counted === (int) $witnessed) {
            throw ValidationException::withMessages(['witnessed_by_member_id' => 'The witness must be someone other than the person who counted.']);
        }
        $known = Member::where('club_id', $club->id)->whereIn('id', array_filter([$counted, $witnessed]))->count();
        if ($known !== count(array_filter([$counted, $witnessed]))) {
            throw ValidationException::withMessages(['counted_by_member_id' => 'Choose people from this lodge.']);
        }

        $postedReturn = MeetingFinancialReturn::where('club_id', $club->id)->where('meeting_id', $meeting->id)->where('is_draft', false)->first();
        if ($postedReturn && ((float) $postedReturn->raffle_amount > 0 || (float) $postedReturn->alms_amount > 0) && ! $this->recordedFor($meeting)) {
            throw ValidationException::withMessages(['cash_amount' => 'This meeting\'s financial return already put the alms and raffle in the books. Ask the Treasurer to correct it there.']);
        }

        return DB::transaction(function () use ($club, $meeting, $type, $data, $by, $counted, $witnessed) {
            $row = $this->forMeeting($meeting)[$type->value];
            $row = $row ? CharityCollection::whereKey($row->id)->lockForUpdate()->first() : null;

            if ($row?->bank_transaction_id) {
                throw ValidationException::withMessages(['cash_amount' => 'This money has been paid into the bank, so it can no longer be changed here. Ask the Treasurer.']);
            }

            $row ??= new CharityCollection(['club_id' => $club->id, 'meeting_id' => $meeting->id, 'collection_type' => $type]);
            $row->fill([
                'cash_amount' => round((float) ($data['cash_amount'] ?? 0), 2),
                'cheque_amount' => round((float) ($data['cheque_amount'] ?? 0), 2),
                'card_amount' => round((float) ($data['card_amount'] ?? 0), 2),
                'counted_by_member_id' => $counted,
                'witnessed_by_member_id' => $witnessed,
                'notes' => $data['notes'] ?? null,
                'entered_by_user_id' => $by->id,
            ])->save();

            $this->syncJournal($club, $meeting, $row);

            return $row;
        });
    }

    /**
     * Cash and cheques: debit money awaiting banking, credit Alms or Raffle, dated the meeting. An edit rewrites the same
     * entry (the ledger log keeps the before and after); nothing left in cash or cheques removes it.
     */
    private function syncJournal(Club $club, Meeting $meeting, CharityCollection $row): void
    {
        $amount = round((float) $row->cash_amount + (float) $row->cheque_amount, 2);
        $entry = $row->journal_entry_id ? JournalEntry::where('club_id', $club->id)->find($row->journal_entry_id) : null;
        $label = $row->collection_type === CollectionType::Raffle ? 'Raffle' : 'Alms';

        if ($amount <= 0) {
            if ($entry) {
                $this->accounting->log($club, 'journal_entry', $entry->id, 'deleted', "Meeting {$label} removed: {$meeting->title}");
                $entry->items()->delete();
                $entry->delete();
                $row->forceFill(['journal_entry_id' => null])->save();
            }

            return;
        }

        $income = $this->accounting->getAccount($club, $row->collection_type === CollectionType::Raffle ? '4300' : '4400');
        $data = [
            'description' => "Meeting {$label}: {$meeting->title}",
            'entry_date' => $meeting->meeting_date->toDateString(),
            'source_type' => 'CharityCollection',
            'source_id' => $row->id,
            'items' => [
                ['account_id' => self::awaitingBanking($club)->id, 'debit' => $amount, 'credit' => 0, 'memo' => "{$label} cash and cheques to bank"],
                ['account_id' => $income->id, 'debit' => 0, 'credit' => $amount, 'memo' => "{$label} at {$meeting->title}"],
            ],
        ];

        if ($entry) {
            $this->accounting->updateJournalEntry($club, $entry->id, $data);
        } else {
            $entry = $this->accounting->postJournalEntry($club, $data);
            $row->forceFill(['journal_entry_id' => $entry->id])->save();
        }
    }

    public static function awaitingBanking(Club $club): Account
    {
        return Account::firstOrCreate(
            ['club_id' => $club->id, 'code' => self::AWAITING_BANKING],
            ['name' => 'Collections awaiting banking', 'type' => 'asset', 'currency' => $club->currencyCode(), 'is_active' => true],
        );
    }

    /**
     * Cash and cheques entered here and not yet paid in, by meeting: what a paying-in line on the statement can match.
     *
     * @return Collection<int, array{meeting: Meeting, amount: float, ids: list<int>}>
     */
    public function unbanked(int $clubId): Collection
    {
        return CharityCollection::where('club_id', $clubId)->whereNotNull('journal_entry_id')->whereNull('bank_transaction_id')
            ->whereNotNull('meeting_id')->get()
            ->groupBy('meeting_id')
            ->map(function (Collection $rows, $meetingId) use ($clubId) {
                $meeting = Meeting::where('club_id', $clubId)->find($meetingId);

                return $meeting ? [
                    'meeting' => $meeting,
                    'amount' => round($rows->sum(fn (CharityCollection $row) => (float) $row->cash_amount + (float) $row->cheque_amount), 2),
                    'ids' => array_values($rows->map(fn (CharityCollection $row): int => $row->id)->all()),
                ] : null;
            })
            ->filter()
            ->sortBy(fn (array $group) => $group['meeting']->meeting_date)
            ->values();
    }

    /**
     * A paying-in line matched: the collections it covers are marked banked (and so locked). The bank posting itself is
     * made by the reconciliation, against AWAITING_BANKING.
     *
     * @param  list<int>  $meetingIds
     */
    public function markBanked(int $clubId, array $meetingIds, int $bankTransactionId, float $amount): void
    {
        $groups = $this->unbanked($clubId)->filter(fn (array $group) => in_array($group['meeting']->id, $meetingIds, true));

        if ($groups->isEmpty() || abs($groups->sum('amount') - $amount) > 0.009) {
            throw new LedgerRuleException('This bank line does not match the alms and raffle still to be paid in.');
        }

        CharityCollection::whereIn('id', $groups->pluck('ids')->flatten())->update(['bank_transaction_id' => $bankTransactionId]);
    }
}
