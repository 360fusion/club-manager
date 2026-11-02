<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;

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

    // shortcut: no editable template row yet, so the built-in wording below is always used; add a `meeting_summons` template when a lodge needs to reword it.
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
                'meeting_time' => substr((string) $this->meeting->starts_at, 0, 5),
                'venue' => (string) $this->meeting->venue,
                'rsvp_url' => route('summons.rsvp.show', ['token' => $this->rsvpToken]),
            ],
            'html' => [],
        ];
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
