<?php

namespace App\Http\Controllers;

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

    public function markPaid(Request $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:99999999.99',
            'method' => 'nullable|string|max:100',
            'received_on' => 'nullable|date',
            'comment' => 'nullable|string|max:1000',
        ]);

        $payments->markPaid($registration, $request->user(), isset($validated['amount']) ? (float) $validated['amount'] : null, $validated['method'] ?? null, isset($validated['received_on']) ? Carbon::parse($validated['received_on']) : null, $validated['comment'] ?? null);

        return redirect()->back()->with('success', $registration->contact_name.' marked as paid.');
    }

    public function bulkPaid(Request $request, string $clubSlug, int $id, EventPaymentService $payments): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'registration_ids' => 'required|array|min:1|max:200',
            'registration_ids.*' => 'integer|max:4294967295',
            'method' => 'nullable|string|max:100',
            'comment' => 'nullable|string|max:1000',
        ]);

        $count = 0;

        foreach (EventRegistration::where('event_id', $event->id)->whereIn('id', $validated['registration_ids'])->get() as $registration) {
            if ($registration->balanceDue() > 0 && ! in_array($registration->payment_status, ['waived', 'refunded'], true)) {
                $payments->markPaid($registration, $request->user(), null, $validated['method'] ?? null, null, $validated['comment'] ?? null);
                $count++;
            }
        }

        return redirect()->back()->with('success', $count.' '.($count === 1 ? 'booking' : 'bookings').' marked as paid.');
    }

    public function markUnpaid(Request $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);
        $validated = $request->validate(['comment' => 'required|string|min:3|max:1000']);

        $payments->markUnpaid($registration, $request->user(), $validated['comment']);

        return redirect()->back()->with('success', 'Payment taken back.');
    }

    public function waive(Request $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);
        $validated = $request->validate(['comment' => 'required|string|min:3|max:1000']);

        $payments->waive($registration, $request->user(), $validated['comment']);

        return redirect()->back()->with('success', 'Charge waived.');
    }

    public function refund(Request $request, string $clubSlug, int $id, int $registrationId, EventPaymentService $payments): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);

        $validated = $request->validate([
            'comment' => 'required|string|min:3|max:1000',
            'amount' => 'nullable|numeric|min:0.01|max:99999999.99',
        ]);

        $payments->refund($registration, $request->user(), $validated['comment'], isset($validated['amount']) ? (float) $validated['amount'] : null);

        return redirect()->back()->with('success', 'Refund recorded.');
    }

    /**
     * @return array{0: Club, 1: EventRegistration}
     */
    private function find(string $clubSlug, int $id, int $registrationId): array
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        return [$club, EventRegistration::where('event_id', $event->id)->findOrFail($registrationId)];
    }
}
