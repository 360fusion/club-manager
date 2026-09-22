<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * One place for what may be uploaded, so every form applies the same limits.
 *
 * SVG and HTML are never accepted: files are served from the same origin as the
 * app, so a script inside one would run with the visitor's session.
 */
class UploadRules
{
    public const IMAGE_TYPES = 'jpg,jpeg,png,gif,webp';

    public const DOCUMENT_TYPES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,rtf,zip';

    /** Widest or tallest image accepted, so a small file cannot decode into gigabytes of memory. */
    public const MAX_IMAGE_SIDE = 6000;

    /** Extensions that must never be stored, wherever they appear in a filename (e.g. avatar.php.png). */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'phar', 'phtml', 'php3', 'php4', 'php5', 'py', 'pl', 'cgi',
        'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm', 'vbs', 'jar', 'htaccess',
    ];

    /**
     * Content-level checks beyond MIME/extension validation: blocks disguised
     * executables and, defense-in-depth, script-bearing SVGs. Returns an error
     * message, or null if the file is safe to store.
     */
    public static function assertSafeUpload(UploadedFile $file): ?string
    {
        $segments = explode('.', strtolower($file->getClientOriginalName()));
        foreach ($segments as $segment) {
            if (in_array($segment, self::DANGEROUS_EXTENSIONS, true)) {
                return 'File contains forbidden file extensions.';
            }
        }

        if (strtolower((string) $file->getClientOriginalExtension()) === 'svg') {
            $content = file_get_contents($file->getRealPath());
            if ($content !== false && preg_match('/<script|javascript:|onload=|onerror=|onclick=/i', $content)) {
                return 'SVG contains forbidden script execution tags.';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function image(int $maxKilobytes, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'mimes:'.self::IMAGE_TYPES,
            'max:'.$maxKilobytes,
            'dimensions:max_width='.self::MAX_IMAGE_SIDE.',max_height='.self::MAX_IMAGE_SIDE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function attachment(int $maxKilobytes): array
    {
        return ['nullable', 'file', 'mimes:'.self::DOCUMENT_TYPES.','.self::IMAGE_TYPES, 'max:'.$maxKilobytes];
    }
}
