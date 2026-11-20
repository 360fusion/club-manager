<?php

namespace App\Jobs;

use App\Mail\PassedOnMail;
use App\Models\Club;
use App\Models\PassedOnDelivery;
use App\Models\PassedOnItem;
use App\Notifications\ClubNotification;
use App\Services\PassedOn\PassedOnAudience;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends a passed-on item once: a delivery for each person PassedOnAudience picks, then the bell (which goes on to
 * their phones) and an email for each who wants one, queued on `bulk` like newsletters.
 */
class SendPassedOnItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $itemId)
    {
        $this->onQueue('bulk');
    }

    public function handle(PassedOnAudience $audience): void
    {
        $item = PassedOnItem::with(['club', 'lodge'])->find($this->itemId);
        if ($item === null || $item->sent_at !== null) {
            return;
        }

        $this->addPdfToEarlierCopies($item);
        $recipients = $audience->recipients($item);
        $clubs = Club::whereIn('id', $recipients->pluck('via_club_id')->unique()->all())->get()->keyBy('id');
        $emails = 0;

        foreach ($recipients as $row) {
            $delivery = PassedOnDelivery::create([
                'item_id' => $item->id,
                'user_id' => $row['user']?->id,
                'member_id' => $row['member']?->id,
                'email' => $row['email'],
                'kind' => $row['kind'],
                'via_club_id' => $row['via_club_id'],
                'lodge_id' => $row['lodge_id'],
                'dedupe_key' => $item->dedupe_key,
                'bell' => $row['bell'],
                'emailed' => $row['emailed'],
                'follower' => $row['follower'],
                'token' => Str::random(64),
            ])->setRelation('item', $item)->setRelation('viaClub', $clubs[$row['via_club_id']]);

            if ($row['bell']) {
                $row['user']->notifyNow(ClubNotification::passedOn($delivery));
            }

            if ($row['emailed']) {
                Mail::to($row['email'])->send(new PassedOnMail($delivery));
                $emails++;
            }
        }

        // Followers are left out of the count, so nobody can work out how many people follow a lodge.
        $item->update(['recipient_count' => $recipients->where('follower', false)->count(), 'email_count' => $emails, 'sent_at' => now()]);
    }

    /**
     * The Secretary passing on the full PDF of a summons his members already had from the lodge itself (key facts
     * only): their copies now show his, quietly, with no second notice. Only his own lodge's members' copies.
     */
    private function addPdfToEarlierCopies(PassedOnItem $item): void
    {
        if (! $item->attachment_path || $item->type !== PassedOnItem::SUMMONS) {
            return;
        }

        PassedOnDelivery::where('dedupe_key', $item->dedupe_key)
            ->where('via_club_id', $item->club_id)
            ->whereHas('item', fn ($earlier) => $earlier->whereNull('attachment_path'))
            ->update(['item_id' => $item->id]);
    }
}
