<?php

namespace App\Http\Controllers;

use App\Models\MeetingRsvp;
use App\Services\Meetings\MeetingDiningPayment;
use App\Services\Meetings\MeetingReplies;
use App\Services\RsvpTokenService;
use App\Support\Currencies;
use App\Support\NativeApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A member's reply page without signing in, reached two ways: the summons email's token link, or the signed links in
 * the reply schedule's emails (MeetingReplies::replyUrl), which can arrive with an answer already picked.
 */
class PasswordlessRsvpController extends Controller
{
    public function show(string $token, RsvpTokenService $tokenService, MeetingDiningPayment $payments): Response
    {
        $rsvp = $tokenService->validateToken($token);

        return $rsvp
            ? $this->page($rsvp, route('summons.rsvp.store', ['token' => $token]), route('summons.rsvp.pay', ['token' => $token]), null, $payments)
            : $this->expired();
    }

    public function store(Request $request, string $token, RsvpTokenService $tokenService, MeetingReplies $replies): RedirectResponse
    {
        $rsvp = $tokenService->validateToken($token);

        return $rsvp ? $this->save($request, $rsvp, $replies) : redirect()->back()->withErrors(['token' => 'Token has expired or is invalid.']);
    }

    public function pay(Request $request, string $token, RsvpTokenService $tokenService, MeetingDiningPayment $payments): HttpResponse
    {
        $rsvp = $tokenService->validateToken($token);

        abort_unless($rsvp !== null, 403);

        $back = route('summons.rsvp.show', ['token' => $token]);

        return Inertia::location($payments->start(
            $rsvp,
            $this->provider($request),
            NativeApp::returnUrl($request, $back.'?payment=success'),
            NativeApp::returnUrl($request, $back.'?payment=cancelled'),
            NativeApp::returnUrl($request, route('summons.rsvp.paypal_return', ['token' => $token])),
        ));
    }

    public function payPalReturn(Request $request, string $token, RsvpTokenService $tokenService, MeetingDiningPayment $payments): RedirectResponse
    {
        $rsvp = $tokenService->validateToken($token);

        abort_unless($rsvp !== null, 403);

        return redirect(route('summons.rsvp.show', ['token' => $token]).'?payment='.($this->capture($payments, $rsvp, $request) ? 'success' : 'cancelled'));
    }

    public function showSigned(Request $request, int $rsvp, MeetingReplies $replies, MeetingDiningPayment $payments): Response
    {
        $reply = $this->activeReply($rsvp);

        if (! $reply) {
            return $this->expired();
        }

        $expires = $replies->linkExpiry($reply->meeting);
        $answer = in_array($request->query('answer'), MeetingReplies::ANSWERS, true) ? $request->query('answer') : null;

        return $this->page(
            $reply,
            URL::temporarySignedRoute('summons.reply.store', $expires, ['rsvp' => $reply->id]),
            URL::temporarySignedRoute('summons.reply.pay', $expires, ['rsvp' => $reply->id]),
            $answer,
            $payments,
        );
    }

    public function storeSigned(Request $request, int $rsvp, MeetingReplies $replies): RedirectResponse
    {
        $reply = $this->activeReply($rsvp);

        return $reply ? $this->save($request, $reply, $replies) : redirect()->back()->withErrors(['token' => 'This link has expired or is invalid.']);
    }

    public function paySigned(Request $request, int $rsvp, MeetingReplies $replies, MeetingDiningPayment $payments): HttpResponse
    {
        $reply = $this->activeReply($rsvp);

        abort_unless($reply !== null, 403);

        // Each way back is signed with its own outcome, as any extra query would break the link's signature.
        $expires = $replies->linkExpiry($reply->meeting);
        $back = fn (string $outcome) => NativeApp::returnUrl($request, URL::temporarySignedRoute('summons.reply', $expires, ['rsvp' => $reply->id, 'payment' => $outcome]));

        return Inertia::location($payments->start(
            $reply,
            $this->provider($request),
            $back('success'),
            $back('cancelled'),
            NativeApp::returnUrl($request, URL::temporarySignedRoute('summons.reply.paypal_return', $expires, ['rsvp' => $reply->id])),
        ));
    }

    /**
     * PayPal adds its own token and PayerID to the way back, so the signature is checked without them.
     */
    public function payPalReturnSigned(Request $request, int $rsvp, MeetingReplies $replies, MeetingDiningPayment $payments): RedirectResponse
    {
        abort_unless($request->hasValidSignatureWhileIgnoring(['token', 'PayerID']), 403);
        $reply = $this->activeReply($rsvp);
        abort_unless($reply !== null, 403);

        $outcome = $this->capture($payments, $reply, $request) ? 'success' : 'cancelled';

        return redirect(URL::temporarySignedRoute('summons.reply', $replies->linkExpiry($reply->meeting), ['rsvp' => $reply->id, 'payment' => $outcome]));
    }

