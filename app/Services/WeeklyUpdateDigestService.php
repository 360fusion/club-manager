<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Club;
use App\Models\ClubUpdate;
use App\Models\Event;
use App\Models\Newsletter;
use App\Models\NewsletterType;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WeeklyUpdateDigestService
{
    /**
     * Clean forwarded email text by stripping email headers and disclaimers.
     */
    public static function cleanForwardedText(string $text): string
    {
        // Strip common email headers
        $text = preg_replace('/From:.*?\n/i', '', $text);
        $text = preg_replace('/Sent:.*?\n/i', '', $text);
        $text = preg_replace('/To:.*?\n/i', '', $text);
        $text = preg_replace('/Subject:.*?\n/i', '', $text);
        $text = preg_replace('/---------- Forwarded message ---------/i', '', $text);
        $text = preg_replace('/-----Original Message-----/i', '', $text);
        
        return trim($text);
    }

    /**
     * Generate Google Calendar event link.
     */
    public static function generateGoogleCalendarUrl(string $title, Carbon $start, ?Carbon $end = null, string $location = '', string $details = ''): string
    {
        $end = $end ?? (clone $start)->addHours(2);
        
        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details' => $details,
            'location' => $location,
        ];

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    /**
     * Compile rich multi-section HTML content for the weekly digest email.
     */
    public function compileDigestHtml(Club $club, Collection $updates, array $options = []): string
    {
        $includeUpdates = $options['include_updates'] ?? true;
        $includeMeetings = $options['include_upcoming_meetings'] ?? true;
        $includeEvents = $options['include_upcoming_events'] ?? true;
        $includeNews = $options['include_news_posts'] ?? true;

        $html = '<div style="font-family: Arial, sans-serif; max-width: 680px; margin: 0 auto; color: #1e293b; background-color: #f8fafc; padding: 24px; rounded: 16px;">';
        
        // Header
        $html .= '<div style="text-align: center; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0;">';
        $html .= '<h1 style="color: #0f172a; margin: 0; font-size: 24px; font-weight: 800;">' . e($club->name) . ' Digest</h1>';
        $html .= '<p style="color: #64748b; font-size: 13px; margin-top: 4px;">Weekly Circular, Upcoming Meetings, Events & Member Updates</p>';
        $html .= '<p style="color: #94a3b8; font-size: 11px; margin-top: 2px;">Published on ' . Carbon::now()->format('F j, Y') . '</p>';
        $html .= '</div>';

        // 1. Approved Updates Section
        if ($includeUpdates && $updates->isNotEmpty()) {
            $html .= '<div style="margin-top: 24px;">';
            $html .= '<h2 style="font-size: 16px; color: #334155; font-weight: 700; text-transform: uppercase; tracking: 1px; margin-bottom: 12px; border-left: 4px solid #4f46e5; padding-left: 10px;">📜 Summonses & Member Bulletins</h2>';
            
            foreach ($updates as $update) {
                $categoryBadge = match($update->category) {
                    'summons' => '📜 Visiting Summons',
                    'provincial' => '🏛️ Provincial News',
                    'event_notice' => '🎟️ Event Notice',
                    'charity' => '❤️ Charity Notice',
                    default => '📣 General Announcement'
                };

                $html .= '<div style="background: #ffffff; padding: 16px; border-radius: 12px; margin-bottom: 12px; border: 1px solid #e2e8f0; shadow: 0 1px 2px rgba(0,0,0,0.05);">';
                $html .= '<span style="display: inline-block; font-size: 10px; font-weight: 700; background: #e0e7ff; color: #3730a3; padding: 3px 8px; border-radius: 999px; margin-bottom: 8px;">' . e($categoryBadge) . '</span>';
                $html .= '<h3 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a;">' . e($update->title) . '</h3>';
                
                if ($update->cover_image_url) {
                    $html .= '<img src="' . e($update->cover_image_url) . '" style="max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0;" />';
                }

                if ($update->summary) {
                    $html .= '<div style="font-size: 13px; color: #475569; line-height: 1.5; margin-bottom: 10px;">' . $update->summary . '</div>';
                }

                // Downloadable attachments
                if (!empty($update->attachments) && is_array($update->attachments)) {
                    $html .= '<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed #cbd5e1;">';
                    foreach ($update->attachments as $att) {
                        $attName = e($att['name'] ?? 'Download File');
                        $attUrl = e($att['url'] ?? '#');
                        $html .= '<a href="' . $attUrl . '" target="_blank" style="display: inline-block; font-size: 12px; font-weight: 700; color: #ffffff; background-color: #4f46e5; padding: 6px 14px; border-radius: 8px; text-decoration: none; margin-right: 8px; margin-bottom: 6px;">📥 Download ' . $attName . '</a>';
                    }
                    $html .= '</div>';
                }

                $html .= '</div>';
            }
            $html .= '</div>';
        }

        // 2. Upcoming Meetings (Next 30 Days)
        if ($includeMeetings) {
            $upcomingMeetings = ClubCommitteeMeeting::where('club_id', $club->id)
                ->where('meeting_date', '>=', Carbon::now())
                ->where('meeting_date', '<=', Carbon::now()->addDays(30))
                ->orderBy('meeting_date', 'asc')
                ->get();

            if ($upcomingMeetings->isNotEmpty()) {
                $html .= '<div style="margin-top: 24px;">';
                $html .= '<h2 style="font-size: 16px; color: #334155; font-weight: 700; text-transform: uppercase; tracking: 1px; margin-bottom: 12px; border-left: 4px solid #059669; padding-left: 10px;">📅 Upcoming Lodge & Committee Meetings</h2>';
                
                foreach ($upcomingMeetings as $meeting) {
                    $mDate = Carbon::parse($meeting->meeting_date);
                    $gCalUrl = self::generateGoogleCalendarUrl($meeting->title, $mDate, null, $meeting->location ?? 'Lodge Room', 'Lodge Committee Meeting');

                    $html .= '<div style="background: #ffffff; padding: 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">';
                    $html .= '<div style="display: flex; justify-content: space-between; align-items: center;">';
                    $html .= '<div>';
                    $html .= '<h4 style="margin: 0; font-size: 14px; color: #0f172a;">' . e($meeting->title) . '</h4>';
                    $html .= '<p style="margin: 2px 0 0 0; font-size: 12px; color: #64748b;">🗓️ ' . $mDate->format('l, F j, Y \a\t g:i A') . ' • 📍 ' . e($meeting->location ?? 'Main Lodge Room') . '</p>';
                    $html .= '</div>';
                    $html .= '<div>';
                    $html .= '<a href="' . e($gCalUrl) . '" target="_blank" style="font-size: 11px; font-weight: 700; color: #059669; background: #ecfdf5; padding: 5px 10px; border-radius: 6px; text-decoration: none;">+ Add to Calendar</a>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // 3. Upcoming Events & Dining (Next 30 Days)
        if ($includeEvents) {
            $upcomingEvents = Event::where('club_id', $club->id)
                ->where('starts_at', '>=', Carbon::now())
                ->where('starts_at', '<=', Carbon::now()->addDays(30))
                ->orderBy('starts_at', 'asc')
                ->get();

            if ($upcomingEvents->isNotEmpty()) {
                $html .= '<div style="margin-top: 24px;">';
                $html .= '<h2 style="font-size: 16px; color: #334155; font-weight: 700; text-transform: uppercase; tracking: 1px; margin-bottom: 12px; border-left: 4px solid #d97706; padding-left: 10px;">🎟️ Upcoming Events & Dining</h2>';
                
                foreach ($upcomingEvents as $evt) {
                    $eDate = Carbon::parse($evt->starts_at);
                    $gCalUrl = self::generateGoogleCalendarUrl($evt->title, $eDate, null, $evt->location ?? 'Clubhouse', $evt->description ?? '');

                    $html .= '<div style="background: #ffffff; padding: 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">';
                    $html .= '<h4 style="margin: 0; font-size: 14px; color: #0f172a;">' . e($evt->title) . '</h4>';
                    $html .= '<p style="margin: 2px 0 6px 0; font-size: 12px; color: #64748b;">🗓️ ' . $eDate->format('F j, Y \a\t g:i A') . ($evt->location ? ' • 📍 ' . e($evt->location) : '') . '</p>';
                    
                    if ($evt->price) {
                        $html .= '<span style="font-size: 11px; font-weight: 700; color: #b45309; background: #fef3c7; padding: 2px 8px; border-radius: 4px; margin-right: 6px;">💰 Ticket: £' . number_format($evt->price, 2) . '</span>';
                    }

                    $html .= '<div style="margin-top: 8px;">';
                    $html .= '<a href="' . e($gCalUrl) . '" target="_blank" style="font-size: 11px; font-weight: 700; color: #d97706; background: #fffbeb; padding: 4px 8px; border-radius: 6px; text-decoration: none; margin-right: 6px;">+ Calendar</a>';
                    $html .= '<a href="' . url('/clubs/' . $club->slug . '/portal/events') . '" style="font-size: 11px; font-weight: 700; color: #ffffff; background: #d97706; padding: 4px 10px; border-radius: 6px; text-decoration: none;">Book / RSVP →</a>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // 4. Featured News Articles
        if ($includeNews) {
            $recentPosts = Post::where('club_id', $club->id)
                ->where('status', 'published')
                ->where('created_at', '>=', Carbon::now()->subDays(14))
                ->orderBy('created_at', 'desc')
                ->take(3)
                ->get();

            if ($recentPosts->isNotEmpty()) {
                $html .= '<div style="margin-top: 24px;">';
                $html .= '<h2 style="font-size: 16px; color: #334155; font-weight: 700; text-transform: uppercase; tracking: 1px; margin-bottom: 12px; border-left: 4px solid #0284c7; padding-left: 10px;">📰 Latest News Articles</h2>';
                
                foreach ($recentPosts as $post) {
                    $html .= '<div style="background: #ffffff; padding: 14px; border-radius: 12px; margin-bottom: 10px; border: 1px solid #e2e8f0;">';
                    $html .= '<h4 style="margin: 0; font-size: 14px; color: #0f172a;">' . e($post->title) . '</h4>';
                    if ($post->excerpt) {
                        $html .= '<p style="margin: 4px 0 6px 0; font-size: 12px; color: #64748b;">' . e($post->excerpt) . '</p>';
                    }
                    $html .= '<a href="' . url('/clubs/' . $club->slug . '/portal/news/' . $post->id) . '" style="font-size: 11px; font-weight: 700; color: #0284c7; text-decoration: none;">Read Article →</a>';
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
        }

        // Footer
        $html .= '<div style="margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 11px; color: #94a3b8;">';
        $html .= '<p style="margin: 0;">Sent by <strong>' . e($club->name) . '</strong> via ClubManager Governance Portal.</p>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Dispatch the weekly digest email broadcast.
     */
    public function dispatchWeeklyDigest(Club $club, ?array $selectedUpdateIds = null, ?array $recipientRoles = null): Newsletter
    {
        // 1. Fetch approved updates
        $updatesQuery = ClubUpdate::where('club_id', $club->id)
            ->where('status', 'approved');

        if (!empty($selectedUpdateIds)) {
            $updatesQuery->whereIn('id', $selectedUpdateIds);
        }

        $updates = $updatesQuery->orderBy('is_important', 'desc')->orderBy('created_at', 'desc')->get();

        // 2. Resolve newsletter type
        $digestType = NewsletterType::where('club_id', $club->id)
            ->where(function ($q) {
                $q->where('slug', 'weekly-digest')
                  ->orWhere('is_automated_digest', true)
                  ->orWhere('name', 'LIKE', '%Digest%');
            })
            ->first();

        if (!$digestType) {
            $digestType = NewsletterType::create([
                'club_id' => $club->id,
                'name' => 'Weekly Digest & Circular',
                'slug' => 'weekly-digest',
                'description' => 'Automated weekly digest combining summonses, upcoming meetings, events, and lodge bulletins.',
                'color' => '#4f46e5',
                'icon' => '📰',
                'is_external_subscribable' => true,
                'is_mandatory' => true,
                'is_automated_digest' => true,
                'digest_frequency' => 'weekly',
                'digest_send_day' => 'friday',
                'digest_send_time' => '09:00',
                'default_roles' => ['member', 'admin'],
            ]);
        }

        // 3. Compile HTML
        $htmlContent = $this->compileDigestHtml($club, $updates, [
            'include_updates' => $digestType->include_updates,
            'include_upcoming_meetings' => $digestType->include_upcoming_meetings,
            'include_upcoming_events' => $digestType->include_upcoming_events,
            'include_news_posts' => $digestType->include_news_posts,
        ]);

        // Collect attachments from updates into newsletter attachments
        $newsletterAttachments = [];
        foreach ($updates as $update) {
            if (!empty($update->attachments) && is_array($update->attachments)) {
                foreach ($update->attachments as $att) {
                    $newsletterAttachments[] = [
                        'name' => $att['name'] ?? 'Attached Document',
                        'url' => $att['url'] ?? '#',
                        'size' => $att['size'] ?? '',
                        'mime_type' => $att['mime_type'] ?? 'application/pdf',
                    ];
                }
            }
        }

        // 4. Create Newsletter
        $newsletter = Newsletter::create([
            'club_id' => $club->id,
            'newsletter_type_id' => $digestType->id,
            'subject' => $club->name . ' - Weekly Digest (' . Carbon::now()->format('M d, Y') . ')',
            'content' => $htmlContent,
            'attachments' => $newsletterAttachments,
            'target_roles' => $recipientRoles ?? $digestType->default_roles ?? ['member', 'admin'],
            'status' => 'sent',
            'sent_at' => Carbon::now(),
        ]);

        // 5. Update used ClubUpdate records from 'approved' to 'sent'
        foreach ($updates as $update) {
            $update->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(),
                'newsletter_id' => $newsletter->id,
            ]);
        }

        return $newsletter;
    }
}
