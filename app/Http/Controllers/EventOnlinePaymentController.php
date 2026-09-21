<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventOnlinePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Pay now" for a booking: sends the person to Stripe's secure page for what they still owe.
 */
class EventOnlinePaymentController extends Controller
{
    public function member(Request $request, string $slug, int $id, EventOnlinePayment $online): Response
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->published()->findOrFail($id);
        $registration = EventRegistration::where('event_id', $event->id)->where('user_id', Auth::id())->firstOrFail();

        $back = route('member.events', ['slug' => $club->slug]);

        return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled'));
    }

    public function guest(string $clubSlug, string $token, EventOnlinePayment $online): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        abort_if(strlen($token) < 20, 404);

        $registration = EventRegistration::where('token_hash', hash('sha256', $token))
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->firstOrFail();

        $back = route('public.event.booking', ['clubSlug' => $club->slug, 'token' => $token]);

        return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled'));
    }
}
