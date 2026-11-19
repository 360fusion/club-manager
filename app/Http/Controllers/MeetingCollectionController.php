<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Services\Meetings\MeetingCollections;
use App\Support\Currencies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Alms and raffle": the counted money for a meeting, entered on a phone by the Charity Steward, Almoner, Treasurer or
 * Secretary (MeetingCollections::canRecord). In the member area, because those offices are often plain members.
 */
class MeetingCollectionController extends Controller
{
    public function show(Request $request, string $slug, int $id, MeetingCollections $collections): Response
    {
        [$club, $meeting] = $this->meeting($request, $slug, $id);
        $entries = $collections->forMeeting($meeting);

        $people = Member::where('club_id', $club->id)->active()->get();
        $signedIn = MeetingRsvp::where('meeting_id', $meeting->id)->whereNotNull('attended_at')->pluck('user_id')->all();
        $people = $people
            ->map(fn (Member $member) => ['id' => $member->id, 'name' => $member->formatted_rank_name, 'signed_in' => $member->user_id && in_array($member->user_id, $signedIn, true), 'sort' => $member->last_name.' '.$member->first_name])
            ->sortBy([['signed_in', 'desc'], ['sort', 'asc']])
            ->map(fn (array $person) => collect($person)->except('sort')->all())
            ->values();

        return Inertia::render('Member/Meetings/Collections', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'meeting' => ['id' => $meeting->id, 'title' => $meeting->title, 'date' => $meeting->meeting_date->format('l j F Y')],
            'entries' => collect($entries)->map(fn (?CharityCollection $row, string $type) => [
                'type' => $type,
                'label' => CollectionType::from($type) === CollectionType::Raffle ? 'Raffle' : 'Alms',
                'cash_amount' => $row ? (float) $row->cash_amount : null,
                'cheque_amount' => $row ? (float) $row->cheque_amount : null,
                'card_amount' => $row ? (float) $row->card_amount : null,
                'counted_by_member_id' => $row?->counted_by_member_id,
                'witnessed_by_member_id' => $row?->witnessed_by_member_id,
                'notes' => $row?->notes,
                'saved_at' => $row?->updated_at?->format('j M, H:i'),
                'banked' => (bool) $row?->bank_transaction_id,
            ])->values(),
            'total' => Currencies::format(collect($entries)->filter()->sum(fn (CharityCollection $row) => $row->total_amount), $club),
            'people' => $people,
        ]);
    }

    public function store(Request $request, string $slug, int $id, MeetingCollections $collections): RedirectResponse
    {
        [$club, $meeting] = $this->meeting($request, $slug, $id);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (CollectionType $type) => $type->value, MeetingCollections::TYPES))],
            'cash_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'cheque_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'card_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'counted_by_member_id' => ['nullable', 'integer'],
            'witnessed_by_member_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $type = CollectionType::from($validated['type']);
        $collections->save($club, $meeting, $type, $validated, $request->user());

        return back()->with('success', ($type === CollectionType::Raffle ? 'Raffle' : 'Alms').' saved.');
    }

    /**
     * @return array{0: Club, 1: Meeting}
     */
    private function meeting(Request $request, string $slug, int $id): array
    {
        $club = $this->findClub($slug);
        abort_unless(MeetingCollections::canRecord($request->user(), $club), 403, 'Only the Charity Steward, Almoner, Treasurer or Secretary can enter the alms and raffle.');

        return [$club, Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail()];
    }
}
