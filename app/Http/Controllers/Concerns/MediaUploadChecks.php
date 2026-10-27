<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Club;
use App\Support\MediaFolders;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

trait MediaUploadChecks
{
    /**
     * Returns a 422 JSON response if storing `$incomingBytes` more would exceed the
     * club's storage quota, or null if there's room.
     */
    protected function assertQuotaAvailable(Club $club, int $incomingBytes): ?JsonResponse
    {
        if (! $club->hasStorageFor($incomingBytes)) {
            $quota = (int) $club->storageQuotaBytes();
            $used = $club->storageUsedBytes();

            return response()->json([
                'success' => false,
                'message' => 'Storage quota exceeded. This club has used '.Club::formatStorageBytes($used).' of '.Club::formatStorageBytes($quota).' available.',
                'storage' => $club->storageSummary(),
            ], 422);
        }

        return null;
    }

    /**
     * The set of folder identifiers a file may be uploaded/moved into: the fixed
     * system folders plus this club's own custom folders.
     */
    protected function folderRule(Club $club): In
    {
        return Rule::in([...MediaFolders::SYSTEM, ...$club->mediaFolders()->pluck('slug')->all()]);
    }
}
