<?php

namespace App\Support;

/**
 * One place for what may be uploaded, so every form applies the same limits.
 *
 * SVG and HTML are never accepted: files are served from the same origin as the
 * app, so a script inside one would run with the visitor's session.
 */
class UploadRules
{
    public const IMAGE_TYPES = 'jpg,jpeg,png,gif,webp';

    public const DOCUMENT_TYPES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt';

    /** Widest or tallest image accepted, so a small file cannot decode into gigabytes of memory. */
    public const MAX_IMAGE_SIDE = 6000;

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
