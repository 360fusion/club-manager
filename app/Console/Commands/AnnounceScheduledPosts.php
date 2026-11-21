<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use Illuminate\Console\Command;

/**
 * News saved to go out later is announced to the lodge's members when its time comes, as "publish now" news is when
 * saved. Each post is announced once (members_notified_at), and only within two days of its time, so a scheduler that
 * was stopped for a while does not send a pile of old news when it starts again.
 */
class AnnounceScheduledPosts extends Command
{
    protected $signature = 'app:announce-scheduled-posts';

    protected $description = 'Notify members about scheduled news posts that have now gone live';

    public function handle(ClubNotifier $notifier): int
    {
        $announced = 0;

        Post::with(['club', 'author'])
            ->where('status', 'published')
            ->whereNull('members_notified_at')
            ->whereBetween('published_at', [now()->subDays(2), now()])
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get()
            ->each(function (Post $post) use ($notifier, &$announced) {
                // Claimed before sending, so two runs at once cannot both announce it.
                if (! Post::whereKey($post->id)->whereNull('members_notified_at')->update(['members_notified_at' => now()])) {
                    return;
                }

                $notifier->toMembers($post->club, ClubNotification::news($post, $post->club), $post->author);
                $announced++;
            });

        $this->info("Announced {$announced} scheduled ".str('post')->plural($announced).'.');

        return self::SUCCESS;
    }
}
