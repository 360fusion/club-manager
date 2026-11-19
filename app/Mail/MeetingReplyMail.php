<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use App\Support\Currencies;

/**
 * One step of a meeting's reply schedule (app:send-meeting-replies): asking a member who has not replied, the last call
 * before the dining deadline, or the reminder to someone coming. Each answer is a private link to their reply page with
 * that answer picked; they confirm it there, so a mail scanner opening links cannot answer for them.
 */
class MeetingReplyMail extends EventTemplatedMail
{
    public const ASK = 'ask';

    public const LAST_CALL = 'last_call';

    public const CONFIRM = 'confirm';

    /**
     * @param  array<string, string>  $links  answer => private link, plus 'change' => the page itself
     */
    public function __construct(
        public Club $club,
        public Meeting $meeting,
        public User $user,
        public MeetingRsvp $rsvp,
        public string $kind,
        public array $links,
    ) {
        $this->onQueue('transactional');
    }

    protected function club(): Club
    {
        return $this->club;
    }

    protected function templateKey(): string
    {
        return 'meeting_reply_'.$this->kind;
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
                'venue' => (string) $this->meeting->venue,
                'reply_url' => $this->links['change'],
            ],
            'html' => [],
        ];
    }

    protected function fallbackSubject(): string
    {
        $v = $this->values()['text'];

        return match ($this->kind) {
            self::ASK => "Will you be there? {$v['meeting_title']}, {$v['meeting_date']}",
            self::LAST_CALL => "Last call for dining: {$v['meeting_title']}",
            default => "Reminder: {$v['meeting_title']}, {$v['meeting_date']}",
        };
    }

    protected function fallbackBody(): string
    {
        $v = $this->values()['text'];
        $when = '<p><strong>'.e($v['meeting_title']).'</strong>, '.e($v['meeting_date']).($v['meeting_time'] ? ' at '.e($v['meeting_time']) : '').($v['venue'] ? '<br>'.e($v['venue']) : '').'</p>';

        $intro = match ($this->kind) {
            self::ASK => '<p>Please let the Secretary know whether you will be at the next meeting, so the festive board can be booked.</p>',
            self::LAST_CALL => '<p>Replies for dining close at midnight tonight, and we have not heard from you yet.</p>',
            default => '<p>You are down as <strong>'.e(MeetingReplies::label($this->rsvp->attendance_status)).'</strong>'
                .($this->rsvp->guests->where('attending_dining', true)->count() ? ', with '.$this->rsvp->guests->where('attending_dining', true)->count().' dining guest(s)' : '')
                .'. If that has changed, please update your reply.</p>',
        };

        $answers = $this->kind === self::CONFIRM
            ? '<p><a href="'.e($this->links['change']).'">Change my reply</a></p>'
            : '<p>'.collect(MeetingReplies::ANSWERS)
                ->map(fn (string $answer) => '<a href="'.e($this->links[$answer]).'">'.e(ucfirst(MeetingReplies::label($answer))).'</a>')
                ->implode('<br>').'</p>';

        $owed = MeetingReplies::owed($this->meeting, $this->rsvp);
        $payment = $owed['amount'] > 0
            ? '<p>Dining still to pay: <strong>'.e(Currencies::format($owed['amount'], $this->club)).'</strong>. <a href="'.e($this->links['change']).'">Pay or see the bank details</a>.</p>'
            : '';

        return '<p>Dear '.e($v['member_name']).',</p>'.$intro.$when.$answers.$payment
            .'<p>These links are private to you and work until the day of the meeting.</p>';
    }
}
