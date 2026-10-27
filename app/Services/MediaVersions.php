<?php

namespace App\Services;

use App\Models\Media;
use App\Models\MediaVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Keeps a media item's version history: snapshots the old file before it is overwritten.
 */
class MediaVersions
{
    /**
     * Copy a media item's current file content into version history before it is overwritten.
     */
    public function snapshot(Media $media, ?string $note, ?int $userId): MediaVersion
    {
        $versionDisk = Storage::disk('local');
        $versionDir = "media-versions/{$media->model_id}/{$media->id}";
        $versionDisk->makeDirectory($versionDir);

        $extension = pathinfo($media->file_name, PATHINFO_EXTENSION);
        $versionFileName = now()->format('YmdHis').'_'.Str::random(8).($extension ? ".{$extension}" : '');
        $versionPath = "{$versionDir}/{$versionFileName}";

        $versionDisk->put($versionPath, Storage::disk($media->disk)->get($media->getPathRelativeToRoot()));

        $version = MediaVersion::create([
            'media_id' => $media->id,
            'club_id' => $media->model_id,
            'disk' => 'local',
            'path' => $versionPath,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'note' => $note,
            'created_by' => $userId,
        ]);

        // Only the newest few versions are kept, so repeated edits cannot fill the disk.
        $media->pruneVersions();

        return $version;
    }

    /**
     * Overwrite a media item's file content in place, keeping its id, after saving
     * the current content to version history.
     */
    public function replace(Media $media, UploadedFile $file, string $note, ?int $userId): Media
    {
        $this->snapshot($media, $note, $userId);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $file->getClientOriginalName());
        $liveDisk = Storage::disk($media->disk);
        $directory = dirname($media->getPathRelativeToRoot());

        $liveDisk->deleteDirectory($directory);
        $liveDisk->makeDirectory($directory);
        $liveDisk->putFileAs($directory, $file, $safeName);

        $media->file_name = $safeName;
        $media->mime_type = $file->getMimeType();
        $media->size = $file->getSize();
        $media->save();

        return $media->fresh();
    }
}
