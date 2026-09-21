<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\Post;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Services\WeeklyUpdateDigestService;
use App\Support\ImageDownscaler;
use App\Support\UploadRules;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateAdminController extends Controller
{
    /**
     * Display listing of club updates and queue status.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $updates = ClubUpdate::where('club_id', $club->id)
            ->with(['author', 'newsletter'])
            ->orderByRaw("CASE status WHEN 'draft' THEN 1 WHEN 'approved' THEN 2 WHEN 'sent' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->get();

        $counts = [
            'all' => $updates->count(),
            'draft' => $updates->where('status', 'draft')->count(),
            'approved' => $updates->where('status', 'approved')->count(),
            'sent' => $updates->where('status', 'sent')->count(),
        ];

        // Selectable upcoming calendar items for the Content Picker Modal
        $upcomingMeetings = ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('meeting_date', '>=', Carbon::now())
            ->orderBy('meeting_date', 'asc')
            ->take(10)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'date' => Carbon::parse($m->meeting_date)->format('M d, Y g:i A'),
                'room' => $m->location ?? 'Main Lodge Room',
            ]);

        $upcomingEvents = Event::where('club_id', $club->id)
            ->where('starts_at', '>=', Carbon::now())
            ->orderBy('starts_at', 'asc')
            ->take(10)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'date' => Carbon::parse($e->starts_at)->format('M d, Y g:i A'),
                'price' => $e->price ? '£'.number_format($e->price, 2) : 'Free',
            ]);

        $recentNews = Post::where('club_id', $club->id)
            ->where('status', 'published')
            ->orderByDesc('created_at')
            ->take(10)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'date' => $p->published_at ? Carbon::parse($p->published_at)->format('M d, Y') : $p->created_at->format('M d, Y'),
                'excerpt' => $p->excerpt ?? '',
            ]);

        return Inertia::render('Admin/Updates/Index', [
            'club' => $club,
            'updates' => $updates,
            'counts' => $counts,
            'pickerMeetings' => $upcomingMeetings,
            'pickerEvents' => $upcomingEvents,
            'pickerNews' => $recentNews,
        ]);
    }

    /**
     * Store or update a ClubUpdate item.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'category' => 'required|string|in:summons,provincial,event_notice,general,charity',
            'summary' => 'nullable|string|max:10000',
            'cover_image_url' => 'nullable|string|max:1000',
            'cover_image_file' => UploadRules::image(8192),
            'attachments' => 'nullable|array|max:50',
            'attachment_files.*' => UploadRules::attachment(20480),
            'status' => 'required|string|in:draft,approved,sent,archived',
            'is_important' => 'nullable|boolean',
            'clean_text' => 'nullable|boolean',
        ]);

        $coverImageUrl = $validated['cover_image_url'] ?? null;
        if ($request->hasFile('cover_image_file') && $request->file('cover_image_file')->isValid()) {
            ImageDownscaler::apply($request->file('cover_image_file'));
            $media = $club->addMediaFromRequest('cover_image_file')->toMediaCollection('updates');
            $coverImageUrl = "/storage/{$media->id}/{$media->file_name}";
        }

        $rawAttachments = $validated['attachments'] ?? [];
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

        $collectionName = ($validated['category'] === 'summons') ? 'summons' : 'updates';

        if ($request->hasFile('attachment_files')) {
            foreach ($request->file('attachment_files') as $file) {
                if ($file && $file->isValid()) {
                    $name = $file->getClientOriginalName();
                    $bytes = $file->getSize();
                    $mime = $file->getClientMimeType();

                    $media = $club->addMedia($file)->toMediaCollection($collectionName);
                    $attachments[] = [
                        'name' => $name,
                        'url' => "/storage/{$media->id}/{$media->file_name}",
                        'size' => $bytes,
                        'mime_type' => $mime,
                    ];
                }
            }
        }

        $summary = $validated['summary'] ?? '';
        if (! empty($validated['clean_text']) && $summary) {
            $summary = WeeklyUpdateDigestService::cleanForwardedText($summary);
        }

        $approvedAt = null;
        if ($validated['status'] === 'approved') {
            $approvedAt = now();
        }

        $previousStatus = ! empty($validated['id']) ? ClubUpdate::where('club_id', $club->id)->whereKey($validated['id'])->value('status') : null;

        $update = ClubUpdate::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'author_id' => auth()->id(),
                'title' => $validated['title'],
                'category' => $validated['category'],
                'summary' => $summary,
                'cover_image_url' => $coverImageUrl,
                'attachments' => $attachments,
                'status' => $validated['status'],
                'is_important' => $validated['is_important'] ?? false,
                'approved_at' => $approvedAt,
            ]
        );

        $this->notifyIfImportant($update, $club, $previousStatus);

        if ($validated['category'] === 'summons' && ! empty($attachments)) {
            foreach ($attachments as $att) {
                if (isset($att['url']) && preg_match('/\/storage\/(\d+)\//', $att['url'], $m)) {
                    $mediaId = (int) $m[1];
                    $mObj = $club->media()->find($mediaId);
                    if ($mObj && $mObj->collection_name !== 'summons') {
                        $mObj->collection_name = 'summons';
                        $mObj->save();
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Update item saved successfully.');
    }

    /**
     * Update status (e.g. approve, unapprove, archive).
     */
    public function updateStatus(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $update = ClubUpdate::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:draft,approved,sent,archived',
        ]);

        $updateData = ['status' => $validated['status']];
        if ($validated['status'] === 'approved') {
            $updateData['approved_at'] = now();
        }

        $previousStatus = $update->status;
        $update->update($updateData);

        $this->notifyIfImportant($update, $club, $previousStatus);

        return redirect()->back()->with('success', 'Item status updated to '.ucfirst($validated['status']).'.');
    }

    /**
     * Delete an update item.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $update = ClubUpdate::where('club_id', $club->id)->findOrFail($id);
        $update->delete();

        return redirect()->back()->with('success', 'Update item deleted.');
    }

    /**
     * Immediately compile and dispatch weekly digest newsletter.
     */
    public function triggerDigest(Request $request, string $clubSlug, WeeklyUpdateDigestService $digestService): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $selectedIds = $request->input('selected_ids');

        $approvedCount = ClubUpdate::where('club_id', $club->id)
            ->when(! empty($selectedIds), fn ($q) => $q->whereIn('id', $selectedIds))
            ->where('status', 'approved')
            ->count();

        if ($approvedCount === 0 && ! $request->boolean('force')) {
            return redirect()->back()->with('error', 'No approved update items ready to send.');
        }

        $newsletter = $digestService->dispatchWeeklyDigest($club, $selectedIds);

        return redirect()->back()->with('success', "Weekly Digest dispatched to members! Newsletter #{$newsletter->id} generated.");
    }

    /**
     * Important notices notify members once, when they first become approved or sent.
     */
    private function notifyIfImportant(ClubUpdate $update, Club $club, ?string $previousStatus): void
    {
        if ($update->is_important && in_array($update->status, ['approved', 'sent'], true) && ! in_array($previousStatus, ['approved', 'sent'], true)) {
            app(ClubNotifier::class)->toMembers($club, ClubNotification::notice($update, $club), auth()->user());
        }
    }
}
