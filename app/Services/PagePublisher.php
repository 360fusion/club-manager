<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;

/**
 * The one place a page's content is written after it is created: making an edit live, keeping an unpublished
 * draft of a live page, recording earlier versions and adding a copied element.
 *
 * "Content" means the title, the search title and description, the share image and the elements. Everything
 * else (address, published, menus, dates) is a setting and always saves straight away.
 */
class PagePublisher
{
    /**
     * How many earlier versions of a page are kept unless the lodge chooses otherwise (Website & SEO Settings, Page
     * history), the range it may choose from, and how old a version may get before it is deleted (0 = never).
     */
    public const DEFAULT_KEEP = 15;

    public const MIN_KEEP = 5;

    public const MAX_KEEP = 50;

    public const DEFAULT_MAX_AGE_DAYS = 180;

    public static function keepFor(Club $club): int
    {
        return max(self::MIN_KEEP, min(self::MAX_KEEP, (int) ($club->settings['revisions_keep'] ?? self::DEFAULT_KEEP)));
    }

    public static function maxAgeDaysFor(Club $club): int
    {
        return max(0, (int) ($club->settings['revisions_max_age_days'] ?? self::DEFAULT_MAX_AGE_DAYS));
    }

    /**
     * @return array{title: string, meta_title: string|null, meta_description: string|null, share_image: string|null, blocks: array<int, mixed>}
     */
    public function liveContent(Page $page): array
    {
        return [
            'title' => (string) $page->title,
            'meta_title' => $page->meta_title,
            'meta_description' => $page->meta_description,
            'share_image' => $page->share_image,
            'blocks' => $page->blocks ?? [],
        ];
    }

    /**
     * What the page will look like once its draft is published (or just the live content when there is no draft).
     *
     * @return array{title: string, meta_title: string|null, meta_description: string|null, share_image: string|null, blocks: array<int, mixed>}
     */
    public function effectiveContent(Page $page): array
    {
        $live = $this->liveContent($page);

        if (! $page->hasDraft()) {
            return $live;
        }

        $draft = $page->draft_content ?? [];

        return [
            'title' => (string) ($draft['title'] ?? $live['title']),
            'meta_title' => $draft['meta_title'] ?? $live['meta_title'],
            'meta_description' => $draft['meta_description'] ?? $live['meta_description'],
            'share_image' => $draft['share_image'] ?? $live['share_image'],
            'blocks' => $page->draft_blocks ?? $live['blocks'],
        ];
    }

    /**
     * Make content live, replace any draft, and remember the version.
     *
     * @param  array{title: string, meta_title?: string|null, meta_description?: string|null, share_image?: string|null, blocks?: array<int, mixed>}  $content
     */
    public function applyLive(Page $page, array $content, ?User $user, string $source = 'save'): void
    {
        $page->fill([
            'title' => $content['title'],
            'meta_title' => $content['meta_title'] ?? null,
            'meta_description' => $content['meta_description'] ?? null,
            'share_image' => $content['share_image'] ?? null,
            'blocks' => $content['blocks'] ?? [],
            'draft_blocks' => null,
            'draft_content' => null,
            'draft_saved_at' => null,
        ])->save();

        $this->snapshot($page, $user, $source);
    }

    /**
     * Keep edits to a live page aside, unseen by visitors, until they are published.
     *
     * @param  array{title: string, meta_title?: string|null, meta_description?: string|null, share_image?: string|null, blocks?: array<int, mixed>}  $content
     */
    public function saveDraft(Page $page, array $content): void
    {
        $page->fill([
            'draft_blocks' => $content['blocks'] ?? [],
            'draft_content' => [
                'title' => $content['title'],
                'meta_title' => $content['meta_title'] ?? null,
                'meta_description' => $content['meta_description'] ?? null,
                'share_image' => $content['share_image'] ?? null,
            ],
            'draft_saved_at' => now(),
        ])->save();
    }

    public function discardDraft(Page $page): void
    {
        $page->fill(['draft_blocks' => null, 'draft_content' => null, 'draft_saved_at' => null])->save();
    }

    public function publishDraft(Page $page, ?User $user): void
    {
        $this->applyLive($page, $this->effectiveContent($page), $user, 'publish');
    }

    /**
     * Add an element (copied from another page) to the end of the page: to its draft if it has one, so the
     * copy stays unseen until that is published, otherwise straight to the live page.
     *
     * @param  array<string, mixed>  $block
     */
    public function appendBlock(Page $page, array $block, ?User $user): void
    {
        if ($page->hasDraft()) {
            $page->fill(['draft_blocks' => [...($page->draft_blocks ?? $page->blocks ?? []), $block], 'draft_saved_at' => now()])->save();

            return;
        }

        $page->fill(['blocks' => [...($page->blocks ?? []), $block]])->save();
        $this->snapshot($page, $user, 'copy');
    }

    /**
     * Record the page's live content as a version, unless nothing has changed since the last one.
     */
    public function snapshot(Page $page, ?User $user, string $source): ?PageRevision
    {
        $content = $this->liveContent($page);
        $latest = $page->revisions()->latest('id')->first();

        if ($latest && $this->fingerprint([
            'title' => $latest->title, 'meta_title' => $latest->meta_title, 'meta_description' => $latest->meta_description,
            'share_image' => $latest->share_image, 'blocks' => $latest->blocks ?? [],
        ]) === $this->fingerprint($content)) {
            return null;
        }

        $revision = PageRevision::create([
            'page_id' => $page->id,
            'club_id' => $page->club_id,
            'user_id' => $user?->id,
            'title' => $content['title'],
            'meta_title' => $content['meta_title'],
            'meta_description' => $content['meta_description'],
            'share_image' => $content['share_image'],
            'blocks' => $content['blocks'],
            'source' => $source,
        ]);

        $page->revisions()->orderByDesc('id')->skip(self::keepFor($page->club))->take(PHP_INT_MAX)->pluck('id')
            ->whenNotEmpty(fn ($ids) => PageRevision::whereIn('id', $ids)->delete());

        return $revision;
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function fingerprint(array $content): string
    {
        return md5((string) json_encode([$content['title'], $content['meta_title'], $content['meta_description'], $content['share_image'], $content['blocks']]));
    }
}
