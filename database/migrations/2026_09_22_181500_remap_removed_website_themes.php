<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The website builder's theme catalogue was cut down to `masonic`, `heritage`, `editorial` and `bold`
     * (see resources/js/Support/siteThemes.js). Any club still holding one of the retired theme ids
     * (classic, obsidian, minimal, vibrant, light_navy, executive_light, masonic_light, warm_light) falls
     * back to `editorial`, the new default for clubs that aren't lodges. `masonic` is left untouched.
     */
    public function up(): void
    {
        $validThemes = ['masonic', 'heritage', 'editorial', 'bold'];

        DB::table('clubs')->orderBy('id')->each(function ($club) use ($validThemes) {
            $settings = json_decode($club->settings ?? '', true) ?: [];

            if (empty($settings['website_theme']) || in_array($settings['website_theme'], $validThemes, true)) {
                return;
            }

            $settings['website_theme'] = 'editorial';

            DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // Nothing to undo: the retired theme ids no longer exist in the front end.
    }
};
