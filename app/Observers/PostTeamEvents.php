<?php

namespace App\Observers;

use App\Models\Post;
use App\Support\ClubTeamEvents;

/** A post published: saved as published, or moved to published */
class PostTeamEvents
{
    public function created(Post $post): void
    {
        $post->status === 'published' && $this->record($post);
    }

    public function updated(Post $post): void
    {
        $post->wasChanged('status') && $post->status === 'published' && $this->record($post);
    }

    private function record(Post $post): void
    {
        ClubTeamEvents::record((int) $post->club_id, 'post.published', "published the post {$post->title}", [
            'post_id' => $post->id, 'title' => $post->title, 'slug' => $post->slug,
        ]);
    }
}
