<?php

namespace App\Services\StaticSite;

use App\Enums\ClubMemberStatus;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use App\Models\User;
use App\Support\ClubPermissions;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
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

    /**
     * @param  bool  $skipIfUnchanged  for automatic builds: when every file matches the live version, the new one is
     *                                 discarded and null is returned
     */
    public function build(Club $club, ?User $by = null, bool $skipIfUnchanged = false): ?SiteRelease
    {
        $release = SiteRelease::create(['club_id' => $club->id, 'created_by' => $by?->id, 'status' => 'building']);
        $hotFile = Vite::hotFile();

        try {
            if (Vite::isRunningHot()) {
                throw new StaticSiteException('The Vite dev server is running, so the pages would load their code from it. Stop it and run "npm run build" first.');
            }

            // Every page of this build uses the built code, even if a dev server starts while it runs.
            Vite::useHotFile(storage_path('framework/static-build-without-vite-hot'));

            $dir = $release->path();
            File::ensureDirectoryExists($dir);

            $pages = $this->writePages($club, $dir);
            $this->copyBuild($dir);
            $this->writeExtras($club, $dir);
            $manifest = $this->writeManifest($dir);

            if ($skipIfUnchanged && $manifest === self::manifest(new SiteRelease(['club_id' => $club->id]), SiteRelease::currentPath($club->id))) {
                File::deleteDirectory($dir);
                $release->delete();

                return null;
            }

            $this->shareUnchangedFiles($club, $dir, $manifest);

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
        } finally {
            Vite::useHotFile($hotFile);
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
    /**
     * Whether edits should produce a new version on their own: the club's domain shows the files, or a destination
     * takes each new version automatically.
     */
    /**
     * Something that ran on its own (a rebuild after an edit, the nightly one, an automatic push) failed: nobody is
     * watching the Publish screen, so everyone who may edit the club's website gets an email.
     */
    public static function tellWebsiteEditors(Club $club, string $heading, string $body): void
    {
        $club->users()->wherePivot('status', ClubMemberStatus::Active->value)->get()
            ->filter(fn (User $user) => ClubPermissions::allows($club, $user->pivot->role, 'edit_website'))
            ->each(fn (User $user) => Mail::to($user)->queue(new LodgeClaimMail(
                $heading, $body, 'Open the Publish screen', route('admin.pages.publish', ['clubSlug' => $club->slug]),
            )));
    }

    /**
     * Sent with every static page, wherever it is served (the .htaccess, our domain serving, the Caddy rule). Scripts
     * may only come from the site itself and the two analytics services the cookie notice can load, and forms and
     * requests may only go to the site and the platform, so injected content can't load or send anything elsewhere.
     * Images and embedded frames may come from any https address, since lodges add both.
     *
     * @return array<string, string>
     */
    public static function securityHeaders(): array
    {
        $platform = rtrim((string) config('app.url'), '/');

        return [
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' https://plausible.io https://www.googletagmanager.com",
                // The app's stylesheet imports the signature font from Google Fonts.
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "img-src 'self' data: https:",
                "font-src 'self' data: https://fonts.gstatic.com",
                "connect-src 'self' {$platform} https://plausible.io https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com",
                'frame-src https:',
                "form-action 'self' {$platform}",
                "base-uri 'self'",
                "object-src 'none'",
                "frame-ancestors 'self'",
            ]),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];
    }

    /**
     * Where the static website lives, for canonical links, link previews and the sitemap: the address the lodge gave
     * (when it hosts the files elsewhere), else its own domain. Null leaves links relative and skips the sitemap.
     */
    public static function siteAddress(Club $club): ?string
    {
        $address = $club->settings['static_site_url'] ?? null;

        return $address ?: ($club->custom_domain ? 'https://'.$club->custom_domain : null);
    }

    public static function keepsUpdated(Club $club): bool
    {
        return ! empty($club->settings['static_site_serve'])
            || SiteDeployTarget::where('club_id', $club->id)->get()->contains(fn (SiteDeployTarget $target) => ! empty($target->config['auto_send']));
    }

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
        // Inertia's local DevTools tags each response with a new id: never wanted in the files, and it would make every
        // build differ from the last.
        $html = (string) preg_replace('#<script data-inertia-devtools-id[^>]*>[^<]*</script>#', '', $html);
        // Laravel Herd adds its own editor script to pages it serves locally, which Chrome then copies.
        $html = (string) preg_replace('#<script[^>]*src="[^"]*/herd-studio/[^"]*"[^>]*></script>#', '', $html);

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
        $siteBase = self::siteAddress($club) ?? '';
        $serverOnly = implode('|', array_map(fn ($path) => preg_quote($path, '~'), self::SERVER_PATHS));

        // Link previews need a full address for their image.
        if ($siteBase !== '') {
            $html = (string) preg_replace('~(<meta (?:property|name)="(?:og|twitter):image" content=")/~', '$1'.$siteBase.'/', $html);
        }

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
        $siteBase = self::siteAddress($club);
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
        $lines = ['# Written by Club Manager with each published version of the website.', 'ErrorDocument 404 /404.html', 'Options -Indexes', '<IfModule mod_headers.c>'];

        foreach (self::securityHeaders() as $name => $value) {
            $lines[] = '    Header always set '.$name.' "'.$value.'"';
        }
        $lines[] = '    Header always set Strict-Transport-Security "max-age=31536000" env=HTTPS';
        $lines[] = '</IfModule>';

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

    /**
     * Copies only the built code and styles the public pages use, not the whole app (admin, accounting, members
     * screens): the two entry files, the page components the written pages name, and everything those import, lazily
     * loaded blocks included. The app entry's own lazy imports are every page of the app, so only those are skipped.
     */
    private function copyBuild(string $dir): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            throw new StaticSiteException('The app\'s code has not been built on this server yet (run "npm run build").');
        }

        $manifest = (array) json_decode((string) File::get(public_path('build/manifest.json')), true);
        $components = [];

        foreach (File::allFiles($dir) as $file) {
            if ($file->getExtension() === 'html' && preg_match_all('~"component":"([^"]+)"~', $file->getContents(), $matches)) {
                foreach ($matches[1] as $name) {
                    $components['resources/js/Pages/'.str_replace('\\/', '/', $name).'.vue'] = true;
                }
            }
        }

        $files = ['manifest.json' => true];
        $seen = [];
        $walk = function (string $key) use (&$walk, &$seen, &$files, $manifest) {
            if (isset($seen[$key]) || ! isset($manifest[$key])) {
                return;
            }
            $seen[$key] = true;
            $chunk = $manifest[$key];

            foreach ([$chunk['file'], ...($chunk['css'] ?? []), ...($chunk['assets'] ?? [])] as $file) {
                $files[$file] = true;
            }
            $lazy = array_filter($chunk['dynamicImports'] ?? [], fn (string $import) => ! (($chunk['isEntry'] ?? false) && str_starts_with($import, 'resources/js/Pages/')));

            foreach ([...($chunk['imports'] ?? []), ...$lazy] as $import) {
                $walk($import);
            }
        };

        foreach (['resources/js/app.js', 'resources/css/app.css', ...array_keys($components)] as $key) {
            $walk($key);
        }

        foreach (array_keys($files) as $file) {
            $from = public_path('build/'.$file);
            if (is_file($from)) {
                File::ensureDirectoryExists(dirname($dir.'/build/'.$file));
                File::copy($from, $dir.'/build/'.$file);
            }
        }
    }

    /**
     * A file that is the same as in the live version becomes a hard link to it, so unchanged images and code are kept
     * on disk once however many versions there are. Each version is still a whole folder of its own, and removing an
     * old one only removes its name for the file. Where links aren't possible (another disk), the copy stays.
     *
     * @param  array<string, string>  $manifest
     */
    private function shareUnchangedFiles(Club $club, string $dir, array $manifest): void
    {
        $live = realpath(SiteRelease::currentPath($club->id));

        if (! $live || $live === realpath($dir)) {
            return;
        }

        $previous = self::manifest(new SiteRelease(['club_id' => $club->id]), $live);

        foreach ($manifest as $path => $hash) {
            $old = $live.'/'.$path;
            $new = $dir.'/'.$path;

            if (($previous[$path] ?? null) !== $hash || ! is_file($old) || fileinode($old) === fileinode($new)) {
                continue;
            }

            $temporary = $new.'.link';
            if (@link($old, $temporary)) {
                rename($temporary, $new);
            }
        }
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
    public static function manifest(SiteRelease $release, ?string $folder = null): array
    {
        $path = ($folder ?? $release->path()).'/'.self::MANIFEST;

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }
}
