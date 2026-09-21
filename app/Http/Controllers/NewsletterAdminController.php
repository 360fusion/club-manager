<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\Newsletter;
use App\Models\NewsletterType;
use App\Models\Post;
use App\Support\UploadRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterAdminController extends Controller
{
    /**
     * Display listing of newsletters for a club.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        NewsletterTypeAdminController::ensureDefaultTypes($club);

        $newsletters = Newsletter::where('club_id', $club->id)
            ->with('newsletterType')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($newsletter) use ($club) {
                $targetRoles = $newsletter->target_roles ?? ['member'];
                $internalCount = $club->users()
                    ->whereIn('club_user.role', $targetRoles)
                    ->where('club_user.status', 'active')
                    ->count();

                $externalCount = 0;
                if ($newsletter->newsletter_type_id) {
                    $externalCount = $club->newsletterSubscriptions()
                        ->where('newsletter_type_id', $newsletter->newsletter_type_id)
                        ->whereNull('user_id')
                        ->where('status', 'active')
                        ->count();
                }

                return [
                    'id' => $newsletter->id,
                    'newsletter_type_id' => $newsletter->newsletter_type_id,
                    'type_name' => $newsletter->newsletterType?->name ?? 'General Broadcast',
                    'type_color' => $newsletter->newsletterType?->color ?? '#4f46e5',
                    'type_icon' => $newsletter->newsletterType?->icon ?? '✉️',
                    'subject' => $newsletter->subject,
                    'content' => $newsletter->content,
                    'attachments' => $newsletter->attachments ?? [],
                    'target_roles' => $newsletter->target_roles ?? [],
                    'status' => $newsletter->status ?? 'draft',
                    'sent_at' => $newsletter->sent_at?->format('M d, Y @ H:i'),
                    'recipient_count' => $internalCount + $externalCount,
                    'external_recipient_count' => $externalCount,
                ];
            });

        $types = NewsletterType::where('club_id', $club->id)->get();

        return Inertia::render('Admin/Newsletters/Index', [
            'club' => $club,
            'newsletters' => $newsletters,
            'types' => $types,
        ]);
    }

    /**
     * Show form to create or edit a newsletter.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        NewsletterTypeAdminController::ensureDefaultTypes($club);

        $types = NewsletterType::where('club_id', $club->id)->get();
        $posts = Post::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->get();

        $pickerApprovedUpdates = ClubUpdate::where('club_id', $club->id)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get();

        $pickerMeetings = ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('meeting_date', '>=', Carbon::now())
            ->orderBy('meeting_date', 'asc')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'date' => Carbon::parse($m->meeting_date)->format('M d, Y g:i A'),
                'room' => $m->location ?? 'Main Lodge Room',
            ]);

        $pickerEvents = Event::where('club_id', $club->id)
            ->where('starts_at', '>=', Carbon::now())
            ->orderBy('starts_at', 'asc')
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'date' => Carbon::parse($e->starts_at)->format('M d, Y g:i A'),
                'price' => $e->price ? '£'.number_format($e->price, 2) : 'Free',
            ]);

        $newsletter = $id
            ? Newsletter::where('club_id', $club->id)->findOrFail($id)
            : new Newsletter([
                'club_id' => $club->id,
                'newsletter_type_id' => $types->first()?->id,
                'subject' => '',
                'content' => '',
                'attachments' => [],
                'target_roles' => ['member', 'admin', 'coach'],
                'status' => 'draft',
            ]);

        return Inertia::render('Admin/Newsletters/Form', [
            'club' => $club,
            'newsletter' => $newsletter,
            'types' => $types,
            'posts' => $posts,
            'pickerApprovedUpdates' => $pickerApprovedUpdates,
            'pickerMeetings' => $pickerMeetings,
            'pickerEvents' => $pickerEvents,
        ]);
    }

    /**
     * Store or update a newsletter draft.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'newsletter_type_id' => 'nullable|exists:newsletter_types,id',
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'target_roles' => 'required|array|max:10',
            'status' => 'required|in:draft,sent',
            'existing_attachments' => 'nullable|array|max:50',
            'new_attachments.*' => UploadRules::attachment(10240),
        ]);

        $rawAttachments = $validated['existing_attachments'] ?? [];
        $attachments = array_values(array_filter($rawAttachments, function ($att) {
            if (! is_array($att)) {
                return false;
            }
            if (! empty($att['isPendingFile'])) {
                return false;
            }
            if (isset($att['url']) && str_starts_with($att['url'], 'blob:')) {
                return false;
            }

            return true;
        }));

        if ($request->hasFile('new_attachments')) {
            foreach ($request->file('new_attachments') as $file) {
                if ($file && $file->isValid()) {
                    $name = $file->getClientOriginalName();
                    $bytes = $file->getSize();
                    $mime = $file->getClientMimeType();

                    $media = $club->addMedia($file)->toMediaCollection('newsletters');
                    $sizeFormatted = $bytes >= 1048576
                        ? round($bytes / 1048576, 1).' MB'
                        : round($bytes / 1024, 1).' KB';

                    $attachments[] = [
                        'name' => $name,
                        'url' => "/storage/{$media->id}/{$media->file_name}",
                        'size' => $sizeFormatted,
                        'mime_type' => $mime,
                    ];
                }
            }
        }

        $isSending = $validated['status'] === 'sent';

        Newsletter::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'newsletter_type_id' => $validated['newsletter_type_id'] ?? null,
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'attachments' => $attachments,
                'target_roles' => $validated['target_roles'],
                'status' => $validated['status'],
                'sent_at' => $isSending ? now() : null,
            ]
        );

        $msg = $isSending ? 'Newsletter broadcast sent successfully.' : 'Newsletter draft saved.';

        return redirect()->route('admin.newsletters.index', ['clubSlug' => $club->slug])
            ->with('success', $msg);
    }

    /**
     * Broadcast an existing newsletter.
     */
    public function send(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $newsletter = Newsletter::where('club_id', $club->id)->findOrFail($id);

        $newsletter->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Newsletter broadcast sent.');
    }

    /**
     * Delete a newsletter.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $newsletter = Newsletter::where('club_id', $club->id)->findOrFail($id);
        $newsletter->delete();

        return redirect()->back()->with('success', 'Newsletter deleted.');
    }
}
