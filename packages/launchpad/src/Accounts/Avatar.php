<?php

namespace Launchpad\Accounts;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** A person's uploaded photo: cut to a square, shrunk, and saved again as a JPEG (so what was uploaded is never served as it came) */
final class Avatar
{
    private const SIZE = 256;

    private static function disk(): Filesystem
    {
        return Storage::disk((string) config('launchpad.accounts.avatar_disk', 'public'));
    }

    public static function url(?object $user): ?string
    {
        return $user && filled($user->avatar_path ?? null) ? self::disk()->url($user->avatar_path) : null;
    }

    /** @throws ValidationException */
    public static function store(object $user, UploadedFile $file): void
    {
        $path = 'avatars/'.$user->getKey().'-'.Str::random(10).'.jpg';
        self::disk()->put($path, self::square((string) file_get_contents($file->getRealPath())), 'public');

        self::remove($user);
        $user->forceFill(['avatar_path' => $path])->save();
    }

    public static function remove(object $user): void
    {
        if (filled($user->avatar_path ?? null)) {
            self::disk()->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }

    private static function square(string $bytes): string
    {
        $source = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;

        if ($source === false) {
            throw ValidationException::withMessages(['avatar' => __('That image could not be read. Try a JPEG or PNG.')]);
        }

        $side = min(imagesx($source), imagesy($source));
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), self::SIZE, self::SIZE, $side, $side);

        ob_start();
        imagejpeg($target, null, 85);

        return (string) ob_get_clean();
    }
}
