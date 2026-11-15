<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Jobs\BuildStaticSite;
use App\Jobs\DeployStaticSite;
use App\Mail\LodgeClaimMail;
use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use App\Models\User;
use App\Services\StaticSite\FtpConnection;
use App\Services\StaticSite\SftpConnection;
use App\Services\StaticSite\SiteDeployer;
use App\Services\StaticSite\StaticPagePrerenderer;
use App\Services\StaticSite\StaticSiteBuilder;
use App\Services\StaticSite\StaticSiteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaticSiteTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('sites');
        Storage::fake('public');
        Vite::useHotFile(storage_path('framework/testing/no-vite-hot'));
        // Chrome is not run in tests: the page is kept as the server rendered it.
        $this->app->instance(StaticPagePrerenderer::class, new class extends StaticPagePrerenderer
        {
            public function render(string $url, string $fallbackHtml): string
            {
                return $fallbackHtml;
            }
        });

        $this->club = Club::factory()->create(['slug' => 'lodge-of-fraternity', 'email' => 'info@fraternity.example', 'custom_domain' => 'www.fraternity.example', 'domain_status' => 'active']);
        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);

        Storage::disk('public')->put('media/7/crest.png', 'png-bytes');
        Page::create(['club_id' => $this->club->id, 'title' => 'Our History', 'slug' => 'history', 'is_published' => true, 'blocks' => [
            ['id' => 'b1', 'type' => 'text', 'content' => '<p>Founded in 1899</p>'],
            ['id' => 'b2', 'type' => 'hero', 'title' => 'Hello', 'image_url' => rtrim(config('app.url'), '/').'/storage/media/7/crest.png'],
            ['id' => 'b3', 'type' => 'contact_form', 'recipient_email' => 'secretary@fraternity.example'],
        ]]);
        Page::create(['club_id' => $this->club->id, 'title' => 'Members area', 'slug' => 'members-area', 'is_published' => true, 'is_members_only' => true, 'blocks' => [['id' => 'm1', 'type' => 'text', 'content' => '<p>Secret minutes</p>']]]);
        Page::create(['club_id' => $this->club->id, 'title' => 'Draft', 'slug' => 'draft-page', 'is_published' => false, 'blocks' => []]);
    }

    private function build(): SiteRelease
    {
        return app(StaticSiteBuilder::class)->build($this->club->fresh(), $this->admin);
    }

    /** A version asked for and built by its queued job, as the worker would; null when an unchanged automatic one is dropped. */
    private function buildQueued(bool $automatic, ?User $by = null): ?SiteRelease
    {
        $release = SiteRelease::create(['club_id' => $this->club->id, 'created_by' => $by?->id, 'status' => 'queued', 'automatic' => $automatic, 'scheduled_for' => now()]);
        (new BuildStaticSite($release))->handle(app(StaticSiteBuilder::class));

        return $release->fresh();
    }

    private function file(SiteRelease $release, string $path): string
    {
        return (string) file_get_contents($release->path().'/'.$path);
    }

    public function test_a_build_writes_every_public_page_as_files_with_its_own_links_and_images(): void
    {
        $release = $this->build();

        $this->assertSame('ready', $release->status, (string) $release->error);
        $this->assertTrue($release->isCurrent());
        $this->assertFileExists($release->path().'/index.html');
        $this->assertFileExists($release->path().'/404.html');
        $this->assertFileExists($release->path().'/'.StaticSiteBuilder::MANIFEST);

        $history = $this->file($release, 'history/index.html');
        $this->assertStringContainsString('Founded in 1899', $history);
        $this->assertStringContainsString('"staticSite":{', $history);
        // The page's own address and image point at the static site; the image was copied in.
        $this->assertStringContainsString('"url":"\/history"', $history);
        $this->assertStringContainsString('\/storage\/media\/7\/crest.png', $history);
        $this->assertStringNotContainsString(preg_replace('#^https?://#', '', rtrim(config('app.url'), '/')).'\/storage', $history);
        $this->assertSame('png-bytes', $this->file($release, 'storage/media/7/crest.png'));
        $this->assertStringNotContainsString('csrf-token', $history);

        $this->assertStringContainsString('ErrorDocument 404 /404.html', $this->file($release, '.htaccess'));
        $this->assertStringContainsString('https://www.fraternity.example/history', $this->file($release, 'sitemap.xml'));
    }

    public function test_a_build_carries_only_the_public_sites_code(): void
    {
        $release = $this->build();
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        foreach (['resources/js/app.js', 'resources/js/Pages/Public/Site.vue'] as $needed) {
            $this->assertFileExists($release->path().'/build/'.$manifest[$needed]['file']);
        }
        $this->assertFileDoesNotExist($release->path().'/build/'.$manifest['resources/js/Pages/Admin/PageList.vue']['file']);

        // Every built file a page asks for is there.
        preg_match_all('~"/build/([^"]+)"~', $this->file($release, 'index.html'), $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $path) {
            $this->assertFileExists($release->path().'/build/'.$path);
        }
    }

    public function test_a_lodge_hosting_the_files_itself_sets_the_address_its_links_and_sitemap_use(): void
    {
        $this->club->update(['custom_domain' => null, 'domain_status' => 'pending']);

        $this->actingAs($this->admin)->post("/{$this->club->slug}/admin/pages/publish/address", ['site_url' => 'http://lodge.example.org/'])
            ->assertSessionHasErrors('site_url');
        $this->actingAs($this->admin)->post("/{$this->club->slug}/admin/pages/publish/address", ['site_url' => 'https://Lodge.example.org/anything?x=1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://lodge.example.org', $this->club->fresh()->settings['static_site_url']);

        $release = $this->build();
        $this->assertStringContainsString('<link rel="canonical" href="https://lodge.example.org/history">', $this->file($release, 'history/index.html'));
        $this->assertStringContainsString('https://lodge.example.org/history', $this->file($release, 'sitemap.xml'));
        $this->assertStringContainsString('Sitemap: https://lodge.example.org/sitemap.xml', $this->file($release, 'robots.txt'));
    }

    public function test_nothing_a_visitor_could_not_see_goes_into_a_build(): void
    {
        $post = ['club_id' => $this->club->id, 'author_id' => $this->admin->id, 'content' => '<p>News</p>', 'status' => 'published', 'published_at' => now()->subDay()];
        Post::create($post + ['title' => 'Open news', 'slug' => 'open-news', 'visibility' => Visibility::Public->value]);
        Post::create($post + ['title' => 'Lodge only', 'slug' => 'lodge-only', 'content' => '<p>Private news</p>', 'visibility' => Visibility::Club->value]);
        $release = $this->build();
        $all = implode("\n", array_map(fn ($path) => $this->file($release, $path), array_keys(StaticSiteBuilder::manifest($release))));

        $this->assertFileExists($release->path().'/news/open-news/index.html');
        $this->assertFileDoesNotExist($release->path().'/members-area/index.html');
        $this->assertFileDoesNotExist($release->path().'/draft-page/index.html');
        $this->assertFileDoesNotExist($release->path().'/news/lodge-only/index.html');
        $this->assertStringNotContainsString('Secret minutes', $all);
        $this->assertStringNotContainsString('Private news', $all);
        $this->assertStringNotContainsString('secretary@fraternity.example', $all);
        // Members-only pages are left on the platform, where a visitor signs in.
        $this->assertStringContainsString('"server_pages":["members-area"]', $this->file($release, 'index.html'));
    }

    public function test_redirects_go_into_the_htaccess_without_letting_a_value_add_a_directive(): void
    {
        ClubRedirect::create(['club_id' => $this->club->id, 'from_path' => '/old-page', 'to_url' => "/site/lodge-of-fraternity/history\nRewriteRule .* evil", 'is_permanent' => true]);

        $htaccess = $this->file($this->build(), '.htaccess');

        $this->assertStringContainsString('Redirect 301 "/old-page"', $htaccess);
        $this->assertStringNotContainsString("\nRewriteRule", $htaccess);
        // The security headers go with the files, and scripts can only come from the site and its analytics.
        $this->assertStringContainsString('Header always set Content-Security-Policy "default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://plausible.io', $htaccess);
        $this->assertStringContainsString('Header always set X-Frame-Options "SAMEORIGIN"', $htaccess);
    }

    public function test_each_build_is_a_version_and_an_older_one_can_be_made_live_again(): void
    {
        $first = $this->build();
        Page::where('slug', 'history')->update(['title' => 'Our Story']);
        $second = $this->build();

        $this->assertTrue($second->isCurrent());
        $this->assertFalse($first->fresh()->isCurrent());

        $this->actingAs($this->admin)->post(route('admin.pages.publish.activate', ['clubSlug' => $this->club->slug, 'releaseId' => $first->id]))->assertRedirect();

        $this->assertTrue($first->fresh()->isCurrent());
        $this->assertDirectoryExists($second->path());
    }

    public function test_a_new_version_shares_its_unchanged_files_with_the_live_one(): void
    {
        $first = $this->build();
        Page::where('slug', 'history')->update(['title' => 'Our Story']);
        $second = $this->build();

        $built = collect(File::allFiles($second->path().'/build'))->first();
        $relative = 'build/'.$built->getRelativePathname();
        $this->assertSame(fileinode($first->path().'/'.$relative), fileinode($second->path().'/'.$relative), 'unchanged code is one file on disk');
        $this->assertNotSame(fileinode($first->path().'/history/index.html'), fileinode($second->path().'/history/index.html'));
        $this->assertStringContainsString('Our Story', $this->file($second, 'history/index.html'));
        $this->assertStringNotContainsString('Our Story', $this->file($first, 'history/index.html'));
    }

    public function test_only_the_newest_versions_are_kept(): void
    {
        $releases = collect(range(1, SiteRelease::KEEP + 1))->map(fn () => $this->build());

        $this->assertDirectoryDoesNotExist($releases->first()->path());
        $this->assertNull(SiteRelease::find($releases->first()->id));
        $this->assertSame(SiteRelease::KEEP, SiteRelease::where('club_id', $this->club->id)->count());
    }

    public function test_the_domain_serves_the_live_version_from_its_files_and_the_rest_from_the_app(): void
    {
        $release = $this->build();
        Queue::fake(); // turning serving on queues a rebuild, which would replace the version this test edits
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], ['static_site_serve' => true])]);
        file_put_contents($release->path().'/history/index.html', 'STATIC COPY');
        // The web server's own way in: the domain's link leads to the live version.
        $this->assertFileExists(Storage::disk('sites')->path('hosts/www.fraternity.example/history/index.html'));

        $response = $this->get('http://www.fraternity.example/history');
        $response->assertOk();
        $this->assertSame('STATIC COPY', $response->streamedContent());
        $this->assertStringContainsString("script-src 'self'", (string) $response->headers->get('Content-Security-Policy'));

        // Not in the files: the app answers (a members-only page sends the visitor to sign in on the main address).
        $this->get('http://www.fraternity.example/members-area')->assertRedirect();
        // The manifest and .htaccess are never served, and a path can't climb out of the release.
        $this->get('http://www.fraternity.example/'.StaticSiteBuilder::MANIFEST)->assertNotFound();
        $this->get('http://www.fraternity.example/.htaccess')->assertNotFound();
        $this->get('http://www.fraternity.example/..%2F..%2Fcurrent/index.html')->assertNotFound();

        $this->club->update(['settings' => array_merge($this->club->settings, ['static_site_serve' => false])]);
        $this->assertFalse(is_link(Storage::disk('sites')->path('hosts/www.fraternity.example')));
        $this->get('http://www.fraternity.example/history')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Public/Site'));
    }

    public function test_the_static_sites_contact_form_is_answered_as_json_from_another_address(): void
    {
        $this->withHeaders(['Origin' => 'https://www.fraternity.example', 'Accept' => 'application/json'])
            ->postJson(route('public.site.contact_form', ['clubSlug' => $this->club->slug]), ['email' => 'visitor@example.com', 'message' => 'Hello', 'block_id' => 'b3'])
            ->assertOk()
            ->assertJson(['message' => 'Thank you! Your message has been sent successfully.'])
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->postJson(route('public.site.contact_form', ['clubSlug' => $this->club->slug]), ['email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_an_ftp_push_sends_changed_files_pages_last_and_only_removes_what_it_sent(): void
    {
        $ftp = new class extends FtpConnection
        {
            /** @var array<string, string> */
            public array $files = ['/public_html/keep-me.txt' => 'not ours'];

            public array $log = [];

            public function connect(string $host, int $port, bool $tls, string $username, string $password): void
            {
                $this->log[] = "connect {$host}:{$port} ".($tls ? 'tls' : 'plain');
            }

            public function get(string $remotePath): ?string
            {
                return $this->files[$remotePath] ?? null;
            }

            public function put(string $remotePath, string $localPath): void
            {
                $this->files[$remotePath] = (string) file_get_contents($localPath);
                $this->log[] = "put {$remotePath}";
            }

            public function delete(string $remotePath): void
            {
                unset($this->files[$remotePath]);
                $this->log[] = "delete {$remotePath}";
            }

            public function close(): void {}
        };
        $this->app->instance(FtpConnection::class, $ftp);

        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'ftps', 'name' => 'Host', 'config' => ['host' => 'ftp.fraternity.example', 'port' => 21, 'username' => 'web', 'password' => 'secret', 'path' => 'public_html']]);
        $push = function (SiteRelease $release) use ($target) {
            $deploy = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
            app(SiteDeployer::class)->run($deploy);

            return $deploy->fresh();
        };

        $first = $push($this->build());
        $this->assertSame('done', $first->status, (string) $first->message);
        $this->assertSame('connect ftp.fraternity.example:21 tls', $ftp->log[0]);
        $puts = array_values(array_filter($ftp->log, fn ($line) => str_starts_with($line, 'put ')));
        $firstHtml = array_search(true, array_map(fn ($line) => str_ends_with($line, '.html'), $puts), true);
        $this->assertSame([], array_filter(array_slice($puts, $firstHtml, -1), fn ($line) => ! str_ends_with($line, '.html')), 'Pages go after every other file');
        $this->assertSame('put /public_html/'.StaticSiteBuilder::MANIFEST, end($puts));

        // The next push: one page changed, one page gone.
        Page::where('slug', 'history')->update(['is_published' => false]);
        $ftp->log = [];
        $second = $push($this->build());

        $this->assertSame('done', $second->status);
        $this->assertContains('delete /public_html/history/index.html', $ftp->log);
        $this->assertArrayHasKey('/public_html/keep-me.txt', $ftp->files);
        $this->assertLessThan($first->files_sent, $second->files_sent);
    }

    public function test_an_sftp_push_remembers_the_servers_key_and_stops_if_it_changes(): void
    {
        $sftp = new class extends SftpConnection
        {
            public string $key = 'SHA256:first';

            public array $puts = [];

            public function connect(string $host, int $port, string $username, string $password, ?string $knownKey): string
            {
                if ($knownKey && $knownKey !== $this->key) {
                    throw new StaticSiteException('different server key');
                }

                return $this->key;
            }

            public function get(string $remotePath): ?string
            {
                return null;
            }

            public function put(string $remotePath, string $localPath): void
            {
                $this->puts[] = $remotePath;
            }

            public function delete(string $remotePath): void {}

            public function close(): void {}
        };
        $this->app->instance(SftpConnection::class, $sftp);
        $release = $this->build();
        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'sftp', 'name' => 'SSH', 'config' => ['host' => 'ssh.fraternity.example', 'port' => 22, 'username' => 'web', 'password' => 'secret', 'path' => 'www']]);
        $push = function () use ($target, $release) {
            $deploy = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
            app(SiteDeployer::class)->run($deploy);

            return $deploy->fresh();
        };

        $this->assertSame('done', $push()->status);
        $this->assertContains('/www/index.html', $sftp->puts);
        $this->assertSame('SHA256:first', $target->fresh()->config['host_key']);

        // Saving the destination again keeps the key; the server then answers with another one.
        $this->actingAs($this->admin)->post("/{$this->club->slug}/admin/pages/publish/targets", ['id' => $target->id, 'type' => 'sftp', 'name' => 'SSH', 'host' => 'ssh.fraternity.example', 'port' => 22, 'username' => 'web', 'path' => 'www'])
            ->assertSessionHasNoErrors();
        $this->assertSame('SHA256:first', $target->fresh()->config['host_key']);
        $this->assertSame('secret', $target->fresh()->config['password']);

        $sftp->key = 'SHA256:other';
        $failed = $push();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('different server key', $failed->message);
    }

    public function test_a_cpanel_push_uploads_through_the_api_with_the_token(): void
    {
        $release = $this->build();
        $refuse = false;
        Http::fake(function (HttpRequest $request) use (&$refuse) {
            return match (true) {
                $refuse => Http::response('', 401),
                str_contains($request->url(), 'get_file_content') => Http::response(['result' => ['status' => 0, 'errors' => ['No such file']]]),
                default => Http::response(['result' => ['status' => 1, 'data' => ['succeeded' => 1, 'uploads' => [['status' => 1]]]]]),
            };
        });

        // A public address written as numbers, so the test needs no DNS.
        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'cpanel', 'name' => 'cPanel', 'config' => ['host' => '8.8.8.8', 'port' => 2083, 'username' => 'lodge', 'token' => 'ABC123', 'path' => 'public_html']]);
        $deploy = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
        app(SiteDeployer::class)->run($deploy);

        $this->assertSame('done', $deploy->fresh()->status, (string) $deploy->fresh()->message);
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), 'https://8.8.8.8:2083/execute/Fileman/upload_files?dir=public_html%2Fhistory')
            && $request->hasHeader('Authorization', 'cpanel lodge:ABC123'));

        // A refused token reads as such.
        $refuse = true;
        $refused = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
        app(SiteDeployer::class)->run($refused);
        $this->assertSame('cPanel refused the username or API token.', $refused->fresh()->message);
    }

    public function test_a_github_push_makes_one_commit_with_only_the_changed_files(): void
    {
        $release = $this->build();
        $blobs = 0;

        Http::fake(function (HttpRequest $request) use (&$blobs) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                str_ends_with($path, '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head1']]),
                str_ends_with($path, '/git/commits/head1') => Http::response(['tree' => ['sha' => 'tree1']]),
                str_contains($path, '/contents/') => Http::response([], 404),
                str_ends_with($path, '/git/blobs') => Http::response(['sha' => 'blob'.(++$blobs)]),
                str_ends_with($path, '/git/trees') => Http::response(['sha' => 'tree2']),
                str_ends_with($path, '/git/commits') => Http::response(['sha' => 'commit2abcdef']),
                str_ends_with($path, '/git/refs/heads/main') => Http::response(['ref' => 'refs/heads/main']),
                default => Http::response([], 500),
            };
        });

        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'github', 'name' => 'Repo', 'config' => ['repository' => 'fraternity/website', 'branch' => 'main', 'cpanel_path' => 'public_html', 'token' => 'ghp_test']]);
        $deploy = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
        app(SiteDeployer::class)->run($deploy);

        // The cPanel deploy instructions go in with the site, and the manifest lists them so a later push can remove them.
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/git/trees') && collect($request['tree'])->contains('path', '.cpanel.yml'));
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/git/blobs')
            && str_contains((string) base64_decode($request['content']), 'export DEPLOYPATH=$HOME/public_html/'));

        $this->assertSame('done', $deploy->fresh()->status, (string) $deploy->fresh()->message);
        $this->assertSame('Committed commit2 to main.', $deploy->fresh()->message);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/git/trees') && $request['base_tree'] === 'tree1');
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/git/refs/heads/main') && $request->method() === 'PATCH' && $request['sha'] === 'commit2abcdef'
            && $request->hasHeader('Authorization', 'Bearer ghp_test'));
    }

    public function test_the_publish_screen_never_sends_back_a_password_or_token_and_a_blank_one_keeps_the_saved_one(): void
    {
        $this->actingAs($this->admin);
        $save = fn (array $data) => $this->post(route('admin.pages.publish.targets.save', ['clubSlug' => $this->club->slug]), $data);

        $save(['type' => 'ftps', 'name' => 'Host', 'host' => 'ftp.fraternity.example', 'port' => 21, 'username' => 'web', 'password' => 'first-secret', 'path' => 'public_html'])->assertSessionHasNoErrors();
        $target = SiteDeployTarget::firstOrFail();
        $save(['id' => $target->id, 'type' => 'ftps', 'name' => 'Host', 'host' => 'ftp.fraternity.example', 'username' => 'web2', 'password' => ''])->assertSessionHasNoErrors();

        $this->assertSame('first-secret', $target->fresh()->config['password']);
        $this->assertSame('web2', $target->fresh()->config['username']);

        $save(['type' => 'ftps', 'name' => 'Bad', 'host' => 'ftp://ftp.example', 'username' => 'x', 'password' => 'y'])->assertSessionHasErrors('host');
        $save(['type' => 'github', 'name' => 'Bad', 'repository' => 'not a repo', 'branch' => '../x', 'token' => 't'])->assertSessionHasErrors(['repository', 'branch']);

        $this->get(route('admin.pages.publish', ['clubSlug' => $this->club->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('selectedId', 'publish')->where('publishing.targets.0.username', 'web2')->missing('publishing.targets.0.password'));
    }

    public function test_building_and_pushing_are_queued_and_limited_to_the_clubs_own_versions(): void
    {
        Queue::fake();
        $this->actingAs($this->admin);

        $this->post(route('admin.pages.publish.build', ['clubSlug' => $this->club->slug]))->assertRedirect();
        Queue::assertPushed(BuildStaticSite::class, fn ($job) => $job->release->club_id === $this->club->id && $job->queue === 'sites' && $job->delay === null);
        SiteRelease::where('club_id', $this->club->id)->delete();

        $release = $this->build();
        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'github', 'name' => 'Repo', 'config' => ['repository' => 'a/b', 'branch' => 'main', 'token' => 't']]);
        $this->post(route('admin.pages.publish.targets.deploy', ['clubSlug' => $this->club->slug, 'targetId' => $target->id]))->assertRedirect();
        Queue::assertPushed(DeployStaticSite::class, fn ($job) => $job->deploy->site_release_id === $release->id);

        $other = Club::factory()->create();
        $otherRelease = SiteRelease::create(['club_id' => $other->id, 'status' => 'ready']);
        $this->post(route('admin.pages.publish.activate', ['clubSlug' => $this->club->slug, 'releaseId' => $otherRelease->id]))->assertNotFound();
        $this->get(route('admin.pages.publish.download', ['clubSlug' => $this->club->slug, 'releaseId' => $otherRelease->id]))->assertNotFound();
    }

    public function test_an_edit_rebuilds_the_site_only_when_the_domain_shows_the_published_files(): void
    {
        Queue::fake();
        $page = Page::where('club_id', $this->club->id)->firstOrFail();

        $page->update(['title' => 'Changed']);
        Queue::assertNotPushed(BuildStaticSite::class);

        $this->club->update(['settings' => array_merge($this->club->settings ?? [], ['static_site_serve' => true])]);
        $page->update(['title' => 'Changed again']);
        Queue::assertPushed(BuildStaticSite::class, fn ($job) => $job->release->club_id === $this->club->id && $job->delay !== null);

        // Nightly: one version for the club, now.
        SiteRelease::where('club_id', $this->club->id)->delete();
        Queue::fake();
        $this->artisan('app:rebuild-static-sites')->assertSuccessful();
        Queue::assertPushed(BuildStaticSite::class, fn ($job) => $job->delay === null);
        Queue::assertPushed(BuildStaticSite::class, 1);
    }

    public function test_edits_wait_until_editing_is_over_and_publish_now_takes_the_waiting_version_straight_away(): void
    {
        Queue::fake();
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], ['static_site_serve' => true])]);
        SiteRelease::where('club_id', $this->club->id)->delete();
        Queue::fake();
        $page = Page::where('club_id', $this->club->id)->where('slug', 'history')->firstOrFail();
        $waiting = fn () => SiteRelease::where('club_id', $this->club->id)->where('status', 'queued')->get();

        // An editing session: one version waits, built 30 minutes after the last edit (moved in 5-minute steps or more).
        $page->update(['title' => 'One']);
        $this->travel(10)->minutes();
        $page->update(['title' => 'Two']);
        $this->travel(1)->minutes();
        $page->update(['title' => 'Three']);
        $this->assertCount(1, $waiting());
        $release = $waiting()->first();
        $this->assertTrue($release->automatic);
        $this->assertEquals(now()->subMinute()->addMinutes(30)->startOfSecond(), $release->scheduled_for);
        Queue::assertPushed(BuildStaticSite::class, 2);

        // The first edit's job comes round early and leaves it for the later one.
        (new BuildStaticSite($release))->handle(app(StaticSiteBuilder::class));
        $this->assertSame('queued', $release->fresh()->status);

        $this->actingAs($this->admin)->get(route('admin.pages.publish', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('publishing.releases.0.status', 'queued')->where('publishing.releases.0.automatic', true)->where('publishing.releases.0.ahead', null));

        // Publish now: the same version becomes theirs and goes straight in the queue; pressing again adds nothing.
        $this->post(route('admin.pages.publish.build', ['clubSlug' => $this->club->slug]))->assertRedirect();
        $this->post(route('admin.pages.publish.build', ['clubSlug' => $this->club->slug]))->assertRedirect();
        $this->assertCount(1, $waiting());
        $release->refresh();
        $this->assertFalse($release->automatic);
        $this->assertSame($this->admin->id, $release->created_by);
        Queue::assertPushed(BuildStaticSite::class, 3);

        SiteRelease::create(['club_id' => Club::factory()->create()->id, 'status' => 'building']);
        $this->get(route('admin.pages.publish', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn (Assert $inertia) => $inertia->where('publishing.releases.0.ahead', 1));

        (new BuildStaticSite($release))->handle(app(StaticSiteBuilder::class));
        $release->refresh();
        $this->assertSame('ready', $release->status);
        $this->assertTrue($release->isCurrent());
        $this->assertSame($release->pages_total, $release->page_count);
        $this->assertGreaterThan(1, $release->page_count);

        // A late copy of the job finds it built and does nothing; a job the worker gave up on doesn't stay "building".
        (new BuildStaticSite($release))->handle(app(StaticSiteBuilder::class));
        $this->assertSame(1, SiteRelease::where('club_id', $this->club->id)->count());

        $stuck = SiteRelease::create(['club_id' => $this->club->id, 'status' => 'building']);
        (new BuildStaticSite($stuck))->failed(new \RuntimeException('timed out'));
        $this->assertSame('failed', $stuck->fresh()->status);
        $this->assertTrue($release->fresh()->isCurrent());
    }

    public function test_a_destination_set_to_automatic_gets_each_new_version_and_an_unchanged_automatic_build_is_dropped(): void
    {
        $first = $this->build();
        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'github', 'name' => 'Repo', 'config' => ['repository' => 'a/b', 'branch' => 'main', 'token' => 't', 'auto_send' => true]]);
        Queue::fake([DeployStaticSite::class]);

        // Nothing changed since the live version: thrown away, nothing sent.
        $this->assertNull($this->buildQueued(automatic: true));
        $this->assertSame([$first->id], SiteRelease::where('club_id', $this->club->id)->pluck('id')->all());
        $this->assertTrue($first->isCurrent());
        Queue::assertNothingPushed();

        // An edit: the destination isn't the domain, but takes new versions, so the edit rebuilds and sends.
        Queue::fake([BuildStaticSite::class, DeployStaticSite::class]);
        Page::where('club_id', $this->club->id)->where('slug', 'history')->firstOrFail()->update(['title' => 'Our Long History']);
        $queued = SiteRelease::where('club_id', $this->club->id)->where('status', 'queued')->firstOrFail();
        $this->assertTrue($queued->automatic);

        $this->travel(BuildStaticSite::AFTER_EDITS_MINUTES + 1)->minutes();
        (new BuildStaticSite($queued))->handle(app(StaticSiteBuilder::class));
        $second = $queued->fresh();
        $this->assertSame('ready', $second->status);
        $this->assertNotSame($first->id, $second->id);
        Queue::assertPushed(DeployStaticSite::class, fn ($job) => $job->deploy->site_release_id === $second->id && $job->deploy->site_deploy_target_id === $target->id);
    }

    public function test_the_websites_editors_are_emailed_when_an_automatic_build_or_push_fails(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        Page::where('club_id', $this->club->id)->update(['is_published' => false]);
        Post::where('club_id', $this->club->id)->update(['status' => 'draft']);

        // A build someone pressed shows its error on the screen; only one that ran on its own sends an email.
        $this->buildQueued(automatic: false, by: $this->admin);
        Mail::assertNothingQueued();

        $this->buildQueued(automatic: true);
        Mail::assertQueued(LodgeClaimMail::class, fn ($mail) => $mail->hasTo($this->admin->email) && str_contains($mail->body, 'no public pages'));
        Mail::assertNotQueued(LodgeClaimMail::class, fn ($mail) => $mail->hasTo($member->email));
    }

    public function test_a_version_can_be_previewed_under_the_publish_screen_before_it_goes_live(): void
    {
        $release = $this->build();
        $prefix = "/{$this->club->slug}/admin/pages/publish/releases/{$release->id}/preview";

        $page = $this->actingAs($this->admin)->get($prefix.'/history/');
        $page->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = $page->getContent();
        $this->assertStringContainsString('src="'.$prefix.'/build/', $html);
        $this->assertStringContainsString('"staticSite":{"base":"'.str_replace('/', '\\/', $prefix).'"', $html);
        $this->assertStringContainsString('href="'.$prefix.'/history"', $html, 'the canonical link to the domain points at the preview');
        $this->assertStringNotContainsString($prefix.$prefix, $html);

        $this->actingAs($this->admin)->get($prefix.'/no-such-page')->assertNotFound();
        $this->actingAs($this->admin)->get($prefix.'/'.StaticSiteBuilder::MANIFEST)->assertNotFound();
        $this->actingAs($this->admin)->get($prefix.'/..%2F..%2Fcurrent/index.html')->assertNotFound();

        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($member)->get($prefix.'/history/')->assertForbidden();
    }

    public function test_a_version_downloads_as_a_zip(): void
    {
        $release = $this->build();

        $response = $this->actingAs($this->admin)->get(route('admin.pages.publish.download', ['clubSlug' => $this->club->slug, 'releaseId' => $release->id]));

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $zip = $response->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $zip);
        $this->assertStringContainsString('history/index.html', $zip);
    }
}
