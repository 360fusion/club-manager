<?php

namespace Launchpad\Site;

use Illuminate\Support\Facades\File;
use Launchpad\Models\Setting;

/**
 * The website's pages. A page is a Markdown file called <slug>.md in the app's resources/launchpad/pages if it has one there, else the
 * package's own default; a page can also be written per language in a <locale>/ folder of either, which is tried first. Nothing is copied at install: an app overrides one page at a time (`launchpad:publish <slug>`), and
 * `launchpad:status` says when a default it overrode has changed since.
 */
class Pages
{
    public function appDirectory(): string
    {
        return resource_path('launchpad/pages');
    }

    public function packageDirectory(): string
    {
        return __DIR__.'/../../resources/content/pages';
    }

    /** @return list<string> every slug there is a file for, in either place */
    public function slugs(): array
    {
        $slugs = [];

        foreach ($this->directories() as [$directory]) {
            foreach (File::isDirectory($directory) ? File::files($directory) : [] as $file) {
                if ($file->getExtension() === 'md') {
                    $slugs[] = $file->getFilenameWithoutExtension();
                }
            }
        }

        $slugs = array_values(array_unique($slugs));
        sort($slugs);

        return $slugs;
    }

    /** Where to look, first wins: the app's then the package's file in the request's language, then the same in the base language
     * @return list<array{string, string, string}> directory, source, locale */
    private function directories(): array
    {
        $locale = app()->getLocale();
        $found = [];

        if (preg_match('/\A[A-Za-z]{2,3}([_-][A-Za-z0-9]+)?\z/', $locale)) {
            $found = [[$this->appDirectory()."/{$locale}", 'app', $locale], [$this->packageDirectory()."/{$locale}", 'package', $locale]];
        }

        return [...$found, [$this->appDirectory(), 'app', ''], [$this->packageDirectory(), 'package', '']];
    }

    public function find(string $slug): ?Page
    {
        foreach ($this->directories() as [$directory, $source, $locale]) {
            if ($page = $this->readFrom($slug, $directory, $source, $locale)) {
                return $page;
            }
        }

        return null;
    }

    /** The package's own page, even where the app has overridden it (for the diff and the status) */
    public function packageDefault(string $slug): ?Page
    {
        return $this->readFrom($slug, $this->packageDirectory(), 'package', '');
    }

    private function readFrom(string $slug, string $directory, string $source, string $locale): ?Page
    {
        if (! preg_match('/\A[a-z0-9-]+\z/', $slug)) {
            return null;
        }

        $path = "{$directory}/{$slug}.md";

        if (! File::isFile($path)) {
            return null;
        }

        $raw = File::get($path);
        [$meta, $html, $missing] = Markdown::parse($raw);

        return new Page(
            slug: $slug,
            title: (string) ($meta['title'] ?? ucfirst(str_replace('-', ' ', $slug))),
            description: (string) ($meta['description'] ?? ''),
            html: $html,
            meta: $meta,
            source: $source,
            path: $path,
            bodyHash: sha1(Markdown::body($raw)),
            missingTokens: $missing,
            locale: $locale,
        );
    }

    /** A legal page's review: set in the admin once somebody competent has read the text */
    public function review(string $slug, string $locale = ''): ?array
    {
        $review = Setting::get('page_review_'.$slug.($locale === '' ? '' : ".{$locale}"));

        return is_array($review) ? $review : null;
    }

    /** 'reviewed' if the admin says so, else what the file says ('template'), else null for a page that carries no status */
    public function status(Page $page): ?string
    {
        if ($page->status() === null) {
            return null;
        }

        return $this->review($page->slug, $page->locale) ? 'reviewed' : ($page->status() === 'reviewed' && $page->isOverride() ? 'reviewed' : 'template');
    }
}
