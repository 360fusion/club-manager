<?php

use App\Support\SiteThemes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `website_theme` now holds a layout and a colour scheme ("{layout}:{scheme}"), so the bare
     * layout ids shipped before it split are paired with the colour scheme that reproduces the
     * look they had. `masonic` keeps its own fixed design and is left alone.
     */
    public function up(): void
    {
        $pairings = [
            'heritage' => 'banded:navy_gold',
            'editorial' => 'editorial:rust_stone',
            'bold' => 'bold:violet_coral',
        ];

        $valid = SiteThemes::keys();

        DB::table('clubs')->orderBy('id')->each(function ($club) use ($pairings, $valid) {
            $settings = json_decode($club->settings ?? '', true) ?: [];
            $theme = $settings['website_theme'] ?? null;

            if ($theme === null || in_array($theme, $valid, true)) {
                return;
            }

            $settings['website_theme'] = $pairings[$theme] ?? SiteThemes::DEFAULT;

            DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // Nothing to undo: the bare layout ids are no longer valid on the front end.
    }
};
