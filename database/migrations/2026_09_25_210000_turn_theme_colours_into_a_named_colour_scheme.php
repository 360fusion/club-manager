<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * A lodge's own colours used to sit on top of whichever colour scheme was chosen (`theme_colors`), which made the
     * chosen scheme look ignored. They are now a named colour scheme beside the built-in ones, so the lodge that had
     * set them keeps its look: the scheme is added as "Custom colours" and the site switched to it.
     */
    public function up(): void
    {
        DB::table('clubs')->orderBy('id')->each(function ($club) {
            $settings = json_decode($club->settings ?? '', true) ?: [];
            $colours = $settings['theme_colors'] ?? null;

            if (! is_array($colours)) {
                return;
            }

            unset($settings['theme_colors']);

            if (is_string($colours['primary'] ?? null) && is_string($colours['accent'] ?? null)) {
                $id = 'custom-'.Str::lower(Str::random(8));
                $settings['custom_color_schemes'] = array_merge($settings['custom_color_schemes'] ?? [], [[
                    'id' => $id,
                    'name' => 'Custom colours',
                    'primary' => $colours['primary'],
                    'accent' => $colours['accent'],
                ]]);

                $layout = explode(':', (string) ($settings['website_theme'] ?? ''))[0];

                if ($layout !== '' && $layout !== 'masonic') {
                    $settings['website_theme'] = $layout.':'.$id;
                }
            }

            DB::table('clubs')->where('id', $club->id)->update(['settings' => json_encode($settings)]);
        });
    }

    public function down(): void
    {
        // Nothing to undo: the colours live on as a colour scheme.
    }
};
