<?php

namespace App\Models\Accounting;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'club_id',
        'code',
        'name',
        'type',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return HasMany<JournalItem, $this>
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'account_id');
    }

    /**
     * Debit and credit totals set by loadTotals() for a whole list of accounts at once; read from the database
     * on every call otherwise, so a balance is never stale after something is posted.
     *
     * @var array{debit: float, credit: float}|null
     */
    private ?array $loadedTotals = null;

    /**
     * Work out the totals of every account in one grouped query, instead of two queries for each account.
     *
     * @param  iterable<Account>  $accounts
     */
    public static function loadTotals(iterable $accounts): void
    {
        $accounts = collect($accounts);

        if ($accounts->isEmpty()) {
            return;
        }

        $rows = DB::table('accounting_journal_items as items')
            ->join('accounting_journal_entries as entries', 'entries.id', '=', 'items.journal_entry_id')
            ->where('entries.status', '!=', 'void')
            ->whereIn('items.account_id', $accounts->pluck('id'))
            ->groupBy('items.account_id')
            ->selectRaw('items.account_id, sum(items.debit) as debit, sum(items.credit) as credit')
            ->get()
            ->keyBy('account_id');

        foreach ($accounts as $account) {
            $row = $rows->get($account->id);
            $account->loadedTotals = ['debit' => (float) ($row->debit ?? 0), 'credit' => (float) ($row->credit ?? 0)];
        }
    }

    /**
     * What each account moved in a calendar year (expenses count debits less credits, everything else the other
     * way round), worked out in one grouped query for the whole list instead of two queries per account.
     *
     * @param  iterable<Account>  $accounts
     * @return array<int, float> account id => amount
     */
    public static function actualsForYear(iterable $accounts, int $year): array
    {
        $accounts = collect($accounts);

        if ($accounts->isEmpty()) {
            return [];
        }

        $rows = DB::table('accounting_journal_items as items')
            ->join('accounting_journal_entries as entries', 'entries.id', '=', 'items.journal_entry_id')
            ->where('entries.status', '!=', 'void')
            ->whereYear('entries.entry_date', $year)
            ->whereIn('items.account_id', $accounts->pluck('id'))
            ->groupBy('items.account_id')
            ->selectRaw('items.account_id, sum(items.debit) as debit, sum(items.credit) as credit')
            ->get()
            ->keyBy('account_id');

        return $accounts->mapWithKeys(function (Account $account) use ($rows) {
            $debit = (float) ($rows->get($account->id)->debit ?? 0);
            $credit = (float) ($rows->get($account->id)->credit ?? 0);

            return [$account->id => $account->type === 'expense' ? $debit - $credit : $credit - $debit];
        })->all();
    }

    /**
     * @return array{debit: float, credit: float}
     */
    public function totals(): array
    {
        if ($this->loadedTotals !== null) {
            return $this->loadedTotals;
        }

        $row = $this->journalItems()
            ->whereHas('journalEntry', fn ($q) => $q->where('status', '!=', 'void'))
            ->selectRaw('sum(debit) as debit, sum(credit) as credit')
            ->first();

        return ['debit' => (float) ($row->debit ?? 0), 'credit' => (float) ($row->credit ?? 0)];
    }

    public function getBalanceAttribute(): float
    {
        ['debit' => $debits, 'credit' => $credits] = $this->totals();

        // Assets and Expenses have normal Debit balances
        // Liabilities, Equity, and Revenue have normal Credit balances
        if (in_array($this->type, ['asset', 'expense'])) {
            return $debits - $credits;
        }

        return $credits - $debits;
    }
}
