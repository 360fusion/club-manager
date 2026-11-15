<?php

namespace App\Services\StaticSite;

use phpseclib4\Net\SFTP;
use Throwable;

/**
 * SFTP (file transfer over SSH) with a password, through phpseclib. The server's key is remembered on the first push
 * and must match on every later one, so a push can't be quietly redirected to another machine. Tests swap this class
 * for a fake.
 */
class SftpConnection implements RemoteFiles
{
    private ?SFTP $sftp = null;

    /**
     * @param  string|null  $knownKey  the fingerprint saved from an earlier push, if any
     * @return string the server key's fingerprint, to save on the destination
     */
    public function connect(string $host, int $port, string $username, string $password, ?string $knownKey): string
    {
        $address = FtpConnection::publicAddress($host);

        try {
            $sftp = new SFTP($address, $port, 30);
            $key = (string) $sftp->getServerPublicHostKey();
        } catch (Throwable) {
            throw new StaticSiteException("Could not connect to {$host} on port {$port} with SFTP.");
        }

        $fingerprint = 'SHA256:'.rtrim(base64_encode(hash('sha256', (string) base64_decode(explode(' ', $key)[1] ?? ''), true)), '=');

        if ($knownKey && ! hash_equals($knownKey, $fingerprint)) {
            throw new StaticSiteException("{$host} answered with a different server key from the one it had before ({$fingerprint} instead of {$knownKey}). If your host has moved the website to a new server, remove this destination and add it again.");
        }

        try {
            $loggedIn = $sftp->login($username, $password);
        } catch (Throwable) {
            $loggedIn = false;
        }

        if (! $loggedIn) {
            throw new StaticSiteException('The server refused the username or password.');
        }

        $this->sftp = $sftp;

        return $fingerprint;
    }

    public function get(string $remotePath): ?string
    {
        try {
            $contents = $this->sftp?->get($remotePath);
        } catch (Throwable) {
            return null;
        }

        return is_string($contents) ? $contents : null;
    }

    public function put(string $remotePath, string $localPath): void
    {
        try {
            $this->sftp?->mkdir(dirname($remotePath), -1, true);
        } catch (Throwable) {
            // It already exists; a folder that can't be made shows up as the upload failing.
        }

        try {
            $this->sftp?->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);
        } catch (Throwable) {
            throw new StaticSiteException("Could not upload {$remotePath}. Check the folder exists and the account may write to it.");
        }
    }

    public function delete(string $remotePath): void
    {
        try {
            $this->sftp?->delete($remotePath, false);
        } catch (Throwable) {
            // Already gone.
        }
    }

    public function close(): void
    {
        $this->sftp?->disconnect();
        $this->sftp = null;
    }
}
