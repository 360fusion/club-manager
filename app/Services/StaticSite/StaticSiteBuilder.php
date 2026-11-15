<?php

namespace App\Services\StaticSite;

use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\SiteRelease;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use League\Flysystem\PathTraversalDetected;
use Throwable;

/**
 * Builds a club's public website as plain files: one folder per release under storage/app/sites/{club}/releases, with
 * an index.html for every public page and news article, the app's built CSS/JS, the images the pages use, a sitemap,
 * robots.txt and an .htaccess for Apache (cPanel) hosting. The pages keep their Vue data, so blocks that move or open
 * (slideshow, FAQ, menus) still work; anything that needs the server (events, bookings, the contact form, members-only
 * pages and documents) links or posts back to the platform.
 *
 * Each page is rendered by the app itself, in-process, as a signed-out visitor, so a release never contains anything a
 * visitor couldn't already see. Then, when Chrome is available, the page is drawn once so the file holds real HTML for
 * search engines and visitors without JavaScript.
 */
class StaticSiteBuilder
{
    /** Paths under /site/{club} that only the server can answer; links to them point back at the platform. */
    public const SERVER_PATHS = ['events', 'booking', 'files', 'calendar.ics', 'contact-form', 'newsletter'];

    public const MANIFEST = '.clubmanager-manifest.json';

    public function __construct(private StaticPagePrerenderer $prerenderer) {}

    public function build(Club $club, ?User $by = null): SiteRelease
    {
        $release = SiteRelease::create(['club_id' => $club->id, 'created_by' => $by?->id, 'status' => 'building']);

        try {
            if (Vite::isRunningHot()) {
                throw new StaticSiteException('The Vite dev server is running, so the pages would load their code from it. Stop it and run "npm run build" first.');
            }

            $dir = $release->path();
            File::ensureDirectoryExists($dir);

            $pages = $this->writePages($club, $dir);
            File::copyDirectory(public_path('build'), $dir.'/build');
            $this->writeExtras($club, $dir);
            $manifest = $this->writeManifest($dir);

            $release->update([
                'status' => 'ready',
                'page_count' => $pages,
                'file_count' => count($manifest),
                'bytes' => array_sum(array_map(fn (string $path) => filesize($dir.'/'.$path), array_keys($manifest))),
            ]);

            $this->activate($release);
            $this->prune($club);
        } catch (Throwable $e) {
            report($e);
            $release->update(['status' => 'failed', 'error' => mb_substr($e instanceof StaticSiteException ? $e->getMessage() : 'The website could not be built: '.$e->getMessage(), 0, 1000)]);
            File::deleteDirectory($release->path());
        }

        return $release->fresh();
    }

    /**
     * Makes this release the live one: `current` is repointed in one step (a new link renamed over the old one), so a
     * visitor never sees half of one version and half of another.
     */
    public function activate(SiteRelease $release): void
    {
        $current = SiteRelease::currentPath($release->club_id);
        $temporary = $current.'.'.bin2hex(random_bytes(4));

        symlink($release->path(), $temporary);
        rename($temporary, $current);

        $release->update(['activated_at' => now()]);
    }

    /** Keeps the newest few ready releases (and always the live one); older folders and rows go. */
    private function prune(Club $club): void
    {
        $keep = SiteRelease::where('club_id', $club->id)->where('status', 'ready')->latest('id')->limit(SiteRelease::KEEP)->pluck('id');

        SiteRelease::where('club_id', $club->id)->whereNotIn('id', $keep)->get()
            ->reject(fn (SiteRelease $old) => $old->isCurrent())
            ->each(function (SiteRelease $old) {
                File::deleteDirectory($old->path());
                $old->delete();
            });
    }

