<?php

namespace Launchpad\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Launchpad\Admin\AdminLog;
use Launchpad\Launchpad;

/** A suggestion or a plan on the public roadmap, with people's votes */
class RoadmapItem extends Model
{
    /** What the public page shows, in this order */
    public const SHOWN = ['building', 'planned', 'idea', 'done'];

    /** The headings of the public page */
    public const HEADINGS = ['review' => 'Waiting for review', 'idea' => 'Ideas', 'planned' => 'Planned', 'building' => 'Being built', 'done' => 'Done'];

    public const STATUSES = ['review' => 'Waiting for review', 'idea' => 'Idea', 'planned' => 'Planned', 'building' => 'Being built', 'done' => 'Done', 'declined' => 'Not planned', 'merged' => 'Merged'];

    protected $guarded = [];

    protected static function booted(): void
    {
        // a person's own suggestion is not staff work; what staff change is logged
        static::created(fn (self $i) => AdminLog::actor() && AdminLog::record('roadmap.created', $i, "Roadmap item “{$i->title}” added"));
        static::updated(fn (self $i) => AdminLog::actor() && AdminLog::saved($i, 'roadmap.updated'));
        static::deleted(fn (self $i) => AdminLog::record('roadmap.deleted', null, "Roadmap item “{$i->title}” removed"));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Launchpad::userModel(), 'author_id');
    }

    /** What the public page lists, and a person's own suggestions still waiting for review */
    public function scopeVisibleTo(Builder $query, ?object $user): void
    {
        $query->where(fn ($q) => $q->whereIn('status', self::SHOWN)
            ->when($user, fn ($q) => $q->orWhere(fn ($q) => $q->where('status', 'review')->where('author_id', $user->getKey()))));
    }

    public function countVotes(): void
    {
        $this->forceFill(['votes' => DB::table('roadmap_votes')->where('roadmap_item_id', $this->id)->count()])->saveQuietly();
    }

    /** A duplicate: its votes move to $into (once per person) and it stops showing */
    public function mergeInto(self $into): void
    {
        DB::transaction(function () use ($into) {
            $voters = DB::table('roadmap_votes')->where('roadmap_item_id', $this->id)->pluck('user_id');
            DB::table('roadmap_votes')->insertOrIgnore($voters->map(fn ($id) => ['roadmap_item_id' => $into->id, 'user_id' => $id, 'created_at' => now()])->all());
            $this->update(['status' => 'merged', 'merged_into_id' => $into->id]);
            $into->countVotes();
        });
    }
}
