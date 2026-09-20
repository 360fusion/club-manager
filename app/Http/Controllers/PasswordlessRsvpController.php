<?php

namespace App\Http\Controllers;

use App\Models\MeetingRsvpGuest;
use App\Services\RsvpTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PasswordlessRsvpController extends Controller
{
    /**
     * Display passwordless RSVP page for a given signed raw token.
     */
    public function show(string $token, RsvpTokenService $tokenService): Response
    {
        $rsvp = $tokenService->validateToken($token);

        if (! $rsvp) {
            return Inertia::render('Summons/Expired', [
                'message' => 'This RSVP link is invalid or has expired. Please contact your Secretary for assistance.',
            ]);
        }

        $meeting = $rsvp->meeting;
        $club = $meeting->club;
        $user = $rsvp->user;
        $isCutoffPassed = Carbon::now()->isAfter($meeting->rsvp_cutoff_at);
        $clubUser = $user->clubs()->where('club_id', $club->id)->first()?->pivot;
        $isVisitor = $clubUser && $clubUser->role === 'visitor';
        $visitorHomeClub = $isVisitor ? trim(($clubUser->home_club_name ?? '').($clubUser->home_club_number ? ' No '.$clubUser->home_club_number : '')) : null;

        return Inertia::render('Summons/Rsvp', [
            'token' => $token,
            'club' => $club,
            'user' => $user,
            'meeting' => $meeting,
            'rsvp' => $rsvp->load('guests'),
            'isCutoffPassed' => $isCutoffPassed,
            'isVisitor' => $isVisitor,
            'visitorHomeClub' => $visitorHomeClub,
        ]);
    }

    /**
     * Submit attendance response, guest additions, dietary notes, & payment details.
     */
    public function store(Request $request, string $token, RsvpTokenService $tokenService): RedirectResponse
    {
        $rsvp = $tokenService->validateToken($token);

        if (! $rsvp) {
            return redirect()->back()->withErrors(['token' => 'Token has expired or is invalid.']);
        }

        $meeting = $rsvp->meeting;
        $user = $rsvp->user;
        $club = $meeting->club;

        if (Carbon::now()->isAfter($meeting->rsvp_cutoff_at)) {
            return redirect()->back()->withErrors(['cutoff' => 'The dining deadline has passed. Please contact the Secretary directly.']);
        }

        $clubUser = $user->clubs()->where('club_id', $club->id)->first()?->pivot;
        $isVisitor = $clubUser && $clubUser->role === 'visitor';

        $allowedStatuses = $isVisitor ? 'in:attending_dining,attending_meeting_only' : 'in:attending_dining,attending_meeting_only,apologies';

        $validated = $request->validate([
            'attendance_status' => 'required|'.$allowedStatuses,
            'apology_reason' => 'nullable|string',
            'dietary_requirements' => 'nullable|string',
            'guests' => 'nullable|array',
            'guests.*.guest_name' => 'required|string|max:150',
            'guests.*.guest_title_rank' => 'nullable|string|max:100',
            'guests.*.home_club_lodge' => 'nullable|string|max:150',
            'guests.*.attending_dining' => 'required|boolean',
            'guests.*.dietary_requirements' => 'nullable|string',
        ]);

        $surname = strtoupper(last(explode(' ', $rsvp->user->name)));
        $paymentRef = ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname;

        $rsvp->update([
            'attendance_status' => $validated['attendance_status'],
            'apology_reason' => $validated['apology_reason'] ?? null,
            'dietary_requirements' => $validated['dietary_requirements'] ?? null,
            'payment_reference' => $paymentRef,
            'responded_at' => Carbon::now(),
        ]);

        // Sync Guests
        MeetingRsvpGuest::where('meeting_rsvp_id', $rsvp->id)->delete();
        if (! empty($validated['guests'])) {
            foreach ($validated['guests'] as $g) {
                MeetingRsvpGuest::create([
                    'meeting_rsvp_id' => $rsvp->id,
                    'guest_name' => $g['guest_name'],
                    'guest_title_rank' => $g['guest_title_rank'] ?? null,
                    'home_club_lodge' => $g['home_club_lodge'] ?? null,
                    'attending_dining' => $g['attending_dining'],
                    'dietary_requirements' => $g['dietary_requirements'] ?? null,
                    'dining_fee' => $g['attending_dining'] ? $meeting->dining_cost_guest : 0.00,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Your RSVP response has been submitted successfully.');
    }
}
