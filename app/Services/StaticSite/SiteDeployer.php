<?php

namespace App\Services\StaticSite;

use App\Models\SiteDeploy;
use App\Models\SiteDeployTarget;
use App\Models\SiteRelease;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends a built release of a club's website to one of its targets, recording the outcome on the SiteDeploy row.
 * Only changed files travel: the release's manifest is compared with the one sent last time, which is stored with the
 * files. Pages go last, so a visitor never gets a new page before the CSS and images it needs. Only files a previous
 * push sent are ever deleted, so anything else on the server or in the repository is left alone.
 */
class SiteDeployer
{
    public function __construct(private FtpConnection $ftp) {}

    public function run(SiteDeploy $deploy): void
    {
        $deploy->update(['status' => 'running']);

        try {
            $release = $deploy->release;
            $target = $deploy->target;

            if (! $release || $release->status !== 'ready' || ! is_dir($release->path()) || ! $target) {
                throw new StaticSiteException('That version of the website or the destination no longer exists.');
            }

            [$sent, $deleted, $message] = $target->type === 'github'
                ? $this->toGitHub($release, $target)
                : $this->toFtp($release, $target);

            $deploy->update(['status' => 'done', 'files_sent' => $sent, 'files_deleted' => $deleted, 'message' => $message]);
        } catch (Throwable $e) {
            if (! $e instanceof StaticSiteException) {
                report($e);
            }
            $deploy->update(['status' => 'failed', 'message' => mb_substr($e instanceof StaticSiteException ? $e->getMessage() : 'The push failed: '.$e->getMessage(), 0, 1000)]);
        }
    }

    /**
     * @return array{0: int, 1: int, 2: string}
     */
    private function toFtp(SiteRelease $release, SiteDeployTarget $target): array
    {
        $config = $target->config;
        $root = '/'.trim((string) ($config['path'] ?? ''), '/');
        $root = $root === '/' ? '' : $root;

        $this->ftp->connect((string) $config['host'], (int) ($config['port'] ?? 21), empty($config['insecure']), (string) $config['username'], (string) $config['password']);

        try {
            $local = StaticSiteBuilder::manifest($release);
            $remote = json_decode((string) $this->ftp->get($root.'/'.StaticSiteBuilder::MANIFEST), true) ?: [];
            [$changed, $removed] = $this->diff($local, is_array($remote) ? $remote : []);

            foreach ($changed as $path) {
                $this->ftp->put($root.'/'.$path, $release->path().'/'.$path);
            }
            foreach ($removed as $path) {
                $this->ftp->delete($root.'/'.$path);
            }
            // Last, so an interrupted push is simply redone next time.
            $this->ftp->put($root.'/'.StaticSiteBuilder::MANIFEST, $release->path().'/'.StaticSiteBuilder::MANIFEST);
        } finally {
            $this->ftp->close();
        }

        return [count($changed), count($removed), $changed === [] && $removed === [] ? 'Already up to date.' : 'Website updated.'];
    }

    /**
     * One commit with the changed files on the chosen branch, through GitHub's Git Data API.
     *
     * @return array{0: int, 1: int, 2: string}
     */
    private function toGitHub(SiteRelease $release, SiteDeployTarget $target): array
    {
        $config = $target->config;
        $branch = (string) ($config['branch'] ?? 'main');
        $api = Http::withToken((string) $config['token'])->acceptJson()->timeout(60)
            ->baseUrl('https://api.github.com/repos/'.$config['repository']);

        $head = $this->github($api->get('git/ref/heads/'.rawurlencode($branch)), [404, 409]);

        if (! $head) {
            // A brand-new repository has no commits, and Git's API can't add to it until it has one.
            $this->github($api->put('contents/README.md', ['message' => 'Start the website repository', 'branch' => $branch, 'content' => base64_encode("Website published by Club Manager.\n")]));
            $head = $this->github($api->get('git/ref/heads/'.rawurlencode($branch)));
        }

        $headSha = $head['object']['sha'];
        $baseTree = $this->github($api->get('git/commits/'.$headSha))['tree']['sha'];

        $remoteManifest = $this->github($api->get('contents/'.StaticSiteBuilder::MANIFEST, ['ref' => $branch]), [404]);
        $remote = $remoteManifest ? (json_decode((string) base64_decode((string) $remoteManifest['content']), true) ?: []) : [];

        $local = StaticSiteBuilder::manifest($release);
        [$changed, $removed] = $this->diff($local, $remote);

        if ($changed === [] && $removed === []) {
            return [0, 0, 'Already up to date.'];
        }

        $tree = [];
        foreach ([...$changed, StaticSiteBuilder::MANIFEST] as $path) {
            $blob = $this->github($api->post('git/blobs', ['content' => base64_encode((string) file_get_contents($release->path().'/'.$path)), 'encoding' => 'base64']));
            $tree[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => $blob['sha']];
        }
        foreach ($removed as $path) {
            $tree[] = ['path' => $path, 'mode' => '100644', 'type' => 'blob', 'sha' => null];
        }

        $newTree = $this->github($api->post('git/trees', ['base_tree' => $baseTree, 'tree' => $tree]));
        $commit = $this->github($api->post('git/commits', [
            'message' => 'Publish website version '.$release->id,
            'tree' => $newTree['sha'],
            'parents' => [$headSha],
        ]));
        $this->github($api->patch('git/refs/heads/'.rawurlencode($branch), ['sha' => $commit['sha']]));

        return [count($changed), count($removed), 'Committed '.substr((string) $commit['sha'], 0, 7).' to '.$branch.'.'];
    }

    /**
     * A GitHub answer as an array, null for one of the allowed "not there" statuses, or a readable error.
     *
     * @param  list<int>  $missing
     * @return array<string, mixed>|null
     */
    private function github(Response $response, array $missing = []): ?array
    {
        if (in_array($response->status(), $missing, true)) {
            return null;
        }

        if ($response->failed()) {
            throw new StaticSiteException(match ($response->status()) {
                401 => 'GitHub refused the token. It may have expired or been revoked.',
                403, 404 => 'GitHub would not let the token write to that repository. It needs "Contents: read and write" on it.',
                409, 422 => 'GitHub refused the change: '.($response->json('message') ?: 'check the branch name.'),
                default => 'GitHub answered '.$response->status().'.',
            });
        }

        return (array) $response->json();
    }

    /**
     * What to send (assets first, pages last) and what to remove (only files a previous push sent).
     *
     * @param  array<string, string>  $local
     * @param  array<array-key, mixed>  $remote  read back from the server or repository, so not trusted to be well formed
     * @return array{0: list<string>, 1: list<string>}
     */
    private function diff(array $local, array $remote): array
    {
        unset($local[StaticSiteBuilder::MANIFEST], $remote[StaticSiteBuilder::MANIFEST]);

        $changed = array_keys(array_filter($local, fn (string $hash, string $path) => ($remote[$path] ?? null) !== $hash, ARRAY_FILTER_USE_BOTH));
        usort($changed, fn (string $a, string $b) => [str_ends_with($a, '.html'), $a] <=> [str_ends_with($b, '.html'), $b]);

        $removed = array_values(array_filter(array_keys($remote), fn ($path) => is_string($path) && ! isset($local[$path]) && ! str_contains($path, '..')));

        return [$changed, $removed];
    }
}
