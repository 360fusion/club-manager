<?php

namespace App\Http\Controllers;

use App\Models\ClubPlatformAccount;
use App\Models\PlatformPayment;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The platform's view of card payments taken through lodges' connected Stripe accounts: who is connected, each
 * lodge's commission rate, and the commission earned.
 */
class PlatformPaymentsAdminController extends Controller
{
    public function index(): Response
    {
        $totals = PlatformPayment::query()
            ->select('club_id', 'currency', DB::raw('COUNT(*) as payments'), DB::raw('SUM(gross) as gross'), DB::raw('SUM(commission) as commission'), DB::raw('SUM(refunded) as refunded'))
            ->groupBy('club_id', 'currency')
            ->get()
            ->groupBy('club_id');

        $lodges = ClubPlatformAccount::with('club:id,name,slug')->orderByDesc('id')->get()->map(function (ClubPlatformAccount $account) use ($totals) {
            $rows = $totals->get($account->club_id, collect());

            return [
                'id' => $account->id,
                'club' => $account->club?->name,
                'type' => $account->type,
                'connected' => $account->disconnected_at === null,
                'ready' => $account->canTakePayments(),
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'terms_accepted' => $account->terms_accepted_at !== null,
                'commission_percent' => $account->commission_percent,
                'commission_fixed' => $account->commission_fixed,
                'totals' => $rows->map(fn ($row) => [
                    'currency' => $row->currency,
                    'payments' => (int) $row->payments,
                    'gross' => number_format((float) $row->gross, 2, '.', ''),
                    'commission' => number_format((float) $row->commission, 2, '.', ''),
                    'refunded' => number_format((float) $row->refunded, 2, '.', ''),
                ])->values(),
            ];
        });

        $byMonth = PlatformPayment::where('created_at', '>=', now()->subMonths(12)->startOfMonth())->get(['currency', 'commission', 'created_at'])
            ->groupBy(fn (PlatformPayment $p) => $p->created_at->format('Y-m').'|'.$p->currency)
            ->map(function ($rows, string $key) {
                [$month, $currency] = explode('|', $key);

                return ['month' => $month, 'currency' => $currency, 'commission' => number_format((float) $rows->sum('commission'), 2, '.', ''), 'payments' => $rows->count()];
            })
            ->sortByDesc('month')
            ->values();

        return Inertia::render('SuperAdmin/PlatformPayments/Index', [
            'lodges' => $lodges,
            'byMonth' => $byMonth,
            'enabled' => (bool) config('platform_payments.enabled'),
            'defaults' => ['percent' => config('platform_payments.commission_percent'), 'fixed' => config('platform_payments.commission_fixed')],
        ]);
    }

    /**
     * Give one lodge its own commission rate, or clear it to use the platform default.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $account = ClubPlatformAccount::findOrFail($id);

        $validated = $request->validate([
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'commission_fixed' => 'nullable|numeric|min:0|max:1000',
        ]);

        $account->update(['commission_percent' => $validated['commission_percent'] ?? null, 'commission_fixed' => $validated['commission_fixed'] ?? null]);

        return redirect()->back()->with('success', 'Commission updated.');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo Csv::line(['Date', 'Lodge', 'Booking', 'Currency', 'Gross', 'Commission', 'Refunded']);

            PlatformPayment::with('club:id,name')->orderBy('id')->chunk(500, function ($payments) {
                foreach ($payments as $payment) {
                    echo Csv::line([$payment->created_at?->format('Y-m-d H:i'), $payment->club?->name, $payment->registration_id, $payment->currency, $payment->gross, $payment->commission, $payment->refunded]);
                }
            });
        }, 'platform-payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