    private function provider(Request $request): string
    {
        return $request->input('provider') === MeetingDiningPayment::PAYPAL ? MeetingDiningPayment::PAYPAL : MeetingDiningPayment::CARD;
    }

    private function capture(MeetingDiningPayment $payments, MeetingRsvp $rsvp, Request $request): bool
    {
        try {
            return $payments->capturePayPal($rsvp, (string) $request->query('token'));
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * The reply a signed link points at, while its person is still an active member or registered visitor of the lodge.
     */
    private function activeReply(int $id): ?MeetingRsvp
    {
        $rsvp = MeetingRsvp::with(['meeting.club', 'user', 'guests'])->find($id);

        $isActive = $rsvp?->user && $rsvp->user->clubs()->where('clubs.id', $rsvp->meeting->club_id)->wherePivot('status', 'active')->exists();

        return $isActive ? $rsvp : null;
    }

    private function expired(): Response
    {
        return Inertia::render('Summons/Expired', [
            'message' => 'This RSVP link is invalid or has expired. Please contact your Secretary for assistance.',
        ]);
    }

    private function page(MeetingRsvp $rsvp, string $submitUrl, string $payUrl, ?string $answer, MeetingDiningPayment $payments): Response
    {
        $meeting = $rsvp->meeting;
        $club = $meeting->club;
        $user = $rsvp->user;
        $clubUser = $user->clubs()->where('club_id', $club->id)->first()?->pivot;
        $isVisitor = $clubUser && $clubUser->role === 'visitor';
        $visitorHomeClub = $isVisitor ? trim(($clubUser->home_club_name ?? '').($clubUser->home_club_number ? ' No '.$clubUser->home_club_number : '')) : null;
        $owed = MeetingReplies::owed($meeting, $rsvp->load('guests'));

        return Inertia::render('Summons/Rsvp', [
            'submitUrl' => $submitUrl,
            'payUrl' => $payUrl,
            'answer' => $isVisitor && $answer === 'apologies' ? null : $answer,
            // Only what the page shows: the link is in an email, so nothing else about the person, lodge or meeting goes with it.
            'club' => ['name' => $club->name],
            'user' => ['name' => $user->name],
            'meeting' => $meeting->only(['id', 'title', 'meeting_date', 'starts_at', 'rehearsal_starts_at', 'venue', 'dress_code', 'dining_cost_member', 'bank_sort_code', 'bank_account_number', 'payment_link']),
            'rsvp' => [...$rsvp->only(['attendance_status', 'dietary_requirements', 'apology_reason', 'payment_reference', 'payment_status']), 'guests' => MeetingReplies::listedGuests($rsvp)],
            'owed' => $owed['amount'] > 0 ? Currencies::format($owed['amount'], $club) : null,
            'payWith' => $payments->providers($rsvp),
            'isCutoffPassed' => $meeting->rsvpClosed(),
            'isVisitor' => $isVisitor,
            'visitorHomeClub' => $visitorHomeClub,
        ]);
    }

    /**
     * Save attendance, guests and dietary notes. After the dining deadline dining can be dropped but not added.
     */
    private function save(Request $request, MeetingRsvp $rsvp, MeetingReplies $replies): RedirectResponse
    {
        $meeting = $rsvp->meeting;
        $user = $rsvp->user;
        $club = $meeting->club;

        $clubUser = $user->clubs()->where('club_id', $club->id)->first()?->pivot;
        $isVisitor = $clubUser && $clubUser->role === 'visitor';

        $allowedStatuses = $isVisitor ? 'in:attending_dining,attending_meeting_only' : 'in:attending_dining,attending_meeting_only,apologies';

        $validated = $request->validate([
            'attendance_status' => 'required|'.$allowedStatuses,
            'apology_reason' => 'nullable|string|max:1000',
            'dietary_requirements' => 'nullable|string|max:1000',
            'guests' => 'nullable|array|max:'.$club->maxGuestsPerMember(),
            'guests.*.guest_name' => 'required|string|max:150',
            'guests.*.guest_title_rank' => 'nullable|string|max:100',
            'guests.*.home_club_lodge' => 'nullable|string|max:150',
            'guests.*.attending_dining' => 'required|boolean',
            'guests.*.dietary_requirements' => 'nullable|string|max:1000',
        ]);

        $before = MeetingReplies::before($rsvp);
        MeetingReplies::guardDining($meeting, $before, $validated['attendance_status'], $validated['guests'] ?? []);

        $rsvp->update([
            'attendance_status' => $validated['attendance_status'],
            'apology_reason' => $validated['apology_reason'] ?? null,
            'dietary_requirements' => $validated['dietary_requirements'] ?? null,
            'payment_reference' => MeetingReplies::paymentReference($meeting, $user),
            'responded_at' => Carbon::now(),
        ]);

        MeetingReplies::syncGuests($meeting, $rsvp, $validated['guests'] ?? []);

        $replies->afterChange($meeting, $rsvp, $before);

        return redirect()->back()->with('success', 'Your RSVP response has been submitted successfully.');
    }
}
