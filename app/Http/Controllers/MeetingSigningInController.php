<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingRsvpGuest;
use App\Models\MeetingWalkIn;
use App\Services\Meetings\SigningInBook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signing-in book at the door of a meeting, and the attendance it builds up. Under /admin/meetings, so it needs the
 * meetings capability (the Secretary, Director of Ceremonies or whoever the lodge gives it to).
 */
class MeetingSigningInController extends Controller
{
    public function show(string $clubSlug, int $id, SigningInBook $book): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        return Inertia::render('Admin/Meetings/SigningIn', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'meeting' => ['id' => $meeting->id, 'title' => $meeting->title, 'meeting_date' => $meeting->meeting_date->format('l j F Y')],
            'book' => $book->book($meeting),
        ]);
    }

    /**
     * Tick someone in or out: a member or registered visitor by account, or a member's guest.
     */
    public function update(Request $request, string $clubSlug, int $id, SigningInBook $book): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $validated = $request->validate([
            'kind' => 'required|in:member,guest',
            'id' => 'required|integer',
            'present' => 'required|boolean',
        ]);

        if ($validated['kind'] === 'member') {
            $person = $club->users()->where('users.id', $validated['id'])->wherePivot('status', 'active')->firstOrFail();
            $book->setMember($meeting, $person, $validated['present'], $request->user());
        } else {
            $guest = MeetingRsvpGuest::where('id', $validated['id'])->whereHas('rsvp', fn ($q) => $q->where('meeting_id', $meeting->id))->firstOrFail();
            $book->setGuest($guest, $validated['present']);
        }

        return back();
    }

    public function storeVisitor(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'rank' => 'nullable|string|max:50',
            'lodge' => 'nullable|string|max:150',
        ]);

        MeetingWalkIn::create($validated + ['meeting_id' => $meeting->id, 'signed_in_by' => $request->user()->id]);

        return back()->with('success', $validated['name'].' signed in.');
    }

    public function destroyVisitor(string $clubSlug, int $id, int $walkIn): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        MeetingWalkIn::where('meeting_id', $meeting->id)->where('id', $walkIn)->firstOrFail()->delete();

        return back();
    }

    /**
     * Each member's attendance over the last year, at meetings where the book was used, longest absent first.
     */
    public function attendance(string $clubSlug, SigningInBook $book): Response
    {
        $club = $this->findClub($clubSlug);

        return Inertia::render('Admin/Meetings/Attendance', [
            'club' => ['slug' => $club->slug, 'name' => $club->name],
            'attendance' => $book->attendance($club, now()->subYear()),
        ]);
    }
}
