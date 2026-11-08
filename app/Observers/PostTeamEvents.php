<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\ClubTeamEvents;

/**
 * A post published: saved as published, or moved to published.
 * shortcut: a post scheduled for later (published_at in the future) is never announced; add a scheduled check if receivers need it.
 */
class PostTeamEvents
{
    public function created(Post $post): void
    {
        $this->live($post) && $this->record($post);
    }

    public function updated(Post $post): void
    {
        $post->wasChanged('status') && $this->live($post) && $this->record($post);
    }

    /** Members see it now (as Post::scopePublished): later ones are hidden until then */
    private function live(Post $post): bool
    {
        return $post->status === 'published' && ($post->published_at === null || $post->published_at->lte(now()));
    }

    private function record(Post $post): void
    {
        ClubTeamEvents::record((int) $post->club_id, 'post.published', "published the post {$post->title}", [
            'post_id' => $post->id, 'title' => $post->title, 'slug' => $post->slug,
        ]);
    }
}
