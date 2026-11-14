<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsWebsiteScreens;
use App\Http\Requests\PageAdmin\SaveSiteDeployTargetRequest;
use App\Jobs\BuildStaticSite;
use App\Jobs\DeployStaticSite;
use App\Models\Club;
use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use App\Services\StaticSite\StaticSiteBuilder;
use App\Support\ClubDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

/**
 * The website builder's Publish screen: build the club's website as static files (one folder per version), switch
 * which version is live, serve it on the lodge's domain, download it as a zip, and push it to an FTP(S) server or a
 * GitHub repository. Under /{club}/admin/pages, so it needs the edit_website capability.
 */
class StaticSiteController extends Controller
{
    use BuildsWebsiteScreens;

    public function show(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        return $this->builderScreen($clubSlug, 'publish', extra: ['publishing' => $this->publishing($club)]);
    }

    public function build(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        BuildStaticSite::dispatch($club, $request->user());

        return back()->with('success', 'Building a new version of the website. This takes a minute or two; refresh to see it.');
    }

    public function activate(string $clubSlug, int $releaseId, StaticSiteBuilder $builder): RedirectResponse
    {
        $release = $this->release($clubSlug, $releaseId);
        $builder->activate($release);

        return back()->with('success', 'Version '.$release->id.' is now the live version.');
    }

    public function download(string $clubSlug, int $releaseId): StreamedResponse
    {
        $release = $this->release($clubSlug, $releaseId);
        $name = Str::slug($release->club->name).'-website-'.$release->id.'.zip';

        return response()->streamDownload(function () use ($release) {
            $zip = new ZipStream(sendHttpHeaders: false);

            foreach (File::allFiles($release->path(), true) as $file) {
                $zip->addFileFromPath(fileName: str_replace('\\', '/', $file->getRelativePathname()), path: $file->getPathname());
            }

            $zip->finish();
        }, $name, ['Content-Type' => 'application/zip']);
    }

    public function serve(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $serve = $request->boolean('serve');

        abort_if($serve && ! SiteRelease::where('club_id', $club->id)->where('status', 'ready')->exists(), 422, 'Build a version of the website first.');

        $club->update(['settings' => array_merge($club->settings ?? [], ['static_site_serve' => $serve])]);

        return back()->with('success', $serve ? 'Your domain now shows the published version.' : 'Your domain now shows the website as you edit it.');
    }

    public function saveTarget(SaveSiteDeployTargetRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $data = $request->validated();
        $target = isset($data['id'])
            ? SiteDeployTarget::where('club_id', $club->id)->findOrFail($data['id'])
            : new SiteDeployTarget(['club_id' => $club->id]);

        $config = $data['type'] === 'github'
            ? ['repository' => $data['repository'], 'branch' => $data['branch'], 'token' => $data['token'] ?? null]
            : ['host' => strtolower($data['host']), 'port' => (int) ($data['port'] ?? 21), 'username' => $data['username'], 'password' => $data['password'] ?? null, 'path' => trim((string) ($data['path'] ?? ''), '/ '), 'insecure' => (bool) ($data['insecure'] ?? false)];

        // A blank password or token on an edit keeps the saved one (it is never sent back to the browser).
        $secret = $data['type'] === 'github' ? 'token' : 'password';
        if (blank($config[$secret]) && $target->exists && $target->type === $data['type']) {
            $config[$secret] = $target->config[$secret] ?? null;
        }

        $target->fill(['type' => $data['type'], 'name' => $data['name'], 'config' => $config])->save();

        return back()->with('success', 'Saved.');
    }

    public function destroyTarget(string $clubSlug, int $targetId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        SiteDeployTarget::where('club_id', $club->id)->findOrFail($targetId)->delete();

        return back()->with('success', 'Removed.');
    }

    public function deploy(Request $request, string $clubSlug, int $targetId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $target = SiteDeployTarget::where('club_id', $club->id)->findOrFail($targetId);
        $releaseId = $request->integer('release_id');

        $release = $releaseId
            ? $this->release($clubSlug, $releaseId)
            : SiteRelease::where('club_id', $club->id)->get()->first(fn (SiteRelease $release) => $release->isCurrent());

        abort_unless($release !== null, 422, 'Build a version of the website first.');

        $deploy = SiteDeploy::create([
            'club_id' => $club->id, 'site_deploy_target_id' => $target->id, 'site_release_id' => $release->id,
            'user_id' => $request->user()?->id, 'status' => 'queued',
        ]);
        DeployStaticSite::dispatch($deploy);

        return back()->with('success', 'Sending version '.$release->id.' to '.$target->name.'.');
    }

    private function release(string $clubSlug, int $releaseId): SiteRelease
    {
        $club = $this->findClub($clubSlug);
        $release = SiteRelease::where('club_id', $club->id)->where('status', 'ready')->findOrFail($releaseId);

        abort_unless(is_dir($release->path()), 404);

        return $release;
    }

    /**
     * @return array<string, mixed>
     */
    private function publishing(Club $club): array
    {
        $releases = SiteRelease::where('club_id', $club->id)->with('creator:id,name')->latest('id')->limit(10)->get();

        return [
            'serving' => ! empty($club->settings['static_site_serve']),
            'domain' => ClubDomain::isLive($club) ? $club->custom_domain : null,
            'releases' => $releases->map(fn (SiteRelease $release) => [
                'id' => $release->id,
                'status' => $release->status,
                'is_current' => $release->status === 'ready' && $release->isCurrent(),
                'page_count' => $release->page_count,
                'file_count' => $release->file_count,
                'bytes' => $release->bytes,
                'error' => $release->error,
                'created_by' => $release->creator?->name,
                'created_at' => $release->created_at?->toIso8601String(),
            ])->values(),
            'targets' => SiteDeployTarget::where('club_id', $club->id)->orderBy('name')->get()->map(fn (SiteDeployTarget $target) => [
                'id' => $target->id,
                'type' => $target->type,
                'name' => $target->name,
                // Everything but the password or token, which never leaves the server.
                ...collect($target->config)->except(['password', 'token'])->all(),
            ])->values(),
            'deploys' => SiteDeploy::where('club_id', $club->id)->with(['target:id,name', 'user:id,name'])->latest('id')->limit(15)->get()->map(fn (SiteDeploy $deploy) => [
                'id' => $deploy->id,
                'target' => $deploy->target?->name,
                'release_id' => $deploy->site_release_id,
                'status' => $deploy->status,
                'files_sent' => $deploy->files_sent,
                'files_deleted' => $deploy->files_deleted,
                'message' => $deploy->message,
                'user' => $deploy->user?->name,
                'created_at' => $deploy->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
