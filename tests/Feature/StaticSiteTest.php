<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Jobs\BuildStaticSite;
use App\Jobs\DeployStaticSite;
use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\Page;
use App\Models\Post;
use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use App\Models\User;
use App\Services\StaticSite\FtpConnection;
use App\Services\StaticSite\SiteDeployer;
use App\Services\StaticSite\StaticPagePrerenderer;
use App\Services\StaticSite\StaticSiteBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
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
        $this->club->update(['settings' => array_merge($this->club->settings ?? [], ['static_site_serve' => true])]);
        file_put_contents($release->path().'/history/index.html', 'STATIC COPY');

        $response = $this->get('http://www.fraternity.example/history');
        $response->assertOk();
        $this->assertSame('STATIC COPY', $response->streamedContent());

        // Not in the files: the app answers (a members-only page sends the visitor to sign in on the main address).
        $this->get('http://www.fraternity.example/members-area')->assertRedirect();
        // The manifest and .htaccess are never served, and a path can't climb out of the release.
        $this->get('http://www.fraternity.example/'.StaticSiteBuilder::MANIFEST)->assertNotFound();
        $this->get('http://www.fraternity.example/.htaccess')->assertNotFound();
        $this->get('http://www.fraternity.example/..%2F..%2Fcurrent/index.html')->assertNotFound();

        $this->club->update(['settings' => array_merge($this->club->settings, ['static_site_serve' => false])]);
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

        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'github', 'name' => 'Repo', 'config' => ['repository' => 'fraternity/website', 'branch' => 'main', 'token' => 'ghp_test']]);
        $deploy = SiteDeploy::create(['club_id' => $this->club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id, 'status' => 'queued']);
        app(SiteDeployer::class)->run($deploy);

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
        Queue::assertPushed(BuildStaticSite::class, fn ($job) => $job->club->is($this->club));

        $release = $this->build();
        $target = SiteDeployTarget::create(['club_id' => $this->club->id, 'type' => 'github', 'name' => 'Repo', 'config' => ['repository' => 'a/b', 'branch' => 'main', 'token' => 't']]);
        $this->post(route('admin.pages.publish.targets.deploy', ['clubSlug' => $this->club->slug, 'targetId' => $target->id]))->assertRedirect();
        Queue::assertPushed(DeployStaticSite::class, fn ($job) => $job->deploy->site_release_id === $release->id);

        $other = Club::factory()->create();
        $otherRelease = SiteRelease::create(['club_id' => $other->id, 'status' => 'ready']);
        $this->post(route('admin.pages.publish.activate', ['clubSlug' => $this->club->slug, 'releaseId' => $otherRelease->id]))->assertNotFound();
        $this->get(route('admin.pages.publish.download', ['clubSlug' => $this->club->slug, 'releaseId' => $otherRelease->id]))->assertNotFound();
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
