<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Mail\Mailables\Content;

/**
 * Tells a member a summons has been published and gives them their private, passwordless RSVP link.
 */
class MeetingSummonsMail extends EventTemplatedMail
{
    public function __construct(
        public Club $club,
        public Meeting $meeting,
        public User $user,
        public string $rsvpToken,
    ) {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return 'meeting_summons';
    }

    protected function values(): array
    {
        return [
            'text' => [
                'member_name' => $this->user->name,
                'club_name' => $this->club->name,
                'meeting_title' => $this->meeting->title,
                'meeting_date' => $this->meeting->meeting_date->format('l j F Y'),
                'meeting_time' => $this->meeting->starts_at ?? '',
                'starts_at' => $this->meeting->starts_at ?? '',
                'venue' => (string) $this->meeting->venue,
                'rsvp_url' => route('summons.rsvp.show', ['token' => $this->rsvpToken]),
            ],
            'html' => [],
        ];
    }

    /**
     * Whatever the wording, the member's private reply link is always in the email: it is added when the template lacks it.
     */
    public function content(): Content
    {
        $body = $this->filled()['body'];
        $url = $this->values()['text']['rsvp_url'];

        if (! str_contains($body, $url)) {
            $body .= '<p><a href="'.e($url).'">Reply to the summons</a>: say whether you are attending, dining, or sending apologies. This link is private to you and works until the day of the meeting.</p>';
        }

        return new Content(html: 'emails.templated', with: ['bodyHtml' => $body]);
    }

    protected function fallbackSubject(): string
    {
        return "Summons: {$this->meeting->title}, {$this->values()['text']['meeting_date']}";
    }

    protected function fallbackBody(): string
    {
        $v = $this->values()['text'];

        return '<p>Dear '.e($v['member_name']).',</p>'
            .'<p>You are summoned to <strong>'.e($v['meeting_title']).'</strong> of '.e($v['club_name']).'.</p>'
            .'<p>'.e($v['meeting_date']).' at '.e($v['meeting_time']).'<br>'.e($v['venue']).'</p>'
            .'<p><a href="'.e($v['rsvp_url']).'">Reply to the summons</a>: say whether you are attending, dining, or sending apologies. This link is private to you and works until the day of the meeting.</p>';
    }
}
