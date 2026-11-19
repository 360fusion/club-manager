<?php

namespace App\Jobs;

use App\Mail\LodgeClaimMail;
use App\Models\ClubNotice;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\Notices\NoticeAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a notice once: to the bell (which goes on to the phones of members with the app who have not muted notices)
 * and by email to everyone it would not reach on a phone, or to everyone when phone notifications are off.
 */
class SendClubNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $noticeId)
    {
        $this->onQueue('transactional');
    }

    public function handle(NoticeAudience $audience): void
    {
        $notice = ClubNotice::with(['club', 'meeting'])->find($this->noticeId);

        if ($notice === null || $notice->sent_at !== null) {
            return;
        }

        $club = $notice->club;
        $users = $audience->users($club, $notice->audience, $notice->meeting);
        $onPhone = collect();

        if ($notice->push) {
            Notification::sendNow($users, new ClubNotification(ClubNotification::NOTICE, $notice->title, $notice->body, $club, 'member.dashboard', ['slug' => $club->slug]));

            $withApp = User::whereIn('id', $users->pluck('id'))->whereHas('pushDevices')->pluck('id');
            $onPhone = $users->filter(fn (User $user) => $withApp->contains($user->id) && ! in_array(ClubNotification::NOTICE, $user->push_muted ?? [], true));
        }

        $byEmail = $notice->email ? $users->reject(fn (User $user) => $onPhone->contains('id', $user->id))->filter(fn (User $user) => filled($user->email)) : collect();
        $link = route('member.dashboard', ['slug' => $club->slug]);

        foreach ($byEmail as $user) {
            Mail::to($user->email)->send(new LodgeClaimMail($notice->title, $notice->body, 'Open '.$club->name, $link));
        }

        $notice->update([
            'recipient_count' => $users->count(),
            'push_count' => $onPhone->count(),
            'email_count' => $byEmail->count(),
            'sent_at' => now(),
        ]);
    }
}
