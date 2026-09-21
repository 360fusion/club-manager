<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormSubmittedMail;
use App\Models\Club;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class PublicSiteController extends Controller
{
    /**
     * Render a club's public website page.
     */
    public function showPage(string $clubSlug, ?string $pageSlug = null): Response
    {
        $club = Club::where('slug', $clubSlug)
            ->with(['clubType', 'membershipPlans', 'donations.contributions'])
            ->firstOrFail();

        $club->ensureDefaultPages();

        $viewer = Auth::user();

        // Determine target page (Homepage or specific page slug)
        $query = Page::where('club_id', $club->id)->where('is_published', true);

        if ($pageSlug) {
            $page = $query->where('slug', $pageSlug)->firstOrFail();
        } else {
            $page = $query->where('is_homepage', true)->first()
                ?? $query->orderBy('sort_order')->firstOrFail();
        }

        // Navigation links (All published pages marked show_in_navigation)
        $navigationPages = Page::where('club_id', $club->id)
            ->where('is_published', true)
            ->where('show_in_navigation', true)
            ->orderBy('sort_order')
            ->get(['id', 'title', 'slug', 'is_homepage']);

        $validThemes = ['classic', 'obsidian', 'masonic', 'minimal', 'vibrant', 'light_navy', 'executive_light', 'masonic_light', 'warm_light'];
        $rawPreviewTheme = request('preview_theme');
        $previewTheme = in_array($rawPreviewTheme, $validThemes) ? $rawPreviewTheme : null;

        return Inertia::render('Public/Site', [
            'previewTheme' => $previewTheme,
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'type_name' => $club->clubType->name,
                'tagline' => $club->settings['tagline'] ?? '',
                'primary_color' => $club->settings['primary_color'] ?? '#0369a1',
                'contact_email' => $club->settings['contact_email'] ?? $club->email,
                'meeting_formula' => $club->settings['meeting_formula'] ?? '',
                'address' => $club->settings['address'] ?? '',
            ],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'blocks' => $page->blocks ?? [],
                'is_homepage' => $page->is_homepage,
                'is_members_only' => $page->is_members_only,
            ],
            'navigation' => $navigationPages,
            'latestPosts' => $club->posts()->with('author')->published()->visibleTo($viewer)->take(24)->get()->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => $p->excerpt,
                'content' => $p->content,
                'cover_image_url' => $p->cover_image_url ?: ($p->getFirstMediaUrl('cover') ?: null),
                'author_name' => $p->author?->name ?? 'Club Admin',
                'published_at' => ($p->published_at ?? $p->created_at)?->format('M d, Y'),
            ]),
            'upcomingEvents' => $club->events()->visibleTo($viewer)->limit(3)->get()->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'slug' => $e->slug,
                'location' => $e->location,
                'starts_at' => $e->starts_at?->format('M d, Y @ H:i'),
                'is_recurring' => $e->is_recurring,
                'requires_payment' => $e->requires_payment,
                'price' => number_format($e->price, 2),
                'has_dining' => $e->has_dining,
                'dining_price' => number_format($e->dining_price, 2),
            ]),
            'membershipPlans' => $club->membershipPlans->map(fn ($mp) => [
                'id' => $mp->id,
                'name' => $mp->name,
                'description' => $mp->description,
                'price' => number_format($mp->price, 2),
                'billing_period' => $mp->billing_period,
            ]),
            'donations' => $club->donations->map(fn ($d) => [
                'id' => $d->id,
                'campaign_name' => $d->campaign_name,
                'target_amount' => number_format($d->target_amount, 2),
                'current_amount' => number_format($d->current_amount, 2),
                'percentage' => $d->percentage,
                'description' => $d->description,
                'status' => $d->status,
                'contributions_count' => $d->contributions->count(),
            ]),
        ]);
    }

    /**
     * Handle contact form submission from public website.
     */
    public function submitContactForm(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'message' => 'nullable|string|max:5000',
            'recipient_email' => 'nullable|email',
            'cc_emails' => 'nullable|string',
            'success_message' => 'nullable|string',
        ]);

        // Recipients are never taken on trust from the request. A form may only send to the
        // club's own contact addresses, to addresses on the club's own email domain, or to an
        // active member. Free-mail domains are never treated as "the club's domain".
        $clubEmails = collect([$club->settings['contact_email'] ?? null, $club->email])->filter()->map(fn ($email) => strtolower((string) $email));
        $freeMail = ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'hotmail.co.uk', 'live.com', 'yahoo.com', 'yahoo.co.uk', 'icloud.com', 'me.com', 'aol.com', 'btinternet.com', 'sky.com', 'proton.me', 'protonmail.com'];
        $clubDomains = $clubEmails->map(fn ($email) => substr((string) strrchr($email, '@'), 1))->filter()->reject(fn ($domain) => in_array($domain, $freeMail, true))->unique();
        $memberEmails = $club->users()->wherePivot('status', 'active')->pluck('users.email')->map(fn ($email) => strtolower((string) $email));

        $isAllowed = fn (string $email) => $clubEmails->contains(strtolower($email))
            || $memberEmails->contains(strtolower($email))
            || $clubDomains->contains(substr((string) strrchr(strtolower($email), '@'), 1));

        $recipientEmail = $club->settings['contact_email'] ?? $club->email;
        if (! empty($validated['recipient_email']) && $isAllowed($validated['recipient_email'])) {
            $recipientEmail = $validated['recipient_email'];
        }

        $ccEmails = [];
        if (! empty($validated['cc_emails'])) {
            foreach (preg_split('/[\s,]+/', $validated['cc_emails']) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL) && $isAllowed($candidate)) {
                    $ccEmails[] = $candidate;
                }
            }
        }

        $mailable = new ContactFormSubmittedMail(
            club: $club,
            senderName: $validated['name'] ?? 'Anonymous Visitor',
            senderEmail: $validated['email'],
            senderPhone: $validated['phone'] ?? null,
            messageContent: $validated['message'] ?? ''
        );

        $mail = Mail::to($recipientEmail);
        if (! empty($ccEmails)) {
            $mail->cc($ccEmails);
        }
        $mail->send($mailable);

        $msg = $validated['success_message'] ?? 'Thank you! Your message has been sent successfully.';

        return back()->with('success', $msg);
    }
}
