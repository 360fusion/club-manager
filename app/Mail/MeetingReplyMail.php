<?php

namespace App\Mail;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use App\Support\Currencies;
use Illuminate\Mail\Mailables\Content;

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
        $guests = $this->rsvp->guests->where('attending_dining', true)->count();

        return [
            'text' => [
                'member_name' => $this->user->name,
                'club_name' => $this->club->name,
                'meeting_title' => $this->meeting->title,
                'meeting_date' => $this->meeting->meeting_date->format('l j F Y'),
                'meeting_time' => $this->meeting->starts_at ?? '',
                'venue' => (string) $this->meeting->venue,
                'your_reply' => MeetingReplies::label($this->rsvp->attendance_status).($guests ? ", with {$guests} dining ".($guests === 1 ? 'guest' : 'guests') : ''),
                'reply_url' => $this->links['change'],
            ],
            'html' => [
                'reply_links' => $this->replyLinks(),
                'payment' => $this->payment(),
            ],
        ];
    }

    /**
     * The answers as private links (or, for the reminder, the link to change the reply).
     */
    private function replyLinks(): string
    {
        if ($this->kind === self::CONFIRM) {
            return '<p><a href="'.e($this->links['change']).'">Change my reply</a></p>';
        }

        return '<p>'.collect(MeetingReplies::ANSWERS)
            ->map(fn (string $answer) => '<a href="'.e($this->links[$answer]).'">'.e(ucfirst(MeetingReplies::label($answer))).'</a>')
            ->implode('<br>').'</p>';
    }

    private function payment(): string
    {
        $owed = MeetingReplies::owed($this->meeting, $this->rsvp);

        return $owed['amount'] > 0
            ? '<p>Dining still to pay: <strong>'.e(Currencies::format($owed['amount'], $this->club)).'</strong>. <a href="'.e($this->links['change']).'">Pay or see the bank details</a>.</p>'
            : '';
    }

    /**
     * Whatever the wording, the private links are always in the email: they are added when the template lacks them.
     */
    public function content(): Content
    {
        $body = $this->filled()['body'];

        // A reworded template that dropped both its answers and its reply link still gets them.
        $hasLinks = str_contains($body, e($this->links['change'])) || str_contains($body, e($this->links['attending_dining'] ?? $this->links['change']));

        if (! $hasLinks) {
            $body .= $this->replyLinks();
        }

        return new Content(html: 'emails.templated', with: ['bodyHtml' => $body]);
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
        $html = $this->values()['html'];
        $when = '<p><strong>'.e($v['meeting_title']).'</strong>, '.e($v['meeting_date']).($v['meeting_time'] ? ' at '.e($v['meeting_time']) : '').($v['venue'] ? '<br>'.e($v['venue']) : '').'</p>';

        $intro = match ($this->kind) {
            self::ASK => '<p>Please let the Secretary know whether you will be at the next meeting, so the festive board can be booked.</p>',
            self::LAST_CALL => '<p>Replies for dining close at midnight tonight, and we have not heard from you yet.</p>',
            default => '<p>You are down as <strong>'.e($v['your_reply']).'</strong>. If that has changed, please update your reply.</p>',
        };

        return '<p>Dear '.e($v['member_name']).',</p>'.$intro.$when.$html['reply_links'].$html['payment']
            .'<p>These links are private to you and work until the day of the meeting.</p>';
    }
}
