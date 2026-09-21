<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventOnlinePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Pay now" for a booking: sends the person to Stripe's or PayPal's secure page for what they still owe.
 */
class EventOnlinePaymentController extends Controller
{
    public function member(Request $request, string $slug, int $id, EventOnlinePayment $online): Response
    {
        $registration = $this->memberRegistration($slug, $id);
        $optionId = $this->optionId($request);

        $back = route('member.events', ['slug' => $slug]);

        return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled', $optionId, route('member.events.paypal_return', ['slug' => $slug, 'id' => $id])));
    }

    public function guest(Request $request, string $clubSlug, string $token, EventOnlinePayment $online): Response
    {
        $registration = $this->guestRegistration($clubSlug, $token);
        $optionId = $this->optionId($request);

        $back = route('public.event.booking', ['clubSlug' => $clubSlug, 'token' => $token]);

        return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled', $optionId, route('public.event.booking.paypal_return', ['clubSlug' => $clubSlug, 'token' => $token])));
    }

    /**
     * PayPal sends a member back here once they have approved the payment; it is taken now.
     */
    public function memberPayPalReturn(Request $request, string $slug, int $id, EventOnlinePayment $online): RedirectResponse
    {
        $registration = $this->memberRegistration($slug, $id);
        $back = route('member.events', ['slug' => $slug]);

        return redirect($back.'?payment='.($this->capture($online, $registration, (string) $request->query('token')) ? 'success' : 'cancelled'));
    }

    public function guestPayPalReturn(Request $request, string $clubSlug, string $token, EventOnlinePayment $online): RedirectResponse
    {
        $registration = $this->guestRegistration($clubSlug, $token);
        $back = route('public.event.booking', ['clubSlug' => $clubSlug, 'token' => $token]);

        return redirect($back.'?payment='.($this->capture($online, $registration, (string) $request->query('token')) ? 'success' : 'cancelled'));
    }

    private function capture(EventOnlinePayment $online, EventRegistration $registration, string $orderId): bool
    {
        try {
            return $online->capturePayPal($registration, $orderId);
        } catch (ValidationException) {
            return false;
        }
    }

    private function optionId(Request $request): ?int
    {
        $validated = $request->validate(['option' => 'nullable|integer|min:1|max:4294967295']);

        return isset($validated['option']) ? (int) $validated['option'] : null;
    }

    private function memberRegistration(string $slug, int $id): EventRegistration
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->published()->findOrFail($id);

        return EventRegistration::where('event_id', $event->id)->where('user_id', Auth::id())->firstOrFail();
    }

    private function guestRegistration(string $clubSlug, string $token): EventRegistration
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        abort_if(strlen($token) < 20, 404);

        return EventRegistration::where('token_hash', hash('sha256', $token))
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->firstOrFail();
    }
}
