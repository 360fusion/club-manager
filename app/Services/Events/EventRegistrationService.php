<?php

namespace App\Services\Events;

use App\Mail\EventGuestBookingMail;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\EventPaymentMethod;
use App\Models\EventPromo;
use App\Models\EventRegistration;
use App\Models\EventTicketTier;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every booking goes through here, so capacity, the waitlist, guest limits and
 * meal and ticket choices are enforced in one place.
 *
 * @phpstan-type AttendeeInput array{name?: string, is_guest?: bool, ticket_tier_id?: int|null, attending_dining?: bool, starter_item_id?: int|null, main_item_id?: int|null, dessert_item_id?: int|null, dietary_requirements?: string|null}
 */
class EventRegistrationService
{
    /** Course => the attendee column that stores the chosen dish. */
    private const COURSES = ['starter' => 'starter_item_id', 'main' => 'main_item_id', 'dessert' => 'dessert_item_id'];

    /**
     * Create or update a booking.
     *
     * @param  array{status?: string, contact_name?: string, contact_email?: string|null, contact_phone?: string|null, notes?: string|null, attendees?: list<AttendeeInput>}  $data
     */
    public function register(Event $event, ?User $user, array $data): EventRegistration
    {
        return DB::transaction(function () use ($event, $user, $data) {
            // Lock the event row so two people cannot take the last place at once.
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            $this->assertOpen($event, $user);

            $existing = $user
                ? EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->first()
                : null;

            if ($existing && $existing->attendees()->whereNotNull('checked_in_at')->exists()) {
                throw ValidationException::withMessages(['registration' => 'You have already been checked in, so this booking can no longer be changed.']);
            }

            $status = $data['status'] ?? 'attending';
            $attendees = $this->prepareAttendees($event, $user, $status, $data['attendees'] ?? [], $data['contact_name'] ?? $user?->name ?? '');
            $going = in_array($status, EventRegistration::HOLDING_PLACE, true);

            if ($going) {
                $status = $this->statusWithCapacity($event, $existing, $status, count($attendees));
            }

            $plainToken = null;
            $fields = [
                'contact_name' => $data['contact_name'] ?? $user?->name ?? '',
                'contact_email' => $data['contact_email'] ?? $user?->email,
                'contact_phone' => $data['contact_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
            ];

            $alreadyEmailed = [];

            if ($existing) {
                $alreadyEmailed = $existing->attendees()->whereNotNull('notified_at')->whereNotNull('email')->pluck('email')->map(fn ($e) => strtolower($e))->all();
                $existing->update($fields);
                $existing->attendees()->delete();
                $registration = $existing;
            } else {
                if (! $user) {
                    $plainToken = Str::random(40);
                    $fields['token_hash'] = hash('sha256', $plainToken);
                }

                $registration = EventRegistration::create($fields + ['event_id' => $event->id, 'user_id' => $user?->id]);
            }

            foreach ($attendees as $attendee) {
                $emailed = ! empty($attendee['email']) && in_array(strtolower($attendee['email']), $alreadyEmailed, true);
                $registration->attendees()->create($attendee + ['notified_at' => $emailed ? now() : null]);
            }

            $registration->load('attendees');

            if ($going) {
                $this->applyPricing($event, $registration, $attendees, $data, $existing);
            }

            $registration->plainToken = $plainToken;

            $this->syncTierCounts($event);

            if (! $going || $status === 'waitlisted') {
                $this->promoteFromWaitlist($event);
            }

            return $registration;
        });
    }

    /**
     * Work out what this booking costs and store it, with the payment method chosen, its reference and due date.
     *
     * @param  list<array<string, mixed>>  $people
     * @param  array<string, mixed>  $data
     */
    private function applyPricing(Event $event, EventRegistration $registration, array $people, array $data, ?EventRegistration $before): void
    {
        $pricing = app(EventPricing::class);
        $enabled = $pricing->enabledMethods($event);
        $chosen = null;

        if (! empty($data['payment_method'])) {
            $chosen = $enabled->firstWhere('id', (int) $data['payment_method']);

            if (! $chosen) {
                throw ValidationException::withMessages(['payment_method' => 'That way of paying is not available for this event.']);
            }
        }

        $code = isset($data['promo_code']) && trim((string) $data['promo_code']) !== '' ? trim((string) $data['promo_code']) : null;
        $sameCode = $code !== null && $before && strcasecmp((string) $before->promo_code, $code) === 0;
        $quote = $pricing->quote($event, $people, $code, $chosen, $sameCode);

        if ($quote['total'] > 0 && $enabled->isNotEmpty() && ! $chosen) {
            throw ValidationException::withMessages(['payment_method' => 'Please choose how you will pay.']);
        }

        if ($quote['promo_code'] !== null && ! $sameCode) {
            EventPromo::where('event_id', $event->id)->whereRaw('UPPER(code) = ?', [strtoupper($quote['promo_code'])])->increment('uses_count');
        }

        foreach ($registration->attendees as $index => $attendee) {
            $attendee->update(['price' => $quote['people'][$index]['price'] ?? 0]);
        }

        $method = $chosen?->method;
        $registration->fill([
            'subtotal' => $quote['subtotal'],
            'promo_code' => $quote['promo_code'],
            'promo_discount' => $quote['promo_discount'],
            'method_adjustment' => $quote['method_adjustment'],
            'booking_fee' => $quote['booking_fee'],
            'total' => $quote['total'],
            'payment_method_id' => $method?->id,
            'payment_reference' => $registration->payment_reference ?? $registration->makeReference($method),
            'due_at' => $method?->type === ClubPaymentMethod::LATER ? $this->dueAt($event, $chosen) : null,
        ]);
        $registration->payment_status = $registration->statusFromAmounts();
        $registration->save();
    }

    /**
     * Change how an unpaid booking will be paid, at that option's price. Used when someone chooses to pay online
     * after booking, so paying early still earns the discount. Nothing changes once money has been paid.
     */
    public function reprice(EventRegistration $registration, EventPaymentMethod $option): EventRegistration
    {
        if ((float) $registration->amount_paid > 0) {
            throw ValidationException::withMessages(['payment' => 'This booking already has a payment on it, so the way of paying cannot be changed.']);
        }

        $registration->loadMissing(['attendees', 'event']);
        $people = $registration->attendees->map(fn ($a) => ['is_guest' => $a->is_guest, 'ticket_tier_id' => $a->ticket_tier_id, 'attending_dining' => $a->attending_dining])->all();
        $quote = app(EventPricing::class)->quote($registration->event, $people, $registration->promo_code, $option, true);

        foreach ($registration->attendees as $index => $attendee) {
            $attendee->update(['price' => $quote['people'][$index]['price'] ?? 0]);
        }

        $registration->fill([
            'subtotal' => $quote['subtotal'],
            'promo_discount' => $quote['promo_discount'],
            'method_adjustment' => $quote['method_adjustment'],
            'booking_fee' => $quote['booking_fee'],
            'total' => $quote['total'],
            'payment_method_id' => $option->method->id,
            'due_at' => null,
        ]);
        $registration->payment_status = $registration->statusFromAmounts();
        $registration->save();

        return $registration;
    }

    private function dueAt(Event $event, EventPaymentMethod $method): ?Carbon
    {
        ['days' => $days, 'basis' => $basis] = $method->due();

        if ($days === null) {
            return null;
        }

        return $basis === 'after_booking' ? now()->addDays($days) : $event->starts_at?->copy()->subDays($days);
    }

    /**
     * A one-tap reply: keep an existing booking's people and choices, or start one for the member alone.
     */
    public function quickReply(Event $event, User $user, string $status, ?string $dietaryFallback = null): EventRegistration
    {
        $existing = EventRegistration::with('attendees')->where('event_id', $event->id)->where('user_id', $user->id)->first();

        $attendees = $existing && $existing->attendees->isNotEmpty()
            ? $existing->attendees->map(fn (EventAttendee $a) => [
                'name' => $a->name,
                'email' => $a->email,
                'is_guest' => $a->is_guest,
                'ticket_tier_id' => $a->ticket_tier_id,
                'attending_dining' => $a->attending_dining,
                'starter_item_id' => $a->starter_item_id,
                'main_item_id' => $a->main_item_id,
                'dessert_item_id' => $a->dessert_item_id,
                'dietary_requirements' => $a->dietary_requirements,
            ])->all()
            : [['name' => $user->name, 'dietary_requirements' => $dietaryFallback]];

        return $this->register($event, $user, ['status' => $status, 'attendees' => $attendees]);
    }

    /**
     * A person who turns up without a booking. The organiser decides, so closing dates
     * and capacity do not stop them; they are checked in straight away.
     */
    public function walkIn(Event $event, string $name, ?string $dietary = null): EventAttendee
    {
        return DB::transaction(function () use ($event, $name, $dietary) {
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'contact_name' => $name,
                'status' => 'attending',
            ]);

            $attendee = $registration->attendees()->create([
                'name' => $name,
                'is_guest' => false,
                'dietary_requirements' => $this->clean($dietary),
                'checked_in_at' => now(),
            ]);

            $this->syncTierCounts($event);

            return $attendee;
        });
    }

    /**
     * A booking the organiser adds by hand (a phone call, a late request). Closing dates and
     * capacity do not stop them; the organiser decides.
     */
    public function addManual(Event $event, string $name, ?string $email = null, ?string $dietary = null): EventRegistration
    {
        return DB::transaction(function () use ($event, $name, $email, $dietary) {
            $registration = EventRegistration::create([
                'event_id' => $event->id,
                'contact_name' => $name,
                'contact_email' => $email,
                'status' => 'attending',
            ]);

            $registration->attendees()->create(['name' => $name, 'is_guest' => false, 'dietary_requirements' => $this->clean($dietary)]);
            $this->syncTierCounts($event);

            return $registration->load('attendees');
        });
    }

    /**
     * Give a waiting booking its place now, even if that goes over capacity.
     */
    public function promote(EventRegistration $registration): EventRegistration
    {
        $registration->update(['status' => 'attending']);
        $this->syncTierCounts($registration->event);

        return $registration;
    }

    /**
     * Cancel a booking and offer the freed places to the waitlist.
     */
    public function cancel(EventRegistration $registration): EventRegistration
    {
        return DB::transaction(function () use ($registration) {
            $event = Event::whereKey($registration->event_id)->lockForUpdate()->firstOrFail();

            $registration->update(['status' => 'cancelled']);
            $this->syncTierCounts($event);
            $this->promoteFromWaitlist($event);

            return $registration->fresh();
        });
    }

    /**
     * Move waitlisted bookings up, oldest first, while whole bookings fit.
     *
     * @return Collection<int, EventRegistration>
     */
    public function promoteFromWaitlist(Event $event): Collection
    {
        $promoted = collect();

        if ($event->capacity === null) {
            return $promoted;
        }

        $waiting = EventRegistration::where('event_id', $event->id)->where('status', 'waitlisted')->orderBy('id')->with('attendees')->get();

        foreach ($waiting as $registration) {
            if ($event->placesTaken() + $registration->headcount() > $event->capacity) {
                continue;
            }

            $registration->update(['status' => 'attending']);
            $promoted->push($registration);
        }

        if ($promoted->isNotEmpty()) {
            $this->syncTierCounts($event);
        }

        return $promoted;
    }

    /**
     * Whether this person may make or change a booking right now.
     */
    public function assertOpen(Event $event, ?User $user): void
    {
        if (in_array($event->status, ['draft', 'cancelled', 'completed'], true)) {
            throw ValidationException::withMessages(['registration' => 'This event is not open for booking.']);
        }

        if ($event->registration_opens_at && now()->isBefore($event->registration_opens_at)) {
            throw ValidationException::withMessages(['registration' => 'Booking has not opened yet.']);
        }

        if ($event->is_booking_closed) {
            throw ValidationException::withMessages(['booking_closed' => 'Bookings for this event have closed.']);
        }

        $allowed = $user
            ? $event->canBeRsvpedBy($user)
            : ($event->allow_public_registration && $event->visibility->value === 'public');

        if (! $allowed) {
            throw ValidationException::withMessages(['registration' => 'This event is not open to your account.']);
        }
    }

    /**
     * How many guests one booking may include.
     */
    public function maxGuests(Event $event): int
    {
        return $event->max_guests_per_booking ?? $event->club->maxGuestsPerMember();
    }

    /**
     * Turn the submitted people into rows, checking every choice against this event.
     *
     * @param  list<AttendeeInput>  $people
     * @return list<array<string, mixed>>
     */
    private function prepareAttendees(Event $event, ?User $user, string $status, array $people, string $bookerName): array
    {
        // A reply that isn't "going" still records the booker, so the roll stays complete.
        if (! in_array($status, EventRegistration::HOLDING_PLACE, true)) {
            $people = [['name' => $bookerName, 'is_guest' => false]];
        }

        if ($people === []) {
            $people = [['name' => $bookerName, 'is_guest' => false]];
        }

        $guests = collect($people)->where('is_guest', true)->count();

        if ($guests > $this->maxGuests($event)) {
            throw ValidationException::withMessages(['attendees' => 'You can bring at most '.$this->maxGuests($event).' guests.']);
        }

        $tiers = $event->ticketTiers()->get();
        $items = $event->menuItems()->get()->keyBy('id');
        $going = in_array($status, EventRegistration::HOLDING_PLACE, true);
        $rows = [];

        foreach (array_values($people) as $index => $person) {
            $isGuest = (bool) ($person['is_guest'] ?? false);
            $name = trim((string) ($person['name'] ?? ''));

            if ($index === 0 && ! $isGuest && $name === '') {
                $name = $bookerName;
            }

            if ($name === '') {
                throw ValidationException::withMessages(["attendees.{$index}.name" => 'Please enter a name for every guest.']);
            }

            $email = $this->guestEmail($person['email'] ?? null, $isGuest || $index > 0, $index);

            $row = [
                'user_id' => $isGuest || $index > 0 ? null : $user?->id,
                'name' => $name,
                'email' => $email,
                'is_guest' => $isGuest || $index > 0,
                'dietary_requirements' => $this->clean($person['dietary_requirements'] ?? null),
                'attending_dining' => false,
                'ticket_tier_id' => null,
            ];

            if ($going) {
                $row['ticket_tier_id'] = $this->resolveTier($tiers, $person['ticket_tier_id'] ?? null, $row['is_guest'], $user !== null, $index);
                $row = [...$row, ...$this->resolveMeal($event, $items, $person, $index)];
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  Collection<int, EventTicketTier>  $tiers
     */
    private function resolveTier(Collection $tiers, mixed $requested, bool $isGuest, bool $isMember, int $index): ?int
    {
        $audiences = $isGuest ? ['all', 'guest', 'public'] : ($isMember ? ['all', 'member'] : ['all', 'public', 'guest']);
        $eligible = $tiers->filter(fn (EventTicketTier $tier) => in_array($tier->audience ?: 'all', $audiences, true));

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($requested === null || $requested === '') {
            if ($eligible->count() === 1) {
                $requested = $eligible->first()->id;
            } else {
                throw ValidationException::withMessages(["attendees.{$index}.ticket_tier_id" => 'Please choose a ticket type.']);
            }
        }

        $tier = $eligible->firstWhere('id', (int) $requested);

        if (! $tier) {
            throw ValidationException::withMessages(["attendees.{$index}.ticket_tier_id" => 'That ticket type is not available to you.']);
        }

        return $tier->id;
    }

    /**
     * @param  Collection<int, EventMenuItem>  $items
     * @param  AttendeeInput  $person
     * @return array<string, mixed>
     */
    private function resolveMeal(Event $event, Collection $items, array $person, int $index): array
    {
        $dining = $event->has_dining && (bool) ($person['attending_dining'] ?? false);
        $meal = ['attending_dining' => $dining, 'starter_item_id' => null, 'main_item_id' => null, 'dessert_item_id' => null];

        if (! $dining) {
            return $meal;
        }

        foreach (self::COURSES as $course => $column) {
            $offered = $items->where('category', $course);
            $chosen = $person[$column] ?? null;

            if ($offered->isEmpty()) {
                continue;
            }

            if ($chosen === null || $chosen === '') {
                throw ValidationException::withMessages(["attendees.{$index}.{$column}" => "Please choose a {$course}."]);
            }

            if (! $offered->has((int) $chosen)) {
                throw ValidationException::withMessages(["attendees.{$index}.{$column}" => "That {$course} is not on the menu."]);
            }

            $meal[$column] = (int) $chosen;
        }

        return $meal;
    }

    /**
     * attending/tentative, unless the event is full: waitlisted if it has one, otherwise refused.
     */
    private function statusWithCapacity(Event $event, ?EventRegistration $existing, string $status, int $headcount): string
    {
        if ($event->capacity === null) {
            return $status;
        }

        $taken = EventAttendee::query()
            ->whereHas('registration', fn ($q) => $q->where('event_id', $event->id)->whereIn('status', EventRegistration::HOLDING_PLACE)->when($existing, fn ($q) => $q->where('id', '!=', $existing->id)))
            ->count();

        if ($taken + $headcount <= $event->capacity) {
            return $status;
        }

        if ($event->waitlist_enabled) {
            return 'waitlisted';
        }

        $left = max(0, $event->capacity - $taken);

        throw ValidationException::withMessages(['capacity' => $left === 0
            ? 'Sorry, this event is full.'
            : "Sorry, only {$left} ".Str::plural('place', $left).' left.']);
    }

    /**
     * Keep each ticket type's sold count in step with the bookings that hold a place.
     */
    private function syncTierCounts(Event $event): void
    {
        foreach ($event->ticketTiers()->get() as $tier) {
            $tier->update(['sold_quantity' => EventAttendee::query()
                ->where('ticket_tier_id', $tier->id)
                ->whereHas('registration', fn ($q) => $q->whereIn('status', EventRegistration::HOLDING_PLACE))
                ->count()]);
        }
    }

    /**
     * A guest's own address, if the booker gave one. Only guests have one; the booker is emailed at their account address.
     */
    private function guestEmail(mixed $value, bool $isGuest, int $index): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        if ($value === '' || ! $isGuest) {
            return null;
        }

        if (! filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 255) {
            throw ValidationException::withMessages(["attendees.{$index}.email" => 'Please enter a valid email address, or leave it blank.']);
        }

        return strtolower($value);
    }

    /**
     * Email each guest who has an address and has not been told yet, once, with their own place and meal.
     * The booker's payment details and private link are never included.
     */
    public function sendGuestConfirmations(EventRegistration $registration): int
    {
        $registration->loadMissing(['event.club', 'attendees.starter', 'attendees.main', 'attendees.dessert']);

        if (! in_array($registration->status, ['attending', 'tentative', 'waitlisted'], true)) {
            return 0;
        }

        $sent = 0;

        foreach ($registration->attendees->where('is_guest', true) as $guest) {
            if (! $guest->email || $guest->notified_at) {
                continue;
            }

            Mail::to($guest->email)->send(new EventGuestBookingMail($registration->event, $registration, $guest));
            $guest->forceFill(['notified_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }

    private function clean(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
