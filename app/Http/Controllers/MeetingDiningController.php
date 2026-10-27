<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingDining\RecordDiningPaymentRequest;
use App\Http\Requests\MeetingDining\UpdatePaymentStatusRequest;
use App\Http\Requests\MeetingDining\UpdateRsvpRequest;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dining list, who is attending, and the RSVPs and payments an organiser records for members and visitors. Split out of MeetingAdminController; the URLs, route names and permissions are unchanged.
 */
class MeetingDiningController extends Controller
{
    /**
     * Display administrative attendance dashboard & caterer breakdown.
     */
    /** How a dining head can be settled. Kept here so the page and the validator can't drift. */
    public const DINING_PAYMENT_METHODS = ['bank', 'online', 'cash'];

    /**
     * The dining list for one meeting: every head the caterer is cooking for, what they owe and
     * whether it has been settled.
     */
    public function dining(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $rsvps = MeetingRsvp::where('meeting_id', $meeting->id)
            ->where('attendance_status', 'attending_dining')
            ->with(['user', 'guests' => fn ($q) => $q->where('attending_dining', true), 'paidRecorder:id,name'])
            ->get();

        $diners = collect();

        foreach ($rsvps as $rsvp) {
            $diners->push([
                'kind' => 'member',
                'id' => $rsvp->id,
                'name' => $rsvp->user?->name ?? 'Unknown member',
                'host' => null,
                'fee' => (float) $meeting->dining_cost_member,
                'dietary' => $rsvp->dietary_requirements,
                'payment_status' => $rsvp->payment_status,
                'payment_method' => $rsvp->payment_method,
                'payment_reference' => $rsvp->payment_reference,
                'paid_at' => $rsvp->paid_at?->format('j M Y, H:i'),
                'paid_by' => $rsvp->paidRecorder?->name,
            ]);

            foreach ($rsvp->guests as $guest) {
                $diners->push([
                    'kind' => 'guest',
                    'id' => $guest->id,
                    'name' => trim(($guest->guest_title_rank ? $guest->guest_title_rank.' ' : '').$guest->guest_name),
                    'host' => $rsvp->user?->name,
                    'fee' => (float) ($guest->dining_fee ?: $meeting->dining_cost_guest),
                    'dietary' => $guest->dietary_requirements,
                    'payment_status' => $guest->payment_status,
                    'payment_method' => $guest->payment_method,
                    'payment_reference' => null,
                    'paid_at' => $guest->paid_at?->format('j M Y, H:i'),
                    'paid_by' => $guest->paidRecorder?->name,
                ]);
            }
        }

        $diners = $diners->sortBy(fn ($d) => [$d['host'] ?? $d['name'], $d['kind'] === 'guest' ? 1 : 0])->values();
        $settled = $diners->whereIn('payment_status', ['paid', 'waived']);

        return Inertia::render('Admin/Meetings/Dining', [
            'club' => $club,
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date->format('l, j F Y'),
                'dining_cost_member' => (float) $meeting->dining_cost_member,
                'dining_cost_guest' => (float) $meeting->dining_cost_guest,
            ],
            'diners' => $diners,
            'paymentMethods' => self::DINING_PAYMENT_METHODS,
            'totals' => [
                'heads' => $diners->count(),
                'expected' => round((float) $diners->sum('fee'), 2),
                'collected' => round((float) $settled->sum('fee'), 2),
                'outstanding' => round((float) $diners->sum('fee') - (float) $settled->sum('fee'), 2),
            ],
        ]);
    }

    /**
     * Record how one dining head was settled. Members and guests pay separately, so the row is
     * addressed by kind plus its own id rather than by user.
     */
    public function recordDiningPayment(RecordDiningPaymentRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $isSettled = $validated['payment_status'] === 'paid';

        $changes = [
            'payment_status' => $validated['payment_status'],
            'payment_method' => $isSettled ? $validated['payment_method'] : null,
            'paid_at' => $isSettled ? now() : null,
            'paid_recorded_by' => $isSettled ? $request->user()->id : null,
        ];

        if ($validated['kind'] === 'member') {
            $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('id', $validated['diner_id'])->firstOrFail();
            $changes['payment_reference'] = $validated['payment_reference'] ?? $rsvp->payment_reference;
            $rsvp->update($changes);
            $who = $rsvp->user?->name ?? 'member';
        } else {
            $guest = MeetingRsvpGuest::where('id', $validated['diner_id'])
                ->whereHas('rsvp', fn ($q) => $q->where('meeting_id', $meeting->id))
                ->firstOrFail();
            $guest->update($changes);
            $who = $guest->guest_name;
        }

        return redirect()->back()->with('success', $isSettled
            ? "Dining payment recorded for {$who}."
            : "Dining payment status updated for {$who}.");
    }

    /**
     * Manually record or update an RSVP on behalf of a member/visitor.
     */
    public function updateRsvp(UpdateRsvpRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $user = $this->rsvpUser($club, $meeting, (int) $validated['user_id']);
        abort_if($user === null, 404);
        $surname = $user ? strtoupper(last(explode(' ', $user->name))) : 'MEMBER';
        $defaultRef = ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname;

        $updateData = [
            'token_hash' => Str::random(40),
            'token_expires_at' => now()->addDays(30),
            'attendance_status' => $validated['attendance_status'],
            'apology_reason' => $validated['apology_reason'] ?? null,
            'dietary_requirements' => $validated['dietary_requirements'] ?? null,
            'responded_at' => now(),
        ];

        if (isset($validated['payment_status'])) {
            $updateData['payment_status'] = $validated['payment_status'];
        }
        if (isset($validated['payment_reference'])) {
            $updateData['payment_reference'] = $validated['payment_reference'];
        } else {
            $updateData['payment_reference'] = $defaultRef;
        }

        $rsvp = MeetingRsvp::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $validated['user_id']],
            $updateData
        );

        if (array_key_exists('guests', $validated)) {
            $rsvp->guests()->delete();
            if (is_array($validated['guests'])) {
                foreach ($validated['guests'] as $g) {
                    if (! empty($g['guest_name'])) {
                        $rsvp->guests()->create([
                            'guest_name' => $g['guest_name'],
                            'dietary_requirements' => $g['dietary_requirements'] ?? null,
                            'attending_dining' => $g['attending_dining'] ?? true,
                            'dining_fee' => $meeting->dining_cost_guest,
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'RSVP record updated successfully.');
    }

    /**
     * Quickly toggle or set RSVP payment status for a member/visitor.
     */
    public function updatePaymentStatus(UpdatePaymentStatusRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

        $user = $this->rsvpUser($club, $meeting, (int) $validated['user_id']);
        abort_if($user === null, 404);
        $surname = strtoupper(last(explode(' ', $user->name)));
        $paymentRef = $validated['payment_reference'] ?? (($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname);

        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
        if ($rsvp) {
            $rsvp->update([
                'payment_status' => $validated['payment_status'],
                'payment_reference' => $paymentRef,
            ]);
        } else {
            MeetingRsvp::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'token_hash' => Str::random(40),
                'token_expires_at' => now()->addDays(30),
                'attendance_status' => 'attending_dining',
                'payment_status' => $validated['payment_status'],
                'payment_reference' => $paymentRef,
                'responded_at' => now(),
            ]);
        }

        $statusLabel = ucfirst($validated['payment_status']);

        return redirect()->back()->with('success', "Payment status marked as {$statusLabel} for {$user->name}.");
    }

    /**
     * A user an admin may record an RSVP for: a member of the club, or someone
     * who already has an RSVP on this meeting (a visitor).
     */
    private function rsvpUser(Club $club, Meeting $meeting, int $userId): ?User
    {
        $isMember = $club->users()->where('users.id', $userId)->exists();
        $isVisitor = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $userId)->exists();

        return $isMember || $isVisitor ? User::find($userId) : null;
    }
}