    /**
     * @return int how many pages were written
     */
    private function writePages(Club $club, string $dir): int
    {
        $staticSite = self::flags($club);

        $targets = Page::where('club_id', $club->id)->live()->where('is_members_only', false)->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Page $page) => [$page->is_homepage ? '' : $page->slug => $page->is_homepage ? '' : $page->slug]);

        $club->posts()->published()->visibleTo(null)->pluck('slug')->filter()->unique()
            ->each(function (string $slug) use ($targets) {
                $targets->put('news/'.$slug, 'news/'.$slug);
            });

        $count = 0;

        foreach ($targets as $sitePath => $outPath) {
            if (! $this->isSafePath($outPath)) {
                continue;
            }

            [$status, $html] = $this->fetch($club, (string) $sitePath, $staticSite);

            if ($status !== 200) {
                continue;
            }

            $html = $this->prerenderer->render($this->signedUrl($club, (string) $sitePath), $html);
            $this->writeFile($dir.'/'.ltrim($outPath.'/index.html', '/'), $this->rewrite($club, $html, $dir));
            $count++;
        }

        // The not-found page: whatever the site shows for an address it doesn't have (the club's own choice, or the default).
        [, $html] = $this->fetch($club, '__clubmanager-not-found', $staticSite);
        $html = $this->prerenderer->render($this->signedUrl($club, '__clubmanager-not-found'), $html);
        $this->writeFile($dir.'/404.html', $this->rewrite($club, $html, $dir));

        if ($count === 0) {
            throw new StaticSiteException('The website has no public pages to build.');
        }

        return $count;
    }

    /**
     * What a page rendered for the static site is told: the platform's address, and the members-only pages, which
     * stay on the platform (a visitor signs in there).
     *
     * @return array{platform: string, server_paths: list<string>, server_pages: list<string>}
     */
    public static function flags(Club $club): array
    {
        return [
            'platform' => rtrim((string) config('app.url'), '/'),
            'server_paths' => self::SERVER_PATHS,
            'server_pages' => Page::where('club_id', $club->id)->live()->where('is_members_only', true)->pluck('slug')->values()->all(),
        ];
    }

    /**
     * Renders one public address as a signed-out visitor would see it, with the static-site flags the pages read.
     *
     * @param  array{platform: string, server_paths: list<string>, server_pages: list<string>}  $staticSite
     * @return array{0: int, 1: string}
     */
    private function fetch(Club $club, string $sitePath, array $staticSite): array
    {
        $request = Request::create(rtrim((string) config('app.url'), '/').'/site/'.$club->slug.($sitePath === '' ? '' : '/'.$sitePath));
        // What the page's address is on the static site, and the flag that turns the pages' links into static ones.
        $request->attributes->set('club_domain_uri', '/'.$sitePath);
        $request->attributes->set('static_site', $staticSite);

        // The page is rendered inside this process, which may be serving someone: their request, sign-in and session are
        // put back afterwards. (It normally runs in a queued job, where there is nothing to put back.)
        $user = Auth::user();
        $original = app('request');
        $session = app('session.store');
        [$sessionId, $sessionData] = [$session->getId(), $session->all()];
        Auth::forgetUser();

        try {
            $response = app(Kernel::class)->handle($request);
        } finally {
            app()->instance('request', $original);
            URL::setRequest($original);
            $session->setId($sessionId);
            $session->flush();
            $session->put($sessionData);
            if ($user) {
                Auth::setUser($user);
            }
        }

        return [$response->getStatusCode(), (string) $response->getContent()];
    }

    /** The same page on the platform, for Chrome to open: the signature is what turns on static mode there. */
    private function signedUrl(Club $club, string $sitePath): string
    {
        return URL::temporarySignedRoute('public.site', now()->addMinutes(10), array_filter(['clubSlug' => $club->slug, 'pageSlug' => $sitePath ?: null, 'static' => 1]));
    }

    /**
     * Points the page at its own copies of the app's files and images, and at its own addresses: the platform's
     * address is replaced in both forms it appears in (plain in the HTML, with escaped slashes in the page's JSON).
     */
    private function rewrite(Club $club, string $html, string $dir): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $host = preg_quote((string) preg_replace('#^https?://#', '', $base), '~');
        $slash = '(\\\\?/)';

        // The page's own CSRF token belongs to a throwaway session: it means nothing on the static site.
        $html = (string) preg_replace('#<meta name="csrf-token"[^>]*>#', '', $html);

        // Built CSS/JS and uploaded images: /build/... and /storage/..., copying each image into the release.
        $html = (string) preg_replace_callback('~https?:'.$slash.$slash.$host.$slash.'(build|storage)'.$slash.'([^"\'\s)<>?\\\\]*(?:\\\\/[^"\'\s)<>?\\\\]*)*)~', function (array $m) use ($dir) {
            $path = str_replace('\\/', '/', $m[6]);

            if ($m[4] === 'storage') {
                $this->copyStorageFile(rawurldecode($path), $dir);
            }

            return $m[3].$m[4].$m[5].$m[6];
        }, $html);

        // Links to the site's own pages lose the platform and /site/{club} prefix; the server-only paths keep both. The
        // character after the prefix is captured so a bare link to the homepage still ends in a slash.
        $siteBase = $club->custom_domain ? 'https://'.$club->custom_domain : '';
        $serverOnly = implode('|', array_map(fn ($path) => preg_quote($path, '~'), self::SERVER_PATHS));

        return (string) preg_replace_callback('~https?:'.$slash.$slash.$host.$slash.'site'.$slash.preg_quote($club->slug, '~')
            .'(?!'.$slash.'(?:'.$serverOnly.')\b)(?=([/\\\\"\'?#<\s]|$))~', function (array $m) use ($siteBase) {
                $escaped = $m[3] === '\\/';
                $base = $escaped ? str_replace('/', '\\/', $siteBase) : $siteBase;

                return in_array($m[6] ?? '', ['/', '\\'], true) ? $base : $base.($escaped ? '\\/' : '/');
            }, $html);
    }

    private function copyStorageFile(string $relative, string $dir): void
    {
        try {
            $root = realpath(Storage::disk('public')->path(''));
            $source = realpath(Storage::disk('public')->path($relative));
        } catch (PathTraversalDetected) {
            return;
        }

        // Only real files inside the public disk: a crafted ../ path can't pull anything else into the release.
        if (! $root || ! $source || ! str_starts_with($source, $root.DIRECTORY_SEPARATOR) || ! is_file($source)) {
            return;
        }

        $target = $dir.'/storage/'.$relative;
        if (! is_file($target)) {
            File::ensureDirectoryExists(dirname($target));
            copy($source, $target);
        }
    }

    private function writeExtras(Club $club, string $dir): void
    {
        $siteBase = $club->custom_domain ? 'https://'.$club->custom_domain : null;
        $staticSite = self::flags($club);

        [$status, $sitemap] = $this->fetch($club, 'sitemap.xml', $staticSite);
        if ($status === 200 && $siteBase) {
            $this->writeFile($dir.'/sitemap.xml', str_replace(url('/site/'.$club->slug), $siteBase, $sitemap));
        }

        $this->writeFile($dir.'/robots.txt', "User-agent: *\nAllow: /\n".(is_file($dir.'/sitemap.xml') ? "Sitemap: {$siteBase}/sitemap.xml\n" : ''));
        $this->writeFile($dir.'/.htaccess', $this->htaccess($club));
    }

    /**
     * For Apache (what cPanel hosting runs): the not-found page, and the site's redirects as permanent or temporary
     * redirects. Every value is stripped of quotes and line breaks so nothing a page admin typed can add a directive.
     */
    private function htaccess(Club $club): string
    {
        $clean = fn (string $value) => preg_replace('/["\\\\\x00-\x1f\x7f\s]/', '', $value);
        $lines = ['# Written by Club Manager with each published version of the website.', 'ErrorDocument 404 /404.html', 'Options -Indexes'];

        $pages = Page::where('club_id', $club->id)->pluck('slug', 'id');
        foreach (PageRedirect::where('club_id', $club->id)->get() as $redirect) {
            $slug = $pages[$redirect->page_id] ?? null;
            if ($slug !== null) {
                $lines[] = 'Redirect 301 "/'.$clean($redirect->old_slug).'" "/'.($slug === 'home' ? '' : $clean($slug)).'"';
            }
        }

        foreach (ClubRedirect::where('club_id', $club->id)->get() as $redirect) {
            $to = str_replace(url('/site/'.$club->slug), '', (string) $redirect->to_url);
            $lines[] = 'Redirect '.($redirect->is_permanent ? '301' : '302').' "'.$clean($redirect->from_path).'" "'.($clean($to) ?: '/').'"';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Every file in the release with its SHA-1, saved beside them: a push compares it with the last one it sent, so
     * only changed files travel.
     *
     * @return array<string, string>
     */
    private function writeManifest(string $dir): array
    {
        $manifest = [];

        foreach (File::allFiles($dir, true) as $file) {
            $manifest[str_replace('\\', '/', $file->getRelativePathname())] = sha1_file($file->getPathname());
        }

        ksort($manifest);
        $this->writeFile($dir.'/'.self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $manifest;
    }

    private function isSafePath(string $path): bool
    {
        return ! str_contains($path, '..') && ! str_contains($path, '\\') && preg_match('#^[a-z0-9/_\-.]*$#i', $path) === 1;
    }

    private function writeFile(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $contents);
    }

    /** The files of a release, relative to its folder, with their SHA-1 (as written when it was built). */
    public static function manifest(SiteRelease $release): array
    {
        $path = $release->path().'/'.self::MANIFEST;

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
