<?php

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use Illuminate\Support\Collection;

/**
 * One source for the guest list, the catering summary and the CSV, so the three never disagree.
 */
class GuestListBuilder
{
    /**
     * Everyone holding a place, one row per person.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function people(Event $event, string $sort = 'name'): Collection
    {
        $rows = $this->registrations($event, EventRegistration::HOLDING_PLACE)
            ->flatMap(fn (EventRegistration $registration) => $registration->attendees->map(fn (EventAttendee $attendee) => $this->row($registration, $attendee)))
            ->values();

        return ($sort === 'table'
            ? $rows->sortBy([['table_label', 'asc'], ['name', 'asc']])
            : $rows->sortBy(fn (array $row) => mb_strtolower($row['name'])))->values();
    }

    /**
     * People on the waiting list, oldest booking first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function waitlist(Event $event): Collection
    {
        return $this->registrations($event, ['waitlisted'])
            ->sortBy('id')
            ->flatMap(fn (EventRegistration $registration) => $registration->attendees->map(fn (EventAttendee $attendee) => $this->row($registration, $attendee)))
            ->values();
    }

    /**
     * What the kitchen needs: covers, dishes and dietary notes.
     *
     * @return array{covers: int, not_dining: int, total: int, courses: array<string, list<array{name: string, count: int}>>, dietary: list<array{name: string, notes: string}>, flags: array<string, int>}
     */
    public function catering(Event $event): array
    {
        $registrations = $this->registrations($event, EventRegistration::HOLDING_PLACE);
        $attendees = $registrations->flatMap(fn (EventRegistration $r) => $r->attendees);
        $diners = $attendees->where('attending_dining', true);

        $courses = [];

        foreach (['starter', 'main', 'dessert'] as $course) {
            $counts = [];

            foreach ($diners as $attendee) {
                $name = $attendee->mealSummary()[$course] ?? null;

                if ($name !== null) {
                    $counts[$name] = ($counts[$name] ?? 0) + 1;
                }
            }

            arsort($counts);
            $courses[$course] = collect($counts)->map(fn (int $count, string $name) => ['name' => $name, 'count' => $count])->values()->all();
        }

        $flags = ['vegetarian' => 0, 'vegan' => 0, 'gluten_free' => 0];

        foreach ($diners as $attendee) {
            foreach ([$attendee->starter, $attendee->main, $attendee->dessert] as $dish) {
                if ($dish?->is_vegetarian) {
                    $flags['vegetarian']++;
                }

                if ($dish?->is_vegan) {
                    $flags['vegan']++;
                }

                if ($dish?->is_gf) {
                    $flags['gluten_free']++;
                }
            }
        }

        return [
            'covers' => $diners->count(),
            'not_dining' => $attendees->where('attending_dining', false)->count(),
            'total' => $attendees->count(),
            'courses' => $courses,
            'dietary' => $attendees->filter(fn (EventAttendee $a) => filled($a->dietary_requirements))
                ->map(fn (EventAttendee $a) => ['name' => $a->name, 'notes' => (string) $a->dietary_requirements])
                ->sortBy('name')->values()->all(),
            'flags' => $flags,
        ];
    }

    /**
     * @param  list<string>  $statuses
     * @return Collection<int, EventRegistration>
     */
    private function registrations(Event $event, array $statuses): Collection
    {
        return EventRegistration::with(['attendees.ticketTier', 'attendees.starter', 'attendees.main', 'attendees.dessert', 'user'])
            ->where('event_id', $event->id)
            ->whereIn('status', $statuses)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(EventRegistration $registration, EventAttendee $attendee): array
    {
        return [
            'name' => $attendee->name,
            'is_guest' => $attendee->is_guest,
            'guest_of' => $attendee->is_guest ? $registration->contact_name : null,
            'email' => $registration->contact_email ?? $registration->user?->email,
            'ticket' => $attendee->ticketTier?->name,
            'dining' => $attendee->attending_dining,
            'meal' => $attendee->mealSummary(),
            'dietary' => $attendee->dietary_requirements,
            'table_label' => $attendee->table_label,
            'payment_status' => $registration->payment_status,
            'amount_paid' => (float) $registration->amount_paid,
            'checked_in' => $attendee->checked_in_at !== null,
            'status' => $registration->status,
        ];
    }
}
