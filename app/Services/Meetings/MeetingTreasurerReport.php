<?php

namespace App\Services\Meetings;

use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Meeting;
use App\Services\AccountingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one-page Treasurer's report read out at a meeting: money held, what came in and went out since the last meeting,
 * dues still owed, charity collected and bills waiting for approval. "Since the last meeting" runs from the day of the
 * previous published meeting up to the day before this one (or today, if that is earlier), so the alms taken at the last
 * meeting are reported at this one and nothing is counted twice.
 */
class MeetingTreasurerReport
{
    public function __construct(private AccountingService $accounting) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Club $club, Meeting $meeting): array
    {
        $previous = Meeting::where('club_id', $club->id)->where('status', 'published')
            ->where('meeting_date', '<', $meeting->meeting_date->toDateString())
            ->orderByDesc('meeting_date')->first();

        $from = $previous?->meeting_date->copy()->startOfDay() ?? $club->financialYearRange($club->financialYearFor($meeting->meeting_date))[0];
        $to = Carbon::parse(min($meeting->meeting_date->copy()->subDay()->toDateString(), now($club->timezone())->toDateString()));

        $moneyAccounts = $this->accounting->moneyAccounts($club);
        $balances = $this->sums($moneyAccounts->pluck('id'), null, $to);
        $movements = Account::where('club_id', $club->id)->whereIn('type', ['revenue', 'expense'])->orderBy('code')->get();
        $inPeriod = $this->sums($movements->pluck('id'), $from, $to);

        $lines = fn (string $type) => $movements->where('type', $type)
            ->map(fn (Account $account) => ['name' => $account->name, 'amount' => round($type === 'revenue' ? -($inPeriod[$account->id] ?? 0) : ($inPeriod[$account->id] ?? 0), 2)])
            ->filter(fn (array $line) => $line['amount'] != 0)->values();

        $owing = MemberSubscription::where('club_id', $club->id)->unpaid()->get();
        $collections = CharityCollection::where('club_id', $club->id)
            ->where(fn ($query) => $query
                ->whereIn('meeting_id', Meeting::where('club_id', $club->id)->whereBetween('meeting_date', [$from->toDateString(), $to->toDateString()])->select('id'))
                ->orWhere(fn ($loose) => $loose->whereNull('meeting_id')->whereBetween('created_at', [$from, $to->copy()->endOfDay()])))
            ->get();
        $bills = Bill::where('club_id', $club->id)->where('status', DocumentStatus::PendingApproval->value)->orderBy('due_date')->get();

        $money = $moneyAccounts->map(fn (Account $account) => ['name' => $account->name, 'balance' => round($balances[$account->id] ?? 0, 2)])->values();

        return [
            'club' => $club,
            'meeting' => $meeting,
            'previous' => $previous,
            'from' => $from,
            'to' => $to,
            'money' => $money->all(),
            'money_total' => round($money->sum('balance'), 2),
            'income' => $lines('revenue')->all(),
            'income_total' => round($lines('revenue')->sum('amount'), 2),
            'spending' => $lines('expense')->all(),
            'spending_total' => round($lines('expense')->sum('amount'), 2),
            'dues' => [
                'members' => $owing->pluck('member_id')->unique()->count(),
                'total' => round($owing->sum(fn (MemberSubscription $subscription) => $subscription->balance_due), 2),
            ],
            'charity' => $collections->groupBy(fn (CharityCollection $collection) => $collection->collection_type->label())
                ->map(fn (Collection $group, string $type) => ['type' => $type, 'amount' => round($group->sum(fn (CharityCollection $c) => (float) $c->cash_amount + (float) $c->cheque_amount + (float) $c->card_amount), 2)])
                ->values()->all(),
            'bills' => $bills->map(fn (Bill $bill) => ['vendor' => $bill->vendor_name, 'amount' => (float) $bill->amount, 'due' => $bill->due_date->format('j M Y')])->all(),
        ];
    }

    /**
     * Debits less credits per account for entries dated in [$from, $to] (from the beginning when $from is null).
     *
     * @param  Collection<int, int>  $accountIds
     * @return array<int, float>
     */
    private function sums(Collection $accountIds, ?CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('accounting_journal_items as items')
            ->join('accounting_journal_entries as entries', 'entries.id', '=', 'items.journal_entry_id')
            ->where('entries.status', '!=', DocumentStatus::Void->value)
            ->whereIn('items.account_id', $accountIds)
            ->when($from, fn ($query) => $query->where('entries.entry_date', '>=', $from->toDateString()))
            ->where('entries.entry_date', '<=', $to->toDateString())
            ->groupBy('items.account_id')
            ->selectRaw('items.account_id, sum(items.debit) - sum(items.credit) as net')
            ->pluck('net', 'account_id')
            ->map(fn ($net) => (float) $net)
            ->all();
    }
}
