<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NewsTag extends Model
{
    use HasFactory;

    /**
     * Tags every club starts with (name => colour key from App\Support\OrderColours).
     * A club can rename, recolour, delete or add to them under Settings.
     *
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'Charity & Community' => 'rose',
        'Ceremonies & Initiations' => 'indigo',
        'Long Service Awards' => 'amber',
        'Provincial News' => 'purple',
        'Lodge News' => 'sky',
        'Events & Social' => 'emerald',
    ];

    protected $fillable = ['club_id', 'name', 'slug', 'color'];

    /**
     * Give a club the default tags. Safe to run twice: existing slugs are left alone.
     */
    public static function ensureDefaults(Club $club): void
    {
        foreach (self::DEFAULTS as $name => $colour) {
            self::firstOrCreate(
                ['club_id' => $club->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'color' => $colour],
            );
        }
    }

    /**
     * The tags a member can filter or browse by across the given clubs: only tags that have
     * at least one published news item this viewer may see, merged by slug so two lodges'
     * "Lodge News" count as one chip.
     *
     * @param  list<int>  $clubIds
     * @return Collection<int, array{name: string, slug: string, color: ?string, count: int}>
     */
    public static function browsableFor(array $clubIds, ?User $viewer): Collection
    {
        return self::whereIn('club_id', $clubIds)
            ->withCount(['posts as visible_posts_count' => fn ($posts) => $posts->published()->visibleTo($viewer)])
            ->orderBy('name')
            ->get()
            ->filter(fn (NewsTag $tag) => $tag->visible_posts_count > 0)
            ->groupBy('slug')
            ->map(fn (Collection $group) => [
                'name' => $group->first()->name,
                'slug' => $group->first()->slug,
                'color' => $group->first()->color,
                'count' => (int) $group->sum('visible_posts_count'),
            ])
            ->values();
    }

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'news_tag_post');
    }
}
