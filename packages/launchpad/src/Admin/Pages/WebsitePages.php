<?php

namespace Launchpad\Admin\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Launchpad\Admin\AdminLog;
use Launchpad\Admin\StaffAccess;
use Launchpad\Models\Setting;
use Launchpad\Site\Pages;

/**
 * Every page of the website: whose text it is (the package's or the app's own), whether it is the template or has been read and approved,
 * and which {{tokens}} still have no value. Marking a page reviewed is a statement that somebody competent has read it: it is in the log.
 */
class WebsitePages extends Page
{
    protected static ?string $slug = 'website-pages';

    protected static ?string $title = 'Website pages';

    protected string $view = 'launchpad::admin.website-pages';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function canAccess(): bool
    {
        return StaffAccess::allowed('manage-site');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Website';
    }

    /** @return list<array<string, mixed>> */
    public function rows(): array
    {
        $pages = app(Pages::class);
        $rows = [];

        foreach ($pages->slugs() as $slug) {
            $page = $pages->find($slug);

            $rows[] = [
                'slug' => $slug,
                'title' => $page->title,
                'on' => (bool) config("launchpad.site.pages.{$slug}"),
                'source' => $page->isOverride() ? 'Your own' : 'Default',
                'status' => $pages->status($page),
                'review' => $pages->review($slug),
                'missing' => collect($page->missingTokens)->map(fn ($token) => '{'.'{'.$token.'}'.'}')->implode(', '),
                'updated' => $page->updated(),
            ];
        }

        return $rows;
    }

    public function markReviewed(string $slug): void
    {
        abort_unless(in_array($slug, app(Pages::class)->slugs(), true), 404);

        $by = AdminLog::actor();
        Setting::set('page_review_'.$slug, ['by' => $by?->email, 'at' => now()->toIso8601String()]);
        Notification::make()->success()->title("{$slug} marked as reviewed")->send();
    }

    public function markTemplate(string $slug): void
    {
        abort_unless(in_array($slug, app(Pages::class)->slugs(), true), 404);

        Setting::unset('page_review_'.$slug);
        Notification::make()->success()->title("{$slug} is a template again")->send();
    }
}
