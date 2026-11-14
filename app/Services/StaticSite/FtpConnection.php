<?php

namespace App\Services\StaticSite;

use FTP\Connection;

/**
 * The few FTP calls a website push needs, over PHP's ftp extension. Explicit TLS (FTPS) unless the lodge chose plain
 * FTP; passive mode ignores the address the server hands back and reuses the one we connected to, so a hostile server
 * cannot point the data connection at another machine. Tests swap this class for a fake.
 */
class FtpConnection
{
    private ?Connection $ftp = null;

    public function connect(string $host, int $port, bool $tls, string $username, string $password): void
    {
        if (! function_exists('ftp_connect')) {
            throw new StaticSiteException('This server has no FTP support (the PHP ftp extension is missing).');
        }

        // Only a public address: a lodge's settings must not be able to reach machines inside our own network.
        $addresses = gethostbynamel($host) ?: [];
        $public = fn (string $ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($addresses === [] || in_array(false, array_map($public, $addresses), true)) {
            throw new StaticSiteException("{$host} is not a public internet address.");
        }

        $ftp = $tls ? @ftp_ssl_connect($addresses[0], $port, 30) : @ftp_connect($addresses[0], $port, 30);

        if (! $ftp) {
            throw new StaticSiteException("Could not connect to {$host} on port {$port}.");
        }

        if (! @ftp_login($ftp, $username, $password)) {
            @ftp_close($ftp);

            throw new StaticSiteException($tls
                ? 'The server refused the username or password (or does not offer FTP with TLS: ask your host, or tick plain FTP).'
                : 'The server refused the username or password.');
        }

        ftp_set_option($ftp, FTP_USEPASVADDRESS, false);
        ftp_pasv($ftp, true);
        $this->ftp = $ftp;
    }

    /** A file's contents, or null when it isn't there. */
    public function get(string $remotePath): ?string
    {
        $stream = fopen('php://temp', 'r+');

        if (! @ftp_fget($this->ftp, $stream, $remotePath, FTP_BINARY)) {
            fclose($stream);

            return null;
        }

        rewind($stream);
        $contents = (string) stream_get_contents($stream);
        fclose($stream);

        return $contents;
    }

    public function put(string $remotePath, string $localPath): void
    {
        $this->makeDirectory(dirname($remotePath));

        if (! @ftp_put($this->ftp, $remotePath, $localPath, FTP_BINARY)) {
            throw new StaticSiteException("Could not upload {$remotePath}. Check the folder exists and the account may write to it.");
        }
    }

    public function delete(string $remotePath): void
    {
        @ftp_delete($this->ftp, $remotePath);
    }

    public function close(): void
    {
        if ($this->ftp) {
            @ftp_close($this->ftp);
            $this->ftp = null;
        }
    }

    /** Creates a folder and any missing parents; folders that already exist are fine. */
    private function makeDirectory(string $directory): void
    {
        $path = '';

        foreach (array_filter(explode('/', $directory), fn (string $part) => $part !== '') as $part) {
            $path .= '/'.$part;
            if (! @ftp_chdir($this->ftp, $path)) {
                @ftp_mkdir($this->ftp, $path);
            }
        }
    }
}
