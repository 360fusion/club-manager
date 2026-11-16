<?php

namespace App\Support;

use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Symfony\Component\Mime\MimeTypes;

/**
 * Every file added to the media library is stored under an extension that matches what the file really is. The
 * upload rules check the content, but the uploader's own extension was kept, so a text or GIF file named
 * `notes.shtml` passed and was then served as a web page from our own address. Bound in AppServiceProvider.
 */
class SafeFileAdder extends FileAdder
{
    /** Extensions web servers serve as pages or scripts. */
    private const ACTIVE_EXTENSIONS = ['htm', 'html', 'shtml', 'xhtml', 'xht', 'svg', 'svgz', 'xml', 'xsl', 'xslt', 'js', 'mjs', 'php', 'phtml', 'phar', 'cgi', 'pl', 'asp', 'aspx', 'jsp'];

    public function defaultSanitizer(string $fileName): string
    {
        $sanitized = parent::defaultSanitizer($fileName);

        if (! is_file($this->pathToFile)) {
            return $sanitized;
        }

        return self::withExtensionForContent($sanitized, (string) mime_content_type($this->pathToFile));
    }

    /**
     * Keep the name's extension when it is one the detected type uses (photo.jpeg stays), otherwise use that type's own.
     */
    public static function withExtensionForContent(string $fileName, string $mimeType): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // An empty or unrecognised binary file cannot carry a script; it keeps its name unless that name would be served as a page.
        if (in_array($mimeType, ['application/x-empty', 'inode/x-empty', 'application/octet-stream'], true) && ! in_array($extension, self::ACTIVE_EXTENSIONS, true)) {
            return $fileName;
        }

        $allowed = MimeTypes::getDefault()->getExtensions($mimeType) ?: ['bin'];
        // Spreadsheets saved as text are detected as plain text; their own extensions are just as harmless to serve.
        if ($mimeType === 'text/plain') {
            $allowed = [...$allowed, 'csv', 'tsv', 'md'];
        }
        if (in_array($extension, $allowed, true)) {
            return $fileName;
        }

        $base = pathinfo($fileName, PATHINFO_FILENAME) ?: 'file';

        return $base.'.'.$allowed[0];
    }
}
