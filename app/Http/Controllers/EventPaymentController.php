<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventPayment\BulkPaidRequest;
use App\Http\Requests\EventPayment\MarkPaidRequest;
use App\Http\Requests\EventPayment\MarkUnpaidRequest;
use App\Http\Requests\EventPayment\RefundRequest;
use App\Http\Requests\EventPayment\WaiveRequest;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventPayload;
use App\Services\Events\EventPaymentService;
use App\Support\ClubAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Organisers confirming payments on bookings. Every action is logged with who did it and why.
 */
class EventPaymentController extends Controller
{
    public function history(Request $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): JsonResponse
    {
        [$club, $registration] = $this->find($clubSlug, $id, $registrationId);

        return response()->json([
            'registration' => ['id' => $registration->id, 'name' => $registration->contact_name],
            'payment' => EventPayload::payment($registration),
            'history' => $payments->history($registration),
            'can_refund' => ClubAccess::can($request->user(), $club, 'manage_billing'),
        ]);
    }

    public function markPaid(MarkPaidRequest $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);

        $validated = $request->validated();

        $payments->markPaid($registration, $request->user(), isset($validated['amount']) ? (float) $validated['amount'] : null, $validated['method'] ?? null, isset($validated['received_on']) ? Carbon::parse($validated['received_on']) : null, $validated['comment'] ?? null);

        return redirect()->back()->with('success', $registration->contact_name.' marked as paid.');
    }

    public function bulkPaid(BulkPaidRequest $request, string $clubSlug, int $id, EventPaymentService $payments): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validated();

        $count = 0;

        foreach (EventRegistration::where('event_id', $event->id)->whereIn('id', $validated['registration_ids'])->get() as $registration) {
            if ($registration->balanceDue() > 0 && ! in_array($registration->payment_status, ['waived', 'refunded'], true)) {
                $payments->markPaid($registration, $request->user(), null, $validated['method'] ?? null, null, $validated['comment'] ?? null);
                $count++;
            }
        }

        return redirect()->back()->with('success', $count.' '.($count === 1 ? 'booking' : 'bookings').' marked as paid.');
    }

    public function markUnpaid(MarkUnpaidRequest $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);
        $validated = $request->validated();

        $payments->markUnpaid($registration, $request->user(), $validated['comment']);

        return redirect()->back()->with('success', 'Payment taken back.');
    }

    public function waive(WaiveRequest $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);
        $validated = $request->validated();

        $payments->waive($registration, $request->user(), $validated['comment']);

        return redirect()->back()->with('success', 'Charge waived.');
    }

    public function refund(RefundRequest $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);

        $validated = $request->validated();

        $payments->refund($registration, $request->user(), $validated['comment'], isset($validated['amount']) ? (float) $validated['amount'] : null);

        return redirect()->back()->with('success', 'Refund recorded.');
    }

    /**
     * @return array{0: Club, 1: EventRegistration}
     */
    private function find(string $clubSlug, int $id, int $registrationId): array
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        return [$club, EventRegistration::where('event_id', $event->id)->findOrFail($registrationId)];
    }
}
