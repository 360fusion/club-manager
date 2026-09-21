<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventMailer;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An organiser adds or changes a booking for a member or a visitor, from the registrations page.
 */
class EventOrganiserBookingController extends Controller
{
    /**
     * Find members of this club to book, by name, email or member number.
     */
    public function memberSearch(Request $request, string $clubSlug, int $id): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2 || mb_strlen($term) > 100) {
            return response()->json(['members' => []]);
        }

        // "!" is the escape character, so a typed % or _ is matched literally.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $members = $club->users()
            ->withPivot('home_club_name')
            ->wherePivot('status', 'active')
            ->where(fn ($q) => $q->whereRaw("users.name LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("users.email LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("club_user.member_number LIKE ? ESCAPE '!'", [$like]))
            ->orderBy('users.name')
            ->limit(10)
            ->get();

        $booked = EventRegistration::where('event_id', $event->id)->whereIn('user_id', $members->pluck('id'))->get()->keyBy('user_id');

        return response()->json(['members' => $members->map(fn (User $member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'phone' => $member->pivot->phone,
            'rank' => $member->pivot->rank,
            'lodge' => $member->pivot->home_club_name,
            'member_number' => $member->pivot->member_number,
            'dietary_notes' => $member->pivot->dietary_notes,
            'registration_id' => $booked->get($member->id)?->id,
            'registration_status' => $booked->get($member->id)?->status,
        ])->values()]);
    }

    /**
     * A booking in the shape the booking popup edits.
     */
    public function show(string $clubSlug, int $id, int $registrationId): JsonResponse
    {
        [$event, $registration] = $this->find($clubSlug, $id, $registrationId);
        $registration->load(['attendees', 'user']);
        $member = $registration->user_id ? $event->club->users()->withPivot('home_club_name')->where('users.id', $registration->user_id)->first() : null;

        return response()->json(['booking' => [
            'id' => $registration->id,
            'status' => $registration->status,
            'member' => $member ? ['id' => $member->id, 'name' => $member->name, 'rank' => $member->pivot->rank, 'lodge' => $member->pivot->home_club_name, 'member_number' => $member->pivot->member_number] : null,
            'contact_email' => $registration->contact_email,
            'contact_phone' => $registration->contact_phone,
            'internal_note' => $registration->internal_note,
            'promo_code' => $registration->promo_code,
            'payment_method' => $registration->payment_method_id ? $event->paymentMethods()->where('payment_method_id', $registration->payment_method_id)->value('id') : null,
            'payment' => ['status' => $registration->payment_status, 'total' => $registration->total, 'amount_paid' => $registration->amount_paid, 'balance' => number_format($registration->balanceDue(), 2, '.', '')],
            'locked' => $registration->attendees->contains(fn ($a) => $a->checked_in_at !== null),
            'attendees' => $registration->attendees->map(fn ($a) => [
                'name' => $a->name,
                'email' => $a->email ?? '',
                'organisation' => $a->organisation ?? '',
                'is_guest' => $a->is_guest,
                'ticket_tier_id' => $a->ticket_tier_id,
                'attending_dining' => $a->attending_dining,
                'starter_item_id' => $a->starter_item_id,
                'main_item_id' => $a->main_item_id,
                'dessert_item_id' => $a->dessert_item_id,
                'dietary_requirements' => $a->dietary_requirements ?? '',
            ])->values(),
        ]]);
    }

    public function store(Request $request, string $clubSlug, int $id, EventRegistrationService $registrations, EventPaymentService $payments): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $validated = $request->validate($this->rules());

        $member = null;

        if (! empty($validated['member_id'])) {
            $member = $club->users()->wherePivot('status', 'active')->where('users.id', $validated['member_id'])->first();

            if (! $member) {
                throw ValidationException::withMessages(['member_id' => 'That person is not an active member of this club.']);
            }
        }

        return $this->save($request, $club, $event, null, $member, $validated, $registrations, $payments, 'added');
    }

    public function update(Request $request, string $clubSlug, int $id, int $registrationId, EventRegistrationService $registrations, EventPaymentService $payments): RedirectResponse
    {
        [$event, $registration] = $this->find($clubSlug, $id, $registrationId);
        $validated = $request->validate($this->rules());

        return $this->save($request, $event->club, $event, $registration, null, $validated, $registrations, $payments, 'updated');
    }

    /**
     * Email the booker their confirmation again. A visitor's private link is only stored as a hash, so this gives them a fresh one.
     */
    public function resend(string $clubSlug, int $id, int $registrationId, EventMailer $mailer): RedirectResponse
    {
        [, $registration] = $this->find($clubSlug, $id, $registrationId);

        if (! $registration->contact_email || in_array($registration->status, ['cancelled', 'declined'], true)) {
            throw ValidationException::withMessages(['email' => 'This booking has no email address, or has been cancelled.']);
        }

        $mailer->bookingConfirmation($registration, null);

        return redirect()->back()->with('success', 'Confirmation sent to '.$registration->contact_email.'.');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function save(Request $request, Club $club, Event $event, ?EventRegistration $existing, ?User $member, array $validated, EventRegistrationService $registrations, EventPaymentService $payments, string $verb): RedirectResponse
    {
        $actor = $request->user();
        $markPaid = ! empty($validated['mark_paid']['enabled']) ? $validated['mark_paid'] : null;

        if ($markPaid && ! ClubAccess::can($actor, $club, 'manage_billing')) {
            throw ValidationException::withMessages(['mark_paid' => 'You do not have permission to record payments.']);
        }

        $people = array_values($validated['attendees']);
        $data = [
            'status' => $validated['status'] ?? 'attending',
            'contact_name' => $people[0]['name'],
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'internal_note' => $validated['internal_note'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'promo_code' => $validated['promo_code'] ?? null,
            'attendees' => $people,
        ];

        $registration = DB::transaction(function () use ($registrations, $payments, $event, $existing, $member, $data, $actor, $validated, $markPaid) {
            $registration = $registrations->saveByOrganiser($event, $existing, $member, $data, $actor, (bool) ($validated['over_capacity'] ?? false));

            if ($markPaid) {
                if ($registration->status !== 'attending' || (float) $registration->total <= 0) {
                    throw ValidationException::withMessages(['mark_paid' => 'Only a confirmed booking with something to pay can be marked as paid.']);
                }

                $payments->markPaid($registration, $actor, isset($markPaid['amount']) ? (float) $markPaid['amount'] : null, null, isset($markPaid['received_on']) ? Carbon::parse($markPaid['received_on']) : null, $markPaid['comment'] ?? null);
            }

            return $registration;
        });

        if ($request->boolean('send_confirmation') && $registration->contact_email) {
            app(EventMailer::class)->bookingConfirmation($registration, $registration->plainToken);
            $registrations->sendGuestConfirmations($registration);
        }

        return redirect()->back()->with('success', $registration->contact_name.' '.$verb.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'member_id' => 'nullable|integer|min:1|max:4294967295',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'internal_note' => 'nullable|string|max:2000',
            'status' => 'nullable|in:attending,waitlisted',
            'over_capacity' => 'nullable|boolean',
            'payment_method' => 'nullable|integer|min:1|max:4294967295',
            'promo_code' => 'nullable|string|max:40',
            'send_confirmation' => 'nullable|boolean',
            'mark_paid' => 'nullable|array|max:5',
            'mark_paid.enabled' => 'nullable|boolean',
            'mark_paid.amount' => 'nullable|numeric|min:0.01|max:99999999.99',
            'mark_paid.received_on' => 'nullable|date',
            'mark_paid.comment' => 'nullable|string|max:500',
            'attendees' => 'required|array|min:1|max:50',
            'attendees.*.name' => 'required|string|max:150',
            'attendees.*.email' => 'nullable|string|max:255',
            'attendees.*.organisation' => 'nullable|string|max:150',
            'attendees.*.is_guest' => 'nullable|boolean',
            'attendees.*.ticket_tier_id' => 'nullable|integer|min:1|max:4294967295',
            'attendees.*.attending_dining' => 'nullable|boolean',
            'attendees.*.starter_item_id' => 'nullable|integer|min:1|max:4294967295',
            'attendees.*.main_item_id' => 'nullable|integer|min:1|max:4294967295',
            'attendees.*.dessert_item_id' => 'nullable|integer|min:1|max:4294967295',
            'attendees.*.dietary_requirements' => 'nullable|string|max:1000',
        ];
    }

    /**
     * @return array{0: Event, 1: EventRegistration}
     */
    private function find(string $clubSlug, int $id, int $registrationId): array
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $registration = EventRegistration::where('event_id', $event->id)->findOrFail($registrationId);

        return [$event, $registration];
    }
}
