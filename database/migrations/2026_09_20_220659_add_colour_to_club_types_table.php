<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each order gets a colour for its date tiles, calendar entries and chips.
     * Existing orders are filled in from their codes; anything else stays slate.
     * (The map is written out here so this migration never changes if the app's
     * default palette does.)
     */
    public function up(): void
    {
        Schema::table('club_types', function (Blueprint $table) {
            $table->string('colour', 20)->default('slate')->after('code');
        });

        $colours = [
            'craft_lodge' => 'sky',
            'royal_arch' => 'red',
            'mark_lodge' => 'orange',
            'royal_ark_mariner' => 'teal',
            'rose_croix' => 'pink',
            'knights_templar' => 'slate',
            'secret_monitor' => 'amber',
            'red_cross_constantine' => 'purple',
            'allied_masonic' => 'emerald',
            'cryptic_council' => 'indigo',
            'ktp_tabernacle' => 'stone',
            'sria_college' => 'violet',
            'royal_order_scotland' => 'cyan',
            'scarlet_cord' => 'lime',
            'rowing' => 'blue',
            'rugby' => 'green',
        ];

        foreach ($colours as $code => $colour) {
            DB::table('club_types')->where('code', $code)->update(['colour' => $colour]);
        }
    }

    public function down(): void
    {
        Schema::table('club_types', function (Blueprint $table) {
            $table->dropColumn('colour');
        });
    }
};
