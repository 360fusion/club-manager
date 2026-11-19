<?php

namespace App\Console\Commands;

use App\Mail\MeetingReplyMail;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\Meetings\MeetingReplies;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendMeetingRepliesCommand extends Command
{
    protected $signature = 'app:send-meeting-replies';

    protected $description = 'Run each meeting\'s reply schedule: ask members who have not replied, a last call before the dining deadline, the Secretary\'s numbers once it passes, and a reminder to those coming';

    public function __construct(private readonly MeetingReplies $replies)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sent = 0;

        Meeting::with('club')
            ->where('status', 'published')
            ->whereBetween('meeting_date', [now()->subDay()->toDateString(), now()->addDays(60)->toDateString()])
            ->get()
            ->each(function (Meeting $meeting) use (&$sent) {
                $sent += $this->runSchedule($meeting);
            });

        $this->info("Sent {$sent} meeting ".($sent === 1 ? 'message' : 'messages').'.');

        return self::SUCCESS;
    }

    private function runSchedule(Meeting $meeting): int
    {
        $club = $meeting->club;
        $local = now($club->timezone());

        // From 9am where the lodge is (this runs hourly), so nobody's phone goes off in the night.
        if ($local->hour < 9 || $meeting->meeting_date->toDateString() < $local->toDateString()) {
            return 0;
        }

        $days = MeetingReplies::days($club);
        $daysLeft = (int) CarbonImmutable::parse($local->toDateString())->diffInDays($meeting->meeting_date->toDateString());
        $diningClosed = $meeting->rsvp_cutoff_at ? $meeting->rsvpClosed() : $daysLeft <= $days['deadline'];
        $sent = 0;

        if (! $diningClosed && $daysLeft <= $days['ask'] && $this->claim($meeting, 'replies_requested_at')) {
            // A summons published inside the ask window has only just asked them.
            $askFrom = $meeting->meeting_date->copy()->subDays($days['ask'])->toDateString();
            if (! $meeting->summons_published_at || $meeting->summons_published_at->setTimezone($club->timezone())->toDateString() < $askFrom) {
                $sent += $this->sendToEach($meeting, $this->replies->nonRepliers($meeting), MeetingReplyMail::ASK, 'Will you be there? '.$meeting->title);
            }
        }

        $lastCallDay = $meeting->rsvp_cutoff_at && $meeting->rsvp_cutoff_at->toDateString() === $local->toDateString();
        $askedToday = $meeting->fresh()->replies_requested_at?->setTimezone($club->timezone())->toDateString() === $local->toDateString();

        if (! $diningClosed && $lastCallDay && $this->claim($meeting, 'last_call_sent_at') && ! $askedToday) {
            $sent += $this->sendToEach($meeting, $this->replies->nonRepliers($meeting), MeetingReplyMail::LAST_CALL, 'Last call for dining: '.$meeting->title);
        }

        if ($diningClosed && $this->claim($meeting, 'numbers_sent_at')) {
            $sent += $this->replies->sendNumbers($meeting->fresh()) ? 1 : 0;
        }

        if ($daysLeft <= $days['confirm'] && $this->claim($meeting, 'confirmations_sent_at')) {
            $title = ($daysLeft === 1 ? 'Tomorrow: ' : 'Reminder: ').$meeting->title;

            foreach ($this->replies->attending($meeting) as $rsvp) {
                $sent += $this->send($meeting, $rsvp->user, $rsvp, MeetingReplyMail::CONFIRM, $title);
            }
        }

        return $sent;
    }

    /**
     * Marks a step as done, once: only the run that sets it goes on to send.
     */
    private function claim(Meeting $meeting, string $column): bool
    {
        return Meeting::whereKey($meeting->id)->whereNull($column)->update([$column => now()]) === 1;
    }

    /**
     * @param  iterable<User>  $people
     */
    private function sendToEach(Meeting $meeting, iterable $people, string $kind, string $title): int
    {
        $sent = 0;

        foreach ($people as $person) {
            $sent += $this->send($meeting, $person, $this->replies->rsvpFor($meeting, $person), $kind, $title);
        }

        return $sent;
    }

    private function send(Meeting $meeting, User $person, MeetingRsvp $rsvp, string $kind, string $title): int
    {
        $links = ['change' => $this->replies->replyUrl($meeting, $rsvp)];
        foreach (MeetingReplies::ANSWERS as $answer) {
            $links[$answer] = $this->replies->replyUrl($meeting, $rsvp, $answer);
        }

        Mail::to($person->email)->queue(new MeetingReplyMail($meeting->club, $meeting, $person, $rsvp->load('guests'), $kind, $links));
        $person->notify(ClubNotification::meetingReply($meeting, $meeting->club, $title, $kind === MeetingReplyMail::CONFIRM));

        return 1;
    }
}
