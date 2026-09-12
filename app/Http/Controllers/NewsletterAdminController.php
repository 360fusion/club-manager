<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Newsletter;
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
        $newsletters = Newsletter::where('club_id', $club->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($newsletter) use ($club) {
                // Calculate estimated recipients based on target roles
                $targetRoles = $newsletter->target_roles ?? ['member'];
                $recipientCount = $club->users()
                    ->whereIn('club_user.role', $targetRoles)
                    ->where('club_user.status', 'active')
                    ->count();

                return [
                    'id' => $newsletter->id,
                    'subject' => $newsletter->subject,
                    'content' => $newsletter->content,
                    'target_roles' => $newsletter->target_roles ?? [],
                    'status' => $newsletter->status ?? 'draft',
                    'sent_at' => $newsletter->sent_at?->format('M d, Y @ H:i'),
                    'recipient_count' => $recipientCount,
                ];
            });

        return Inertia::render('Admin/Newsletters/Index', [
            'club' => $club,
            'newsletters' => $newsletters,
        ]);
    }

    /**
     * Show form to create or edit a newsletter.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $newsletter = $id
            ? Newsletter::where('club_id', $club->id)->findOrFail($id)
            : new Newsletter([
                'club_id' => $club->id,
                'subject' => '',
                'content' => '',
                'target_roles' => ['member', 'admin', 'coach'],
                'status' => 'draft',
            ]);

        return Inertia::render('Admin/Newsletters/Form', [
            'club' => $club,
            'newsletter' => $newsletter,
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
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'target_roles' => 'required|array',
            'status' => 'required|in:draft,sent',
        ]);

        $isSending = $validated['status'] === 'sent';

        $newsletter = Newsletter::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'subject' => $validated['subject'],
                'content' => $validated['content'],
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
