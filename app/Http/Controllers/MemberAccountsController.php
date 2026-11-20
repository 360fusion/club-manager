<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Exceptions\LedgerRuleException;
use App\Models\Club;
use App\Services\DuesTracker;
use App\Services\LodgeAccountsOverview;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * The lodge's accounts on a phone: an overview (LodgeAccountsOverview), and importing a bank statement the Treasurer
 * exported from the banking app. An import is read and shown first; only "Import these lines" adds them.
 */
class MemberAccountsController extends Controller
{
    /** The phone's matching page shows the newest lines; the rest are on the full reconciliation screen. */
    private const MATCH_LINES = 50;

    /** How long a read statement waits for "Import these lines". */
    private const PREVIEW_MINUTES = 30;

    public function show(Request $request, string $slug, LodgeAccountsOverview $overview): Response
    {
        $club = $this->findClub($slug);
        abort_unless(LodgeAccountsOverview::canView($request->user(), $club), 403, 'Only the Treasurer and the lodge\'s officers who look after the accounts can see them.');

        return Inertia::render('Member/Accounts/Overview', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'overview' => $overview->build($club),
            'canImport' => LodgeAccountsOverview::canImport($request->user(), $club),
        ]);
    }

    /**
     * Everyone's dues for the year: paid, left, and who is behind or overdue (DuesTracker::forLodge).
     */
    public function dues(Request $request, string $slug, DuesTracker $tracker): Response
    {
        $club = $this->findClub($slug);
        abort_unless(LodgeAccountsOverview::canView($request->user(), $club), 403);

        return Inertia::render('Member/Accounts/Dues', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'dues' => $tracker->forLodge($club),
            'reminders' => ['on' => ! empty($club->settings['dues_reminders']), 'can_change' => LodgeAccountsOverview::canImport($request->user(), $club)],
        ]);
    }

    /** Switches the lodge's dues reminders (app:send-dues-reminders) on or off. */
    public function duesReminders(Request $request, string $slug): RedirectResponse
    {
        $club = $this->findClub($slug);
        abort_unless(LodgeAccountsOverview::canImport($request->user(), $club), 403);
        $on = $request->validate(['on' => ['required', 'boolean']])['on'];

        $club->forceFill(['settings' => [...($club->settings ?? []), 'dues_reminders' => (bool) $on]])->save();

        return back()->with('success', $on ? 'Dues reminders are on.' : 'Dues reminders are off.');
    }

    public function import(Request $request, string $slug): Response
    {
        $club = $this->importingClub($request, $slug);
        $token = (string) $request->query('preview', '');
        $preview = $token !== '' ? Cache::get($this->previewKey($club, $request, $token)) : null;

        return Inertia::render('Member/Accounts/ImportStatement', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'bankAccounts' => BankAccount::where('club_id', $club->id)->where('is_active', true)->get()
                ->map(fn (BankAccount $account) => ['id' => $account->id, 'name' => trim(($account->account_name ?: $account->bank_name ?: 'Bank account').($account->account_number ? ' ('.substr((string) $account->account_number, -4).')' : ''))])->values(),
            'recent' => BankImport::where('club_id', $club->id)->latest('id')->limit(5)->get()
                ->map(fn (BankImport $import) => ['filename' => $import->filename, 'lines' => $import->total_lines, 'at' => $import->created_at->format('j M Y')])->values(),
            'preview' => $preview ? $this->previewProps($club, $preview, $token) : null,
        ]);
    }

    /**
     * Read the file and keep what was read for half an hour; nothing is imported yet.
     */
    public function preview(Request $request, string $slug, BankStatementParserService $parser): RedirectResponse
    {
        $club = $this->importingClub($request, $slug);
        $validated = $request->validate([
            'statement_file' => ['required', 'file', 'mimes:csv,txt,ofx,qfx', 'max:10240'],
            'bank_account_id' => ['nullable', Rule::exists('club_acc_bank_accounts', 'id')->where('club_id', $club->id)],
        ]);

        $file = $request->file('statement_file');
        try {
            $bundle = $parser->parseFile($file->getRealPath(), $file->getClientOriginalName(), $club->id);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['statement_file' => 'This file could not be read as a bank statement. Export it from the banking app as CSV or OFX.']);
        }

        if ($bundle['lines'] === []) {
            throw ValidationException::withMessages(['statement_file' => 'No statement lines were found in this file.']);
        }

        // Dates as text: the cache only keeps plain values, not Carbon objects.
        $bundle['lines'] = array_map(fn (array $line) => ['transaction_date' => Carbon::parse($line['transaction_date'])->toDateString()] + $line, $bundle['lines']);

        $token = Str::random(32);
        Cache::put($this->previewKey($club, $request, $token), $bundle + ['bank_account_id' => $validated['bank_account_id'] ?? $parser->soleActiveBankAccountId($club->id)], now()->addMinutes(self::PREVIEW_MINUTES));

        return redirect()->route('member.accounts.import', ['slug' => $club->slug, 'preview' => $token]);
    }

    /**
     * Import what the preview showed. Lines imported since (by someone else, or a second tap) are skipped again here.
     */
    public function store(Request $request, string $slug, BankStatementParserService $parser): RedirectResponse
    {
        $club = $this->importingClub($request, $slug);
        $token = (string) $request->input('token', '');
        $key = $this->previewKey($club, $request, $token);
        $bundle = Cache::pull($key);

        if (! $bundle) {
            throw ValidationException::withMessages(['token' => 'That statement has expired. Choose the file again.']);
        }

        $existing = BankTransaction::where('club_id', $club->id)->whereIn('transaction_hash', array_column($bundle['lines'], 'transaction_hash'))->pluck('transaction_hash')->flip();
        $bundle['lines'] = array_map(fn (array $line) => ['is_duplicate' => ! empty($line['is_duplicate']) || $existing->has($line['transaction_hash'])] + $line, $bundle['lines']);

        $import = $parser->importParsedBundle($club, $bundle, null, $bundle['bank_account_id'] ?? null);

        // Straight on to matching the new lines, on the same phone screens.
        return redirect()->route('member.accounts.match', ['slug' => $club->slug])
            ->with('success', $import->total_lines === 0 ? 'Nothing new: every line was already imported.' : "{$import->total_lines} new ".($import->total_lines === 1 ? 'line' : 'lines').' imported.');
    }

    /**
     * The bank lines still to reconcile, on a phone: each with its one certain match (reference and amount), if it
     * has one, to confirm with a tap or all at once. Anything less certain is left for the full reconciliation screen.
     */
    public function match(Request $request, string $slug, BankReconciliationMatcherService $matcher): Response
    {
        $club = $this->importingClub($request, $slug);
        $money = fn (float $amount) => Currencies::format(abs($amount), $club);
        $lines = BankTransaction::where('club_id', $club->id)->where('status', BankTransactionStatus::Unmatched->value)
            ->orderByDesc('transaction_date')->orderByDesc('id')->limit(self::MATCH_LINES)->get();

        $rows = $lines->map(function (BankTransaction $line) use ($matcher, $money) {
            $match = $matcher->exactMatch($line);

            return [
                'id' => $line->id,
                'date' => $line->transaction_date->format('j M'),
                'description' => $line->raw_description,
                'reference' => $line->reference,
                'amount' => $money((float) $line->amount),
                'in' => (float) $line->amount > 0,
                'match' => $match ? ['title' => $match['target_title'], 'reason' => $match['reason'] ?? null] : null,
            ];
        })->values();

        return Inertia::render('Member/Accounts/Match', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'lines' => $rows,
            'more' => max(0, BankTransaction::where('club_id', $club->id)->where('status', BankTransactionStatus::Unmatched->value)->count() - $lines->count()),
        ]);
    }

    /** Confirms one line's certain match: the payment is recorded and the line reconciled. */
    public function confirmMatch(Request $request, string $slug, int $id, BankReconciliationMatcherService $matcher): RedirectResponse
    {
        $club = $this->importingClub($request, $slug);
        $line = BankTransaction::where('club_id', $club->id)->where('status', BankTransactionStatus::Unmatched->value)->findOrFail($id);
        $match = $matcher->exactMatch($line);
        abort_unless($match !== null, 422, 'That line has no certain match any more. Match it on the full reconciliation screen.');

        try {
            $matcher->reconcileTransaction($line, $match['match_type'], $match['target_id']);
        } catch (LedgerRuleException $refused) {
            throw ValidationException::withMessages(['match' => $refused->getMessage()]);
        }

        return back()->with('success', 'Matched: '.$match['target_title'].'.');
    }

    public function confirmAllMatches(Request $request, string $slug, BankReconciliationMatcherService $matcher): RedirectResponse
    {
        $done = $matcher->confirmExactMatches($this->importingClub($request, $slug));

        return back()->with('success', $done === 0 ? 'No line had a certain match.' : "{$done} ".($done === 1 ? 'line' : 'lines').' matched by reference and amount.');
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array<string, mixed>
     */
    private function previewProps(Club $club, array $bundle, string $token): array
    {
        $new = array_values(array_filter($bundle['lines'], fn (array $line) => empty($line['is_duplicate'])));
        $in = array_sum(array_map(fn (array $line) => max(0, (float) $line['amount']), $new));
        $out = array_sum(array_map(fn (array $line) => min(0, (float) $line['amount']), $new));
        // The parser gives each date as a Carbon; compared as Y-m-d text.
        $dates = array_map(fn (array $line) => Carbon::parse($line['transaction_date'])->toDateString(), $bundle['lines']);

        return [
            'token' => $token,
            'filename' => $bundle['filename'],
            'from' => $dates ? Carbon::parse(min($dates))->format('j M Y') : null,
            'to' => $dates ? Carbon::parse(max($dates))->format('j M Y') : null,
            'new_count' => count($new),
            'duplicate_count' => count($bundle['lines']) - count($new),
            'skipped_count' => count($bundle['skipped_rows'] ?? []),
            'money_in' => Currencies::format($in, $club),
            'money_out' => Currencies::format(abs($out), $club),
            'bank_account_id' => $bundle['bank_account_id'] ?? null,
            // The first 200 lines are plenty to check the file was read the right way round.
            'lines' => array_map(fn (array $line) => [
                'date' => Carbon::parse($line['transaction_date'])->format('j M'),
                'description' => $line['raw_description'],
                'amount' => Currencies::format((float) $line['amount'], $club),
                'in' => (float) $line['amount'] > 0,
                'duplicate' => ! empty($line['is_duplicate']),
            ], array_slice($bundle['lines'], 0, 200)),
        ];
    }

    private function importingClub(Request $request, string $slug): Club
    {
        $club = $this->findClub($slug);
        abort_unless(LodgeAccountsOverview::canImport($request->user(), $club), 403, 'Only the Treasurer can import bank statements.');

        return $club;
    }

    /** A preview belongs to the lodge and the person who read it. */
    private function previewKey(Club $club, Request $request, string $token): string
    {
        return 'statement-preview:'.$club->id.':'.$request->user()->id.':'.hash('sha256', $token);
    }
}
