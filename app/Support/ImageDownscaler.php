<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Shrinks large camera photos in place before they are stored, keeping the
 * quality high enough for the web (JPEG/WebP 85) while cutting file size.
 */
class ImageDownscaler
{
    public static function apply(?UploadedFile $file, int $maxSide = 1920): void
    {
        if ($file === null || ! $file->isValid()) {
            return;
        }

        $mime = $file->getMimeType();

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return;
        }

        $path = $file->getRealPath();
        $size = @getimagesize($path);

        if (! $size || $size[0] < 1 || $size[1] < 1) {
            return;
        }

        [$width, $height] = $size;

        if ($width <= $maxSide && $height <= $maxSide) {
            return;
        }

        // Decoding needs about 4 bytes a pixel (GD truecolor). If that would not fit in memory, keep the original.
        if (! self::fitsInMemory($width * $height * 4)) {
            return;
        }

        $scale = $maxSide / max($width, $height);
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };

        if (! $source) {
            return;
        }

        $target = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime !== 'image/jpeg') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        match ($mime) {
            'image/jpeg' => imagejpeg($target, $path, 85),
            'image/png' => imagepng($target, $path, 8),
            default => imagewebp($target, $path, 85),
        };

        imagedestroy($source);
        imagedestroy($target);
        clearstatcache(true, $path);
    }

    private static function fitsInMemory(int $bytes): bool
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return true;
        }

        $unit = strtoupper(substr($limit, -1));
        $value = (int) $limit;
        $limitBytes = match ($unit) {
            'G' => $value * 1024 ** 3,
            'M' => $value * 1024 ** 2,
            'K' => $value * 1024,
            default => $value,
        };

        return $limitBytes - memory_get_usage(true) > $bytes + 8 * 1024 ** 2;
    }
}
