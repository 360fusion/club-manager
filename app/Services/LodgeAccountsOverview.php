<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Budget;
use App\Models\Club;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Meetings\MeetingCollections;
use App\Support\ClubAccess;
use App\Support\Currencies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The lodge's accounts at a glance, for a phone: what is in the bank, this financial year's money in and out against
 * the budget, what needs doing, and charity collected. Read-only; every figure comes from the books or the records
 * behind them, in one query per kind.
 */
class LodgeAccountsOverview
{
    /** Owners, admins, the Treasurer and the examiner by role, or whoever holds the Treasurer office. */
    public static function canView(?User $user, Club $club): bool
    {
        return ClubAccess::can($user, $club, 'view_accounting') || Member::userHoldsOffice($club, $user, [LodgeOffice::Treasurer]);
    }

    /** Importing a statement changes the books, so it needs the billing capability or the Treasurer office. */
    public static function canImport(?User $user, Club $club): bool
    {
        return ClubAccess::can($user, $club, 'manage_billing') || Member::userHoldsOffice($club, $user, [LodgeOffice::Treasurer]);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Club $club): array
    {
        $year = $club->financialYearFor(now());
        [$from, $to] = $club->financialYearRange($year);
        $money = fn (float $amount) => Currencies::format($amount, $club);

        $accounts = Account::where('club_id', $club->id)->whereIn('type', ['revenue', 'expense'])->orderBy('code')->get();
        $actuals = Account::actualsForYear($accounts, $club, $year);
        $budgets = Budget::where('club_id', $club->id)->where('financial_year', $year)->pluck('budgeted_amount', 'account_id');

        $headings = fn (string $type) => $accounts->where('type', $type)
            ->map(fn (Account $account) => [
                'name' => $account->name,
                'amount' => round((float) ($actuals[$account->id] ?? 0), 2),
                'budget' => $budgets->has($account->id) ? round((float) $budgets[$account->id], 2) : null,
            ])
            ->filter(fn (array $row) => $row['amount'] != 0 || $row['budget'] !== null)
            ->sortByDesc('amount')
            ->values();

        $income = $headings('revenue');
        $spending = $headings('expense');
        $charityCodes = ['4300', '4400'];
        $charity = $accounts->whereIn('code', $charityCodes)->sum(fn (Account $account) => (float) ($actuals[$account->id] ?? 0));

        $awaiting = Account::where('club_id', $club->id)->where('code', MeetingCollections::AWAITING_BANKING)->first();
        if ($awaiting) {
            Account::loadTotals([$awaiting]);
        }

        $dues = MemberSubscription::where('club_id', $club->id)
            ->whereIn('status', [SubscriptionStatus::Unpaid->value, SubscriptionStatus::PartiallyPaid->value, SubscriptionStatus::ArrearsWarning->value])
            ->selectRaw('count(distinct member_id) as members, coalesce(sum(amount_due - amount_paid), 0) as owed')
            ->first();

        $bookings = EventRegistration::whereHas('event', fn ($query) => $query->where('club_id', $club->id))
            ->where('status', 'attending')->whereIn('payment_status', ['unpaid', 'part_paid'])
            ->selectRaw('count(*) as bookings, coalesce(sum(total - amount_paid), 0) as owed')
            ->first();

        $bills = Bill::where('club_id', $club->id)
            ->whereIn('status', [DocumentStatus::PendingApproval->value, DocumentStatus::Unpaid->value])
            ->select('status', DB::raw('count(*) as bills'), DB::raw('coalesce(sum(amount), 0) as total'))
            ->groupBy('status')->get()->keyBy('status');
        $billLine = fn (DocumentStatus $status) => ['count' => (int) ($bills[$status->value]->bills ?? 0), 'total' => $money((float) ($bills[$status->value]->total ?? 0))];

        return [
            'year' => ['label' => $from->format('M Y').' to '.$to->format('M Y'), 'number' => $year],
            'bank' => $this->bankAccounts($club, $money),
            'income' => ['total' => $money($income->sum('amount')), 'rows' => $income->map(fn (array $row) => [...$row, 'amount' => $money($row['amount']), 'budget' => $row['budget'] === null ? null : $money($row['budget']), 'share' => $row['budget'] ? min(100, (int) round($row['amount'] / $row['budget'] * 100)) : null])->all()],
            'spending' => ['total' => $money($spending->sum('amount')), 'rows' => $spending->map(fn (array $row) => [...$row, 'amount' => $money($row['amount']), 'budget' => $row['budget'] === null ? null : $money($row['budget']), 'share' => $row['budget'] ? min(100, (int) round($row['amount'] / $row['budget'] * 100)) : null])->all()],
            'net' => $money($income->sum('amount') - $spending->sum('amount')),
            'todo' => [
                'unreconciled' => BankTransaction::where('club_id', $club->id)->where('status', BankTransactionStatus::Unmatched->value)->count(),
                'bills_to_approve' => $billLine(DocumentStatus::PendingApproval),
                'bills_to_pay' => $billLine(DocumentStatus::Unpaid),
                'awaiting_banking' => $awaiting ? $money(max(0, $awaiting->balance)) : null,
                'dues' => ['members' => (int) ($dues->members ?? 0), 'owed' => $money((float) ($dues->owed ?? 0))],
                'bookings' => ['count' => (int) ($bookings->bookings ?? 0), 'owed' => $money((float) ($bookings->owed ?? 0))],
            ],
            'charity' => $money($charity),
        ];
    }

    /**
     * Each bank account's balance in the books, and what its last imported statement said and when.
     *
     * @return list<array{name: string, balance: string, statement_date: ?string, statement_balance: ?string, days_old: ?int}>
     */
    private function bankAccounts(Club $club, callable $money): array
    {
        $bankAccounts = BankAccount::where('club_id', $club->id)->where('is_active', true)->whereNotNull('account_id')->get();
        $ledger = Account::where('club_id', $club->id)->whereIn('id', $bankAccounts->pluck('account_id'))->get()->keyBy('id');
        Account::loadTotals($ledger);

        // Lines imported before they were tied to an account belong to it when the lodge has only one.
        $only = $bankAccounts->count() === 1 ? $bankAccounts->first()->id : null;
        $lines = fn (int $bankId) => BankTransaction::where('club_id', $club->id)
            ->where(fn ($query) => $query->where('bank_account_id', $bankId)->when($only === $bankId, fn ($query) => $query->orWhereNull('bank_account_id')));

        return $bankAccounts->map(function (BankAccount $bank) use ($ledger, $lines, $money) {
            $lastDate = $lines($bank->id)->max('transaction_date');
            $statementBalance = $lastDate ? $lines($bank->id)->whereDate('transaction_date', Carbon::parse($lastDate)->toDateString())->whereNotNull('balance_after')->orderByDesc('id')->value('balance_after') : null;

            return [
                'name' => $bank->account_name ?: ($bank->bank_name ?: 'Bank account'),
                'balance' => $money((float) ($ledger->get($bank->account_id)->balance ?? 0)),
                'statement_date' => $lastDate ? Carbon::parse($lastDate)->format('j M Y') : null,
                'statement_balance' => $statementBalance === null ? null : $money((float) $statementBalance),
                'days_old' => $lastDate ? (int) Carbon::parse($lastDate)->diffInDays(now()) : null,
            ];
        })->values()->all();
    }
}
