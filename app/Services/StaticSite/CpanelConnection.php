<?php

namespace App\Services\StaticSite;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Sends files to cPanel hosting through cPanel's own API with an API token (cPanel → Security → Manage API Tokens), so
 * a lodge needs no FTP account. Uploads use UAPI Fileman::upload_files, which also creates missing folders. cPanel's
 * API has no plain delete in UAPI, so removed files go to the account's trash through API 2 Fileman::fileop, where
 * they can be restored. The request goes to the public address checked first, with the host's name kept for TLS.
 */
class CpanelConnection implements RemoteFiles
{
    private ?PendingRequest $api = null;

    public function connect(string $host, int $port, string $username, string $token): void
    {
        $address = FtpConnection::publicAddress($host);

        $this->api = Http::baseUrl("https://{$host}:{$port}")
            ->withHeaders(['Authorization' => "cpanel {$username}:{$token}"])
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]]])
            ->acceptJson()
            // A redirect would send the request to an address that was never checked (an internal one, say).
            ->withoutRedirecting()
            ->timeout(60);
    }

    public function get(string $remotePath): ?string
    {
        $result = $this->call($this->api()->get('execute/Fileman/get_file_content', ['dir' => $this->relative(dirname($remotePath)), 'file' => basename($remotePath)]));

        return ($result['status'] ?? 0) == 1 ? (string) ($result['data']['content'] ?? '') : null;
    }

    public function put(string $remotePath, string $localPath): void
    {
        $result = $this->call($this->api()
            ->attach('file-1', (string) file_get_contents($localPath), basename($remotePath))
            ->post('execute/Fileman/upload_files?'.http_build_query(['dir' => $this->relative(dirname($remotePath)), 'overwrite' => 1])));

        if (($result['status'] ?? 0) != 1 || ($result['data']['succeeded'] ?? 0) < 1) {
            $reason = $result['data']['uploads'][0]['reason'] ?? implode(' ', (array) ($result['errors'] ?? []));
            throw new StaticSiteException("cPanel would not take {$remotePath}".($reason ? ": {$reason}" : '.'));
        }
    }

    public function delete(string $remotePath): void
    {
        $this->api()->get('json-api/cpanel', [
            'cpanel_jsonapi_apiversion' => 2, 'cpanel_jsonapi_module' => 'Fileman', 'cpanel_jsonapi_func' => 'fileop',
            'op' => 'trash', 'sourcefiles' => $this->relative($remotePath),
        ]);
    }

    public function close(): void
    {
        $this->api = null;
    }

    private function api(): PendingRequest
    {
        return clone ($this->api ?? throw new StaticSiteException('Not connected to cPanel.'));
    }

    /**
     * The UAPI result, or a readable error when cPanel refused the token or couldn't be reached.
     *
     * @return array<string, mixed>
     */
    private function call(Response $response): array
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new StaticSiteException('cPanel refused the username or API token.');
        }
        if (! $response->successful() || ! is_array($response->json('result'))) {
            throw new StaticSiteException('cPanel did not answer as expected (HTTP '.$response->status().'). Check the server name and port (usually 2083).');
        }

        return $response->json('result');
    }

    /** UAPI paths are relative to the account's home folder. */
    private function relative(string $path): string
    {
        return trim($path, '/');
    }
}
