<?php

namespace App\Services\StaticSite;

/**
 * The few file calls a website push makes on the lodge's hosting (FtpConnection, SftpConnection, CpanelConnection).
 * Paths are absolute from the connection's root, like /public_html/about/index.html.
 */
interface RemoteFiles
{
    /** A file's contents, or null when it isn't there. */
    public function get(string $remotePath): ?string;

    /** Uploads a local file, creating any missing folders. */
    public function put(string $remotePath, string $localPath): void;

    /** Removes a file; one that is already gone is fine. */
    public function delete(string $remotePath): void;

    public function close(): void;
}
