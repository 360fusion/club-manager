<?php

namespace App\Http\Controllers;

use App\Jobs\SendClubNotice;
use App\Models\ClubNotice;
use App\Services\Notices\NoticeAudience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A lodge officer sending a short notice to a group of members, to their phones and by email.
 * The route is guarded by the newsletters capability (see config/club_permissions.php).
 */
class NoticeAdminController extends Controller
{
    public function __construct(private readonly NoticeAudience $audience) {}

    public function index(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = $this->audience->nextMeeting($club);

        $notices = ClubNotice::where('club_id', $club->id)->with('sender:id,name')->latest()->orderByDesc('id')->limit(20)->get()
            ->map(fn (ClubNotice $notice) => [
                'id' => $notice->id,
                'title' => $notice->title,
                'body' => $notice->body,
                'audience' => $notice->audience,
                'recipient_count' => $notice->recipient_count,
                'push_count' => $notice->push_count,
                'email_count' => $notice->email_count,
                'sent_at' => $notice->sent_at?->toIso8601String(),
                'sender' => $notice->sender?->name,
            ]);

        return Inertia::render('Admin/Notices/Index', [
            'club' => $club->only(['id', 'name', 'slug']),
            'meeting' => $meeting ? ['id' => $meeting->id, 'title' => $meeting->title, 'date' => $meeting->meeting_date->format('j M')] : null,
            'counts' => $this->audience->counts($club, $meeting),
            'notices' => $notices,
        ]);
    }

    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validate([
            'audience' => ['required', Rule::in(ClubNotice::AUDIENCES)],
            'title' => ['required', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:1000'],
            'push' => ['boolean'],
            'email' => ['boolean'],
        ]);

        $push = $request->boolean('push');
        $email = $request->boolean('email');

        if (! $push && ! $email) {
            throw ValidationException::withMessages(['push' => 'Choose a phone notification, an email or both.']);
        }

        $meeting = in_array($validated['audience'], ['diners', 'not_replied'], true) ? $this->audience->nextMeeting($club) : null;

        if ($this->audience->users($club, $validated['audience'], $meeting)->isEmpty()) {
            throw ValidationException::withMessages(['audience' => 'Nobody is in this group yet.']);
        }

        $notice = ClubNotice::create([
            'club_id' => $club->id,
            'sent_by' => $request->user()->id,
            'audience' => $validated['audience'],
            'meeting_id' => $meeting?->id,
            // Plain text: the bell, the phone and the email all escape it when they show it.
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'push' => $push,
            'email' => $email,
        ]);

        SendClubNotice::dispatch($notice->id);

        return redirect()->route('admin.notices.index', ['clubSlug' => $club->slug])->with('success', 'Your notice is on its way.');
    }
}
