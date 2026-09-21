<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Accounting receipts were stored on the public disk, so anyone with the
     * link could read them. Move them to the private disk.
     */
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');

        DB::table('media')
            ->where('collection_name', 'accounting')
            ->where('disk', 'public')
            ->orderBy('id')
            ->each(function ($media) use ($public, $private) {
                $directory = (string) $media->id;

                foreach ($public->allFiles($directory) as $file) {
                    $private->put($file, $public->get($file));
                }

                DB::table('media')->where('id', $media->id)->update([
                    'disk' => 'local',
                    'conversions_disk' => 'local',
                ]);

                $public->deleteDirectory($directory);
            });
    }

    public function down(): void
    {
        // Files stay private; moving them back would re-expose them.
    }
};
