<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Mail\NewsletterMail;
use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\Newsletter;
use App\Models\NewsletterType;
use App\Models\Post;
use App\Services\Newsletters\NewsletterAudience;
use App\Services\Newsletters\NewsletterSender;
use App\Support\Currencies;
use App\Support\RichTextSanitizer;
use App\Support\UploadRules;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterAdminController extends Controller
{
    /**
     * Display listing of newsletters for a club.
     */
    public function index(string $clubSlug, NewsletterSender $sender, NewsletterAudience $audience): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        NewsletterTypeAdminController::ensureDefaultTypes($club);

        $newsletters = Newsletter::where('club_id', $club->id)
            ->with(['newsletterType', 'club'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($newsletter) use ($sender, $audience) {
                $stats = $sender->stats($newsletter);
                $counts = $newsletter->status === 'sent' ? null : $audience->counts($newsletter);
                $total = $counts['total'] ?? ($stats['queued'] + $stats['sent'] + $stats['failed']);

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
                    'recipient_count' => $total,
                    'external_recipient_count' => $counts['visitors'] ?? 0,
                    'deliveries' => $stats,
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
                'price' => $e->price ? Currencies::format($e->price, $club) : 'Free',
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
    public function store(Request $request, string $clubSlug, NewsletterSender $sender): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'newsletter_type_id' => ['nullable', Rule::exists('newsletter_types', 'id')->where('club_id', $club->id)],
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

        if (! empty($validated['id']) && Newsletter::where('club_id', $club->id)->whereKey($validated['id'])->where('status', 'sent')->exists()) {
            throw ValidationException::withMessages(['status' => 'A newsletter that has been sent cannot be changed. Duplicate it to send something new.']);
        }

        $newsletter = Newsletter::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'newsletter_type_id' => $validated['newsletter_type_id'] ?? null,
                'subject' => $validated['subject'],
                'content' => $validated['content'],
                'attachments' => $attachments,
                'target_roles' => $validated['target_roles'],
                'status' => 'draft',
                'sent_at' => null,
            ]
        );

        if ($isSending) {
            $sender->send($newsletter);
        }

        $msg = $isSending ? 'Newsletter is being sent. Delivery is shown on the list.' : 'Newsletter draft saved.';

        return redirect()->route('admin.newsletters.index', ['clubSlug' => $club->slug])
            ->with('success', $msg);
    }

    /**
     * Broadcast an existing newsletter.
     */
    public function send(string $clubSlug, int $id, NewsletterSender $sender): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $newsletter = Newsletter::where('club_id', $club->id)->findOrFail($id);

        $sender->send($newsletter);

        return redirect()->back()->with('success', 'Newsletter is being sent. Delivery is shown on the list.');
    }

    /**
     * Send again to the people whose email failed.
     */
    public function retry(string $clubSlug, int $id, NewsletterSender $sender): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $newsletter = Newsletter::where('club_id', $club->id)->where('status', 'sent')->findOrFail($id);

        $count = $sender->retryFailed($newsletter);

        return redirect()->back()->with('success', $count === 0 ? 'Nothing had failed.' : "Retrying {$count} failed ".Str::plural('email', $count).'.');
    }

    /**
     * A copy of a newsletter as a new draft, for sending something similar again.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $newsletter = Newsletter::where('club_id', $club->id)->findOrFail($id);

        $copy = Newsletter::create([
            'club_id' => $club->id,
            'newsletter_type_id' => $newsletter->newsletter_type_id,
            'subject' => Str::limit('Copy of '.$newsletter->subject, 255, ''),
            'content' => $newsletter->content,
            'attachments' => $newsletter->attachments,
            'target_roles' => $newsletter->target_roles,
            'status' => 'draft',
        ]);

        return redirect()->route('admin.newsletters.edit', ['clubSlug' => $club->slug, 'id' => $copy->id])->with('success', 'Copied as a new draft.');
    }

    /**
     * Email the newsletter as it is being written (unsaved) to the person testing it.
     */
    public function test(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $mail = $this->draftMail($request, $club, $request->user()->name, isTest: true);

        Mail::to($request->user()->email)->send($mail);

        return redirect()->back()->with('success', 'A test has been sent to '.$request->user()->email.'.');
    }

    /**
     * The newsletter as it will look in an email, for the preview pane.
     */
    public function preview(Request $request, string $clubSlug): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $mail = $this->draftMail($request, $club, 'Alex Sample', unsubscribeUrl: '#');

        return response()->json(['html' => view('emails.newsletter', $mail->viewData())->render()]);
    }

    private function draftMail(Request $request, Club $club, string $recipientName, bool $isTest = false, ?string $unsubscribeUrl = null): NewsletterMail
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'content' => 'required|string|max:200000',
            'newsletter_type_id' => 'nullable|integer|max:4294967295',
        ]);

        $type = ! empty($validated['newsletter_type_id']) ? NewsletterType::where('club_id', $club->id)->find($validated['newsletter_type_id']) : null;

        $newsletter = new Newsletter(['club_id' => $club->id, 'newsletter_type_id' => $type?->id, 'subject' => $validated['subject'], 'attachments' => []]);
        $newsletter->setRelation('club', $club);
        $newsletter->setRelation('newsletterType', $type);

        return new NewsletterMail($newsletter, $recipientName, $unsubscribeUrl, null, $isTest, null, RichTextSanitizer::sanitize($validated['content']));
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
